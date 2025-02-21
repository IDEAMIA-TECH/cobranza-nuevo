<?php

// Verificar si es una petición AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    return;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Cobranza - IDEAMIA Tech</title>
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo"><a href="<?php echo getBaseUrl(); ?>">IDEAMIA Tech</a></div>
            <?php if (isLoggedIn()): ?>
                <ul>
                    <?php if (isAdmin()): ?>
                        <li><a href="<?php echo getBaseUrl(); ?>/index.php">Inicio</a></li>
                        <li><a href="<?php echo getBaseUrl(); ?>/admin/dashboard.php">Panel Admin</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo getBaseUrl(); ?>/user/dashboard/index.php">Inicio</a></li>
                        <li><a href="<?php echo getBaseUrl(); ?>/user/invoices/index.php">Mis Facturas</a></li>
                        <li><a href="<?php echo getBaseUrl(); ?>/user/profile/index.php">Mi Perfil</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo getBaseUrl(); ?>/logout.php">Cerrar Sesión</a></li>
                </ul>
            <?php endif; ?>
        </nav>
    </header>
    <main> 