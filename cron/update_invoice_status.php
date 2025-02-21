<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Verificar que el script solo se ejecute desde la línea de comandos
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse desde la línea de comandos');
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();
    
    // Marcar facturas como vencidas
    $query = "UPDATE invoices 
              SET status = 'overdue',
                  updated_at = NOW()
              WHERE status = 'pending' 
              AND due_date < CURDATE()";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute();
    $facturas_vencidas = $stmt->rowCount();
    
    // Obtener facturas que fueron marcadas como vencidas
    $query = "SELECT i.*, c.business_name 
              FROM invoices i 
              JOIN clients c ON c.id = i.client_id 
              WHERE i.status = 'overdue' 
              AND DATE(i.updated_at) = CURDATE()";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Registrar cada factura vencida en el log de actividades
    foreach ($facturas as $factura) {
        $query = "INSERT INTO activity_logs 
                  (user_id, action, description, ip_address, related_id) 
                  VALUES 
                  (NULL, 'invoice_overdue', :description, 'system', :invoice_id)";
        
        $description = "Factura {$factura['invoice_number']} de {$factura['business_name']} " .
                      "marcada como vencida automáticamente";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":invoice_id", $factura['id']);
        $stmt->execute();
    }
    
    // Actualizar estadísticas de clientes
    $query = "UPDATE clients c 
              SET overdue_invoices = (
                  SELECT COUNT(*) 
                  FROM invoices 
                  WHERE client_id = c.id 
                  AND status = 'overdue'
              ),
              total_debt = (
                  SELECT COALESCE(SUM(total_amount), 0)
                  FROM invoices 
                  WHERE client_id = c.id 
                  AND status IN ('pending', 'overdue')
              )";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    // Registrar la ejecución del cron
    $query = "INSERT INTO activity_logs 
              (user_id, action, description, ip_address) 
              VALUES 
              (NULL, 'cron_status_update', :description, 'system')";
    
    $description = "Actualización automática de estados completada. " .
                  "Facturas marcadas como vencidas: {$facturas_vencidas}";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":description", $description);
    $stmt->execute();
    
    $db->commit();
    echo "Actualización de estados completada exitosamente.\n";
    echo "Facturas marcadas como vencidas: {$facturas_vencidas}\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "Error durante la actualización: " . $e->getMessage() . "\n";
    
    // Registrar el error
    try {
        $query = "INSERT INTO activity_logs 
                  (user_id, action, description, ip_address) 
                  VALUES 
                  (NULL, 'cron_error', :description, 'system')";
        
        $description = "Error en actualización automática de estados: " . $e->getMessage();
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":description", $description);
        $stmt->execute();
    } catch (Exception $logError) {
        echo "Error al registrar el error: " . $logError->getMessage() . "\n";
    }
} 