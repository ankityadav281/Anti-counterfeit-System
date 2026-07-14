<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_login();

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$success = '';
$error = '';
$user_id = (int) $_SESSION['user_id'];
$role = role_alias();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'send';
    if ($action === 'send') {
        $receiver_id = (int) ($_POST['receiver_id'] ?? 0);
        $receiver_role = $_POST['receiver_role'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        if ($receiver_id > 0) {
            $stmt = $db->prepare("SELECT role FROM users WHERE id = :id");
            $stmt->execute([':id' => $receiver_id]);
            $receiver_role = role_alias($stmt->fetchColumn());
        }
        if ($subject === '' || $message === '' || !can_message_role($role, $receiver_role)) {
            $error = 'This message is not allowed by the business communication hierarchy.';
        } else {
            $attachment = null;
            if (!empty($_FILES['attachment']['name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
                $dir = __DIR__ . '/../uploads/messages';
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'csv'], true)) {
                    $name = 'message-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . '/' . $name);
                    $attachment = 'uploads/messages/' . $name;
                }
            }
            $stmt = $db->prepare("INSERT INTO business_messages (sender_id, receiver_id, receiver_role, subject, message, attachment_path, related_message_id, is_important, is_pinned)
                VALUES (:sender_id, :receiver_id, :receiver_role, :subject, :message, :attachment, :related, :important, :pinned)");
            $stmt->execute([
                ':sender_id' => $user_id,
                ':receiver_id' => $receiver_id ?: null,
                ':receiver_role' => $receiver_role,
                ':subject' => $subject,
                ':message' => $message,
                ':attachment' => $attachment,
                ':related' => $_POST['related_message_id'] ?: null,
                ':important' => isset($_POST['is_important']) ? 1 : 0,
                ':pinned' => isset($_POST['is_pinned']) ? 1 : 0,
            ]);
            create_notification($db, 'message', 'New message', $subject, $receiver_id ?: null);
            activity_log($db, 'Business message sent', 'message', (int) $db->lastInsertId());
            $success = 'Message sent.';
        }
    } elseif (in_array($action, ['archive', 'delete', 'read'], true)) {
        $message_id = (int) ($_POST['message_id'] ?? 0);
        if ($action === 'archive') {
            $db->prepare("UPDATE business_messages SET is_archived = 1 WHERE id = :id AND (receiver_id = :user_id OR sender_id = :user_id)")->execute([':id' => $message_id, ':user_id' => $user_id]);
        } elseif ($action === 'delete') {
            $db->prepare("UPDATE business_messages SET deleted_by_receiver = IF(receiver_id = :user_id, 1, deleted_by_receiver), deleted_by_sender = IF(sender_id = :user_id, 1, deleted_by_sender) WHERE id = :id")->execute([':id' => $message_id, ':user_id' => $user_id]);
        } else {
            $db->prepare("UPDATE business_messages SET is_read = 1, read_at = NOW() WHERE id = :id AND receiver_id = :user_id")->execute([':id' => $message_id, ':user_id' => $user_id]);
        }
    }
}

$users = $db->query("SELECT id, username, role FROM users ORDER BY role, username")->fetchAll(PDO::FETCH_ASSOC);
$search = trim($_GET['q'] ?? '');
$folder = $_GET['folder'] ?? 'inbox';
$params = [':user_id' => $user_id, ':role' => $role];
$where = $folder === 'sent' ? "sender_id = :user_id AND deleted_by_sender = 0" : "(receiver_id = :user_id OR receiver_role = :role) AND deleted_by_receiver = 0";
if ($folder === 'important') {
    $where .= " AND is_important = 1";
}
if ($folder === 'pinned') {
    $where .= " AND is_pinned = 1";
}
if ($folder === 'archive') {
    $where .= " AND is_archived = 1";
} else {
    $where .= " AND is_archived = 0";
}
if ($search !== '') {
    $where .= " AND (subject LIKE :search OR message LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
$stmt = $db->prepare("SELECT bm.*, su.username AS sender_name, ru.username AS receiver_name FROM business_messages bm JOIN users su ON su.id = bm.sender_id LEFT JOIN users ru ON ru.id = bm.receiver_id WHERE $where ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Messages - Anti-Counterfeit System';
$active_page = 'messages';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <h1 class="h2 mb-1">Enterprise Communication</h1>
        <p class="text-muted mb-4">Hierarchy-based business inbox with attachments, read receipts, archive, reply, and forwarding.</p>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Compose</h2></div>
                    <div class="app-card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?><input type="hidden" name="action" value="send"><input type="hidden" name="related_message_id" value="">
                            <div class="mb-3"><label class="form-label">Receiver</label><select class="form-select" name="receiver_id">
                                <option value="">Role broadcast</option>
                                <?php foreach ($users as $user): if ((int) $user['id'] === $user_id || !can_message_role($role, $user['role'])) continue; ?>
                                    <option value="<?php echo (int) $user['id']; ?>"><?php echo e($user['username'] . ' (' . role_label($user['role']) . ')'); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                            <div class="mb-3"><label class="form-label">Receiver Role</label><select class="form-select" name="receiver_role">
                                <?php foreach (['super_admin','manufacturer','distributor','warehouse_manager','retailer','customer','auditor'] as $target): if (!can_message_role($role, $target)) continue; ?>
                                    <option value="<?php echo e($target); ?>"><?php echo e(role_label($target)); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                            <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" required></div>
                            <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="4" required></textarea></div>
                            <div class="mb-3"><label class="form-label">Attachment</label><input type="file" class="form-control" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.csv"></div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_important" id="important"><label class="form-check-label" for="important">Mark important</label></div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_pinned" id="pinned"><label class="form-check-label" for="pinned">Pin message</label></div>
                            <button class="btn btn-primary w-100">Send</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach (['inbox'=>'Inbox','sent'=>'Sent','important'=>'Important','pinned'=>'Pinned','archive'=>'Archive'] as $key => $label): ?><a class="btn btn-sm <?php echo $folder === $key ? 'btn-primary' : 'btn-outline-primary'; ?>" href="messages.php?folder=<?php echo e($key); ?>"><?php echo e($label); ?></a><?php endforeach; ?>
                    <form class="ms-auto d-flex gap-2"><input type="hidden" name="folder" value="<?php echo e($folder); ?>"><input class="form-control form-control-sm" name="q" value="<?php echo e($search); ?>" placeholder="Search messages"><button class="btn btn-sm btn-outline-primary">Search</button></form>
                </div>
                <div class="app-card"><div class="app-card-body">
                    <?php if (!$messages): ?><p class="text-muted mb-0">No messages found.</p><?php endif; ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="border-bottom py-3">
                            <div class="d-flex justify-content-between gap-3">
                                <strong><?php echo e($message['subject']); ?></strong>
                                <span class="badge <?php echo $message['is_read'] ? 'bg-secondary' : 'bg-primary'; ?>"><?php echo $message['is_read'] ? 'Read' : 'Unread'; ?></span>
                            </div>
                            <p class="mb-1"><?php echo nl2br(e($message['message'])); ?></p>
                            <small class="text-muted">From <?php echo e($message['sender_name']); ?> to <?php echo e($message['receiver_name'] ?: role_label($message['receiver_role'])); ?> · <?php echo e($message['created_at']); ?></small>
                            <?php if ($message['attachment_path']): ?><br><a href="<?php echo e(asset_url($message['attachment_path'])); ?>" target="_blank">Open attachment</a><?php endif; ?>
                            <div class="mt-2 d-flex gap-2">
                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="message_id" value="<?php echo (int) $message['id']; ?>"><input type="hidden" name="action" value="read"><button class="btn btn-sm btn-outline-secondary">Read</button></form>
                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="message_id" value="<?php echo (int) $message['id']; ?>"><input type="hidden" name="action" value="archive"><button class="btn btn-sm btn-outline-secondary">Archive</button></form>
                                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="message_id" value="<?php echo (int) $message['id']; ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
