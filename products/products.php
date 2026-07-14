<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_role(['admin', 'manufacturer']);

$search = trim($_GET['q'] ?? '');
$products = [];
$error = '';

try {
    $database = new Database();
    $db = $database->getConnection();
    enterprise_bootstrap($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
        verify_csrf();
        $product_id = (int) ($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            $delete_product = $db->prepare("UPDATE products SET archived_at = NOW(), lifecycle_status = 'Archived' WHERE id = :id");
            $delete_product->bindParam(":id", $product_id);
            $delete_product->execute();
            if (function_exists('audit_log')) {
                audit_log($db, 'product', $product_id, $product_id, null, 'Archived', '', []);
            }
        }
    }
    $query = "SELECT p.*, m.name AS manufacturer_name,
        (SELECT COUNT(*) FROM product_verifications pv WHERE pv.product_id = p.id) AS verification_count
        FROM products p
        LEFT JOIN manufacturers m ON p.manufacturer_id = m.id";
    $query .= " WHERE p.archived_at IS NULL";

    if ($search !== '') {
        $query .= " AND (p.name LIKE :search OR p.product_code LIKE :search OR p.batch_number LIKE :search)";
    }

    $query .= " ORDER BY p.created_at DESC";
    $stmt = $db->prepare($query);

    if ($search !== '') {
        $searchTerm = '%' . $search . '%';
        $stmt->bindParam(":search", $searchTerm);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Unable to load products.";
}

$page_title = 'Products - Anti-Counterfeit System';
$active_page = 'products';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 mb-1">Products</h1>
                <p class="text-muted mb-0">Search registered products and review verification counts.</p>
            </div>
            <a href="register_product.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Register Product</a>
        </div>

        <div class="app-card mb-4">
            <div class="app-card-body">
                <form method="GET" action="products.php" class="row g-2">
                    <div class="col-md-10">
                        <input type="search" class="form-control" name="q" value="<?php echo e($search); ?>" placeholder="Search by product, code, or batch">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-search me-1"></i>Search</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="app-card">
            <div class="app-card-body">
                <?php if (!$products): ?>
                    <p class="text-muted mb-0">No products found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Code</th>
                                    <th>Batch</th>
                                    <th>Manufacturer</th>
                                    <th>Expiry</th>
                                    <th>Checks</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo e($product['name']); ?></td>
                                        <td><span class="code-badge"><?php echo e($product['product_code']); ?></span></td>
                                        <td><?php echo e($product['batch_number']); ?></td>
                                        <td><?php echo e($product['manufacturer_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $product['expiry_date'] ? date('M d, Y', strtotime($product['expiry_date'])) : 'N/A'; ?></td>
                                        <td><?php echo (int) $product['verification_count']; ?></td>
                                        <td class="text-end text-nowrap">
                                            <a href="edit_product.php?id=<?php echo (int) $product['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                            <form method="POST" action="<?php echo e(page_url('verify')); ?>" class="d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="product_code" value="<?php echo e($product['product_code']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Verify</button>
                                            </form>
                                            <form method="POST" action="products.php" class="d-inline" onsubmit="return confirm('Delete this product?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
