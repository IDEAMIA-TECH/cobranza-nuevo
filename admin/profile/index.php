<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();

if (!isAdmin()) {
    header("Location: ../../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener datos del usuario
$query = "SELECT u.*, a.avatar_path 
          FROM users u 
          LEFT JOIN admin_profiles a ON a.user_id = u.id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Error de validación CSRF');
        }

        $db->beginTransaction();
        
        // Actualizar email si cambió
        if ($user['email'] !== $_POST['email']) {
            $stmt = $db->prepare("UPDATE users SET email = :email WHERE id = :id");
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
        }
        
        // Actualizar contraseña si se proporcionó una nueva
        if (!empty($_POST['new_password'])) {
            if (empty($_POST['current_password'])) {
                throw new Exception('Debe proporcionar la contraseña actual');
            }
            
            // Verificar contraseña actual
            if (!password_verify($_POST['current_password'], $user['password'])) {
                throw new Exception('La contraseña actual es incorrecta');
            }
            
            // Validar nueva contraseña
            if (!SecurityHelper::validatePassword($_POST['new_password'])) {
                throw new Exception('La nueva contraseña no cumple con los requisitos de seguridad');
            }
            
            $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->bindParam(':password', $new_password_hash);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
        }
        
        // Procesar avatar si se subió uno nuevo
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['avatar']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Formato de imagen no permitido');
            }
            
            $upload_path = __DIR__ . '/../../assets/img/avatars/';
            if (!file_exists($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            
            $new_filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path . $new_filename)) {
                // Eliminar avatar anterior si existe
                if (!empty($user['avatar_path'])) {
                    @unlink(__DIR__ . '/../../' . $user['avatar_path']);
                }
                
                // Actualizar o insertar en admin_profiles
                $avatar_path = '/assets/img/avatars/' . $new_filename;
                if (empty($user['avatar_path'])) {
                    $stmt = $db->prepare("INSERT INTO admin_profiles (user_id, avatar_path) VALUES (:user_id, :path)");
                } else {
                    $stmt = $db->prepare("UPDATE admin_profiles SET avatar_path = :path WHERE user_id = :user_id");
                }
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':path', $avatar_path);
                $stmt->execute();
            }
        }
        
        $db->commit();
        $success = "Perfil actualizado correctamente";
        
        // Recargar datos del usuario
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Mi Perfil</h2>
        <div class="header-actions">
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="profile-container">
        <div class="avatar-section">
            <div class="current-avatar">
                <?php if (!empty($user['avatar_path'])): ?>
                    <img src="<?php echo getBaseUrl() . $user['avatar_path']; ?>" 
                         alt="Avatar" class="avatar-img">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data" class="avatar-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label for="avatar" class="btn btn-outline">
                        <i class="fas fa-camera"></i> Cambiar Foto
                    </label>
                    <input type="file" id="avatar" name="avatar" accept="image/*" class="hidden-input">
                </div>
            </form>
        </div>

        <form method="POST" class="profile-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-section">
                <h3>Cambiar Contraseña</h3>
                
                <div class="form-group">
                    <label for="current_password">Contraseña Actual:</label>
                    <input type="password" id="current_password" name="current_password">
                </div>

                <div class="form-group">
                    <label for="new_password">Nueva Contraseña:</label>
                    <input type="password" id="new_password" name="new_password">
                    <small class="form-help">
                        La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, 
                        minúsculas, números y caracteres especiales
                    </small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.profile-container {
    max-width: 600px;
    margin: 0 auto;
}

.avatar-section {
    text-align: center;
    margin-bottom: 2rem;
}

.current-avatar {
    width: 150px;
    height: 150px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    overflow: hidden;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    font-size: 4rem;
    color: #ccc;
}

.hidden-input {
    display: none;
}

.form-section {
    margin: 2rem 0;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

.form-help {
    display: block;
    margin-top: 0.5rem;
    color: #666;
    font-size: 0.9em;
}
</style>

<script>
document.getElementById('avatar').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        this.form.submit();
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 