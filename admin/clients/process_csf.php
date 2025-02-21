<?php
require_once '../../includes/functions.php';
require_once '../../config/database.php';
require_once '../../includes/SecurityHelper.php';

// Verificar si el usuario está logueado y es administrador
redirectIfNotLoggedIn();
redirectIfNotAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csf_file'])) {
    try {
        // Validar CSRF
        if (!isset($_POST['csrf_token'])) {
            throw new Exception('Error de validación de seguridad');
        }

        $csrf_token = $_POST['csrf_token'];
        if (!SecurityHelper::validateCSRFToken($csrf_token)) {
            throw new Exception('Token de seguridad inválido');
        }

        $file = $_FILES['csf_file'];
        $fileName = $file['name'];
        $fileType = $file['type'];
        
        // Validar que sea un PDF
        if (!in_array($fileType, ['application/pdf', 'application/x-pdf'])) {
            throw new Exception('El archivo debe ser un PDF');
        }
        
        // Validar tamaño (máximo 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('El archivo no debe superar los 5MB');
        }
        
        // Generar nombre único
        $uniqueName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $fileName);
        $uploadDir = '../../uploads/csf/';
        
        // Crear directorio si no existe
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Verificar permisos de escritura
        if (!is_writable($uploadDir)) {
            throw new Exception('Error de permisos en el servidor');
        }
        
        // Mover archivo
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $uniqueName)) {
            echo json_encode([
                'success' => true,
                'file_path' => 'uploads/csf/' . $uniqueName,
                'message' => 'Archivo procesado correctamente'
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