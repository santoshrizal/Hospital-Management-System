<?php
session_start();

// Check if there's a pending appointment
if (!isset($_SESSION['pending_appointment'])) {
    header("Location: patient_book_appointment.php");
    exit();
}

require_once 'vendor/autoload.php';

// Set your secret key
\Stripe\Stripe::setApiKey('');

// Retrieve appointment details from session
$appointment_data = $_SESSION['pending_appointment'];
$amount = $appointment_data['payment_amount'] * 100; // Convert to paisa

try {
    // Create Checkout Session with webhook
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'npr',
                'product_data' => [
                    'name' => 'Doctor Appointment Booking',
                ],
                'unit_amount' => $amount,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'http://localhost/FINAL/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'http://final/payment_cancel.php',
        'metadata' => [
            'patient_id' => $_SESSION['user_id'],
            'doctor_id' => $appointment_data['doctor_user_id'],
            'appointment_date' => $appointment_data['appointment_date'],
            'payment_amount' => $appointment_data['payment_amount']
        ]
    ]);

    // Store session ID in session for verification
    $_SESSION['stripe_session_id'] = $session->id;

    // Redirect to Stripe Checkout
    header("HTTP/1.1 303 See Other");
    header("Location: " . $session->url);
    exit();
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe error: " . $e->getMessage());
    $_SESSION['error'] = "Payment processing failed. Please try again.";
    header("Location: patient_book_appointment.php");
    exit();
}

?>
