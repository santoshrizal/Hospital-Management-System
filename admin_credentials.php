<?php
include 'db.php';

// Admin credentials
$admin_email = "admin@gmail.com";
$admin_plain_password = "admin123";

// Hash the password
$hashed_password = password_hash($admin_plain_password, PASSWORD_DEFAULT);

// Update admin password
$stmt = $conn->prepare("UPDATE Users SET password = ? WHERE email = ? AND role = 'Admin'");
$stmt->bind_param("ss", $hashed_password, $admin_email);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "Admin password has been successfully updated!";
    } else {
        echo "No admin account found with that email.";
    }
} else {
    echo "Error updating admin password: " . $conn->error;
}

$stmt->close();
$conn->close();
?>