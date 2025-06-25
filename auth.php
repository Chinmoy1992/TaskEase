<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/database.php';

class Auth {
    private static $instance = null;
    private $security;
    private $base_path;
    
    private function __construct() {
        $this->security = Security::getInstance();
        // Calculate base path from script path
        $script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $this->base_path = rtrim(dirname(dirname($script_path)), '/') . '/';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            // Check for remember me token
            if (isset($_COOKIE['remember_token'])) {
                $this->validateRememberToken();
            } else {
                $this->redirectToLogin();
            }
        }
        
        // Validate session
        $this->validateSession();
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->security->blockRequest("Unauthorized access attempt");
        }
    }
    
    public function requireUser() {
        $this->requireLogin();
        
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
            $this->security->blockRequest("Unauthorized access attempt");
        }
    }
    
    private function validateSession() {
        // Check if IP changed during session
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
            $this->destroySession();
            $this->security->blockRequest("Session hijacking attempt detected");
        }
        
        // Check if User-Agent changed during session
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            $this->destroySession();
            $this->security->blockRequest("Session hijacking attempt detected");
        }
        
        // Check session age
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
            $this->destroySession();
            $this->redirectToLogin();
        }
        
        // Update session data
        $_SESSION['last_activity'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }
    
    private function validateRememberToken() {
        global $conn;
        
        $token = filter_var($_COOKIE['remember_token'], FILTER_SANITIZE_STRING);
        
        $sql = "SELECT id, name, role FROM users WHERE remember_token = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['created'] = time();
            
            // Generate new remember token for security
            $new_token = bin2hex(random_bytes(32));
            setcookie('remember_token', $new_token, time() + (30 * 24 * 60 * 60), $this->base_path, '', true, true);
            
            $sql = "UPDATE users SET remember_token = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_token, $user['id']);
            $stmt->execute();
        } else {
            $this->destroySession();
            $this->redirectToLogin();
        }
    }
    
    private function destroySession() {
        session_unset();
        session_destroy();
        setcookie('remember_token', '', time() - 3600, $this->base_path, '', true, true);
    }
    
    private function redirectToLogin() {
        header('Location: ' . $this->base_path . 'index.php');
        exit();
    }
    
    public function logout() {
        global $conn;
        
        if (isset($_SESSION['user_id'])) {
            // Clear remember token from database
            $sql = "UPDATE users SET remember_token = NULL WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
        }
        
        $this->destroySession();
        $this->redirectToLogin();
    }
} 