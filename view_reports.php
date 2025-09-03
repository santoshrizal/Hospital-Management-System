<?php
session_start();
include 'db.php';

// Check if the user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit();
}

// Handle CSV export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="hospital_reports_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    if ($_GET['export'] == 'doctors') {
        $stmt = $conn->prepare("
            SELECT d.name AS doctor_name, d.status, 
                   dep.department_name, dep.department_id,
                   COUNT(a.appointment_id) AS appointment_count
            FROM Doctors d
            JOIN department dep ON d.department_id = dep.department_id
            LEFT JOIN Appointments a ON d.user_id = a.doctor_user_id
            GROUP BY d.user_id
            ORDER BY dep.department_name, d.name
        ");
        $stmt->execute();
        $result = $stmt->get_result();

        fputcsv($output, ['S.No', 'Department', 'Doctor Name', 'Status', 'Appointment Count']);
        $counter = 1;
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $counter++,
                $row['department_name'],
                $row['doctor_name'],
                $row['status'],
                $row['appointment_count']
            ]);
        }
    } elseif ($_GET['export'] == 'appointments') {
        $stmt = $conn->prepare("
            SELECT a.appointment_id, a.appointment_date, a.status, 
                   a.payment_amount, d.name AS doctor_name, 
                   dep.department_name, p.name AS patient_name, 
                   p.contact_info AS patient_contact
            FROM Appointments a
            JOIN Doctors d ON a.doctor_user_id = d.user_id
            JOIN department dep ON d.department_id = dep.department_id
            JOIN Patients p ON a.patient_user_id = p.user_id
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();

        fputcsv($output, ['S.No', 'Appointment ID', 'Date', 'Status', 'Doctor', 'Department', 'Patient Name', 'Contact', 'Payment Amount']);
        $counter = 1;
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $counter++,
                $row['appointment_id'],
                $row['appointment_date'],
                $row['status'],
                $row['doctor_name'],
                $row['department_name'],
                $row['patient_name'],
                $row['patient_contact'],
                $row['payment_amount']
            ]);
        }
    } elseif ($_GET['export'] == 'payments') {
        $stmt = $conn->prepare("
            SELECT b.bill_id, b.amount, b.status, b.created_at, b.purpose,
                   pt.name AS patient_name
            FROM Billing b
            JOIN Patients pt ON b.patient_user_id = pt.user_id
        ");
        $stmt->execute();
        $result = $stmt->get_result();

        fputcsv($output, ['S.No', 'Patient Name', 'Amount', 'Purpose', 'Status', 'Date']);
        $counter = 1;
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $counter++,
                $row['patient_name'],
                $row['amount'],
                $row['purpose'],
                $row['status'],
                $row['created_at']
            ]);
        }
    }
    fclose($output);
    exit();
}

// Fetch all doctors with departments
$doctors = [];
$stmt = $conn->prepare("
    SELECT d.user_id, d.name AS doctor_name, d.status, 
           dep.department_name, dep.department_id,
           COUNT(a.appointment_id) AS appointment_count
    FROM Doctors d
    JOIN department dep ON d.department_id = dep.department_id
    LEFT JOIN Appointments a ON d.user_id = a.doctor_user_id
    GROUP BY d.user_id
    ORDER BY dep.department_name, d.name
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $doctors[] = $row;
}
$stmt->close();

// Fetch all appointments
$appointments = [];
$stmt = $conn->prepare("
    SELECT a.appointment_id, a.appointment_date, a.status, a.payment_amount,
           d.name AS doctor_name, dep.department_name,
           p.name AS patient_name, p.contact_info AS patient_contact
    FROM Appointments a
    JOIN Doctors d ON a.doctor_user_id = d.user_id
    JOIN department dep ON d.department_id = dep.department_id
    JOIN Patients p ON a.patient_user_id = p.user_id
    ORDER BY a.appointment_date DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Arial', sans-serif;
        padding-top: 56px;
    }

    .navbar {
        background-color: #007bff;
        position: fixed;
        top: 0;
        width: 100%;
        z-index: 1030;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand, .navbar-nav .nav-link {
        color: #fff !important;
    }

    .sidebar {
        position: fixed;
        top: 56px;
        left: 0;
        bottom: 0;
        width: 250px;
        background-color: #fff;
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.1);
        z-index: 1020;
        overflow-y: auto;
        height: calc(100vh - 56px);
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
        margin-left: 250px;
        padding: 20px;
        margin-top: 0;
    }

    .card {
        margin-bottom: 20px;
        border: none;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .badge-available {
        background-color: #28a745;
    }

    .badge-unavailable {
        background-color: #dc3545;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .export-buttons {
        margin-bottom: 20px;
    }

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
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-hospital"></i> Hospital Management System
            </a>
            <div class="collapse navbar-collapse">
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

    <!-- Sidebar and Main Content Container -->
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
                <h2 class="mb-4"><i class="fas fa-chart-line"></i> System Reports</h2>
                
                <!-- Export Buttons -->
                <div class="export-buttons">
                    <a href="view_reports.php?export=doctors" class="btn btn-success me-2">
                        <i class="fas fa-file-excel"></i> Export Doctors
                    </a>
                    <a href="view_reports.php?export=appointments" class="btn btn-primary me-2">
                        <i class="fas fa-file-excel"></i> Export Appointments
                    </a>
                    <a href="view_reports.php?export=payments" class="btn btn-info">
                        <i class="fas fa-file-excel"></i> Export Payments
                    </a>
                </div>

                <!-- Doctors Report -->
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title"><i class="fas fa-user-md"></i> Doctors by Department</h4>
                        <?php
                        $current_dept = null;
                        foreach ($doctors as $doctor): 
                            if ($current_dept != $doctor['department_name']): 
                                $current_dept = $doctor['department_name'];
                        ?>
                        <h5 class="mt-3 bg-light p-2 rounded"><?php echo $doctor['department_name']; ?></h5>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><?php echo $doctor['doctor_name']; ?></strong>
                                <span class="badge <?php echo $doctor['status'] == 'Available' ? 'badge-available' : 'badge-unavailable'; ?> ms-2">
                                    <?php echo $doctor['status']; ?>
                                </span>
                            </div>
                            <span class="badge bg-primary">
                                Appointments: <?php echo $doctor['appointment_count']; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Appointments Report -->
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title"><i class="fas fa-calendar-check"></i> Patient Appointments</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>S.N.</th> <!-- Changed from ID to Serial Number -->
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Doctor</th>
                                        <th>Department</th>
                                        <th>Patient</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $index => $appointment): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td> <!-- Serial number -->
                                        <td><?php echo $appointment['appointment_date']; ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $appointment['status'] == 'Completed' ? 'badge-available' : 
                                                      ($appointment['status'] == 'Cancelled' ? 'badge-unavailable' : 'bg-primary'); ?>">
                                                <?php echo $appointment['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $appointment['doctor_name']; ?></td>
                                        <td><?php echo $appointment['department_name']; ?></td>
                                        <td><?php echo $appointment['patient_name']; ?></td>
                                        <td>Rs. <?php echo number_format($appointment['payment_amount'], 2); ?></td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
