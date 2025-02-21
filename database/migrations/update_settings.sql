-- Actualizar configuración de correo si no existe
INSERT INTO settings (name, value, description, type)
SELECT 'smtp_host', 'smtp.gmail.com', 'Host del servidor SMTP', 'smtp'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE name = 'smtp_host');

INSERT INTO settings (name, value, description, type)
SELECT 'smtp_port', '587', 'Puerto del servidor SMTP', 'smtp'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE name = 'smtp_port');

INSERT INTO settings (name, value, description, type)
SELECT 'smtp_user', 'tu_correo@gmail.com', 'Usuario SMTP', 'smtp'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE name = 'smtp_user');

INSERT INTO settings (name, value, description, type)
SELECT 'smtp_password', 'tu_contraseña', 'Contraseña SMTP', 'smtp'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE name = 'smtp_password');

INSERT INTO settings (name, value, description, type)
SELECT 'smtp_from', 'no-reply@tudominio.com', 'Dirección de correo remitente', 'smtp'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE name = 'smtp_from');

INSERT INTO settings (name, value, description, type)
SELECT 'company_name', 'IDEAMIA Tech', 'Nombre de la empresa', 'general'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE name = 'company_name'); 