<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';

$database = new Database();
$db = $database->getConnection();
$mailer = new Mailer();

try {
    $db->beginTransaction();

    // Obtener facturas que vencen hoy o están vencidas y no han sido notificadas
    $query = "SELECT i.*, 
                     c.business_name, c.rfc,
                     u.email,
                     DATEDIFF(CURDATE(), i.due_date) as days_overdue
              FROM invoices i 
              JOIN clients c ON c.id = i.client_id 
              JOIN users u ON u.id = c.user_id 
              WHERE i.status = 'pending' 
              AND i.due_date <= CURDATE()
              AND NOT EXISTS (
                  SELECT 1 FROM notifications n 
                  WHERE n.invoice_id = i.id 
                  AND n.type = 'overdue'
                  AND n.sent_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              )";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $overdue_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($overdue_invoices as $invoice) {
        // Enviar correo al cliente
        $mailer->sendOverdueInvoiceNotification(
            $invoice['email'],
            $invoice['business_name'],
            $invoice['invoice_number'],
            $invoice['total_amount'],
            $invoice['due_date'],
            $invoice['days_overdue']
        );

        // Registrar la notificación
        $query = "INSERT INTO notifications (client_id, invoice_id, type, message, status) 
                  VALUES (:client_id, :invoice_id, 'overdue', :message, 'sent')";
        $stmt = $db->prepare($query);
        $message = "Factura {$invoice['invoice_number']} vencida hace {$invoice['days_overdue']} días";
        $stmt->bindParam(":client_id", $invoice['client_id']);
        $stmt->bindParam(":invoice_id", $invoice['id']);
        $stmt->bindParam(":message", $message);
        $stmt->execute();

        // Actualizar estado de la factura
        $query = "UPDATE invoices SET status = 'overdue' WHERE id = :invoice_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":invoice_id", $invoice['id']);
        $stmt->execute();

        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, related_id) 
                  VALUES (1, 'overdue_notification', :description, 'SYSTEM', :client_id)";
        $description = "Notificación de vencimiento enviada para factura: {$invoice['invoice_number']}";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":client_id", $invoice['client_id']);
        $stmt->execute();
    }

    $db->commit();
    echo "Proceso completado. " . count($overdue_invoices) . " facturas procesadas.\n";

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
} 