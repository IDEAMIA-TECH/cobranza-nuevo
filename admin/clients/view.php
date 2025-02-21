<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();

if (!isAdmin()) {
    header("Location: ../../index.php");
    exit();
}

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$client_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Obtener información del cliente
$query = "SELECT c.*, u.email, u.status, u.created_at as registration_date
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE c.id = :client_id";

$stmt = $db->prepare($query);
$stmt->bindParam(":client_id", $client_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: index.php");
    exit();
}

$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener facturas del cliente
$query = "SELECT i.*, 
          CASE 
              WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN 'overdue'
              ELSE i.status 
          END as current_status
          FROM invoices i 
          WHERE i.client_id = :client_id 
          ORDER BY i.issue_date DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(":client_id", $client_id);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas
$stats = [
    'total_invoices' => count($invoices),
    'pending_amount' => 0,
    'overdue_invoices' => 0,
    'paid_invoices' => 0
];

foreach ($invoices as $invoice) {
    if ($invoice['current_status'] === 'pending' || $invoice['current_status'] === 'overdue') {
        $stats['pending_amount'] += $invoice['total_amount'];
    }
    if ($invoice['current_status'] === 'overdue') {
        $stats['overdue_invoices']++;
    }
    if ($invoice['status'] === 'paid') {
        $stats['paid_invoices']++;
    }
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Detalles del Cliente</h2>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="edit.php?id=<?php echo $client_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar Cliente
            </a>
        </div>
    </div>

    <div class="client-stats">
        <div class="stat-card">
            <div class="stat-title">Total Facturas</div>
            <div class="stat-value"><?php echo $stats['total_invoices']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Monto Pendiente</div>
            <div class="stat-value">$<?php echo number_format($stats['pending_amount'], 2); ?></div>
        </div>
        <div class="stat-card <?php echo $stats['overdue_invoices'] > 0 ? 'warning' : ''; ?>">
            <div class="stat-title">Facturas Vencidas</div>
            <div class="stat-value"><?php echo $stats['overdue_invoices']; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Facturas Pagadas</div>
            <div class="stat-value"><?php echo $stats['paid_invoices']; ?></div>
        </div>
    </div>

    <div class="client-details">
        <div class="info-section">
            <h3>Información General</h3>
            <table class="details-table">
                <tr>
                    <th>Estado:</th>
                    <td>
                        <span class="status-badge <?php echo $client['status']; ?>">
                            <?php 
                            echo $client['status'] === 'active' ? 'Activo' : 
                                 ($client['status'] === 'pending' ? 'Pendiente' : 'Inactivo');
                            ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Razón Social:</th>
                    <td><?php echo htmlspecialchars($client['business_name']); ?></td>
                </tr>
                <tr>
                    <th>RFC:</th>
                    <td><?php echo htmlspecialchars($client['rfc']); ?></td>
                </tr>
                <tr>
                    <th>Régimen Fiscal:</th>
                    <td><?php echo htmlspecialchars($client['tax_regime']); ?></td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                </tr>
                <tr>
                    <th>Teléfono:</th>
                    <td><?php echo htmlspecialchars($client['phone']); ?></td>
                </tr>
                <tr>
                    <th>Fecha de Registro:</th>
                    <td><?php echo date('d/m/Y', strtotime($client['registration_date'])); ?></td>
                </tr>
            </table>
        </div>

        <div class="info-section">
            <h3>Dirección Fiscal</h3>
            <table class="details-table">
                <tr>
                    <th>Calle:</th>
                    <td><?php echo htmlspecialchars($client['street']); ?></td>
                </tr>
                <tr>
                    <th>Número Exterior:</th>
                    <td><?php echo htmlspecialchars($client['ext_number']); ?></td>
                </tr>
                <?php if (!empty($client['int_number'])): ?>
                <tr>
                    <th>Número Interior:</th>
                    <td><?php echo htmlspecialchars($client['int_number']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Colonia:</th>
                    <td><?php echo htmlspecialchars($client['neighborhood']); ?></td>
                </tr>
                <tr>
                    <th>Ciudad:</th>
                    <td><?php echo htmlspecialchars($client['city']); ?></td>
                </tr>
                <tr>
                    <th>Estado:</th>
                    <td><?php echo htmlspecialchars($client['state']); ?></td>
                </tr>
                <tr>
                    <th>Código Postal:</th>
                    <td><?php echo htmlspecialchars($client['zip_code']); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="invoices-section">
        <div class="section-header">
            <h3>Facturas</h3>
            <a href="../invoices/create.php?client_id=<?php echo $client_id; ?>" class="btn">Nueva Factura</a>
        </div>

        <?php if (empty($invoices)): ?>
            <p class="no-data">No hay facturas registradas para este cliente</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Fecha Emisión</th>
                            <th>Vencimiento</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="<?php echo $invoice['current_status'] === 'overdue' ? 'overdue' : ''; ?>">
                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                                <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $invoice['current_status']; ?>">
                                        <?php 
                                        echo $invoice['current_status'] === 'pending' ? 'Pendiente' : 
                                             ($invoice['current_status'] === 'overdue' ? 'Vencida' : 
                                              ($invoice['current_status'] === 'paid' ? 'Pagada' : 
                                               $invoice['current_status'])); 
                                        ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="../invoices/view.php?id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-small" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($invoice['status'] !== 'paid'): ?>
                                        <a href="../payments/register.php?invoice_id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-small btn-success" title="Registrar pago">
                                            <i class="fas fa-dollar-sign"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 