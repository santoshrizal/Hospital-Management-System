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

// Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

include 'db.php';

// Check if the user is logged in and is a Patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Patient') {
    header("Location: login.php");
    exit();
}

// Fetch patient ID
$patient_user_id = $_SESSION['user_id'];

// Fetch bills for the patient
$bills = [];
$stmt = $conn->prepare("SELECT * FROM Billing WHERE patient_user_id = ?");
$stmt->bind_param("i", $patient_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bills[] = $row;
}
$stmt->close();

// Handle bill payment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay_bill'])) {
    $bill_id = $_POST['bill_id'];

    // Update bill status to 'Paid'
    $stmt = $conn->prepare("UPDATE Billing SET status = 'Paid' WHERE bill_id = ?");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $stmt->close();

    // Refresh page to show updated status
    header("Location: patient_pay_bills.php");
    exit();
}

// Handle Excel export for medical records
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="medical_records_export.xls"');

    // Fetch medical records for the patient
    $stmt = $conn->prepare("
        SELECT m.diagnosis, m.prescription, m.treatment_plan, m.created_at, d.name AS doctor_name
        FROM MedicalRecords m
        JOIN Doctors d ON m.doctor_user_id = d.user_id
        WHERE m.patient_user_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->bind_param("i", $patient_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Create Excel content
    echo "Date\tDoctor\tDiagnosis\tPrescription\tTreatment Plan\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['created_at'] . "\t" .
             $row['doctor_name'] . "\t" .
             $row['diagnosis'] . "\t" .
             $row['prescription'] . "\t" .
             $row['treatment_plan'] . "\n";
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Bills</title>
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

        .navbar {
            background-color: #007bff;
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

        .sidebar a:hover{
            background-color: #007bff;
            color: white;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            margin-top: 56px;
        }

        .card {
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: bold;
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
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .table tr:hover {
            background-color: #f1f1f1;
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
            <a class="navbar-brand" href="patient_dashboard.php">
                <i class="fas fa-hospital"></i> Hospital Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="patient_dashboard.php">
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

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="patient_dashboard.php"><i class="fas fa-user"></i> Profile</a>
        <a href="patient_book_appointment.php"><i class="fas fa-calendar-check"></i> Book Appointment</a>
        <a href="patient_pay_bills.php" class="active"><i class="fas fa-money-bill-wave"></i> Pay Bills</a>
        <a href="patient_view_records.php"><i class="fas fa-file-medical"></i> Medical Records</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2>Pay Bills</h2>

        <!-- Bills Table -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Your Bills</h5>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bill['bill_id']); ?></td>
                                <td>RS<?php echo number_format($bill['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($bill['status']); ?></td>
                                <td>
                                    <?php if ($bill['status'] == 'Unpaid'): ?>
                                        <form action="patient_pay_bills.php" method="POST" style="display:inline;">
                                            <input type="hidden" name="bill_id" value="<?php echo htmlspecialchars($bill['bill_id']); ?>">
                                            <button type="submit" name="pay_bill" class="btn btn-success btn-sm">
                                                <i class="fas fa-money-bill-wave"></i> Pay Now
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Medical Records Section -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Medical Records</h5>

                <!-- Search/Filters -->
                <form action="patient_pay_bills.php" method="GET" class="row g-3 mb-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search by diagnosis or doctor" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="patient_pay_bills.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Clear
                        </a>
                    </div>
                </form>

                <!-- Export Button -->
                <div class="mb-3">
                    <a href="patient_pay_bills.php?export=excel" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </a>
                </div>

                <!-- Medical Records Table -->
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Diagnosis</th>
                            <th>Prescription</th>
                            <th>Treatment Plan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch medical records with optional search
                        $search = isset($_GET['search']) ? $_GET['search'] : '';
                        $query = "
                            SELECT m.diagnosis, m.prescription, m.treatment_plan, m.created_at, d.name AS doctor_name
                            FROM MedicalRecords m
                            JOIN Doctors d ON m.doctor_user_id = d.user_id
                            WHERE m.patient_user_id = ?
                        ";
                        if (!empty($search)) {
                            $query .= " AND (m.diagnosis LIKE ? OR d.name LIKE ?)";
                            $stmt = $conn->prepare($query);
                            $search_term = "%$search%";
                            $stmt->bind_param("iss", $patient_user_id, $search_term, $search_term);
                        } else {
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("i", $patient_user_id);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();
                        while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['diagnosis']); ?></td>
                                <td><?php echo htmlspecialchars($row['prescription']); ?></td>
                                <td><?php echo htmlspecialchars($row['treatment_plan']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>