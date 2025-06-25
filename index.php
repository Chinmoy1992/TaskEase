<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';
require_once 'config/rate_limit.php';

// Debug information
error_log("Session data at start: " . print_r($_SESSION, true));

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    error_log("User already logged in. Role: " . $_SESSION['role']);
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}

$error = '';
$ip_address = $_SERVER['REMOTE_ADDR'];

// Check if IP is rate limited
if (!checkRateLimit($ip_address, $conn)) {
    $error = 'Too many login attempts. Please try again in 15 minutes.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    try {
        error_log("Login attempt received");
        
        if (!isset($_POST['email']) || !isset($_POST['password'])) {
            throw new Exception('Email and password are required');
        }

        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new Exception('Invalid email format');
        }

        $password = $_POST['password'];
        if (empty($password)) {
            throw new Exception('Password is required');
        }

        error_log("Attempting login for email: " . $email);

        // Prepare statement
        $sql = "SELECT id, name, email, password, role FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Database prepare error: " . $conn->error);
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Database execute error: " . $stmt->error);
            throw new Exception('Database error: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        error_log("Query result rows: " . $result->num_rows);
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                error_log("Password verified successfully");
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['created'] = time();
                $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['last_activity'] = time();
                
                // Debug session after setting variables
                error_log("Session data after login: " . print_r($_SESSION, true));
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                error_log("Redirecting to dashboard. Role: " . $user['role']);
                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: user/dashboard.php");
                }
                exit();
            } else {
                error_log("Password verification failed");
                recordLoginAttempt($ip_address, $email, 0, $conn);
                throw new Exception('Invalid email or password');
            }
        } else {
            error_log("No user found with email: " . $email);
            recordLoginAttempt($ip_address, $email, 0, $conn);
            throw new Exception('Invalid email or password');
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskEase - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .login-container {
            max-width: 400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .card {
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
        .card-body {
            padding: 2rem;
        }
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }
        .form-control:focus {
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .input-group-append {
            position: relative;
        }
        .btn-success {
            padding: 0.75rem;
            font-weight: 500;
            border-radius: 8px;
        }
        .loading .btn-success {
            position: relative;
            color: transparent;
        }
        .loading .btn-success::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin: -10px 0 0 -10px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: button-loading-spinner 1s linear infinite;
        }
        @keyframes button-loading-spinner {
            from {
                transform: rotate(0turn);
            }
            to {
                transform: rotate(1turn);
            }
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 576px) {
            .login-container {
                margin: 1rem auto;
            }
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="login-container fade-in">
            <h1 class="text-center mb-4" style="color: var(--primary-color)">
                <i class="bi bi-check2-square"></i> TaskEase
            </h1>
            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="" id="loginForm" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            </div>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <span class="password-toggle" onclick="togglePassword()">
                                    <i class="bi bi-eye-slash"></i>
                                </span>
                            </div>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <a href="reset-password.php" class="text-decoration-none text-success">Forgot Password?</a>
                        </div>
                        <button type="submit" class="btn btn-success w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </button>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3">
                <div class="card p-3 bg-white">
                    <small class="text-muted mb-2">Demo Credentials</small>
                    <div class="d-flex justify-content-around">
                        <div>
                            <small class="d-block text-success">Admin Login</small>
                            <small class="text-muted">admin@taskease.com</small><br>
                            <small class="text-muted">admin123</small>
                        </div>
                        <div>
                            <small class="d-block text-success">User Login</small>
                            <small class="text-muted">john@taskease.com</small><br>
                            <small class="text-muted">admin123</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        }

        // Form validation and loading state
        document.getElementById('loginForm').addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                this.classList.add('loading');
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
            }
            this.classList.add('was-validated');
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html> 