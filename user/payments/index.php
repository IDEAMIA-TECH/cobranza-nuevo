<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

// Verificar que no sea administrador
if (isAdmin()) {
    header("Location: ../../admin/dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Parámetros de filtrado y paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Obtener ID del cliente
$query = "SELECT id FROM clients WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    header("Location: ../dashboard/");
    exit();
}

// Construir la consulta base
$query = "SELECT p.*, i.invoice_number, i.total_amount as invoice_total,
          u.email as registered_by_email
          FROM payments p
          JOIN invoices i ON i.id = p.invoice_id
          JOIN users u ON u.id = p.created_by
          JOIN clients c ON c.id = i.client_id
          WHERE c.id = :client_id";

$params = [':client_id' => $client['id']];

// Agregar filtros si existen
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $query .= " AND p.status = :status";
    $params[':status'] = cleanInput($_GET['status']);
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $query .= " AND DATE(p.payment_date) >= :date_from";
    $params[':date_from'] = cleanInput($_GET['date_from']);
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $query .= " AND DATE(p.payment_date) <= :date_to";
    $params[':date_to'] = cleanInput($_GET['date_to']);
}

// Obtener total de registros para paginación
$count_query = str_replace("p.*, i.invoice_number, i.total_amount as invoice_total, u.email as registered_by_email", "COUNT(*) as total", $query);
$stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Agregar ordenamiento y límites
$query .= " ORDER BY p.payment_date DESC LIMIT :limit OFFSET :offset";

// Ejecutar consulta principal
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Mis Pagos</h2>
    </div>

    <!-- Filtros -->
    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="status">Estado:</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="processed" <?php echo isset($_GET['status']) && $_GET['status'] === 'processed' ? 'selected' : ''; ?>>Procesado</option>
                        <option value="rejected" <?php echo isset($_GET['status']) && $_GET['status'] === 'rejected' ? 'selected' : ''; ?>>Rechazado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_from">Desde:</label>
                    <input type="date" id="date_from" name="date_from" 
                           value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>" 
                           class="form-control">
                </div>

                <div class="form-group">
                    <label for="date_to">Hasta:</label>
                    <input type="date" id="date_to" name="date_to" 
                           value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>" 
                           class="form-control">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de pagos -->
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Fecha de Pago</th>
                    <th>Monto</th>
                    <th>Método</th>
                    <th>Referencia</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No se encontraron pagos</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                            <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $payment['status']; ?>">
                                    <?php 
                                    echo $payment['status'] === 'pending' ? 'Pendiente' : 
                                         ($payment['status'] === 'processed' ? 'Procesado' : 'Rechazado'); 
                                    ?>
                                </span>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $payment['id']; ?>" 
                                   class="btn btn-small">Ver Detalles</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" 
                   class="<?php echo $page === $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.filters-section {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filters-form .form-row {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85em;
}

.status-badge.pending {
    background-color: #fef3c7;
    color: #92400e;
}

.status-badge.processed {
    background-color: #d1fae5;
    color: #065f46;
}

.status-badge.rejected {
    background-color: #fee2e2;
    color: #991b1b;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.pagination a {
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: var(--primary-color);
}

.pagination a.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}
</style>

<?php include '../../includes/footer.php'; ?> 