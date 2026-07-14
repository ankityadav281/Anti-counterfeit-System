<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('ownership');

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!can_access_module('ownership_register')) {
        header("Location: ownership.php");
        exit();
    }
    verify_csrf();
    $action = $_POST['action'] ?? 'register';
    if ($action === 'register') {
        $code = strtoupper(trim($_POST['product_code'] ?? ''));
        $customer_name = trim($_POST['customer_name'] ?? '');
        $purchase_date = $_POST['purchase_date'] ?? '';
        $invoice = trim($_POST['invoice_number'] ?? '');
        $product_stmt = $db->prepare("SELECT id, lifecycle_status FROM products WHERE product_code = :code LIMIT 1");
        $product_stmt->execute([':code' => $code]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product || $customer_name === '' || $purchase_date === '' || $invoice === '') {
            $error = 'Valid product, customer name, purchase date, and invoice are required.';
        } else {
            $active = $db->prepare("SELECT id FROM ownership WHERE product_id = :product_id AND ownership_status = 'active' LIMIT 1");
            $active->execute([':product_id' => $product['id']]);
            if ($active->fetch()) {
                $error = 'This product already has active ownership.';
            } else {
                $stmt = $db->prepare("INSERT INTO ownership (product_id, customer_user_id, customer_name, purchase_date, invoice_number)
                    VALUES (:product_id, :customer_user_id, :customer_name, :purchase_date, :invoice)");
                $stmt->execute([':product_id' => $product['id'], ':customer_user_id' => $_SESSION['user_id'], ':customer_name' => $customer_name, ':purchase_date' => $purchase_date, ':invoice' => $invoice]);
                $db->prepare("UPDATE products SET lifecycle_status = 'Customer Registered' WHERE id = :id")->execute([':id' => $product['id']]);
                audit_log($db, 'ownership', (int) $db->lastInsertId(), $product['id'], $product['lifecycle_status'], 'Customer Registered', '', ['invoice' => $invoice]);
                create_notification($db, 'ownership', 'Ownership registered', 'Ownership registered for product ' . $code, null);
                $success = 'Ownership registered.';
            }
        }
    } elseif ($action === 'transfer') {
        $ownership_id = (int) ($_POST['ownership_id'] ?? 0);
        $to_customer = trim($_POST['to_customer'] ?? '');
        $transfer_date = $_POST['transfer_date'] ?? date('Y-m-d');
        if ($ownership_id > 0 && $to_customer !== '') {
            $current = $db->prepare("SELECT o.*, p.id AS product_id FROM ownership o JOIN products p ON p.id = o.product_id WHERE o.id = :id AND o.ownership_status = 'active'");
            $current->execute([':id' => $ownership_id]);
            $row = $current->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $workflow = $db->prepare("INSERT INTO workflow_requests (request_type, requester_id, approver_role, entity_type, entity_id, title, details, current_step)
                    VALUES ('ownership_transfer', :requester, 'customer', 'ownership', :entity_id, :title, :details, 'Current owner approval')");
                $workflow->execute([
                    ':requester' => $_SESSION['user_id'],
                    ':entity_id' => $ownership_id,
                    ':title' => 'Ownership transfer request',
                    ':details' => $row['customer_name'] . ' requested transfer to ' . $to_customer . ' on ' . $transfer_date,
                ]);
                audit_log($db, 'ownership_transfer_request', $ownership_id, $row['product_id'], $row['customer_name'], $to_customer, '', []);
                create_notification($db, 'ownership', 'Ownership transfer pending', $row['customer_name'] . ' requested transfer to ' . $to_customer, null);
                $success = 'Ownership transfer request submitted for approval.';
            }
        }
    }
}

$ownerships = $db->query("SELECT o.*, p.name, p.product_code FROM ownership o JOIN products p ON p.id = o.product_id ORDER BY o.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Ownership - Anti-Counterfeit System';
$active_page = 'ownership';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <h1 class="h2 mb-1">Product Ownership</h1>
        <p class="text-muted mb-4">Register purchases, prevent duplicate ownership, and track transfers.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <div class="row g-4">
            <?php if (can_access_module('ownership_register')): ?>
            <div class="col-lg-4">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Register Purchase</h2></div>
                    <div class="app-card-body">
                        <form method="POST">
                            <?php echo csrf_field(); ?><input type="hidden" name="action" value="register">
                            <div class="mb-3"><label class="form-label">Product Code</label><input class="form-control" name="product_code" required></div>
                            <div class="mb-3"><label class="form-label">Customer Name</label><input class="form-control" name="customer_name" value="<?php echo e($_SESSION['username'] ?? ''); ?>" required></div>
                            <div class="mb-3"><label class="form-label">Purchase Date</label><input type="date" class="form-control" name="purchase_date" required></div>
                            <div class="mb-3"><label class="form-label">Invoice Number</label><input class="form-control" name="invoice_number" required></div>
                            <button class="btn btn-primary w-100">Register Ownership</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo can_access_module('ownership_register') ? 'col-lg-8' : 'col-lg-12'; ?>">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Ownership History</h2></div>
                    <div class="app-card-body">
                        <div class="table-responsive"><table class="table align-middle">
                            <thead><tr><th>Product</th><th>Owner</th><th>Invoice</th><th>Status</th><th>Transfer</th></tr></thead>
                            <tbody><?php foreach ($ownerships as $row): ?><tr>
                                <td><?php echo e($row['name']); ?><br><span class="code-badge"><?php echo e($row['product_code']); ?></span></td>
                                <td><?php echo e($row['customer_name']); ?><br><?php echo e($row['purchase_date']); ?></td>
                                <td><?php echo e($row['invoice_number']); ?></td>
                                <td><span class="badge bg-<?php echo $row['ownership_status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo e(ucfirst($row['ownership_status'])); ?></span></td>
                                <td>
                                    <?php if ($row['ownership_status'] === 'active'): ?>
                                        <form method="POST" class="d-flex gap-2">
                                            <?php echo csrf_field(); ?><input type="hidden" name="action" value="transfer"><input type="hidden" name="ownership_id" value="<?php echo (int) $row['id']; ?>">
                                            <input class="form-control form-control-sm" name="to_customer" placeholder="New owner" required>
                                            <input type="date" class="form-control form-control-sm" name="transfer_date" value="<?php echo date('Y-m-d'); ?>">
                                            <button class="btn btn-sm btn-outline-primary">Transfer</button>
                                        </form>
                                    <?php else: ?>Transferred<?php endif; ?>
                                </td>
                            </tr><?php endforeach; ?></tbody>
                        </table></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
