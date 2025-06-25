<?php
session_start();
require_once 'config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';

if (!isset($_GET['token']) || empty($_GET['token'])) {
    header('Location: index.php');
    exit();
}

try {
    $token = $_GET['token'];

    // Verify token without checking expiry
    $sql = "SELECT id, email FROM users WHERE reset_token = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("s", $token);
    if (!$stmt->execute()) {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $error = "Invalid reset link. <a href='reset-password.php' class='alert-link'>Click here to request a new one</a>.";
        $invalid_token = true;
    }

    $user = $result->fetch_assoc();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['password']) || empty($_POST['password'])) {
            throw new Exception("Password is required.");
        }

        if (!isset($_POST['confirm_password']) || empty($_POST['confirm_password'])) {
            throw new Exception("Password confirmation is required.");
        }

        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match.");
        }

        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long.");
        }

        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($hashed_password === false) {
            throw new Exception("Error creating password hash.");
        }
        
        // Update password and clear reset token
        $sql = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ? AND id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt->bind_param("ssi", $hashed_password, $token, $user['id']);
        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->error);
        }

        if ($stmt->affected_rows === 1) {
            $success = "Password updated successfully! You can now <a href='index.php'>login</a> with your new password.";
        } else {
            throw new Exception("Error updating password. Please try again.");
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskEase - Update Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .password-strength-meter {
            height: 5px;
            background-color: #eee;
            margin-top: 5px;
            border-radius: 3px;
        }
        .password-strength-meter div {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease-in-out;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
        .input-group-append {
            position: relative;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container fade-in">
            <h2 class="text-center mb-4" style="color: var(--primary-color)">Update Password</h2>
            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert <?php echo isset($invalid_token) ? 'alert-warning' : 'alert-danger'; ?>"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php elseif (!isset($invalid_token)): ?>
                        <form method="POST" action="" novalidate>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                    <div class="input-group-append">
                                        <span class="password-toggle" onclick="togglePassword('password')">
                                            <i class="bi bi-eye-slash"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="password-strength-meter mt-2">
                                    <div id="strength-meter"></div>
                                </div>
                                <div id="password-strength-text" class="form-text"></div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                    <div class="input-group-append">
                                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                            <i class="bi bi-eye-slash"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success w-100" id="submit-btn" disabled>Update Password</button>
                            <div class="mt-3 text-center">
                                <a href="index.php" class="text-decoration-none">Back to Login</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('.bi');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            }
        }

        function checkPasswordStrength(password) {
            let strength = 0;
            const meter = document.getElementById('strength-meter');
            const strengthText = document.getElementById('password-strength-text');
            const submitBtn = document.getElementById('submit-btn');

            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength += 1;

            switch (strength) {
                case 0:
                    meter.style.width = '0%';
                    meter.style.backgroundColor = '#dc3545';
                    strengthText.innerHTML = 'Password must be at least 6 characters long';
                    break;
                case 1:
                    meter.style.width = '20%';
                    meter.style.backgroundColor = '#dc3545';
                    strengthText.innerHTML = 'Very Weak';
                    break;
                case 2:
                    meter.style.width = '40%';
                    meter.style.backgroundColor = '#ffc107';
                    strengthText.innerHTML = 'Weak';
                    break;
                case 3:
                    meter.style.width = '60%';
                    meter.style.backgroundColor = '#0dcaf0';
                    strengthText.innerHTML = 'Medium';
                    break;
                case 4:
                    meter.style.width = '80%';
                    meter.style.backgroundColor = '#198754';
                    strengthText.innerHTML = 'Strong';
                    break;
                case 5:
                    meter.style.width = '100%';
                    meter.style.backgroundColor = '#198754';
                    strengthText.innerHTML = 'Very Strong';
                    break;
            }

            submitBtn.disabled = password.length < 6;
            validatePasswords();
        }

        function validatePasswords() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submit-btn');
            
            if (password.length >= 6 && password === confirmPassword) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });

        document.getElementById('confirm_password').addEventListener('input', validatePasswords);
    </script>
</body>
</html> 