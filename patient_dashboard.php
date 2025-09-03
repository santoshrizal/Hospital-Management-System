<?php
session_start();
require_once 'db.php';
date_default_timezone_set('Asia/Kathmandu');

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Patient') {
    header("Location: login.php");
    exit();
}

// Fetch patient details
$patient_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM Patients WHERE user_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch appointments with improved query
$appointments = [];
$query = "
    SELECT 
        a.appointment_id,
        DATE_FORMAT(CONVERT_TZ(a.appointment_date, '+00:00', '+05:45'), '%Y-%m-%d %h:%i %p') AS formatted_date,
        d.name AS doctor_name,
        dep.department_name,
        a.status,
        a.payment_amount
    FROM Appointments a
    JOIN Doctors d ON a.doctor_user_id = d.user_id
    JOIN department dep ON d.department_id = dep.department_id
    WHERE a.patient_user_id = ?
    ORDER BY a.appointment_date DESC
";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();
} else {
    error_log("Database error: " . $conn->error);
    $appointments = []; // Ensure empty array on error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* Inline CSS */
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Navbar */
        .navbar {
            background-color: #007bff; /* Blue background */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            height: 56px;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: bold;
            color: white !important;
            display: flex;
            align-items: center;
            font-size: 1.2rem;
        }

        .navbar-brand i {
            margin-right: 8px;
        }

        .navbar-nav .nav-link {
            color: white !important;
            padding: 8px 15px;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            height: calc(100vh - 56px);
            background-color: white;
            position: fixed;
            top: 56px;
            left: 0;
            padding-top: 20px;
            box-shadow: 2px 0px 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: black;
            padding: 12px 20px;
            font-size: 16px;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: 0.3s;
        }

        .sidebar a i {
            margin-right: 10px;
            font-size: 18px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #007bff;
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            margin-top: 56px; /* Adjusted for navbar */
        }

        /* Card Styling */
        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: bold;
        }

        /* Table Styling */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .table tr:hover {
            background-color: #f1f1f1;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="patient_dashboard.php">
                <i class="fas fa-hospital"></i> Hospital Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="patient_dashboard.php"><i class="fas fa-user"></i> Profile</a>
        <a href="patient_book_appointment.php"><i class="fas fa-calendar-check"></i> Book Appointment</a>
        <a href="patient_pay_bills.php"><i class="fas fa-money-bill-wave"></i> Pay Bills</a>
        <a href="patient_view_records.php"><i class="fas fa-file-medical"></i> Medical Records</a>
    </div>

    <div class="main-content">
        <h2>Patient Dashboard</h2>

        <!-- Display success/error messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Patient Profile Card -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Your Profile</h5>
                <p><strong>Name:</strong> <?= htmlspecialchars($patient['name']) ?></p>
                <p><strong>Age:</strong> <?= htmlspecialchars($patient['age']) ?></p>
                <p><strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?></p>
                <p><strong>Contact:</strong> <?= htmlspecialchars($patient['contact_info']) ?></p>
            </div>
        </div>

        <!-- Appointments Card -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Your Appointments</h5>
                
                <?php if (!empty($appointments)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Doctor</th>
                                    <th>Department</th>
                                    <th>Amount (NPR)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><?= htmlspecialchars($appt['formatted_date']) ?></td>
                                    <td><?= htmlspecialchars($appt['doctor_name']) ?></td>
                                    <td><?= htmlspecialchars($appt['department_name']) ?></td>
                                    <td><?= number_format($appt['payment_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $appt['status'] == 'Scheduled' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars($appt['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No appointments found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>












