<?php
// Start the session with secure settings
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);

include 'db.php';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Fetch user from the database with case-insensitive comparison
        $stmt = $conn->prepare("SELECT user_id, password, role FROM Users WHERE LOWER(email) = LOWER(?)");
        
        if (!$stmt) {
            error_log("Database error: " . $conn->error);
            $error_message = "System error. Please try again later.";
        } else {
            $stmt->bind_param("s", $email);
            
            if (!$stmt->execute()) {
                error_log("Execution error: " . $stmt->error);
                $error_message = "System error. Please try again later.";
            } else {
                $stmt->store_result();
                
                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($user_id, $hashed_password, $role);
                    $stmt->fetch();
                    
                    // Verify password against hash
                    if (password_verify($password, $hashed_password)) {
                        // Regenerate session ID to prevent fixation
                        session_regenerate_id(true);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['role'] = $role;
                        $_SESSION['email'] = $email;
                        $_SESSION['last_activity'] = time();
                        
                        // Redirect based on role
                        switch ($role) {
                            case 'Admin':
                                header("Location: dashboard.php");
                                exit();
                            case 'Doctor':
                                header("Location: doctor_dashboard.php");
                                exit();
                            case 'Patient':
                                header("Location: patient_dashboard.php");
                                exit();
                            default:
                                $error_message = "Unauthorized access";
                                session_destroy();
                                break;
                        }
                    } else {
                        $error_message = "Invalid email or password";
                    }
                } else {
                    $error_message = "Invalid email or password";
                }
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management System - Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.6s ease-out;
        }
        .card-title {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .card-title i {
            color: #6a11cb;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #6a11cb;
            box-shadow: 0 0 0 0.25rem rgba(106, 17, 203, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.4);
        }
        .alert {
            border-radius: 8px;
        }
        .text-muted a {
            color: #6a11cb;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
        }
        .text-muted a:hover {
            color: #2575fc;
            text-decoration: underline;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h3 class="card-title">
            <i class="fas fa-hospital me-2"></i>Hospital Login
        </h3>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="on">
            <div class="mb-4">
                <label for="email" class="form-label fw-semibold">
                    <i class="fas fa-envelope me-2"></i>Email Address
                </label>
                <input type="email" class="form-control" id="email" name="email" required 
                       placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="mb-4">
                <label for="password" class="form-label fw-semibold">
                    <i class="fas fa-lock me-2"></i>Password
                </label>
                <input type="password" class="form-control" id="password" name="password" required 
                       placeholder="Enter your password">
            </div>
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </div>
            <div class="text-center text-muted">
                <p class="mb-1">Don't have an account? <a href="patient_signup.php">Sign Up</a></p>
                <p class="mb-0"><a href="forgot_password.php">Forgot Password?</a></p>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>