<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();

if (!isAdmin()) {
    header("Location: ../../index.php");
    exit();
}

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$client_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Obtener información del cliente
$query = "SELECT c.*, u.email, u.status, u.id as user_id
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE c.id = :client_id";

$stmt = $db->prepare($query);
$stmt->bindParam(":client_id", $client_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: index.php");
    exit();
}

$client = $stmt->fetch(PDO::FETCH_ASSOC);
$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();
        
        // Actualizar información del usuario
        $email = cleanInput($_POST['email']);
        $status = cleanInput($_POST['status']);
        
        // Verificar si el email ya existe (excluyendo el usuario actual)
        $query = "SELECT id FROM users WHERE email = :email AND id != :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":user_id", $client['user_id']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("El correo electrónico ya está registrado para otro usuario.");
        }
        
        // Actualizar usuario
        $query = "UPDATE users SET email = :email, status = :status WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":user_id", $client['user_id']);
        $stmt->execute();
        
        // Actualizar información del cliente
        $query = "UPDATE clients SET 
                    business_name = :business_name,
                    rfc = :rfc,
                    tax_regime = :tax_regime,
                    street = :street,
                    ext_number = :ext_number,
                    int_number = :int_number,
                    neighborhood = :neighborhood,
                    city = :city,
                    state = :state,
                    zip_code = :zip_code,
                    phone = :phone
                 WHERE id = :client_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":business_name", $_POST['business_name']);
        $stmt->bindParam(":rfc", $_POST['rfc']);
        $stmt->bindParam(":tax_regime", $_POST['tax_regime']);
        $stmt->bindParam(":street", $_POST['street']);
        $stmt->bindParam(":ext_number", $_POST['ext_number']);
        $stmt->bindParam(":int_number", $_POST['int_number']);
        $stmt->bindParam(":neighborhood", $_POST['neighborhood']);
        $stmt->bindParam(":city", $_POST['city']);
        $stmt->bindParam(":state", $_POST['state']);
        $stmt->bindParam(":zip_code", $_POST['zip_code']);
        $stmt->bindParam(":phone", $_POST['phone']);
        $stmt->bindParam(":client_id", $client_id);
        $stmt->execute();
        
        // Registrar la actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                 VALUES (:user_id, 'edit_client', :description, :ip_address)";
        $description = "Actualizó la información del cliente: " . $_POST['business_name'];
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        
        $db->commit();
        $success = "Información del cliente actualizada correctamente.";
        
        // Actualizar datos en memoria
        $client = array_merge($client, $_POST);
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Editar Cliente</h2>
        <div class="header-actions">
            <a href="view.php?id=<?php echo $client_id; ?>" class="btn btn-secondary">Volver</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="edit-form">
        <div class="form-sections">
            <div class="form-section">
                <h3>Información de Cuenta</h3>
                
                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($client['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="status">Estado:</label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="active" <?php echo $client['status'] === 'active' ? 'selected' : ''; ?>>
                            Activo
                        </option>
                        <option value="inactive" <?php echo $client['status'] === 'inactive' ? 'selected' : ''; ?>>
                            Inactivo
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <h3>Información Fiscal</h3>
                
                <div class="form-group">
                    <label for="business_name">Razón Social:</label>
                    <input type="text" id="business_name" name="business_name" 
                           value="<?php echo htmlspecialchars($client['business_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="rfc">RFC:</label>
                    <input type="text" id="rfc" name="rfc" 
                           value="<?php echo htmlspecialchars($client['rfc']); ?>" 
                           required maxlength="13" pattern="^[A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3}$">
                </div>

                <div class="form-group">
                    <label for="tax_regime">Régimen Fiscal:</label>
                    <input type="text" id="tax_regime" name="tax_regime" 
                           value="<?php echo htmlspecialchars($client['tax_regime']); ?>" required>
                </div>
            </div>

            <div class="form-section">
                <h3>Dirección</h3>
                
                <div class="form-group">
                    <label for="street">Calle:</label>
                    <input type="text" id="street" name="street" 
                           value="<?php echo htmlspecialchars($client['street']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="ext_number">Número Exterior:</label>
                        <input type="text" id="ext_number" name="ext_number" 
                               value="<?php echo htmlspecialchars($client['ext_number']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="int_number">Número Interior:</label>
                        <input type="text" id="int_number" name="int_number" 
                               value="<?php echo htmlspecialchars($client['int_number']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="neighborhood">Colonia:</label>
                    <input type="text" id="neighborhood" name="neighborhood" 
                           value="<?php echo htmlspecialchars($client['neighborhood']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">Ciudad:</label>
                        <input type="text" id="city" name="city" 
                               value="<?php echo htmlspecialchars($client['city']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="state">Estado:</label>
                        <input type="text" id="state" name="state" 
                               value="<?php echo htmlspecialchars($client['state']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="zip_code">Código Postal:</label>
                    <input type="text" id="zip_code" name="zip_code" 
                           value="<?php echo htmlspecialchars($client['zip_code']); ?>" 
                           required maxlength="5" pattern="[0-9]{5}">
                </div>
            </div>

            <div class="form-section">
                <h3>Contacto</h3>
                
                <div class="form-group">
                    <label for="phone">Teléfono:</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($client['phone']); ?>" 
                           required pattern="[0-9]{10}">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?> 