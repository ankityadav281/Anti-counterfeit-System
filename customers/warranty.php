<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('warranty');
$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'claim';
    if ($action === 'claim') {
        if (!can_access_module('warranty_claim')) {
            header("Location: warranty.php");
            exit();
        }
        $code = strtoupper(trim($_POST['product_code']));
        $stmt = $db->prepare("SELECT id, warranty_period_months FROM products WHERE product_code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            $start = date('Y-m-d');
            $expiry = date('Y-m-d', strtotime('+' . (int) $product['warranty_period_months'] . ' months'));
            $db->prepare("INSERT INTO warranty_claims (product_id, customer_user_id, claim_reason, warranty_start, warranty_expiry) VALUES (:product, :customer, :reason, :start, :expiry)")
                ->execute([':product' => $product['id'], ':customer' => $_SESSION['user_id'], ':reason' => trim($_POST['claim_reason']), ':start' => $start, ':expiry' => $expiry]);
            $workflow = $db->prepare("INSERT INTO workflow_requests (request_type, requester_id, approver_role, entity_type, entity_id, title, details, current_step) VALUES ('warranty_request', :requester, 'retailer', 'warranty', :entity, :title, :details, 'Retailer review')");
            $workflow->execute([':requester' => $_SESSION['user_id'], ':entity' => $db->lastInsertId(), ':title' => 'Warranty claim for ' . $code, ':details' => trim($_POST['claim_reason'])]);
            create_notification($db, 'warranty', 'Warranty request', 'Warranty claim submitted for ' . $code, null);
            $success = 'Warranty claim submitted.';
        } else {
            $error = 'Product not found.';
        }
    } elseif (in_array($action, ['retailer_approved','manufacturer_approved','rejected','completed'], true)) {
        if (($action === 'retailer_approved' && !can_access_module('warranty_retailer_approve')) ||
            ($action === 'manufacturer_approved' && !can_access_module('warranty_manufacturer_approve')) ||
            (in_array($action, ['rejected', 'completed'], true) && !has_role(['super_admin', 'manufacturer', 'retailer']))) {
            header("Location: warranty.php");
            exit();
        }
        $id = (int) $_POST['claim_id'];
        $fields = ['claim_status' => $action];
        if ($action === 'retailer_approved') $fields['retailer_approved'] = 1;
        if ($action === 'manufacturer_approved') $fields['manufacturer_approved'] = 1;
        $set = "claim_status = :status" . ($action === 'retailer_approved' ? ", retailer_approved = 1" : "") . ($action === 'manufacturer_approved' ? ", manufacturer_approved = 1" : "");
        $db->prepare("UPDATE warranty_claims SET $set WHERE id = :id")->execute([':status' => $action, ':id' => $id]);
        create_notification($db, 'warranty', 'Warranty updated', 'Warranty claim #' . $id . ' is ' . $action, null);
        $success = 'Warranty claim updated.';
    }
}
$claims = $db->query("SELECT wc.*, p.product_code, p.name, u.username FROM warranty_claims wc JOIN products p ON p.id = wc.product_id LEFT JOIN users u ON u.id = wc.customer_user_id ORDER BY wc.updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Warranty - Anti-Counterfeit System';
$active_page = 'warranty';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section"><div class="container"><h1 class="h2 mb-1">Warranty Management</h1><p class="text-muted mb-4">Customer requests, retailer first approval, manufacturer final approval, and warranty expiry tracking.</p><?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
<div class="row g-4"><?php if (can_access_module('warranty_claim')): ?><div class="col-lg-4"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Request Warranty</h2></div><div class="app-card-body"><form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="claim"><input class="form-control mb-2" name="product_code" placeholder="Product code" required><textarea class="form-control mb-3" name="claim_reason" placeholder="Claim reason" required></textarea><button class="btn btn-primary w-100">Submit Claim</button></form></div></div></div><?php endif; ?>
<div class="<?php echo can_access_module('warranty_claim') ? 'col-lg-8' : 'col-lg-12'; ?>"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Claims</h2></div><div class="app-card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Product</th><th>Customer</th><th>Warranty</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($claims as $claim): ?><tr><td><?php echo e($claim['name']); ?><br><span class="code-badge"><?php echo e($claim['product_code']); ?></span></td><td><?php echo e($claim['username'] ?: 'N/A'); ?></td><td><?php echo e(($claim['warranty_start'] ?: 'N/A') . ' - ' . ($claim['warranty_expiry'] ?: 'N/A')); ?></td><td><span class="badge bg-secondary"><?php echo e($claim['claim_status']); ?></span></td><td><?php if (has_role(['retailer','manufacturer','super_admin'])): ?><form method="POST" class="d-flex gap-2"><?php echo csrf_field(); ?><input type="hidden" name="claim_id" value="<?php echo (int) $claim['id']; ?>"><select class="form-select form-select-sm" name="action"><?php if (can_access_module('warranty_retailer_approve')): ?><option value="retailer_approved">Retailer approve</option><?php endif; ?><?php if (can_access_module('warranty_manufacturer_approve')): ?><option value="manufacturer_approved">Manufacturer approve</option><?php endif; ?><option value="rejected">Reject</option><option value="completed">Complete</option></select><button class="btn btn-sm btn-outline-primary">Update</button></form><?php else: ?>Read only<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div></div></section><?php include __DIR__ . '/../includes/footer.php'; ?>
