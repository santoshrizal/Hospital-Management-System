<?php
session_start();
require_once 'db.php';
date_default_timezone_set('Asia/Kathmandu');

// Verify session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check for session ID
if (!isset($_GET['session_id']) || !isset($_SESSION['stripe_session_id'])) {
    $_SESSION['error'] = "Invalid payment session";
    header("Location: patient_book_appointment.php");
    exit();
}

// Verify session ID matches
if ($_GET['session_id'] !== $_SESSION['stripe_session_id']) {
    $_SESSION['error'] = "Session verification failed";
    header("Location: patient_book_appointment.php");
    exit();
}

require_once 'vendor/autoload.php';
\Stripe\Stripe::setApiKey('sk_test_51RZTbAP7LZzKUp9wfkMeXkunbq7Q4gipW6Y1qatrgUKYNFjAhjSxV1pejvNpVw7zpzxAsmKnxJ4IchaynEFeokhd001C08HcV1');

try {
    // Retrieve the Checkout Session
    $session = \Stripe\Checkout\Session::retrieve($_GET['session_id']);
    
    // Verify payment is completed
    if ($session->payment_status !== 'paid') {
        throw new Exception("Payment not completed");
    }

    // Check for pending appointment data
    if (!isset($_SESSION['pending_appointment'])) {
        throw new Exception("No appointment data found");
    }

    $appointment_data = $_SESSION['pending_appointment'];
    $conn->begin_transaction();

    // Prepare variables for appointment insertion
    $doctor_id = $appointment_data['doctor_user_id'];
    $patient_id = $_SESSION['user_id'];
    $appointment_date = $appointment_data['appointment_date'];
    $payment_amount = $appointment_data['payment_amount'];
    $status = 'Scheduled';

    // 1. Insert appointment
    $stmt = $conn->prepare("INSERT INTO Appointments 
                          (doctor_user_id, patient_user_id, appointment_date, payment_amount, status) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisds", 
        $doctor_id,
        $patient_id,
        $appointment_date,
        $payment_amount,
        $status
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Appointment creation failed: " . $stmt->error);
    }

    $appointment_id = $conn->insert_id;
    $stmt->close();

    // Prepare variables for payment record
    $stripe_payment_id = $session->payment_intent;
    $currency = 'npr';
    $payment_status = 'succeeded';
    $customer_email = $_SESSION['email'] ?? ''; // Fallback to empty string if email not in session

    // 2. Insert payment record
    $stmt = $conn->prepare("INSERT INTO appointment_payments 
                          (appointment_id, stripe_payment_id, amount, currency, payment_status, customer_email) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", 
        $appointment_id,
        $stripe_payment_id,
        $payment_amount,
        $currency,
        $payment_status,
        $customer_email
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Payment record creation failed: " . $stmt->error);
    }
    $stmt->close();

    // 3. Insert billing record
    $purpose = 'Appointment';
    $billing_status = 'Paid';
    
    $stmt = $conn->prepare("INSERT INTO Billing 
                          (patient_user_id, appointment_id, amount, purpose, status) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iidss", 
        $patient_id,
        $appointment_id,
        $payment_amount,
        $purpose,
        $billing_status
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Billing record creation failed: " . $stmt->error);
    }

    // Commit transaction
    $conn->commit();
    $stmt->close();

    // Clear session and redirect
    unset($_SESSION['pending_appointment']);
    unset($_SESSION['stripe_session_id']);
    $_SESSION['success'] = "Appointment booked successfully!";
    header("Location: patient_dashboard.php");
    exit();

} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    
    error_log("Payment processing error: " . $e->getMessage());
    $_SESSION['error'] = "Error processing your appointment: " . $e->getMessage();
    header("Location: patient_book_appointment.php");
    exit();
}
?>