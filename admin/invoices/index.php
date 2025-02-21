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
$status = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : '';

// Construir la consulta base
$query = "SELECT i.*, 
          c.business_name, c.rfc,
          DATEDIFF(i.due_date, CURDATE()) as days_to_due,
          CASE 
              WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN 'overdue'
              ELSE i.status 
          END as current_status
          FROM invoices i 
          JOIN clients c ON c.id = i.client_id 
          WHERE 1=1";

$params = [];

// Agregar filtros si existen
if ($status) {
    if ($status === 'overdue') {
        $query .= " AND i.status = 'pending' AND i.due_date < CURDATE()";
    } else {
        $query .= " AND i.status = :status";
        $params[':status'] = $status;
    }
}

if ($client_id) {
    $query .= " AND i.client_id = :client_id";
    $params[':client_id'] = $client_id;
}

if ($date_from) {
    $query .= " AND i.issue_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND i.issue_date <= :date_to";
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY i.issue_date DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de clientes para el filtro
$query = "SELECT id, business_name FROM clients ORDER BY business_name";
$stmt = $db->query($query);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Gestión de Facturas</h2>
        <div class="header-actions">
            <a href="create.php" class="btn">
                <i class="fas fa-plus"></i> Nueva Factura
            </a>
        </div>
    </div>

    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-group">
                <label for="status">Estado:</label>
                <select name="status" id="status" class="form-control">
                    <option value="">Todos</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                    <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Vencidas</option>
                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Pagadas</option>
                </select>
            </div>

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
        <?php if (empty($invoices)): ?>
            <p class="no-data">No se encontraron facturas</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>RFC</th>
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
                            <td><?php echo htmlspecialchars($invoice['business_name']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['rfc']); ?></td>
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
                                          ($invoice['current_status'] === 'paid' ? 'Pagada' : 
                                           $invoice['current_status'])); 
                                    ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="view.php?id=<?php echo $invoice['id']; ?>" 
                                   class="btn btn-small" title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($invoice['status'] !== 'paid'): ?>
                                    <a href="../payments/register.php?invoice_id=<?php echo $invoice['id']; ?>" 
                                       class="btn btn-small btn-success" title="Registrar pago">
                                        <i class="fas fa-dollar-sign"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($invoice['pdf_path']): ?>
                                    <a href="<?php echo htmlspecialchars($invoice['pdf_path']); ?>" 
                                       class="btn btn-small btn-secondary" 
                                       target="_blank" title="Ver PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 