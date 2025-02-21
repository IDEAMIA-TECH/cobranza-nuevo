<?php
ob_start();

require_once '../../includes/functions.php';
require_once '../../config/database.php';
require_once '../../includes/Mailer.php';
require_once '../../includes/SecurityHelper.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
if (!isAdmin()) {
    header("Location: ../../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verificar si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['csrf_token'])) {
    try {
        if (!SecurityHelper::validateCSRF()) {
            throw new Exception("Error de validación CSRF");
        }

        // Verificar si se proporcionó un ID
        if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
            throw new Exception("ID de usuario no válido");
        }

        $user_id = (int)$_POST['user_id'];
        
        $db->beginTransaction();
        
        // Obtener información del cliente
        $query = "SELECT c.*, u.email 
                  FROM clients c 
                  JOIN users u ON u.id = c.user_id 
                  WHERE u.id = :user_id AND u.status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Cliente no encontrado o ya procesado");
        }
        
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Actualizar estado del usuario
        $query = "UPDATE users SET status = 'inactive' WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, related_id) 
                  VALUES (:admin_id, 'reject_client', :description, :ip_address, :client_id)";
        $description = "Rechazó el registro del cliente: " . $client['business_name'];
        $stmt = $db->prepare($query);
        $stmt->bindParam(":admin_id", $_SESSION['user_id']);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
        $stmt->bindParam(":client_id", $client['id']);
        $stmt->execute();
        
        // Enviar correo de rechazo
        $mailer = new Mailer();
        $mailer->sendAccountRejectionEmail($client['email'], $client['business_name']);
        
        $db->commit();
        
        $_SESSION['success'] = "Cliente rechazado exitosamente";
        header("Location: pending.php");
        exit();
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header("Location: pending.php");
        exit();
    }
}

// Si no es POST o no hay token CSRF, redirigir a la página de pendientes
header("Location: pending.php");
exit();

ob_end_flush();
?> 