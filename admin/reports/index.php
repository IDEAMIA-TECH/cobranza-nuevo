<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();

if (!isAdmin()) {
    header("Location: ../../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Parámetros de filtrado
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : date('Y-m-t');
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

// Obtener lista de clientes para el filtro
$query = "SELECT id, business_name FROM clients ORDER BY business_name";
$stmt = $db->query($query);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas generales
$query = "SELECT 
            COUNT(DISTINCT i.id) as total_invoices,
            SUM(CASE WHEN i.status = 'pending' THEN 1 ELSE 0 END) as pending_invoices,
            SUM(CASE WHEN i.status = 'overdue' THEN 1 ELSE 0 END) as overdue_invoices,
            SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
            SUM(i.total_amount) as total_amount,
            SUM(CASE WHEN i.status IN ('pending', 'overdue') THEN i.total_amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) as collected_amount
          FROM invoices i 
          WHERE i.issue_date BETWEEN :date_from AND :date_to";

if ($client_id) {
    $query .= " AND i.client_id = :client_id";
}

$stmt = $db->prepare($query);
$stmt->bindParam(":date_from", $date_from);
$stmt->bindParam(":date_to", $date_to);
if ($client_id) {
    $stmt->bindParam(":client_id", $client_id);
}
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener pagos por método
$query = "SELECT 
            p.payment_method,
            COUNT(*) as total_payments,
            SUM(p.amount) as total_amount
          FROM payments p 
          JOIN invoices i ON i.id = p.invoice_id
          WHERE p.payment_date BETWEEN :date_from AND :date_to";

if ($client_id) {
    $query .= " AND i.client_id = :client_id";
}

$query .= " GROUP BY p.payment_method";

$stmt = $db->prepare($query);
$stmt->bindParam(":date_from", $date_from);
$stmt->bindParam(":date_to", $date_to);
if ($client_id) {
    $stmt->bindParam(":client_id", $client_id);
}
$stmt->execute();
$payment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener facturas vencidas por antigüedad
$query = "SELECT 
            CASE 
                WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN '1-30 días'
                WHEN DATEDIFF(CURDATE(), due_date) <= 60 THEN '31-60 días'
                WHEN DATEDIFF(CURDATE(), due_date) <= 90 THEN '61-90 días'
                ELSE 'Más de 90 días'
            END as aging,
            COUNT(*) as total_invoices,
            SUM(total_amount) as total_amount
          FROM invoices 
          WHERE status IN ('pending', 'overdue') 
          AND due_date < CURDATE()";

if ($client_id) {
    $query .= " AND client_id = :client_id";
}

$query .= " GROUP BY aging ORDER BY 
            CASE aging
                WHEN '1-30 días' THEN 1
                WHEN '31-60 días' THEN 2
                WHEN '61-90 días' THEN 3
                ELSE 4
            END";

$stmt = $db->prepare($query);
if ($client_id) {
    $stmt->bindParam(":client_id", $client_id);
}
$stmt->execute();
$aging_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Reportes de Cobranza</h2>
        <div class="header-actions">
            <a href="export_pdf.php?<?php echo http_build_query($_GET); ?>" 
               class="btn btn-secondary" target="_blank">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
        </div>
    </div>

    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-group">
                <label for="client_id">Cliente:</label>
                <select name="client_id" id="client_id" class="form-control">
                    <option value="">Todos los clientes</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>" 
                                <?php echo $client_id === $client['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['business_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="date_from">Desde:</label>
                <input type="date" id="date_from" name="date_from" 
                       value="<?php echo $date_from; ?>" class="form-control">
            </div>

            <div class="form-group">
                <label for="date_to">Hasta:</label>
                <input type="date" id="date_to" name="date_to" 
                       value="<?php echo $date_to; ?>" class="form-control">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> Generar Reporte
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Limpiar
                </a>
            </div>
        </form>
    </div>

    <div class="report-sections">
        <div class="report-section">
            <h3>Resumen General</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Facturas</div>
                    <div class="stat-value"><?php echo $stats['total_invoices']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Monto Total</div>
                    <div class="stat-value">$<?php echo number_format($stats['total_amount'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Monto Cobrado</div>
                    <div class="stat-value">$<?php echo number_format($stats['collected_amount'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Monto Pendiente</div>
                    <div class="stat-value">$<?php echo number_format($stats['pending_amount'], 2); ?></div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <h3>Estado de Facturas</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Pagadas</div>
                    <div class="stat-value"><?php echo $stats['paid_invoices']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Pendientes</div>
                    <div class="stat-value"><?php echo $stats['pending_invoices']; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-title">Vencidas</div>
                    <div class="stat-value"><?php echo $stats['overdue_invoices']; ?></div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <h3>Pagos por Método</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th>Cantidad</th>
                            <th>Monto Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_stats as $payment): ?>
                            <tr>
                                <td>
                                    <span class="payment-method <?php echo $payment['payment_method']; ?>">
                                        <?php 
                                        echo $payment['payment_method'] === 'transfer' ? 'Transferencia' : 
                                             ($payment['payment_method'] === 'cash' ? 'Efectivo' : 
                                              ($payment['payment_method'] === 'check' ? 'Cheque' : 
                                               'Tarjeta')); 
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $payment['total_payments']; ?></td>
                                <td>$<?php echo number_format($payment['total_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-section">
            <h3>Antigüedad de Saldos</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Antigüedad</th>
                            <th>Facturas</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aging_report as $aging): ?>
                            <tr>
                                <td><?php echo $aging['aging']; ?></td>
                                <td><?php echo $aging['total_invoices']; ?></td>
                                <td>$<?php echo number_format($aging['total_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 