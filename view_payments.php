<?php
session_start();
include 'db.php';

// Check if the user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Handle filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_patient = isset($_GET['patient']) ? $_GET['patient'] : '';

$payments = [];
$query = "
    SELECT b.bill_id, b.amount, b.status, b.created_at, b.purpose,
           pt.name AS patient_name
    FROM Billing b
    JOIN Patients pt ON b.patient_user_id = pt.user_id
";

// Add conditions based on filters
$conditions = [];
if ($filter_status) {
    $conditions[] = "b.status = ?";
}
if ($filter_patient) {
    $conditions[] = "pt.name LIKE ?";
}
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $conn->prepare($query);

// Bind parameters
$types = '';
$params = [];

if ($filter_status) {
    $types .= 's';
    $params[] = $filter_status;
}
if ($filter_patient) {
    $types .= 's';
    $params[] = "%$filter_patient%";
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}
$stmt->close();


// Calculate analytics
$total_payments = 0;
$pending_payments = 0;
$completed_payments = 0;

foreach ($payments as $payment) {
    $total_payments += $payment['amount'];
    if ($payment['status'] == 'Unpaid') {
        $pending_payments += $payment['amount'];
    } elseif ($payment['status'] == 'Paid') {
        $completed_payments += $payment['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payments</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Internal CSS -->
    <style>
body {
    background-color: #f8f9fa;
    font-family: 'Arial', sans-serif;
    padding-top: 56px;
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

.navbar-nav .nav-link,
.navbar-nav .fas {
    color: #fff !important;
}

.sidebar {
    position: fixed;
    top: 56px;
    left: 0;
    bottom: 0;
    width: 250px;
    background-color: #fff;
    z-index: 1020;
    overflow-y: auto;
    box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
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

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
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

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}

.fa-hospital,
.fa-user-md,
.fa-user-injured,
.fa-calendar-check,
.fa-bed,
.fa-money-bill-wave,
.fa-chart-line {
    margin-right: 10px;
    /* color: #007bff; */
}

.fa-sign-out-alt,
.fa-home,
.fa-tachometer-alt {
    margin-right: 10px;
}

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

@media (max-width: 768px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main-content {
        margin-left: 0;
        padding: 15px;
    }
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
                <h2 class="mt-3">View Payments</h2>

                <!-- Analytics Cards -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-money-bill-wave"></i> Total Payments
                                </h5>
                                <p class="card-text">RS <?php echo number_format($total_payments, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-hourglass-half"></i> Pending Payments
                                </h5>
                                <p class="card-text">RS <?php echo number_format($pending_payments, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-check-circle"></i> Completed Payments
                                </h5>
                                <p class="card-text">RS <?php echo number_format($completed_payments, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payments Table -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Payment List</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>S.N.</th> <!-- Changed from ID to Serial Number -->
                                    <th>Patient Name</th>
                                    <th>Amount</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $index => $payment): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td> <!-- Serial number -->
                                        <td><?php echo $payment['patient_name']; ?></td>
                                        <td>Rs <?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo $payment['purpose']; ?></td>
                                        <td><?php echo $payment['status']; ?></td>
                                        <td><?php echo $payment['created_at']; ?></td>
                                    </tr>
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
