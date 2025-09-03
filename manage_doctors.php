<?php
session_start();
include 'db.php';

// Check if the user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Fetch all departments
$departments = [];
$stmt = $conn->prepare("SELECT * FROM department");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}
$stmt->close();

// Handle Add Doctor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_doctor'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password
    $department_id = $_POST['department'];
    $contact_info = $_POST['contact_info'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // First, insert into Users table
        $stmt = $conn->prepare("INSERT INTO Users (email, password, role) VALUES (?, ?, 'Doctor')");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        $stmt->close();

        // Then, insert into Doctors table
        $stmt = $conn->prepare("INSERT INTO Doctors (user_id, name, department_id, contact_info) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isii", $user_id, $name, $department_id, $contact_info);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        header("Location: manage_doctors.php");
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error adding doctor: " . $e->getMessage();
    }
}

// Handle Edit Doctor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_doctor'])) {
    $doctor_id = $_POST['doctor_id'];
    $name = $_POST['name'];
    $department_id = $_POST['department'];
    $contact_info = $_POST['contact_info'];

    // Update Doctors table
    $stmt = $conn->prepare("UPDATE Doctors SET name = ?, department_id = ?, contact_info = ? WHERE user_id = ?");
    $stmt->bind_param("sisi", $name, $department_id, $contact_info, $doctor_id);
    $stmt->execute();
    $stmt->close();

    header("Location: manage_doctors.php");
    exit();
}

// Handle Delete Doctor
if (isset($_GET['delete_id'])) {
    $doctor_id = $_GET['delete_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // First get the user_id
        $stmt = $conn->prepare("SELECT user_id FROM Doctors WHERE user_id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doctor = $result->fetch_assoc();
        $user_id = $doctor['user_id'];
        $stmt->close();

        // Delete from Doctors table
        $stmt = $conn->prepare("DELETE FROM Doctors WHERE user_id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $stmt->close();

        // Delete from Users table
        $stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        header("Location: manage_doctors.php");
        exit();
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error deleting doctor: " . $e->getMessage();
    }
}

// Fetch all doctors with department names
$doctors = [];
$stmt = $conn->prepare("SELECT d.user_id, d.name, d.contact_info, d.status, d.department_id, dep.department_name, u.email
                        FROM Doctors d
                        JOIN department dep ON d.department_id = dep.department_id
                        JOIN Users u ON d.user_id = u.user_id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $doctors[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors</title>
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
                            <a class="nav-link active" href="manage_doctors.php">
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
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mt-3">Manage Doctors</h2>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Add Doctor Form -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Add New Doctor</h5>
                        <form action="manage_doctors.php" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <select class="form-control" id="department" name="department" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $department): ?>
                                            <option value="<?php echo $department['department_id']; ?>">
                                                <?php echo $department['department_name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Info</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" required>
                            </div>
                            <button type="submit" name="add_doctor" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Doctor
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Doctors Table -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Doctor List</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Serial No.</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Department</th>
                                        <th>Contact Info</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctors as $index => $doctor): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td> <!-- Serial number -->
                                            <td><?php echo $doctor['name']; ?></td>
                                            <td><?php echo $doctor['email']; ?></td>
                                            <td><?php echo $doctor['department_name']; ?></td>
                                            <td><?php echo $doctor['contact_info']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $doctor['status'] == 'Available' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $doctor['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editDoctorModal<?php echo $doctor['user_id']; ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <a href="manage_doctors.php?delete_id=<?php echo $doctor['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure? This will also delete the user account.')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
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
    </div>

    <!-- Edit Doctor Modals -->
    <?php foreach ($doctors as $doctor): ?>
        <div class="modal fade" id="editDoctorModal<?php echo $doctor['user_id']; ?>" tabindex="-1" aria-labelledby="editDoctorModalLabel<?php echo $doctor['user_id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDoctorModalLabel<?php echo $doctor['user_id']; ?>">Edit Doctor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form action="manage_doctors.php" method="POST">
                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['user_id']; ?>">
                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo $doctor['name']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-control" id="department" name="department" required>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['department_id']; ?>"
                                            <?php echo ($department['department_id'] == $doctor['department_id']) ? 'selected' : ''; ?>>
                                            <?php echo $department['department_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="contact_info" class="form-label">Contact Info</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" value="<?php echo $doctor['contact_info']; ?>" required>
                            </div>
                            <button type="submit" name="edit_doctor" class="btn btn-primary">
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
