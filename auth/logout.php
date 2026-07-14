<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/enterprise.php';

if (isset($_SESSION['user_id'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        enterprise_bootstrap($db);
        activity_log($db, 'User logged out', 'user', (int) $_SESSION['user_id']);
    } catch (Exception $e) {
    }
}

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: " . page_url('login'));
exit();
?> 
