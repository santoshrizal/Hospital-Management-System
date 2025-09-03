<?php
session_start();
include 'db.php';

// Check if the user is logged in and is a Doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Doctor') {
    header("Location: login.php");
    exit();
}

// Fetch doctor details with department name
$doctor_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT Doctors.name, Doctors.contact_info, Doctors.status, department.department_name 
    FROM Doctors 
    JOIN department ON Doctors.department_id = department.department_id 
    WHERE Doctors.user_id = ?
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$doctor = $result->fetch_assoc();
$stmt->close();

// Fetch assigned nurses
$stmt = $conn->prepare("
    SELECT n.name, n.phone, n.status 
    FROM doctor_nurse_assignments dna
    JOIN nurses n ON dna.nurse_id = n.nurse_id
    WHERE dna.doctor_id = ? AND dna.is_active = 1
");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$assigned_nurses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle status change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_status'])) {
    $new_status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE Doctors SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_status, $doctor_id);
    $stmt->execute();
    $stmt->close();

    // Refresh doctor details
    $stmt = $conn->prepare("
        SELECT Doctors.name, Doctors.contact_info, Doctors.status, department.department_name 
        FROM Doctors 
        JOIN department ON Doctors.department_id = department.department_id 
        WHERE Doctors.user_id = ?
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard</title>
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

        /* Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            margin-top: 56px; /* Adjusted for navbar */
        }

        /* Card styling */
        .card {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: #007bff;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        /* Status indicators */
        .status-available { color: #28a745; font-weight: bold; }
        .status-not-available { color: #dc3545; font-weight: bold; }
        
        /* Table styling */
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background-color: #007bff;
            color: white;
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
                        <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="doctor_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
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
        <a href="doctor_dashboard.php" class="active"><i class="fas fa-user"></i> Profile</a>
        <a href="doctor_view_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
        <a href="doctor_patient_records.php"><i class="fas fa-file-medical"></i> Patients Records</a>
        <a href="doctor_prescriptions.php"><i class="fas fa-file-prescription"></i> Prescriptions</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>Doctor Dashboard</h2>

        <!-- Doctor Profile -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Doctor Profile</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($doctor['name']); ?></p>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($doctor['department_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Contact Info:</strong> <?php echo htmlspecialchars($doctor['contact_info']); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="status-<?php echo strtolower(str_replace(' ', '-', $doctor['status'])); ?>">
                                <?php echo htmlspecialchars($doctor['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Availability Status -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Availability Status</h5>
            </div>
            <div class="card-body">
                <form action="doctor_dashboard.php" method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Change Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="Available" <?php echo ($doctor['status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                                    <option value="Not Available" <?php echo ($doctor['status'] == 'Not Available') ? 'selected' : ''; ?>>Not Available</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" name="change_status" class="btn btn-primary">
                                <i class="fas fa-sync"></i> Update Status
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Assigned Nurses -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Assigned Nurses</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($assigned_nurses)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>S.N</th>
                                    <th>Nurse Name</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_nurses as $index => $nurse): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($nurse['name']); ?></td>
                                        <td><?php echo htmlspecialchars($nurse['phone']); ?></td>
                                        <td class="status-<?php echo strtolower(str_replace(' ', '-', $nurse['status'])); ?>">
                                            <?php echo htmlspecialchars($nurse['status']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No nurses are currently assigned to you.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>