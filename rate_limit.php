<?php
function checkRateLimit($ip_address, $conn) {
    // Check if table exists, if not create it
    $check_table = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($check_table->num_rows == 0) {
        $create_table = "CREATE TABLE login_attempts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            ip_address VARCHAR(45) NOT NULL,
            email VARCHAR(100) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success TINYINT(1) DEFAULT 0,
            INDEX idx_ip_time (ip_address, attempt_time)
        )";
        $conn->query($create_table);
    }

    try {
        // Clean up old attempts (older than 15 minutes)
        $cleanup_sql = "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $conn->query($cleanup_sql);
        
        // Count recent attempts
        $sql = "SELECT COUNT(*) as attempt_count FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['attempt_count'];
        
        // If more than 5 attempts in 15 minutes, block the IP
        if ($count >= 5) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        // If any error occurs, allow the login attempt but log the error
        error_log("Rate limit error: " . $e->getMessage());
        return true;
    }
}

function recordLoginAttempt($ip_address, $email, $success, $conn) {
    try {
        $sql = "INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $ip_address, $email, $success);
        $stmt->execute();
    } catch (Exception $e) {
        // Log error but don't stop the login process
        error_log("Error recording login attempt: " . $e->getMessage());
    }
} 