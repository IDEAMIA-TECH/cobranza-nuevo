<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Configuración del servidor SMTP
        $this->mail->isSMTP();
        $this->mail->Host = 'mail.devgdlhost.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'cobranza@devgdlhost.com';
        $this->mail->Password = ')S8y{k6aHqf~';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        
        // Configuración general
        $this->mail->setFrom('no-reply@devgdl.com', 'Sistema de Cobranza');
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }
    
    public function sendNewInvoiceNotification($client, $invoice) {
        try {
            $this->mail->addAddress($client['email'], $client['business_name']);
            $this->mail->Subject = 'Nueva Factura - IDEAMIA Tech';
            
            $this->mail->Body = "
                <h2>Nueva Factura Registrada</h2>
                <p>Estimado cliente {$client['business_name']},</p>
                
                <p>Le informamos que se ha registrado una nueva factura a su nombre.</p>
                
                <p>Detalles de la factura:</p>
                <ul>
                    <li>Número de Factura: {$invoice['invoice_number']}</li>
                    <li>Monto Total: $" . number_format($invoice['total_amount'], 2) . "</li>
                    <li>Fecha de Emisión: " . date('d/m/Y', strtotime($invoice['issue_date'])) . "</li>
                    <li>Fecha de Vencimiento: " . date('d/m/Y', strtotime($invoice['due_date'])) . "</li>
                </ul>
                
                <p>Por favor, asegúrese de realizar el pago antes de la fecha de vencimiento.</p>
                
                <p>Si tiene alguna pregunta o inquietud, no dude en contactarnos.</p>
                
                <p>Atentamente,<br>
                IDEAMIA Tech</p>
            ";
            
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando correo: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendPaymentConfirmation($client, $invoice, $payment_info) {
        try {
            $this->mail->addAddress($client['email'], $client['business_name']);
            $this->mail->Subject = 'Confirmación de Pago - IDEAMIA Tech';
            
            $this->mail->Body = "
                <h2>Confirmación de Pago Recibido</h2>
                <p>Estimado cliente {$client['business_name']},</p>
                
                <p>Hemos registrado correctamente el pago para la factura 
                   <strong>{$invoice['invoice_number']}</strong>.</p>
                
                <p>Detalles del pago:</p>
                <ul>
                    <li>Monto: $" . number_format($payment_info['amount'], 2) . "</li>
                    <li>Fecha de Pago: " . date('d/m/Y', strtotime($payment_info['payment_date'])) . "</li>
                    <li>Método de Pago: {$payment_info['payment_method']}</li>
                    " . ($payment_info['reference'] ? "<li>Referencia: {$payment_info['reference']}</li>" : "") . "
                </ul>
                
                <p>Gracias por su pago.</p>
                
                <p>Atentamente,<br>
                IDEAMIA Tech</p>
            ";
            
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando correo: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendDueReminder($client, $invoice, $days_to_due) {
        try {
            $this->mail->addAddress($client['email'], $client['business_name']);
            $this->mail->Subject = 'Recordatorio de Vencimiento - IDEAMIA Tech';
            
            $this->mail->Body = "
                <h2>Recordatorio de Vencimiento</h2>
                <p>Estimado cliente {$client['business_name']},</p>
                
                <p>Le recordamos que la factura <strong>{$invoice['invoice_number']}</strong> 
                   está próxima a vencer.</p>
                
                <p>Detalles de la factura:</p>
                <ul>
                    <li>Número de Factura: {$invoice['invoice_number']}</li>
                    <li>Monto Total: $" . number_format($invoice['total_amount'], 2) . "</li>
                    <li>Fecha de Vencimiento: " . date('d/m/Y', strtotime($invoice['due_date'])) . "</li>
                    <li>Días Restantes: {$days_to_due}</li>
                </ul>
                
                <p>Por favor, asegúrese de realizar el pago antes de la fecha de vencimiento.</p>
                
                <p>Si ya realizó el pago, por favor haga caso omiso de este mensaje.</p>
                
                <p>Atentamente,<br>
                IDEAMIA Tech</p>
            ";
            
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando correo: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendOverdueNotification($client, $invoice) {
        try {
            $this->mail->addAddress($client['email'], $client['business_name']);
            $this->mail->Subject = 'Factura Vencida - IDEAMIA Tech';
            
            $this->mail->Body = "
                <h2>Notificación de Factura Vencida</h2>
                <p>Estimado cliente {$client['business_name']},</p>
                
                <p>Le informamos que la factura <strong>{$invoice['invoice_number']}</strong> 
                   se encuentra vencida.</p>
                
                <p>Detalles de la factura:</p>
                <ul>
                    <li>Número de Factura: {$invoice['invoice_number']}</li>
                    <li>Monto Total: $" . number_format($invoice['total_amount'], 2) . "</li>
                    <li>Fecha de Vencimiento: " . date('d/m/Y', strtotime($invoice['due_date'])) . "</li>
                </ul>
                
                <p>Por favor, realice el pago correspondiente lo antes posible para evitar 
                   cargos adicionales.</p>
                
                <p>Si ya realizó el pago, por favor haga caso omiso de este mensaje.</p>
                
                <p>Atentamente,<br>
                IDEAMIA Tech</p>
            ";
            
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando correo: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendWelcomeEmail($email, $businessName) {
        try {
            $this->mail->addAddress($email);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Bienvenido al Sistema de Cobranza';
            
            // Contenido del correo
            $this->mail->Body = "
                <h2>¡Bienvenido {$businessName}!</h2>
                <p>Gracias por registrarte en nuestro Sistema de Cobranza.</p>
                <p>Tu cuenta está siendo revisada por nuestro equipo administrativo. 
                   Te notificaremos cuando haya sido activada.</p>
                <p>Una vez activada, podrás acceder al sistema con tu correo electrónico:</p>
                <p><strong>{$email}</strong></p>
                <br>
                <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
                <br>
                <p>Saludos cordiales,</p>
                <p>Equipo de Sistema de Cobranza</p>
            ";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo de bienvenida: {$e->getMessage()}");
            return false;
        }
    }
    
    public function sendAdminNewRegistrationNotification($clientData) {
        try {
            // Email del administrador (podría venir de configuración)
            $this->mail->addAddress('admin@devgdlhost.com');
            $this->mail->Subject = 'Nuevo Registro de Cliente - Requiere Aprobación';
            
            // Contenido del correo
            $this->mail->Body = "
                <h2>Nuevo Cliente Registrado</h2>
                <p>Se ha registrado un nuevo cliente que requiere aprobación:</p>
                <br>
                <h3>Datos del Cliente:</h3>
                <ul>
                    <li><strong>Razón Social:</strong> {$clientData['business_name']}</li>
                    <li><strong>RFC:</strong> {$clientData['rfc']}</li>
                    <li><strong>Email:</strong> {$clientData['email']}</li>
                    <li><strong>Teléfono:</strong> {$clientData['phone']}</li>
                    <li><strong>Contacto:</strong> {$clientData['contact_name']}</li>
                </ul>
                <br>
                <p>Para aprobar o rechazar este registro, ingrese al panel de administración:</p>
                <p><a href='" . getBaseUrl() . "/admin/clients/pending.php'>Gestionar Clientes Pendientes</a></p>
                <br>
                <p>Saludos,</p>
                <p>Sistema de Cobranza</p>
            ";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar notificación al admin: {$e->getMessage()}");
            return false;
        }
    }
    
    public function sendAccountActivationEmail($email, $businessName) {
        try {
            $this->mail->addAddress($email);
            $this->mail->Subject = 'Cuenta Activada - Sistema de Cobranza';
            
            $this->mail->Body = "
                <h2>¡Felicidades {$businessName}!</h2>
                <p>Tu cuenta ha sido activada exitosamente.</p>
                <p>Ya puedes acceder al sistema con tu correo electrónico y contraseña.</p>
                <p><a href='" . getBaseUrl() . "/login.php'>Iniciar Sesión</a></p>
                <br>
                <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
                <br>
                <p>Saludos cordiales,</p>
                <p>Equipo de Sistema de Cobranza</p>
            ";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo de activación: {$e->getMessage()}");
            return false;
        }
    }
    
    public function sendAccountRejectionEmail($email, $businessName) {
        try {
            $this->mail->addAddress($email);
            $this->mail->Subject = 'Registro No Aprobado - Sistema de Cobranza';
            
            $this->mail->Body = "
                <h2>Estimado {$businessName}</h2>
                <p>Lamentamos informarte que tu solicitud de registro no ha sido aprobada.</p>
                <p>Si consideras que esto es un error o necesitas más información, 
                   por favor contáctanos respondiendo este correo.</p>
                <br>
                <p>Saludos cordiales,</p>
                <p>Equipo de Sistema de Cobranza</p>
            ";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo de rechazo: {$e->getMessage()}");
            return false;
        }
    }
    
    public function sendPasswordResetEmail($email, $businessName, $token) {
        try {
            $this->mail->addAddress($email);
            $this->mail->Subject = 'Recuperación de Contraseña - Sistema de Cobranza';
            
            $resetLink = getBaseUrl() . '/reset-password.php?token=' . $token;
            
            $this->mail->Body = "
                <h2>Recuperación de Contraseña</h2>
                <p>Hola {$businessName},</p>
                <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.</p>
                <p>Para continuar con el proceso, haz clic en el siguiente enlace:</p>
                <p><a href='{$resetLink}'>{$resetLink}</a></p>
                <p>Este enlace expirará en 1 hora.</p>
                <p>Si no solicitaste este cambio, puedes ignorar este correo.</p>
                <br>
                <p>Saludos cordiales,</p>
                <p>Equipo de Sistema de Cobranza</p>
            ";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Error al enviar correo de recuperación: {$e->getMessage()}");
            return false;
        }
    }
    
    public function sendOverdueInvoiceNotification($email, $business_name, $invoice_number, $total_amount, $due_date, $days_overdue) {
        try {
            $this->mail->addAddress($email, $business_name);
            $this->mail->Subject = "Factura Vencida - $invoice_number";
            
            $this->mail->Body = "
                <h2>Notificación de Factura Vencida</h2>
                <p>Estimado cliente $business_name,</p>
                
                <p>Le informamos que la factura <strong>$invoice_number</strong> se encuentra vencida.</p>
                
                <p>Detalles de la factura:</p>
                <ul>
                    <li>Número de Factura: $invoice_number</li>
                    <li>Monto Total: $" . number_format($total_amount, 2) . "</li>
                    <li>Fecha de Vencimiento: " . date('d/m/Y', strtotime($due_date)) . "</li>
                    <li>Días de Vencimiento: $days_overdue</li>
                </ul>
                
                <p>Por favor, realice el pago correspondiente lo antes posible para evitar cargos adicionales.</p>
                
                <p>Si ya realizó el pago, por favor haga caso omiso de este mensaje.</p>
                
                <p>Atentamente,<br>
                IDEAMIA Tech</p>
            ";
            
            $this->mail->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Error enviando correo de factura vencida: " . $e->getMessage());
            return false;
        }
    }
    
    private function getEmailTemplate($type, $data) {
        $template_path = __DIR__ . '/email_templates/' . $type . '.html';
        
        if (!file_exists($template_path)) {
            throw new Exception("Template no encontrado: " . $type);
        }
        
        $template = file_get_contents($template_path);
        
        foreach ($data as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
    
    private function logEmail($recipient, $subject, $type, $related_id = null, $status = 'sent', $error = null) {
        try {
            $query = "INSERT INTO email_logs 
                     (recipient_email, recipient_name, subject, email_type, related_id, status, error_message) 
                     VALUES 
                     (:email, :name, :subject, :type, :related_id, :status, :error)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $recipient['email']);
            $stmt->bindParam(':name', $recipient['name']);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':related_id', $related_id);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':error', $error);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error logging email: " . $e->getMessage());
            return false;
        }
    }
} 