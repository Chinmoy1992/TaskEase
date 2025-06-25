<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is working\n";
echo "PHP version: " . phpversion() . "\n";

// Test database connection
require_once 'config/database.php';
if (isset($conn) && $conn instanceof mysqli) {
    echo "Database connection successful\n";
    echo "MySQL version: " . $conn->server_info . "\n";
} else {
    echo "Database connection failed\n";
}

// Test session
session_start();
echo "Session working: " . (session_status() === PHP_SESSION_ACTIVE ? "Yes" : "No") . "\n";

// Display some server information
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
phpinfo(); 