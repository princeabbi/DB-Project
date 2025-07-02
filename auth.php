<?php
// MODIFIED: api/auth.php

require_once 'db.php';
session_start(['cookie_httponly' => true, 'cookie_secure' => isset($_SERVER['HTTPS']), 'cookie_samesite' => 'Lax']);
header('Content-Type: application/json');
$action = $_POST['action'] ?? $_GET['action'] ?? '';
switch ($action) {
    case 'register': handle_register($pdo); break;
    case 'login': handle_login($pdo); break;
    case 'logout': handle_logout(); break;
    case 'check_auth': check_authentication(); break;
    default: echo json_encode(['success' => false, 'message' => 'Invalid action specified.']); break;
}

function handle_register($pdo) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) { echo json_encode(['success' => false, 'message' => 'Username and password are required.']); return; }
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'Username already taken.']); return; }
    
    // By default, new users are registered with the 'staff' role.
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $default_role = 'staff';
    
    // WHAT CHANGED: The INSERT query now includes the role.
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    if ($stmt->execute([$username, $password_hash, $default_role])) { echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
    } else { echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']); }
}

function handle_login($pdo) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) { echo json_encode(['success' => false, 'message' => 'Username and password are required.']); return; }
    
    // WHAT CHANGED: The SELECT query now also fetches the user's role.
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        // WHAT CHANGED: We store the role in the session. This is critical.
        $_SESSION['role'] = $user['role']; 
        
        echo json_encode(['success' => true, 'message' => 'Login successful.']);
    } else { echo json_encode(['success' => false, 'message' => 'Invalid username or password.']); }
}

function handle_logout() { session_unset(); session_destroy(); echo json_encode(['success' => true, 'message' => 'Logged out successfully.']); }

function check_authentication() {
    if (isset($_SESSION['user_id'])) {
        // WHAT CHANGED: We also return the role so the front-end knows the user's permissions.
        echo json_encode(['authenticated' => true, 'username' => $_SESSION['username'], 'role' => $_SESSION['role']]);
    } else { echo json_encode(['authenticated' => false]); }
}