<?php
include 'db.php'; // Include database connection

// Admin credentials
$email = "admin@gmail.com";
$password = "admin123"; // Plain text password
$hashed_password = password_hash($password, PASSWORD_DEFAULT); // Hash the password

// Insert into Users table
$stmt = $conn->prepare("INSERT INTO Users (email, password, role) VALUES (?, ?, 'Admin')");
$stmt->bind_param("ss", $email, $hashed_password);
$stmt->execute();

echo "Admin credentials inserted successfully!";
?>