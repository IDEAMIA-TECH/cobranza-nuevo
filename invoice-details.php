<?php
require_once 'includes/functions.php';
require_once 'config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

// Verificar si se proporcionó un ID de factura
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$invoice_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Obtener detalles de la factura y verificar que pertenezca al cliente
$query = "SELECT i.*, c.business_name, c.rfc, c.tax_regime, 
                 c.street, c.ext_number, c.int_number, c.neighborhood,
                 c.city, c.state, c.zip_code
          FROM invoices i
          JOIN clients c ON c.id = i.client_id
          JOIN users u ON u.id = c.user_id
          WHERE i.id = :invoice_id AND u.id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: index.php");
    exit();
}

$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener items de la factura
$query = "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="dashboard-container">
    <div class="invoice-header">
        <h2>Detalles de Factura</h2>
        <div class="invoice-actions">
            <a href="index.php" class="btn btn-secondary">Volver</a>
            <?php if ($invoice['pdf_path']): ?>
                <a href="<?php echo htmlspecialchars($invoice['pdf_path']); ?>" 
                   class="btn" target="_blank">Descargar PDF</a>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($invoice['xml_path']); ?>" 
               class="btn" target="_blank">Descargar XML</a>
        </div>
    </div>

    <div class="invoice-details">
        <div class="invoice-info">
            <div class="info-section">
                <h3>Información de Factura</h3>
                <table class="details-table">
                    <tr>
                        <th>Número de Factura:</th>
                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                    </tr>
                    <tr>
                        <th>UUID:</th>
                        <td><?php echo htmlspecialchars($invoice['invoice_uuid']); ?></td>
                    </tr>
                    <tr>
                        <th>Fecha de Emisión:</th>
                        <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Fecha de Vencimiento:</th>
                        <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <span class="status-badge <?php echo $invoice['status']; ?>">
                                <?php 
                                echo $invoice['status'] === 'pending' ? 'Pendiente' : 
                                     ($invoice['status'] === 'overdue' ? 'Vencida' : 
                                      $invoice['status']); 
                                ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="info-section">
                <h3>Datos Fiscales</h3>
                <table class="details-table">
                    <tr>
                        <th>Razón Social:</th>
                        <td><?php echo htmlspecialchars($invoice['business_name']); ?></td>
                    </tr>
                    <tr>
                        <th>RFC:</th>
                        <td><?php echo htmlspecialchars($invoice['rfc']); ?></td>
                    </tr>
                    <tr>
                        <th>Régimen Fiscal:</th>
                        <td><?php echo htmlspecialchars($invoice['tax_regime']); ?></td>
                    </tr>
                    <tr>
                        <th>Dirección:</th>
                        <td>
                            <?php 
                            echo htmlspecialchars($invoice['street']) . " " . 
                                 htmlspecialchars($invoice['ext_number']);
                            if (!empty($invoice['int_number'])) {
                                echo " Int. " . htmlspecialchars($invoice['int_number']);
                            }
                            echo ", " . htmlspecialchars($invoice['neighborhood']) . "<br>";
                            echo htmlspecialchars($invoice['city']) . ", " . 
                                 htmlspecialchars($invoice['state']) . " " . 
                                 htmlspecialchars($invoice['zip_code']);
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="items-section">
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
                        <?php foreach ($items as $item): ?>
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
                            <td colspan="4" class="text-right"><strong>Total:</strong></td>
                            <td colspan="3"><strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 