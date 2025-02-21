-- Insertar plantilla para nueva factura si no existe
INSERT INTO email_templates (name, subject, content, description, variables, type)
SELECT 'Nueva Factura',
       'Nueva Factura #{invoice_number}',
       '<h2>Nueva Factura Registrada</h2>
        <p>Estimado cliente {client_name},</p>
        <p>Le informamos que se ha registrado una nueva factura a su nombre.</p>
        <p>Detalles de la factura:</p>
        <ul>
            <li>Número de Factura: {invoice_number}</li>
            <li>Monto Total: ${total_amount}</li>
            <li>Fecha de Emisión: {issue_date}</li>
            <li>Fecha de Vencimiento: {due_date}</li>
        </ul>
        <p>Por favor, asegúrese de realizar el pago antes de la fecha de vencimiento.</p>
        <p>Si tiene alguna pregunta o inquietud, no dude en contactarnos.</p>
        <p>Atentamente,<br>IDEAMIA Tech</p>',
       'Plantilla para nuevas facturas',
       '{"invoice_number":"Número de factura","client_name":"Nombre del cliente","total_amount":"Monto total","issue_date":"Fecha de emisión","due_date":"Fecha de vencimiento"}',
       'new_invoice'
WHERE NOT EXISTS (
    SELECT 1 FROM email_templates WHERE type = 'new_invoice'
); 