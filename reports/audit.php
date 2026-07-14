<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('audit');

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$logs = $db->query("SELECT al.*, u.username, p.product_code FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id LEFT JOIN products p ON p.id = al.product_id ORDER BY al.id ASC")->fetchAll(PDO::FETCH_ASSOC);
$tampered = [];
$previous = '';
foreach ($logs as $log) {
    if (($log['previous_record_hash'] ?? '') !== ($previous ?: null) && !($previous === '' && $log['previous_record_hash'] === null)) {
        $tampered[] = $log['id'];
    }
    $previous = $log['record_hash'];
}
if ($tampered) {
    create_notification($db, 'audit', 'Audit trail warning', 'Hash-chain mismatch detected in audit logs: ' . implode(', ', $tampered), null);
}
$page_title = 'Audit Trail - Anti-Counterfeit System';
$active_page = 'audit';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <h1 class="h2 mb-1">Immutable Audit Trail</h1>
        <p class="text-muted mb-4">Hash-chained records for product, verification, ownership, and supply-chain events.</p>
        <?php if ($tampered): ?><div class="alert alert-danger">Hash-chain mismatch detected. Check records: <?php echo e(implode(', ', $tampered)); ?></div><?php else: ?><div class="alert alert-success">Audit chain verified.</div><?php endif; ?>
        <div class="app-card"><div class="app-card-body"><div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>ID</th><th>Entity</th><th>Product</th><th>Status</th><th>User</th><th>Hash</th><th>Date</th></tr></thead>
                <tbody><?php foreach (array_reverse($logs) as $log): ?><tr>
                    <td><?php echo (int) $log['id']; ?></td>
                    <td><?php echo e($log['entity_type']); ?></td>
                    <td><?php echo e($log['product_code'] ?: 'N/A'); ?></td>
                    <td><?php echo e(($log['previous_status'] ?: 'N/A') . ' -> ' . ($log['new_status'] ?: 'N/A')); ?></td>
                    <td><?php echo e($log['username'] ?: 'System'); ?></td>
                    <td><small class="text-muted"><?php echo e(substr($log['record_hash'], 0, 18)); ?>...</small></td>
                    <td><?php echo e($log['created_at']); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table>
        </div></div></div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
