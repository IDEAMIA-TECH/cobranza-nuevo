<?php
// Asegurarse que no haya salida antes de los headers
ob_start();

require_once 'includes/functions.php';
require_once 'config/database.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $email = cleanInput($_POST['email']);
        $password = $_POST['password'];
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Verificar intentos de login
        if (!SecurityHelper::checkLoginAttempts($email, $db)) {
            throw new Exception('Demasiados intentos fallidos. Por favor, espere 15 minutos.');
        }
        
        // Buscar usuario
        $query = "SELECT u.*, 
                  CASE 
                      WHEN u.role = 'admin' THEN 'Administrador'
                      ELSE c.business_name 
                  END as business_name
                  FROM users u 
                  LEFT JOIN clients c ON c.user_id = u.id 
                  WHERE u.email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    // Actualizar intentos de login
                    SecurityHelper::updateLoginAttempts($email, true, $db);
                    
                    // Iniciar sesión
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['business_name'] = $user['business_name'];
                    $_SESSION['is_admin'] = ($user['role'] === 'admin');
                    $_SESSION['last_activity'] = time();
                    
                    // Registrar actividad
                    $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                             VALUES (:user_id, 'login', :description, :ip_address)";
                    $description = "Inicio de sesión exitoso";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":user_id", $user['id']);
                    $stmt->bindParam(":description", $description);
                    $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
                    $stmt->execute();
                    
                    // Redirigir según el rol
                    $redirect = $user['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php';
                    header("Location: $redirect");
                    exit();
                } else {
                    throw new Exception('Tu cuenta está pendiente de activación.');
                }
            } else {
                SecurityHelper::updateLoginAttempts($email, false, $db);
                throw new Exception('Credenciales incorrectas.');
            }
        } else {
            // Por seguridad, actualizar intentos aunque el usuario no exista
            SecurityHelper::updateLoginAttempts($email, false, $db);
            throw new Exception('Credenciales incorrectas.');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Incluir el header después de todas las redirecciones posibles
include 'includes/header.php';
?>

<div class="auth-container">
    <h2>Iniciar Sesión</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" class="auth-form">
        <?php echo SecurityHelper::getCSRFTokenField(); ?>
        
        <div class="form-group">
            <label for="email">Correo Electrónico:</label>
            <input type="email" id="email" name="email" required 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
            <a href="forgot-password.php" class="btn btn-link">¿Olvidaste tu contraseña?</a>
        </div>
        
        <div class="auth-links">
            <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a></p>
        </div>
    </form>
</div>

<?php 
include 'includes/footer.php';
ob_end_flush();
?> 