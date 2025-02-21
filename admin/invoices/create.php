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

$database = new Database();
$db = $database->getConnection();

// Obtener lista de clientes activos
$query = "SELECT c.*, u.email 
          FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE u.status = 'active' 
          ORDER BY c.business_name";
$stmt = $db->query($query);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';
$invoice_data = [];

// Función para procesar el XML
function parseInvoiceXML($xml_content) {
    $xml = simplexml_load_string($xml_content);
    if (!$xml) {
        throw new Exception("Error al procesar el archivo XML");
    }

    // Registrar los namespaces del CFDI
    $namespaces = $xml->getNamespaces(true);
    $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
    $xml->registerXPathNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');

    // Extraer datos básicos de la factura
    $data = [
        'uuid' => (string)$xml->xpath('//tfd:TimbreFiscalDigital/@UUID')[0],
        'invoice_number' => (string)$xml->attributes()->Serie . (string)$xml->attributes()->Folio,
        'issue_date' => (string)$xml->attributes()->Fecha,
        'subtotal' => (float)$xml->attributes()->SubTotal,
        'total' => (float)$xml->attributes()->Total,
        'client_rfc' => (string)$xml->xpath('//cfdi:Receptor/@Rfc')[0],
        'items' => []
    ];

    // Extraer conceptos/items
    foreach ($xml->xpath('//cfdi:Concepto') as $concepto) {
        $item = [
            'product_key' => (string)$concepto->attributes()->ClaveProdServ,
            'description' => (string)$concepto->attributes()->Descripcion,
            'quantity' => (float)$concepto->attributes()->Cantidad,
            'unit_price' => (float)$concepto->attributes()->ValorUnitario,
            'subtotal' => (float)$concepto->attributes()->Importe,
            'tax_amount' => 0
        ];

        // Calcular impuestos
        foreach ($concepto->xpath('.//cfdi:Traslado') as $tax) {
            $item['tax_amount'] += (float)$tax->attributes()->Importe;
        }

        $item['total'] = $item['subtotal'] + $item['tax_amount'];
        $data['items'][] = $item;
    }

    return $data;
}

// Función para procesar el PDF
function parsePDFText($pdf_path) {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($pdf_path);
    $text = $pdf->getText();
    
    // Extraer datos relevantes usando expresiones regulares
    $data = [];
    
    // RFC
    if (preg_match('/RFC:\s*([A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3})/', $text, $matches)) {
        $data['rfc'] = $matches[1];
    }
    
    // Razón Social
    if (preg_match('/Denominación\/Nombre:\s*([^\n]+)/', $text, $matches)) {
        $data['business_name'] = trim($matches[1]);
    }
    
    // Régimen Fiscal
    if (preg_match('/Régimen Fiscal:\s*(\d+)\s*([^\n]+)/', $text, $matches)) {
        $data['tax_regime'] = $matches[1] . ' - ' . trim($matches[2]);
    }
    
    // Código Postal
    if (preg_match('/C\.P\.?\s*(\d{5})/', $text, $matches)) {
        $data['zip_code'] = $matches[1];
    }
    
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Procesar Constancia de Situación Fiscal
        if (isset($_FILES['csf_file']) && $_FILES['csf_file']['error'] === UPLOAD_ERR_OK) {
            $temp_pdf_directory = '../../uploads/temp/';
            if (!file_exists($temp_pdf_directory)) {
                mkdir($temp_pdf_directory, 0755, true);
            }

            $temp_pdf_path = $temp_pdf_directory . uniqid() . '.pdf';
            move_uploaded_file($_FILES['csf_file']['tmp_name'], $temp_pdf_path);

            $csf_data = parsePDFText($temp_pdf_path);
            unlink($temp_pdf_path); // Eliminar archivo temporal

            if (!empty($csf_data)) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'data' => $csf_data]);
                exit;
            }
        }

        // Procesar archivos XML
        if (isset($_FILES['xml_files'])) {
            $results = [];
            $temp_xml_directory = '../../uploads/temp/';
            if (!file_exists($temp_xml_directory)) {
                mkdir($temp_xml_directory, 0755, true);
            }

            // Procesar cada archivo
            foreach ($_FILES['xml_files']['tmp_name'] as $key => $tmp_name) {
                $result = [
                    'filename' => $_FILES['xml_files']['name'][$key],
                    'status' => 'error',
                    'message' => '',
                    'data' => null
                ];

                try {
                    if ($_FILES['xml_files']['error'][$key] === UPLOAD_ERR_OK) {
                        $xml_content = file_get_contents($tmp_name);
                        $invoice_data = parseInvoiceXML($xml_content);

                        // Verificar si el cliente existe
                        $query = "SELECT c.id, c.business_name, c.credit_days 
                                 FROM clients c 
                                 JOIN users u ON u.id = c.user_id 
                                 WHERE c.rfc = :rfc AND u.status = 'active'";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":rfc", $invoice_data['client_rfc']);
                        $stmt->execute();

                        if ($stmt->rowCount() === 0) {
                            throw new Exception("No se encontró un cliente activo con el RFC: " . $invoice_data['client_rfc']);
                        }

                        $client = $stmt->fetch(PDO::FETCH_ASSOC);
                        $invoice_data['client_id'] = $client['id'];
                        $invoice_data['client_name'] = $client['business_name'];
                        $invoice_data['credit_days'] = $client['credit_days'];

                        // Guardar el archivo XML
                        $xml_filename = $invoice_data['uuid'] . '.xml';
                        move_uploaded_file($tmp_name, $temp_xml_directory . $xml_filename);
                        $invoice_data['xml_path'] = 'uploads/temp/' . $xml_filename;

                        $result['status'] = 'success';
                        $result['message'] = 'XML procesado correctamente';
                        $result['data'] = $invoice_data;
                    }
                } catch (Exception $e) {
                    $result['message'] = $e->getMessage();
                }

                $results[] = $result;
            }

            // Devolver resultados en formato JSON si es una petición AJAX
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['results' => $results]);
                exit;
            }

            // Almacenar resultados en la sesión
            $_SESSION['invoice_results'] = $results;
            $success = "Se procesaron " . count($results) . " archivos XML";
        }

        // Si se envió el formulario completo
        if (isset($_POST['save_invoice'])) {
            // Recuperar resultados de la sesión
            $results = $_SESSION['invoice_results'] ?? [];
            $success_count = 0;
  
            $db->beginTransaction();
  
            // Verificar que tengamos resultados para procesar
            if (empty($results)) {
                throw new Exception("No hay facturas pendientes de procesar");
            }
  
            // Procesar cada factura exitosa
            foreach ($results as $result) {
                if ($result['status'] !== 'success' || empty($result['data'])) {
                    continue;
                }
  
                $invoice_data = $result['data'];
                
                // Validar datos necesarios
                $required_fields = ['client_id', 'invoice_number', 'issue_date', 'uuid', 'total'];
                foreach ($required_fields as $field) {
                    if (!isset($invoice_data[$field])) {
                        continue 2; // Saltar a la siguiente factura
                    }
                }
  
                // Preparar datos
                $client_id = (int)$invoice_data['client_id'];
                $invoice_number = $invoice_data['invoice_number'];
                $issue_date = date('Y-m-d', strtotime($invoice_data['issue_date']));
                $credit_days = $invoice_data['credit_days'] > 0 ? $invoice_data['credit_days'] : 0;
                $due_date = date('Y-m-d', strtotime($issue_date . " +{$credit_days} days"));
                
                // Mover archivo XML
                $xml_directory = '../../uploads/xml/';
                if (!file_exists($xml_directory)) {
                    mkdir($xml_directory, 0755, true);
                }
                
                $xml_filename = $invoice_data['uuid'] . '.xml';
                $temp_path = $temp_xml_directory . $xml_filename;
                $final_path = $xml_directory . $xml_filename;
                
                if (file_exists($temp_path)) {
                    rename($temp_path, $final_path);
                }
                
                $xml_path = 'uploads/xml/' . $xml_filename;
                $total_amount = $invoice_data['total'];
                $uuid = $invoice_data['uuid'];
  
                // Guardar la factura en la base de datos
                $query = "INSERT INTO invoices (client_id, invoice_uuid, invoice_number, 
                                             issue_date, due_date, total_amount, 
                                             xml_path, status) 
                         VALUES (:client_id, :uuid, :invoice_number, 
                                :issue_date, :due_date, :total_amount, 
                                :xml_path, 'pending')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":client_id", $client_id);
                $stmt->bindParam(":uuid", $uuid);
                $stmt->bindParam(":invoice_number", $invoice_number);
                $stmt->bindParam(":issue_date", $issue_date);
                $stmt->bindParam(":due_date", $due_date);
                $stmt->bindParam(":total_amount", $total_amount);
                $stmt->bindParam(":xml_path", $xml_path);
                $stmt->execute();

                $invoice_id = $db->lastInsertId();

                // Guardar los conceptos de la factura
                foreach ($invoice_data['items'] as $item) {
                    $product_key = $item['product_key'];
                    $description = $item['description'];
                    $quantity = $item['quantity'];
                    $unit_price = $item['unit_price'];
                    $subtotal = $item['subtotal'];
                    $tax_amount = $item['tax_amount'];
                    $total = $item['total'];

                    $query = "INSERT INTO invoice_items (invoice_id, product_key, description, 
                                                       quantity, unit_price, subtotal, 
                                                       tax_amount, total) 
                             VALUES (:invoice_id, :product_key, :description, 
                                    :quantity, :unit_price, :subtotal, 
                                    :tax_amount, :total)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":invoice_id", $invoice_id);
                    $stmt->bindParam(":product_key", $product_key);
                    $stmt->bindParam(":description", $description);
                    $stmt->bindParam(":quantity", $quantity);
                    $stmt->bindParam(":unit_price", $unit_price);
                    $stmt->bindParam(":subtotal", $subtotal);
                    $stmt->bindParam(":tax_amount", $tax_amount);
                    $stmt->bindParam(":total", $total);
                    $stmt->execute();
                }

                $success_count++;
            }
  
            $db->commit();
  
            // Limpiar resultados de la sesión
            unset($_SESSION['invoice_results']);
  
            $_SESSION['success'] = "Se guardaron $success_count facturas exitosamente";
            header("Location: index.php");
            exit();
        }
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Crear Nueva Factura</h2>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">
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

    <div class="form-section">
        <h3>Cargar Constancia de Situación Fiscal</h3>
        <form method="POST" enctype="multipart/form-data" id="csf-form" class="upload-form">
            <?php echo SecurityHelper::getCSRFTokenField(); ?>
            <div class="form-group">
                <label for="csf_file">Archivo PDF de la Constancia:</label>
                <input type="file" id="csf_file" name="csf_file" accept=".pdf" required>
                <small class="form-text text-muted">
                    Suba la Constancia de Situación Fiscal para autocompletar los datos del cliente
                </small>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Procesar CSF
            </button>
        </form>
    </div>

    <!-- Formulario para cargar XML -->
    <div class="form-section">
        <h3>Cargar XML</h3>
        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <?php echo SecurityHelper::getCSRFTokenField(); ?>
            <div class="form-group">
                <label for="xml_files">Archivos XML de facturas:</label>
                <input type="file" id="xml_files" name="xml_files[]" accept=".xml" multiple required>
                <small class="form-text text-muted">
                    Puede seleccionar múltiples archivos XML a la vez
                </small>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Procesar XML
            </button>
        </form>
    </div>

    <!-- Tabla de resultados del procesamiento -->
    <div id="processing-results" class="processing-results" style="display: none;">
        <h3>Resultados del Procesamiento</h3>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Cliente</th>
                        <th>Factura</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody id="results-body">
                </tbody>
            </table>
        </div>
    </div>

    <!-- Formulario principal de la factura -->
    <?php if (!empty($invoice_data) && isset($invoice_data['invoice_number']) && isset($invoice_data['client_id'])): ?>
    <form method="POST" class="invoice-form">
        <?php echo SecurityHelper::getCSRFTokenField(); ?>
        <input type="hidden" name="save_invoice" value="1">
        
        <div class="form-sections">
            <div class="form-section">
                <h3>Información del Cliente</h3>
                <div class="form-group">
                    <label>RFC:</label>
                    <input type="text" value="<?php echo htmlspecialchars($invoice_data['client_rfc']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Razón Social:</label>
                    <input type="text" value="<?php echo htmlspecialchars($invoice_data['client_name']); ?>" readonly>
                </div>
            </div>

            <div class="form-section">
                <h3>Información de la Factura</h3>
                <div class="form-group">
                    <label>UUID:</label>
                    <input type="text" value="<?php echo htmlspecialchars($invoice_data['uuid']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Número de Factura:</label>
                    <input type="text" value="<?php echo htmlspecialchars($invoice_data['invoice_number']); ?>" readonly>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="issue_date">Fecha de Emisión:</label>
                        <input type="text" value="<?php echo date('d/m/Y', strtotime($invoice_data['issue_date'])); ?>" readonly>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Conceptos</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Clave</th>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                                <th>IVA</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice_data['items'] as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_key']); ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                <td>$<?php echo number_format($item['tax_amount'], 2); ?></td>
                                <td>$<?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-right"><strong>Totales:</strong></td>
                                <td>$<?php echo number_format($invoice_data['subtotal'], 2); ?></td>
                                <td>$<?php echo number_format($invoice_data['total'] - $invoice_data['subtotal'], 2); ?></td>
                                <td>$<?php echo number_format($invoice_data['total'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Guardar Factura
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsContainer = document.getElementById('itemsContainer');
    const addItemButton = document.getElementById('addItem');
    let itemCount = 1;

    addItemButton.addEventListener('click', function() {
        const newItem = document.createElement('div');
        newItem.className = 'item-row';
        newItem.innerHTML = `
            <hr>
            <div class="form-group">
                <label>Clave Producto/Servicio:</label>
                <input type="text" name="items[${itemCount}][product_key]" required>
            </div>
            <div class="form-group">
                <label>Descripción:</label>
                <input type="text" name="items[${itemCount}][description]" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Cantidad:</label>
                    <input type="number" name="items[${itemCount}][quantity]" min="0.01" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Precio Unitario:</label>
                    <input type="number" name="items[${itemCount}][unit_price]" min="0.01" step="0.01" required>
                </div>
            </div>
            <button type="button" class="btn btn-danger remove-item">
                <i class="fas fa-trash"></i> Eliminar
            </button>
        `;
        
        itemsContainer.appendChild(newItem);
        itemCount++;

        // Agregar evento para eliminar concepto
        newItem.querySelector('.remove-item').addEventListener('click', function() {
            newItem.remove();
        });
    });
});
</script>

<?php 
include '../../includes/footer.php';
ob_end_flush();
?> 