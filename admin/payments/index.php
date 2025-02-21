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
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$payment_method = isset($_GET['payment_method']) ? cleanInput($_GET['payment_method']) : '';
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : '';

// Construir la consulta base
$query = "SELECT p.*, 
          i.invoice_number, i.total_amount as invoice_total,
          c.business_name, c.rfc,
          u.name as registered_by_name
          FROM payments p 
          JOIN invoices i ON i.id = p.invoice_id 
          JOIN clients c ON c.id = i.client_id 
          LEFT JOIN users u ON u.id = p.registered_by 
          WHERE 1=1";

$params = [];

// Agregar filtros si existen
if ($client_id) {
    $query .= " AND c.id = :client_id";
    $params[':client_id'] = $client_id;
}

if ($payment_method) {
    $query .= " AND p.payment_method = :payment_method";
    $params[':payment_method'] = $payment_method;
}

if ($date_from) {
    $query .= " AND p.payment_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND p.payment_date <= :date_to";
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY p.payment_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de clientes para el filtro
$query = "SELECT id, business_name FROM clients ORDER BY business_name";
$stmt = $db->query($query);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$totals = [
    'transfer' => 0,
    'cash' => 0,
    'check' => 0,
    'card' => 0,
    'total' => 0
];

foreach ($payments as $payment) {
    $totals[$payment['payment_method']] += $payment['amount'];
    $totals['total'] += $payment['amount'];
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Registro de Pagos</h2>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">Total Pagos</div>
            <div class="stat-value">$<?php echo number_format($totals['total'], 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Transferencias</div>
            <div class="stat-value">$<?php echo number_format($totals['transfer'], 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Efectivo</div>
            <div class="stat-value">$<?php echo number_format($totals['cash'], 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Cheques</div>
            <div class="stat-value">$<?php echo number_format($totals['check'], 2); ?></div>
        </div>
    </div>

    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-group">
                <label for="client_id">Cliente:</label>
                <select name="client_id" id="client_id" class="form-control">
                    <option value="">Todos</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>" 
                                <?php echo $client_id === $client['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['business_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

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
                        <th>Cliente</th>
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
                            <td><?php echo htmlspecialchars($payment['business_name']); ?></td>
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
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 