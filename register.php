<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/Mailer.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Convertir todos los datos a mayúsculas
        $email = strtoupper(cleanInput($_POST['email']));
        $password = $_POST['password']; // La contraseña no se convierte a mayúsculas
        $business_name = strtoupper(cleanInput($_POST['business_name']));
        $rfc = strtoupper(cleanInput($_POST['rfc']));
        $tax_regime = cleanInput($_POST['tax_regime']);
        
        // Validar RFC (formato mexicano)
        if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3}$/', $rfc)) {
            throw new Exception('El RFC no tiene un formato válido');
        }
        
        // Validar contraseña
        if (!SecurityHelper::validatePassword($password)) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas, números y caracteres especiales');
        }
        
        // Validar régimen fiscal
        if (!preg_match('/^[0-9]{3}$/', $tax_regime)) {
            throw new Exception('Por favor seleccione un régimen fiscal válido');
        }
        
        // Convertir el código a descripción completa
        $regimes = [
            '601' => '601 - GENERAL DE LEY PERSONAS MORALES',
            '603' => '603 - PERSONAS MORALES CON FINES NO LUCRATIVOS',
            '605' => '605 - SUELDOS Y SALARIOS E INGRESOS ASIMILADOS A SALARIOS',
            '606' => '606 - ARRENDAMIENTO',
            '607' => '607 - RÉGIMEN DE ENAJENACIÓN O ADQUISICIÓN DE BIENES',
            '608' => '608 - DEMÁS INGRESOS',
            '609' => '609 - CONSOLIDACIÓN',
            '610' => '610 - RESIDENTES EN EL EXTRANJERO SIN ESTABLECIMIENTO PERMANENTE EN MÉXICO',
            '611' => '611 - INGRESOS POR DIVIDENDOS (SOCIOS Y ACCIONISTAS)',
            '612' => '612 - PERSONAS FÍSICAS CON ACTIVIDADES EMPRESARIALES Y PROFESIONALES',
            '614' => '614 - INGRESOS POR INTERESES',
            '615' => '615 - RÉGIMEN DE LOS INGRESOS POR OBTENCIÓN DE PREMIOS',
            '616' => '616 - SIN OBLIGACIONES FISCALES',
            '620' => '620 - SOCIEDADES COOPERATIVAS DE PRODUCCIÓN QUE OPTAN POR DIFERIR SUS INGRESOS',
            '621' => '621 - INCORPORACIÓN FISCAL',
            '622' => '622 - ACTIVIDADES AGRÍCOLAS, GANADERAS, SILVÍCOLAS Y PESQUERAS',
            '623' => '623 - OPCIONAL PARA GRUPOS DE SOCIEDADES',
            '624' => '624 - COORDINADOS',
            '625' => '625 - RÉGIMEN DE LAS ACTIVIDADES EMPRESARIALES CON INGRESOS A TRAVÉS DE PLATAFORMAS TECNOLÓGICAS',
            '626' => '626 - RÉGIMEN SIMPLIFICADO DE CONFIANZA'
        ];
        
        $tax_regime = $regimes[$tax_regime] ?? throw new Exception('Régimen fiscal no válido');
        
        $street = strtoupper(cleanInput($_POST['street']));
        $ext_number = strtoupper(cleanInput($_POST['ext_number']));
        $int_number = !empty($_POST['int_number']) ? strtoupper(cleanInput($_POST['int_number'])) : null;
        $neighborhood = strtoupper(cleanInput($_POST['neighborhood']));
        $city = strtoupper(cleanInput($_POST['city']));
        $state = strtoupper(cleanInput($_POST['state']));
        $zip_code = strtoupper(cleanInput($_POST['zip_code']));
        $phone = strtoupper(cleanInput($_POST['phone']));
        $contact_name = strtoupper(cleanInput($_POST['contact_name']));
        
        $db->beginTransaction();
        
        // Verificar si el email ya existe
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('El correo electrónico ya está registrado');
        }
        
        // Crear usuario
        $query = "INSERT INTO users (email, password, role, status) 
                  VALUES (:email, :password, 'client', 'pending')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", password_hash($password, PASSWORD_DEFAULT));
        $stmt->execute();
        
        $user_id = $db->lastInsertId();
        
        // Crear cliente
        $query = "INSERT INTO clients (user_id, business_name, rfc, tax_regime, street, 
                                     ext_number, int_number, neighborhood, city, state, 
                                     zip_code, phone, contact_name) 
                  VALUES (:user_id, :business_name, :rfc, :tax_regime, :street, 
                         :ext_number, :int_number, :neighborhood, :city, :state, 
                         :zip_code, :phone, :contact_name)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":business_name", $business_name);
        $stmt->bindParam(":rfc", $rfc);
        $stmt->bindParam(":tax_regime", $tax_regime);
        $stmt->bindParam(":street", $street);
        $stmt->bindParam(":ext_number", $ext_number);
        $stmt->bindParam(":int_number", $int_number);
        $stmt->bindParam(":neighborhood", $neighborhood);
        $stmt->bindParam(":city", $city);
        $stmt->bindParam(":state", $state);
        $stmt->bindParam(":zip_code", $zip_code);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":contact_name", $contact_name);
        $stmt->execute();
        
        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                  VALUES (:user_id, 'register', :description, :ip_address)";
        $description = "Nuevo registro de cliente";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        
        $db->commit();
        
        // Enviar correo de bienvenida
        $mailer = new Mailer();
        $mailer->sendWelcomeEmail($email, $business_name);
        
        // Enviar notificación al administrador
        $clientData = [
            'business_name' => $business_name,
            'rfc' => $rfc,
            'email' => $email,
            'phone' => $phone,
            'contact_name' => $contact_name
        ];
        $mailer->sendAdminNewRegistrationNotification($clientData);
        
        $success = "Registro exitoso. Por favor espere la activación de su cuenta.";
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <h2>Registro de Cliente</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php else: ?>
        <form method="POST" class="auth-form">
            <?php echo SecurityHelper::getCSRFTokenField(); ?>
            
            <div class="form-section">
                <h3>Información de Acceso</h3>
                
                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" required 
                           style="text-transform: uppercase;">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <small>Mínimo 8 caracteres, incluir mayúsculas, minúsculas, números y caracteres especiales</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Información Fiscal</h3>
                
                <div class="form-group">
                    <label for="business_name">Razón Social:</label>
                    <input type="text" id="business_name" name="business_name" required 
                           style="text-transform: uppercase;">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="rfc">RFC:</label>
                        <input type="text" id="rfc" name="rfc" required 
                               
                               style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="tax_regime">Régimen Fiscal:</label>
                        <select id="tax_regime" name="tax_regime" required>
                            <option value="">Seleccione un régimen fiscal</option>
                            <option value="601">601 - General de Ley Personas Morales</option>
                            <option value="603">603 - Personas Morales con Fines no Lucrativos</option>
                            <option value="605">605 - Sueldos y Salarios e Ingresos Asimilados a Salarios</option>
                            <option value="606">606 - Arrendamiento</option>
                            <option value="607">607 - Régimen de Enajenación o Adquisición de Bienes</option>
                            <option value="608">608 - Demás ingresos</option>
                            <option value="609">609 - Consolidación</option>
                            <option value="610">610 - Residentes en el Extranjero sin Establecimiento Permanente en México</option>
                            <option value="611">611 - Ingresos por Dividendos (socios y accionistas)</option>
                            <option value="612">612 - Personas Físicas con Actividades Empresariales y Profesionales</option>
                            <option value="614">614 - Ingresos por intereses</option>
                            <option value="615">615 - Régimen de los ingresos por obtención de premios</option>
                            <option value="616">616 - Sin obligaciones fiscales</option>
                            <option value="620">620 - Sociedades Cooperativas de Producción que optan por diferir sus ingresos</option>
                            <option value="621">621 - Incorporación Fiscal</option>
                            <option value="622">622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras</option>
                            <option value="623">623 - Opcional para Grupos de Sociedades</option>
                            <option value="624">624 - Coordinados</option>
                            <option value="625">625 - Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas</option>
                            <option value="626">626 - Régimen Simplificado de Confianza</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Dirección</h3>
                
                <div class="form-group">
                    <label for="street">Calle:</label>
                    <input type="text" id="street" name="street" required 
                           style="text-transform: uppercase;">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="ext_number">Número Exterior:</label>
                        <input type="text" id="ext_number" name="ext_number" required 
                               style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="int_number">Número Interior:</label>
                        <input type="text" id="int_number" name="int_number" 
                               style="text-transform: uppercase;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="neighborhood">Colonia:</label>
                    <input type="text" id="neighborhood" name="neighborhood" required 
                           style="text-transform: uppercase;">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">Ciudad:</label>
                        <input type="text" id="city" name="city" required 
                               style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="state">Estado:</label>
                        <input type="text" id="state" name="state" required 
                               style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="zip_code">Código Postal:</label>
                        <input type="text" id="zip_code" name="zip_code" required 
                               pattern="[0-9]{5}" style="text-transform: uppercase;">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Información de Contacto</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_name">Nombre de Contacto:</label>
                        <input type="text" id="contact_name" name="contact_name" required 
                               style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="phone">Teléfono:</label>
                        <input type="tel" id="phone" name="phone" required 
                               style="text-transform: uppercase;">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Registrarse</button>
                <a href="login.php" class="btn btn-link">¿Ya tienes cuenta? Inicia sesión</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?> 