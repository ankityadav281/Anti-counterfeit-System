<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('business_reports');

$recent_verifications = [];
$suspicious = [];
$error = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    enterprise_bootstrap($db);
    $db->exec("CREATE TABLE IF NOT EXISTS suspicious_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_code VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $db->prepare("SELECT pv.*, p.name AS product_name, p.product_code
        FROM product_verifications pv
        JOIN products p ON pv.product_id = p.id
        ORDER BY pv.verification_date DESC
        LIMIT 25");
    $stmt->execute();
    $recent_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $bad_stmt = $db->prepare("SELECT * FROM suspicious_verifications ORDER BY attempted_at DESC LIMIT 25");
    $bad_stmt->execute();
    $suspicious = $bad_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Unable to load reports.";
}

$page_title = 'Reports - Anti-Counterfeit System';
$active_page = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="mb-4">
            <h1 class="h2 mb-1">Verification Reports</h1>
            <p class="text-muted mb-0">Review valid product checks and suspicious product-code attempts.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Valid Verifications</h2></div>
                    <div class="app-card-body">
                        <?php if (!$recent_verifications): ?>
                            <p class="text-muted mb-0">No valid verifications recorded.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Product</th><th>Code</th><th>IP</th><th>Date</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($recent_verifications as $row): ?>
                                            <tr>
                                                <td><?php echo e($row['product_name']); ?></td>
                                                <td><?php echo e($row['product_code']); ?></td>
                                                <td><?php echo e($row['ip_address']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($row['verification_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Suspicious Attempts</h2></div>
                    <div class="app-card-body">
                        <?php if (!$suspicious): ?>
                            <p class="text-muted mb-0">No suspicious attempts recorded.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Code</th><th>IP</th><th>Date</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($suspicious as $row): ?>
                                            <tr>
                                                <td><span class="code-badge"><?php echo e($row['product_code']); ?></span></td>
                                                <td><?php echo e($row['ip_address']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($row['attempted_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
