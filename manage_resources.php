<?php
session_start();
include 'db.php';

// Check if the user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Handle Add Resource
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_resource'])) {
    $resource_name = $_POST['resource_name'];
    $quantity = $_POST['quantity'];
    $status = $_POST['status'];

    // Insert into HospitalResources table
    $stmt = $conn->prepare("INSERT INTO HospitalResources (resource_name, quantity, status) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $resource_name, $quantity, $status);
    $stmt->execute();
    $stmt->close();
}

// Handle Update Resource
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_resource'])) {
    $resource_id = $_POST['resource_id'];
    $resource_name = $_POST['resource_name'];
    $quantity = $_POST['quantity'];
    $status = $_POST['status'];

    // Update HospitalResources table
    $stmt = $conn->prepare("UPDATE HospitalResources SET resource_name = ?, quantity = ?, status = ? WHERE resource_id = ?");
    $stmt->bind_param("sisi", $resource_name, $quantity, $status, $resource_id);
    $stmt->execute();
    $stmt->close();
}

// Handle Delete Resource
if (isset($_GET['delete_id'])) {
    $resource_id = $_GET['delete_id'];

    // Delete from HospitalResources table
    $stmt = $conn->prepare("DELETE FROM HospitalResources WHERE resource_id = ?");
    $stmt->bind_param("i", $resource_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch all resources
$resources = [];
$stmt = $conn->prepare("SELECT * FROM HospitalResources");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $resources[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Internal CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            background-color: #007bff; /* Lighter blue for better visibility */
            z-index: 1000; /* Ensure navbar stays on top */
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            color: #fff !important; /* White text for contrast */
        }

        .navbar-nav .nav-link {
            color: #fff !important; /* White text for navbar links */
        }

        .sidebar {
            height: 100vh;
            position: fixed;
            top: 56px; /* Adjusted to account for navbar height */
            left: 0;
            width: 250px;
            box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
            background-color: #fff;
            z-index: 999; /* Ensure sidebar stays below navbar */
        }

        .sidebar-sticky {
            padding-top: 20px;
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

        .main-content {
            margin-left: 250px; /* Width of the sidebar */
            padding: 10px;
            margin-top: 20px; /* Height of the navbar */
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

        .fa-hospital, .fa-user-md, .fa-user-injured, .fa-calendar-check, .fa-bed, .fa-money-bill-wave, .fa-chart-line {
            margin-right: 10px;
            color: #007bff;
        }

        .fa-sign-out-alt {
            margin-right: 10px;
        }

        .fa-home {
            margin-right: 10px;
        }

        .fa-tachometer-alt {
            margin-right: 10px;
        }

        /* Modal Styling */
        .modal-header {
            background-color: #007bff;
            color: #fff;
        }

        .modal-title {
            font-weight: bold;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            border-top: 1px solid #ddd;
            padding: 15px;
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
                        <a class="nav-link" href="C:\xampp\htdocs\Final\logout.php">
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
                <h2 class="mt-3">Manage Resources</h2>

                <!-- Add Resource Form -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Add New Resource</h5>
                        <form action="manage_resources.php" method="POST">
                            <div class="mb-3">
                                <label for="resource_name" class="form-label">Resource Name</label>
                                <input type="text" class="form-control" id="resource_name" name="resource_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" required>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="Available">Available</option>
                                    <option value="Occupied">Occupied</option>
                                </select>
                            </div>
                            <button type="submit" name="add_resource" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Resource
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Resources Table -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Resource List</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Resource Name</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resources as $resource): ?>
                                    <tr>
                                        <td><?php echo $resource['resource_id']; ?></td>
                                        <td><?php echo $resource['resource_name']; ?></td>
                                        <td><?php echo $resource['quantity']; ?></td>
                                        <td><?php echo $resource['status']; ?></td>
                                        <td>
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editResourceModal<?php echo $resource['resource_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <!-- Delete Button -->
                                            <a href="manage_resources.php?delete_id=<?php echo $resource['resource_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>

                                    <!-- Edit Resource Modal -->
                                    <div class="modal fade" id="editResourceModal<?php echo $resource['resource_id']; ?>" tabindex="-1" aria-labelledby="editResourceModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editResourceModalLabel">Edit Resource</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="manage_resources.php" method="POST">
                                                        <input type="hidden" name="resource_id" value="<?php echo $resource['resource_id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="resource_name" class="form-label">Resource Name</label>
                                                            <input type="text" class="form-control" id="resource_name" name="resource_name" value="<?php echo $resource['resource_name']; ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="quantity" class="form-label">Quantity</label>
                                                            <input type="number" class="form-control" id="quantity" name="quantity" value="<?php echo $resource['quantity']; ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="status" class="form-label">Status</label>
                                                            <select class="form-control" id="status" name="status" required>
                                                                <option value="Available" <?php echo ($resource['status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                                                                <option value="Occupied" <?php echo ($resource['status'] == 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                                                            </select>
                                                        </div>
                                                        <button type="submit" name="update_resource" class="btn btn-primary">
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
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>