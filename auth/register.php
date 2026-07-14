<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';

if (is_logged_in()) {
    header("Location: " . page_url('dashboard'));
    exit();
}

$errors = [];
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $account_type = $_POST['account_type'] ?? 'customer';

    if ($username === '' || !preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors[] = "Username must be 3-30 characters and use only letters, numbers, or underscores.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (!in_array($account_type, ['manufacturer', 'distributor', 'warehouse_manager', 'retailer', 'auditor', 'customer'], true)) {
        $errors[] = "Please choose a valid account type.";
    }

    if (empty($errors)) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            enterprise_bootstrap($db);

            $check_query = "SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(":username", $username);
            $check_stmt->bindParam(":email", $email);
            $check_stmt->execute();

            if ($check_stmt->fetch()) {
                $errors[] = "Username or email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":username", $username);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":password", $hashed_password);
                $stmt->bindParam(":role", $account_type);
                $stmt->execute();

                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $account_type;

                header("Location: " . page_url('dashboard'));
                exit();
            }
        } catch (PDOException $e) {
            $errors[] = "Registration failed. Please check your database setup.";
        }
    }
}

$page_title = 'Create Account - Anti-Counterfeit System';
$active_page = 'register';
include __DIR__ . '/../includes/header.php';
?>
<section class="auth-shell page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="app-card">
                    <div class="app-card-header">
                        <h1 class="h4 mb-1"><i class="fas fa-user-plus me-2 text-primary"></i>Create Account</h1>
                        <p class="text-muted mb-0">Register to access your dashboard and product tools.</p>
                    </div>
                    <div class="app-card-body">
                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo e($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo e($success); ?></div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-dark provider-button" type="button" data-google-auth>
                                <i class="fab fa-google me-2"></i>Continue with Google
                            </button>
                            <button class="btn btn-outline-primary provider-button" type="button" onclick="document.getElementById('email').focus();">
                                <i class="fas fa-envelope me-2"></i>Continue with Email
                            </button>
                        </div>

                        <div class="auth-divider">or create account with email</div>

                        <form method="POST" action="register.php">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo e($_POST['username'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Register As</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input class="btn-check" type="radio" name="account_type" id="account_manufacturer" value="manufacturer" <?php echo ($_POST['account_type'] ?? '') === 'manufacturer' ? 'checked' : ''; ?> required>
                                        <label class="btn btn-outline-primary w-100 text-start" for="account_manufacturer">
                                            <i class="fas fa-industry me-2"></i> Manufacturer
                                            <small class="d-block text-muted">Register and manage products.</small>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <input class="btn-check" type="radio" name="account_type" id="account_user" value="customer" <?php echo ($_POST['account_type'] ?? 'customer') === 'customer' ? 'checked' : ''; ?> required>
                                        <label class="btn btn-outline-primary w-100 text-start" for="account_user">
                                            <i class="fas fa-user me-2"></i> Customer
                                            <small class="d-block text-muted">Verify products and complain.</small>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <input class="btn-check" type="radio" name="account_type" id="account_retailer" value="retailer" <?php echo ($_POST['account_type'] ?? '') === 'retailer' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-primary w-100 text-start" for="account_retailer">
                                            <i class="fas fa-store me-2"></i> Retailer
                                            <small class="d-block text-muted">Manage sales and ownership.</small>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <input class="btn-check" type="radio" name="account_type" id="account_auditor" value="auditor" <?php echo ($_POST['account_type'] ?? '') === 'auditor' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-primary w-100 text-start" for="account_auditor">
                                            <i class="fas fa-clipboard-check me-2"></i> Auditor
                                            <small class="d-block text-muted">Review audit and fraud data.</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimum 8 characters.</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="confirm_password" aria-label="Show password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">I agree to responsible use of this verification system.</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Create Account</button>
                        </form>

                        <p class="text-center mt-4 mb-0">Already have an account? <a href="login.php">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
