<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_role(['admin', 'manufacturer']);

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$id = (int) ($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($id <= 0) {
    header("Location: products.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $batch_number = trim($_POST['batch_number'] ?? '');
    $manufacturing_date = $_POST['manufacturing_date'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?: null;
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $batch_number === '' || $manufacturing_date === '') {
        $error = "Product name, batch number, and manufacturing date are required.";
    } else {
        $stmt = $db->prepare("UPDATE products SET name = :name, batch_number = :batch_number, manufacturing_date = :manufacturing_date, expiry_date = :expiry_date, description = :description WHERE id = :id");
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":batch_number", $batch_number);
        $stmt->bindParam(":manufacturing_date", $manufacturing_date);
        $stmt->bindParam(":expiry_date", $expiry_date);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        audit_log($db, 'product', $id, $id, 'Product Updated', 'Product Updated', '', ['name' => $name, 'batch_number' => $batch_number]);
        $success = "Product updated successfully.";
    }
}

$stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
$stmt->bindParam(":id", $id);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit();
}

$page_title = 'Edit Product - Anti-Counterfeit System';
$active_page = 'products';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="app-card">
                    <div class="app-card-header">
                        <h1 class="h3 mb-1">Edit Product</h1>
                        <p class="text-muted mb-0"><span class="code-badge"><?php echo e($product['product_code']); ?></span></p>
                    </div>
                    <div class="app-card-body">
                        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
                        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label" for="name">Product Name</label>
                                <input class="form-control" id="name" name="name" value="<?php echo e($product['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="batch_number">Batch Number</label>
                                <input class="form-control" id="batch_number" name="batch_number" value="<?php echo e($product['batch_number']); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="manufacturing_date">Manufacturing Date</label>
                                    <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" value="<?php echo e($product['manufacturing_date']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="expiry_date">Expiry Date</label>
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?php echo e($product['expiry_date']); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($product['description']); ?></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" type="submit">Save Changes</button>
                                <a class="btn btn-outline-secondary" href="products.php">Back</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
