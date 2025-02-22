<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();

if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Obtener estadísticas generales
$stats = [
    'pending_invoices' => 0,
    'overdue_invoices' => 0,
    'total_amount_pending' => 0,
    'pending_approvals' => 0
];

// Contar facturas pendientes y vencidas
$query = "SELECT 
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
            SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END) as total_pending
          FROM invoices";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$stats['paid_invoices'] = $result['paid_count'];
$stats['overdue_invoices'] = $result['overdue_count'];
$stats['total_amount_pending'] = $result['total_pending'];

// Contar clientes pendientes de aprobación
$query = "SELECT COUNT(*) as pending_count FROM users WHERE status = 'pending' AND role = 'client'";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['pending_approvals'] = $result['pending_count'];

// Obtener últimas facturas
$query = "SELECT i.*, c.business_name 
          FROM invoices i 
          JOIN clients c ON c.id = i.client_id 
          WHERE i.status = 'pending' 
          ORDER BY i.due_date ASC 
          LIMIT 5";
$stmt = $db->query($query);
$recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener últimos registros pendientes
$query = "SELECT u.email, c.business_name, c.rfc, u.created_at 
          FROM users u 
          JOIN clients c ON c.user_id = u.id 
          WHERE u.status = 'pending' AND u.role = 'client' 
          ORDER BY u.created_at DESC 
          LIMIT 5";
$stmt = $db->query($query);
$pending_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="dashboard-container admin-dashboard">
    <div class="dashboard-header">
        <h2>Panel de Administración</h2>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">Facturas Pagadas</div>
            <div class="stat-value"><?php echo $stats['paid_invoices']; ?></div>
            <a href="invoices/index.php?status=pending" class="stat-link">Ver detalles</a>
        </div>

        <div class="stat-card warning">
            <div class="stat-title">Facturas Vencidas</div>
            <div class="stat-value"><?php echo $stats['overdue_invoices']; ?></div>
            <a href="invoices/index.php?status=overdue" class="stat-link">Ver detalles</a>
        </div>

        <div class="stat-card">
            <div class="stat-title">Monto Pendiente Total</div>
            <div class="stat-value">$<?php echo number_format($stats['total_amount_pending'], 2); ?></div>
            <a href="invoices/index.php" class="stat-link">Ver todas las facturas</a>
        </div>

        <div class="stat-card alert">
            <div class="stat-title">Clientes Pendientes de Aprobación</div>
            <div class="stat-value"><?php echo $stats['pending_approvals']; ?></div>
            <a href="clients/index.php?status=pending" class="stat-link">Revisar solicitudes</a>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h3>Facturas Próximas a Vencer</h3>
            <div class="section-actions">
                <a href="<?php echo getBaseUrl(); ?>/admin/invoices/" class="btn btn-link">Ver todas</a>
            </div>
            <?php if (empty($recent_invoices)): ?>
                <p class="no-data">No hay facturas pendientes</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Factura</th>
                                <th>Vencimiento</th>
                                <th>Monto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_invoices as $invoice): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($invoice['business_name']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                                    <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                    <td>
                                        <a href="invoices/view.php?id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-small">Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3>Solicitudes de Registro Pendientes</h3>
            <div class="section-actions">
                <a href="<?php echo getBaseUrl(); ?>/admin/clients/" class="btn btn-link">Ver todos</a>
            </div>
            <?php if (empty($pending_clients)): ?>
                <p class="no-data">No hay solicitudes pendientes</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Razón Social</th>
                                <th>RFC</th>
                                <th>Email</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_clients as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['business_name']); ?></td>
                                    <td><?php echo htmlspecialchars($client['rfc']); ?></td>
                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></td>
                                    <td>
                                        <a href="clients/view.php?id=<?php echo $client['id']; ?>" 
                                           class="btn btn-small">Revisar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3>Últimas Actividades</h3>
            <div class="section-actions">
                <a href="<?php echo getBaseUrl(); ?>/admin/activity_logs/index.php" class="btn btn-link">Ver todas</a>
            </div>
            <!-- ... -->
        </div>
    </div>

    <div class="action-buttons">
        <a href="invoices/create.php" class="btn">Nueva Factura</a>
        <a href="clients/create.php" class="btn">Nuevo Cliente</a>
        <a href="payments/register.php" class="btn">Registrar Pago</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 