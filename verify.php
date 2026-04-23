<?php
session_start();
include_once 'config/database.php';

// Initialize variables
$verification_result = null;
$error = null;
$success = null;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $product_code = $_POST['product_code'];
    
    if (!empty($product_code)) {
        try {
            // Prepare query
            $query = "SELECT p.*, m.name as manufacturer_name 
                     FROM products p 
                     LEFT JOIN manufacturers m ON p.manufacturer_id = m.id 
                     WHERE p.product_code = :product_code";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":product_code", $product_code);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if product has been verified before
                $verification_query = "SELECT COUNT(*) as verification_count 
                                    FROM product_verifications 
                                    WHERE product_id = :product_id";
                
                $verification_stmt = $db->prepare($verification_query);
                $verification_stmt->bindParam(":product_id", $row['id']);
                $verification_stmt->execute();
                $verification_result = $verification_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log verification
                $log_query = "INSERT INTO product_verifications 
                             (product_id, verification_date, ip_address, user_agent) 
                             VALUES (:product_id, NOW(), :ip_address, :user_agent)";
                
                $log_stmt = $db->prepare($log_query);
                $log_stmt->bindParam(":product_id", $row['id']);
                $log_stmt->bindParam(":ip_address", $_SERVER['REMOTE_ADDR']);
                $log_stmt->bindParam(":user_agent", $_SERVER['HTTP_USER_AGENT']);
                $log_stmt->execute();
                
                $success = "Product verified successfully!";
                $verification_result = $row;
            } else {
                $error = "Product not found. This might be a counterfeit product.";
            }
        } catch(PDOException $e) {
            $error = "Verification error: " . $e->getMessage();
        }
    } else {
        $error = "Please enter a product code";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Product - Anti-Counterfeit System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-shield-alt"></i> Anti-Counterfeit System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="verify.php">Verify Product</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <!-- <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li> -->
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Verification Section -->
    <div class="container py-5 " style="height:72vh">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-qrcode me-2"></i> Verify Product Authenticity</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="verify.php">
                            <div class="mb-4">
                                <label for="product_code" class="form-label">Product Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                    <input type="text" class="form-control form-control-lg" id="product_code" name="product_code" placeholder="Enter product code" required>
                                    <button type="submit" class="btn btn-primary btn-lg">Verify</button>
                                </div>
                            </div>
                        </form>
                        
                        <?php if ($verification_result): ?>
                            <div class="mt-4">
                                <h5 class="border-bottom pb-2">Product Details</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Product Name:</strong> <?php echo htmlspecialchars($verification_result['name']); ?></p>
                                        <p><strong>Manufacturer:</strong> <?php echo htmlspecialchars($verification_result['manufacturer_name']); ?></p>
                                        <p><strong>Product Code:</strong> <?php echo htmlspecialchars($verification_result['product_code']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Batch Number:</strong> <?php echo htmlspecialchars($verification_result['batch_number']); ?></p>
                                        <p><strong>Manufacturing Date:</strong> <?php echo date('F d, Y', strtotime($verification_result['manufacturing_date'])); ?></p>
                                        <p><strong>Expiry Date:</strong> <?php echo $verification_result['expiry_date'] ? date('F d, Y', strtotime($verification_result['expiry_date'])) : 'N/A'; ?></p>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <h5 class="border-bottom pb-2">Verification Status</h5>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-check-circle fa-3x text-success"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-success mb-0">Authentic Product</h4>
                                            <p class="text-muted mb-0">This product has been verified as authentic.</p>
                                            <!-- <p class="text-muted mb-0">Verification count: <?php echo $verification_result['verification_count']; ?></p> -->
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($verification_result['description']): ?>
                                    <div class="mt-3">
                                        <h5 class="border-bottom pb-2">Description</h5>
                                        <p><?php echo nl2br(htmlspecialchars($verification_result['description'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Anti-Counterfeit System</h5>
                    <p>Protecting brands and consumers from counterfeit products</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 Anti-Counterfeit System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form submission handling
            const verifyForm = document.querySelector('form');
            if (verifyForm) {
                verifyForm.addEventListener('submit', function(e) {
                    const productCode = document.getElementById('product_code').value;
                    if (!productCode) {
                        e.preventDefault();
                        const alertDiv = document.createElement('div');
                        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                        alertDiv.innerHTML = `
                            Please enter a product code
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        verifyForm.insertBefore(alertDiv, verifyForm.firstChild);
                    }
                });
            }
        });
    </script>
</body>
</html> 