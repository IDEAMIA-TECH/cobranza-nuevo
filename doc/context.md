# Sistema de Cobranza para IDEAMIA Tech

## Descripción General
Este sistema de cobranza permitirá a los clientes ingresar al sitio web, visualizar sus facturas pendientes de pago y realizar pagos de manera eficiente. El administrador gestionará las cuentas de clientes, subirá facturas, registrará pagos y enviará notificaciones automáticas a los clientes.

---

### Tecnologías
- **Frontend**: html, css, javascript 
- **Backend**: PHP
- **Base de Datos**: MySQL
- **Autenticación**: PHP Session, Hash de contraseñas
- **Manejo de XML**: Librería específica para leer XML de facturas y pagos.
- **Correos**: php mailer, smtp

## Flujo del Sistema

### 1. Inicio de Sesión
- El cliente accederá al sitio web y verá una página de inicio de sesión.
- Ingresará su **usuario y contraseña**.
- Si no tiene cuenta, podrá registrarse.
- El registro debe ser aprobado por el **administrador** tras validar los datos.
- Una vez aprobado, el cliente podrá iniciar sesión.

### 2. Registro de Cliente
- Un cliente nuevo podrá registrarse ingresando sus datos fiscales.
- El sistema notificará al administrador por correo sobre la solicitud de registro.
- El administrador validará la información y aprobará el acceso.
- Una vez aprobado, el cliente recibirá un correo de confirmación.

### 3. Panel del Cliente
- Después de iniciar sesión, el cliente verá una **tabla de facturas pendientes**, con:
  - **Fecha de emisión**
  - **Monto**
  - **Día de vencimiento**
  - **CFDI**
  - **Botón de "Detalles"**
- Al presionar el botón **"Detalles"**, el cliente será redirigido a una página con la información completa de la factura:
  - Fecha de emisión
  - Fecha de vencimiento
  - Productos y cantidades
  - Costo unitario y total
  - Clave de producto y servicio
  - Datos fiscales completos

### 4. Gestión del Administrador
- **Alta de Clientes**: Puede registrar clientes con datos fiscales completos.
- **Carga de Facturas**:
  - El administrador sube un **archivo XML** de la factura.
  - El sistema procesa el XML e inserta los datos en la base de datos.
  - La factura se asocia automáticamente al cliente.
  - Se envía un **correo de notificación** al cliente sobre la nueva factura.
- **Edición y Eliminación de Facturas**: Puede modificar o eliminar facturas en la base de datos.
- **Registro de Pagos**:
  - El administrador sube un **XML de pago timbrado**.
  - El sistema inserta los datos y asocia el pago con la factura y el cliente.
  - La factura cambia de estado a "Pagada".
  - Se envía un **correo de confirmación de pago** al cliente.

### 5. Notificaciones Automáticas
El sistema enviará correos automáticos en los siguientes casos:
- **Nueva factura**: Cuando el administrador sube una nueva factura, el cliente recibe una notificación.
- **Recordatorio de vencimiento**:
  - A **15, 10 y 5 días** antes del vencimiento, el cliente recibirá un correo recordatorio.
- **Factura vencida**:
  - Cada **5 días después del vencimiento**, el cliente recibirá un correo informándole del adeudo.
- **Pago registrado**: Cuando se suba un XML de pago, el sistema notificará al cliente que la factura ha sido saldada.

### 6. Formato de Correos
Los correos enviados por el sistema serán elegantes y concretos, incluyendo:
- **Motivo del correo**
- **Monto de la deuda**
- **Estado de la deuda**
- **Acción a seguir**

---

## Especificaciones Técnicas

### Base de Datos
- **Clientes**: Nombre, RFC, Razón Social, Correo, Teléfono.
- **Facturas**: Número, Cliente, Fecha de Emisión, Fecha de Vencimiento, Monto, CFDI, Estado.
- **Pagos**: Número de factura, Monto, Fecha de Pago, Estado.
- **Usuarios**: Administradores y Clientes.

### Esquema Detallado de Base de Datos

#### 1. Tabla: users
- **id**: INT (PK, AUTO_INCREMENT)
- **email**: VARCHAR(255) UNIQUE
- **password**: VARCHAR(255)
- **role**: ENUM('admin', 'client')
- **status**: ENUM('active', 'pending', 'inactive')
- **created_at**: TIMESTAMP
- **updated_at**: TIMESTAMP

#### 2. Tabla: clients
- **id**: INT (PK, AUTO_INCREMENT)
- **user_id**: INT (FK → users.id)
- **business_name**: VARCHAR(255)
- **rfc**: VARCHAR(13) UNIQUE
- **tax_regime**: VARCHAR(100)
- **street**: VARCHAR(255)
- **ext_number**: VARCHAR(20)
- **int_number**: VARCHAR(20)
- **neighborhood**: VARCHAR(100)
- **city**: VARCHAR(100)
- **state**: VARCHAR(100)
- **zip_code**: VARCHAR(5)
- **phone**: VARCHAR(20)
- **created_at**: TIMESTAMP
- **updated_at**: TIMESTAMP

#### 3. Tabla: invoices
- **id**: INT (PK, AUTO_INCREMENT)
- **client_id**: INT (FK → clients.id)
- **invoice_uuid**: VARCHAR(36) UNIQUE
- **invoice_number**: VARCHAR(50)
- **issue_date**: DATE
- **due_date**: DATE
- **total_amount**: DECIMAL(10,2)
- **status**: ENUM('pending', 'paid', 'overdue', 'cancelled')
- **xml_path**: VARCHAR(255)
- **pdf_path**: VARCHAR(255)
- **created_at**: TIMESTAMP
- **updated_at**: TIMESTAMP

#### 4. Tabla: invoice_items
- **id**: INT (PK, AUTO_INCREMENT)
- **invoice_id**: INT (FK → invoices.id)
- **product_key**: VARCHAR(50)
- **description**: TEXT
- **quantity**: DECIMAL(10,2)
- **unit_price**: DECIMAL(10,2)
- **subtotal**: DECIMAL(10,2)
- **tax_amount**: DECIMAL(10,2)
- **total**: DECIMAL(10,2)
- **created_at**: TIMESTAMP

#### 5. Tabla: payments
- **id**: INT (PK, AUTO_INCREMENT)
- **invoice_id**: INT (FK → invoices.id)
- **payment_uuid**: VARCHAR(36) UNIQUE
- **amount**: DECIMAL(10,2)
- **payment_date**: DATE
- **payment_method**: VARCHAR(50)
- **xml_path**: VARCHAR(255)
- **status**: ENUM('processed', 'pending', 'rejected')
- **created_at**: TIMESTAMP
- **updated_at**: TIMESTAMP

#### 6. Tabla: notifications
- **id**: INT (PK, AUTO_INCREMENT)
- **client_id**: INT (FK → clients.id)
- **invoice_id**: INT (FK → invoices.id)
- **type**: ENUM('new_invoice', 'payment_confirmation', 'due_reminder', 'overdue')
- **message**: TEXT
- **sent_at**: TIMESTAMP
- **status**: ENUM('sent', 'failed', 'pending')

#### 7. Tabla: activity_logs
- **id**: INT (PK, AUTO_INCREMENT)
- **user_id**: INT (FK → users.id)
- **action**: VARCHAR(100)
- **description**: TEXT
- **ip_address**: VARCHAR(45)
- **created_at**: TIMESTAMP

### Relaciones Principales
- users ─1:1─ clients
- clients ─1:N─ invoices
- invoices ─1:N─ invoice_items
- invoices ─1:N─ payments
- clients ─1:N─ notifications
- invoices ─1:N─ notifications
- users ─1:N─ activity_logs

---

# Módulo de Gestión Fiscal

## Características Principales

### 🔒 Control de Acceso
- Acceso exclusivo para usuarios con nivel "contador"
- Sistema de autenticación y autorización integrado

### 👥 Gestión de Clientes
- Registro y administración de clientes
- Control de declaraciones fiscales:
  - Mensuales
  - Bimestrales

### 📤 Procesamiento de Facturas
- Carga masiva de archivos XML
- Procesamiento automático de información
- Validación de estructura y contenido

### 💰 Análisis de IVA
Desglose detallado de las diferentes tasas de IVA en México:
- IVA 16%
- IVA 8%
- IVA Exento
- IVA 0%
- Otros tipos según requerimientos

### 📊 Reportes y Exportación
- Generación de reportes detallados
- Exportación a formato Excel
- Compatibilidad con declaraciones fiscales

### 📋 Sistema de Consultas
- Almacenamiento histórico de datos
- Filtros avanzados:
  - Por cliente
  - Por rango de fechas
  - Por tipo de IVA

## Estructura de Base de Datos

### Tabla: accountants
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | PK, AUTO_INCREMENT |
| user_id | INT | FK → users.id |
| created_at | TIMESTAMP | Fecha de creación |
| updated_at | TIMESTAMP | Fecha de actualización |

### Tabla: accountant_clients
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | PK, AUTO_INCREMENT |
| accountant_id | INT | FK → accountants.id |
| client_id | INT | FK → clients.id |
| created_at | TIMESTAMP | Fecha de creación |
| updated_at | TIMESTAMP | Fecha de actualización |

### Tabla: tax_reports
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | PK, AUTO_INCREMENT |
| accountant_id | INT | FK → accountants.id |
| client_id | INT | FK → clients.id |
| period | ENUM | 'Mensual', 'Bimestral' |
| start_date | DATE | Inicio del período |
| end_date | DATE | Fin del período |
| total_iva_16 | DECIMAL(10,2) | Total IVA 16% |
| total_iva_8 | DECIMAL(10,2) | Total IVA 8% |
| total_iva_exento | DECIMAL(10,2) | Total IVA Exento |
| total_iva_0 | DECIMAL(10,2) | Total IVA 0% |
| xml_count | INT | Cantidad de XML procesados |
| report_path | VARCHAR(255) | Ruta del reporte |
| created_at | TIMESTAMP | Fecha de creación |

### Tabla: uploaded_xmls
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | PK, AUTO_INCREMENT |
| accountant_id | INT | FK → accountants.id |
| client_id | INT | FK → clients.id |
| invoice_uuid | VARCHAR(36) | UUID único de factura |
| xml_path | VARCHAR(255) | Ruta del archivo XML |
| processed | BOOLEAN | Estado de procesamiento |
| created_at | TIMESTAMP | Fecha de creación |

## Flujo de Trabajo

1. ### Inicio de Sesión
   - Autenticación del contador
   - Acceso al panel de control

2. ### Gestión de Clientes
   - Alta de nuevos clientes
   - Asignación de clientes al contador
   - Configuración de períodos fiscales

3. ### Procesamiento de Documentos
   - Carga masiva de XML
   - Validación automática
   - Extracción de datos fiscales

4. ### Generación de Reportes
   - Cálculo de desgloses de IVA
   - Almacenamiento en base de datos
   - Generación de documentos Excel

5. ### Consulta y Seguimiento
   - Acceso al historial de reportes
   - Filtrado de información
   - Exportación de datos históricos

---
