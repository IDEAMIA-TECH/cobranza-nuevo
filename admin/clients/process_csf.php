<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
redirectIfNotAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csf_file'])) {
    try {
        $file = $_FILES['csf_file'];
        $fileName = $file['name'];
        $fileType = $file['type'];
        
        // Validar que sea un PDF
        if ($fileType !== 'application/pdf') {
            throw new Exception('El archivo debe ser un PDF');
        }
        
        // Generar nombre único
        $uniqueName = uniqid() . '_' . $fileName;
        $uploadDir = '../../uploads/csf/';
        
        // Crear directorio si no existe
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $uniqueName)) {
            echo json_encode([
                'success' => true,
                'file_path' => 'uploads/csf/' . $uniqueName
            ]);
        } else {
            throw new Exception('Error al guardar el archivo');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} 