<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';

header('Content-Type: application/json');

function json_response($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$resource = $_GET['resource'] ?? 'status';
$method = $_SERVER['REQUEST_METHOD'];

if ($resource === 'auth' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => trim($data['username'] ?? '')]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($data['password'] ?? '', $user['password'])) {
        json_response(['success' => false, 'message' => 'Invalid credentials'], 401);
    }
    $token = bin2hex(random_bytes(32));
    $db->prepare("INSERT INTO api_tokens (user_id, token_hash, label, expires_at) VALUES (:user_id, :hash, 'REST API', DATE_ADD(NOW(), INTERVAL 30 DAY))")
        ->execute([':user_id' => $user['id'], ':hash' => hash('sha256', $token)]);
    json_response(['success' => true, 'token' => $token, 'role' => role_alias($user['role'])]);
}

$public_status = $resource === 'status';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$token = substr($auth, 0, 7) === 'Bearer ' ? substr($auth, 7) : ($_GET['token'] ?? '');
if (!$public_status) {
    $token_stmt = $db->prepare("SELECT u.id, u.role FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = :hash AND (t.expires_at IS NULL OR t.expires_at > NOW()) LIMIT 1");
    $token_stmt->execute([':hash' => hash('sha256', $token)]);
    $api_user = $token_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$api_user) {
        json_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    $_SESSION['user_id'] = $api_user['id'];
    $_SESSION['role'] = $api_user['role'];
    if (!can_access_module('api')) {
        json_response(['success' => false, 'message' => 'This API is restricted to Super Admin accounts.'], 403);
    }
}

if ($resource === 'status') {
    json_response(['success' => true, 'service' => 'Anti-Counterfeit REST API']);
}

if ($resource === 'products' && $method === 'GET') {
    $rows = $db->query("SELECT id, name, product_code, batch_number, lifecycle_status, flagged FROM products ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    json_response(['success' => true, 'data' => $rows]);
}

if ($resource === 'verify' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $payload = parse_qr_or_code($data['product_code'] ?? '');
    $code = strtoupper(trim($payload['product_id'] ?? ''));
    $stmt = $db->prepare("SELECT * FROM products WHERE product_code = :code LIMIT 1");
    $stmt->execute([':code' => $code]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        assess_fraud($db, null, $data['city'] ?? '', $data['state'] ?? '', $data['country'] ?? '', client_ip());
        json_response(['success' => false, 'status' => 'Counterfeit Product'], 404);
    }
    $risk = assess_fraud($db, $product, $data['city'] ?? '', $data['state'] ?? '', $data['country'] ?? '', client_ip());
    json_response(['success' => true, 'status' => $risk['level'] === 'High' ? 'Counterfeit Product' : 'Genuine Product', 'risk' => $risk, 'product' => $product]);
}

if ($resource === 'inventory' && $method === 'GET') {
    $rows = $db->query("SELECT i.*, p.product_code, p.name FROM inventory i JOIN products p ON p.id = i.product_id")->fetchAll(PDO::FETCH_ASSOC);
    json_response(['success' => true, 'data' => $rows]);
}

if ($resource === 'complaints' && $method === 'GET') {
    $rows = $db->query("SELECT id, product_code, product_name, status, created_at FROM complaints ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    json_response(['success' => true, 'data' => $rows]);
}

if ($resource === 'notifications' && $method === 'GET') {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = :user_id ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    json_response(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($resource === 'analytics' && $method === 'GET') {
    json_response(['success' => true, 'data' => [
        'products' => (int) $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'verifications' => (int) $db->query("SELECT COUNT(*) FROM product_verifications")->fetchColumn(),
        'fraud_flags' => (int) $db->query("SELECT COUNT(*) FROM fraud_logs")->fetchColumn(),
    ]]);
}

json_response(['success' => false, 'message' => 'Endpoint not found'], 404);
?>
