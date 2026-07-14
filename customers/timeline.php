<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_login();
$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$code = strtoupper(trim($_GET['code'] ?? ''));
$product = null;
$events = [];
if ($code !== '') {
    $stmt = $db->prepare("SELECT * FROM products WHERE product_code = :code LIMIT 1");
    $stmt->execute([':code' => $code]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        $queries = [
            "SELECT stage AS title, notes AS details, location, created_at FROM product_history WHERE product_id = :id",
            "SELECT status AS title, 'Product verified' AS details, CONCAT_WS(', ', city, state, country) AS location, verification_date AS created_at FROM product_verifications WHERE product_id = :id",
            "SELECT 'Warranty Registered' AS title, claim_reason AS details, claim_status AS location, created_at FROM warranty_claims WHERE product_id = :id",
            "SELECT 'Ownership Changed' AS title, customer_name AS details, ownership_status AS location, created_at FROM ownership WHERE product_id = :id",
            "SELECT 'Complaint Raised' AS title, description AS details, status AS location, created_at FROM complaints WHERE product_code = :code"
        ];
        foreach ($queries as $sql) {
            $s = $db->prepare($sql);
            strpos($sql, ':code') !== false ? $s->execute([':code' => $code]) : $s->execute([':id' => $product['id']]);
            $events = array_merge($events, $s->fetchAll(PDO::FETCH_ASSOC));
        }
        usort($events, function ($a, $b) { return strcmp($a['created_at'], $b['created_at']); });
    }
}
$page_title = 'Lifecycle Timeline - Anti-Counterfeit System';
$active_page = 'timeline';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section"><div class="container">
    <h1 class="h2 mb-1">Product Lifecycle Timeline</h1><p class="text-muted mb-4">Manufactured, packed, inspected, shipped, sold, verified, warrantied, transferred, complained, and resolved.</p>
    <div class="app-card mb-4"><div class="app-card-body"><form class="row g-2"><div class="col-md-10"><input class="form-control" name="code" value="<?php echo e($code); ?>" placeholder="Enter product code"></div><div class="col-md-2"><button class="btn btn-primary w-100">View</button></div></form></div></div>
    <?php if ($code && !$product): ?><div class="alert alert-danger">Product not found.</div><?php endif; ?>
    <?php if ($product): ?><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0"><?php echo e($product['name']); ?> <span class="code-badge"><?php echo e($product['product_code']); ?></span></h2></div><div class="app-card-body">
        <?php if (!$events): ?><p class="text-muted mb-0">No lifecycle events yet.</p><?php endif; ?>
        <div class="timeline"><?php foreach ($events as $event): ?><div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><strong><?php echo e($event['title']); ?></strong><p class="mb-1"><?php echo e($event['details'] ?: 'No details'); ?></p><small class="text-muted"><?php echo e(($event['location'] ?: 'N/A') . ' · ' . $event['created_at']); ?></small></div></div><?php endforeach; ?></div>
    </div></div><?php endif; ?>
</div></section><?php include __DIR__ . '/../includes/footer.php'; ?>
