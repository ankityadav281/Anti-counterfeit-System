<?php
session_start();
include_once 'config/database.php';

// For testing purposes, we'll bypass the login check
// In production, you should uncomment these lines:
/*
// Check if user is logged in and is a manufacturer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: login.php");
    exit();
}
*/

// Initialize variables
$success_message = '';
$error_message = '';
$product_code = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get form data
    $product_name = $_POST['product_name'];
    $batch_number = $_POST['batch_number'];
    $manufacturing_date = $_POST['manufacturing_date'];
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $description = $_POST['description'];
    
    // For testing, use a default manufacturer ID
    $manufacturer_id = 1; // Default manufacturer ID for testing
    
    // Generate unique product code
    $prefix = "PROD";
    $year = date("Y");
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
    $product_code = $prefix . "-" . $year . "-" . $random;
    
    try {
        // Insert product into database
        $query = "INSERT INTO products 
                  (manufacturer_id, name, product_code, batch_number, manufacturing_date, expiry_date, description) 
                  VALUES 
                  (:manufacturer_id, :name, :product_code, :batch_number, :manufacturing_date, :expiry_date, :description)";
        
        $stmt = $db->prepare($query);
        
        // Bind parameters
        $stmt->bindParam(":manufacturer_id", $manufacturer_id);
        $stmt->bindParam(":name", $product_name);
        $stmt->bindParam(":product_code", $product_code);
        $stmt->bindParam(":batch_number", $batch_number);
        $stmt->bindParam(":manufacturing_date", $manufacturing_date);
        $stmt->bindParam(":expiry_date", $expiry_date);
        $stmt->bindParam(":description", $description);
        
        // Execute query
        if ($stmt->execute()) {
            $success_message = "Product registered successfully!";
        } else {
            $error_message = "Error registering product. Please try again.";
        }
    } catch(PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Product - Anti-Counterfeit System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-image: url('istock.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }
        
        .card {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .navbar {
            background-color: rgba(13, 110, 253, 0.9) !important;
        }
        
        footer {
            background-color: rgba(33, 37, 41, 0.9) !important;
        }
    </style>
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
                        <a class="nav-link" href="verify.php">Verify Product</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="register_product.php">Register Product</a>
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
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li> -->
                                <!-- <li><hr class="dropdown-divider"></li> -->
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

    <!-- Registration Section -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-box-open me-2"></i> Register New Product</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="register_product.php">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="product_name" class="form-label">Product Name</label>
                                    <input type="text" class="form-control" id="product_name" name="product_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="batch_number" class="form-label">Batch Number</label>
                                    <input type="text" class="form-control" id="batch_number" name="batch_number" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="manufacturing_date" class="form-label">Manufacturing Date</label>
                                    <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="expiry_date" class="form-label">Expiry Date (Optional)</label>
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Product Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Register Product</button>
                            </div>
                        </form>
                        
                        <?php if (!empty($product_code)): ?>
                            <div class="mt-4 p-3 bg-light rounded">
                                <h5 class="border-bottom pb-2">Product Registration Successful</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Product Name:</strong> <?php echo htmlspecialchars($product_name); ?></p>
                                        <p><strong>Batch Number:</strong> <?php echo htmlspecialchars($batch_number); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Product Code:</strong> <span class="badge bg-primary"><?php echo $product_code; ?></span></p>
                                        <p><strong>Manufacturing Date:</strong> <?php echo date('F d, Y', strtotime($manufacturing_date)); ?></p>
                                    </div>
                                </div>
                                
                                <!-- <div class="mt-3">
                                    <h5 class="border-bottom pb-2">QR Code</h5>
                                    <div class="text-center">
                                        <div id="qrcode" class="d-inline-block p-2 bg-white rounded"></div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary" id="downloadQR">
                                                <i class="fas fa-download me-1"></i> Download QR Code
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" id="printQR">
                                                <i class="fas fa-print me-1"></i> Print QR Code
                                            </button>
                                        </div>
                                    </div>
                                </div> -->
                                
                                <div class="mt-3">
                                    <h5 class="border-bottom pb-2">Instructions</h5>
                                    <ol>
                                        <li>Copy the Product Code</li>
                                        <li>Attach the Product Coder to your product packaging</li>
                                        <li>Consumers can check by filling the Product Code to verify the product's authenticity</li>
                                    </ol>
                                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($product_code)): ?>
            // Generate QR Code
            const qrcode = new QRCode(document.getElementById("qrcode"), {
                text: "<?php echo $product_code; ?>",
                width: 200,
                height: 200,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            
            // Download QR Code
            document.getElementById('downloadQR').addEventListener('click', function() {
                const canvas = document.querySelector('#qrcode canvas');
                const url = canvas.toDataURL('image/png');
                const a = document.createElement('a');
                a.download = 'product-qr-<?php echo $product_code; ?>.png';
                a.href = url;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
            
            // Print QR Code
            document.getElementById('printQR').addEventListener('click', function() {
                const canvas = document.querySelector('#qrcode canvas');
                const win = window.open('', '', 'width=600,height=600');
                win.document.write('<html><head><title>Print QR Code</title>');
                win.document.write('<style>body{display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}</style>');
                win.document.write('</head><body>');
                win.document.write('<img src="' + canvas.toDataURL('image/png') + '" style="max-width:100%;">');
                win.document.write('</body></html>');
                win.document.close();
                win.print();
            });
            <?php endif; ?>
        });
    </script>
</body>
</html> 