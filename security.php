<?php
// Set secure session parameters before session starts
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Security Configuration
class Security {
    private $blockedIPs = [];
    private $blockedUserAgents = [
        'sqlmap', 'havij', 'acunetix', 'nikto', 'netsparker', 'nmap'
    ];
    private $allowedMethods = ['GET', 'POST'];
    private $maxRequestSize = 10485760; // 10MB
    private $requestLimit = 100; // Requests per minute
    private static $instance = null;

    // Singleton pattern
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function initializeSecurity() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters before starting session
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Lax');
        }
        $this->enforceSecurityHeaders();
        $this->validateRequest();
    }

    public function enforceSecurityHeaders() {
        // Security Headers
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        // Temporarily disable HSTS for debugging
        // header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }

    public function validateRequest() {
        // Check request method
        if (!in_array($_SERVER['REQUEST_METHOD'], $this->allowedMethods)) {
            $this->blockRequest("Invalid request method");
        }

        // Check request size
        if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > $this->maxRequestSize) {
            $this->blockRequest("Request too large");
        }

        // Check User-Agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        foreach ($this->blockedUserAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                $this->blockRequest("Suspicious user agent detected");
            }
        }

        // Validate input for XSS
        if (!empty($_GET)) {
            $this->validateInput($_GET);
        }
        if (!empty($_POST)) {
            $this->validateInput($_POST);
        }

        // Rate limiting
        $this->checkRateLimit();
    }

    private function validateInput($data) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->validateInput($value);
            } else {
                // Check for XSS attempts
                if ($this->containsXSS($value)) {
                    error_log("XSS attempt detected: " . $value);
                    $this->blockRequest("XSS attempt detected");
                }
                
                // Check for SQL Injection attempts
                if ($this->containsSQLInjection($value)) {
                    error_log("SQL Injection attempt detected: " . $value);
                    $this->blockRequest("SQL Injection attempt detected");
                }
            }
        }
    }

    private function containsXSS($value) {
        $patterns = [
            '/<script\b[^>]*>(.*?)<\/script>/is',
            '/javascript:/i',
            '/onclick/i',
            '/onload/i',
            '/onerror/i',
            '/onmouseover/i',
            '/<iframe/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        return false;
    }

    private function containsSQLInjection($value) {
        $patterns = [
            '/\bUNION\b/i',
            '/\bSELECT\b.*\bFROM\b/i',
            '/\bINSERT\b.*\bINTO\b/i',
            '/\bDROP\b.*\bTABLE\b/i',
            '/\bDELETE\b.*\bFROM\b/i',
            '/\bUPDATE\b.*\bSET\b/i',
            '/--/',
            '/;/',
            '/\/\*.*\*\//'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        return false;
    }

    private function checkRateLimit() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $timestamp = time();
        
        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }
        
        if (!isset($_SESSION['rate_limit'][$ip])) {
            $_SESSION['rate_limit'][$ip] = [];
        }
        
        // Remove old requests
        $_SESSION['rate_limit'][$ip] = array_filter($_SESSION['rate_limit'][$ip], function($time) use ($timestamp) {
            return $time > ($timestamp - 60);
        });
        
        // Add current request
        $_SESSION['rate_limit'][$ip][] = $timestamp;
        
        // Check limit
        if (count($_SESSION['rate_limit'][$ip]) > $this->requestLimit) {
            $this->blockRequest("Rate limit exceeded");
        }
    }

    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            $this->blockRequest("CSRF validation failed");
        }
    }

    public function sanitizeOutput($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitizeOutput($value);
            }
        } else {
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    private function blockRequest($reason) {
        http_response_code(403);
        error_log("Security violation: " . $reason . " - IP: " . $_SERVER['REMOTE_ADDR']);
        die("Access Denied: " . $reason);
    }

    public function secureSession() {
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

// Initialize security
$security = Security::getInstance();
$security->initializeSecurity();

// Function to validate and sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Function to validate file uploads
function validate_file_upload($file) {
    $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $max_size = 5242880; // 5MB
    
    if ($file['size'] > $max_size) {
        throw new Exception('File size exceeds limit');
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        throw new Exception('Invalid file type');
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg',
        'image/png',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    if (!in_array($mime_type, $allowed_mimes)) {
        throw new Exception('Invalid file type');
    }
    
    return true;
}

?> 