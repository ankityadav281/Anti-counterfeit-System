<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';

// Decode JSON input
$input = json_decode(file_get_contents('php://input'), true);

$accessToken = $input['access_token'] ?? '';
$role = $input['role'] ?? 'customer'; // Default role if new registration
$isMock = !empty($input['is_mock']);

$email = '';
$name = '';
$googleId = '';

if ($isMock) {
    // Only allow mock login on local / debug environment
    if (!app_is_debug()) {
        echo json_encode(['success' => false, 'error' => 'Mock login is only allowed in debug mode.']);
        exit();
    }
    $email = $input['mock_email'] ?? '';
    $name = $input['mock_name'] ?? 'Mock User';
    $googleId = 'mock_' . md5($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid mock email.']);
        exit();
    }
} else {
    if (empty($accessToken)) {
        echo json_encode(['success' => false, 'error' => 'Access token is required.']);
        exit();
    }

    // Call Google UserInfo API to verify access token and fetch profile
    $url = 'https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . urlencode($accessToken);
    
    // Set a short timeout for the API call
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\n",
            'timeout' => 5
        ]
    ];
    $context = stream_context_create($opts);
    $responseJson = @file_get_contents($url, false, $context);
    
    if ($responseJson === false) {
        // Try curl as fallback
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $responseJson = curl_exec($ch);
            curl_close($ch);
        }
    }

    if (!$responseJson) {
        echo json_encode(['success' => false, 'error' => 'Failed to verify Google access token.']);
        exit();
    }

    $profile = json_decode($responseJson, true);
    if (empty($profile['email'])) {
        echo json_encode(['success' => false, 'error' => 'Google profile did not contain an email address.']);
        exit();
    }

    $email = $profile['email'];
    $name = $profile['name'] ?? $profile['given_name'] ?? 'Google User';
    $googleId = $profile['sub'] ?? '';
}

try {
    $database = new Database();
    $db = $database->getConnection();
    enterprise_bootstrap($db);

    // Check if the user already exists by email
    $query = "SELECT id, username, role FROM users WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Check account status
        $profile_status = $db->prepare("SELECT account_status FROM user_profiles WHERE user_id = :id LIMIT 1");
        $profile_status->execute([':id' => $user['id']]);
        $account_status = $profile_status->fetchColumn() ?: 'active';
        
        if (in_array($account_status, ['inactive', 'suspended', 'deactivated'], true)) {
            echo json_encode(['success' => false, 'error' => 'This account is not active. Please contact your administrator.']);
            exit();
        }

        // Existing user: Log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Save login history
        $history = $db->prepare("INSERT INTO login_history (user_id, username, success, ip_address, user_agent) VALUES (:user_id, :username, 1, :ip, :agent)");
        $history->execute([
            ':user_id' => $user['id'], 
            ':username' => $user['username'], 
            ':ip' => client_ip(), 
            ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        activity_log($db, 'User logged in via Google', 'user', $user['id']);

        echo json_encode(['success' => true, 'redirect' => page_url('dashboard')]);
        exit();
    } else {
        // User does not exist: Register them automatically
        
        // Generate a unique username from email prefix
        $baseUsername = preg_replace('/[^A-Za-z0-9_]/', '', explode('@', $email)[0]);
        if (strlen($baseUsername) < 3) {
            $baseUsername = "user_" . substr(md5(uniqid()), 0, 6);
        }
        
        $username = $baseUsername;
        $count = 1;
        
        // Check uniqueness of username
        $checkQuery = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
        while (true) {
            $checkQuery->execute([':username' => $username]);
            if (!$checkQuery->fetch()) {
                break;
            }
            $username = $baseUsername . $count;
            $count++;
        }

        // Insert new user with a random password
        $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $db->beginTransaction();
        
        $insertQuery = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
        $insertQuery->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $randomPassword,
            ':role' => $role
        ]);
        
        $userId = $db->lastInsertId();

        // Create a default profile
        $insertProfile = $db->prepare("INSERT INTO user_profiles (user_id, full_name, account_status, date_joined) VALUES (:user_id, :full_name, 'active', CURDATE())");
        $insertProfile->execute([
            ':user_id' => $userId,
            ':full_name' => $name
        ]);

        $db->commit();

        // Log them in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;

        // Save login history
        $history = $db->prepare("INSERT INTO login_history (user_id, username, success, ip_address, user_agent) VALUES (:user_id, :username, 1, :ip, :agent)");
        $history->execute([
            ':user_id' => $userId, 
            ':username' => $username, 
            ':ip' => client_ip(), 
            ':agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        activity_log($db, 'User registered and logged in via Google', 'user', $userId);

        echo json_encode(['success' => true, 'redirect' => page_url('dashboard')]);
        exit();
    }
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error during Google login: ' . $e->getMessage()]);
    exit();
}
