<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

// Verificar que no sea administrador
if (isAdmin()) {
    header("Location: ../../admin/dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Obtener datos del cliente
$query = "SELECT c.*, u.email 
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Error de validación CSRF');
        }

        $db->beginTransaction();

        // Actualizar datos del cliente
        $query = "UPDATE clients SET 
                  phone = :phone,
                  contact_name = :contact_name,
                  street = :street,
                  ext_number = :ext_number,
                  int_number = :int_number,
                  neighborhood = :neighborhood,
                  city = :city,
                  state = :state,
                  zip_code = :zip_code
                  WHERE user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':phone', $_POST['phone']);
        $stmt->bindParam(':contact_name', $_POST['contact_name']);
        $stmt->bindParam(':street', $_POST['street']);
        $stmt->bindParam(':ext_number', $_POST['ext_number']);
        $stmt->bindParam(':int_number', $_POST['int_number']);
        $stmt->bindParam(':neighborhood', $_POST['neighborhood']);
        $stmt->bindParam(':city', $_POST['city']);
        $stmt->bindParam(':state', $_POST['state']);
        $stmt->bindParam(':zip_code', $_POST['zip_code']);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();

        // Actualizar contraseña si se proporcionó una nueva
        if (!empty($_POST['new_password'])) {
            if (empty($_POST['current_password'])) {
                throw new Exception('Debe proporcionar la contraseña actual');
            }
            
            // Verificar contraseña actual
            $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
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

        $db->commit();
        $success = "Perfil actualizado correctamente";

        // Recargar datos del cliente
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->execute();
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

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
            <a href="../dashboard/" class="btn btn-secondary">
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
        <form method="POST" class="profile-form">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-section">
                <h3>Información de la Empresa</h3>
                
                <div class="form-group">
                    <label>Razón Social:</label>
                    <input type="text" value="<?php echo htmlspecialchars($client['business_name']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>RFC:</label>
                    <input type="text" value="<?php echo htmlspecialchars($client['rfc']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Régimen Fiscal:</label>
                    <input type="text" value="<?php echo htmlspecialchars($client['tax_regime']); ?>" readonly>
                </div>
            </div>

            <div class="form-section">
                <h3>Información de Contacto</h3>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" value="<?php echo htmlspecialchars($client['email']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label for="phone">Teléfono:</label>
                    <input type="text" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($client['phone']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="contact_name">Nombre de Contacto:</label>
                    <input type="text" id="contact_name" name="contact_name" 
                           value="<?php echo htmlspecialchars($client['contact_name']); ?>" required>
                </div>
            </div>

            <div class="form-section">
                <h3>Dirección Fiscal</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="street">Calle:</label>
                        <input type="text" id="street" name="street" 
                               value="<?php echo htmlspecialchars($client['street']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="ext_number">Número Exterior:</label>
                        <input type="text" id="ext_number" name="ext_number" 
                               value="<?php echo htmlspecialchars($client['ext_number']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="int_number">Número Interior:</label>
                        <input type="text" id="int_number" name="int_number" 
                               value="<?php echo htmlspecialchars($client['int_number']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="neighborhood">Colonia:</label>
                        <input type="text" id="neighborhood" name="neighborhood" 
                               value="<?php echo htmlspecialchars($client['neighborhood']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="zip_code">Código Postal:</label>
                        <input type="text" id="zip_code" name="zip_code" 
                               value="<?php echo htmlspecialchars($client['zip_code']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">Ciudad:</label>
                        <input type="text" id="city" name="city" 
                               value="<?php echo htmlspecialchars($client['city']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="state">Estado:</label>
                        <input type="text" id="state" name="state" 
                               value="<?php echo htmlspecialchars($client['state']); ?>" required>
                    </div>
                </div>
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
    max-width: 800px;
    margin: 0 auto;
}

.form-section {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-section h3 {
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.form-help {
    display: block;
    margin-top: 0.5rem;
    color: #666;
    font-size: 0.9em;
}
</style>

<?php include '../../includes/footer.php'; ?> 