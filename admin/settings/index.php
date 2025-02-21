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

$success = '';
$error = '';

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Error de validación CSRF');
        }

        $db->beginTransaction();
        
        // Actualizar configuraciones
        $stmt = $db->prepare("UPDATE system_settings SET setting_value = :value WHERE setting_key = :key");
        
        // Procesar cada configuración
        $settings = [
            'company_name',
            'company_address',
            'company_phone',
            'company_email',
            'system_name',
            'footer_text',
            'smtp_host',
            'smtp_user',
            'smtp_password',
            'smtp_port',
            'smtp_from'
        ];
        
        foreach ($settings as $key) {
            if (isset($_POST[$key])) {
                $stmt->bindValue(':key', $key);
                $stmt->bindValue(':value', $_POST[$key]);
                $stmt->execute();
            }
        }
        
        // Procesar logo si se subió uno nuevo
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['company_logo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_path = __DIR__ . '/../../assets/img/';
                $new_filename = 'logo_' . time() . '.' . $ext;
                
                if (move_uploaded_file($_FILES['company_logo']['tmp_name'], $upload_path . $new_filename)) {
                    $stmt->bindValue(':key', 'company_logo');
                    $stmt->bindValue(':value', '/assets/img/' . $new_filename);
                    $stmt->execute();
                }
            }
        }
        
        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                  VALUES (:user_id, 'update_settings', 'Configuración del sistema actualizada', :ip)";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':user_id', $_SESSION['user_id']);
        $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        
        $db->commit();
        $success = "Configuración actualizada correctamente.";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error al actualizar la configuración: " . $e->getMessage();
    }
}

// Obtener configuración actual
$query = "SELECT * FROM system_settings ORDER BY setting_key";
$stmt = $db->query($query);
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Configuración del Sistema</h2>
        <div class="header-actions">
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="settings-form">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div class="settings-sections">
            <div class="settings-section">
                <h3>Información de la Empresa</h3>
                
                <div class="form-group">
                    <label for="company_name">Nombre de la Empresa:</label>
                    <input type="text" id="company_name" name="company_name" 
                           value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="company_logo">Logo de la Empresa:</label>
                    <input type="file" id="company_logo" name="company_logo" accept="image/*">
                    <?php if ($settings['company_logo']): ?>
                        <div class="current-logo">
                            <img src="<?php echo getBaseUrl() . htmlspecialchars($settings['company_logo']); ?>" 
                                 alt="Logo actual" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="company_address">Dirección:</label>
                    <textarea id="company_address" name="company_address" rows="3"><?php 
                        echo htmlspecialchars($settings['company_address']); 
                    ?></textarea>
                </div>

                <div class="form-group">
                    <label for="company_phone">Teléfono:</label>
                    <input type="text" id="company_phone" name="company_phone" 
                           value="<?php echo htmlspecialchars($settings['company_phone']); ?>">
                </div>

                <div class="form-group">
                    <label for="company_email">Email de Contacto:</label>
                    <input type="email" id="company_email" name="company_email" 
                           value="<?php echo htmlspecialchars($settings['company_email']); ?>">
                </div>
            </div>

            <div class="settings-section">
                <h3>Configuración del Sistema</h3>
                
                <div class="form-group">
                    <label for="system_name">Nombre del Sistema:</label>
                    <input type="text" id="system_name" name="system_name" 
                           value="<?php echo htmlspecialchars($settings['system_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="footer_text">Texto del Pie de Página:</label>
                    <input type="text" id="footer_text" name="footer_text" 
                           value="<?php echo htmlspecialchars($settings['footer_text']); ?>">
                </div>
            </div>

            <div class="settings-section">
                <h3>Configuración de Correo</h3>
                
                <div class="form-group">
                    <label for="smtp_host">Servidor SMTP:</label>
                    <input type="text" id="smtp_host" name="smtp_host" 
                           value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="smtp_user">Usuario SMTP:</label>
                    <input type="text" id="smtp_user" name="smtp_user" 
                           value="<?php echo htmlspecialchars($settings['smtp_user']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="smtp_password">Contraseña SMTP:</label>
                    <input type="password" id="smtp_password" name="smtp_password" 
                           value="<?php echo htmlspecialchars($settings['smtp_password']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="smtp_port">Puerto SMTP:</label>
                    <input type="text" id="smtp_port" name="smtp_port" 
                           value="<?php echo htmlspecialchars($settings['smtp_port']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="smtp_from">Email Remitente:</label>
                    <input type="email" id="smtp_from" name="smtp_from" 
                           value="<?php echo htmlspecialchars($settings['smtp_from']); ?>" required>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>

<style>
.settings-sections {
    display: grid;
    gap: 2rem;
    margin-bottom: 2rem;
}

.settings-section {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.settings-section h3 {
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.current-logo {
    margin: 1rem 0;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.form-actions {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}
</style>

<?php include '../../includes/footer.php'; ?> 