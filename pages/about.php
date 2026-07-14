<?php
session_start();
$page_title = 'About - Anti-Counterfeit System';
$active_page = 'about';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="app-card">
                    <div class="app-card-body">
                        <h1 class="h2 mb-3">About Anti-Counterfeit System</h1>
                        <p class="lead text-muted">A professional product-authentication platform for consumers, brands, and small manufacturers.</p>

                        <div class="row g-4 mt-2">
                            <div class="col-md-6">
                                <h2 class="h5"><i class="fas fa-shield-alt text-primary me-2"></i>Mission</h2>
                                <p>Help users confirm product authenticity quickly while giving businesses a simple way to register products and monitor suspicious verification activity.</p>
                            </div>
                            <div class="col-md-6">
                                <h2 class="h5"><i class="fas fa-database text-primary me-2"></i>Core System</h2>
                                <p>Products are stored with unique codes, batch information, manufacturing dates, expiry dates, and verification history.</p>
                            </div>
                        </div>

                        <hr>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="feature-card">
                                    <i class="fas fa-bolt text-primary fa-2x mb-3"></i>
                                    <h3 class="h5">Fast Verification</h3>
                                    <p class="text-muted mb-0">Consumers can verify product codes instantly.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="feature-card">
                                    <i class="fas fa-qrcode text-primary fa-2x mb-3"></i>
                                    <h3 class="h5">QR Labels</h3>
                                    <p class="text-muted mb-0">Registered products can be issued QR labels for packaging.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="feature-card">
                                    <i class="fas fa-chart-simple text-primary fa-2x mb-3"></i>
                                    <h3 class="h5">Reports</h3>
                                    <p class="text-muted mb-0">Admins can review valid and suspicious checks.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
