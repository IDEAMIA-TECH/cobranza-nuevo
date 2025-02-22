<?php
ob_start();

require_once '../../includes/functions.php';
require_once '../../config/database.php';
require_once '../../includes/Mailer.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
if (!isAdmin()) {
    header("Location: ../../index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Iniciando proceso de creación de cliente");
    $db = null;
    try {
        $database = new Database();
        $db = $database->getConnection();
        $db->beginTransaction();
        
        error_log("POST Data: " . print_r($_POST, true));
        
        // Definir el array de regímenes fiscales
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
        
        // Procesar archivo CSF si fue subido
        $csf_file_path = null;
        if (isset($_FILES['csf_file']) && $_FILES['csf_file']['error'] === UPLOAD_ERR_OK && !empty($_FILES['csf_file']['name'])) {
            $file = $_FILES['csf_file'];
            $fileName = $file['name'];
            $fileType = $file['type'];
            
            // Validar que sea un PDF
            if (!in_array($fileType, ['application/pdf', 'application/x-pdf'])) {
                throw new Exception('El archivo debe ser un PDF');
            }
            
            // Validar tamaño (máximo 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('El archivo no debe superar los 5MB');
            }
            
            // Generar nombre único
            $uniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $fileName);
            $uploadDir = '../../uploads/csf/';
            
            // Crear directorio si no existe
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Mover archivo
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $uniqueName)) {
                $csf_file_path = 'uploads/csf/' . $uniqueName;
            } else {
                throw new Exception('Error al guardar el archivo CSF');
            }
        }
        
        // Validar campos requeridos
        $required_fields = ['email', 'password', 'business_name', 'rfc', 'tax_regime', 
                          'street', 'ext_number', 'neighborhood', 'city', 'state', 
                          'zip_code', 'contact_phone', 'contact_name'];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                error_log("Campo requerido faltante: " . $field);
                throw new Exception("El campo " . str_replace('_', ' ', $field) . " es requerido");
            }
        }
        
        // Convertir todos los datos a mayúsculas
        $email = strtolower(cleanInput($_POST['email']));
        $password = $_POST['password'];
        $business_name = strtoupper(cleanInput($_POST['business_name']));
        $rfc = strtoupper(cleanInput($_POST['rfc']));
        $tax_regime = cleanInput($_POST['tax_regime']);
        
        error_log("Datos procesados: " . print_r([
            'email' => $email,
            'business_name' => $business_name,
            'rfc' => $rfc,
            'tax_regime' => $tax_regime
        ], true));
        
        // Validar RFC (formato mexicano)
        if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3}$/', $rfc)) {
            throw new Exception('El RFC no tiene un formato válido');
        }
        
        // Validar email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no tiene un formato válido');
        }
        
        // Validar contraseña
        if (!SecurityHelper::validatePassword($password)) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas, números y caracteres especiales');
        }
        
        // Validar régimen fiscal y asegurar que existe en el catálogo
        if (!preg_match('/^[0-9]{3}$/', $tax_regime) || !isset($regimes[$tax_regime])) {
            throw new Exception('Por favor seleccione un régimen fiscal válido');
        }
        
        $tax_regime = $regimes[$tax_regime];
        
        $street = strtoupper(cleanInput($_POST['street']));
        $ext_number = strtoupper(cleanInput($_POST['ext_number']));
        $int_number = !empty($_POST['int_number']) ? strtoupper(cleanInput($_POST['int_number'])) : null;
        $neighborhood = strtoupper(cleanInput($_POST['neighborhood']));
        $city = strtoupper(cleanInput($_POST['city']));
        $state = strtoupper(cleanInput($_POST['state']));
        $zip_code = strtoupper(cleanInput($_POST['zip_code']));
        $phone = strtoupper(cleanInput($_POST['contact_phone']));
        $contact_name = strtoupper(cleanInput($_POST['contact_name']));
        
        // Verificar si el email ya existe
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            error_log("Email duplicado: " . $email);
            throw new Exception('El correo electrónico ya está registrado');
        }
        
        // Crear usuario
        $query = "INSERT INTO users (email, password, role, status) 
                  VALUES (:email, :password, 'client', 'active')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindParam(":password", $hashed_password);
        
        if (!$stmt->execute()) {
            error_log("Error SQL al crear usuario: " . implode(', ', $stmt->errorInfo()));
            throw new Exception('Error al crear el usuario: ' . implode(', ', $stmt->errorInfo()));
        }
        
        $user_id = $db->lastInsertId();
        error_log("Usuario creado con ID: " . $user_id);
        
        // Crear cliente
        $query = "INSERT INTO clients (user_id, business_name, rfc, tax_regime, csf_file_path, 
                                    street, ext_number, int_number, neighborhood, city, state, 
                                    zip_code, phone, contact_name) 
                  VALUES (:user_id, :business_name, :rfc, :tax_regime, :csf_file_path, 
                         :street, :ext_number, :int_number, :neighborhood, :city, :state, 
                         :zip_code, :phone, :contact_name)";
        $stmt = $db->prepare($query);
        
        // Bind todos los parámetros
        $params = [
            ":user_id" => $user_id,
            ":business_name" => $business_name,
            ":rfc" => $rfc,
            ":tax_regime" => $tax_regime,
            ":csf_file_path" => $csf_file_path,
            ":street" => $street,
            ":ext_number" => $ext_number,
            ":int_number" => $int_number,
            ":neighborhood" => $neighborhood,
            ":city" => $city,
            ":state" => $state,
            ":zip_code" => $zip_code,
            ":phone" => $phone,
            ":contact_name" => $contact_name
        ];
        
        error_log("Parámetros para crear cliente: " . print_r($params, true));
        
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value);
        }
        
        if (!$stmt->execute()) {
            error_log("Error SQL al crear cliente: " . implode(', ', $stmt->errorInfo()));
            throw new Exception('Error al crear el cliente: ' . implode(', ', $stmt->errorInfo()));
        }
        
        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                  VALUES (:admin_id, 'create_client', :description, :ip_address)";
        $description = "Creó nuevo cliente: " . $business_name;
        $stmt = $db->prepare($query);
        $stmt->bindParam(":admin_id", $_SESSION['user_id']);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
        $stmt->execute();
        
        $db->commit();
        
        error_log("Cliente creado exitosamente");
        
        $_SESSION['success'] = "Cliente creado exitosamente";
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error en create.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $error = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Crear Nuevo Cliente</h2>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <form method="POST" class="edit-form" enctype="multipart/form-data">
        <?php echo SecurityHelper::getCSRFTokenField(); ?>
        
        <div class="form-sections">
            <div class="form-section">
                <h3>Información de Acceso</h3>
                
                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           style="text-transform: uppercase;">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                    <small>Mínimo 8 caracteres, incluir mayúsculas, minúsculas, números y caracteres especiales</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Información Fiscal</h3>
                
                <div class="form-group">
                    <label for="csf_file">Constancia de Situación Fiscal (PDF):</label>
                    <input type="file" id="csf_file" name="csf_file" accept=".pdf">
                    <small class="form-text text-muted">
                        Opcional: Suba la Constancia de Situación Fiscal en formato PDF para autocompletar los datos
                    </small>
                </div>

                <div id="loading_csf" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Procesando documento...
                </div>

                <div class="form-group">
                    <label for="business_name">Razón Social:</label>
                    <input type="text" id="business_name" name="business_name" class="csf-field" required>
                </div>

                <div class="form-group">
                    <label for="rfc">RFC:</label>
                    <input type="text" id="rfc" name="rfc" required 
                           pattern="^[A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3}$"
                           value="<?php echo isset($_POST['rfc']) ? htmlspecialchars($_POST['rfc']) : ''; ?>"
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
                        <option value="608">608 - Demás Ingresos</option>
                        <option value="609">609 - Consolidación</option>
                        <option value="610">610 - Residentes en el Extranjero sin Establecimiento Permanente en México</option>
                        <option value="611">611 - Ingresos por Dividendos (Socios y Accionistas)</option>
                        <option value="612">612 - Personas Físicas con Actividades Empresariales y Profesionales</option>
                        <option value="614">614 - Ingresos por Intereses</option>
                        <option value="615">615 - Régimen de los Ingresos por Obtención de Premios</option>
                        <option value="616">616 - Sin Obligaciones Fiscales</option>
                        <option value="620">620 - Sociedades Cooperativas de Producción que Optan por Diferir sus Ingresos</option>
                        <option value="621">621 - Incorporación Fiscal</option>
                        <option value="622">622 - Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras</option>
                        <option value="623">623 - Opcional para Grupos de Sociedades</option>
                        <option value="624">624 - Coordinados</option>
                        <option value="625">625 - Régimen de las Actividades Empresariales con Ingresos a través de Plataformas Tecnológicas</option>
                        <option value="626">626 - Régimen Simplificado de Confianza</option>
                    </select>
                    <small class="form-text text-muted">
                        Seleccione el régimen fiscal que corresponda según la Constancia de Situación Fiscal
                    </small>
                </div>
            </div>

            <div class="form-section">
                <h3>Dirección</h3>
                
                <div class="form-group">
                    <label for="street">Calle:</label>
                    <input type="text" id="street" name="street" required 
                           value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : ''; ?>"
                           style="text-transform: uppercase;">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="ext_number">Número Exterior:</label>
                        <input type="text" id="ext_number" name="ext_number" required 
                               value="<?php echo isset($_POST['ext_number']) ? htmlspecialchars($_POST['ext_number']) : ''; ?>"
                               style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="int_number">Número Interior:</label>
                        <input type="text" id="int_number" name="int_number" 
                               value="<?php echo isset($_POST['int_number']) ? htmlspecialchars($_POST['int_number']) : ''; ?>"
                               style="text-transform: uppercase;">
                    </div>
                </div>

                <div class="form-group">
                    <label for="neighborhood">Colonia:</label>
                    <input type="text" id="neighborhood" name="neighborhood" required 
                           value="<?php echo isset($_POST['neighborhood']) ? htmlspecialchars($_POST['neighborhood']) : ''; ?>"
                           style="text-transform: uppercase;">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">Ciudad:</label>
                        <input type="text" id="city" name="city" required 
                               value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>"
                               style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="state">Estado:</label>
                        <input type="text" id="state" name="state" required 
                               value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>"
                               style="text-transform: uppercase;">
                    </div>

                    <div class="form-group">
                        <label for="zip_code">Código Postal:</label>
                        <input type="text" id="zip_code" name="zip_code" required 
                               pattern="[0-9]{5}"
                               value="<?php echo isset($_POST['zip_code']) ? htmlspecialchars($_POST['zip_code']) : ''; ?>"
                               style="text-transform: uppercase;">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Información de Contacto</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="contact_name">Nombre del Contacto:</label>
                        <input type="text" id="contact_name" name="contact_name" required
                               value="<?php echo isset($_POST['contact_name']) ? htmlspecialchars($_POST['contact_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_phone">Teléfono:</label>
                        <input type="tel" id="contact_phone" name="contact_phone" required
                               value="<?php echo isset($_POST['contact_phone']) ? htmlspecialchars($_POST['contact_phone']) : ''; ?>"
                               pattern="[0-9]{10}">
                        <small class="form-text text-muted">Formato: 10 dígitos sin espacios ni guiones</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_email">Correo Electrónico:</label>
                        <input type="email" id="contact_email" name="contact_email"
                               value="<?php echo isset($_POST['contact_email']) ? htmlspecialchars($_POST['contact_email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_position">Puesto:</label>
                        <input type="text" id="contact_position" name="contact_position"
                               value="<?php echo isset($_POST['contact_position']) ? htmlspecialchars($_POST['contact_position']) : ''; ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cliente
            </button>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csfInput = document.getElementById('csf_file');
    const loadingIndicator = document.getElementById('loading_csf');
    
    // Cargar worker de PDF.js
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.js';
    
    csfInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validar tipo de archivo
        if (file.type !== 'application/pdf') {
            alert('Por favor, seleccione un archivo PDF válido');
            csfInput.value = '';
            return;
        }
        
        loadingIndicator.style.display = 'block';
        
        try {
            // Leer el archivo PDF
            const arrayBuffer = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = () => reject(reader.error);
                reader.readAsArrayBuffer(file);
            });
            
            const pdf = await pdfjsLib.getDocument({data: arrayBuffer}).promise;
            const page = await pdf.getPage(1);
            const textContent = await page.getTextContent();
            const text = textContent.items.map(item => item.str).join(' ');
            
            console.log('Texto extraído:', text); // Para debug
            
            // Extraer información usando expresiones regulares
            // RFC - buscar primero en el encabezado
            let rfc = text.match(/RFC:\s*([A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3})/i);
            if (!rfc) {
                // Si no se encuentra, buscar en el cuerpo del documento
                rfc = text.match(/Registro Federal de Contribuyentes[:\s]*([A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3})/i);
            }
            
            // Nombre/Razón Social
            let businessName = text.match(/Registro Federal de Contribuyentes\s+(.*?)\s+Nombre,\s*denominación/i);
            if (!businessName) {
                // Intentar con formato de persona física
                const nombre = text.match(/Nombre\s*\(s\):\s*(.*?)(?:Primer|$)/i);
                const apellido1 = text.match(/Primer\s*Apellido:\s*(.*?)(?:Segundo|$)/i);
                const apellido2 = text.match(/Segundo\s*Apellido:\s*(.*?)(?:Fecha|$)/i);
                
                if (nombre && apellido1) {
                    let nombreCompleto = `${nombre[1].trim()} ${apellido1[1].trim()}`;
                    if (apellido2) {
                        nombreCompleto += ` ${apellido2[1].trim()}`;
                    }
                    businessName = [null, nombreCompleto];
                }
            }
            
            // Si aún no se encuentra, intentar con otro formato común
            if (!businessName) {
                businessName = text.match(/CÉDULA DE IDENTIFICACIÓN FISCAL\s+[A-Z0-9]+\s+(.*?)\s+Nombre,/i);
            }
            
            // Código Postal
            const cp = text.match(/Código\s*Postal:\s*(\d{5})/i);
            
            // Dirección
            const calle = text.match(/Nombre\s*de\s*Vialidad:\s*(.*?)(?:Número|$)/i);
            const numExt = text.match(/Número\s*Exterior:\s*(\d+)/i);
            const numInt = text.match(/Número\s*Interior:\s*(\d+)/i);
            const colonia = text.match(/Nombre\s*de\s*la\s*Colonia:\s*(.*?)(?:Nombre|$)/i);
            const ciudad = text.match(/Nombre\s*del\s*Municipio[^:]*:\s*(.*?)(?:Nombre|$)/i);
            const estado = text.match(/Nombre\s*de\s*la\s*Entidad\s*Federativa:\s*(.*?)(?:\s|$)/i);
            
            // Régimen Fiscal
            let regimen = text.match(/Régimen\s*Fiscal:\s*(\d{3})\s*-?\s*([^,\n]*)/i);
            if (!regimen) {
                // Intentar otros formatos comunes
                regimen = text.match(/RÉGIMEN:\s*(\d{3})\s*-?\s*([^,\n]*)/i);
            }
            
            // Autocompletar campos
            if (rfc) {
                document.getElementById('rfc').value = rfc[1].toUpperCase();
                console.log('RFC encontrado:', rfc[1]);
            }
            if (businessName) {
                document.getElementById('business_name').value = businessName[1].trim().toUpperCase();
                console.log('Razón Social encontrada:', businessName[1]);
            }
            if (regimen) {
                const regimenSelect = document.getElementById('tax_regime');
                const regimenCode = regimen[1];
                console.log('Régimen encontrado:', regimenCode, regimen[2]);
                
                // Buscar y seleccionar la opción correcta en el select
                for (let option of regimenSelect.options) {
                    if (option.value === regimenCode) {
                        option.selected = true;
                        break;
                    }
                }
            }
            if (cp) {
                document.getElementById('zip_code').value = cp[1];
                console.log('CP encontrado:', cp[1]);
            }
            // Autocompletar dirección
            if (calle) document.getElementById('street').value = calle[1].trim().toUpperCase();
            if (numExt) document.getElementById('ext_number').value = numExt[1];
            if (numInt) document.getElementById('int_number').value = numInt[1];
            if (colonia) document.getElementById('neighborhood').value = colonia[1].trim().toUpperCase();
            if (ciudad) document.getElementById('city').value = ciudad[1].trim().toUpperCase();
            if (estado) document.getElementById('state').value = estado[1].trim().toUpperCase();
            
            // Guardar el archivo
            const formData = new FormData();
            formData.append('csf_file', file);
            formData.append('action', 'upload_csf');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            
            const response = await fetch('process_csf.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('Error procesando CSF:', error);
            alert('Error al procesar el documento: ' + error.message);
            csfInput.value = '';
        } finally {
            loadingIndicator.style.display = 'none';
        }
    });
});
</script>

<?php 
include '../../includes/footer.php';
ob_end_flush();
?> 