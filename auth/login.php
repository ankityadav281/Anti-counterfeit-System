<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';

if (is_logged_in()) {
    header("Location: " . page_url('dashboard'));
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Username and password are required.";
    } else {
        try {
            $database = new Database();
            $db = $database->getConnection();
            enterprise_bootstrap($db);
            $query = "SELECT id, username, password, role FROM users WHERE username = :username LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $profile_status = $db->prepare("SELECT account_status FROM user_profiles WHERE user_id = :id LIMIT 1");
                $profile_status->execute([':id' => $user['id']]);
                $account_status = $profile_status->fetchColumn() ?: 'active';
                if (in_array($account_status, ['inactive', 'suspended', 'deactivated'], true)) {
                    $error = "This account is not active. Please contact your administrator.";
                    $history = $db->prepare("INSERT INTO login_history (user_id, username, success, ip_address, user_agent) VALUES (:user_id, :username, 0, :ip, :agent)");
                    $history->execute([':user_id' => $user['id'], ':username' => $user['username'], ':ip' => client_ip(), ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
                } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $history = $db->prepare("INSERT INTO login_history (user_id, username, success, ip_address, user_agent) VALUES (:user_id, :username, 1, :ip, :agent)");
                $history->execute([':user_id' => $user['id'], ':username' => $user['username'], ':ip' => client_ip(), ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
                activity_log($db, 'User logged in', 'user', $user['id']);
                header("Location: " . page_url('dashboard'));
                exit();
                }
            }

            if ($error === '') {
                $history = $db->prepare("INSERT INTO login_history (username, success, ip_address, user_agent) VALUES (:username, 0, :ip, :agent)");
                $history->execute([':username' => $username, ':ip' => client_ip(), ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']);
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Login failed. Please check your database connection.";
        }
    }
}

$page_title = 'Login - Anti-Counterfeit System';
$active_page = 'login';
include __DIR__ . '/../includes/header.php';
?>
<section class="auth-shell page-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="app-card">
                    <div class="app-card-header">
                        <h1 class="h4 mb-1"><i class="fas fa-right-to-bracket me-2 text-primary"></i>Login</h1>
                        <p class="text-muted mb-0">Access product management and verification analytics.</p>
                    </div>
                    <div class="app-card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo e($error); ?></div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-dark provider-button" type="button" data-google-auth>
                                <i class="fab fa-google me-2"></i>Continue with Google
                            </button>
                            <button class="btn btn-outline-primary provider-button" type="button" onclick="document.getElementById('username').focus();">
                                <i class="fas fa-envelope me-2"></i>Continue with Email
                            </button>
                        </div>

                        <div class="auth-divider">or login with username</div>

                        <form method="POST" action="login.php">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="password" aria-label="Show password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Login</button>
                        </form>

                        <p class="text-center mt-4 mb-0">New here? <a href="register.php">Create an account</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
