<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_role(['super_admin']);

$checks = [];
$database = new Database();
$db = $database->getConnection();

$checks[] = [
    'name' => 'Database Connection',
    'status' => $db instanceof PDO ? 'OK' : 'Failed',
    'detail' => $db instanceof PDO ? app_config('db_name') . ' on ' . app_config('db_host') : 'Unable to connect',
];

$paths = [
    'Uploads writable' => APP_ROOT . '/uploads',
    'Logs writable' => APP_ROOT . '/storage/logs',
    'Database folder protected' => APP_ROOT . '/database/.htaccess',
    'Config folder protected' => APP_ROOT . '/config/.htaccess',
];

foreach ($paths as $name => $path) {
    $is_file_check = substr($path, -9) === '.htaccess';
    $checks[] = [
        'name' => $name,
        'status' => $is_file_check ? (is_readable($path) ? 'OK' : 'Missing') : (is_writable($path) ? 'OK' : 'Not writable'),
        'detail' => $path,
    ];
}

$checks[] = [
    'name' => 'PHP Version',
    'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'OK' : 'Upgrade recommended',
    'detail' => PHP_VERSION,
];

$checks[] = [
    'name' => 'Application Mode',
    'status' => app_is_debug() ? 'Development' : 'Production',
    'detail' => 'APP_ENV=' . app_config('env'),
];

$page_title = 'System Health - Anti-Counterfeit System';
$active_page = 'system_health';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">System Health</h1>
                <p class="text-muted mb-0">Deployment readiness checks for database, storage, security, and runtime configuration.</p>
            </div>
            <span class="badge bg-<?php echo in_array('Failed', array_column($checks, 'status'), true) ? 'danger' : 'success'; ?>">Operations</span>
        </div>

        <div class="app-card">
            <div class="app-card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
                        <tbody>
                            <?php foreach ($checks as $check): ?>
                                <?php $ok = in_array($check['status'], ['OK', 'Development', 'Production'], true); ?>
                                <tr>
                                    <td><?php echo e($check['name']); ?></td>
                                    <td><span class="badge bg-<?php echo $ok ? 'success' : 'danger'; ?>"><?php echo e($check['status']); ?></span></td>
                                    <td><small class="text-muted"><?php echo e($check['detail']); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
