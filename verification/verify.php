<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_login();

$verification_result = null;
$verification_count = 0;
$error = '';
$success = '';
$submitted_code = '';
$risk = ['score' => 0, 'level' => 'Low', 'reasons' => []];
$verification_status = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $raw_code = trim($_POST['product_code'] ?? '');
    $payload = parse_qr_or_code($raw_code);
    $submitted_code = strtoupper(trim($payload['product_id'] ?? ''));
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $latitude = ($_POST['latitude'] ?? '') !== '' ? $_POST['latitude'] : null;
    $longitude = ($_POST['longitude'] ?? '') !== '' ? $_POST['longitude'] : null;

    if ($submitted_code === '') {
        $error = "Please enter a product code.";
    } else {
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

            $query = "SELECT p.*, m.name AS manufacturer_name
                FROM products p
                LEFT JOIN manufacturers m ON p.manufacturer_id = m.id
                WHERE p.product_code = :product_code
                LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":product_code", $submitted_code);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $is_qr_valid = true;
                if (isset($payload['secure_hash'], $payload['digital_signature'], $payload['manufacturer_id'], $payload['timestamp'])) {
                    $expected_hash = product_secure_hash($submitted_code, $payload['manufacturer_id'], $payload['timestamp']);
                    $expected_signature = digital_signature($expected_hash);
                    $is_qr_valid = hash_equals($expected_hash, $payload['secure_hash']) && hash_equals($expected_signature, $payload['digital_signature']);
                }

                $count_query = "SELECT COUNT(*) FROM product_verifications WHERE product_id = :product_id";
                $count_stmt = $db->prepare($count_query);
                $count_stmt->bindParam(":product_id", $product['id']);
                $count_stmt->execute();
                $verification_count = (int) $count_stmt->fetchColumn() + 1;
                $risk = assess_fraud($db, $product, $city, $state, $country, client_ip());
                $verification_status = 'Genuine Product';

                if (!$is_qr_valid) {
                    $verification_status = 'Invalid QR Code';
                } elseif (($product['lifecycle_status'] ?? '') === 'Sold') {
                    $verification_status = 'Already Sold';
                } elseif (($product['lifecycle_status'] ?? '') === 'Ownership Changed') {
                    $verification_status = 'Ownership Changed';
                } elseif ($risk['level'] === 'High') {
                    $verification_status = 'Counterfeit Product';
                }

                $log_query = "INSERT INTO product_verifications
                    (product_id, verification_date, ip_address, user_agent, status, latitude, longitude, city, state, country, risk_score)
                    VALUES (:product_id, NOW(), :ip_address, :user_agent, :status, :latitude, :longitude, :city, :state, :country, :risk_score)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(":product_id", $product['id']);
                $log_stmt->bindValue(":ip_address", client_ip());
                $log_stmt->bindValue(":user_agent", $_SERVER['HTTP_USER_AGENT'] ?? '');
                $log_stmt->bindParam(":status", $verification_status);
                $log_stmt->bindParam(":latitude", $latitude);
                $log_stmt->bindParam(":longitude", $longitude);
                $log_stmt->bindParam(":city", $city);
                $log_stmt->bindParam(":state", $state);
                $log_stmt->bindParam(":country", $country);
                $log_stmt->bindValue(":risk_score", $risk['score']);
                $log_stmt->execute();

                $verification_result = $product;
                if (in_array($verification_status, ['Counterfeit Product', 'Invalid QR Code'], true)) {
                    $error = $verification_status . ". This scan has been flagged for review.";
                    create_notification($db, 'fraud', $verification_status, 'Suspicious scan for ' . $submitted_code . ' from ' . (client_ip()), null);
                    $flag = $db->prepare("UPDATE products SET flagged = 1 WHERE id = :id");
                    $flag->execute([':id' => $product['id']]);
                } else {
                    $success = $verification_status . ".";
                }
                audit_log($db, 'verification', null, $product['id'], null, $verification_status, trim($city . ', ' . $state . ', ' . $country, ', '), ['risk' => $risk]);
                if (role_alias() === 'customer' && in_array($verification_status, ['Genuine Product', 'Already Sold'], true)) {
                    $exists = $db->prepare("SELECT id FROM customer_purchases WHERE product_id = :product_id AND customer_user_id = :user_id LIMIT 1");
                    $exists->execute([':product_id' => $product['id'], ':user_id' => $_SESSION['user_id']]);
                    if (!$exists->fetch()) {
                        $user_stmt = $db->prepare("SELECT u.email, COALESCE(up.full_name, u.username) AS full_name, up.phone FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = :id");
                        $user_stmt->execute([':id' => $_SESSION['user_id']]);
                        $customer = $user_stmt->fetch(PDO::FETCH_ASSOC) ?: ['email' => '', 'full_name' => $_SESSION['username'], 'phone' => ''];
                        $qr_id_stmt = $db->prepare("SELECT id FROM qr_codes WHERE product_id = :id LIMIT 1");
                        $qr_id_stmt->execute([':id' => $product['id']]);
                        $invoice = 'AUTO-' . $product['product_code'] . '-' . date('YmdHis');
                        $purchase = $db->prepare("INSERT INTO customer_purchases
                            (product_id, qr_id, customer_user_id, customer_name, customer_email, phone, purchase_date, verification_location, invoice_number, warranty_status, ownership_status)
                            VALUES (:product_id, :qr_id, :customer_user_id, :customer_name, :customer_email, :phone, CURDATE(), :location, :invoice, 'Active', 'active')");
                        $purchase->execute([
                            ':product_id' => $product['id'],
                            ':qr_id' => $qr_id_stmt->fetchColumn() ?: null,
                            ':customer_user_id' => $_SESSION['user_id'],
                            ':customer_name' => $customer['full_name'],
                            ':customer_email' => $customer['email'],
                            ':phone' => $customer['phone'],
                            ':location' => trim($city . ', ' . $state . ', ' . $country, ', '),
                            ':invoice' => $invoice,
                        ]);
                        $own = $db->prepare("INSERT IGNORE INTO ownership (product_id, customer_user_id, customer_name, purchase_date, invoice_number) VALUES (:product_id, :user_id, :name, CURDATE(), :invoice)");
                        $own->execute([':product_id' => $product['id'], ':user_id' => $_SESSION['user_id'], ':name' => $customer['full_name'], ':invoice' => $invoice]);
                        $db->prepare("UPDATE products SET lifecycle_status = 'Verified', warranty_start = CURDATE(), warranty_expiry = DATE_ADD(CURDATE(), INTERVAL warranty_period_months MONTH) WHERE id = :id AND lifecycle_status NOT IN ('Ownership Changed')")
                            ->execute([':id' => $product['id']]);
                        create_notification($db, 'purchase', 'New customer verified', $customer['full_name'] . ' verified ' . $product['product_code'], null);
                        activity_log($db, 'Customer purchase auto-created', 'product', $product['id'], 'success', trim($city . ', ' . $state . ', ' . $country, ', '));
                    }
                }
            } else {
                $log_bad_query = "INSERT INTO suspicious_verifications (product_code, ip_address, user_agent)
                    VALUES (:product_code, :ip_address, :user_agent)";
                $log_bad_stmt = $db->prepare($log_bad_query);
                $log_bad_stmt->bindParam(":product_code", $submitted_code);
                $log_bad_stmt->bindValue(":ip_address", client_ip());
                $log_bad_stmt->bindValue(":user_agent", $_SERVER['HTTP_USER_AGENT'] ?? '');
                $log_bad_stmt->execute();
                $risk = assess_fraud($db, null, $city, $state, $country, client_ip());
                create_notification($db, 'counterfeit', 'Counterfeit product detected', 'Invalid product code scanned: ' . $submitted_code, null);

                $error = "Product code not found. Treat this product as suspicious.";
            }
        } catch (PDOException $e) {
            $error = "Verification failed. Please check your database setup.";
        }
    }
}

$page_title = 'Verify Product - Anti-Counterfeit System';
$active_page = 'verify';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="app-card">
                    <div class="app-card-header">
                        <h1 class="h3 mb-1"><i class="fas fa-barcode me-2 text-primary"></i>Verify Product Authenticity</h1>
                        <p class="text-muted mb-0">Check whether a product code exists in the official database.</p>
                    </div>
                    <div class="app-card-body">
                        <form method="POST" action="verify.php" class="row g-2 align-items-end">
                            <?php echo csrf_field(); ?>
                            <div class="col-md-9">
                                <label for="product_code" class="form-label">Product Code</label>
                                <input type="text" class="form-control form-control-lg" id="product_code" name="product_code" value="<?php echo e($submitted_code); ?>" placeholder="Enter product code" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-lg w-100">Verify</button>
                            </div>
                            <input type="hidden" id="latitude" name="latitude">
                            <input type="hidden" id="longitude" name="longitude">
                            <input type="hidden" id="city" name="city">
                            <input type="hidden" id="state" name="state">
                            <input type="hidden" id="country" name="country">
                        </form>

                        <div class="mt-4">
                            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#qrScannerPanel">
                                <i class="fas fa-qrcode me-2"></i>Scan QR Code
                            </button>
                            <div class="collapse mt-3" id="qrScannerPanel">
                                <div class="alert alert-info">Allow camera permission, then place the product QR code inside the scanner.</div>
                                <div id="qr-reader" class="border rounded bg-light" style="width:100%; max-width:420px;"></div>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger mt-4">
                                <span class="status-pill danger"><i class="fas fa-triangle-exclamation"></i> Suspicious</span>
                                <p class="mb-0 mt-3"><?php echo e($error); ?></p>
                                <p class="mb-0 mt-2"><strong>Fraud Risk:</strong> <?php echo e($risk['level']); ?> (<?php echo (int) $risk['score']; ?>)</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($success && $verification_result): ?>
                            <div class="alert alert-success mt-4">
                                <span class="status-pill success"><i class="fas fa-circle-check"></i> Authentic</span>
                                <p class="mb-0 mt-3"><?php echo e($success); ?> This check has been recorded.</p>
                            </div>

                            <div class="row g-4 mt-1">
                                <div class="col-md-7">
                                    <h2 class="h5">Product Details</h2>
                                    <table class="table">
                                        <tr><th>Name</th><td><?php echo e($verification_result['name']); ?></td></tr>
                                        <tr><th>Manufacturer</th><td><?php echo e($verification_result['manufacturer_name'] ?? 'N/A'); ?></td></tr>
                                        <tr><th>Code</th><td><span class="code-badge"><?php echo e($verification_result['product_code']); ?></span></td></tr>
                                        <tr><th>Batch</th><td><?php echo e($verification_result['batch_number']); ?></td></tr>
                                        <tr><th>Manufactured</th><td><?php echo date('F d, Y', strtotime($verification_result['manufacturing_date'])); ?></td></tr>
                                        <tr><th>Expiry</th><td><?php echo $verification_result['expiry_date'] ? date('F d, Y', strtotime($verification_result['expiry_date'])) : 'N/A'; ?></td></tr>
                                        <tr><th>Total Checks</th><td><?php echo $verification_count; ?></td></tr>
                                        <tr><th>Verification Status</th><td><?php echo e($verification_status); ?></td></tr>
                                        <tr><th>Fraud Risk</th><td><?php echo e($risk['level']); ?> (<?php echo (int) $risk['score']; ?>)</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-5">
                                    <h2 class="h5">Consumer Guidance</h2>
                                    <p class="text-muted">Match the code, batch number, and product details with the physical packaging before purchase.</p>
                                    <?php if (!empty($verification_result['description'])): ?>
                                        <h3 class="h6">Description</h3>
                                        <p><?php echo nl2br(e($verification_result['description'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const scannerPanel = document.getElementById('qrScannerPanel');
    const productInput = document.getElementById('product_code');
    let scannerStarted = false;
    let scanner;

    if (scannerPanel && window.Html5Qrcode) {
        scannerPanel.addEventListener('shown.bs.collapse', function () {
            if (scannerStarted) {
                return;
            }

            scanner = new Html5Qrcode("qr-reader");
            scanner.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                function (decodedText) {
                    productInput.value = decodedText;
                    scanner.stop();
                    scannerStarted = false;
                    productInput.form.submit();
                }
            ).then(function () {
                scannerStarted = true;
            }).catch(function () {
                document.getElementById('qr-reader').innerHTML = '<div class="p-3 text-danger">Camera could not be started. Please type the product code manually.</div>';
            });
        });
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
        });
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
