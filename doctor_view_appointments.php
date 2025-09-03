<?php
session_start();
include 'db.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in and is a Doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Doctor') {
    header("Location: login.php");
    exit();
}

// Fetch doctor's appointments
$doctor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT a.appointment_id, a.appointment_date, a.status, p.name AS patient_name
    FROM Appointments a
    JOIN Patients p ON a.patient_user_id = p.user_id
    WHERE a.doctor_user_id = ?
    ORDER BY a.appointment_date
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
} else {
    echo "No appointments found for this doctor.";
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointments</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
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
            color: #fff !important;
            display: flex;
            align-items: center;
            font-size: 1.2rem;
        }

        .navbar-brand i {
            margin-right: 8px;
        }

        .navbar-nav .nav-link {
            color: #fff !important;
            padding: 8px 15px;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            height: calc(100vh - 56px);
            background-color: #fff;
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
            color: #333;
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
            <a class="navbar-brand" href="doctor_dashboard.php">
                <i class="fas fa-hospital"></i> Hospital Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="doctor_dashboard.php">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doctor_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="C:\xampp\htdocs\Final\logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="doctor_dashboard.php"><i class="fas fa-user"></i> Profile</a>
        <a href="view_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
        <a href="doctor_patient_records.php"><i class="fas fa-file-medical"></i> Patients Records</a>
        <a href="doctor_prescriptions.php"><i class="fas fa-file-prescription"></i> Prescriptions</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>View Appointments</h2>

        <!-- Appointments Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Appointment List</h5>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Patient Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($appointments)): ?>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo $appointment['appointment_id']; ?></td>
                                    <td><?php echo $appointment['appointment_date']; ?></td>
                                    <td><?php echo $appointment['patient_name']; ?></td>
                                    <td><?php echo $appointment['status']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>