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

$error = '';
$success = '';

// Obtener configuración actual
$query = "SELECT * FROM system_settings";
$stmt = $db->query($query);
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $db->beginTransaction();
        
        // Actualizar configuraciones
        $query = "INSERT INTO system_settings (setting_key, setting_value) 
                 VALUES (:key, :value) 
                 ON DUPLICATE KEY UPDATE setting_value = :value";
        $stmt = $db->prepare($query);
        
        // Configuración de correos
        $email_settings = [
            'smtp_host' => cleanInput($_POST['smtp_host']),
            'smtp_port' => cleanInput($_POST['smtp_port']),
            'smtp_user' => cleanInput($_POST['smtp_user']),
            'smtp_password' => !empty($_POST['smtp_password']) ? 
                             cleanInput($_POST['smtp_password']) : 
                             $settings['smtp_password'],
            'smtp_from_email' => cleanInput($_POST['smtp_from_email']),
            'smtp_from_name' => cleanInput($_POST['smtp_from_name'])
        ];
        
        // Configuración de notificaciones
        $notification_settings = [
            'reminder_days' => cleanInput($_POST['reminder_days']),
            'overdue_interval' => cleanInput($_POST['overdue_interval']),
            'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? '1' : '0'
        ];
        
        // Configuración de facturas
        $invoice_settings = [
            'invoice_prefix' => cleanInput($_POST['invoice_prefix']),
            'payment_term_days' => cleanInput($_POST['payment_term_days']),
            'tax_rate' => cleanInput($_POST['tax_rate']),
            'company_name' => cleanInput($_POST['company_name']),
            'company_rfc' => cleanInput($_POST['company_rfc']),
            'company_address' => cleanInput($_POST['company_address']),
            'company_phone' => cleanInput($_POST['company_phone']),
            'company_email' => cleanInput($_POST['company_email'])
        ];
        
        // Guardar todas las configuraciones
        $all_settings = array_merge($email_settings, $notification_settings, $invoice_settings);
        
        foreach ($all_settings as $key => $value) {
            $stmt->bindParam(":key", $key);
            $stmt->bindParam(":value", $value);
            $stmt->execute();
        }
        
        // Registrar la actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                 VALUES (:user_id, 'update_settings', :description, :ip_address)";
        $description = "Actualizó la configuración del sistema";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        
        $db->commit();
        $success = "Configuración actualizada correctamente.";
        
        // Actualizar configuraciones en memoria
        $settings = $all_settings;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Configuración del Sistema</h2>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" class="settings-form">
        <div class="settings-sections">
            <div class="settings-section">
                <h3>Configuración de Correo</h3>
                
                <div class="form-group">
                    <label for="smtp_host">Servidor SMTP:</label>
                    <input type="text" id="smtp_host" name="smtp_host" 
                           value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="smtp_port">Puerto SMTP:</label>
                        <input type="number" id="smtp_port" name="smtp_port" 
                               value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="smtp_user">Usuario SMTP:</label>
                        <input type="text" id="smtp_user" name="smtp_user" 
                               value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="smtp_password">Contraseña SMTP:</label>
                    <input type="password" id="smtp_password" name="smtp_password" 
                           placeholder="Dejar en blanco para mantener la actual">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="smtp_from_email">Correo Remitente:</label>
                        <input type="email" id="smtp_from_email" name="smtp_from_email" 
                               value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="smtp_from_name">Nombre Remitente:</label>
                        <input type="text" id="smtp_from_name" name="smtp_from_name" 
                               value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <h3>Configuración de Notificaciones</h3>
                
                <div class="form-group">
                    <label for="reminder_days">Días para Recordatorio:</label>
                    <input type="text" id="reminder_days" name="reminder_days" 
                           value="<?php echo htmlspecialchars($settings['reminder_days'] ?? '15,10,5'); ?>" 
                           placeholder="Ejemplo: 15,10,5" required>
                    <small>Separar números con comas</small>
                </div>

                <div class="form-group">
                    <label for="overdue_interval">Intervalo de Notificaciones Vencidas (días):</label>
                    <input type="number" id="overdue_interval" name="overdue_interval" 
                           value="<?php echo htmlspecialchars($settings['overdue_interval'] ?? '5'); ?>" 
                           min="1" required>
                </div>

                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="enable_email_notifications" 
                               <?php echo ($settings['enable_email_notifications'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        Habilitar notificaciones por correo
                    </label>
                </div>
            </div>

            <div class="settings-section">
                <h3>Configuración de Facturas</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_prefix">Prefijo de Factura:</label>
                        <input type="text" id="invoice_prefix" name="invoice_prefix" 
                               value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'FAC-'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_term_days">Plazo de Pago (días):</label>
                        <input type="number" id="payment_term_days" name="payment_term_days" 
                               value="<?php echo htmlspecialchars($settings['payment_term_days'] ?? '30'); ?>" 
                               min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="tax_rate">Tasa de IVA (%):</label>
                    <input type="number" id="tax_rate" name="tax_rate" 
                           value="<?php echo htmlspecialchars($settings['tax_rate'] ?? '16'); ?>" 
                           min="0" max="100" step="0.01" required>
                </div>
            </div>

            <div class="settings-section">
                <h3>Información de la Empresa</h3>
                
                <div class="form-group">
                    <label for="company_name">Razón Social:</label>
                    <input type="text" id="company_name" name="company_name" 
                           value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="company_rfc">RFC:</label>
                    <input type="text" id="company_rfc" name="company_rfc" 
                           value="<?php echo htmlspecialchars($settings['company_rfc'] ?? ''); ?>" 
                           pattern="^[A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3}$" required>
                </div>

                <div class="form-group">
                    <label for="company_address">Dirección:</label>
                    <textarea id="company_address" name="company_address" rows="3" required><?php 
                        echo htmlspecialchars($settings['company_address'] ?? ''); 
                    ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="company_phone">Teléfono:</label>
                        <input type="tel" id="company_phone" name="company_phone" 
                               value="<?php echo htmlspecialchars($settings['company_phone'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="company_email">Correo Electrónico:</label>
                        <input type="email" id="company_email" name="company_email" 
                               value="<?php echo htmlspecialchars($settings['company_email'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Guardar Configuración
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?> 