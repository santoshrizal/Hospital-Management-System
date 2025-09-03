<?php
session_start();
include 'db.php';

// Check if the user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Handle Add Patient
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_patient'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $contact_info = $_POST['contact_info'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // First, insert into Users table
        $stmt = $conn->prepare("INSERT INTO Users (email, password, role) VALUES (?, ?, 'Patient')");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        $stmt->close();

        // Then, insert into Patients table
        $stmt = $conn->prepare("INSERT INTO Patients (user_id, name, age, gender, contact_info) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiss", $user_id, $name, $age, $gender, $contact_info);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        header("Location: manage_patients.php");
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error adding patient: " . $e->getMessage();
    }
}

// Handle Update Patient
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_patient'])) {
    $user_id = $_POST['user_id']; // Use user_id instead of patient_id
    $name = $_POST['name'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $contact_info = $_POST['contact_info'];

    // Update Patients table
    $stmt = $conn->prepare("UPDATE Patients SET name = ?, age = ?, gender = ?, contact_info = ? WHERE user_id = ?");
    $stmt->bind_param("sissi", $name, $age, $gender, $contact_info, $user_id);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_patients.php");
    exit();
}

// Handle Delete Patient
if (isset($_GET['delete_id'])) {
    $user_id = $_GET['delete_id']; // Use user_id instead of patient_id

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete from Patients table
        $stmt = $conn->prepare("DELETE FROM Patients WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Delete from Users table
        $stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        header("Location: manage_patients.php");
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error deleting patient: " . $e->getMessage();
    }
}

// Fetch all patients with their email addresses
$patients = [];
$stmt = $conn->prepare("SELECT p.*, u.email 
                       FROM Patients p 
                       JOIN Users u ON p.user_id = u.user_id");
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
    <title>Manage Patients</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Internal CSS -->
   <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Arial', sans-serif;
        padding-top: 56px; /* Add padding to account for fixed navbar */
    }

    .navbar {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        background-color: #007bff;
        z-index: 1030; /* Higher than sidebar */
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

    /* Change navbar links to white */
    .navbar-nav .nav-link {
        color: #fff !important;
    }

    /* Change navbar icons to white */
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
        z-index: 1020; /* Below navbar but above content */
        overflow-y: auto; /* Enable vertical scrolling */
        height: calc(100vh - 56px); /* Full height minus navbar */
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

    /* Rest of your existing CSS remains the same */
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
        background-color: #007bff;
        color: #fff;
    }

    .table tr:hover {
        background-color: #f1f1f1;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 0.875rem;
    }

    .btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
    }

    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-hospital"></i> Hospital Management System
            </a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
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

    <!-- Sidebar and Main Content -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 bg-light sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_doctors.php">
                                <i class="fas fa-user-md"></i> Manage Doctors
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_patients.php">
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
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mt-3">Manage Patients</h2>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Add Patient Form -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Add New Patient</h5>
                        <form action="manage_patients.php" method="POST">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="age" class="form-label">Age</label>
                                <input type="number" class="form-control" id="age" name="age" required>
                            </div>
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Info</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" required>
                            </div>
                            <button type="submit" name="add_patient" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Patient
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Patients Table -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Patient List</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Serial No.</th> <!-- Changed from ID to Serial No. -->
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Contact Info</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $index => $patient): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td> <!-- Serial number -->
                                        <td><?php echo $patient['name']; ?></td>
                                        <td><?php echo $patient['email']; ?></td>
                                        <td><?php echo $patient['age']; ?></td>
                                        <td><?php echo $patient['gender']; ?></td>
                                        <td><?php echo $patient['contact_info']; ?></td>
                                        <td>
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editPatientModal<?php echo $patient['user_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <!-- Delete Button -->
                                            <a href="manage_patients.php?delete_id=<?php echo $patient['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure? This will also delete the user account.')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Patient Modals -->
    <?php foreach ($patients as $patient): ?>
        <div class="modal fade" id="editPatientModal<?php echo $patient['user_id']; ?>" tabindex="-1" aria-labelledby="editPatientModalLabel<?php echo $patient['user_id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPatientModalLabel<?php echo $patient['user_id']; ?>">Edit Patient</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form action="manage_patients.php" method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $patient['user_id']; ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo $patient['name']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="age" class="form-label">Age</label>
                                <input type="number" class="form-control" id="age" name="age" value="<?php echo $patient['age']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-control" id="gender" name="gender" required>
                                    <option value="Male" <?php echo ($patient['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($patient['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($patient['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Info</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" value="<?php echo $patient['contact_info']; ?>" required>
                            </div>
                            <button type="submit" name="update_patient" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
