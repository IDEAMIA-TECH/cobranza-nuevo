<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Obtener información del cliente
$query = "SELECT c.*, u.email 
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener estadísticas del cliente
$query = "SELECT 
            COUNT(*) as total_invoices,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_invoices,
            SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_invoices,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
            SUM(total_amount) as total_amount,
            SUM(CASE WHEN status IN ('pending', 'overdue') THEN total_amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount
          FROM invoices 
          WHERE client_id = :client_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":client_id", $client['id']);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener últimas facturas
$query = "SELECT * FROM invoices 
          WHERE client_id = :client_id 
          ORDER BY created_at DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(":client_id", $client['id']);
$stmt->execute();
$recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener últimos pagos
$query = "SELECT p.*, i.invoice_number 
          FROM payments p 
          JOIN invoices i ON i.id = p.invoice_id 
          WHERE i.client_id = :client_id 
          ORDER BY p.payment_date DESC 
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(":client_id", $client['id']);
$stmt->execute();
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Panel de Control</h2>
        <div class="welcome-message">
            Bienvenido/a, <?php echo htmlspecialchars($client['business_name']); ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">Total Facturas</div>
            <div class="stat-value"><?php echo $stats['total_invoices']; ?></div>
            <a href="../invoices/" class="stat-link">Ver todas</a>
        </div>

        <div class="stat-card">
            <div class="stat-title">Monto Total</div>
            <div class="stat-value">$<?php echo number_format($stats['total_amount'], 2); ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-title">Monto Pagado</div>
            <div class="stat-value">$<?php echo number_format($stats['paid_amount'], 2); ?></div>
        </div>

        <div class="stat-card warning">
            <div class="stat-title">Monto Pendiente</div>
            <div class="stat-value">$<?php echo number_format($stats['pending_amount'], 2); ?></div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-section">
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

        <div class="dashboard-section">
            <div class="section-header">
                <h3>Últimas Facturas</h3>
                <a href="../invoices/" class="btn btn-small">
                    <i class="fas fa-list"></i> Ver todas
                </a>
            </div>
            <?php if (empty($recent_invoices)): ?>
                <p class="no-data">No hay facturas recientes</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Vencimiento</th>
                                <th>Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_invoices as $invoice): ?>
                                <tr class="<?php echo $invoice['status'] === 'overdue' ? 'overdue' : ''; ?>">
                                    <td>
                                        <a href="../invoices/view.php?id=<?php echo $invoice['id']; ?>">
                                            <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                                    <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $invoice['status']; ?>">
                                            <?php 
                                            echo $invoice['status'] === 'paid' ? 'Pagada' : 
                                                 ($invoice['status'] === 'pending' ? 'Pendiente' : 
                                                  'Vencida'); 
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <div class="section-header">
                <h3>Últimos Pagos</h3>
                <a href="../payments/" class="btn btn-small">
                    <i class="fas fa-list"></i> Ver todos
                </a>
            </div>
            <?php if (empty($recent_payments)): ?>
                <p class="no-data">No hay pagos recientes</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Factura</th>
                                <th>Método</th>
                                <th>Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
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
                                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 