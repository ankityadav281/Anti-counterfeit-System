<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('supply_chain');

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$stages = ['Manufactured', 'Packed', 'Quality Checked', 'Dispatched', 'Warehouse', 'Distributor', 'Retailer', 'Sold', 'Customer Registered'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_access_module('supply_chain_record')) {
        header("Location: supply_chain.php");
        exit();
    }
    verify_csrf();
    $product_id = (int) ($_POST['product_id'] ?? 0);
    $stage = $_POST['stage'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($product_id && in_array($stage, $stages, true)) {
        $old = $db->prepare("SELECT lifecycle_status FROM products WHERE id = :id");
        $old->execute([':id' => $product_id]);
        $previous = $old->fetchColumn();
        $db->prepare("UPDATE products SET lifecycle_status = :stage, sold_at = IF(:stage = 'Sold', NOW(), sold_at) WHERE id = :id")->execute([':stage' => $stage, ':id' => $product_id]);
        $db->prepare("INSERT INTO product_history (product_id, stage, location, handled_by, notes) VALUES (:product_id, :stage, :location, :handled_by, :notes)")
            ->execute([':product_id' => $product_id, ':stage' => $stage, ':location' => $location, ':handled_by' => $_SESSION['user_id'], ':notes' => $notes]);
        audit_log($db, 'supply_chain', null, $product_id, $previous, $stage, $location, ['notes' => $notes]);
        $success = 'Supply chain stage recorded.';
    } else {
        $error = 'Choose a product and valid lifecycle stage.';
    }
}

$products = $db->query("SELECT id, name, product_code, lifecycle_status FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$history = $db->query("SELECT ph.*, p.name, p.product_code, u.username FROM product_history ph JOIN products p ON p.id = ph.product_id LEFT JOIN users u ON u.id = ph.handled_by ORDER BY ph.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Supply Chain - Anti-Counterfeit System';
$active_page = 'supply_chain';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <h1 class="h2 mb-1">Supply Chain Tracking</h1>
        <p class="text-muted mb-4">Maintain lifecycle movement from manufacturing to customer registration.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <div class="row g-4">
            <?php if (can_access_module('supply_chain_record')): ?>
            <div class="col-lg-4">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Record Movement</h2></div>
                    <div class="app-card-body">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3"><label class="form-label">Product</label><select class="form-select" name="product_id" required>
                                <option value="">Select product</option>
                                <?php foreach ($products as $product): ?><option value="<?php echo (int) $product['id']; ?>"><?php echo e($product['name'] . ' - ' . $product['product_code']); ?></option><?php endforeach; ?>
                            </select></div>
                            <div class="mb-3"><label class="form-label">Stage</label><select class="form-select" name="stage"><?php foreach ($stages as $stage): ?><option><?php echo e($stage); ?></option><?php endforeach; ?></select></div>
                            <div class="mb-3"><label class="form-label">Location</label><input class="form-control" name="location"></div>
                            <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
                            <button class="btn btn-primary w-100">Save Movement</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo can_access_module('supply_chain_record') ? 'col-lg-8' : 'col-lg-12'; ?>">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Movement History</h2></div>
                    <div class="app-card-body">
                        <div class="table-responsive"><table class="table align-middle">
                            <thead><tr><th>Product</th><th>Stage</th><th>Location</th><th>User</th><th>Date</th></tr></thead>
                            <tbody><?php foreach ($history as $row): ?><tr>
                                <td><?php echo e($row['name']); ?><br><span class="code-badge"><?php echo e($row['product_code']); ?></span></td>
                                <td><?php echo e($row['stage']); ?></td><td><?php echo e($row['location'] ?: 'N/A'); ?></td><td><?php echo e($row['username'] ?: 'System'); ?></td><td><?php echo e($row['created_at']); ?></td>
                            </tr><?php endforeach; ?></tbody>
                        </table></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
