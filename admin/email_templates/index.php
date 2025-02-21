<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';
header('Content-Type: text/html; charset=utf-8');

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Obtener plantillas
$query = "SELECT * FROM email_templates ORDER BY type, name";
$stmt = $db->prepare($query);
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Plantillas de Correo</h2>
        <div class="header-actions">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nueva Plantilla
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="templates-grid">
        <?php foreach ($templates as $template): ?>
            <div class="template-card">
                <div class="template-header">
                    <h3><?php echo htmlspecialchars($template['name']); ?></h3>
                    <span class="template-type"><?php echo ucfirst(str_replace('_', ' ', $template['type'])); ?></span>
                </div>
                <div class="template-subject">
                    <strong>Asunto:</strong> <?php echo htmlspecialchars($template['subject']); ?>
                </div>
                <div class="template-description">
                    <?php echo htmlspecialchars($template['description'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="template-actions">
                    <a href="edit.php?id=<?php echo $template['id']; ?>" class="btn btn-small">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="preview.php?id=<?php echo $template['id']; ?>" class="btn btn-small btn-secondary">
                        <i class="fas fa-eye"></i> Vista Previa
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.template-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.template-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--primary-color);
}

.template-type {
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85em;
    color: #666;
}

.template-subject {
    margin-bottom: 1rem;
    font-size: 0.9em;
}

.template-description {
    color: #666;
    font-size: 0.9em;
    margin-bottom: 1rem;
}

.template-actions {
    display: flex;
    gap: 0.5rem;
}
</style>

<?php include '../../includes/footer.php'; ?> 