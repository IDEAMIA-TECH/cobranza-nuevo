<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/Mailer.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $email = cleanInput($_POST['email']);
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Verificar si el email existe
        $query = "SELECT u.id, u.email, u.role,
                  CASE 
                      WHEN u.role = 'admin' THEN 'Administrador'
                      ELSE c.business_name 
                  END as business_name
                  FROM users u 
                  LEFT JOIN clients c ON c.user_id = u.id 
                  WHERE u.email = :email 
                  AND u.status != 'inactive'";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generar token único
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Guardar token en la base de datos
            $query = "INSERT INTO password_resets (user_id, token, expiry) 
                      VALUES (:user_id, :token, :expiry)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $user['id']);
            $stmt->bindParam(":token", $token);
            $stmt->bindParam(":expiry", $expiry);
            $stmt->execute();
            
            // Enviar correo con link de recuperación
            $mailer = new Mailer();
            $mailer->sendPasswordResetEmail($user['email'], $user['business_name'], $token);
            
            $success = "Se ha enviado un enlace de recuperación a tu correo electrónico.";
        } else {
            $error = "Si el correo existe en nuestro sistema, recibirás un enlace de recuperación.";
        }
        
    } catch (Exception $e) {
        $error = "Ocurrió un error al procesar la solicitud.";
        error_log($e->getMessage());
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <h2>Recuperar Contraseña</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php else: ?>
        <form method="POST" class="auth-form">
            <?php echo SecurityHelper::getCSRFTokenField(); ?>
            
            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Enviar Link de Recuperación</button>
                <a href="login.php" class="btn btn-link">Volver al Login</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?> 