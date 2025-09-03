<?php
session_start();
include 'db.php';

// Check if the user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_GET['doctor_id']) && isset($_GET['status'])) {
    $doctor_id = $_GET['doctor_id'];
    $status = $_GET['status'];

    $stmt = $conn->prepare("UPDATE Doctors SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $status, $doctor_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
}
?>