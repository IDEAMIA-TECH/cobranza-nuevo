<?php
ob_start();

require_once 'includes/functions.php';
require_once 'config/database.php';

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Redirigir admin a su panel
if (isAdmin()) {
    header("Location: admin/dashboard.php");
    exit();
}

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

// Obtener facturas pendientes
$query = "SELECT i.*, 
          DATEDIFF(i.due_date, CURDATE()) as days_to_due,
          CASE 
              WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN 'overdue'
              ELSE i.status 
          END as current_status
          FROM invoices i 
          WHERE i.client_id = :client_id 
          AND i.status IN ('pending', 'overdue')
          ORDER BY i.due_date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":client_id", $client['id']);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="dashboard-container">
    <div class="welcome-section">
        <h2>Bienvenido, <?php echo htmlspecialchars($client['business_name']); ?></h2>
        <p>RFC: <?php echo htmlspecialchars($client['rfc']); ?></p>
    </div>

    <div class="invoices-section">
        <h3>Facturas Pendientes</h3>
        
        <?php if (empty($invoices)): ?>
            <div class="no-invoices">
                <p>No tienes facturas pendientes de pago.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Factura</th>
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
                                <td>
                                    <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                                    <?php if ($invoice['days_to_due'] > 0): ?>
                                        <span class="days-remaining">
                                            (<?php echo $invoice['days_to_due']; ?> días)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $invoice['current_status']; ?>">
                                        <?php 
                                        echo $invoice['current_status'] === 'pending' ? 'Pendiente' : 
                                             ($invoice['current_status'] === 'overdue' ? 'Vencida' : 
                                              $invoice['current_status']); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-small">Ver Detalles</a>
                                    <?php if ($invoice['pdf_path']): ?>
                                        <a href="<?php echo htmlspecialchars($invoice['pdf_path']); ?>" 
                                           class="btn btn-small btn-secondary" 
                                           target="_blank">PDF</a>
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

<?php include 'includes/footer.php';
ob_end_flush();
?> 