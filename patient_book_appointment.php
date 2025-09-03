<?php
session_start();

// Check if the user is logged in and is a Patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Patient') {
    header("Location: login.php");
    exit();
}

include 'db.php';

$patient_user_id = $_SESSION['user_id'];

// Fetch all departments
$departments = [];
$stmt = $conn->prepare("SELECT * FROM department");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}
$stmt->close();

// Initialize variables
$doctors = [];
$selected_department = isset($_POST['department_id']) ? $_POST['department_id'] : '';

// Fetch doctors if a department is selected
if (!empty($selected_department)) {
    $stmt = $conn->prepare("SELECT user_id, name, status FROM Doctors WHERE department_id = ?");
    $stmt->bind_param("i", $selected_department);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
    $stmt->close();
}

// Handle appointment booking
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_appointment'])) {
    $doctor_user_id = $_POST['doctor_user_id'];
    $appointment_date = $_POST['appointment_date'];
    $payment_amount = 100; // Fixed amount for all appointments

    // Server-side date validation
    $appointment_timestamp = strtotime($appointment_date);
    $current_timestamp = time();
    
    // Get the hour from the appointment time
    $appointment_hour = date('H', $appointment_timestamp);
    
    if ($appointment_timestamp < $current_timestamp) {
        $error = "Appointment date cannot be in the past.";
    } elseif ($appointment_hour < 10 || $appointment_hour >= 14) {
        $error = "Appointments can only be booked between 10:00 AM and 2:00 PM.";
    } else {
        // Check if doctor is available
        $stmt = $conn->prepare("SELECT status FROM Doctors WHERE user_id = ?");
        $stmt->bind_param("i", $doctor_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $doctor = $result->fetch_assoc();
        $stmt->close();

        if ($doctor['status'] != 'Available') {
            $error = "Cannot book appointment - doctor is not available.";
        } else {
            // Check doctor's daily appointment limit (5 per day)
            $appointment_date_only = date('Y-m-d', strtotime($appointment_date));
            
            $stmt = $conn->prepare("SELECT COUNT(*) as appointment_count 
                                   FROM appointments 
                                   WHERE doctor_user_id = ? 
                                   AND DATE(appointment_date) = ?");
            $stmt->bind_param("is", $doctor_user_id, $appointment_date_only);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['appointment_count'];
            $stmt->close();
            
            if ($count >= 5) {
                $error = "This doctor has already reached the maximum of 5 appointments for this day. Please choose another day.";
            } else {
                // Instead of inserting now, save data in session and redirect for Stripe payment
                $_SESSION['pending_appointment'] = [
                    'doctor_user_id' => $doctor_user_id,
                    'appointment_date' => $appointment_date,
                    'payment_amount' => $payment_amount
                ];

                header("Location: create_stripe_session.php");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Book Appointment</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
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
        .sidebar a:hover {
            background-color: #007bff;
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            margin-top: 56px;
        }
        .form-control {
            border-radius: 5px;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
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
        option:disabled {
            color: #ccc;
            background-color: #f8f9fa;
        }
        #availability-info {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
   <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="patient_dashboard.php">
                <i class="fas fa-hospital"></i> Hospital Management System
            </a>
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav"
                aria-controls="navbarNav"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="patient_dashboard.php"
                            ><i class="fas fa-home"></i> Home</a
                        >
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"
                            ><i class="fas fa-sign-out-alt"></i> Logout</a
                        >
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="sidebar">
        <a href="patient_dashboard.php"><i class="fas fa-user"></i> Profile</a>
        <a href="patient_book_appointment.php" class="active"
            ><i class="fas fa-calendar-check"></i> Book Appointment</a
        >
        <a href="patient_pay_bills.php"><i class="fas fa-money-bill-wave"></i> Pay Bills</a>
        <a href="patient_view_records.php"><i class="fas fa-file-medical"></i> Medical Records</a>
    </div>

    <div class="main-content">
        <h2>Book Appointment</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="patient_book_appointment.php" method="POST" id="appointmentForm">
            <div class="mb-3">
                <label for="department_id" class="form-label">Select Department</label>
                <select
                    class="form-control"
                    id="department_id"
                    name="department_id"
                    onchange="this.form.submit()"
                    required
                >
                    <option value="">Choose a department</option>
                    <?php foreach ($departments as $department): ?>
                        <option
                            value="<?php echo $department['department_id']; ?>"
                            <?php echo ($selected_department == $department['department_id']) ? 'selected' : ''; ?>
                        >
                            <?php echo $department['department_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($selected_department)): ?>
                <div class="mb-3">
                    <label for="doctor_user_id" class="form-label">Select Doctor</label>
                    <select
                        class="form-control"
                        id="doctor_user_id"
                        name="doctor_user_id"
                        required
                    >
                        <option value="">Choose a doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <?php if ($doctor['status'] == 'Available'): ?>
                                <option value="<?php echo $doctor['user_id']; ?>">
                                    <?php echo $doctor['name']; ?> (Available)
                                </option>
                            <?php else: ?>
                                <option value="<?php echo $doctor['user_id']; ?>" disabled>
                                    <?php echo $doctor['name']; ?> (Not Available)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="appointment_date" class="form-label">Appointment Date</label>
                <input
                    type="datetime-local"
                    class="form-control"
                    id="appointment_date"
                    name="appointment_date"
                    required
                />
                <small class="text-muted">Appointments can only be booked between 10:00 AM and 2:00 PM</small>
                <div id="availability-info" class="mt-2"></div>
            </div>

            <div class="mb-3">
                <label class="form-label">Appointment Fee</label>
                <input
                    type="text"
                    class="form-control"
                    value="100 NPR"
                    readonly
                />
                <input type="hidden" name="payment_amount" value="100">
            </div>

            <button type="submit" name="book_appointment" class="btn btn-success" id="book-btn">
                Book Appointment
            </button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Set minimum date/time to current date/time and validate time range
        document.addEventListener('DOMContentLoaded', function () {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            const dateInput = document.getElementById('appointment_date');
            dateInput.min = minDateTime;

            // Add validation for time range (10 AM to 2 PM)
            dateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const selectedHour = selectedDate.getHours();
                
                if (selectedHour < 10 || selectedHour >= 14) {
                    this.setCustomValidity('Appointments can only be booked between 10:00 AM and 2:00 PM');
                } else {
                    this.setCustomValidity('');
                }
                
                checkAvailability();
            });
            
            // Doctor selection change handler
            document.getElementById('doctor_user_id')?.addEventListener('change', checkAvailability);
            
            // Function to check appointment availability
            function checkAvailability() {
                const doctorId = document.getElementById('doctor_user_id')?.value;
                const dateInput = document.getElementById('appointment_date');
                const dateValue = dateInput.value;
                const availabilityInfo = document.getElementById('availability-info');
                const bookBtn = document.getElementById('book-btn');
                
                if (!doctorId || !dateValue) {
                    return;
                }
                
                const selectedDate = new Date(dateValue);
                const dateOnly = selectedDate.toISOString().split('T')[0];
                
                fetch(`check_availability.php?doctor_id=${doctorId}&date=${dateOnly}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.available >= 0) {
                            if (data.available <= 0) {
                                availabilityInfo.innerHTML = `<div class="alert alert-danger">This doctor has no available slots for ${dateOnly}. Maximum 5 appointments per day.</div>`;
                                bookBtn.disabled = true;
                            } else {
                                availabilityInfo.innerHTML = `<div class="alert alert-info">Available slots: ${5 - data.booked}/5 for ${dateOnly}</div>`;
                                bookBtn.disabled = false;
                            }
                        } else {
                            availabilityInfo.innerHTML = '';
                            bookBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Error checking availability:', error);
                    });
            }
        });
    </script>
</body>
</html> 