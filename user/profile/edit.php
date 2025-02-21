<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';
require_once '../../includes/SecurityHelper.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Obtener información actual del usuario
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (!SecurityHelper::validateCSRF()) {
            throw new Exception("Error de validación CSRF");
        }

        // Validar y limpiar datos
        $phone = cleanInput($_POST['phone']);
        $contact_name = strtoupper(cleanInput($_POST['contact_name']));
        $street = strtoupper(cleanInput($_POST['street']));
        $ext_number = strtoupper(cleanInput($_POST['ext_number']));
        $int_number = !empty($_POST['int_number']) ? strtoupper(cleanInput($_POST['int_number'])) : null;
        $neighborhood = strtoupper(cleanInput($_POST['neighborhood']));
        $city = strtoupper(cleanInput($_POST['city']));
        $state = strtoupper(cleanInput($_POST['state']));
        $zip_code = cleanInput($_POST['zip_code']);

        // Validar código postal
        if (!preg_match('/^[0-9]{5}$/', $zip_code)) {
            throw new Exception('El código postal debe tener 5 dígitos');
        }

        $db->beginTransaction();

        // Actualizar información del cliente
        $query = "UPDATE clients SET 
                    street = :street,
                    ext_number = :ext_number,
                    int_number = :int_number,
                    neighborhood = :neighborhood,
                    city = :city,
                    state = :state,
                    zip_code = :zip_code,
                    phone = :phone,
                    contact_name = :contact_name
                 WHERE user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":street", $street);
        $stmt->bindParam(":ext_number", $ext_number);
        $stmt->bindParam(":int_number", $int_number);
        $stmt->bindParam(":neighborhood", $neighborhood);
        $stmt->bindParam(":city", $city);
        $stmt->bindParam(":state", $state);
        $stmt->bindParam(":zip_code", $zip_code);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":contact_name", $contact_name);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->execute();

        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                  VALUES (:user_id, 'update_profile', 'Actualizó su información de perfil', :ip_address)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
        $stmt->execute();

        $db->commit();
        $_SESSION['success'] = "Información actualizada exitosamente";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h2>Editar Perfil</h2>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST" class="form-container">
        <?php echo SecurityHelper::getCSRFTokenField(); ?>
        
        <div class="form-section">
            <h3>Información de Contacto</h3>
            <div class="form-group">
                <label for="contact_name">Nombre de Contacto:</label>
                <input type="text" id="contact_name" name="contact_name" required 
                       value="<?php echo htmlspecialchars($user['contact_name']); ?>"
                       style="text-transform: uppercase;">
            </div>
            <div class="form-group">
                <label for="phone">Teléfono:</label>
                <input type="tel" id="phone" name="phone" required 
                       value="<?php echo htmlspecialchars($user['phone']); ?>">
            </div>
        </div>

        <div class="form-section">
            <h3>Dirección</h3>
            <div class="form-group">
                <label for="street">Calle:</label>
                <input type="text" id="street" name="street" required 
                       value="<?php echo htmlspecialchars($user['street']); ?>"
                       style="text-transform: uppercase;">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="ext_number">Número Exterior:</label>
                    <input type="text" id="ext_number" name="ext_number" required 
                           value="<?php echo htmlspecialchars($user['ext_number']); ?>"
                           style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label for="int_number">Número Interior:</label>
                    <input type="text" id="int_number" name="int_number" 
                           value="<?php echo htmlspecialchars($user['int_number']); ?>"
                           style="text-transform: uppercase;">
                </div>
            </div>
            <div class="form-group">
                <label for="neighborhood">Colonia:</label>
                <input type="text" id="neighborhood" name="neighborhood" required 
                       value="<?php echo htmlspecialchars($user['neighborhood']); ?>"
                       style="text-transform: uppercase;">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="city">Ciudad:</label>
                    <input type="text" id="city" name="city" required 
                           value="<?php echo htmlspecialchars($user['city']); ?>"
                           style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label for="state">Estado:</label>
                    <input type="text" id="state" name="state" required 
                           value="<?php echo htmlspecialchars($user['state']); ?>"
                           style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label for="zip_code">Código Postal:</label>
                    <input type="text" id="zip_code" name="zip_code" required 
                           pattern="[0-9]{5}"
                           value="<?php echo htmlspecialchars($user['zip_code']); ?>">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?> 