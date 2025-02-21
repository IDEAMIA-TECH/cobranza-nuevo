<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
redirectIfNotLoggedIn();

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$invoice_id = (int)$_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Obtener información del cliente
$query = "SELECT c.* FROM clients c 
          JOIN users u ON u.id = c.user_id 
          WHERE u.id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $_SESSION['user_id']);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Verificar que la factura pertenezca al cliente
$query = "SELECT i.*, 
          DATEDIFF(i.due_date, CURDATE()) as days_remaining
          FROM invoices i 
          WHERE i.id = :invoice_id AND i.client_id = :client_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->bindParam(":client_id", $client['id']);
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

// Obtener pagos de la factura
$query = "SELECT p.*, u.name as registered_by_name 
          FROM payments p 
          LEFT JOIN users u ON u.id = p.registered_by 
          WHERE p.invoice_id = :invoice_id 
          ORDER BY p.payment_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":invoice_id", $invoice_id);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="invoice-header">
        <h2>Factura <?php echo htmlspecialchars($invoice['invoice_number']); ?></h2>
        <div class="invoice-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="download.php?id=<?php echo $invoice['id']; ?>" 
               class="btn" target="_blank">
                <i class="fas fa-file-pdf"></i> Descargar PDF
            </a>
        </div>
    </div>

    <div class="invoice-details">
        <div class="invoice-info">
            <div class="info-section">
                <h3>Estado de la Factura</h3>
                <table class="details-table">
                    <tr>
                        <th>Estado:</th>
                        <td>
                            <span class="status-badge <?php echo $invoice['status']; ?>">
                                <?php 
                                echo $invoice['status'] === 'paid' ? 'Pagada' : 
                                     ($invoice['status'] === 'pending' ? 'Pendiente' : 
                                      'Vencida'); 
                                ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($invoice['status'] !== 'paid'): ?>
                        <tr>
                            <th>Días:</th>
                            <td>
                                <span class="days-remaining <?php echo $invoice['days_remaining'] < 0 ? 'overdue' : ''; ?>">
                                    <?php 
                                    if ($invoice['days_remaining'] > 0) {
                                        echo $invoice['days_remaining'] . ' días para vencer';
                                    } elseif ($invoice['days_remaining'] < 0) {
                                        echo abs($invoice['days_remaining']) . ' días vencida';
                                    } else {
                                        echo 'Vence hoy';
                                    }
                                    ?>
                                </span>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Fecha de Emisión:</th>
                        <td><?php echo date('d/m/Y', strtotime($invoice['issue_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Fecha de Vencimiento:</th>
                        <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                    </tr>
                </table>
            </div>

            <div class="info-section">
                <h3>Información Fiscal</h3>
                <table class="details-table">
                    <tr>
                        <th>Razón Social:</th>
                        <td><?php echo htmlspecialchars($client['business_name']); ?></td>
                    </tr>
                    <tr>
                        <th>RFC:</th>
                        <td><?php echo htmlspecialchars($client['rfc']); ?></td>
                    </tr>
                    <tr>
                        <th>Régimen Fiscal:</th>
                        <td><?php echo htmlspecialchars($client['tax_regime']); ?></td>
                    </tr>
                    <tr>
                        <th>Dirección:</th>
                        <td>
                            <?php 
                            echo htmlspecialchars($client['street']) . ' ' . 
                                 htmlspecialchars($client['ext_number']);
                            if ($client['int_number']) {
                                echo ' Int. ' . htmlspecialchars($client['int_number']);
                            }
                            echo ', ' . htmlspecialchars($client['neighborhood']) . '<br>';
                            echo htmlspecialchars($client['city']) . ', ' . 
                                 htmlspecialchars($client['state']) . ' CP ' . 
                                 htmlspecialchars($client['zip_code']);
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
                            <th>Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_key']); ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Subtotal:</strong></td>
                            <td>$<?php echo number_format($invoice['subtotal'], 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-right"><strong>IVA (16%):</strong></td>
                            <td>$<?php echo number_format($invoice['tax'], 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Total:</strong></td>
                            <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if (!empty($payments)): ?>
            <div class="payments-section">
                <h3>Historial de Pagos</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Método</th>
                                <th>Referencia</th>
                                <th>Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td>
                                        <span class="payment-method <?php echo $payment['payment_method']; ?>">
                                            <?php 
                                            echo $payment['payment_method'] === 'transfer' ? 'Transferencia' : 
                                                 ($payment['payment_method'] === 'cash' ? 'Efectivo' : 
                                                  ($payment['payment_method'] === 'check' ? 'Cheque' : 
                                                   'Tarjeta')); 
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['registered_by_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 