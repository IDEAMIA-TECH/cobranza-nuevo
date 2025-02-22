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

function validatePassword($password) {
    // Mínimo 8 caracteres
    if (strlen($password) < 8) {
        return "La contraseña debe tener al menos 8 caracteres";
    }

    // Debe contener al menos una letra mayúscula
    if (!preg_match('/[A-Z]/', $password)) {
        return "La contraseña debe contener al menos una letra mayúscula";
    }

    // Debe contener al menos una letra minúscula
    if (!preg_match('/[a-z]/', $password)) {
        return "La contraseña debe contener al menos una letra minúscula";
    }

    // Debe contener al menos un número
    if (!preg_match('/[0-9]/', $password)) {
        return "La contraseña debe contener al menos un número";
    }

    // Debe contener al menos un carácter especial
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return "La contraseña debe contener al menos un carácter especial (!@#$%^&*(),.?\":{}|<>)";
    }

    return true;
}
?> 