<?php
ob_start();

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

$invoice_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Obtener información de la factura
$query = "SELECT i.*, 
          c.business_name, c.rfc, c.phone,
          u.email,
          c.street, c.ext_number, c.int_number, c.neighborhood,
          c.city, c.state, c.zip_code,
          DATEDIFF(i.due_date, CURDATE()) as days_to_due,
          CASE 
              WHEN i.status = 'pending' AND i.due_date < CURDATE() THEN 'overdue'
              ELSE i.status 
          END as current_status
          FROM invoices i 
          JOIN clients c ON c.id = i.client_id 
          JOIN users u ON u.id = c.user_id
          WHERE i.id = :invoice_id";

$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    header("Location: index.php");
    exit();
}

$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener conceptos de la factura
$query = "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener pagos relacionados
$query = "SELECT p.*, CONCAT(COALESCE(c.business_name, 'Admin'), ' (', u.email, ')') as registered_by 
          FROM payments p 
          LEFT JOIN users u ON u.id = p.created_by
          LEFT JOIN clients c ON c.user_id = u.id 
          WHERE p.invoice_id = :invoice_id 
          ORDER BY p.payment_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';

ob_end_flush();
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Detalles de Factura</h2>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <?php if ($invoice['status'] !== 'paid'): ?>
                <a href="../payments/register.php?invoice_id=<?php echo $invoice_id; ?>" 
                   class="btn btn-success">
                    <i class="fas fa-dollar-sign"></i> Registrar Pago
                </a>
            <?php endif; ?>
            <?php if ($invoice['pdf_path']): ?>
                <a href="<?php echo htmlspecialchars($invoice['pdf_path']); ?>" 
                   class="btn" target="_blank">
                    <i class="fas fa-file-pdf"></i> Ver PDF
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="invoice-details">
        <div class="info-section">
            <h3>Información General</h3>
            <table class="details-table">
                <tr>
                    <th>Número de Factura:</th>
                    <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                </tr>
                <tr>
                    <th>UUID:</th>
                    <td><?php echo htmlspecialchars($invoice['uuid']); ?></td>
                </tr>
                <tr>
                    <th>Fecha de Emisión:</th>
                    <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                </tr>
                <tr>
                    <th>Fecha de Vencimiento:</th>
                    <td>
                        <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                        <?php if ($invoice['days_to_due'] > 0): ?>
                            <span class="days-remaining">
                                (<?php echo $invoice['days_to_due']; ?> días restantes)
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Estado:</th>
                    <td>
                        <span class="status-badge <?php echo $invoice['current_status']; ?>">
                            <?php 
                            echo $invoice['current_status'] === 'pending' ? 'Pendiente' : 
                                 ($invoice['current_status'] === 'overdue' ? 'Vencida' : 
                                  ($invoice['current_status'] === 'paid' ? 'Pagada' : 
                                   $invoice['current_status'])); 
                            ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="info-section">
            <h3>Información del Cliente</h3>
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
                <tr>
                    <th>Email:</th>
                    <td><?php echo htmlspecialchars($invoice['email']); ?></td>
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
                        <th>Clave Producto/Servicio</th>
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

    <?php if (!empty($payments)): ?>
        <div class="payments-section">
            <h3>Pagos Registrados</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Método de Pago</th>
                            <th>Referencia</th>
                            <th>Registrado por</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                                <td><?php echo htmlspecialchars($payment['registered_by']); ?></td>
                                <td><?php echo htmlspecialchars($payment['notes']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?> 