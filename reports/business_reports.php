<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('business_reports');
$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$type = $_GET['type'] ?? 'verification';
$map = [
    'manufacturer' => ["SELECT p.product_code, p.name, p.lifecycle_status, COUNT(pv.id) AS verifications FROM products p LEFT JOIN product_verifications pv ON pv.product_id = p.id GROUP BY p.id", ['Code','Product','Lifecycle','Verifications']],
    'distributor' => ["SELECT product_id, from_location, to_location, quantity, movement_type, created_at FROM inventory_movements ORDER BY created_at DESC", ['Product','From','To','Qty','Type','Date']],
    'retailer' => ["SELECT product_code, customer_name, invoice_number, ownership_status, created_at FROM ownership o JOIN products p ON p.id = o.product_id ORDER BY o.created_at DESC", ['Code','Customer','Invoice','Status','Date']],
    'customer' => ["SELECT p.product_code, p.name, o.customer_name, o.purchase_date, o.ownership_status FROM ownership o JOIN products p ON p.id = o.product_id ORDER BY o.created_at DESC", ['Code','Product','Customer','Purchase','Status']],
    'warranty' => ["SELECT p.product_code, wc.claim_status, wc.warranty_start, wc.warranty_expiry, wc.created_at FROM warranty_claims wc JOIN products p ON p.id = wc.product_id ORDER BY wc.created_at DESC", ['Code','Status','Start','Expiry','Date']],
    'complaint' => ["SELECT product_code, product_name, status, created_at FROM complaints ORDER BY created_at DESC", ['Code','Product','Status','Date']],
    'verification' => ["SELECT p.product_code, pv.status, pv.city, pv.risk_score, pv.verification_date FROM product_verifications pv JOIN products p ON p.id = pv.product_id ORDER BY pv.verification_date DESC", ['Code','Status','City','Risk','Date']],
    'fraud' => ["SELECT product_code, risk_score, risk_level, reason, created_at FROM fraud_logs ORDER BY created_at DESC", ['Code','Score','Level','Reason','Date']],
    'inventory' => ["SELECT p.product_code, i.location_type, i.location_name, i.quantity, i.updated_at FROM inventory i JOIN products p ON p.id = i.product_id ORDER BY i.updated_at DESC", ['Code','Type','Location','Qty','Updated']],
    'batch' => ["SELECT batch_number, factory, supervisor, batch_status, created_at FROM product_batches ORDER BY created_at DESC", ['Batch','Factory','Supervisor','Status','Date']],
    'company' => ["SELECT company_name, gst_number, license_number, company_status, created_at FROM companies ORDER BY created_at DESC", ['Company','GST','License','Status','Date']],
];
$allowed_by_role = [
    'super_admin' => array_keys($map),
    'manufacturer' => ['manufacturer', 'warranty', 'complaint', 'verification', 'fraud', 'inventory', 'batch'],
    'retailer' => ['retailer', 'warranty', 'complaint', 'inventory'],
    'auditor' => array_keys($map),
];
$allowed_types = $allowed_by_role[role_alias()] ?? [];
if (!in_array($type, $allowed_types, true)) {
    $type = $allowed_types[0] ?? 'verification';
}
[$sql, $headers] = $map[$type] ?? $map['verification'];
$rows = $db->query($sql)->fetchAll(PDO::FETCH_NUM);
if (($_GET['export'] ?? '') === 'csv') {
    export_csv($type . '-report.csv', $headers, $rows);
}
$page_title = 'Business Reports - Anti-Counterfeit System';
$active_page = 'business_reports';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section"><div class="container"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h2 mb-1">Business Reports</h1><p class="text-muted mb-0">Manufacturer, distributor, retailer, customer, warranty, complaint, verification, fraud, inventory, sales, batch, and company reports.</p></div><div class="btn-group"><a class="btn btn-outline-primary" href="business_reports.php?type=<?php echo e($type); ?>&export=csv">CSV</a><button class="btn btn-outline-secondary" onclick="window.print()">PDF</button><a class="btn btn-outline-success" href="business_reports.php?type=<?php echo e($type); ?>&export=csv">Excel</a></div></div>
<div class="app-card mb-4"><div class="app-card-body"><form class="row g-2"><div class="col-md-10"><select class="form-select" name="type"><?php foreach ($allowed_types as $key): ?><option value="<?php echo e($key); ?>" <?php echo $type === $key ? 'selected' : ''; ?>><?php echo e(ucfirst($key)); ?> Report</option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Load</button></div></form></div></div>
<div class="app-card"><div class="app-card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><?php foreach ($headers as $header): ?><th><?php echo e($header); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?php echo e($cell); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div></div></div></section><?php include __DIR__ . '/../includes/footer.php'; ?>
