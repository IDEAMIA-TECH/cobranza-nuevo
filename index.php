<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Si es administrador, redirigir al dashboard de admin
if (isAdmin()) {
    header("Location: admin/dashboard.php");
    exit();
}

// Obtener información del cliente
$user_id = $_SESSION['user_id'];
$query = "SELECT c.*, u.email 
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener facturas pendientes del cliente
$query = "SELECT * FROM invoices 
          WHERE client_id = :client_id 
          AND status IN ('pending', 'overdue') 
          ORDER BY due_date ASC";
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
                            <tr class="<?php echo $invoice['status'] === 'overdue' ? 'overdue' : ''; ?>">
                                <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                                <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $invoice['status']; ?>">
                                        <?php 
                                        echo $invoice['status'] === 'pending' ? 'Pendiente' : 'Vencida';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="user/invoices/view.php?id=<?php echo $invoice['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 