<?php
// Asegurarse que no haya salida antes de los headers
ob_start();

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/Settings.php';

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
                if ($user['status'] === 'pending') {
                    $error = "Su cuenta está pendiente de aprobación.";
                } else if ($user['status'] === 'inactive') {
                    $error = "Su cuenta está inactiva. Contacte al administrador.";
                } else {
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
                    if ($_SESSION['is_admin']) {
                        header("Location: admin/dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit();
                }
            } else {
                SecurityHelper::updateLoginAttempts($email, false, $db);
                $error = "Credenciales incorrectas.";
            }
        } else {
            // Por seguridad, actualizar intentos aunque el usuario no exista
            SecurityHelper::updateLoginAttempts($email, false, $db);
            $error = "Credenciales incorrectas.";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Cobranza</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/styles.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            text-align: center;
        }

        .logo-section {
            margin-bottom: 2rem;
        }

        .logo-section img {
            max-width: 120px;
            height: auto;
            margin-bottom: 1rem;
        }

        .login-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .login-card h2 {
            margin: 0 0 1.5rem;
            color: #333;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .forgot-password {
            display: block;
            text-align: right;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            margin: -1rem 0 1rem;
        }

        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-login:hover {
            background: #5a32a3;
        }

        .register-link {
            margin-top: 1.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <img src="<?php echo getBaseUrl(); ?>/assets/img/logo.png" 
                 alt="Sistema de Cobranza">
        </div>

        <div class="login-card">
            <h2>Iniciar Sesión</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <?php echo SecurityHelper::getCSRFTokenField(); ?>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <a href="forgot-password.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
                
                <button type="submit" class="btn-login">Login</button>
                
                <div class="register-link">
                    ¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php 
ob_end_flush();
?> 