<?php
// Load configuration from environment variables or a secure configuration file
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'taskease');

// Set additional MySQL settings for security
ini_set('mysqli.allow_persistent', 'Off');
ini_set('mysql.connect_timeout', '5');

try {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if (!$conn->query($sql)) {
        throw new Exception("Error creating database: " . $conn->error);
    }

    // Select the database
    if (!$conn->select_db(DB_NAME)) {
        throw new Exception("Error selecting database: " . $conn->error);
    }

    // Set charset to utf8
    if (!$conn->set_charset("utf8")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }

    // Import database schema if tables don't exist
    $check_table = $conn->query("SHOW TABLES LIKE 'users'");
    if ($check_table->num_rows == 0) {
        // Read and execute SQL file
        $sql = file_get_contents(__DIR__ . '/../taskease.sql');
        if (!$conn->multi_query($sql)) {
            throw new Exception("Error importing database schema: " . $conn->error);
        }
        // Clear results
        while ($conn->more_results() && $conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
    }

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?> 