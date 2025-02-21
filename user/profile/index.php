<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Obtener información del usuario y cliente
$query = "SELECT u.email, u.status, 
                 c.business_name, c.rfc, c.tax_regime, 
                 c.street, c.ext_number, c.int_number, 
                 c.neighborhood, c.city, c.state, c.zip_code, 
                 c.phone, c.contact_name 
          FROM users u 
          LEFT JOIN clients c ON c.user_id = u.id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="container">
    <h2>Mi Perfil</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="profile-container">
        <div class="profile-section">
            <h3>Información de la Cuenta</h3>
            <div class="section-actions">
                <a href="edit.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Editar Información
                </a>
            </div>
            <div class="info-group">
                <label>Email:</label>
                <span><?php echo htmlspecialchars($user['email']); ?></span>
            </div>
            <div class="info-group">
                <label>Estado:</label>
                <span class="status-badge <?php echo $user['status']; ?>">
                    <?php 
                    $status_labels = [
                        'active' => 'Activo',
                        'pending' => 'Pendiente',
                        'inactive' => 'Inactivo'
                    ];
                    echo $status_labels[$user['status']];
                    ?>
                </span>
            </div>
            <div class="form-actions">
                <a href="change-password.php" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Cambiar Contraseña
                </a>
            </div>
        </div>

        <div class="profile-section">
            <h3>Información Fiscal</h3>
            <div class="info-group">
                <label>Razón Social:</label>
                <span><?php echo htmlspecialchars($user['business_name']); ?></span>
            </div>
            <div class="info-group">
                <label>RFC:</label>
                <span><?php echo htmlspecialchars($user['rfc']); ?></span>
            </div>
            <div class="info-group">
                <label>Régimen Fiscal:</label>
                <span><?php echo htmlspecialchars($user['tax_regime']); ?></span>
            </div>
        </div>

        <div class="profile-section">
            <h3>Dirección</h3>
            <div class="info-group">
                <label>Calle:</label>
                <span><?php echo htmlspecialchars($user['street']); ?></span>
            </div>
            <div class="info-group">
                <label>Número Exterior:</label>
                <span><?php echo htmlspecialchars($user['ext_number']); ?></span>
            </div>
            <?php if (!empty($user['int_number'])): ?>
            <div class="info-group">
                <label>Número Interior:</label>
                <span><?php echo htmlspecialchars($user['int_number']); ?></span>
            </div>
            <?php endif; ?>
            <div class="info-group">
                <label>Colonia:</label>
                <span><?php echo htmlspecialchars($user['neighborhood']); ?></span>
            </div>
            <div class="info-group">
                <label>Ciudad:</label>
                <span><?php echo htmlspecialchars($user['city']); ?></span>
            </div>
            <div class="info-group">
                <label>Estado:</label>
                <span><?php echo htmlspecialchars($user['state']); ?></span>
            </div>
            <div class="info-group">
                <label>Código Postal:</label>
                <span><?php echo htmlspecialchars($user['zip_code']); ?></span>
            </div>
        </div>

        <div class="profile-section">
            <h3>Información de Contacto</h3>
            <div class="info-group">
                <label>Nombre de Contacto:</label>
                <span><?php echo htmlspecialchars($user['contact_name']); ?></span>
            </div>
            <div class="info-group">
                <label>Teléfono:</label>
                <span><?php echo htmlspecialchars($user['phone']); ?></span>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 