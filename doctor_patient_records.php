<?php
session_start();
include 'db.php';

// Check if the user is logged in and is a Doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Doctor') {
    header("Location: login.php");
    exit();
}

$doctor_id = $_SESSION['user_id']; // Current logged-in doctor's user_id

// Fetch all patients
$patients = [];
$stmt = $conn->prepare("SELECT * FROM Patients");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}
$stmt->close();

// Handle update medical record
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_record'])) {
    $patient_user_id = $_POST['patient_user_id'];
    $diagnosis = $_POST['diagnosis'];
    $prescription = $_POST['prescription'];
    $test_results = $_POST['test_results'];

    // Check if the record exists
    $stmt = $conn->prepare("SELECT * FROM MedicalRecords WHERE patient_user_id = ? AND doctor_user_id = ?");
    $stmt->bind_param("ii", $patient_user_id, $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE MedicalRecords SET diagnosis = ?, prescription = ?, test_results = ? WHERE patient_user_id = ? AND doctor_user_id = ?");
        $stmt->bind_param("sssii", $diagnosis, $prescription, $test_results, $patient_user_id, $doctor_id);
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO MedicalRecords (doctor_user_id, patient_user_id, diagnosis, prescription, test_results) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $doctor_id, $patient_user_id, $diagnosis, $prescription, $test_results);
    }
    $stmt->execute();
    $stmt->close();

    // Refresh page to show updated data
    header("Location: doctor_patient_records.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records</title>
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

        /* Modal Styling */
        .modal-content {
            border-radius: 10px;
        }

        .modal-header {
            background-color: #007bff;
            color: white;
            border-radius: 10px 10px 0 0;
        }

        .modal-title {
            font-weight: bold;
        }

        .modal-body textarea {
            resize: none;
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
                        <a class="nav-link" href="C:\xampp\htdocs\Final\logout.php.php">
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
        <h2>Patient Records</h2>

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
                                    <!-- View/Edit Button -->
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewRecordModal<?php echo $patient['user_id']; ?>">
                                        <i class="fas fa-eye"></i> View/Edit
                                    </button>
                                </td>
                            </tr>

                            <!-- View/Edit Record Modal -->
                            <div class="modal fade" id="viewRecordModal<?php echo $patient['user_id']; ?>" tabindex="-1" aria-labelledby="viewRecordModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="viewRecordModalLabel">Medical Record for <?php echo $patient['name']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?php
                                            // Fetch medical record for the patient
                                            $stmt = $conn->prepare("SELECT * FROM MedicalRecords WHERE patient_user_id = ? AND doctor_user_id = ?");
                                            $stmt->bind_param("ii", $patient['user_id'], $doctor_id);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            $record = $result->fetch_assoc();
                                            $stmt->close();
                                            ?>
                                            <form action="doctor_patient_records.php" method="POST">
                                                <input type="hidden" name="patient_user_id" value="<?php echo $patient['user_id']; ?>">
                                                <div class="mb-3">
                                                    <label for="diagnosis" class="form-label">Diagnosis</label>
                                                    <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required><?php echo $record['diagnosis'] ?? ''; ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="prescription" class="form-label">Prescription</label>
                                                    <textarea class="form-control" id="prescription" name="prescription" rows="3" required><?php echo $record['prescription'] ?? ''; ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="test_results" class="form-label">Test Results</label>
                                                    <textarea class="form-control" id="test_results" name="test_results" rows="3"><?php echo $record['test_results'] ?? ''; ?></textarea>
                                                </div>
                                                <button type="submit" name="update_record" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Save Changes
                                                </button>
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