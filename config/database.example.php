<?php
date_default_timezone_set('Asia/Jakarta');
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'mikrotik_monitor');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');


// Connection with error handling
function get_db_connection() {
    static $conn = null;
    
    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                error_log("Database connection failed: " . $conn->connect_error);
                throw new Exception("Database connection failed");
            }
            
            $conn->set_charset(DB_CHARSET);
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            die("System temporarily unavailable. Please try again later.");
        }
    }
    
    return $conn;
}
?>