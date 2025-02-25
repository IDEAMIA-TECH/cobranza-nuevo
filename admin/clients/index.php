<?php
ob_start();
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

// Obtener lista de clientes con información adicional
$query = "SELECT c.*, u.email, u.status as user_status,
          (SELECT COUNT(*) FROM invoices WHERE client_id = c.id) as total_invoices,
          (SELECT COUNT(*) FROM invoices WHERE client_id = c.id AND status = 'pending') as pending_invoices,
          (SELECT COUNT(*) FROM invoices WHERE client_id = c.id AND status = 'overdue') as overdue_invoices
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          ORDER BY c.business_name";

$stmt = $db->query($query);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Gestión de Clientes</h2>
        <div class="header-actions">
            <a href="create.php" class="btn btn-success">
                <i class="fas fa-user-plus"></i> Nuevo Cliente
            </a>
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="clients-list">
        <table class="table">
            <thead>
                <tr>
                    <th>Razón Social</th>
                    <th>RFC</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Estado</th>
                    <th>Facturas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?php echo htmlspecialchars($client['business_name']); ?></td>
                    <td><?php echo htmlspecialchars($client['rfc']); ?></td>
                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                    <td><?php echo htmlspecialchars($client['phone']); ?></td>
                    <td>
                        <span class="status-badge <?php echo $client['user_status']; ?>">
                            <?php echo ucfirst($client['user_status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="invoice-stats">
                            <span title="Total de Facturas">
                                <i class="fas fa-file-invoice"></i> <?php echo $client['total_invoices']; ?>
                            </span>
                            <span title="Facturas Pendientes" class="pending">
                                <i class="fas fa-clock"></i> <?php echo $client['pending_invoices']; ?>
                            </span>
                            <span title="Facturas Vencidas" class="overdue">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $client['overdue_invoices']; ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="view.php?id=<?php echo $client['id']; ?>" class="btn btn-small btn-primary">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            <a href="edit.php?id=<?php echo $client['id']; ?>" class="btn btn-small btn-secondary">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <form method="POST" action="toggle_status.php" class="d-inline" onsubmit="return confirm('¿Está seguro de cambiar el estado del cliente?');">
                                <?php echo SecurityHelper::getCSRFTokenField(); ?>
                                <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $client['user_status']; ?>">
                                <button type="submit" class="btn btn-small <?php echo $client['user_status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                    <i class="fas <?php echo $client['user_status'] === 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                    <?php echo $client['user_status'] === 'active' ? 'Desactivar' : 'Activar'; ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deactivateClient(clientId) {
    if (confirm('¿Está seguro de que desea desactivar este cliente?')) {
        window.location.href = `status.php?id=${clientId}&action=deactivate`;
    }
}

function activateClient(clientId) {
    if (confirm('¿Está seguro de que desea activar este cliente?')) {
        window.location.href = `status.php?id=${clientId}&action=activate`;
    }
}
</script>

<style>
.invoice-stats {
    display: flex;
    gap: 10px;
}

.invoice-stats span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.invoice-stats .pending {
    color: #f0ad4e;
}

.invoice-stats .overdue {
    color: #d9534f;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.85em;
}

.status-badge.active {
    background-color: #5cb85c;
    color: white;
}

.status-badge.pending {
    background-color: #f0ad4e;
    color: white;
}

.status-badge.inactive {
    background-color: #d9534f;
    color: white;
}
</style>

<?php 
include '../../includes/footer.php';
ob_end_flush();
?> 