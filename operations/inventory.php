<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('inventory');

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_access_module('inventory_update')) {
        header("Location: inventory.php");
        exit();
    }
    verify_csrf();
    $product_id = (int) ($_POST['product_id'] ?? 0);
    $location_type = $_POST['location_type'] ?? 'warehouse';
    $location_name = trim($_POST['location_name'] ?? '');
    $quantity = max(0, (int) ($_POST['quantity'] ?? 0));
    $threshold = max(0, (int) ($_POST['low_stock_threshold'] ?? 10));

    if ($product_id <= 0 || $location_name === '') {
        $error = 'Product and location are required.';
    } else {
        $product = $db->prepare("SELECT batch_number, expiry_date FROM products WHERE id = :id");
        $product->execute([':id' => $product_id]);
        $row = $product->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stmt = $db->prepare("INSERT INTO inventory (product_id, location_type, location_name, batch_number, quantity, low_stock_threshold, expiry_date)
                VALUES (:product_id, :location_type, :location_name, :batch_number, :quantity, :threshold, :expiry_date)
                ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), low_stock_threshold = VALUES(low_stock_threshold), expiry_date = VALUES(expiry_date)");
            $stmt->execute([
                ':product_id' => $product_id,
                ':location_type' => $location_type,
                ':location_name' => $location_name,
                ':batch_number' => $row['batch_number'],
                ':quantity' => $quantity,
                ':threshold' => $threshold,
                ':expiry_date' => $row['expiry_date'],
            ]);
            $movement = $db->prepare("INSERT INTO inventory_movements (product_id, to_location, quantity, movement_type, user_id) VALUES (:product_id, :to_location, :quantity, 'stock_update', :user_id)");
            $movement->execute([':product_id' => $product_id, ':to_location' => $location_name, ':quantity' => $quantity, ':user_id' => $_SESSION['user_id']]);
            audit_log($db, 'inventory', null, $product_id, null, 'Stock Updated', $location_name, ['quantity' => $quantity]);
            if ($quantity <= $threshold) {
                create_notification($db, 'inventory', 'Low stock alert', 'Low stock at ' . $location_name, null);
            }
            $success = 'Inventory updated.';
        }
    }
}

$products = $db->query("SELECT id, name, product_code FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$inventory = $db->query("SELECT i.*, p.name, p.product_code FROM inventory i JOIN products p ON p.id = i.product_id ORDER BY i.updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Inventory - Anti-Counterfeit System';
$active_page = 'inventory';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">Inventory Management</h1>
                <p class="text-muted mb-0">Track stock across manufacturers, warehouses, distributors, and retailers.</p>
            </div>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <div class="row g-4">
            <?php if (can_access_module('inventory_update')): ?>
            <div class="col-lg-4">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Update Stock</h2></div>
                    <div class="app-card-body">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Product</label>
                                <select class="form-select" name="product_id" required>
                                    <option value="">Select product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo (int) $product['id']; ?>"><?php echo e($product['name'] . ' - ' . $product['product_code']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Location Type</label>
                                <select class="form-select" name="location_type">
                                    <option value="manufacturer">Manufacturer</option>
                                    <option value="warehouse">Warehouse</option>
                                    <option value="distributor">Distributor</option>
                                    <option value="retailer">Retailer</option>
                                </select>
                            </div>
                            <div class="mb-3"><label class="form-label">Location Name</label><input class="form-control" name="location_name" required></div>
                            <div class="row">
                                <div class="col-6 mb-3"><label class="form-label">Quantity</label><input type="number" class="form-control" name="quantity" min="0" required></div>
                                <div class="col-6 mb-3"><label class="form-label">Low Alert</label><input type="number" class="form-control" name="low_stock_threshold" min="0" value="10"></div>
                            </div>
                            <button class="btn btn-primary w-100">Save Stock</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo can_access_module('inventory_update') ? 'col-lg-8' : 'col-lg-12'; ?>">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Current Inventory</h2></div>
                    <div class="app-card-body">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Product</th><th>Location</th><th>Batch</th><th>Qty</th><th>Expiry</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($inventory as $item): ?>
                                        <tr>
                                            <td><?php echo e($item['name']); ?><br><span class="code-badge"><?php echo e($item['product_code']); ?></span></td>
                                            <td><?php echo e(role_label($item['location_type']) . ': ' . $item['location_name']); ?></td>
                                            <td><?php echo e($item['batch_number']); ?></td>
                                            <td><?php echo (int) $item['quantity']; ?></td>
                                            <td><?php echo $item['expiry_date'] ? e($item['expiry_date']) : 'N/A'; ?></td>
                                            <td><span class="badge <?php echo ((int) $item['quantity'] <= (int) $item['low_stock_threshold']) ? 'bg-warning text-dark' : 'bg-success'; ?>"><?php echo ((int) $item['quantity'] <= (int) $item['low_stock_threshold']) ? 'Low Stock' : 'Available'; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
