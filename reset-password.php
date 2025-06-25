<?php
session_start();
require_once 'config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['email']) || empty($_POST['email'])) {
            throw new Exception('Email is required');
        }

        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new Exception('Invalid email format');
        }
        
        // Check if email exists
        $sql = "SELECT id, email FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            // Set expiry to a far future date (effectively non-expiring)
            $expiry = '2099-12-31 23:59:59';
            
            // Store token in database
            $sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }

            $stmt->bind_param("sss", $token, $expiry, $email);
            if (!$stmt->execute()) {
                throw new Exception('Database error: ' . $stmt->error);
            }

            // In a real application, you would send an email with the reset link
            // For demo purposes, we'll just show the reset link
            $success = "Password reset link (this link will not expire):<br>";
            $success .= "<a href='update-password.php?token=" . htmlspecialchars($token) . "' class='btn btn-success mt-2'>Click here to reset password</a>";
        } else {
            // Don't reveal if email exists or not for security
            $success = "If the email exists in our system, you will receive reset instructions.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskEase - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="login-container fade-in">
            <h2 class="text-center mb-4" style="color: var(--primary-color)">Reset Password</h2>
            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <div class="form-text">Enter your email address and we'll send you instructions to reset your password.</div>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Send Reset Link</button>
                        <div class="mt-3 text-center">
                            <a href="index.php" class="text-decoration-none">Back to Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 