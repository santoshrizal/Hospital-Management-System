<?php
require_once 'db.php';
require_once 'vendor/autoload.php';

\Stripe\Stripe::setApiKey('');

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, 'your_webhook_signing_secret'
    );
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// Handle payment success
if ($event->type == 'checkout.session.completed') {
    $session = $event->data->object;
    
    if ($session->payment_status == 'paid') {
        $conn->begin_transaction();
        
        try {
            // Insert payment record 
            $stmt = $conn->prepare("INSERT IGNORE INTO appointment_payments 
                                  (appointment_id, stripe_payment_id, amount, currency, payment_status, customer_email) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", 
                $session->metadata->appointment_id ?? 0, 
                $session->payment_intent,
                $session->metadata->payment_amount,
                'npr',
                'succeeded',
                $session->customer_email
            );
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Webhook error: " . $e->getMessage());
        }
    }
}

http_response_code(200);

?>
