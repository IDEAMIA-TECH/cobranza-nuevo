<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Configuración de paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Obtener el total de facturas para el cliente
$query = "SELECT COUNT(*) as total 
          FROM invoices i 
          JOIN clients c ON c.id = i.client_id 
          JOIN users u ON u.id = c.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$total_rows = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_rows / $limit);

// Obtener las facturas del cliente con paginación
$query = "SELECT i.*, c.business_name 
          FROM invoices i 
          JOIN clients c ON c.id = i.client_id 
          JOIN users u ON u.id = c.user_id 
          WHERE u.id = :user_id 
          ORDER BY i.issue_date DESC 
          LIMIT :offset, :limit";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
$stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="container">
    <h2>Mis Facturas</h2>
    
    <?php if (empty($invoices)): ?>
        <p class="no-data">No hay facturas disponibles.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Fecha</th>
                        <th>Vencimiento</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                            <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $invoice['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'pending' => 'Pendiente',
                                        'paid' => 'Pagada',
                                        'overdue' => 'Vencida',
                                        'cancelled' => 'Cancelada'
                                    ];
                                    echo $status_labels[$invoice['status']];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-small btn-primary">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                    <a href="download.php?id=<?php echo $invoice['id']; ?>&type=pdf" class="btn btn-small btn-secondary">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </a>
                                    <a href="download.php?id=<?php echo $invoice['id']; ?>&type=xml" class="btn btn-small btn-info">
                                        <i class="fas fa-file-code"></i> XML
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="btn <?php echo $page === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?> 