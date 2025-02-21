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

$action_filter = isset($_GET['action']) ? cleanInput($_GET['action']) : '';
$date_from = isset($_GET['date_from']) ? cleanInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? cleanInput($_GET['date_to']) : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Construir la consulta base
$query = "SELECT al.*, u.name as user_name, u.email as user_email 
          FROM activity_logs al 
          LEFT JOIN users u ON u.id = al.user_id 
          WHERE 1=1";

$params = [];

// Agregar filtros si existen
if ($action_filter) {
    $query .= " AND al.action = :action";
    $params[':action'] = $action_filter;
}

if ($date_from) {
    $query .= " AND DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($user_id) {
    $query .= " AND al.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

// Obtener total de registros para paginación
$count_query = str_replace("al.*, u.name as user_name, u.email as user_email", "COUNT(*) as total", $query);
$stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Agregar ordenamiento y límites
$query .= " ORDER BY al.created_at DESC LIMIT :offset, :per_page";
$params[':offset'] = $offset;
$params[':per_page'] = $per_page;

// Ejecutar consulta principal
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de usuarios para el filtro
$query = "SELECT id, name FROM users ORDER BY name";
$stmt = $db->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener tipos de acciones únicas para el filtro
$query = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
$stmt = $db->query($query);
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Registro de Actividades</h2>
    </div>

    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="action">Tipo de Acción:</label>
                    <select name="action" id="action" class="form-control">
                        <option value="">Todas las acciones</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo $action; ?>" 
                                    <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="user_id">Usuario:</label>
                    <select name="user_id" id="user_id" class="form-control">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $user_id === $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                        <?php endforeach; ?>
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
        <?php if (empty($logs)): ?>
            <p class="no-data">No se encontraron registros</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td>
                                <?php if ($log['user_id']): ?>
                                    <?php echo htmlspecialchars($log['user_name']); ?>
                                <?php else: ?>
                                    <span class="system-user">Sistema</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="action-badge <?php echo $log['action']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter([
                            'action' => $action_filter,
                            'user_id' => $user_id,
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