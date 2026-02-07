<?php
// Security Configuration File

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (adjust as needed)
// header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';");

// Database Security Functions
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generate_password_reset_token() {
    return bin2hex(random_bytes(32));
}

function rate_limit($key, $limit = 10, $timeframe = 3600) {
    $redis_key = "rate_limit:$key";
    $current = isset($_SESSION[$redis_key]) ? $_SESSION[$redis_key] : 0;
    
    if ($current >= $limit) {
        return false;
    }
    
    $_SESSION[$redis_key] = $current + 1;
    
    // Set expiration
    if (!isset($_SESSION[$redis_key . '_time'])) {
        $_SESSION[$redis_key . '_time'] = time();
    }
    
    // Reset counter after timeframe
    if (time() - $_SESSION[$redis_key . '_time'] > $timeframe) {
        unset($_SESSION[$redis_key], $_SESSION[$redis_key . '_time']);
    }
    
    return true;
}

function log_security_event($event, $details = '') {
    $log_file = __DIR__ . '/../logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $log_entry = "[$timestamp] [$ip] [$event] $details [User-Agent: $user_agent]\n";
    
    // Ensure logs directory exists
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function secure_redirect($url) {
    // Ensure URL is relative to current domain
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        header("Location: $url");
        exit;
    }
}

function validate_session() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    // Check session timeout (7 days)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 604800)) {
        session_destroy();
        return false;
    }

    if (isset($_SESSION['login_time'])) {
        $_SESSION['login_time'] = time();
    }
    
    // Verify session fingerprint
    if (isset($_SESSION['fingerprint'])) {
        $current_fingerprint = md5($_SERVER['HTTP_USER_AGENT'] . (get_client_ip()));
        if ($_SESSION['fingerprint'] !== $current_fingerprint) {
            log_security_event('SESSION_HIJACK', 'Session fingerprint mismatch');
            session_destroy();
            return false;
        }
    }
    
    return true;
}

function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function encrypt_data($data, $key) {
    $method = 'aes-256-gcm';
    $iv = random_bytes(openssl_cipher_iv_length($method));
    $tag = '';
    
    $ciphertext = openssl_encrypt(
        $data,
        $method,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    
    return base64_encode($iv . $tag . $ciphertext);
}

function decrypt_data($data, $key) {
    $method = 'aes-256-gcm';
    $data = base64_decode($data);
    
    $iv_length = openssl_cipher_iv_length($method);
    $tag_length = 16;
    
    $iv = substr($data, 0, $iv_length);
    $tag = substr($data, $iv_length, $tag_length);
    $ciphertext = substr($data, $iv_length + $tag_length);
    
    return openssl_decrypt(
        $ciphertext,
        $method,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
}

function generate_secure_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function password_strength_check($password) {
    $strength = 0;
    
    // Length check
    if (strlen($password) >= 12) $strength += 2;
    elseif (strlen($password) >= 8) $strength += 1;
    
    // Complexity checks
    if (preg_match('/[A-Z]/', $password)) $strength += 1;
    if (preg_match('/[a-z]/', $password)) $strength += 1;
    if (preg_match('/[0-9]/', $password)) $strength += 1;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $strength += 1;
    
    return $strength;
}
?>
