<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';
require_once '../../includes/ReportPDF.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();

if (!isAdmin()) {
    header("Location: ../../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Obtener parámetros
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : date('Y-m-t');
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

// Preparar filtros para el título
$filters = [];
$period = date('d/m/Y', strtotime($date_from)) . ' al ' . date('d/m/Y', strtotime($date_to));

if ($client_id) {
    $query = "SELECT business_name FROM clients WHERE id = :client_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":client_id", $client_id);
    $stmt->execute();
    if ($client = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $filters[] = "Cliente: " . $client['business_name'];
    }
}

// Obtener datos para el reporte
// ... (mismo código de consultas que en index.php) ...

// Crear PDF
$title = "Reporte de Cobranza";
$pdf = new ReportPDF($title, $period, $filters);

// Agregar página
$pdf->AddPage();

// Agregar tablas
$pdf->addSummaryTable($stats);
$pdf->addPaymentMethodsTable($payment_stats);
$pdf->addAgingTable($aging_report);

// Registrar la descarga
$query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
          VALUES (:user_id, 'export_report', :description, :ip_address)";
$description = "Exportó reporte de cobranza (Período: $period)";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->bindParam(":description", $description);
$stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
$stmt->execute();

// Generar el archivo
$filename = 'reporte_cobranza_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D'); 