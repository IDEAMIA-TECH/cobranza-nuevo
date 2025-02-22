<?php

$stmt->execute();

$db->commit();

// Enviar correo de confirmación
try {
    $mailer = new Mailer();
    $mailer->sendPaymentConfirmation($client_data, $invoice_data, [
        'amount' => $amount,
        'payment_date' => $payment_date,
        'payment_method' => $payment_method,
        'reference' => $reference
    ]);
} catch (Exception $e) {
    error_log("Error al enviar confirmación de pago: " . $e->getMessage());
}

$_SESSION['success'] = "Pago registrado correctamente"; 