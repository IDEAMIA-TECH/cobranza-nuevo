<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';
$token = isset($_GET['token']) ? cleanInput($_GET['token']) : '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verificar si el token es válido y no ha expirado
$query = "SELECT pr.*, u.email 
          FROM password_resets pr 
          JOIN users u ON u.id = pr.user_id 
          WHERE pr.token = :token 
          AND pr.expiry > NOW() 
          AND pr.used = FALSE";
$stmt = $db->prepare($query);
$stmt->bindParam(":token", $token);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    $error = "El enlace de recuperación no es válido o ha expirado.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    try {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validar que las contraseñas coincidan
        if ($password !== $confirm_password) {
            throw new Exception('Las contraseñas no coinciden');
        }
        
        // Validar requisitos de contraseña
        if (!SecurityHelper::validatePassword($password)) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas, números y caracteres especiales');
        }
        
        $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $db->beginTransaction();
        
        // Actualizar contraseña
        $query = "UPDATE users 
                  SET password = :password 
                  WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":password", password_hash($password, PASSWORD_DEFAULT));
        $stmt->bindParam(":user_id", $reset_data['user_id']);
        $stmt->execute();
        
        // Marcar token como usado
        $query = "UPDATE password_resets 
                  SET used = TRUE 
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $reset_data['id']);
        $stmt->execute();
        
        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                  VALUES (:user_id, 'reset_password', :description, :ip_address)";
        $description = "Restableció su contraseña";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $reset_data['user_id']);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        
        $db->commit();
        $success = "Tu contraseña ha sido actualizada exitosamente.";
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <h2>Restablecer Contraseña</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
            <p>Serás redirigido al login en unos segundos...</p>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 3000);
        </script>
    <?php elseif (empty($error)): ?>
        <form method="POST" class="auth-form">
            <?php echo SecurityHelper::getCSRFTokenField(); ?>
            
            <div class="form-group">
                <label for="password">Nueva Contraseña:</label>
                <input type="password" id="password" name="password" required minlength="8">
                <small>Mínimo 8 caracteres, incluir mayúsculas, minúsculas, números y caracteres especiales</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
                <a href="login.php" class="btn btn-link">Volver al Login</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?> 