<?php
// ==================== DATABASE CONNECTION ====================
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hospitalmanagementsystem";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ==================== BACKEND LOGIC ====================
// Start session
session_start();
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_nurse']) && isset($_POST['form_token']) && $_POST['form_token'] === $_SESSION['form_token']) {
        $name = $_POST['name'] ?? '';
        $address = $_POST['address'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $status = $_POST['status'] ?? 'Available';
        $department_id = $_POST['department_id'] ?? null;

        if (!$department_id) {
            $error = "Please select a department for the nurse.";
        } else {
            $stmt = $conn->prepare("INSERT INTO nurses (name, address, phone, status, department_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $name, $address, $phone, $status, $department_id);

            if ($stmt->execute()) {
                $success = "Nurse added successfully!";
                autoAssignNurse($conn, $stmt->insert_id, $department_id);
            } else {
                $error = "Error adding nurse: " . $conn->error;
            }
            $stmt->close();
            $_SESSION['form_token'] = bin2hex(random_bytes(32));
        }
    } elseif (isset($_POST['assign_nurse'])) {
        $nurse_id = $_POST['nurse_id'] ?? '';
        $doctor_id = $_POST['doctor_id'] ?? '';
        $admin_id = 1;

        $stmt = $conn->prepare("INSERT INTO doctor_nurse_assignments (doctor_id, nurse_id, assigned_by) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $doctor_id, $nurse_id, $admin_id);

        if ($stmt->execute()) {
            $success = "Nurse assigned to doctor successfully!";
        } else {
            $error = "Error assigning nurse: " . $conn->error;
        }
        $stmt->close();
    } elseif (isset($_POST['change_nurse_status'])) {
        $nurse_id = $_POST['nurse_id'] ?? '';
        $new_status = $_POST['status'] ?? 'Available';
        
        $stmt = $conn->prepare("UPDATE nurses SET status = ? WHERE nurse_id = ?");
        $stmt->bind_param("si", $new_status, $nurse_id);
        
        if ($stmt->execute()) {
            $success = "Nurse status updated successfully!";
        } else {
            $error = "Error updating nurse status: " . $conn->error;
        }
        $stmt->close();
    }
}

function autoAssignNurse($conn, $nurse_id, $department_id) {
    $query = "SELECT user_id FROM doctors 
              WHERE department_id = ? AND status = 'Available'
              AND NOT EXISTS (
                  SELECT 1 FROM doctor_nurse_assignments 
                  WHERE doctor_id = doctors.user_id AND is_active = 1
              ) LIMIT 1";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $doctor = $result->fetch_assoc();
        $admin_id = 1;

        $assign_stmt = $conn->prepare("INSERT INTO doctor_nurse_assignments (doctor_id, nurse_id, assigned_by) VALUES (?, ?, ?)");
        $assign_stmt->bind_param("iii", $doctor['user_id'], $nurse_id, $admin_id);
        $assign_stmt->execute();
        $assign_stmt->close();
    }
    $stmt->close();
}

// Get all data
$departments = $conn->query("SELECT * FROM department")->fetch_all(MYSQLI_ASSOC);
$nurses = $conn->query("
    SELECT n.*, d.department_name, 
           GROUP_CONCAT(DISTINCT doc.name SEPARATOR ', ') as assigned_doctors
    FROM nurses n 
    LEFT JOIN department d ON n.department_id = d.department_id
    LEFT JOIN doctor_nurse_assignments dna ON n.nurse_id = dna.nurse_id AND dna.is_active = 1
    LEFT JOIN doctors doc ON dna.doctor_id = doc.user_id
    GROUP BY n.nurse_id
")->fetch_all(MYSQLI_ASSOC);
$doctors = $conn->query("SELECT d.user_id, d.name, d.department_id, dep.department_name FROM doctors d JOIN department dep ON d.department_id = dep.department_id WHERE d.status = 'Available'")->fetch_all(MYSQLI_ASSOC);
$assignments = $conn->query("SELECT a.*, d.name as doctor_name, n.name as nurse_name FROM doctor_nurse_assignments a JOIN doctors d ON a.doctor_id = d.user_id JOIN nurses n ON a.nurse_id = n.nurse_id WHERE a.is_active = 1")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ==================== CUSTOM STYLES ==================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Navbar styling */
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Sidebar styling */
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 56px; /* Below navbar */
            left: 0;
            width: 250px;
            overflow-y: auto;
            background: #343a40;
            color: white;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 56px);
            padding-top: 0.5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            margin-bottom: 0.2rem;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main content area */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            margin-top: 56px;
            transition: all 0.3s;
        }
        
        /* Card styling */
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header {
            background-color: #3498db;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        /* Status indicators */
        .status-available { color: #28a745; font-weight: bold; }
        .status-not-available { color: #dc3545; font-weight: bold; }
        .status-on-leave { color: #ffc107; font-weight: bold; }
        
        /* Form styling */
        .form-group { margin-bottom: 1rem; }
        
        /* Table styling */
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table th {
            background-color: #3498db;
            color: white;
        }
        
        /* Alert messages */
        .alert { border-radius: 10px; }
        
        /* Tabs styling */
        .nav-tabs .nav-link {
            border: none;
            color: #495057;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            color: #3498db;
            border-bottom: 3px solid #3498db;
            background: transparent;
        }
        
        /* Status dropdown styling */
        .status-dropdown {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: bold;
        }
        
        /* Responsive adjustments */
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-hospital me-2"></i>Hospital Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Logout
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
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="sidebar-sticky pt-3">
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
                                <i class="fas fa-calendar-check"></i> Appointments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_payments.php">
                                <i class="fas fa-money-bill-wave"></i> Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_reports.php">
                                <i class="fas fa-chart-line"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="nurse_management.php">
                                <i class="fas fa-user-nurse"></i> Nurse Management
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Nurse Management</h1>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs mb-4" id="nurseTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="add-tab" data-bs-toggle="tab" data-bs-target="#addNurse" type="button" role="tab">Add Nurse</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="assign-tab" data-bs-toggle="tab" data-bs-target="#assignNurse" type="button" role="tab">Assign to Doctor</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="view-tab" data-bs-toggle="tab" data-bs-target="#viewNurses" type="button" role="tab">View Nurses</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#viewAssignments" type="button" role="tab">View Assignments</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="nurseTabsContent">
                    <!-- Add Nurse Tab -->
                    <div class="tab-pane fade show active" id="addNurse" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Add New Nurse</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="add_nurse" value="1">
                                    <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="name">Full Name*</label>
                                                <input type="text" class="form-control" id="name" name="name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone">Phone Number*</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="address">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="department_id">Department*</label>
                                                <select class="form-select" id="department_id" name="department_id" required>
                                                    <option value="">Select Department</option>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <option value="<?= $dept['department_id'] ?>">
                                                            <?= htmlspecialchars($dept['department_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="status">Status*</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="Available">Available</option>
                                                    <option value="Not Available">Not Available</option>
                                                    <option value="On Leave">On Leave</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Save Nurse</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assign Nurse Tab -->
                    <div class="tab-pane fade" id="assignNurse" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Assign Nurse to Doctor</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="assign_nurse" value="1">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="nurse_id">Select Nurse*</label>
                                                <select class="form-select" id="nurse_id" name="nurse_id" required>
                                                    <option value="">Select Nurse</option>
                                                    <?php foreach ($nurses as $nurse): ?>
                                                        <?php if ($nurse['status'] === 'Available'): ?>
                                                            <option value="<?= $nurse['nurse_id'] ?>" data-department="<?= $nurse['department_id'] ?>">
                                                                <?= htmlspecialchars($nurse['name']) ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="doctor_id">Select Doctor*</label>
                                                <select class="form-select" id="doctor_id" name="doctor_id" required disabled>
                                                    <option value="">First select nurse</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Assign Nurse</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- View Nurses Tab -->
                    <div class="tab-pane fade" id="viewNurses" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Nurse List</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>S.N</th>
                                                <th>Name</th>
                                                <th>Phone</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                                <th>Assigned Doctor</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($nurses as $index => $nurse): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($nurse['name']) ?></td>
                                                    <td><?= htmlspecialchars($nurse['phone']) ?></td>
                                                    <td><?= htmlspecialchars($nurse['department_name'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <form method="POST" class="status-form">
                                                            <input type="hidden" name="change_nurse_status" value="1">
                                                            <input type="hidden" name="nurse_id" value="<?= $nurse['nurse_id'] ?>">
                                                            <select name="status" class="form-select form-select-sm status-dropdown status-<?= strtolower(str_replace(' ', '-', $nurse['status'])) ?>" onchange="this.form.submit()">
                                                                <option value="Available" <?= $nurse['status'] === 'Available' ? 'selected' : '' ?>>Available</option>
                                                                <option value="Not Available" <?= $nurse['status'] === 'Not Available' ? 'selected' : '' ?>>Not Available</option>
                                                                <option value="On Leave" <?= $nurse['status'] === 'On Leave' ? 'selected' : '' ?>>On Leave</option>
                                                            </select>
                                                        </form>
                                                    </td>
                                                    <td>
                                                        <?= $nurse['assigned_doctors'] ? 'Dr. ' . htmlspecialchars($nurse['assigned_doctors']) : 'Not assigned' ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editNurseModal" data-nurse-id="<?= $nurse['nurse_id'] ?>" data-name="<?= htmlspecialchars($nurse['name']) ?>" data-phone="<?= htmlspecialchars($nurse['phone']) ?>" data-address="<?= htmlspecialchars($nurse['address']) ?>" data-department="<?= $nurse['department_id'] ?>" data-status="<?= $nurse['status'] ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- View Assignments Tab -->
                    <div class="tab-pane fade" id="viewAssignments" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Current Assignments</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>S.N</th>
                                                <th>Doctor</th>
                                                <th>Nurse</th>
                                                <th>Department</th>
                                                <th>Assigned At</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assignments as $index => $assign): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td>Dr. <?= htmlspecialchars($assign['doctor_name']) ?></td>
                                                    <td><?= htmlspecialchars($assign['nurse_name']) ?></td>
                                                    <td><?= $nurses[array_search($assign['nurse_id'], array_column($nurses, 'nurse_id'))]['department_name'] ?? 'N/A' ?></td>
                                                    <td><?= date('M j, Y g:i A', strtotime($assign['assigned_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Nurse Modal -->
    <div class="modal fade" id="editNurseModal" tabindex="-1" aria-labelledby="editNurseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editNurseModalLabel">Edit Nurse Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editNurseForm" method="POST">
                        <input type="hidden" name="edit_nurse" value="1">
                        <input type="hidden" name="nurse_id" id="editNurseId">
                        <div class="mb-3">
                            <label for="editName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPhone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="editPhone" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAddress" class="form-label">Address</label>
                            <textarea class="form-control" id="editAddress" name="address" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editDepartment" class="form-label">Department</label>
                            <select class="form-select" id="editDepartment" name="department_id" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editStatus" class="form-label">Status</label>
                            <select class="form-select" id="editStatus" name="status" required>
                                <option value="Available">Available</option>
                                <option value="Not Available">Not Available</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="editNurseForm" class="btn btn-primary">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            if (!/^\d{10,15}$/.test(phone)) {
                alert('Please enter a valid phone number (10-15 digits)');
                e.preventDefault();
            }
        });
        
        // Department and Doctor filtering logic
        document.addEventListener('DOMContentLoaded', function() {
            const nurseSelect = document.getElementById('nurse_id');
            const doctorSelect = document.getElementById('doctor_id');
            const allDoctors = <?= json_encode($doctors) ?>;

            // Filter doctors when nurse is selected
            nurseSelect.addEventListener('change', function() {
                const nurseId = this.value;
                const selectedNurse = Array.from(nurseSelect.options).find(option => option.value === nurseId);
                const departmentId = selectedNurse ? selectedNurse.dataset.department : null;

                // Clear previous doctor options
                doctorSelect.innerHTML = '<option value="">Select Doctor</option>';
                doctorSelect.disabled = true;

                if (departmentId) {
                    // Filter doctors by department
                    const filteredDoctors = allDoctors.filter(doctor => doctor.department_id == departmentId);

                    // Populate doctors dropdown
                    filteredDoctors.forEach(doctor => {
                        const option = document.createElement('option');
                        option.value = doctor.user_id;
                        option.textContent = `Dr. ${doctor.name} (${doctor.department_name})`;
                        doctorSelect.appendChild(option);
                    });

                    doctorSelect.disabled = false; // Enable the doctor select
                }
            });

            // Edit Nurse Modal
            const editNurseModal = document.getElementById('editNurseModal');
            if (editNurseModal) {
                editNurseModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const nurseId = button.getAttribute('data-nurse-id');
                    const name = button.getAttribute('data-name');
                    const phone = button.getAttribute('data-phone');
                    const address = button.getAttribute('data-address');
                    const department = button.getAttribute('data-department');
                    const status = button.getAttribute('data-status');

                    document.getElementById('editNurseId').value = nurseId;
                    document.getElementById('editName').value = name;
                    document.getElementById('editPhone').value = phone;
                    document.getElementById('editAddress').value = address;
                    document.getElementById('editDepartment').value = department;
                    document.getElementById('editStatus').value = status;
                });
            }
        });
    </script>
</body>
</html>