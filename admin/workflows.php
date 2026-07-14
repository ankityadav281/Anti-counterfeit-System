<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_login();
$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'create';
    if ($action === 'create') {
        $type = $_POST['request_type'] ?? 'inventory_request';
        $title = trim($_POST['title'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $approver_role = $_POST['approver_role'] ?? 'super_admin';
        if ($title === '') {
            $error = 'Request title is required.';
        } else {
            $stmt = $db->prepare("INSERT INTO workflow_requests (request_type, requester_id, approver_role, title, details, current_step)
                VALUES (:type, :requester, :approver_role, :title, :details, 'Submitted')");
            $stmt->execute([':type' => $type, ':requester' => $_SESSION['user_id'], ':approver_role' => $approver_role, ':title' => $title, ':details' => $details]);
            create_notification($db, 'workflow', 'Approval request pending', $title, null);
            audit_log($db, 'workflow', (int) $db->lastInsertId(), null, null, 'pending', '', ['type' => $type]);
            activity_log($db, 'Workflow request created', 'workflow', (int) $db->lastInsertId());
            $success = 'Request submitted for approval.';
        }
    } elseif (in_array($action, ['approved', 'rejected', 'cancelled', 'completed'], true)) {
        $id = (int) ($_POST['request_id'] ?? 0);
        $request_stmt = $db->prepare("SELECT * FROM workflow_requests WHERE id = :id LIMIT 1");
        $request_stmt->execute([':id' => $id]);
        $request = $request_stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $db->prepare("UPDATE workflow_requests SET status = :status, approver_id = :approver, current_step = :step WHERE id = :id");
        $stmt->execute([':status' => $action, ':approver' => $_SESSION['user_id'], ':step' => ucfirst($action), ':id' => $id]);
        if ($action === 'approved' && $request && $request['request_type'] === 'ownership_transfer' && $request['entity_type'] === 'ownership') {
            $ownership_id = (int) $request['entity_id'];
            $ownership_stmt = $db->prepare("SELECT o.*, p.id AS product_id FROM ownership o JOIN products p ON p.id = o.product_id WHERE o.id = :id LIMIT 1");
            $ownership_stmt->execute([':id' => $ownership_id]);
            $ownership = $ownership_stmt->fetch(PDO::FETCH_ASSOC);
            if ($ownership) {
                $to_customer = 'Approved New Owner';
                if (preg_match('/ to (.+) on /', $request['details'] ?? '', $matches)) {
                    $to_customer = trim($matches[1]);
                }
                $db->prepare("UPDATE ownership SET ownership_status = 'transferred' WHERE id = :id")->execute([':id' => $ownership_id]);
                $db->prepare("INSERT INTO ownership_transfers (ownership_id, from_customer, to_customer, transfer_date) VALUES (:ownership_id, :from_customer, :to_customer, CURDATE())")
                    ->execute([':ownership_id' => $ownership_id, ':from_customer' => $ownership['customer_name'], ':to_customer' => $to_customer]);
                $db->prepare("UPDATE products SET lifecycle_status = 'Ownership Changed' WHERE id = :id")->execute([':id' => $ownership['product_id']]);
                create_notification($db, 'ownership', 'Ownership approved', 'Ownership transfer approved for ' . $ownership['customer_name'], $ownership['customer_user_id']);
            }
        }
        audit_log($db, 'workflow', $id, null, null, $action, '', []);
        create_notification($db, 'workflow', 'Workflow ' . $action, 'Request #' . $id . ' was ' . $action, null);
        $success = 'Workflow updated.';
    }
}

$where = role_alias() === 'super_admin' ? '1=1' : '(requester_id = :user OR approver_role = :role OR approver_id = :user)';
$stmt = $db->prepare("SELECT wr.*, u.username FROM workflow_requests wr LEFT JOIN users u ON u.id = wr.requester_id WHERE $where ORDER BY wr.updated_at DESC");
if (role_alias() === 'super_admin') {
    $stmt->execute();
} else {
    $stmt->execute([':user' => $_SESSION['user_id'], ':role' => role_alias()]);
}
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Approval Workflows - Anti-Counterfeit System';
$active_page = 'workflows';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section"><div class="container">
    <h1 class="h2 mb-1">Approval Workflows</h1><p class="text-muted mb-4">Pending, approved, rejected, cancelled, and completed business requests.</p>
    <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">New Request</h2></div><div class="app-card-body">
            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
                <div class="mb-3"><label class="form-label">Type</label><select class="form-select" name="request_type"><option>manufacturer_approval</option><option>inventory_request</option><option>ownership_transfer</option><option>warranty_request</option><option>complaint_review</option><option>shipment_acknowledgement</option></select></div>
                <div class="mb-3"><label class="form-label">Approver Role</label><select class="form-select" name="approver_role"><option value="super_admin">Super Admin</option><option value="manufacturer">Manufacturer</option><option value="retailer">Retailer</option><option value="distributor">Distributor</option><option value="warehouse_manager">Warehouse Manager</option></select></div>
                <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" required></div>
                <div class="mb-3"><label class="form-label">Details</label><textarea class="form-control" name="details" rows="4"></textarea></div>
                <button class="btn btn-primary w-100">Submit</button>
            </form>
        </div></div></div>
        <div class="col-lg-8"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Requests</h2></div><div class="app-card-body"><div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Request</th><th>Requester</th><th>Approver</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($requests as $request): ?><tr>
                <td><strong><?php echo e($request['title']); ?></strong><br><small><?php echo e($request['request_type']); ?></small></td><td><?php echo e($request['username'] ?: 'System'); ?></td><td><?php echo e(role_label($request['approver_role'])); ?></td><td><span class="badge bg-<?php echo $request['status'] === 'pending' ? 'warning text-dark' : ($request['status'] === 'approved' ? 'success' : 'secondary'); ?>"><?php echo e(ucfirst($request['status'])); ?></span></td>
                <td class="text-end"><?php if ($request['status'] === 'pending' && (role_alias() === $request['approver_role'] || role_alias() === 'super_admin')): foreach (['approved','rejected','completed'] as $next): ?><form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>"><input type="hidden" name="action" value="<?php echo e($next); ?>"><button class="btn btn-sm btn-outline-primary"><?php echo e(ucfirst($next)); ?></button></form> <?php endforeach; endif; ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div></div></div></div>
    </div>
</div></section><?php include __DIR__ . '/../includes/footer.php'; ?>
