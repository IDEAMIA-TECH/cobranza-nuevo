<?php

require_once __DIR__ . '/Settings.php';

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
    <title><?php echo Settings::get('system_name', 'Sistema de Cobranza'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/styles.css">
    <style>
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .company-logo {
            height: 40px;
            max-width: 200px;
            object-fit: contain;
        }
        
        .company-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="<?php echo getBaseUrl(); ?>">
                    <?php if ($logo = Settings::get('company_logo')): ?>
                        <img src="<?php echo getBaseUrl() . $logo; ?>" 
                             alt="<?php echo Settings::get('company_name'); ?>" 
                             class="company-logo">
                        <span class="company-name">
                            <?php echo Settings::get('company_name', 'Sistema de Cobranza'); ?>
                        </span>
                    <?php else: ?>
                        <span class="company-name">
                            <?php echo Settings::get('company_name', 'Sistema de Cobranza'); ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
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