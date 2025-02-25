<?php

require_once __DIR__ . '/Settings.php';

// Verificar si es una petición AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    return;
}

// Obtener avatar del usuario si está logueado
if (isLoggedIn()) {
    $database = new Database();
    $db = $database->getConnection();
    if (isAdmin()) {
        $query = "SELECT a.avatar_path as profile_image 
                  FROM admin_profiles a 
                  WHERE a.user_id = :user_id";
    } else {
        $query = "SELECT c.company_logo as profile_image 
                  FROM clients c 
                  WHERE c.user_id = :user_id";
    }
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $_SESSION['user_id']);
    $stmt->execute();
    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
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

        .menu-divider {
            padding: 1rem 1.5rem 0.5rem;
            color: #6c757d;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
            border-top: 1px solid #eee;
            margin-top: 0.5rem;
        }

        .menu-divider:first-child {
            border-top: none;
            margin-top: 0;
        }

        .sidebar-menu li:not(.menu-divider) {
            padding: 0;
        }

        .sidebar-menu li:not(.menu-divider) a {
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .sidebar-menu li:not(.menu-divider) a:hover {
            background: var(--primary-color);
            color: white;
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

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .nav-icon {
            color: white;
            font-size: 1.2rem;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .nav-icon:hover {
            opacity: 1;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: white;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .user-menu:hover {
            background: rgba(255,255,255,0.1);
        }

        .user-menu .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
        }

        .user-menu span {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="menu-toggle">
            <button id="sidebar-toggle" class="btn-toggle" title="Mostrar/Ocultar menú">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="header-content">
            <div class="header-logo">
                <img src="<?php echo getBaseUrl(); ?>/assets/img/logo.png" alt="Logo" class="logo-img">
            </div>
            <div class="header-title">
                IDEAMIA - COBRANZA
            </div>
        </div>
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
                <li class="menu-divider">Clientes</li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/clients/">
                        <i class="fas fa-users"></i> Clientes
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/clients/pending.php">
                        <i class="fas fa-user-clock"></i> Clientes Pendientes
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/clients/create.php">
                        <i class="fas fa-user-plus"></i> Nuevo Cliente
                    </a>
                </li>
                <li class="menu-divider">Facturación</li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/invoices/">
                        <i class="fas fa-file-invoice"></i> Facturas
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/invoices/create.php">
                        <i class="fas fa-file-invoice"></i> Nueva Factura
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/payments/register.php">
                        <i class="fas fa-money-bill"></i> Registrar Pago
                    </a>
                </li>
                <li class="menu-divider">Sistema</li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/activity_logs/">
                        <i class="fas fa-history"></i> Registro de Actividad
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/email_templates/">
                        <i class="fas fa-envelope-open-text"></i> Plantillas de Correo
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/settings/">
                        <i class="fas fa-cog"></i> Configuración
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/profile/">
                        <i class="fas fa-user-circle"></i> Mi Perfil
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </li>
            <?php else: ?>
                <li class="menu-divider">Mi Cuenta</li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/user/dashboard/">
                        <i class="fas fa-home"></i> Inicio
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/user/profile/">
                        <i class="fas fa-user-circle"></i> Mi Perfil
                    </a>
                </li>
                <li class="menu-divider">Facturación</li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/user/invoices/">
                        <i class="fas fa-file-invoice"></i> Mis Facturas
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/user/payments/">
                        <i class="fas fa-money-bill"></i> Mis Pagos
                    </a>
                </li>
                <li class="menu-divider">Soporte</li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/user/support/">
                        <i class="fas fa-question-circle"></i> Ayuda
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/user/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </aside>
    <?php endif; ?>
    <main> 