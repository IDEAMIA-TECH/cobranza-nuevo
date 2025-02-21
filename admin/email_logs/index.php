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

// Parámetros de filtrado y paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

$email_type = isset($_GET['email_type']) ? cleanInput($_GET['email_type']) : '';
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : '';
$status = isset($_GET['status']) ? cleanInput($_GET['status']) : '';

// Construir la consulta base
$query = "SELECT el.*, 
          CASE 
              WHEN el.email_type = 'new_invoice' THEN i.invoice_number
              WHEN el.email_type = 'payment_confirmation' THEN CONCAT(i.invoice_number, ' - Pago')
              ELSE i.invoice_number
          END as reference_number
          FROM email_logs el
          LEFT JOIN invoices i ON i.id = el.related_id
          WHERE 1=1";

$params = array();

// Agregar filtros si existen
if ($email_type) {
    $query .= " AND el.email_type = :email_type";
    $params[':email_type'] = $email_type;
}

if ($date_from) {
    $query .= " AND DATE(el.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(el.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($status) {
    $query .= " AND el.status = :status";
    $params[':status'] = $status;
}

// Obtener total de registros para paginación
$count_query = str_replace("el.*, CASE", "COUNT(*) as total, CASE", $query);
$count_query = preg_replace("/SELECT.*?FROM/s", "SELECT COUNT(*) as total FROM", $query);
$stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Agregar ordenamiento y límites
$query .= " ORDER BY el.created_at DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = $per_page;
$params[':offset'] = $offset;

// Ejecutar consulta principal
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Historial de Correos Enviados</h2>
        <div class="header-actions">
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="email_type">Tipo de Correo:</label>
                    <select name="email_type" id="email_type" class="form-control">
                        <option value="">Todos</option>
                        <option value="new_invoice" <?php echo $email_type === 'new_invoice' ? 'selected' : ''; ?>>Nueva Factura</option>
                        <option value="payment_confirmation" <?php echo $email_type === 'payment_confirmation' ? 'selected' : ''; ?>>Confirmación de Pago</option>
                        <option value="due_reminder" <?php echo $email_type === 'due_reminder' ? 'selected' : ''; ?>>Recordatorio de Vencimiento</option>
                        <option value="overdue_notice" <?php echo $email_type === 'overdue_notice' ? 'selected' : ''; ?>>Aviso de Vencimiento</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Estado:</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Enviado</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Fallido</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
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

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha y Hora</th>
                    <th>Destinatario</th>
                    <th>Tipo de Correo</th>
                    <th>Referencia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars($log['recipient_name']); ?><br>
                            <small><?php echo htmlspecialchars($log['recipient_email']); ?></small>
                        </td>
                        <td>
                            <span class="email-type-badge <?php echo $log['email_type']; ?>">
                                <?php 
                                    echo match($log['email_type']) {
                                        'new_invoice' => 'Nueva Factura',
                                        'payment_confirmation' => 'Confirmación de Pago',
                                        'due_reminder' => 'Recordatorio',
                                        'overdue_notice' => 'Aviso Vencimiento',
                                        default => ucfirst($log['email_type'])
                                    };
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($log['reference_number']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $log['status']; ?>">
                                <?php echo $log['status'] === 'sent' ? 'Enviado' : 'Fallido'; ?>
                            </span>
                            <?php if ($log['status'] === 'failed' && $log['error_message']): ?>
                                <i class="fas fa-info-circle" title="<?php echo htmlspecialchars($log['error_message']); ?>"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&email_type=<?php echo urlencode($email_type); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                   class="btn <?php echo $page === $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.email-type-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.85em;
}

.email-type-badge.new_invoice {
    background-color: #e3f2fd;
    color: #1565c0;
}

.email-type-badge.payment_confirmation {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.email-type-badge.due_reminder {
    background-color: #fff3e0;
    color: #f57c00;
}

.email-type-badge.overdue_notice {
    background-color: #ffebee;
    color: #c62828;
}

.status-badge {
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 0.85em;
}

.status-badge.sent {
    background-color: #4caf50;
    color: white;
}

.status-badge.failed {
    background-color: #f44336;
    color: white;
}
</style>

<?php include '../../includes/footer.php'; ?> 