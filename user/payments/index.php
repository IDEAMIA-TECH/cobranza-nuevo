<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

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

// Parámetros de filtrado y paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$payment_method = isset($_GET['payment_method']) ? cleanInput($_GET['payment_method']) : '';
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : '';

// Construir la consulta base
$query = "SELECT p.*, i.invoice_number, i.total_amount as invoice_total,
          u.name as registered_by_name 
          FROM payments p 
          JOIN invoices i ON i.id = p.invoice_id 
          LEFT JOIN users u ON u.id = p.registered_by 
          WHERE i.client_id = :client_id";

$params = [':client_id' => $client['id']];

// Agregar filtros si existen
if ($payment_method) {
    $query .= " AND p.payment_method = :payment_method";
    $params[':payment_method'] = $payment_method;
}

if ($date_from) {
    $query .= " AND DATE(p.payment_date) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(p.payment_date) <= :date_to";
    $params[':date_to'] = $date_to;
}

// Obtener total de registros para paginación
$count_query = str_replace("p.*, i.invoice_number, i.total_amount as invoice_total,\n          u.name as registered_by_name", "COUNT(*) as total", $query);
$stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Agregar ordenamiento y límites
$query .= " ORDER BY p.payment_date DESC LIMIT :offset, :per_page";
$params[':offset'] = $offset;
$params[':per_page'] = $per_page;

// Ejecutar consulta principal
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener totales por método de pago
$query = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total 
          FROM payments p 
          JOIN invoices i ON i.id = p.invoice_id 
          WHERE i.client_id = :client_id 
          GROUP BY payment_method";
$stmt = $db->prepare($query);
$stmt->bindParam(":client_id", $client['id']);
$stmt->execute();
$payment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Historial de Pagos</h2>
    </div>

    <div class="stats-grid">
        <?php foreach ($payment_stats as $stat): ?>
            <div class="stat-card">
                <div class="stat-title">
                    <span class="payment-method <?php echo $stat['payment_method']; ?>">
                        <?php 
                        echo $stat['payment_method'] === 'transfer' ? 'Transferencia' : 
                             ($stat['payment_method'] === 'cash' ? 'Efectivo' : 
                              ($stat['payment_method'] === 'check' ? 'Cheque' : 
                               'Tarjeta')); 
                        ?>
                    </span>
                </div>
                <div class="stat-value">$<?php echo number_format($stat['total'], 2); ?></div>
                <div class="stat-subtitle"><?php echo $stat['count']; ?> pagos</div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="payment_method">Método de Pago:</label>
                    <select name="payment_method" id="payment_method" class="form-control">
                        <option value="">Todos</option>
                        <option value="transfer" <?php echo $payment_method === 'transfer' ? 'selected' : ''; ?>>
                            Transferencia
                        </option>
                        <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>
                            Efectivo
                        </option>
                        <option value="check" <?php echo $payment_method === 'check' ? 'selected' : ''; ?>>
                            Cheque
                        </option>
                        <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>
                            Tarjeta
                        </option>
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
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Limpiar
                </a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <?php if (empty($payments)): ?>
            <p class="no-data">No se encontraron pagos</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Factura</th>
                        <th>Monto</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th>Registrado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                            <td>
                                <a href="../invoices/view.php?id=<?php echo $payment['invoice_id']; ?>">
                                    <?php echo htmlspecialchars($payment['invoice_number']); ?>
                                </a>
                            </td>
                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
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
                            <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                            <td><?php echo htmlspecialchars($payment['registered_by_name']); ?></td>
                            <td class="actions">
                                <a href="../invoices/view.php?id=<?php echo $payment['invoice_id']; ?>" 
                                   class="btn btn-small" title="Ver factura">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter([
                            'payment_method' => $payment_method,
                            'date_from' => $date_from,
                            'date_to' => $date_to
                        ])); ?>" 
                           class="btn <?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 