<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('global_search');
$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $searches = [
        'Users' => ["SELECT username AS title, email AS subtitle, role AS meta FROM users WHERE username LIKE :q OR email LIKE :q LIMIT 10", page_url('profile')],
        'Products' => ["SELECT name AS title, product_code AS subtitle, lifecycle_status AS meta FROM products WHERE name LIKE :q OR product_code LIKE :q OR batch_number LIKE :q LIMIT 10", page_url('products')],
        'Companies' => ["SELECT company_name AS title, gst_number AS subtitle, company_status AS meta FROM companies WHERE company_name LIKE :q OR gst_number LIKE :q OR license_number LIKE :q LIMIT 10", page_url('companies')],
        'Complaints' => ["SELECT product_name AS title, product_code AS subtitle, status AS meta FROM complaints WHERE product_name LIKE :q OR product_code LIKE :q OR description LIKE :q LIMIT 10", page_url('complaints')],
        'Batches' => ["SELECT batch_number AS title, factory AS subtitle, batch_status AS meta FROM product_batches WHERE batch_number LIKE :q OR factory LIKE :q OR supervisor LIKE :q LIMIT 10", page_url('batches')],
        'Invoices' => ["SELECT invoice_number AS title, customer_name AS subtitle, ownership_status AS meta FROM ownership WHERE invoice_number LIKE :q OR customer_name LIKE :q LIMIT 10", page_url('ownership')],
        'QR Codes' => ["SELECT secure_hash AS title, digital_signature AS subtitle, generated_at AS meta FROM qr_codes WHERE secure_hash LIKE :q OR digital_signature LIKE :q LIMIT 10", page_url('products')],
        'Inventory' => ["SELECT location_name AS title, batch_number AS subtitle, quantity AS meta FROM inventory WHERE location_name LIKE :q OR batch_number LIKE :q LIMIT 10", page_url('inventory')],
        'Notifications' => ["SELECT title, message AS subtitle, type AS meta FROM notifications WHERE title LIKE :q OR message LIKE :q LIMIT 10", page_url('notifications')]
    ];
    foreach ($searches as $label => [$sql, $url]) {
        $stmt = $db->prepare($sql);
        $stmt->execute([':q' => $like]);
        $results[$label] = ['url' => $url, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }
}
$page_title = 'Global Search - Anti-Counterfeit System';
$active_page = 'global_search';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section"><div class="container"><h1 class="h2 mb-1">Global Search</h1><p class="text-muted mb-4">Search users, products, companies, complaints, batches, invoices, QR codes, logs, inventory, and notifications.</p><div class="app-card mb-4"><div class="app-card-body"><form class="row g-2"><div class="col-md-10"><input class="form-control form-control-lg" name="q" value="<?php echo e($q); ?>" placeholder="Search the enterprise platform"></div><div class="col-md-2"><button class="btn btn-primary btn-lg w-100">Search</button></div></form></div></div><?php foreach ($results as $label => $group): ?><div class="app-card mb-3"><div class="app-card-header"><h2 class="h5 mb-0"><?php echo e($label); ?></h2></div><div class="app-card-body"><?php if (!$group['rows']): ?><p class="text-muted mb-0">No matches.</p><?php else: ?><?php foreach ($group['rows'] as $row): ?><a class="d-block border-bottom py-2 text-decoration-none" href="<?php echo e($group['url']); ?>"><strong><?php echo e($row['title']); ?></strong><br><small class="text-muted"><?php echo e(($row['subtitle'] ?? '') . ' · ' . ($row['meta'] ?? '')); ?></small></a><?php endforeach; ?><?php endif; ?></div></div><?php endforeach; ?></div></section><?php include __DIR__ . '/../includes/footer.php'; ?>
