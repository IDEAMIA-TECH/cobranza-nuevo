-- Agregar campo de días de crédito a la tabla clients
ALTER TABLE clients 
ADD COLUMN credit_days INT NOT NULL DEFAULT 0 
COMMENT 'Días de crédito para facturas' 
AFTER tax_regime;

-- Actualizar clientes existentes con un valor por defecto de 0 días
UPDATE clients SET credit_days = 0 WHERE credit_days IS NULL; 