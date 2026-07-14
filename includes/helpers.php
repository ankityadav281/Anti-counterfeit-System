<?php
require_once __DIR__ . '/../config/app.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_role() {
    return $_SESSION['role'] ?? 'guest';
}

function role_alias($role = null) {
    $role = $role ?? current_user_role();
    $aliases = [
        'admin' => 'super_admin',
        'user' => 'customer',
    ];

    return $aliases[$role] ?? $role;
}

function role_label($role = null) {
    return ucwords(str_replace('_', ' ', role_alias($role)));
}

function has_role($roles) {
    $roles = array_map('role_alias', (array) $roles);
    return in_array(role_alias(), $roles, true);
}

function is_manufacturer_area_allowed() {
    return has_role(['super_admin', 'manufacturer']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: " . page_url('login'));
        exit();
    }
}

function require_role($roles) {
    require_login();
    $roles = array_map('role_alias', (array) $roles);

    if (!in_array(role_alias(), $roles, true)) {
        header("Location: " . page_url('dashboard'));
        exit();
    }
}

function require_module($module) {
    require_login();

    if (!can_access_module($module)) {
        header("Location: " . page_url('dashboard'));
        exit();
    }
}

function can_access_module($module) {
    $role = role_alias();
    $matrix = [
        'products' => ['super_admin', 'manufacturer'],
        'inventory' => ['super_admin', 'manufacturer', 'retailer'],
        'inventory_update' => ['super_admin', 'manufacturer', 'retailer'],
        'supply_chain' => ['super_admin', 'manufacturer', 'auditor'],
        'supply_chain_record' => ['super_admin', 'manufacturer'],
        'ownership' => ['super_admin', 'retailer', 'customer'],
        'ownership_register' => ['super_admin', 'retailer', 'customer'],
        'analytics' => ['super_admin', 'manufacturer', 'auditor'],
        'audit' => ['super_admin', 'auditor'],
        'notifications' => ['super_admin', 'manufacturer', 'warehouse_manager', 'distributor', 'retailer', 'auditor', 'customer'],
        'complaints' => ['super_admin', 'manufacturer', 'retailer', 'auditor', 'customer'],
        'complaint_manage' => ['super_admin', 'manufacturer', 'retailer'],
        'profile' => ['super_admin', 'manufacturer', 'warehouse_manager', 'distributor', 'retailer', 'auditor', 'customer'],
        'messages' => ['super_admin', 'manufacturer', 'warehouse_manager', 'distributor', 'retailer', 'auditor', 'customer'],
        'workflows' => ['super_admin', 'manufacturer', 'warehouse_manager', 'distributor', 'retailer', 'auditor', 'customer'],
        'companies' => ['super_admin', 'manufacturer', 'auditor'],
        'company_manage' => ['super_admin', 'manufacturer'],
        'batches' => ['super_admin', 'manufacturer', 'auditor'],
        'batch_manage' => ['super_admin', 'manufacturer'],
        'warranty' => ['super_admin', 'manufacturer', 'retailer', 'customer', 'auditor'],
        'warranty_claim' => ['customer'],
        'warranty_retailer_approve' => ['super_admin', 'retailer'],
        'warranty_manufacturer_approve' => ['super_admin', 'manufacturer'],
        'recalls' => ['super_admin', 'manufacturer', 'distributor', 'warehouse_manager', 'retailer', 'auditor'],
        'recall_publish' => ['super_admin', 'manufacturer'],
        'global_search' => ['super_admin', 'manufacturer', 'auditor'],
        'business_reports' => ['super_admin', 'manufacturer', 'retailer', 'auditor'],
        'api' => ['super_admin'],
    ];

    return in_array($role, $matrix[$module] ?? [], true);
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid security token.');
    }
}

function client_ip() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function app_base_url() {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $one_level_folders = [
        'account', 'admin', 'api', 'auth', 'communication', 'customers',
        'dashboard', 'operations', 'pages', 'products', 'reports', 'verification'
    ];

    if (in_array(basename($dir), $one_level_folders, true)) {
        $dir = rtrim(dirname($dir), '/');
    }

    return $dir === '' || $dir === '.' ? '' : $dir;
}

function app_url($path = '') {
    $base = app_base_url();
    $path = ltrim((string) $path, '/');

    return $base . ($path !== '' ? '/' . $path : '/');
}

function asset_url($path) {
    return app_url($path);
}

function page_url($key) {
    $routes = [
        'home' => 'index.php',
        'about' => 'pages/about.php',
        'login' => 'auth/login.php',
        'logout' => 'auth/logout.php',
        'register' => 'auth/register.php',
        'dashboard' => 'dashboard/dashboard.php',
        'verify' => 'verification/verify.php',
        'products' => 'products/products.php',
        'register_product' => 'products/register_product.php',
        'edit_product' => 'products/edit_product.php',
        'batches' => 'products/batches.php',
        'recalls' => 'products/recalls.php',
        'inventory' => 'operations/inventory.php',
        'supply_chain' => 'operations/supply_chain.php',
        'ownership' => 'customers/ownership.php',
        'complaints' => 'customers/complaint.php',
        'warranty' => 'customers/warranty.php',
        'timeline' => 'customers/timeline.php',
        'messages' => 'communication/messages.php',
        'profile' => 'account/profile.php',
        'companies' => 'admin/companies.php',
        'workflows' => 'admin/workflows.php',
        'notifications' => 'admin/notifications.php',
        'system_health' => 'admin/system_health.php',
        'analytics' => 'reports/analytics.php',
        'audit' => 'reports/audit.php',
        'reports' => 'reports/reports.php',
        'business_reports' => 'reports/business_reports.php',
        'global_search' => 'reports/global_search.php',
        'api' => 'api/api.php',
    ];

    return app_url($routes[$key] ?? $key);
}

function nav_active($page, $active_page) {
    return $page === $active_page ? ' active' : '';
}
?>
