<?php
if (ob_get_level() == 0) ob_start();

require_once 'SecurityHelper.php';

session_start();

function cleanInput($data) {
    return SecurityHelper::sanitizeInput($data);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function redirectIfNotAdmin() {
    if (!isAdmin()) {
        header("Location: " . getBaseUrl() . "/index.php");
        exit();
    }
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Debe iniciar sesión para acceder a esta página";
        header("Location: " . getBaseUrl() . "/login.php");
        exit();
    }
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $baseDir = '/cobranza1'; // Ajusta esto según tu directorio de instalación
    return $protocol . $host . $baseDir;
}

// Agregar validación CSRF a todos los formularios POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    if (!SecurityHelper::validateCSRF()) {
        die('Error de validación CSRF');
    }
}
?> 