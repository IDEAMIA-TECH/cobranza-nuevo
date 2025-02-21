<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

// Obtener plantilla
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];
$query = "SELECT * FROM email_templates WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(":id", $id);
$stmt->execute();
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    header("Location: index.php");
    exit();
}

// Datos de ejemplo para la vista previa
$preview_data = [
    'invoice_number' => 'FAC-2024-001',
    'client_name' => 'Empresa de Ejemplo, S.A. de C.V.',
    'total_amount' => '1,234.56',
    'due_date' => date('d/m/Y', strtotime('+30 days')),
    'payment_amount' => '1,234.56',
    'payment_date' => date('d/m/Y'),
    'days_to_due' => '5',
    'days_overdue' => '3',
    'invoice_link' => getBaseUrl() . '/invoices/FAC-2024-001.pdf'
];

// Reemplazar variables en el contenido
$subject = $template['subject'];
$body = $template['body'];

$variables = json_decode($template['variables'], true);
foreach ($variables as $key => $description) {
    $subject = str_replace('{' . $key . '}', $preview_data[$key] ?? '[' . $key . ']', $subject);
    $body = str_replace('{' . $key . '}', $preview_data[$key] ?? '[' . $key . ']', $body);
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Vista Previa de Plantilla</h2>
        <div class="header-actions">
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="preview-container">
        <div class="email-preview">
            <div class="email-header">
                <div class="email-field">
                    <label>De:</label>
                    <span><?php echo Settings::get('company_name'); ?> &lt;<?php echo Settings::get('smtp_from'); ?>&gt;</span>
                </div>
                <div class="email-field">
                    <label>Para:</label>
                    <span>cliente@ejemplo.com</span>
                </div>
                <div class="email-field">
                    <label>Asunto:</label>
                    <span><?php echo htmlspecialchars($subject); ?></span>
                </div>
            </div>
            
            <div class="email-body">
                <?php echo $body; ?>
            </div>
        </div>

        <div class="preview-info">
            <h3>Información de la Plantilla</h3>
            <div class="info-item">
                <label>Nombre:</label>
                <span><?php echo htmlspecialchars($template['name']); ?></span>
            </div>
            <div class="info-item">
                <label>Tipo:</label>
                <span><?php echo ucfirst(str_replace('_', ' ', $template['type'])); ?></span>
            </div>
            <div class="info-item">
                <label>Descripción:</label>
                <span><?php echo htmlspecialchars($template['description']); ?></span>
            </div>
            <div class="variables-list">
                <h4>Variables Utilizadas</h4>
                <ul>
                    <?php foreach ($variables as $key => $description): ?>
                        <li>
                            <code>{<?php echo htmlspecialchars($key); ?>}</code>
                            <span><?php echo htmlspecialchars($description); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.preview-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-top: 1.5rem;
}

.email-preview {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.email-header {
    background: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
}

.email-field {
    margin-bottom: 0.5rem;
}

.email-field:last-child {
    margin-bottom: 0;
}

.email-field label {
    font-weight: bold;
    margin-right: 0.5rem;
    color: #666;
}

.email-body {
    padding: 2rem;
}

.preview-info {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.preview-info h3 {
    margin: 0 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #dee2e6;
}

.info-item {
    margin-bottom: 1rem;
}

.info-item label {
    display: block;
    font-weight: bold;
    color: #666;
    margin-bottom: 0.25rem;
}

.variables-list {
    margin-top: 1.5rem;
}

.variables-list h4 {
    margin: 0 0 1rem;
    color: #666;
}

.variables-list ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.variables-list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.variables-list code {
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    color: var(--primary-color);
}
</style>

<?php include '../../includes/footer.php'; ?> 