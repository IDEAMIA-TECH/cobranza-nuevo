<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
redirectIfNotAdmin();

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

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

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Error de validación CSRF');
        }

        $query = "UPDATE email_templates SET 
                  name = :name,
                  subject = :subject,
                  body = :body,
                  description = :description
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':subject', $_POST['subject']);
        $stmt->bindParam(':body', $_POST['body']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        $success = "Plantilla actualizada correctamente";
        
        // Recargar plantilla
        $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = :id");
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include '../../includes/header.php';
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h2>Editar Plantilla</h2>
        <div class="header-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="template-form">
        <?php echo SecurityHelper::getCSRFTokenField(); ?>
        
        <div class="form-row">
            <div class="form-group">
                <label for="name">Nombre de la Plantilla:</label>
                <input type="text" id="name" name="name" 
                       value="<?php echo htmlspecialchars($template['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="subject">Asunto:</label>
                <input type="text" id="subject" name="subject" 
                       value="<?php echo htmlspecialchars($template['subject']); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Descripción:</label>
            <textarea id="description" name="description" rows="2"><?php 
                echo htmlspecialchars($template['description']); 
            ?></textarea>
        </div>

        <div class="form-group">
            <label for="body">Contenido:</label>
            <textarea id="body" name="body" class="wysiwyg"><?php 
                echo htmlspecialchars($template['body']); 
            ?></textarea>
        </div>

        <div class="variables-section">
            <h3>Variables Disponibles</h3>
            <div class="variables-grid">
                <?php 
                $variables = json_decode($template['variables'], true);
                foreach ($variables as $key => $description): 
                ?>
                    <div class="variable-item">
                        <code>{<?php echo htmlspecialchars($key); ?>}</code>
                        <span><?php echo htmlspecialchars($description); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </form>
</div>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<style>
.template-form {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.variables-section {
    margin: 2rem 0;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.variables-section h3 {
    margin: 0 0 1rem;
    font-size: 1.1rem;
    color: #666;
}

.variables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.variable-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9em;
}

.variable-item code {
    background: #e9ecef;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    color: var(--primary-color);
}

.ql-container {
    height: 300px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var quill = new Quill('#body', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                ['clean']
            ]
        }
    });

    // Sincronizar contenido con el textarea al enviar
    document.querySelector('form').onsubmit = function() {
        var bodyInput = document.querySelector('input[name=body]');
        bodyInput.value = quill.root.innerHTML;
    };
});
</script>

<?php include '../../includes/footer.php'; ?> 