<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('recalls');
$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can_access_module('recall_publish')) {
    verify_csrf();
    $code = strtoupper(trim($_POST['product_code']));
    $stmt = $db->prepare("SELECT id, batch_number FROM products WHERE product_code = :code LIMIT 1");
    $stmt->execute([':code' => $code]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    $db->prepare("INSERT INTO product_recalls (product_id, batch_number, manufacturer_user_id, reason, description) VALUES (:product, :batch, :user, :reason, :description)")
        ->execute([':product' => $product['id'] ?? null, ':batch' => trim($_POST['batch_number'] ?: ($product['batch_number'] ?? '')), ':user' => $_SESSION['user_id'], ':reason' => $_POST['reason'], ':description' => trim($_POST['description'])]);
    create_notification($db, 'recall', 'Product recall alert', $_POST['reason'] . ': ' . ($code ?: $_POST['batch_number']), null);
    audit_log($db, 'recall', (int) $db->lastInsertId(), $product['id'] ?? null, null, 'active', '', []);
    $success = 'Recall alert published to affected stakeholders.';
}
$recalls = $db->query("SELECT pr.*, p.product_code, p.name FROM product_recalls pr LEFT JOIN products p ON p.id = pr.product_id ORDER BY pr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Product Recalls - Anti-Counterfeit System';
$active_page = 'recalls';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section"><div class="container"><h1 class="h2 mb-1">Product Recall System</h1><p class="text-muted mb-4">Stop selling, block shipment, and notify affected retailers, distributors, and customers.</p><?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
<div class="row g-4"><?php if (can_access_module('recall_publish')): ?><div class="col-lg-4"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Create Recall</h2></div><div class="app-card-body"><form method="POST"><?php echo csrf_field(); ?><input class="form-control mb-2" name="product_code" placeholder="Product code"><input class="form-control mb-2" name="batch_number" placeholder="Batch number"><select class="form-select mb-2" name="reason"><option>Manufacturing defect</option><option>Safety issue</option><option>Quality issue</option><option>Fake batch detected</option></select><textarea class="form-control mb-3" name="description" placeholder="Recall description"></textarea><button class="btn btn-danger w-100">Publish Recall</button></form></div></div></div><?php endif; ?>
<div class="<?php echo can_access_module('recall_publish') ? 'col-lg-8' : 'col-lg-12'; ?>"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Recall Alerts</h2></div><div class="app-card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Target</th><th>Reason</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($recalls as $recall): ?><tr><td><?php echo e(($recall['product_code'] ?: $recall['batch_number']) . ' ' . ($recall['name'] ?: '')); ?></td><td><?php echo e($recall['reason']); ?></td><td><span class="badge bg-danger"><?php echo e($recall['recall_status']); ?></span></td><td><?php echo e($recall['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div></div></section><?php include __DIR__ . '/../includes/footer.php'; ?>
