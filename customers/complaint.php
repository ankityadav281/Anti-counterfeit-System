<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_module('complaints');

$success = '';
$error = '';
$complaints = [];
$selected_complaint = null;
$is_customer = role_alias() === 'customer';
$can_review_complaints = can_access_module('complaint_manage');
$can_view_complaints = can_access_module('complaints');
$selected_id = (int) ($_GET['id'] ?? 0);

function complaint_status_badge($status) {
    if ($status === 'closed') {
        return 'bg-success';
    }

    if ($status === 'reviewing') {
        return 'bg-info text-dark';
    }

    return 'bg-warning text-dark';
}

try {
    $database = new Database();
    $db = $database->getConnection();
    enterprise_bootstrap($db);
    $db->exec("CREATE TABLE IF NOT EXISTS complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        product_code VARCHAR(100),
        product_name VARCHAR(255) NOT NULL,
        seller_name VARCHAR(255),
        purchase_location VARCHAR(255),
        description TEXT NOT NULL,
        status ENUM('open', 'reviewing', 'resolved', 'closed') DEFAULT 'open',
        manufacturer_reply TEXT,
        closed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("ALTER TABLE complaints MODIFY status ENUM('open', 'reviewing', 'resolved', 'closed') DEFAULT 'open'");
    $db->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS manufacturer_reply TEXT");
    $db->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP NULL DEFAULT NULL");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_complaint') {
        if (!$can_review_complaints) {
            header("Location: complaint.php");
            exit();
        }

        $complaint_id = (int) ($_POST['complaint_id'] ?? 0);
        $reply = trim($_POST['manufacturer_reply'] ?? '');

        if ($complaint_id > 0) {
            $new_status = $_POST['status'] ?? 'closed';
            if (!in_array($new_status, ['reviewing', 'resolved', 'closed'], true)) {
                $new_status = 'closed';
            }
            $query = "UPDATE complaints
                SET status = :status, manufacturer_reply = :reply, closed_at = IF(:status = 'closed', NOW(), closed_at)
                WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":status", $new_status);
            $stmt->bindParam(":reply", $reply);
            $stmt->bindParam(":id", $complaint_id);
            $stmt->execute();
            audit_log($db, 'complaint', $complaint_id, null, null, $new_status, '', []);

            header("Location: complaint.php?id=" . $complaint_id);
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'close_complaint') {
        if (!$is_customer) {
            header("Location: complaint.php");
            exit();
        }

        $product_code = trim($_POST['product_code'] ?? '');
        $product_name = trim($_POST['product_name'] ?? '');
        $seller_name = trim($_POST['seller_name'] ?? '');
            $purchase_location = trim($_POST['purchase_location'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $user_id = $_SESSION['user_id'];
            $product_image = null;
            $invoice_file = null;

        if ($product_name === '' || $description === '') {
            $error = "Product name and complaint details are required.";
        } else {
            $upload_dir = __DIR__ . '/../uploads/complaints';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }
            foreach (['product_image' => 'product_image', 'invoice_file' => 'invoice_file'] as $field => $target) {
                if (!empty($_FILES[$field]['name']) && is_uploaded_file($_FILES[$field]['tmp_name'])) {
                    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
                        $name = $target . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                        move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . '/' . $name);
                        if ($field === 'product_image') {
                            $product_image = 'uploads/complaints/' . $name;
                        } else {
                            $invoice_file = 'uploads/complaints/' . $name;
                        }
                    }
                }
            }

            $query = "INSERT INTO complaints (user_id, product_code, product_name, seller_name, purchase_location, description, product_image, invoice_file)
                VALUES (:user_id, :product_code, :product_name, :seller_name, :purchase_location, :description, :product_image, :invoice_file)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":product_code", $product_code);
            $stmt->bindParam(":product_name", $product_name);
            $stmt->bindParam(":seller_name", $seller_name);
            $stmt->bindParam(":purchase_location", $purchase_location);
            $stmt->bindParam(":description", $description);
            $stmt->bindParam(":product_image", $product_image);
            $stmt->bindParam(":invoice_file", $invoice_file);
            $stmt->execute();
            create_notification($db, 'complaint', 'New complaint submitted', $product_name . ' was reported as suspicious.', null);
            $success = "Complaint submitted successfully.";
            $_POST = [];
        }
    }

    if ($can_view_complaints && !$is_customer && $selected_id > 0) {
        $detail_stmt = $db->prepare("SELECT c.*, u.username, u.email
            FROM complaints c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = :id
            LIMIT 1");
        $detail_stmt->bindParam(":id", $selected_id);
        $detail_stmt->execute();
        $selected_complaint = $detail_stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$is_customer) {
        $stmt = $db->prepare("SELECT c.*, u.username FROM complaints c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.created_at DESC");
    } else {
        $stmt = $db->prepare("SELECT c.*, u.username FROM complaints c LEFT JOIN users u ON c.user_id = u.id WHERE c.user_id = :user_id ORDER BY c.created_at DESC");
        $stmt->bindParam(":user_id", $_SESSION['user_id']);
    }
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Unable to process complaints. Please check your database.";
}

$page_title = 'Complaints - Anti-Counterfeit System';
$active_page = 'complaints';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="row g-4">
            <?php if ($is_customer): ?>
                <div class="col-lg-5">
                    <div class="app-card">
                        <div class="app-card-header">
                            <h1 class="h4 mb-1"><i class="fas fa-triangle-exclamation me-2 text-primary"></i>Report Counterfeit Product</h1>
                            <p class="text-muted mb-0">Submit suspicious product details for manufacturer review.</p>
                        </div>
                        <div class="app-card-body">
                            <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
                            <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

                            <form method="POST" action="complaint.php" enctype="multipart/form-data">
                                <?php echo csrf_field(); ?>
                                <div class="mb-3">
                                    <label class="form-label" for="product_code">Product Code</label>
                                    <input class="form-control" id="product_code" name="product_code" value="<?php echo e($_POST['product_code'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="product_name">Product Name</label>
                                    <input class="form-control" id="product_name" name="product_name" value="<?php echo e($_POST['product_name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="seller_name">Seller / Shop Name</label>
                                    <input class="form-control" id="seller_name" name="seller_name" value="<?php echo e($_POST['seller_name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="purchase_location">Purchase Location</label>
                                    <input class="form-control" id="purchase_location" name="purchase_location" value="<?php echo e($_POST['purchase_location'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="description">Complaint Details</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo e($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="product_image">Product Image</label>
                                    <input type="file" class="form-control" id="product_image" name="product_image" accept=".jpg,.jpeg,.png,.webp">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="invoice_file">Invoice File</label>
                                    <input type="file" class="form-control" id="invoice_file" name="invoice_file" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                </div>
                                <button class="btn btn-primary w-100" type="submit">Submit Complaint</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="<?php echo $is_customer ? 'col-lg-7' : 'col-lg-12'; ?>">
                <?php if (!$is_customer && $selected_complaint): ?>
                    <div class="app-card mb-4">
                        <div class="app-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h2 class="h5 mb-1">Complaint Details</h2>
                                <span class="badge <?php echo complaint_status_badge($selected_complaint['status']); ?>"><?php echo e(ucfirst($selected_complaint['status'])); ?></span>
                            </div>
                            <a href="complaint.php" class="btn btn-sm btn-outline-secondary">Close Details</a>
                        </div>
                        <div class="app-card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><strong>Product:</strong><br><?php echo e($selected_complaint['product_name']); ?></div>
                                <div class="col-md-6"><strong>Product Code:</strong><br><?php echo e($selected_complaint['product_code'] ?: 'N/A'); ?></div>
                                <div class="col-md-6"><strong>Seller / Shop:</strong><br><?php echo e($selected_complaint['seller_name'] ?: 'N/A'); ?></div>
                                <div class="col-md-6"><strong>Purchase Location:</strong><br><?php echo e($selected_complaint['purchase_location'] ?: 'N/A'); ?></div>
                                <div class="col-md-6"><strong>Reporter:</strong><br><?php echo e(($selected_complaint['username'] ?? 'N/A') . ' (' . ($selected_complaint['email'] ?? 'No email') . ')'); ?></div>
                                <div class="col-md-6"><strong>Submitted:</strong><br><?php echo date('M d, Y H:i', strtotime($selected_complaint['created_at'])); ?></div>
                                <div class="col-12"><strong>Complaint Details:</strong><br><?php echo nl2br(e($selected_complaint['description'])); ?></div>
                                <?php if (!empty($selected_complaint['manufacturer_reply'])): ?>
                                    <div class="col-12">
                                        <div class="alert alert-info mb-0">
                                            <strong>Manufacturer Reply:</strong><br>
                                            <?php echo nl2br(e($selected_complaint['manufacturer_reply'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($can_review_complaints && $selected_complaint['status'] !== 'closed'): ?>
                                <form method="POST" action="complaint.php?id=<?php echo (int) $selected_complaint['id']; ?>" class="mt-4">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="close_complaint">
                                    <input type="hidden" name="complaint_id" value="<?php echo (int) $selected_complaint['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="reviewing">Investigate</option>
                                            <option value="resolved">Resolve</option>
                                            <option value="closed">Close</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label" for="manufacturer_reply">Reply to Customer Optional</label>
                                        <textarea class="form-control" id="manufacturer_reply" name="manufacturer_reply" rows="3" placeholder="Write a response before closing this complaint."></textarea>
                                    </div>
                                    <button class="btn btn-success" type="submit">Close Complaint</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="app-card">
                    <div class="app-card-header">
                        <h2 class="h5 mb-0"><?php echo $can_review_complaints ? 'Customer Complaints' : 'My Complaints'; ?></h2>
                    </div>
                    <div class="app-card-body">
                        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
                        <?php if (!$complaints): ?>
                            <p class="text-muted mb-0">No complaints submitted yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Code</th>
                                            <th>Reporter</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Reply</th>
                                            <?php if ($can_review_complaints): ?><th></th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($complaints as $complaint): ?>
                                            <tr>
                                                <td><?php echo e($complaint['product_name']); ?></td>
                                                <td><?php echo e($complaint['product_code'] ?: 'N/A'); ?></td>
                                                <td><?php echo e($complaint['username'] ?? 'N/A'); ?></td>
                                                <td><span class="badge <?php echo complaint_status_badge($complaint['status']); ?>"><?php echo e(ucfirst($complaint['status'])); ?></span></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($complaint['created_at'])); ?></td>
                                                <td><?php echo !empty($complaint['manufacturer_reply']) ? e($complaint['manufacturer_reply']) : 'N/A'; ?></td>
                                                <?php if ($can_review_complaints): ?>
                                                    <td class="text-end">
                                                        <a class="btn btn-sm btn-outline-primary" href="complaint.php?id=<?php echo (int) $complaint['id']; ?>">Open</a>
                                                    </td>
                                                <?php endif; ?>
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
