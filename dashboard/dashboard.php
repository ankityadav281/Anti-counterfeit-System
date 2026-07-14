<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_login();

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$username = $_SESSION['username'];
$role = role_label($_SESSION['role']);
$total_verifications = 0;
$total_products = 0;
$total_manufacturers = 0;
$suspicious_checks = 0;
$recent_verifications = [];
$recent_activities = [];
$dashboard_notifications = [];
$pending_requests = [];
$recent_messages = [];
$profile_summary = [];
$last_login = null;
$today_stats = [];
$role_key = role_alias($_SESSION['role']);
$role_dashboards = [
    'super_admin' => ['title' => 'Super Admin Command Center', 'summary' => 'Companies, users, analytics, logs, fraud, complaints, security alerts, announcements, backup, and search.', 'actions' => [['Companies',page_url('companies'),'fa-building'],['Approvals',page_url('workflows'),'fa-list-check'],['Fraud Reports',page_url('analytics'),'fa-shield-virus'],['Audit Logs',page_url('audit'),'fa-link'],['Global Search',page_url('global_search'),'fa-search'],['Reports',page_url('business_reports'),'fa-file-lines'],['REST API',page_url('api'),'fa-code'],['System Health',page_url('system_health'),'fa-heart-pulse']]],
    'manufacturer' => ['title' => 'Manufacturer Operations Dashboard', 'summary' => 'Products, QR codes, batches, dispatch, customers, locations, sales, recalls, warranties, complaints, and distributor communication.', 'actions' => [['Register Product',page_url('register_product'),'fa-plus'],['Manage Products',page_url('products'),'fa-box'],['Batches',page_url('batches'),'fa-layer-group'],['Inventory',page_url('inventory'),'fa-boxes-stacked'],['Recalls',page_url('recalls'),'fa-bullhorn'],['Warranty',page_url('warranty'),'fa-screwdriver-wrench'],['Reports',page_url('business_reports') . '?type=manufacturer','fa-file-lines'],['Messages',page_url('messages'),'fa-envelope']]],
    'distributor' => ['title' => 'Distributor Fulfillment Dashboard', 'summary' => 'Receive shipments, acknowledge inventory, transfer stock, track delivery history, orders, invoices, and manufacturer communication.', 'actions' => [['Approvals',page_url('workflows'),'fa-list-check'],['Messages',page_url('messages'),'fa-envelope']]],
    'warehouse_manager' => ['title' => 'Warehouse Control Dashboard', 'summary' => 'Inventory receipt, quality inspection, damaged stock rejection, low stock, expired products, transfers, and warehouse reports.', 'actions' => [['Inventory',page_url('inventory'),'fa-warehouse'],['Supply Chain',page_url('supply_chain'),'fa-truck-ramp-box'],['Approvals',page_url('workflows'),'fa-list-check'],['Reports',page_url('business_reports') . '?type=inventory','fa-file-lines']]],
    'retailer' => ['title' => 'Retailer Sales Dashboard', 'summary' => 'Receive products, sell stock, register purchases, generate invoices, handle returns, warranty requests, and customer complaints.', 'actions' => [['Inventory',page_url('inventory'),'fa-store'],['Ownership',page_url('ownership'),'fa-user-shield'],['Warranty',page_url('warranty'),'fa-screwdriver-wrench'],['Messages',page_url('messages'),'fa-envelope'],['Reports',page_url('business_reports') . '?type=retailer','fa-file-lines']]],
    'customer' => ['title' => 'Customer Product Center', 'summary' => 'Verify products, manage purchases, ownership, warranty, invoices, complaints, announcements, favorites, and security alerts.', 'actions' => [['Verify Product',page_url('verify'),'fa-barcode'],['Ownership',page_url('ownership'),'fa-user-shield'],['Warranty',page_url('warranty'),'fa-screwdriver-wrench'],['Complaint',page_url('complaints'),'fa-triangle-exclamation'],['Profile',page_url('profile'),'fa-user']]],
    'auditor' => ['title' => 'Auditor Compliance Dashboard', 'summary' => 'Read-only reports, supply-chain audit, fraud analysis, risk assessment, audit logs, and compliance exports.', 'actions' => [['Audit Logs',page_url('audit'),'fa-link'],['Analytics',page_url('analytics'),'fa-chart-line'],['Global Search',page_url('global_search'),'fa-search'],['Reports',page_url('business_reports'),'fa-file-lines'],['Supply Chain',page_url('supply_chain'),'fa-truck-fast']]],
];
$role_dashboard = $role_dashboards[$role_key] ?? $role_dashboards['customer'];

try {
    $db->exec("CREATE TABLE IF NOT EXISTS suspicious_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_code VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $total_verifications = (int) $db->query("SELECT COUNT(*) FROM product_verifications")->fetchColumn();
    $total_products = (int) $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $total_manufacturers = (int) $db->query("SELECT COUNT(*) FROM manufacturers")->fetchColumn();
    $suspicious_checks = (int) $db->query("SELECT COUNT(*) FROM suspicious_verifications")->fetchColumn();

    $recent_query = "SELECT pv.*, p.name AS product_name, p.product_code, m.name AS manufacturer_name
        FROM product_verifications pv
        JOIN products p ON pv.product_id = p.id
        LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
        ORDER BY pv.verification_date DESC
        LIMIT 8";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute();
    $recent_verifications = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

    $activity_stmt = $db->prepare("SELECT * FROM activity_logs WHERE user_id = :user_id OR :role IN ('super_admin','auditor') ORDER BY created_at DESC LIMIT 6");
    $activity_stmt->execute([':user_id' => $_SESSION['user_id'], ':role' => $role_key]);
    $recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

    $notification_stmt = $db->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = :user_id ORDER BY created_at DESC LIMIT 5");
    $notification_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $dashboard_notifications = $notification_stmt->fetchAll(PDO::FETCH_ASSOC);

    $request_stmt = $db->prepare("SELECT * FROM workflow_requests WHERE status = 'pending' AND (requester_id = :user_id OR approver_role = :role OR :role = 'super_admin') ORDER BY created_at DESC LIMIT 5");
    $request_stmt->execute([':user_id' => $_SESSION['user_id'], ':role' => $role_key]);
    $pending_requests = $request_stmt->fetchAll(PDO::FETCH_ASSOC);

    $message_stmt = $db->prepare("SELECT bm.*, u.username AS sender_name FROM business_messages bm JOIN users u ON u.id = bm.sender_id WHERE (bm.receiver_id = :user_id OR bm.receiver_role = :role) AND bm.deleted_by_receiver = 0 ORDER BY bm.created_at DESC LIMIT 5");
    $message_stmt->execute([':user_id' => $_SESSION['user_id'], ':role' => $role_key]);
    $recent_messages = $message_stmt->fetchAll(PDO::FETCH_ASSOC);

    $profile_stmt = $db->prepare("SELECT up.*, u.email FROM user_profiles up JOIN users u ON u.id = up.user_id WHERE up.user_id = :user_id LIMIT 1");
    $profile_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $profile_summary = $profile_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $login_stmt = $db->prepare("SELECT * FROM login_history WHERE user_id = :user_id AND success = 1 ORDER BY created_at DESC LIMIT 1");
    $login_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $last_login = $login_stmt->fetch(PDO::FETCH_ASSOC);

    $unread_stmt = $db->prepare("SELECT COUNT(*) FROM business_messages WHERE (receiver_id = :user_id OR receiver_role = :role) AND is_read = 0");
    $unread_stmt->execute([':user_id' => $_SESSION['user_id'], ':role' => $role_key]);
    $today_stats = [
        'Verifications Today' => (int) $db->query("SELECT COUNT(*) FROM product_verifications WHERE DATE(verification_date) = CURDATE()")->fetchColumn(),
        'Pending Requests' => count($pending_requests),
        'Unread Messages' => (int) $unread_stmt->fetchColumn(),
    ];
} catch (PDOException $e) {
    $error = "Unable to load dashboard data.";
}

$page_title = 'Dashboard - Anti-Counterfeit System';
$active_page = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 mb-1"><?php echo e($role_dashboard['title']); ?></h1>
                <p class="text-muted mb-0">Welcome, <?php echo e($username); ?>. <?php echo e($role_dashboard['summary']); ?></p>
            </div>
            <?php if (is_manufacturer_area_allowed()): ?>
                <a href="<?php echo e(page_url('register_product')); ?>" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Register Product</a>
            <?php else: ?>
                <a href="<?php echo e(page_url('complaints')); ?>" class="btn btn-primary"><i class="fas fa-triangle-exclamation me-2"></i>Submit Complaint</a>
            <?php endif; ?>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="stat-card"><span>Total verifications</span><strong><?php echo $total_verifications; ?></strong></div></div>
            <div class="col-md-3"><div class="stat-card"><span>Registered products</span><strong><?php echo $total_products; ?></strong></div></div>
            <div class="col-md-3"><div class="stat-card"><span>Manufacturers</span><strong><?php echo $total_manufacturers; ?></strong></div></div>
            <div class="col-md-3"><div class="stat-card"><span>Suspicious checks</span><strong><?php echo $suspicious_checks; ?></strong></div></div>
        </div>
        <div class="row g-3 mb-4">
            <?php foreach ($role_dashboard['actions'] as $action): ?>
                <div class="col-md-3"><a class="module-tile" href="<?php echo e($action[1]); ?>"><i class="fas <?php echo e($action[2]); ?>"></i><span><?php echo e($action[0]); ?></span></a></div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="app-card h-100">
                    <div class="app-card-header"><h2 class="h5 mb-0">Profile Summary</h2></div>
                    <div class="app-card-body">
                        <strong><?php echo e($profile_summary['full_name'] ?? $username); ?></strong>
                        <p class="text-muted mb-2"><?php echo e(($profile_summary['company_name'] ?? 'No company') . ' · ' . ($profile_summary['designation'] ?? $role)); ?></p>
                        <div class="progress mb-3"><div class="progress-bar" style="width: <?php echo (int) ($profile_summary['profile_completion'] ?? 0); ?>%"><?php echo (int) ($profile_summary['profile_completion'] ?? 0); ?>%</div></div>
                        <small class="text-muted">Last login: <?php echo e($last_login['created_at'] ?? 'Not recorded'); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="app-card h-100">
                    <div class="app-card-header"><h2 class="h5 mb-0">Today's Statistics</h2></div>
                    <div class="app-card-body">
                        <?php foreach ($today_stats as $label => $value): ?>
                            <div class="d-flex justify-content-between border-bottom py-2"><span><?php echo e($label); ?></span><strong><?php echo (int) $value; ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="app-card h-100">
                    <div class="app-card-header"><h2 class="h5 mb-0">System Status</h2></div>
                    <div class="app-card-body">
                        <div class="d-flex justify-content-between border-bottom py-2"><span>Database</span><span class="badge bg-success">Online</span></div>
                        <div class="d-flex justify-content-between border-bottom py-2"><span>Role Mode</span><span class="badge bg-primary"><?php echo e($role); ?></span></div>
                        <div class="d-flex justify-content-between py-2"><span>Session</span><span class="badge bg-success">Active</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="app-card">
                    <div class="app-card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Recent Verifications</h2>
                        <a href="<?php echo e(page_url('reports')); ?>" class="btn btn-sm btn-outline-primary">View Report</a>
                    </div>
                    <div class="app-card-body">
                        <?php if (!$recent_verifications): ?>
                            <p class="text-muted mb-0">No verifications yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Product</th><th>Code</th><th>Manufacturer</th><th>Date</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($recent_verifications as $verification): ?>
                                            <tr>
                                                <td><?php echo e($verification['product_name']); ?></td>
                                                <td><span class="code-badge"><?php echo e($verification['product_code']); ?></span></td>
                                                <td><?php echo e($verification['manufacturer_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($verification['verification_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Quick Actions</h2></div>
                    <div class="app-card-body d-grid gap-2">
                        <?php foreach (array_slice($role_dashboard['actions'], 0, 5) as $action): ?>
                            <a href="<?php echo e($action[1]); ?>" class="btn btn-outline-primary"><i class="fas <?php echo e($action[2]); ?> me-2"></i><?php echo e($action[0]); ?></a>
                        <?php endforeach; ?>
                        <div class="alert alert-info mb-0 mt-2">Role: <strong><?php echo e($role); ?></strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-lg-3"><div class="app-card h-100"><div class="app-card-header"><h2 class="h6 mb-0">Recent Activities</h2></div><div class="app-card-body"><?php foreach ($recent_activities as $item): ?><div class="border-bottom py-2"><strong><?php echo e($item['activity']); ?></strong><br><small class="text-muted"><?php echo e($item['created_at']); ?></small></div><?php endforeach; ?><?php if (!$recent_activities): ?><p class="text-muted mb-0">No recent activity.</p><?php endif; ?></div></div></div>
            <div class="col-lg-3"><div class="app-card h-100"><div class="app-card-header"><h2 class="h6 mb-0">Notifications</h2></div><div class="app-card-body"><?php foreach ($dashboard_notifications as $item): ?><div class="border-bottom py-2"><strong><?php echo e($item['title']); ?></strong><br><small class="text-muted"><?php echo e($item['created_at']); ?></small></div><?php endforeach; ?><?php if (!$dashboard_notifications): ?><p class="text-muted mb-0">No notifications.</p><?php endif; ?></div></div></div>
            <div class="col-lg-3"><div class="app-card h-100"><div class="app-card-header"><h2 class="h6 mb-0">Pending Requests</h2></div><div class="app-card-body"><?php foreach ($pending_requests as $item): ?><div class="border-bottom py-2"><strong><?php echo e($item['title']); ?></strong><br><small class="text-muted"><?php echo e($item['request_type']); ?></small></div><?php endforeach; ?><?php if (!$pending_requests): ?><p class="text-muted mb-0">No pending requests.</p><?php endif; ?></div></div></div>
            <div class="col-lg-3"><div class="app-card h-100"><div class="app-card-header"><h2 class="h6 mb-0">Recent Messages</h2></div><div class="app-card-body"><?php foreach ($recent_messages as $item): ?><div class="border-bottom py-2"><strong><?php echo e($item['subject']); ?></strong><br><small class="text-muted">From <?php echo e($item['sender_name']); ?></small></div><?php endforeach; ?><?php if (!$recent_messages): ?><p class="text-muted mb-0">No recent messages.</p><?php endif; ?></div></div></div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
