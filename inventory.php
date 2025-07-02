<?php
// MODIFIED: api/inventory.php

require_once 'db.php';
session_start(['cookie_httponly' => true, 'cookie_secure' => isset($_SERVER['HTTPS']),'cookie_samesite' => 'Lax']);
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Authentication required.']); exit(); }
header('Content-Type: application/json');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        // WHAT CHANGED: Gatekeeper check for admin-only actions.
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403); // 403 Forbidden status
            echo json_encode(['success' => false, 'message' => 'Permission Denied: Only admins can create products.']);
            exit();
        }
        handle_create($pdo);
        break;
    case 'read':
        handle_read($pdo);
        break;
    case 'update':
        handle_update($pdo);
        break;
    case 'delete':
        // WHAT CHANGED: Gatekeeper check for admin-only actions.
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403); // 403 Forbidden status
            echo json_encode(['success' => false, 'message' => 'Permission Denied: Only admins can delete products.']);
            exit();
        }
        handle_delete($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid inventory action.']);
        break;
}

// The handler functions below do not need to change. The gatekeeper above protects them.

function handle_create($pdo) {
    $name = trim($_POST['name'] ?? ''); $sku = trim($_POST['sku'] ?? '');
    $qty = filter_input(INPUT_POST, 'qty', FILTER_VALIDATE_INT); $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    if (empty($name) || empty($sku) || $qty === false || $price === false) { echo json_encode(['success' => false, 'message' => 'Invalid input. Please check all fields.']); return; }
    $stmt = $pdo->prepare("INSERT INTO products (name, sku, qty, price) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$name, $sku, $qty, $price])) { echo json_encode(['success' => true, 'message' => 'Product added successfully.']); } else { echo json_encode(['success' => false, 'message' => 'Failed to add product. SKU might already exist.']); }
}
function handle_read($pdo) { $stmt = $pdo->query("SELECT id, name, sku, qty, price FROM products ORDER BY name ASC"); $products = $stmt->fetchAll(); echo json_encode($products); }
function handle_update($pdo) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT); $name = trim($_POST['name'] ?? ''); $sku = trim($_POST['sku'] ?? '');
    $qty = filter_input(INPUT_POST, 'qty', FILTER_VALIDATE_INT); $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    if ($id === false || empty($name) || empty($sku) || $qty === false || $price === false) { echo json_encode(['success' => false, 'message' => 'Invalid input for update.']); return; }
    $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, qty = ?, price = ? WHERE id = ?");
    if ($stmt->execute([$name, $sku, $qty, $price, $id])) { echo json_encode(['success' => true, 'message' => 'Product updated successfully.']); } else { echo json_encode(['success' => false, 'message' => 'Failed to update product. SKU might already exist.']); }
}
function handle_delete($pdo) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id === false) { echo json_encode(['success' => false, 'message' => 'Invalid product ID.']); return; }
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$id])) { echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']); } else { echo json_encode(['success' => false, 'message' => 'Failed to delete product.']); }
}