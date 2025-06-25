<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<pre>";
echo "Session Data:\n";
print_r($_SESSION);
echo "\n\nServer Variables:\n";
print_r($_SERVER);
echo "</pre>";

// Test database connection
require_once 'config/database.php';

try {
    // Test query
    $sql = "SELECT id, name, email, role FROM users WHERE role = 'admin' LIMIT 1";
    $result = $conn->query($sql);
    
    echo "\nDatabase Test:\n";
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "Found admin user: " . htmlspecialchars($admin['email']) . "\n";
    } else {
        echo "No admin user found\n";
    }
} catch (Exception $e) {
    echo "Database Error: " . htmlspecialchars($e->getMessage()) . "\n";
}

// Test session path
$script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$base_path = rtrim(dirname($script_path), '/') . '/';
echo "\nPath Information:\n";
echo "Script Path: " . htmlspecialchars($script_path) . "\n";
echo "Base Path: " . htmlspecialchars($base_path) . "\n";
echo "Current Directory: " . htmlspecialchars(__DIR__) . "\n";

// Test session configuration
echo "\nSession Configuration:\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Cookie Parameters:\n";
print_r(session_get_cookie_params());
?> 