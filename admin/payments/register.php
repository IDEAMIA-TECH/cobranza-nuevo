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
        // Validar CSRF token
        if (!SecurityHelper::validateCSRF()) {
            throw new Exception('Error de validación de seguridad');
        }

        // Inicializar base de datos
        $database = new Database();
        $db = $database->getConnection();
        $db->beginTransaction();

        // Validar datos
        if (!isset($_POST['invoice_id']) || !is_numeric($_POST['invoice_id'])) {
            throw new Exception('ID de factura inválido');
        }

        $invoice_id = (int)$_POST['invoice_id'];
        $amount = (float)str_replace(',', '', $_POST['amount']);
        $payment_date = cleanInput($_POST['payment_date']);
        $payment_method = cleanInput($_POST['payment_method']);
        $reference = cleanInput($_POST['reference']);

        // Debug
        error_log("Procesando pago: " . json_encode([
            'invoice_id' => $invoice_id,
            'amount' => $amount,
            'payment_date' => $payment_date,
            'payment_method' => $payment_method,
            'post_data' => $_POST
        ]));

        if ($amount <= 0) {
            throw new Exception('El monto debe ser mayor a cero');
        }

        // Obtener información de la factura
        $query = "SELECT i.*, c.business_name, c.email, u.email as client_email 
                 FROM invoices i 
                 JOIN clients c ON i.client_id = c.id 
                 JOIN users u ON c.user_id = u.id 
                 WHERE i.id = :invoice_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':invoice_id', $invoice_id);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new Exception('Factura no encontrada');
        }

        $invoice_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validar que el monto no exceda el saldo pendiente
        $query = "SELECT COALESCE(SUM(amount), 0) as total_paid 
                 FROM payments 
                 WHERE invoice_id = :invoice_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':invoice_id', $invoice_id);
        $stmt->execute();
        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'];

        $remaining_balance = $invoice_data['total_amount'] - $total_paid;

        if ($amount > $remaining_balance) {
            throw new Exception('El monto del pago excede el saldo pendiente');
        }

        // Registrar actividad
        $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                 VALUES (:user_id, :action, :description, :ip_address)";
        $description = "Registró pago de $" . number_format($amount, 2) . 
                      " para la factura " . $invoice_data['invoice_number'];
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':action', 'register_payment');
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
        $stmt->execute();

        // Registrar el pago
        $query = "INSERT INTO payments (invoice_id, amount, payment_date, payment_method, reference) 
                 VALUES (:invoice_id, :amount, :payment_date, :payment_method, :reference)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':invoice_id', $invoice_id);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':payment_date', $payment_date);
        $stmt->bindParam(':payment_method', $payment_method);
        $stmt->bindParam(':reference', $reference);
        $stmt->execute();

        // Actualizar estado de la factura si está pagada completamente
        $new_total_paid = $total_paid + $amount;
        if ($new_total_paid >= $invoice_data['total_amount']) {
            $query = "UPDATE invoices SET status = 'paid' WHERE id = :invoice_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':invoice_id', $invoice_id);
            $stmt->execute();
        }

        // Obtener datos del cliente para el correo
        $client_data = [
            'email' => $invoice_data['client_email'],
            'business_name' => $invoice_data['business_name']
        ];

        $db->commit();

        // Enviar correo de confirmación
        try {
            $mailer = new Mailer();
            $mailer->sendPaymentConfirmation($client_data, $invoice_data, [
                'amount' => $amount,
                'payment_date' => $payment_date,
                'payment_method' => $payment_method,
                'reference' => $reference
            ]);
        } catch (Exception $e) {
            error_log("Error al enviar confirmación de pago: " . $e->getMessage());
        }

        $_SESSION['success'] = "Pago registrado correctamente";
        header("Location: ../invoices/view.php?id=" . $invoice_id);
        exit();

    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header("Location: register.php?invoice_id=" . $invoice_id);
        exit();
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

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="payment-form">
                <?php echo SecurityHelper::getCSRFTokenField(); ?>
                <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                
                <div class="form-section">
                    <h3>Información del Pago</h3>
                    <div class="form-group">
                        <label for="amount">Monto:</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01"
                              class="form-control"
                              value="<?php echo number_format($result['pending_amount'], 2, '.', ''); ?>"
                              required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date">Fecha de Pago:</label>
                        <input type="date" id="payment_date" name="payment_date"
                              class="form-control"
                              value="<?php echo date('Y-m-d'); ?>"
                              required>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Método de Pago:</label>
                        <select name="payment_method" id="payment_method" class="form-control" required
                                onchange="toggleReferenceField()">
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
                              class="form-control"
                              placeholder="Número de transferencia, cheque, etc.">
                    </div>

                    <div class="form-group">
                        <label for="notes">Notas:</label>
                        <textarea id="notes" name="notes" rows="3"
                                  class="form-control"
                                  placeholder="Observaciones adicionales"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success" onclick="return validateForm()">
                        <i class="fas fa-save"></i> Registrar Pago
                    </button>
                </div>
            </form>

            <script>
            function validateForm() {
                var amount = document.getElementById('amount').value;
                var payment_date = document.getElementById('payment_date').value;
                var payment_method = document.getElementById('payment_method').value;

                if (!amount || !payment_date || !payment_method) {
                    alert('Por favor complete todos los campos requeridos');
                    return false;
                }

                // Validar monto
                var pendingAmount = <?php echo $result['pending_amount']; ?>;
                if (parseFloat(amount) > pendingAmount) {
                    alert('El monto no puede ser mayor al saldo pendiente');
                    return false;
                }

                // Validar fecha
                var selectedDate = new Date(payment_date);
                var today = new Date();
                if (selectedDate > today) {
                    alert('La fecha de pago no puede ser futura');
                    return false;
                }

                return true;
            }

            function toggleReferenceField() {
                var method = document.getElementById('payment_method').value;
                var referenceField = document.getElementById('reference');
                referenceField.required = (method === 'transfer' || method === 'check');
            }
            </script>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<?php
ob_end_flush();
?> 