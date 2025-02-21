<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Mailer.php';

// Verificar que el script solo se ejecute desde la línea de comandos
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse desde la línea de comandos');
}

$database = new Database();
$db = $database->getConnection();

// Obtener configuración del sistema
$query = "SELECT * FROM system_settings";
$stmt = $db->query($query);
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Verificar si las notificaciones están habilitadas
if (($settings['enable_email_notifications'] ?? '1') !== '1') {
    die("Las notificaciones por correo están deshabilitadas\n");
}

// Configurar el mailer
$mailer = new Mailer([
    'host' => $settings['smtp_host'],
    'port' => $settings['smtp_port'],
    'user' => $settings['smtp_user'],
    'password' => $settings['smtp_password'],
    'from_email' => $settings['smtp_from_email'],
    'from_name' => $settings['smtp_from_name']
]);

// Obtener días de recordatorio
$reminder_days = array_map('intval', explode(',', $settings['reminder_days']));
$overdue_interval = (int)($settings['overdue_interval'] ?? 5);

// Procesar recordatorios de vencimiento próximo
foreach ($reminder_days as $days) {
    $query = "SELECT i.*, c.business_name, c.email, c.contact_name
              FROM invoices i 
              JOIN clients c ON c.id = i.client_id 
              WHERE i.status = 'pending' 
              AND DATE(i.due_date) = DATE_ADD(CURDATE(), INTERVAL :days DAY)
              AND NOT EXISTS (
                  SELECT 1 FROM notification_logs 
                  WHERE invoice_id = i.id 
                  AND notification_type = 'reminder' 
                  AND days_before = :days
              )";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":days", $days);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($invoices as $invoice) {
        try {
            // Preparar el contenido del correo
            $subject = "Recordatorio de Factura por Vencer - {$invoice['invoice_number']}";
            $body = "Estimado/a {$invoice['contact_name']},\n\n";
            $body .= "Le recordamos que la factura {$invoice['invoice_number']} vencerá en {$days} días.\n\n";
            $body .= "Detalles de la factura:\n";
            $body .= "- Monto: $" . number_format($invoice['total_amount'], 2) . "\n";
            $body .= "- Fecha de vencimiento: " . date('d/m/Y', strtotime($invoice['due_date'])) . "\n\n";
            $body .= "Por favor, realice el pago antes de la fecha de vencimiento.\n\n";
            $body .= "Saludos cordiales,\n";
            $body .= $settings['company_name'];
            
            // Enviar correo
            $mailer->send($invoice['email'], $subject, $body);
            
            // Registrar notificación
            $query = "INSERT INTO notification_logs 
                     (invoice_id, notification_type, days_before, sent_at) 
                     VALUES (:invoice_id, 'reminder', :days, NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":invoice_id", $invoice['id']);
            $stmt->bindParam(":days", $days);
            $stmt->execute();
            
            echo "Recordatorio enviado para factura {$invoice['invoice_number']}\n";
            
        } catch (Exception $e) {
            echo "Error al enviar recordatorio para factura {$invoice['invoice_number']}: {$e->getMessage()}\n";
        }
    }
}

// Procesar notificaciones de facturas vencidas
$query = "SELECT i.*, c.business_name, c.email, c.contact_name,
          DATEDIFF(CURDATE(), i.due_date) as days_overdue
          FROM invoices i 
          JOIN clients c ON c.id = i.client_id 
          WHERE i.status = 'overdue'
          AND MOD(DATEDIFF(CURDATE(), i.due_date), :interval) = 0
          AND NOT EXISTS (
              SELECT 1 FROM notification_logs 
              WHERE invoice_id = i.id 
              AND notification_type = 'overdue'
              AND DATE(sent_at) = CURDATE()
          )";

$stmt = $db->prepare($query);
$stmt->bindParam(":interval", $overdue_interval);
$stmt->execute();
$overdue_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($overdue_invoices as $invoice) {
    try {
        // Preparar el contenido del correo
        $subject = "Aviso de Factura Vencida - {$invoice['invoice_number']}";
        $body = "Estimado/a {$invoice['contact_name']},\n\n";
        $body .= "La factura {$invoice['invoice_number']} se encuentra vencida por {$invoice['days_overdue']} días.\n\n";
        $body .= "Detalles de la factura:\n";
        $body .= "- Monto: $" . number_format($invoice['total_amount'], 2) . "\n";
        $body .= "- Fecha de vencimiento: " . date('d/m/Y', strtotime($invoice['due_date'])) . "\n\n";
        $body .= "Por favor, realice el pago lo antes posible.\n\n";
        $body .= "Saludos cordiales,\n";
        $body .= $settings['company_name'];
        
        // Enviar correo
        $mailer->send($invoice['email'], $subject, $body);
        
        // Registrar notificación
        $query = "INSERT INTO notification_logs 
                 (invoice_id, notification_type, days_overdue, sent_at) 
                 VALUES (:invoice_id, 'overdue', :days_overdue, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":invoice_id", $invoice['id']);
        $stmt->bindParam(":days_overdue", $invoice['days_overdue']);
        $stmt->execute();
        
        echo "Notificación de vencimiento enviada para factura {$invoice['invoice_number']}\n";
        
    } catch (Exception $e) {
        echo "Error al enviar notificación de vencimiento para factura {$invoice['invoice_number']}: {$e->getMessage()}\n";
    }
}

// Registrar la ejecución del cron
$query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
          VALUES (NULL, 'cron_notifications', :description, 'system')";
$description = "Cron de notificaciones ejecutado. " . 
               "Recordatorios enviados: " . count($invoices) . ". " .
               "Notificaciones de vencimiento enviadas: " . count($overdue_invoices);
$stmt = $db->prepare($query);
$stmt->bindParam(":description", $description);
$stmt->execute(); 