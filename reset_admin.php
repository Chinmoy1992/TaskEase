<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

$email = 'admin@taskease.com';
$password = 'admin123';
$name = 'Admin User';
$role = 'admin';

// Generate password hash
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// First try to update existing admin
$sql = "UPDATE users SET password = ? WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $hashed_password, $email);
$stmt->execute();

// If no rows were updated, create new admin
if ($stmt->affected_rows === 0) {
    $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
    $stmt->execute();
    echo "Created new admin user\n";
} else {
    echo "Updated existing admin password\n";
}

// Verify the password
$sql = "SELECT password FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

echo "Password verification test: " . (password_verify($password, $user['password']) ? "Success" : "Failed") . "\n";
echo "\nAdmin credentials:\n";
echo "Email: " . $email . "\n";
echo "Password: " . $password . "\n";
?> 