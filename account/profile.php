<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';
require_login();

$database = new Database();
$db = $database->getConnection();
enterprise_bootstrap($db);
$user_id = (int) $_SESSION['user_id'];
$success = '';
$error = '';

$db->prepare("INSERT IGNORE INTO user_profiles (user_id, full_name, account_status, date_joined) VALUES (:user_id, :full_name, 'active', CURDATE())")
    ->execute([':user_id' => $user_id, ':full_name' => $_SESSION['username'] ?? '']);

if (($_GET['download'] ?? '') === 'profile') {
    $stmt = $db->prepare("SELECT u.username, u.email, u.role, up.* FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = :id");
    $stmt->execute([':id' => $user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    export_csv('profile-details.csv', array_keys($profile), [array_values($profile)]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        if (!$stmt->fetchColumn() || $new !== $confirm || strlen($new) < 8) {
            $error = 'Enter a valid current password and matching new password of at least 8 characters.';
        } else {
            $stmt->execute([':id' => $user_id]);
            $hash = $stmt->fetchColumn();
            if (!password_verify($current, $hash)) {
                $error = 'Current password is incorrect.';
            } else {
                $db->prepare("UPDATE users SET password = :password WHERE id = :id")->execute([':password' => password_hash($new, PASSWORD_DEFAULT), ':id' => $user_id]);
                $db->prepare("UPDATE user_profiles SET last_password_change = NOW() WHERE user_id = :id")->execute([':id' => $user_id]);
                activity_log($db, 'Password changed', 'user', $user_id);
                $success = 'Password changed.';
            }
        }
    } elseif ($action === 'deactivate' && role_alias() === 'customer') {
        $db->prepare("UPDATE user_profiles SET account_status = 'deactivated' WHERE user_id = :id")->execute([':id' => $user_id]);
        activity_log($db, 'Account deactivated', 'user', $user_id);
        $success = 'Account deactivated. You can ask support to reactivate it.';
    } else {
        $upload_path = $_POST['existing_photo'] ?? null;
        if (!empty($_FILES['profile_photo']['name']) && is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
            $dir = __DIR__ . '/../uploads/profiles';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $name = 'profile-' . $user_id . '-' . time() . '.' . $ext;
                move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . '/' . $name);
                $upload_path = 'uploads/profiles/' . $name;
            }
        }
        $data = [
            'profile_photo' => $upload_path,
            'full_name' => trim($_POST['full_name'] ?? ''),
            'employee_id' => trim($_POST['employee_id'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'designation' => trim($_POST['designation'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'alternate_phone' => trim($_POST['alternate_phone'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?: null,
            'gender' => trim($_POST['gender'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'state' => trim($_POST['state'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'pin_code' => trim($_POST['pin_code'] ?? ''),
            'date_joined' => $_POST['date_joined'] ?: null,
            'preferred_language' => trim($_POST['preferred_language'] ?? 'English'),
            'notification_preferences' => trim($_POST['notification_preferences'] ?? ''),
            'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
            'bio' => trim($_POST['bio'] ?? ''),
            'social_links' => trim($_POST['social_links'] ?? ''),
            'two_factor_enabled' => isset($_POST['two_factor_enabled']) ? 1 : 0,
        ];
        $data['profile_completion'] = profile_completion($data);
        $data['user_id'] = $user_id;
        $sql = "UPDATE user_profiles SET profile_photo=:profile_photo, full_name=:full_name, employee_id=:employee_id, company_name=:company_name,
            department=:department, designation=:designation, phone=:phone, alternate_phone=:alternate_phone, date_of_birth=:date_of_birth,
            gender=:gender, address=:address, city=:city, state=:state, country=:country, pin_code=:pin_code, date_joined=:date_joined,
            preferred_language=:preferred_language, notification_preferences=:notification_preferences, emergency_contact=:emergency_contact,
            bio=:bio, social_links=:social_links, two_factor_enabled=:two_factor_enabled, profile_completion=:profile_completion WHERE user_id=:user_id";
        $db->prepare($sql)->execute($data);
        activity_log($db, 'Profile updated', 'user', $user_id);
        $success = 'Profile updated.';
    }
}

$stmt = $db->prepare("SELECT u.username, u.email, u.role, up.* FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = :id");
$stmt->execute([':id' => $user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);
$history = $db->prepare("SELECT * FROM login_history WHERE user_id = :id ORDER BY created_at DESC LIMIT 15");
$history->execute([':id' => $user_id]);
$logins = $history->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Profile - Anti-Counterfeit System';
$active_page = 'profile';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">Professional Profile</h1>
                <p class="text-muted mb-0">Manage account, security, preferences, and business identity.</p>
            </div>
            <a class="btn btn-outline-primary" href="profile.php?download=profile">Download Details</a>
        </div>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="app-card">
                    <div class="app-card-header"><h2 class="h5 mb-0">Profile Information</h2></div>
                    <div class="app-card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?><input type="hidden" name="action" value="profile"><input type="hidden" name="existing_photo" value="<?php echo e($profile['profile_photo'] ?? ''); ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?php echo e($profile['full_name'] ?? ''); ?>"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Profile Photo</label><input type="file" class="form-control" name="profile_photo" accept=".jpg,.jpeg,.png,.webp"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Employee ID</label><input class="form-control" name="employee_id" value="<?php echo e($profile['employee_id'] ?? ''); ?>"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Company</label><input class="form-control" name="company_name" value="<?php echo e($profile['company_name'] ?? ''); ?>"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Department</label><input class="form-control" name="department" value="<?php echo e($profile['department'] ?? ''); ?>"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Designation</label><input class="form-control" name="designation" value="<?php echo e($profile['designation'] ?? ''); ?>"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?php echo e($profile['phone'] ?? ''); ?>"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Alternate Phone</label><input class="form-control" name="alternate_phone" value="<?php echo e($profile['alternate_phone'] ?? ''); ?>"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="date_of_birth" value="<?php echo e($profile['date_of_birth'] ?? ''); ?>"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Gender</label><input class="form-control" name="gender" value="<?php echo e($profile['gender'] ?? ''); ?>"></div>
                                <div class="col-md-4 mb-3"><label class="form-label">Date Joined</label><input type="date" class="form-control" name="date_joined" value="<?php echo e($profile['date_joined'] ?? ''); ?>"></div>
                                <div class="col-12 mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?php echo e($profile['address'] ?? ''); ?></textarea></div>
                                <div class="col-md-3 mb-3"><label class="form-label">City</label><input class="form-control" name="city" value="<?php echo e($profile['city'] ?? ''); ?>"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">State</label><input class="form-control" name="state" value="<?php echo e($profile['state'] ?? ''); ?>"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">Country</label><input class="form-control" name="country" value="<?php echo e($profile['country'] ?? ''); ?>"></div>
                                <div class="col-md-3 mb-3"><label class="form-label">PIN Code</label><input class="form-control" name="pin_code" value="<?php echo e($profile['pin_code'] ?? ''); ?>"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Preferred Language</label><input class="form-control" name="preferred_language" value="<?php echo e($profile['preferred_language'] ?? 'English'); ?>"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Emergency Contact</label><input class="form-control" name="emergency_contact" value="<?php echo e($profile['emergency_contact'] ?? ''); ?>"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Notification Preferences</label><input class="form-control" name="notification_preferences" value="<?php echo e($profile['notification_preferences'] ?? 'Email, Dashboard'); ?>"></div>
                                <div class="col-md-6 mb-3"><label class="form-label">Social Links</label><input class="form-control" name="social_links" value="<?php echo e($profile['social_links'] ?? ''); ?>"></div>
                                <div class="col-12 mb-3"><label class="form-label">Bio / About</label><textarea class="form-control" name="bio" rows="3"><?php echo e($profile['bio'] ?? ''); ?></textarea></div>
                            </div>
                            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="two_factor_enabled" id="two_factor_enabled" <?php echo !empty($profile['two_factor_enabled']) ? 'checked' : ''; ?>><label class="form-check-label" for="two_factor_enabled">Enable Two-Factor Authentication</label></div>
                            <button class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="app-card mb-4">
                    <div class="app-card-body text-center">
                        <?php if (!empty($profile['profile_photo'])): ?><img src="<?php echo e(asset_url($profile['profile_photo'])); ?>" class="rounded-circle mb-3" style="width:96px;height:96px;object-fit:cover;" alt="Profile"><?php endif; ?>
                        <h2 class="h5 mb-1"><?php echo e($profile['full_name'] ?: $profile['username']); ?></h2>
                        <p class="text-muted mb-2"><?php echo e(role_label($profile['role'])); ?> · <?php echo e($profile['account_status'] ?? 'active'); ?></p>
                        <div class="progress"><div class="progress-bar" style="width: <?php echo (int) ($profile['profile_completion'] ?? 0); ?>%"><?php echo (int) ($profile['profile_completion'] ?? 0); ?>%</div></div>
                    </div>
                </div>
                <div class="app-card mb-4"><div class="app-card-header"><h2 class="h5 mb-0">Change Password</h2></div><div class="app-card-body">
                    <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="password">
                        <input type="password" class="form-control mb-2" name="current_password" placeholder="Current password">
                        <input type="password" class="form-control mb-2" name="new_password" placeholder="New password">
                        <input type="password" class="form-control mb-3" name="confirm_password" placeholder="Confirm password">
                        <button class="btn btn-outline-primary w-100">Change Password</button>
                    </form>
                    <?php if (role_alias() === 'customer'): ?><form method="POST" class="mt-2" onsubmit="return confirm('Deactivate this account?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="deactivate"><button class="btn btn-outline-danger w-100">Deactivate Account</button></form><?php endif; ?>
                </div></div>
                <div class="app-card"><div class="app-card-header"><h2 class="h5 mb-0">Login History</h2></div><div class="app-card-body">
                    <?php foreach ($logins as $login): ?><div class="border-bottom py-2"><strong><?php echo $login['success'] ? 'Success' : 'Failed'; ?></strong><br><small><?php echo e($login['ip_address'] . ' · ' . $login['created_at']); ?></small></div><?php endforeach; ?>
                </div></div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
