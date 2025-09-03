<?php
session_start();
include 'db.php';

// Check if the user is logged in and is a Doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['user_id']; // Current logged-in doctor's user_id

// Handle form submission for adding a prescription
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_prescription'])) {
    $patient_user_id = $_POST['patient_user_id'];
    $diagnosis = $_POST['diagnosis'];
    $prescription = $_POST['prescription'];
    $treatment_plan = $_POST['treatment_plan'];

    // Insert the prescription into the database
    $stmt = $conn->prepare("
        INSERT INTO MedicalRecords (doctor_user_id, patient_user_id, diagnosis, prescription, treatment_plan, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iisss", $doctor_id, $patient_user_id, $diagnosis, $prescription, $treatment_plan);

    if ($stmt->execute()) {
        $success_message = "Prescription added successfully!";
    } else {
        $error_message = "Error adding prescription: " . $stmt->error;
    }
    $stmt->close();
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Fetch all prescriptions
    $stmt = $conn->prepare("
        SELECT m.patient_user_id, p.name AS patient_name, m.diagnosis, m.prescription, m.treatment_plan, m.created_at
        FROM MedicalRecords m
        JOIN Patients p ON m.patient_user_id = p.user_id
        WHERE m.doctor_user_id = ?
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Set headers for CSV export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=prescriptions_export.csv');

    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Output the column headings
    fputcsv($output, array('Patient ID', 'Patient Name', 'Diagnosis', 'Prescription', 'Treatment Plan', 'Date'));

    // Output the data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

// Fetch all patients
$patients = [];
$stmt = $conn->prepare("SELECT * FROM Patients");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions and Diagnoses</title>
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

        /* Export Button */
        .btn-export {
            margin-bottom: 20px;
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
        <a href="doctor_patient_records.php"><i class="fas fa-file-medical"></i> Patient Records</a>
        <a href="doctor_prescriptions.php"><i class="fas fa-file-prescription"></i> Prescriptions</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>Prescriptions and Diagnoses</h2>

        <!-- Display success or error messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Export Buttons -->
        <div class="btn-export">
            <a href="doctor_prescriptions.php?export=csv" class="btn btn-success">
                <i class="fas fa-file-csv"></i> Export to CSV
            </a>
        </div>

        <!-- Patients Table -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Patient List</h5>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Contact Info</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td><?php echo $patient['user_id']; ?></td>
                                <td><?php echo $patient['name']; ?></td>
                                <td><?php echo $patient['age']; ?></td>
                                <td><?php echo $patient['gender']; ?></td>
                                <td><?php echo $patient['contact_info']; ?></td>
                                <td>
                                    <!-- Add Prescription Button -->
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addPrescriptionModal<?php echo $patient['user_id']; ?>">
                                        <i class="fas fa-plus"></i> Add Prescription
                                    </button>
                                </td>
                            </tr>

                            <!-- Add Prescription Modal for each patient -->
                            <div class="modal fade" id="addPrescriptionModal<?php echo $patient['user_id']; ?>" tabindex="-1" aria-labelledby="addPrescriptionModalLabel<?php echo $patient['user_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addPrescriptionModalLabel<?php echo $patient['user_id']; ?>">Add Prescription for <?php echo $patient['name']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="doctor_prescriptions.php" method="POST">
                                                <input type="hidden" name="patient_user_id" value="<?php echo $patient['user_id']; ?>">
                                                <div class="mb-3">
                                                    <label for="diagnosis" class="form-label">Diagnosis</label>
                                                    <input type="text" class="form-control" id="diagnosis" name="diagnosis" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="prescription" class="form-label">Prescription</label>
                                                    <textarea class="form-control" id="prescription" name="prescription" rows="3" required></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="treatment_plan" class="form-label">Treatment Plan</label>
                                                    <textarea class="form-control" id="treatment_plan" name="treatment_plan" rows="3" required></textarea>
                                                </div>
                                                <button type="submit" name="add_prescription" class="btn btn-primary">Submit</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>