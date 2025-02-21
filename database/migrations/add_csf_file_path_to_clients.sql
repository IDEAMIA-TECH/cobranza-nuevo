-- Agregar campo para almacenar la ruta del archivo CSF
ALTER TABLE clients 
ADD COLUMN csf_file_path VARCHAR(255) 
COMMENT 'Ruta al archivo de Constancia de Situación Fiscal' 
AFTER tax_regime; 