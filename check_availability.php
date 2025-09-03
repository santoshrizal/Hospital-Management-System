<?php
require_once 'db.php';

header('Content-Type: application/json');

if (isset($_GET['doctor_id']) && isset($_GET['date'])) {
    $doctor_id = $_GET['doctor_id'];
    $date = $_GET['date'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as booked 
                           FROM appointments 
                           WHERE doctor_user_id = ? 
                           AND DATE(appointment_date) = ?");
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $booked = $result->fetch_assoc()['booked'];
    
    echo json_encode([
        'booked' => $booked,
        'available' => 5 - $booked
    ]);
} else {
    echo json_encode(['error' => 'Missing parameters']);
}