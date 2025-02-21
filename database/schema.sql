-- Establecer codificación por defecto
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
ALTER DATABASE cobranza1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Tabla de usuarios
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    status ENUM('active', 'pending', 'inactive') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de clientes
CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    company_logo VARCHAR(255) DEFAULT NULL,
    rfc VARCHAR(13) UNIQUE NOT NULL,
    tax_regime VARCHAR(100) NOT NULL,
    credit_days INT NOT NULL DEFAULT 0 COMMENT 'Días de crédito para facturas',
    street VARCHAR(255) NOT NULL,
    ext_number VARCHAR(20) NOT NULL,
    int_number VARCHAR(20),
    neighborhood VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    zip_code VARCHAR(5) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de facturas
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    invoice_uuid VARCHAR(36) UNIQUE NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'pending',
    xml_path VARCHAR(255) NOT NULL,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de items de factura
CREATE TABLE invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    product_key VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de pagos
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    payment_uuid VARCHAR(36) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    xml_path VARCHAR(255) NOT NULL,
    reference VARCHAR(100),
    notes TEXT,
    status ENUM('processed', 'pending', 'rejected') NOT NULL DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de notificaciones
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    invoice_id INT NOT NULL,
    type ENUM('new_invoice', 'payment_confirmation', 'due_reminder', 'overdue') NOT NULL,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed', 'pending') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de registro de actividades
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    related_id INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para restablecimiento de contraseñas
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expiry DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear usuario administrador por defecto
-- Contraseña: Admin123!
INSERT INTO users (email, password, role, status) VALUES 
('admin@ideamia.tech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Crear índices para optimizar consultas frecuentes
CREATE INDEX idx_invoice_status ON invoices(status);
CREATE INDEX idx_invoice_due_date ON invoices(due_date);
CREATE INDEX idx_client_rfc ON clients(rfc);
CREATE INDEX idx_notification_status ON notifications(status);
CREATE INDEX idx_payment_status ON payments(status);

-- Tabla de logs de correo
CREATE TABLE email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    email_type ENUM('new_invoice', 'payment_confirmation', 'due_reminder', 'overdue_notice') NOT NULL,
    related_id INT COMMENT 'ID de la factura o pago relacionado',
    status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones por defecto
INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('company_name', 'IDEAMIA Tech', 'Nombre de la empresa'),
('company_logo', '/assets/img/logo.png', 'Ruta del logo de la empresa'),
('company_address', 'Dirección Fiscal', 'Dirección de la empresa'),
('company_phone', '(123) 456-7890', 'Teléfono de contacto'),
('company_email', 'contacto@ideamia.tech', 'Email de contacto'),
('system_name', 'Sistema de Cobranza', 'Nombre del sistema'),
('footer_text', '© 2024 IDEAMIA Tech - Todos los derechos reservados', 'Texto del pie de página'),
('smtp_host', 'mail.devgdlhost.com', 'Servidor SMTP'),
('smtp_user', 'cobranza@devgdlhost.com', 'Usuario SMTP'),
('smtp_password', ')S8y{k6aHqf~', 'Contraseña SMTP'),
('smtp_port', '587', 'Puerto SMTP'),
('smtp_from', 'no-reply@devgdl.com', 'Email remitente');

CREATE TABLE admin_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    avatar_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    description TEXT,
    variables TEXT,
    type ENUM('new_invoice', 'payment_confirmation', 'due_reminder', 'overdue_notice') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar plantillas por defecto
INSERT INTO email_templates (name, subject, body, description, variables, type) VALUES
('Nueva Factura', 
 'Nueva factura #{invoice_number}', 
 '<p>Estimado {client_name},</p><p>Se ha generado una nueva factura con los siguientes detalles:</p><p>Número de factura: {invoice_number}<br>Monto: ${total_amount}<br>Fecha de vencimiento: {due_date}</p><p>Puede descargar su factura desde el siguiente enlace: {invoice_link}</p>',
 'Plantilla para notificar nuevas facturas',
 '{"invoice_number":"Número de factura","client_name":"Nombre del cliente","total_amount":"Monto total","due_date":"Fecha de vencimiento","invoice_link":"Enlace de la factura"}',
 'new_invoice'),
('Confirmación de Pago',
 'Pago confirmado - Factura #{invoice_number}',
 '<p>Estimado {client_name},</p><p>Su pago ha sido procesado correctamente:</p><p>Factura: {invoice_number}<br>Monto pagado: ${payment_amount}<br>Fecha de pago: {payment_date}</p>',
 'Plantilla para confirmar pagos recibidos',
 '{"invoice_number":"Número de factura","client_name":"Nombre del cliente","payment_amount":"Monto del pago","payment_date":"Fecha del pago"}',
 'payment_confirmation'),
('Recordatorio de Vencimiento',
 'Recordatorio: Factura #{invoice_number} próxima a vencer',
 '<p>Estimado {client_name},</p><p>Le recordamos que la factura #{invoice_number} vencerá en {days_to_due} días.</p><p>Detalles de la factura:<br>Monto pendiente: ${total_amount}<br>Fecha de vencimiento: {due_date}</p><p>Para evitar cargos adicionales, por favor realice el pago antes de la fecha de vencimiento.</p>',
 'Plantilla para recordatorios de facturas próximas a vencer',
 '{"invoice_number":"Número de factura","client_name":"Nombre del cliente","total_amount":"Monto total","due_date":"Fecha de vencimiento","days_to_due":"Días para vencer"}',
 'due_reminder'),
('Factura Vencida',
 'IMPORTANTE: Factura #{invoice_number} vencida',
 '<p>Estimado {client_name},</p><p>La factura #{invoice_number} se encuentra vencida por {days_overdue} días.</p><p>Detalles de la factura:<br>Monto pendiente: ${total_amount}<br>Fecha de vencimiento: {due_date}</p><p>Para regularizar su situación y evitar inconvenientes, por favor realice el pago lo antes posible.</p><p>Si ya realizó el pago, por favor ignore este mensaje.</p>',
 'Plantilla para notificación de facturas vencidas',
 '{"invoice_number":"Número de factura","client_name":"Nombre del cliente","total_amount":"Monto total","due_date":"Fecha de vencimiento","days_overdue":"Días de vencimiento"}',
 'overdue_notice'); 