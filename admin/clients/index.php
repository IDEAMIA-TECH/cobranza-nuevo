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
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

// Construir la consulta base
$query = "SELECT c.*, u.email, u.status 
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE 1=1";

$params = [];

// Agregar filtros si existen
if ($status) {
    $query .= " AND u.status = :status";
    $params[':status'] = $status;
}

if ($search) {
    $query .= " AND (c.business_name LIKE :search 
                OR c.rfc LIKE :search 
                OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY c.business_name ASC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Gestión de Clientes</h2>
        <div class="header-actions">
            <a href="create.php" class="btn">Nuevo Cliente</a>
        </div>
    </div>

    <div class="filters-section">
        <form method="GET" class="filters-form">
            <div class="form-group">
                <label for="status">Estado:</label>
                <select name="status" id="status" class="form-control">
                    <option value="">Todos</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activos</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                </select>
            </div>

            <div class="form-group">
                <label for="search">Buscar:</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Razón social, RFC o email"
                       class="form-control">
            </div>

            <button type="submit" class="btn">Filtrar</button>
            <a href="index.php" class="btn btn-secondary">Limpiar</a>
        </form>
    </div>

    <div class="table-responsive">
        <?php if (empty($clients)): ?>
            <p class="no-data">No se encontraron clientes</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Razón Social</th>
                        <th>RFC</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
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
                                <span class="status-badge <?php echo $client['status']; ?>">
                                    <?php 
                                    echo $client['status'] === 'active' ? 'Activo' : 
                                         ($client['status'] === 'pending' ? 'Pendiente' : 'Inactivo');
                                    ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="view.php?id=<?php echo $client['id']; ?>" 
                                   class="btn btn-small" title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $client['id']; ?>" 
                                   class="btn btn-small btn-secondary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($client['status'] === 'pending'): ?>
                                    <a href="approve.php?id=<?php echo $client['id']; ?>" 
                                       class="btn btn-small btn-success" title="Aprobar">
                                        <i class="fas fa-check"></i>
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