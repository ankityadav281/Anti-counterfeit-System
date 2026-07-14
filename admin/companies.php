<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('companies');
$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!can_access_module('company_manage')) {
        header("Location: companies.php");
        exit();
    }
    if (($_POST['action'] ?? '') === 'approve' && role_alias() === 'super_admin') {
        $db->prepare("UPDATE companies SET company_status = :status WHERE id = :id")->execute([':status' => $_POST['status'], ':id' => (int) $_POST['company_id']]);
        $success = 'Company status updated.';
    } else {
        $stmt = $db->prepare("INSERT INTO companies (owner_user_id, company_name, gst_number, license_number, address, contact_person, factory_details, product_categories, authorized_retailers, authorized_distributors)
            VALUES (:owner, :company, :gst, :license, :address, :contact, :factory, :categories, :retailers, :distributors)");
        $stmt->execute([':owner' => $_SESSION['user_id'], ':company' => trim($_POST['company_name']), ':gst' => trim($_POST['gst_number']), ':license' => trim($_POST['license_number']), ':address' => trim($_POST['address']), ':contact' => trim($_POST['contact_person']), ':factory' => trim($_POST['factory_details']), ':categories' => trim($_POST['product_categories']), ':retailers' => trim($_POST['authorized_retailers']), ':distributors' => trim($_POST['authorized_distributors'])]);
        $workflow = $db->prepare("INSERT INTO workflow_requests (request_type, requester_id, approver_role, entity_type, entity_id, title, details, current_step) VALUES ('manufacturer_approval', :requester, 'super_admin', 'company', :entity, :title, 'Company approval required', 'Submitted')");
        $workflow->execute([':requester' => $_SESSION['user_id'], ':entity' => $db->lastInsertId(), ':title' => 'Approve company: ' . trim($_POST['company_name'])]);
        $success = 'Company submitted for approval.';
    }
}
$companies = $db->query("SELECT c.*, u.username FROM companies c LEFT JOIN users u ON u.id = c.owner_user_id ORDER BY c.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Companies - Anti-Counterfeit System';
$active_page = 'companies';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section"><div class="container"><h1 class="h2 mb-1">Company & Brand Management</h1><p class="text-muted mb-4">Multi-company manufacturer, license, brand, distributor, and retailer registry.</p><?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
<div class="row g-4"><?php if (can_access_module('company_manage')): ?><div class="col-lg-4"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Register Company</h2></div><div class="app-card-body"><form method="POST"><?php echo csrf_field(); ?><input class="form-control mb-2" name="company_name" placeholder="Company name" required><input class="form-control mb-2" name="gst_number" placeholder="GST number"><input class="form-control mb-2" name="license_number" placeholder="License number"><input class="form-control mb-2" name="contact_person" placeholder="Contact person"><textarea class="form-control mb-2" name="address" placeholder="Address"></textarea><textarea class="form-control mb-2" name="factory_details" placeholder="Factory details"></textarea><textarea class="form-control mb-2" name="product_categories" placeholder="Product categories"></textarea><textarea class="form-control mb-2" name="authorized_retailers" placeholder="Authorized retailers"></textarea><textarea class="form-control mb-3" name="authorized_distributors" placeholder="Authorized distributors"></textarea><button class="btn btn-primary w-100">Save Company</button></form></div></div></div><?php endif; ?>
<div class="<?php echo can_access_module('company_manage') ? 'col-lg-8' : 'col-lg-12'; ?>"><div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Companies</h2></div><div class="app-card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Company</th><th>License</th><th>Owner</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($companies as $company): ?><tr><td><?php echo e($company['company_name']); ?><br><small><?php echo e($company['gst_number']); ?></small></td><td><?php echo e($company['license_number']); ?></td><td><?php echo e($company['username'] ?: 'N/A'); ?></td><td><span class="badge bg-secondary"><?php echo e($company['company_status']); ?></span></td><td><?php if (role_alias() === 'super_admin'): ?><form method="POST" class="d-flex gap-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="approve"><input type="hidden" name="company_id" value="<?php echo (int) $company['id']; ?>"><select class="form-select form-select-sm" name="status"><option>approved</option><option>rejected</option><option>suspended</option></select><button class="btn btn-sm btn-outline-primary">Update</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div></div></section><?php include __DIR__ . '/../includes/footer.php'; ?>
