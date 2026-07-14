<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_login();

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id IS NULL OR user_id = :user_id")->execute([':user_id' => $_SESSION['user_id']]);
}
$notifications = $db->prepare("SELECT * FROM notifications WHERE user_id IS NULL OR user_id = :user_id ORDER BY created_at DESC LIMIT 100");
$notifications->execute([':user_id' => $_SESSION['user_id']]);
$rows = $notifications->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Notifications - Anti-Counterfeit System';
$active_page = 'notifications';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h1 class="h2 mb-1">Notifications</h1><p class="text-muted mb-0">Counterfeit, ownership, inventory, and suspicious activity alerts.</p></div>
            <form method="POST"><?php echo csrf_field(); ?><button class="btn btn-outline-primary">Mark All Read</button></form>
        </div>
        <div class="app-card"><div class="app-card-body">
            <?php if (!$rows): ?><p class="text-muted mb-0">No notifications yet.</p><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between gap-3">
                        <strong><?php echo e($row['title']); ?></strong>
                        <span class="badge <?php echo $row['is_read'] ? 'bg-secondary' : 'bg-primary'; ?>"><?php echo e($row['type']); ?></span>
                    </div>
                    <p class="mb-1"><?php echo e($row['message']); ?></p>
                    <small class="text-muted"><?php echo e($row['created_at']); ?></small>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
