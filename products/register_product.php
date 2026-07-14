<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_role(['admin', 'manufacturer']);

$success_message = '';
$error_message = '';
$product_code = '';
$qr_payload_text = '';
$created_product = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $product_name = trim($_POST['product_name'] ?? '');
    $batch_number = trim($_POST['batch_number'] ?? '');
    $manufacturing_date = $_POST['manufacturing_date'] ?? '';
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $description = trim($_POST['description'] ?? '');
    $manufacturer_id = 1;

    if ($product_name === '' || $batch_number === '' || $manufacturing_date === '') {
        $error_message = "Product name, batch number, and manufacturing date are required.";
    } elseif ($expiry_date && $expiry_date < $manufacturing_date) {
        $error_message = "Expiry date cannot be earlier than manufacturing date.";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            enterprise_bootstrap($db);
            $dup = $db->prepare("SELECT id FROM products WHERE manufacturer_id = :manufacturer_id AND batch_number = :batch_number AND name = :name LIMIT 1");
            $dup->execute([':manufacturer_id' => $manufacturer_id, ':batch_number' => $batch_number, ':name' => $product_name]);
            if ($dup->fetch()) {
                $error_message = "Duplicate product for this manufacturer and batch already exists.";
            } else {
            $product_code = "PRD-" . date("Y") . "-" . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $timestamp = date('c');
            $product_hash = product_secure_hash($product_code, $manufacturer_id, $timestamp);
            $signature = digital_signature($product_hash);
            $qr_payload_text = qr_payload($product_code, $manufacturer_id, $timestamp, $product_hash, $signature);

            $query = "INSERT INTO products
                (manufacturer_id, name, product_code, batch_number, manufacturing_date, expiry_date, description, product_hash, digital_signature, lifecycle_status)
                VALUES (:manufacturer_id, :name, :product_code, :batch_number, :manufacturing_date, :expiry_date, :description, :product_hash, :digital_signature, 'Manufactured')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":manufacturer_id", $manufacturer_id);
            $stmt->bindParam(":name", $product_name);
            $stmt->bindParam(":product_code", $product_code);
            $stmt->bindParam(":batch_number", $batch_number);
            $stmt->bindParam(":manufacturing_date", $manufacturing_date);
            $stmt->bindParam(":expiry_date", $expiry_date);
            $stmt->bindParam(":description", $description);
            $stmt->bindParam(":product_hash", $product_hash);
            $stmt->bindParam(":digital_signature", $signature);
            $stmt->execute();
            $product_id = (int) $db->lastInsertId();

            $qr_stmt = $db->prepare("INSERT INTO qr_codes (product_id, qr_payload, secure_hash, digital_signature) VALUES (:product_id, :payload, :hash, :signature)");
            $qr_stmt->execute([':product_id' => $product_id, ':payload' => $qr_payload_text, ':hash' => $product_hash, ':signature' => $signature]);

            $history = $db->prepare("INSERT INTO product_history (product_id, stage, location, handled_by, notes) VALUES (:product_id, 'Manufactured', 'Manufacturer', :handled_by, 'Product registered')");
            $history->execute([':product_id' => $product_id, ':handled_by' => $_SESSION['user_id'] ?? null]);

            $inventory = $db->prepare("INSERT IGNORE INTO inventory (product_id, location_type, location_name, batch_number, quantity, expiry_date) VALUES (:product_id, 'manufacturer', 'Main Manufacturer Stock', :batch_number, 1, :expiry_date)");
            $inventory->execute([':product_id' => $product_id, ':batch_number' => $batch_number, ':expiry_date' => $expiry_date]);

            audit_log($db, 'product', $product_id, $product_id, null, 'Manufactured', 'Manufacturer', ['product_code' => $product_code]);

            $success_message = "Product registered successfully.";
            $created_product = [
                'name' => $product_name,
                'batch_number' => $batch_number,
                'manufacturing_date' => $manufacturing_date,
                'expiry_date' => $expiry_date,
                'description' => $description,
            ];
            }
        } catch (PDOException $e) {
            $error_message = "Product registration failed. Please try again.";
        } catch (Exception $e) {
            $error_message = "Could not generate a secure product code.";
        }
    }
}

$page_title = 'Register Product - Anti-Counterfeit System';
$active_page = 'products';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="app-card">
                    <div class="app-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h1 class="h3 mb-1"><i class="fas fa-box-open me-2 text-primary"></i>Register Product</h1>
                            <p class="text-muted mb-0">Create a product identity and generate a QR label.</p>
                        </div>
                        <a href="products.php" class="btn btn-outline-primary">View Products</a>
                    </div>
                    <div class="app-card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success"><?php echo e($success_message); ?></div>
                        <?php endif; ?>
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo e($error_message); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="register_product.php">
                            <?php echo csrf_field(); ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="product_name" class="form-label">Product Name</label>
                                    <input type="text" class="form-control" id="product_name" name="product_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="batch_number" class="form-label">Batch Number</label>
                                    <input type="text" class="form-control" id="batch_number" name="batch_number" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="manufacturing_date" class="form-label">Manufacturing Date</label>
                                    <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="expiry_date" class="form-label">Expiry Date</label>
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Register Product</button>
                        </form>

                        <?php if ($product_code): ?>
                            <div class="app-card mt-4">
                                <div class="app-card-body">
                                    <div class="row g-4 align-items-center">
                                        <div class="col-md-7">
                                            <h2 class="h5">Registration Complete</h2>
                                            <p><strong>Product:</strong> <?php echo e($created_product['name']); ?></p>
                                            <p><strong>Batch:</strong> <?php echo e($created_product['batch_number']); ?></p>
                                            <p><strong>Code:</strong> <span class="code-badge"><?php echo e($product_code); ?></span></p>
                                            <button class="btn btn-outline-primary btn-sm" type="button" data-copy="<?php echo e($product_code); ?>">
                                                <i class="fas fa-copy me-1"></i> Copy Code
                                            </button>
                                        </div>
                                        <div class="col-md-5 text-center">
                                            <div id="qrcode" class="d-inline-block bg-white p-2 border rounded"></div>
                                            <div class="mt-3">
                                                <button class="btn btn-sm btn-primary" id="downloadQR" type="button"><i class="fas fa-download me-1"></i>Download QR</button>
                                                <button class="btn btn-sm btn-outline-secondary" id="printQR" type="button"><i class="fas fa-print me-1"></i>Print</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<?php if ($product_code): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new QRCode(document.getElementById("qrcode"), {
        text: <?php echo json_encode($qr_payload_text ?: $product_code); ?>,
        width: 180,
        height: 180
    });

    document.getElementById('downloadQR').addEventListener('click', function () {
        const img = document.querySelector('#qrcode img') || document.querySelector('#qrcode canvas');
        const url = img.tagName === 'IMG' ? img.src : img.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = url;
        a.download = 'product-qr-<?php echo e($product_code); ?>.png';
        a.click();
    });

    document.getElementById('printQR').addEventListener('click', function () {
        window.print();
    });
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
