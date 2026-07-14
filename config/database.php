<?php
require_once __DIR__ . '/app.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;

    public function __construct() {
        $this->host = app_config('db_host', 'localhost');
        $this->db_name = app_config('db_name', 'anti_counterfeit');
        $this->username = app_config('db_user', 'root');
        $this->password = app_config('db_pass', '');
        $this->charset = app_config('db_charset', 'utf8mb4');
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]
            );
            $this->conn->exec("set names " . $this->charset);
        } catch(PDOException $e) {
            app_log('Database connection failed', ['error' => $e->getMessage()]);
            if (app_is_debug()) {
                echo "Connection error: " . $e->getMessage();
            } else {
                echo "Database connection failed. Please contact the system administrator.";
            }
        }

        return $this->conn;
    }
}
?> 
