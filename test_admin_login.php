<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Test database connection
echo "<h2>Testing Database Connection</h2>";
if ($conn instanceof mysqli) {
    echo "Database connection successful<br>";
} else {
    echo "Database connection failed<br>";
    exit;
}

// Test admin user
$email = 'admin@taskease.com';
$password = 'admin123';

$sql = "SELECT id, name, email, password, role FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>Testing Admin User</h2>";
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    echo "Admin user found:<br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Name: " . $user['name'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Password verification: " . (password_verify($password, $user['password']) ? "Success" : "Failed") . "<br>";
} else {
    echo "Admin user not found<br>";
}

// Test session
session_start();
echo "<h2>Testing Session</h2>";
echo "Session status: " . session_status() . "<br>";
echo "Session save path: " . session_save_path() . "<br>";
echo "Session name: " . session_name() . "<br>";
echo "Session ID: " . session_id() . "<br>";

// Test session writing
$_SESSION['test'] = 'test_value';
echo "Session write test: " . (isset($_SESSION['test']) ? "Success" : "Failed") . "<br>";

// Test paths
echo "<h2>Testing Paths</h2>";
echo "Script name: " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "PHP self: " . $_SERVER['PHP_SELF'] . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Base path: " . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/' . "<br>";

// Test admin dashboard path
$admin_dashboard = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/admin/dashboard.php';
echo "<h2>Testing Admin Dashboard Path</h2>";
echo "Admin dashboard path: " . $admin_dashboard . "<br>";
echo "File exists: " . (file_exists($_SERVER['DOCUMENT_ROOT'] . $admin_dashboard) ? "Yes" : "No") . "<br>";

// Display PHP Info
echo "<h2>PHP Information</h2>";
phpinfo(INFO_CONFIGURATION | INFO_SESSION); 