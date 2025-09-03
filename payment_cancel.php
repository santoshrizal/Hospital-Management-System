<?php
session_start();
$_SESSION['error'] = "Payment was canceled. Please try again if you wish to complete your appointment booking.";
header("Location: patient_book_appointment.php");
exit();
?>