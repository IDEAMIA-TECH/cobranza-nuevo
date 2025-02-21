<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';
require_once '../../includes/InvoicePDF.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$invoice_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Obtener información del cliente
$query = "SELECT c.* FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar que la factura pertenezca al cliente
$query = "SELECT * FROM invoices WHERE id = :invoice_id AND client_id = :client_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->bindParam(":client_id", $client['id']);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: index.php");
    exit();
}

$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener conceptos de la factura
$query = "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();
$invoice['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener pagos de la factura
$query = "SELECT p.*, u.name as registered_by_name 
          FROM payments p 
          LEFT JOIN users u ON u.id = p.registered_by 
          WHERE p.invoice_id = :invoice_id 
          ORDER BY p.payment_date";
$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();
$invoice['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener configuración de la empresa
$query = "SELECT * FROM system_settings";
$stmt = $db->query($query);
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$company = [
    'name' => $settings['company_name'],
    'rfc' => $settings['company_rfc'],
    'address' => $settings['company_address'],
    'phone' => $settings['company_phone'],
    'email' => $settings['company_email']
];

// Generar PDF
$pdf = new InvoicePDF($invoice, $client, $company);
$pdf->generateInvoice();

// Registrar la descarga
$query = "INSERT INTO activity_logs (user_id, action, description, ip_address, related_id) 
          VALUES (:user_id, 'download_invoice', :description, :ip_address, :invoice_id)";
$description = "Descargó la factura " . $invoice['invoice_number'];
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->bindParam(":description", $description);
$stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();

// Descargar el archivo
$filename = 'factura_' . $invoice['invoice_number'] . '.pdf';
$pdf->Output($filename, 'D'); 