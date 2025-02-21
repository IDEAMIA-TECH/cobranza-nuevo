<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';
require_once '../../includes/Mailer.php';
require_once '../../includes/SecurityHelper.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
if (!isAdmin()) {
    header("Location: ../../index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Obtener clientes pendientes
$query = "SELECT c.*, u.email, u.status 
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE u.status = 'pending' 
          ORDER BY c.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generar un único token CSRF para todos los formularios
$csrf_token = SecurityHelper::generateCSRFToken();

include '../../includes/header.php';
?>

<div class="container">
    <h2>Clientes Pendientes de Aprobación</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <?php if (empty($pending_clients)): ?>
        <p>No hay clientes pendientes de aprobación.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Razón Social</th>
                        <th>RFC</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_clients as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['business_name']); ?></td>
                            <td><?php echo htmlspecialchars($client['rfc']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['phone']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" action="approve.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $client['user_id']; ?>">
                                        <button type="submit" class="btn btn-small btn-success">
                                            <i class="fas fa-check"></i> Aprobar
                                        </button>
                                    </form>
                                    <form method="POST" action="reject.php" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $client['user_id']; ?>">
                                        <button type="submit" class="btn btn-small btn-danger">
                                            <i class="fas fa-times"></i> Rechazar
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?> 