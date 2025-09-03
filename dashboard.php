<?php
// Start session with proper configuration
session_set_cookie_params([
    'lifetime' => 0, // Until browser is closed
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']), // Auto-detect if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Check if the user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Fetch data from the database
$availableDoctors = 0;
$totalPatients = 0;
$todayAppointments = 0;

// Fetch Available Doctors
$query = "SELECT COUNT(*) as count FROM Doctors WHERE status = 'Available'";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $availableDoctors = $row['count'];
}

// Fetch Total Patients
$query = "SELECT COUNT(*) as count FROM Patients";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalPatients = $row['count'];
}

// Fetch Today's Appointments (fixed query)
$today = date("Y-m-d"); // Get today's date in YYYY-MM-DD format
$query = "SELECT COUNT(*) as count FROM Appointments WHERE DATE(appointment_date) = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $todayAppointments = $row['count'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Internal CSS -->
<!-- Replace your <style> block in dashboard.php with this -->
<style>
    body {
        background-color: #f8f9fa;
        font-family: 'Arial', sans-serif;
        padding-top: 56px; /* To offset the fixed navbar */
    }

    .navbar {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        background-color: #007bff;
        z-index: 1030;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
    }

    .navbar-brand {
        font-weight: bold;
        font-size: 1.5rem;
        color: #fff !important;
    }

    .navbar-nav .nav-link {
        color: #fff !important;
    }

    .navbar-nav .fas {
        color: #fff !important;
    }

    .sidebar {
        position: fixed;
        top: 56px; /* Height of navbar */
        left: 0;
        bottom: 0;
        width: 250px;
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
        background-color: #fff;
        z-index: 1020;
        overflow-y: auto;
        height: calc(100vh - 56px);
    }

    .sidebar-sticky {
        padding-top: 20px;
        height: 100%;
        overflow-x: hidden;
    }

    .main-content {
        margin-left: 250px;
        padding: 20px;
        margin-top: 0;
    }

    .nav-item {
        margin-bottom: 10px;
    }

    .nav-link {
        color: #333;
        font-weight: 500;
        padding: 10px 15px;
        border-radius: 5px;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        background-color: #007bff;
        color: #fff !important;
    }

    .nav-link.active {
        background-color: #007bff;
        color: #fff !important;
    }

    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: bold;
        color: #333;
    }

    .card-text {
        font-size: 1.5rem;
        font-weight: bold;
        color: #007bff;
    }

    .fa-sign-out-alt,
    .fa-home,
    .fa-user-md,
    .fa-user-injured,
    .fa-calendar-check,
    .fa-bed,
    .fa-money-bill-wave,
    .fa-chart-line {
        margin-right: 10px;
    }
</style>

</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-hospital me-2"></i>Hospital Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar and Main Content -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-light sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_doctors.php">
                                <i class="fas fa-user-md"></i> Manage Doctors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_patients.php">
                                <i class="fas fa-user-injured"></i> Manage Patients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_appointments.php">
                                <i class="fas fa-calendar-check"></i> View Appointments
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="view_payments.php">
                                <i class="fas fa-money-bill-wave"></i> View Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_reports.php">
                                <i class="fas fa-chart-line"></i> View Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="nurse_management.php">
                                <i class="fas fa-chart-line"></i> nurse
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mt-3">Dashboard</h2>
                <div class="row mt-4">
                    <!-- Available Doctors -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-user-md"></i> Available Doctors
                                </h5>
                                <p class="card-text"><?php echo $availableDoctors; ?></p>
                            </div>
                        </div>
                    </div>
                    <!-- Total Patients -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-user-injured"></i> Total Patients
                                </h5>
                                <p class="card-text"><?php echo $totalPatients; ?></p>
                            </div>
                        </div>
                    </div>
                    <!-- Today's Appointments -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-calendar-check"></i> Today's Appointments
                                </h5>
                                <p class="card-text"><?php echo $todayAppointments; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>