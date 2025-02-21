<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';
require_once '../../includes/SecurityHelper.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
redirectIfNotAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar CSRF
        if (!isset($_POST['csrf_token']) || !SecurityHelper::validateCSRFToken($_POST['csrf_token'])) {
            throw new Exception('Error de validación de seguridad');
        }

        // Validar datos
        if (!isset($_POST['client_id']) || !is_numeric($_POST['client_id'])) {
            throw new Exception('ID de cliente inválido');
        }

        $client_id = (int)$_POST['client_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status === 'active' ? 'inactive' : 'active';

        $database = new Database();
        $db = $database->getConnection();
        $db->beginTransaction();

        // Obtener información del cliente
        $query = "SELECT c.*, u.id as user_id, u.email 
                 FROM clients c 
                 JOIN users u ON c.user_id = u.id 
                 WHERE c.id = :client_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':client_id', $client_id);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new Exception('Cliente no encontrado');
        }

        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        // Actualizar estado del usuario
        $query = "UPDATE users SET status = :status WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':user_id', $client['user_id']);
        $stmt->execute();

        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                 VALUES (:user_id, :action, :description, :ip_address)";
        $action = $new_status === 'active' ? 'activate_client' : 'deactivate_client';
        $description = ($new_status === 'active' ? 'Activó' : 'Desactivó') . 
                      " al cliente: " . $client['business_name'];

        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->execute();

        $db->commit();

        $_SESSION['success'] = "Estado del cliente actualizado correctamente";
        header('Location: index.php');
        exit();

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
} 