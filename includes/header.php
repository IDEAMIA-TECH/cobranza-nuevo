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
        :root {
            --primary-color: #6f42c1;
            --secondary-color: #e83e8c;
            --background-color: #f8f9fa;
            --sidebar-width: 250px;
            --header-height: 60px;
        }

        body {
            background-color: var(--background-color);
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        main {
            margin-left: var(--sidebar-width);
            padding: 80px 20px 20px;
            min-height: calc(100vh - var(--header-height));
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: var(--header-height);
            bottom: 0;
            width: var(--sidebar-width);
            background: white;
            box-shadow: 2px 0 4px rgba(0,0,0,0.1);
            padding: 20px 0;
            overflow-y: auto;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            padding: 10px 20px;
        }

        .sidebar-menu a {
            color: #333;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu a:hover {
            color: var(--primary-color);
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            padding: 0 20px;
        }

        nav ul {
            display: flex;
            gap: 20px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        nav ul li a:hover {
            background: rgba(255,255,255,0.1);
        }

        .dashboard-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

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
            color: #fff;
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
            <div class="nav-actions">
                <a href="#" class="nav-icon" title="Notificaciones">
                    <i class="fas fa-bell"></i>
                </a>
                <a href="#" class="nav-icon" title="Mensajes">
                    <i class="fas fa-envelope"></i>
                </a>
                <?php if (isLoggedIn()): ?>
                    <div class="user-menu">
                        <img src="<?php echo getBaseUrl(); ?>/assets/img/default-avatar.png" 
                             alt="Usuario" class="avatar">
                        <span><?php echo $_SESSION['user_name']; ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <?php if (isLoggedIn()): ?>
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <?php if (isAdmin()): ?>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Panel Admin
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/clients/">
                        <i class="fas fa-users"></i> Clientes
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/user/dashboard/">
                        <i class="fas fa-home"></i> Inicio
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/user/invoices/">
                        <i class="fas fa-file-invoice"></i> Mis Facturas
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </aside>
    <?php endif; ?>
    <main> 