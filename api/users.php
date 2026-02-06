<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();

if (!$auth->is_logged_in()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$conn = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'technician'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    // Get all users except the current user
    $current_user_id = $_SESSION['user_id'] ?? 0;
    $stmt = $conn->prepare(
        "SELECT id, username, full_name, role, is_active, created_at 
         FROM users 
         WHERE id != ? 
         ORDER BY id DESC"
    );
    $stmt->bind_param('i', $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    try {
        // Validate required fields
        if (empty($data['username']) || empty($data['full_name']) || empty($data['role'])) {
            throw new Exception('Username, full name, and role are required');
        }

        if (empty($data['id'])) {
            // ADD NEW USER
            if (empty($data['password'])) {
                throw new Exception('Password is required for new user');
            }

            // Check if username already exists
            $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $check->bind_param('s', $data['username']);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('Username already exists');
            }

            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare(
                "INSERT INTO users (username, full_name, password, role, is_active) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'ssssi',
                $data['username'],
                $data['full_name'],
                $hashed_password,
                $data['role'],
                $data['is_active']
            );
        } else {
            // EDIT EXISTING USER
            if (!empty($data['password'])) {
                // Update with password
                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "UPDATE users SET 
                     full_name = ?, 
                     password = ?,
                     role = ?, 
                     is_active = ? 
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'sssii',
                    $data['full_name'],
                    $hashed_password,
                    $data['role'],
                    $data['is_active'],
                    $data['id']
                );
            } else {
                // Update without password
                $stmt = $conn->prepare(
                    "UPDATE users SET 
                     full_name = ?, 
                     role = ?, 
                     is_active = ? 
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'ssii',
                    $data['full_name'],
                    $data['role'],
                    $data['is_active'],
                    $data['id']
                );
            }
        }

        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->error);
        }

        echo json_encode([
            'success' => true,
            'message' => empty($data['id']) ? 'User added successfully' : 'User updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

if ($method === 'DELETE') {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }
    try {
        $id = (int)($_GET['id'] ?? 0);
        $current_user_id = $_SESSION['user_id'] ?? 0;

        // Validate
        if ($id <= 0) {
            throw new Exception('Invalid user ID');
        }

        // Prevent self-deletion
        if ($id === $current_user_id) {
            throw new Exception('Cannot delete your own account');
        }

        // Prevent deleting the last admin
        $check_admin = $conn->query(
            "SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin' AND id != $id"
        );
        $admin_count = $check_admin->fetch_assoc()['admin_count'];
        
        if ($admin_count < 1) {
            throw new Exception('Cannot delete the last admin user');
        }

        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->error);
        }

        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>
