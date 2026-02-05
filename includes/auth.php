<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;

    public function __construct() {
        $this->conn = get_db_connection();
        $this->initialize_session();
    }

    private function initialize_session() {
        if (session_status() === PHP_SESSION_NONE) {
            // Session configuration
            session_name('MikrotikMonitor');
            
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            
            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }

    public function login($username, $password) {
        // Validate input
        $username = trim($username);
        $password = trim($password);
        
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        // Get user from database
        $user = $this->get_user_by_username($username);
        
        if (!$user) {
            // Delay to prevent timing attacks
            sleep(1);
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Check if account is active
        if ((int)$user['is_active'] !== 1) {
            return ['success' => false, 'message' => 'Account is disabled. Contact administrator.'];
        }

        // Verify password
        if (!$this->verify_password($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // Regenerate session ID
        session_regenerate_id(true);

        // Set session data
        $_SESSION = [
            'logged_in' => true,
            'user_id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'login_time' => time(),
            'session_id' => session_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        // Update last login jika kolom ada
        $this->update_user_login($user['id']);

        return ['success' => true];
    }

    private function get_user_by_username($username) {
        $stmt = $this->conn->prepare(
            "SELECT id, username, password, role, is_active, full_name 
             FROM users WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    private function verify_password($password, $hash, $username = null) {
        // Try password_verify first
        if (password_verify($password, $hash)) {
            return true;
        }
        
        // Legacy: check plaintext (for migration)
        if ($password === $hash) {
            // Auto-upgrade to hashed password
            if ($username !== null) {
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $this->upgrade_password_hash($username, $new_hash);
            }
            return true;
        }
        
        return false;
    }

    private function upgrade_password_hash($username, $new_hash) {
        try {
            $stmt = $this->conn->prepare(
                "UPDATE users SET password = ?, updated_at = NOW() WHERE username = ?"
            );
            $stmt->bind_param('ss', $new_hash, $username);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to upgrade password hash: " . $e->getMessage());
        }
    }

    private function update_user_login($user_id) {
        try {
            // Cek apakah kolom last_login ada
            $check = $this->conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
            
            if ($check && $check->num_rows > 0) {
                // Kolom ada, update dengan last_login
                $stmt = $this->conn->prepare(
                    "UPDATE users SET last_login = NOW(), updated_at = NOW() WHERE id = ?"
                );
            } else {
                // Kolom tidak ada, update hanya updated_at
                $stmt = $this->conn->prepare(
                    "UPDATE users SET updated_at = NOW() WHERE id = ?"
                );
            }
            
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            
        } catch (Exception $e) {
            // Log error tapi jangan stop proses login
            error_log("Error updating user login: " . $e->getMessage());
        }
    }

    public function logout() {
        if ($this->is_logged_in()) {
            // Clear session data
            $_SESSION = [];
            
            // Destroy session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
        }
        
        session_destroy();
        
        // Redirect to login page
        header('Location: login.php');
        exit;
    }

    public function is_logged_in() {
        if (empty($_SESSION['logged_in'])) {
            return false;
        }

        // Basic session validation
        $session_timeout = 28800; // 8 hours
        
        if (time() - $_SESSION['login_time'] > $session_timeout) {
            $this->logout();
            return false;
        }

        return true;
    }

    public function get_current_user() {
        if (!$this->is_logged_in()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role']
        ];
    }

    public function has_role($required_role) {
        $user = $this->get_current_user();
        if (!$user) return false;

        $role_hierarchy = [
            'viewer' => 1,
            'technician' => 2,
            'admin' => 3
        ];

        $required_level = $role_hierarchy[$required_role] ?? 0;
        $user_level = $role_hierarchy[$user['role']] ?? 0;

        return $user_level >= $required_level;
    }

    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
?>