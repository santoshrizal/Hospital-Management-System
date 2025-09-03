<?php
session_start(); // Start the session

// Check if the user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .welcome-section {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('hospital_image.jpg'); /* Add a hospital background image */
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
        }
        .welcome-section h1 {
            font-size: 4rem;
            font-weight: bold;
        }
        .welcome-section p {
            font-size: 1.5rem;
        }
        .btn-custom {
            margin: 10px;
            padding: 10px 20px;
            font-size: 1.2rem;
        }

        /* Custom Navbar Styles */
        .navbar {
            padding: 1.5rem 1rem; 
        }
        .navbar-brand {
            font-size: 1.5rem; 
        }
        .nav-link {
            font-size: 1.2rem; 
        }
        .nav-link.active {
            background-color: #007bff;
            color: #fff !important;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-hospital"></i>  Hospital Management System
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
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Welcome Section -->
    <div class="welcome-section">
        <div>
            <h1>Welcome to Hospital Management System</h1>
            <p>Manage your health and appointments with ease.</p>
            <div>
                <?php if ($isLoggedIn): ?>
                    <!-- If logged in, allow access to the Book Appointment page -->
                    <a href="patient_book_appointment.php" class="btn btn-primary btn-custom">
                        <i class="fas fa-calendar-check"></i> Book Appointment
                    </a>
                <?php else: ?>
                    <!-- If not logged in, redirect to the login page -->
                    <a href="login.php" class="btn btn-primary btn-custom">
                        <i class="fas fa-calendar-check"></i> Book Appointment
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>