<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'config/security.php';
require_once 'config/auth.php';

// Initialize security and auth
$security = Security::getInstance();
$auth = Auth::getInstance();

// Require admin access
$auth->requireAdmin();

// Check if users table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
echo "Users table exists: " . ($tableCheck->num_rows > 0 ? "Yes" : "No") . "\n";

if ($tableCheck->num_rows > 0) {
    // Check admin user
    $stmt = $conn->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
    $email = 'admin@taskease.com';
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "Admin user found:\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Password hash exists: " . (!empty($user['password']) ? "Yes" : "No") . "\n";
        
        // Test password verification
        $test_password = 'admin123';
        echo "Password 'admin123' matches: " . (password_verify($test_password, $user['password']) ? "Yes" : "No") . "\n";
    } else {
        echo "Admin user not found in database\n";
    }
} else {
    echo "Creating users table and admin user...\n";
    
    // Read and execute SQL file
    $sql = file_get_contents('taskease.sql');
    if ($conn->multi_query($sql)) {
        echo "Database schema imported successfully\n";
    } else {
        echo "Error importing schema: " . $conn->error . "\n";
    }
}
?> 