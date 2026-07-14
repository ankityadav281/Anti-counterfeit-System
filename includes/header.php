<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
$active_page = $active_page ?? '';
$page_title = $page_title ?? 'Anti-Counterfeit System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(asset_url('assets/css/style.css')); ?>">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark app-navbar">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="<?php echo e(page_url('home')); ?>">
                <i class="fas fa-shield-alt me-2"></i> Anti-Counterfeit System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link<?php echo nav_active('home', $active_page); ?>" href="<?php echo e(page_url('home')); ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link<?php echo nav_active('about', $active_page); ?>" href="<?php echo e(page_url('about')); ?>">About</a></li>
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('verify', $active_page); ?>" href="<?php echo e(page_url('verify')); ?>">Verify</a></li>
                    <?php endif; ?>

                    <?php if (is_logged_in()): ?>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('dashboard', $active_page); ?>" href="<?php echo e(page_url('dashboard')); ?>">Dashboard</a></li>
                        <?php if (can_access_module('products')): ?>
                            <li class="nav-item"><a class="nav-link<?php echo nav_active('products', $active_page); ?>" href="<?php echo e(page_url('products')); ?>">Products</a></li>
                        <?php endif; ?>
                        <?php if (can_access_module('inventory')): ?>
                            <li class="nav-item"><a class="nav-link<?php echo nav_active('inventory', $active_page); ?>" href="<?php echo e(page_url('inventory')); ?>">Inventory</a></li>
                        <?php endif; ?>
                        <?php if (can_access_module('supply_chain')): ?>
                            <li class="nav-item"><a class="nav-link<?php echo nav_active('supply_chain', $active_page); ?>" href="<?php echo e(page_url('supply_chain')); ?>">Supply Chain</a></li>
                        <?php endif; ?>
                        <?php if (can_access_module('ownership')): ?>
                            <li class="nav-item"><a class="nav-link<?php echo nav_active('ownership', $active_page); ?>" href="<?php echo e(page_url('ownership')); ?>">Ownership</a></li>
                        <?php endif; ?>
                        <?php if (can_access_module('analytics')): ?>
                            <li class="nav-item"><a class="nav-link<?php echo nav_active('analytics', $active_page); ?>" href="<?php echo e(page_url('analytics')); ?>">Analytics</a></li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle<?php echo in_array($active_page, ['profile','messages','workflows','companies','batches','warranty','recalls','timeline','global_search','business_reports'], true) ? ' active' : ''; ?>" href="#" role="button" data-bs-toggle="dropdown">Business</a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo e(page_url('profile')); ?>">Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo e(page_url('messages')); ?>">Messages</a></li>
                                <li><a class="dropdown-item" href="<?php echo e(page_url('workflows')); ?>">Approvals</a></li>
                                <?php if (can_access_module('companies')): ?><li><a class="dropdown-item" href="<?php echo e(page_url('companies')); ?>">Companies</a></li><?php endif; ?>
                                <?php if (can_access_module('batches')): ?><li><a class="dropdown-item" href="<?php echo e(page_url('batches')); ?>">Batches</a></li><?php endif; ?>
                                <?php if (can_access_module('warranty')): ?><li><a class="dropdown-item" href="<?php echo e(page_url('warranty')); ?>">Warranty</a></li><?php endif; ?>
                                <?php if (can_access_module('recalls')): ?><li><a class="dropdown-item" href="<?php echo e(page_url('recalls')); ?>">Recalls</a></li><?php endif; ?>
                                <li><a class="dropdown-item" href="<?php echo e(page_url('timeline')); ?>">Timeline</a></li>
                                <?php if (can_access_module('global_search')): ?><li><a class="dropdown-item" href="<?php echo e(page_url('global_search')); ?>">Global Search</a></li><?php endif; ?>
                                <?php if (can_access_module('business_reports')): ?><li><a class="dropdown-item" href="<?php echo e(page_url('business_reports')); ?>">Reports</a></li><?php endif; ?>
                                <?php if (can_access_module('api')): ?><li><a class="dropdown-item" href="<?php echo e(page_url('api')); ?>">REST API</a></li><?php endif; ?>
                                <?php if (has_role(['super_admin'])): ?><li><a class="dropdown-item" href="<?php echo e(page_url('system_health')); ?>">System Health</a></li><?php endif; ?>
                            </ul>
                        </li>
                        <?php if (can_access_module('complaints')): ?><li class="nav-item"><a class="nav-link<?php echo nav_active('complaints', $active_page); ?>" href="<?php echo e(page_url('complaints')); ?>">Complaint</a></li><?php endif; ?>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('notifications', $active_page); ?>" href="<?php echo e(page_url('notifications')); ?>">Alerts</a></li>
                        <li class="nav-item">
                            <a class="btn btn-light btn-sm ms-lg-3" href="<?php echo e(page_url('logout')); ?>">
                                <i class="fas fa-sign-out-alt me-1"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link<?php echo nav_active('login', $active_page); ?>" href="<?php echo e(page_url('login')); ?>">Login</a></li>
                        <li class="nav-item">
                            <a class="btn btn-light btn-sm ms-lg-3" href="<?php echo e(page_url('register')); ?>">Create Account</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main>
