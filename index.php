<?php
session_start();
$page_title = 'Anti-Counterfeit System';
$active_page = 'home';
include 'includes/header.php';
?>
<section class="hero">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-7">
                <span class="badge bg-light text-primary mb-3">Secure product authenticity platform</span>
                <h1 class="display-5 fw-bold">Verify genuine products before they reach your customers.</h1>
                <p class="lead mt-3">Register products, generate traceable QR codes, track verification activity, and identify suspicious counterfeit attempts from one clean dashboard.</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <?php if (is_logged_in()): ?>
                        <a href="<?php echo e(page_url('verify')); ?>" class="btn btn-light btn-lg"><i class="fas fa-barcode me-2"></i>Verify Product</a>
                        <a href="<?php echo e(page_url('dashboard')); ?>" class="btn btn-outline-light btn-lg">Open Dashboard</a>
                    <?php else: ?>
                        <a href="<?php echo e(page_url('login')); ?>" class="btn btn-light btn-lg"><i class="fas fa-right-to-bracket me-2"></i>Login to Verify</a>
                        <a href="<?php echo e(page_url('register')); ?>" class="btn btn-outline-light btn-lg">Create Account</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-panel">
                    <h2 class="h5 mb-3">How verification works</h2>
                    <div class="d-flex gap-3 mb-3">
                        <i class="fas fa-box text-warning fa-lg mt-1"></i>
                        <div><strong>Register product</strong><p class="mb-0">Add batch, date, manufacturer, and description details.</p></div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <i class="fas fa-qrcode text-warning fa-lg mt-1"></i>
                        <div><strong>Generate code</strong><p class="mb-0">Attach the unique code or QR to product packaging.</p></div>
                    </div>
                    <div class="d-flex gap-3">
                        <i class="fas fa-shield-check text-warning fa-lg mt-1"></i>
                        <div><strong>Verify instantly</strong><p class="mb-0">Customers can check authenticity and see product details.</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
