<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('batches');
$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!can_access_module('batch_manage')) {
        header("Location: batches.php");
        exit();
    }
    if (($_POST['action'] ?? '') === 'recall') {
        $batch = trim($_POST['batch_number']);
        $db->prepare("UPDATE product_batches SET batch_status = 'recalled' WHERE batch_number = :batch")->execute([':batch' => $batch]);
        $db->prepare("INSERT INTO product_recalls (batch_number, manufacturer_user_id, reason, description) VALUES (:batch, :user, :reason, :description)")
            ->execute([':batch' => $batch, ':user' => $_SESSION['user_id'], ':reason' => $_POST['reason'], ':description' => trim($_POST['description'])]);
        create_notification($db, 'recall', 'Batch recalled', 'Batch ' . $batch . ' has been recalled.', null);
        $success = 'Batch recall created.';
    } else {
        $stmt = $db->prepare("INSERT INTO product_batches (manufacturer_id, batch_number, manufacturing_date, expiry_date, production_unit, factory, supervisor, batch_status, quality_report, created_by)
            VALUES (1, :batch, :mfg, :exp, :unit, :factory, :supervisor, :status, :report, :user)");
        $stmt->execute([':batch' => trim($_POST['batch_number']), ':mfg' => $_POST['manufacturing_date'], ':exp' => $_POST['expiry_date'] ?: null, ':unit' => trim($_POST['production_unit']), ':factory' => trim($_POST['factory']), ':supervisor' => trim($_POST['supervisor']), ':status' => $_POST['batch_status'], ':report' => trim($_POST['quality_report']), ':user' => $_SESSION['user_id']]);
        activity_log($db, 'Batch created', 'batch', (int) $db->lastInsertId());
        $success = 'Batch saved.';
    }
}
$batches = $db->query("SELECT * FROM product_batches ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Batches - Anti-Counterfeit System';
$active_page = 'batches';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section"><div class="container"><h1 class="h2 mb-1">Batch Management</h1><p class="text-muted mb-4">Production unit, factory, supervisor, quality report, status, and recall control.</p><?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
<div class="row g-4"><?php if (can_access_module('batch_manage')): ?><div class="col-lg-4"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Create Batch</h2></div><div class="app-card-body"><form method="POST"><?php echo csrf_field(); ?><input class="form-control mb-2" name="batch_number" placeholder="Batch number" required><input type="date" class="form-control mb-2" name="manufacturing_date" required><input type="date" class="form-control mb-2" name="expiry_date"><input class="form-control mb-2" name="production_unit" placeholder="Production unit"><input class="form-control mb-2" name="factory" placeholder="Factory"><input class="form-control mb-2" name="supervisor" placeholder="Supervisor"><select class="form-select mb-2" name="batch_status"><option>pending</option><option>approved</option><option>completed</option><option>rejected</option></select><textarea class="form-control mb-3" name="quality_report" placeholder="Quality report"></textarea><button class="btn btn-primary w-100">Save Batch</button></form></div></div></div><?php endif; ?>
<div class="<?php echo can_access_module('batch_manage') ? 'col-lg-8' : 'col-lg-12'; ?>"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Batches</h2></div><div class="app-card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Batch</th><th>Factory</th><th>Supervisor</th><th>Status</th><th>Recall</th></tr></thead><tbody><?php foreach ($batches as $batch): ?><tr><td><?php echo e($batch['batch_number']); ?><br><small><?php echo e($batch['manufacturing_date'] . ' - ' . ($batch['expiry_date'] ?: 'N/A')); ?></small></td><td><?php echo e($batch['factory']); ?></td><td><?php echo e($batch['supervisor']); ?></td><td><span class="badge bg-secondary"><?php echo e($batch['batch_status']); ?></span></td><td><?php if (can_access_module('recall_publish')): ?><form method="POST" class="d-flex gap-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="recall"><input type="hidden" name="batch_number" value="<?php echo e($batch['batch_number']); ?>"><input type="hidden" name="reason" value="Fake batch detected"><input class="form-control form-control-sm" name="description" placeholder="Reason"><button class="btn btn-sm btn-outline-danger">Recall</button></form><?php else: ?>Read only<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div></div></section><?php include __DIR__ . '/../includes/footer.php'; ?>
