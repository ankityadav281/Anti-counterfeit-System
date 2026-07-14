<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

function app_load_env() {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $env_file = APP_ROOT . '/.env';
    if (!is_readable($env_file)) {
        return;
    }

    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            @putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function app_env($key, $default = null) {
    app_load_env();
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function app_config($key, $default = null) {
    $config = [
        'name' => app_env('APP_NAME', 'Anti-Counterfeit Enterprise Platform'),
        'env' => app_env('APP_ENV', 'local'),
        'debug' => filter_var(app_env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
        'timezone' => app_env('APP_TIMEZONE', 'Asia/Kolkata'),
        'secret_key' => app_env('APP_SECRET_KEY', 'change-this-local-enterprise-secret-key'),
        'db_host' => app_env('DB_HOST', 'localhost'),
        'db_name' => app_env('DB_NAME', 'anti_counterfeit'),
        'db_user' => app_env('DB_USER', 'root'),
        'db_pass' => app_env('DB_PASS', ''),
        'db_charset' => app_env('DB_CHARSET', 'utf8mb4'),
    ];

    return $config[$key] ?? $default;
}

function app_is_debug() {
    return (bool) app_config('debug', false);
}

function app_log($message, array $context = []) {
    $log_dir = APP_ROOT . '/storage/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0775, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context) {
        $line .= ' ' . json_encode($context);
    }
    @file_put_contents($log_dir . '/app.log', $line . PHP_EOL, FILE_APPEND);
}

function app_bootstrap() {
    static $booted = false;
    if ($booted) {
        return;
    }
    $booted = true;

    date_default_timezone_set(app_config('timezone', 'Asia/Kolkata'));

    if (app_is_debug()) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
    }

    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');
    }
}

app_bootstrap();
?>
