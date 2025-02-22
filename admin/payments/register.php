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

// Verificar si se proporcionó un ID de factura
if (!isset($_GET['invoice_id']) || !is_numeric($_GET['invoice_id'])) {
    header("Location: ../invoices/index.php");
    exit();
}

$invoice_id = (int)$_GET['invoice_id'];
$database = new Database();
$db = $database->getConnection();

// Obtener información de la factura
$query = "SELECT i.*, 
          c.business_name, c.rfc, c.phone,
          u.email,
          c.street, c.ext_number, c.int_number, c.neighborhood,
          c.city, c.state, c.zip_code 
          FROM invoices i 
          JOIN clients c ON c.id = i.client_id 
          JOIN users u ON u.id = c.user_id
          WHERE i.id = :invoice_id 
          AND i.status != 'paid'";

$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: ../invoices/index.php");
    exit();
}

$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
$error = '';
$success = '';
$payment_data = [];

// Función para procesar el XML de pago
function parsePaymentXML($xml_content) {
    $xml = simplexml_load_string($xml_content);
    if (!$xml) {
        throw new Exception("Error al procesar el archivo XML");
    }

    // Registrar los namespaces del CFDI
    $namespaces = $xml->getNamespaces(true);
    $xml->registerXPathNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');
    $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');

    // Extraer datos del pago
    $data = [
        'uuid' => (string)$xml->xpath('//tfd:TimbreFiscalDigital/@UUID')[0],
        'payment_date' => (string)$xml->xpath('//pago20:Pago/@FechaPago')[0],
        'payment_method' => (string)$xml->xpath('//pago20:Pago/@FormaDePagoP')[0],
        'amount' => (float)$xml->xpath('//pago20:Pago/@Monto')[0],
        'related_documents' => []
    ];

    // Obtener documentos relacionados
    foreach ($xml->xpath('//pago20:DoctoRelacionado') as $doc) {
        $data['related_documents'][] = [
            'uuid' => (string)$doc->attributes()->IdDocumento,
            'amount' => (float)$doc->attributes()->ImpPagado
        ];
    }

    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Procesar archivo XML si fue subido
        if (isset($_FILES['xml_file']) && $_FILES['xml_file']['error'] === UPLOAD_ERR_OK) {
            $xml_content = file_get_contents($_FILES['xml_file']['tmp_name']);
            $payment_data = parsePaymentXML($xml_content);

            // Verificar que el UUID relacionado coincida con la factura
            $found_invoice = false;
            foreach ($payment_data['related_documents'] as $doc) {
                if ($doc['uuid'] === $invoice['invoice_uuid']) {
                    $payment_data['amount'] = $doc['amount'];
                    $found_invoice = true;
                    break;
                }
            }

            if (!$found_invoice) {
                throw new Exception("El XML no corresponde a un pago para esta factura");
            }

            // Guardar el archivo XML temporalmente
            $temp_xml_directory = '../../uploads/temp/';
            if (!file_exists($temp_xml_directory)) {
                mkdir($temp_xml_directory, 0755, true);
            }
            $xml_filename = $payment_data['uuid'] . '.xml';
            move_uploaded_file($_FILES['xml_file']['tmp_name'], $temp_xml_directory . $xml_filename);
            $payment_data['xml_path'] = 'uploads/temp/' . $xml_filename;

            $success = "XML procesado correctamente. Por favor, verifique los datos y complete la información adicional.";
        }

        // Si se envió el formulario completo
        if (isset($_POST['save_payment'])) {
            $db->beginTransaction();
            
            // Validar datos del pago
            $amount = !empty($payment_data['amount']) ? $payment_data['amount'] : floatval($_POST['amount']);
            $payment_date = !empty($payment_data['payment_date']) ? date('Y-m-d', strtotime($payment_data['payment_date'])) : cleanInput($_POST['payment_date']);
            $payment_method = !empty($payment_data['payment_method']) ? $payment_data['payment_method'] : cleanInput($_POST['payment_method']);
            $reference = cleanInput($_POST['reference']);
            $notes = cleanInput($_POST['notes']);
            
            if ($amount <= 0) {
                throw new Exception("El monto debe ser mayor a cero.");
            }
            
            // Verificar que el monto no exceda el total pendiente
            $query = "SELECT 
                        i.total_amount,
                        i.total_amount - COALESCE(SUM(p.amount), 0) as pending_amount
                      FROM invoices i 
                      LEFT JOIN payments p ON p.invoice_id = i.id
                      WHERE i.id = :invoice_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":invoice_id", $invoice_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($amount > $result['pending_amount']) {
                throw new Exception("El monto excede el saldo pendiente de la factura.");
            }
            
            // Registrar el pago
            $payment_uuid = isset($payment_data['uuid']) ? $payment_data['uuid'] : uniqid();
            $xml_path = isset($payment_data['xml_path']) ? $payment_data['xml_path'] : '';
            
            $query = "INSERT INTO payments 
                      (invoice_id, amount, payment_date, payment_method, reference, notes, created_by,
                       payment_uuid, xml_path, status) 
                      VALUES 
                      (:invoice_id, :amount, :payment_date, :payment_method, :reference, :notes, :created_by,
                       :payment_uuid, :xml_path, 'processed')";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":invoice_id", $invoice_id);
            $stmt->bindParam(":amount", $amount);
            $stmt->bindParam(":payment_date", $payment_date);
            $stmt->bindParam(":payment_method", $payment_method);
            $stmt->bindParam(":reference", $reference);
            $stmt->bindParam(":notes", $notes);
            $stmt->bindParam(":created_by", $_SESSION['user_id']);
            $stmt->bindParam(":payment_uuid", $payment_uuid);
            $stmt->bindParam(":xml_path", $xml_path);
            $stmt->execute();
            
            // Verificar si con este pago se completa el total
            $new_pending = $result['pending_amount'] - $amount;
            if ($new_pending <= 0) {
                // Actualizar estado de la factura a pagada
                $query = "UPDATE invoices SET status = 'paid' WHERE id = :invoice_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":invoice_id", $invoice_id);
                $stmt->execute();
            }
            
            // Registrar la actividad
            $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                     VALUES (:user_id, 'register_payment', :description, :ip_address)";
            $description = "Registró pago de $" . number_format($amount, 2) . 
                          " para la factura: " . $invoice['invoice_number'];
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $_SESSION['user_id']);
            $stmt->bindParam(":description", $description);
            $stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
            $stmt->execute();
            
            $db->commit();
            
            // Enviar confirmación por correo
            // Preparar los datos necesarios para el correo
            $payment_info = [
                'amount' => $amount,
                'payment_date' => $payment_date,
                'payment_method' => $payment_method,
                'reference' => $reference
            ];
            
            $mailer = new Mailer();
            $mailer->sendPaymentConfirmation([
                'email' => $invoice['email'],
                'business_name' => $invoice['business_name']
            ], [
                'invoice_number' => $invoice['invoice_number'],
                'total_amount' => $invoice['total_amount'],
                'issue_date' => $invoice['issue_date']
            ], $payment_info);
            
            $success = "Pago registrado correctamente.";
            header("refresh:2;url=../invoices/view.php?id=" . $invoice_id);
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Registrar Pago</h2>
        <div class="header-actions">
            <a href="../invoices/view.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="payment-form-container">
        <div class="invoice-summary">
            <h3>Información de la Factura</h3>
            <table class="details-table">
                <tr>
                    <th>Número de Factura:</th>
                    <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                </tr>
                <tr>
                    <th>Cliente:</th>
                    <td><?php echo htmlspecialchars($invoice['business_name']); ?></td>
                </tr>
                <tr>
                    <th>Monto Total:</th>
                    <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <th>Saldo Pendiente:</th>
                    <td class="pending-amount">
                        $<?php 
                        $query = "SELECT 
                                    total_amount - COALESCE(
                                        (SELECT SUM(amount) FROM payments WHERE invoice_id = :invoice_id), 
                                        0
                                    ) as pending_amount 
                                 FROM invoices 
                                 WHERE id = :invoice_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":invoice_id", $invoice_id);
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        echo number_format($result['pending_amount'], 2);
                        ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="form-container">
            <!-- Formulario para cargar XML -->
            <div class="form-section">
                <h3>Cargar XML de Pago</h3>
                <form method="POST" enctype="multipart/form-data" class="upload-form">
                    <?php echo SecurityHelper::getCSRFTokenField(); ?>
                    <div class="form-group">
                        <label for="xml_file">Archivo XML del pago:</label>
                        <input type="file" id="xml_file" name="xml_file" accept=".xml">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Procesar XML
                    </button>
                </form>
            </div>

            <form method="POST" class="payment-form">
                <?php echo SecurityHelper::getCSRFTokenField(); ?>
                <input type="hidden" name="save_payment" value="1">
                
                <div class="form-section">
                    <h3>Información del Pago</h3>
                    <div class="form-group">
                        <label for="amount">Monto:</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" 
                              value="<?php echo isset($payment_data['amount']) ? $payment_data['amount'] : ''; ?>"
                              <?php echo isset($payment_data['amount']) ? 'readonly' : 'required'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date">Fecha de Pago:</label>
                        <input type="date" id="payment_date" name="payment_date" 
                              value="<?php echo isset($payment_data['payment_date']) ? date('Y-m-d', strtotime($payment_data['payment_date'])) : ''; ?>"
                              <?php echo isset($payment_data['payment_date']) ? 'readonly' : 'required'; ?>>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Método de Pago:</label>
                        <select name="payment_method" id="payment_method" class="form-control" required>
                            <option value="">Seleccionar método</option>
                            <option value="transfer">Transferencia</option>
                            <option value="cash">Efectivo</option>
                            <option value="check">Cheque</option>
                            <option value="card">Tarjeta</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reference">Referencia:</label>
                        <input type="text" id="reference" name="reference" 
                               placeholder="Número de transferencia, cheque, etc.">
                    </div>

                    <div class="form-group">
                        <label for="notes">Notas:</label>
                        <textarea id="notes" name="notes" rows="3" 
                                  placeholder="Observaciones adicionales"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Registrar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
ob_end_flush();
?> 