<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Anti-Counterfeit System</title>
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
        
        .content-wrapper {
            background-color: rgba(255, 255, 255, 0.9);
            opacity: 0.;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 20px;
        }
        
        .navbar {
            background-color: rgba(13, 110, 253, 0.9) !important;
        }
        
        .about-section {
            background-color: rgba(255, 255, 255, 0.8);
            /* opacity: 0.8; */
            border-radius: 10px;
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .team-member {
            text-align: center;
            margin-bottom: 30px;
        }

        .team-member img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin-bottom: 15px;
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
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#verify">Verify Product</a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="register_product.php">Register Product</a>
                    </li> -->
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="about-section">
            <h1 class="text-center mb-4">About Anti-Counterfeit System</h1>
            
            <div class="row mb-5">
                <div class="col-md-12">
                    <h2>Our Mission</h2>
                    <p class="lead">To protect brands and consumers from counterfeit products through innovative technology and reliable verification systems.</p>
                    
                    <h2 class="mt-4">What We Do</h2>
                    <p>The Anti-Counterfeit System is a cutting-edge solution designed to combat the growing problem of counterfeit products in the market. Our system uses advanced technology and secure database management to ensure that products can be easily verified for authenticity.</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h3><i class="fas fa-check-circle text-primary"></i> Product Verification</h3>
                            <p>Our system allows consumers to instantly verify the authenticity of products using their smartphones.</p>
                        </div>
                        <div class="col-md-6">
                            <h3><i class="fas fa-shield-alt text-primary"></i> Brand Protection</h3>
                            <p>We help businesses protect their brands from counterfeiters and maintain consumer trust.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <h2 class="text-center mb-4">Why Choose Us?</h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-bolt fa-3x text-primary mb-3"></i>
                                    <h4>Fast Verification</h4>
                                    <p>Instant product verification with our quick and efficient system.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                                    <h4>Secure System</h4>
                                    <p>Advanced security measures to protect product and user data.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                    <h4>User-Friendly</h4>
                                    <p>Easy to use interface for both businesses and consumers.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
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