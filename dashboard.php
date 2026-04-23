<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Get verification statistics
try {
    $stats_query = "SELECT COUNT(*) as total_verifications FROM product_verifications";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $total_verifications = $stats_stmt->fetch(PDO::FETCH_ASSOC)['total_verifications'];
    
    // Get recent verifications
    $recent_query = "SELECT pv.*, p.name as product_name, p.product_code, m.name as manufacturer_name 
                    FROM product_verifications pv 
                    JOIN products p ON pv.product_id = p.id 
                    JOIN manufacturers m ON p.manufacturer_id = m.id 
                    ORDER BY pv.verification_date DESC LIMIT 5";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute();
    $recent_verifications = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get product count
    $products_query = "SELECT COUNT(*) as total_products FROM products";
    $products_stmt = $db->prepare($products_query);
    $products_stmt->execute();
    $total_products = $products_stmt->fetch(PDO::FETCH_ASSOC)['total_products'];
    
    // Get manufacturer count
    $manufacturers_query = "SELECT COUNT(*) as total_manufacturers FROM manufacturers";
    $manufacturers_stmt = $db->prepare($manufacturers_query);
    $manufacturers_stmt->execute();
    $total_manufacturers = $manufacturers_stmt->fetch(PDO::FETCH_ASSOC)['total_manufacturers'];
    
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Anti-Counterfeit System</title>
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="verify.php">Verify Product</a>
                    </li>
                    <?php if ($role == 'admin' || $role == 'manufacturer'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Products</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($role == 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manufacturers.php">Manufacturers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <!-- <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li> -->
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
                <p class="text-muted">Here's an overview of the anti-counterfeit system</p>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Total Verifications</h6>
                                <h2 class="mb-0"><?php echo $total_verifications; ?></h2>
                            </div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Total Products</h6>
                                <h2 class="mb-0"><?php echo $total_products; ?></h2>
                            </div>
                            <i class="fas fa-box fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Manufacturers</h6>
                                <h2 class="mb-0"><?php echo $total_manufacturers; ?></h2>
                            </div>
                            <i class="fas fa-industry fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Your Role</h6>
                                <h2 class="mb-0"><?php echo ucfirst($role); ?></h2>
                            </div>
                            <i class="fas fa-user-shield fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Verifications -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Verifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_verifications)): ?>
                            <p class="text-muted">No recent verifications found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Code</th>
                                            <th>Manufacturer</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_verifications as $verification): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($verification['product_name']); ?></td>
                                                <td><?php echo htmlspecialchars($verification['product_code']); ?></td>
                                                <td><?php echo htmlspecialchars($verification['manufacturer_name']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($verification['verification_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end">
                                <!-- <a href="verifications.php" class="btn btn-sm btn-primary">View All</a> -->
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="verify.php" class="btn btn-primary"><i class="fas fa-qrcode me-2"></i> Verify Product</a>
                            <?php if ($role == 'admin' || $role == 'manufacturer'): ?>
                                <a href="add-product.php" class="btn btn-success"><i class="fas fa-plus-circle me-2"></i> Add New Product</a>
                            <?php endif; ?>
                            <?php if ($role == 'admin'): ?>
                                <a href="add-manufacturer.php" class="btn btn-info text-white"><i class="fas fa-industry me-2"></i> Add Manufacturer</a>
                                <a href="reports.php" class="btn btn-warning text-white"><i class="fas fa-chart-bar me-2"></i> Generate Reports</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="register_product.php" class="btn btn-outline-primary btn-lg">Register Product</a>
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
                    <p>&copy; 2025 Anti-Counterfeit System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 