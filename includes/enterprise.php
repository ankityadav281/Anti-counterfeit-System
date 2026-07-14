<?php
require_once __DIR__ . '/helpers.php';

if (!defined('APP_SECRET_KEY')) {
    define('APP_SECRET_KEY', app_config('secret_key', 'change-this-local-enterprise-secret-key'));
}

function enterprise_bootstrap(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        label VARCHAR(100) NOT NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS qr_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL UNIQUE,
        qr_payload TEXT NOT NULL,
        secure_hash CHAR(64) NOT NULL,
        digital_signature CHAR(64) NOT NULL,
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT NULL,
        product_id INT NULL,
        previous_status VARCHAR(100),
        new_status VARCHAR(100),
        user_id INT NULL,
        location VARCHAR(255),
        metadata TEXT,
        record_hash CHAR(64) NOT NULL,
        previous_record_hash CHAR(64),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_product (product_id),
        INDEX idx_audit_hash (record_hash)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS product_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        stage VARCHAR(80) NOT NULL,
        location VARCHAR(255),
        handled_by INT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS ownership (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        customer_user_id INT NULL,
        customer_name VARCHAR(255) NOT NULL,
        purchase_date DATE NOT NULL,
        invoice_number VARCHAR(100) NOT NULL,
        ownership_status ENUM('active','transferred','revoked') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_product_invoice (product_id, invoice_number),
        INDEX idx_ownership_product (product_id)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS ownership_transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ownership_id INT NOT NULL,
        from_customer VARCHAR(255),
        to_customer VARCHAR(255) NOT NULL,
        transfer_date DATE NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ownership_id) REFERENCES ownership(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        location_type ENUM('manufacturer','warehouse','distributor','retailer') NOT NULL,
        location_name VARCHAR(255) NOT NULL,
        batch_number VARCHAR(50),
        quantity INT NOT NULL DEFAULT 0,
        low_stock_threshold INT NOT NULL DEFAULT 10,
        expiry_date DATE NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_inventory_scope (product_id, location_type, location_name, batch_number),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS inventory_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        from_location VARCHAR(255),
        to_location VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        movement_type VARCHAR(60) NOT NULL,
        user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        type VARCHAR(80) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notifications_user (user_id, is_read)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS fraud_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NULL,
        product_code VARCHAR(100),
        risk_score INT NOT NULL DEFAULT 0,
        risk_level ENUM('Low','Medium','High') NOT NULL DEFAULT 'Low',
        reason TEXT,
        city VARCHAR(120),
        state VARCHAR(120),
        country VARCHAR(120),
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fraud_product (product_id),
        INDEX idx_fraud_level (risk_level)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS api_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        label VARCHAR(120),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS login_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        username VARCHAR(100),
        success TINYINT(1) NOT NULL DEFAULT 0,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = [
        "ALTER TABLE users MODIFY role ENUM('admin','super_admin','manufacturer','distributor','warehouse_manager','retailer','auditor','user','customer') DEFAULT 'customer'",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS product_hash CHAR(64)",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS digital_signature CHAR(64)",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS lifecycle_status VARCHAR(80) DEFAULT 'Manufactured'",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS sold_at TIMESTAMP NULL DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS flagged TINYINT(1) DEFAULT 0",
        "ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS status VARCHAR(80) DEFAULT 'Genuine Product'",
        "ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL",
        "ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL",
        "ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS city VARCHAR(120)",
        "ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS state VARCHAR(120)",
        "ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS country VARCHAR(120)",
        "ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS risk_score INT DEFAULT 0",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS product_image VARCHAR(255)",
        "ALTER TABLE complaints ADD COLUMN IF NOT EXISTS invoice_file VARCHAR(255)"
    ];

    foreach ($columns as $sql) {
        try {
            $db->exec($sql);
        } catch (PDOException $e) {
        }
    }

    $roles = [
        'super_admin' => 'Super Admin',
        'manufacturer' => 'Manufacturer',
        'distributor' => 'Distributor',
        'warehouse_manager' => 'Warehouse Manager',
        'retailer' => 'Retailer',
        'auditor' => 'Auditor',
        'customer' => 'Customer',
    ];
    $stmt = $db->prepare("INSERT IGNORE INTO roles (name, label) VALUES (:name, :label)");
    foreach ($roles as $name => $label) {
        $stmt->execute([':name' => $name, ':label' => $label]);
    }

    enterprise_phase2_bootstrap($db);
}

function enterprise_phase2_bootstrap(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS user_profiles (
        user_id INT PRIMARY KEY,
        profile_photo VARCHAR(255),
        full_name VARCHAR(255),
        employee_id VARCHAR(80),
        company_name VARCHAR(255),
        department VARCHAR(120),
        designation VARCHAR(120),
        phone VARCHAR(30),
        alternate_phone VARCHAR(30),
        date_of_birth DATE NULL,
        gender VARCHAR(40),
        address TEXT,
        city VARCHAR(120),
        state VARCHAR(120),
        country VARCHAR(120),
        pin_code VARCHAR(20),
        date_joined DATE NULL,
        account_status ENUM('active','inactive','pending','suspended','deactivated') DEFAULT 'active',
        last_password_change TIMESTAMP NULL,
        preferred_language VARCHAR(80) DEFAULT 'English',
        notification_preferences TEXT,
        emergency_contact VARCHAR(255),
        bio TEXT,
        social_links TEXT,
        two_factor_enabled TINYINT(1) DEFAULT 0,
        profile_completion INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        owner_user_id INT NULL,
        company_name VARCHAR(255) NOT NULL,
        gst_number VARCHAR(80),
        license_number VARCHAR(120),
        brand_logo VARCHAR(255),
        address TEXT,
        contact_person VARCHAR(255),
        factory_details TEXT,
        product_categories TEXT,
        authorized_retailers TEXT,
        authorized_distributors TEXT,
        company_status ENUM('pending','approved','rejected','suspended') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS product_batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        manufacturer_id INT NULL,
        batch_number VARCHAR(100) NOT NULL,
        manufacturing_date DATE NOT NULL,
        expiry_date DATE NULL,
        production_unit VARCHAR(120),
        factory VARCHAR(255),
        supervisor VARCHAR(255),
        batch_status ENUM('pending','approved','rejected','recalled','completed') DEFAULT 'pending',
        quality_report TEXT,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_batch_manufacturer (manufacturer_id, batch_number)
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS business_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NULL,
        receiver_role VARCHAR(50),
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        attachment_path VARCHAR(255),
        related_message_id INT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_important TINYINT(1) DEFAULT 0,
        is_pinned TINYINT(1) DEFAULT 0,
        is_archived TINYINT(1) DEFAULT 0,
        deleted_by_sender TINYINT(1) DEFAULT 0,
        deleted_by_receiver TINYINT(1) DEFAULT 0,
        read_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_msg_receiver (receiver_id, receiver_role, is_read),
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS workflow_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_type VARCHAR(80) NOT NULL,
        requester_id INT NULL,
        approver_role VARCHAR(50),
        approver_id INT NULL,
        entity_type VARCHAR(80),
        entity_id INT NULL,
        title VARCHAR(255) NOT NULL,
        details TEXT,
        status ENUM('pending','approved','rejected','cancelled','completed') DEFAULT 'pending',
        current_step VARCHAR(120),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS customer_purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        qr_id INT NULL,
        customer_user_id INT NULL,
        customer_name VARCHAR(255),
        customer_email VARCHAR(255),
        phone VARCHAR(30),
        retailer_id INT NULL,
        retailer_name VARCHAR(255),
        purchase_date DATE,
        verification_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        verification_location VARCHAR(255),
        invoice_number VARCHAR(120),
        warranty_status VARCHAR(80) DEFAULT 'Active',
        ownership_status VARCHAR(80) DEFAULT 'active',
        UNIQUE KEY uq_purchase_customer_product (product_id, customer_user_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS warranty_claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        customer_user_id INT NULL,
        retailer_approved TINYINT(1) DEFAULT 0,
        manufacturer_approved TINYINT(1) DEFAULT 0,
        claim_status ENUM('pending','retailer_approved','manufacturer_approved','rejected','completed') DEFAULT 'pending',
        claim_reason TEXT,
        invoice_file VARCHAR(255),
        warranty_start DATE NULL,
        warranty_expiry DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS product_recalls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NULL,
        batch_number VARCHAR(100),
        manufacturer_user_id INT NULL,
        reason ENUM('Manufacturing defect','Safety issue','Quality issue','Fake batch detected') NOT NULL,
        description TEXT,
        recall_status ENUM('active','completed','cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        role VARCHAR(50),
        activity VARCHAR(120) NOT NULL,
        entity_type VARCHAR(80),
        entity_id INT NULL,
        ip_address VARCHAR(45),
        browser TEXT,
        location VARCHAR(255),
        status VARCHAR(80),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_user (user_id, created_at)
    )");

    $columns = [
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL DEFAULT NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS warranty_period_months INT DEFAULT 12",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS warranty_start DATE NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS warranty_expiry DATE NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS retailer_user_id INT NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS distributor_user_id INT NULL",
        "ALTER TABLE products ADD COLUMN IF NOT EXISTS warehouse_user_id INT NULL",
        "ALTER TABLE inventory_movements ADD COLUMN IF NOT EXISTS acknowledged_at TIMESTAMP NULL DEFAULT NULL",
        "ALTER TABLE inventory_movements ADD COLUMN IF NOT EXISTS receiver_user_id INT NULL",
        "ALTER TABLE business_messages ADD COLUMN IF NOT EXISTS is_pinned TINYINT(1) DEFAULT 0"
    ];

    foreach ($columns as $sql) {
        try {
            $db->exec($sql);
        } catch (PDOException $e) {
        }
    }
}

function activity_log(PDO $db, $activity, $entity_type = null, $entity_id = null, $status = 'success', $location = '') {
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, role, activity, entity_type, entity_id, ip_address, browser, location, status)
        VALUES (:user_id, :role, :activity, :entity_type, :entity_id, :ip, :browser, :location, :status)");
    $stmt->execute([
        ':user_id' => $_SESSION['user_id'] ?? null,
        ':role' => role_alias(),
        ':activity' => $activity,
        ':entity_type' => $entity_type,
        ':entity_id' => $entity_id,
        ':ip' => client_ip(),
        ':browser' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ':location' => $location,
        ':status' => $status,
    ]);
}

function profile_completion(array $profile) {
    $fields = ['full_name','company_name','department','designation','phone','date_of_birth','gender','address','city','state','country','pin_code','date_joined','preferred_language','notification_preferences','emergency_contact','bio'];
    $filled = 0;
    foreach ($fields as $field) {
        if (!empty($profile[$field])) {
            $filled++;
        }
    }

    return (int) round(($filled / count($fields)) * 100);
}

function can_message_role($sender_role, $receiver_role) {
    $sender_role = role_alias($sender_role);
    $receiver_role = role_alias($receiver_role);
    if ($sender_role === 'super_admin') {
        return true;
    }
    $allowed = [
        'manufacturer' => ['distributor'],
        'distributor' => ['retailer'],
        'retailer' => ['customer', 'manufacturer'],
        'customer' => ['retailer'],
        'auditor' => ['super_admin'],
    ];

    return in_array($receiver_role, $allowed[$sender_role] ?? [], true);
}

function product_secure_hash($product_code, $manufacturer_id, $timestamp) {
    return hash('sha256', $product_code . '|' . $manufacturer_id . '|' . $timestamp . '|' . APP_SECRET_KEY);
}

function digital_signature($hash) {
    return hash_hmac('sha256', $hash, APP_SECRET_KEY);
}

function qr_payload($product_code, $manufacturer_id, $timestamp, $hash, $signature) {
    return json_encode([
        'product_id' => $product_code,
        'manufacturer_id' => (int) $manufacturer_id,
        'timestamp' => $timestamp,
        'secure_hash' => $hash,
        'digital_signature' => $signature,
    ]);
}

function parse_qr_or_code($input) {
    $input = trim($input);
    $decoded = json_decode($input, true);
    if (is_array($decoded) && isset($decoded['product_id'])) {
        return $decoded;
    }

    return ['product_id' => strtoupper($input)];
}

function create_notification(PDO $db, $type, $title, $message, $user_id = null) {
    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (:user_id, :type, :title, :message)");
    $stmt->execute([':user_id' => $user_id, ':type' => $type, ':title' => $title, ':message' => $message]);
}

function audit_log(PDO $db, $entity_type, $entity_id, $product_id, $previous_status, $new_status, $location = '', array $metadata = []) {
    $previous_hash = $db->query("SELECT record_hash FROM audit_logs ORDER BY id DESC LIMIT 1")->fetchColumn() ?: '';
    $user_id = $_SESSION['user_id'] ?? null;
    $payload = implode('|', [$entity_type, $entity_id, $product_id, $previous_status, $new_status, $user_id, $location, json_encode($metadata), $previous_hash, microtime(true)]);
    $record_hash = hash('sha256', $payload);
    $stmt = $db->prepare("INSERT INTO audit_logs
        (entity_type, entity_id, product_id, previous_status, new_status, user_id, location, metadata, record_hash, previous_record_hash)
        VALUES (:entity_type, :entity_id, :product_id, :previous_status, :new_status, :user_id, :location, :metadata, :record_hash, :previous_record_hash)");
    $stmt->execute([
        ':entity_type' => $entity_type,
        ':entity_id' => $entity_id,
        ':product_id' => $product_id,
        ':previous_status' => $previous_status,
        ':new_status' => $new_status,
        ':user_id' => $user_id,
        ':location' => $location,
        ':metadata' => json_encode($metadata),
        ':record_hash' => $record_hash,
        ':previous_record_hash' => $previous_hash ?: null,
    ]);
}

function assess_fraud(PDO $db, $product, $city, $state, $country, $ip) {
    $score = 0;
    $reasons = [];
    $product_id = $product['id'] ?? null;
    $product_code = $product['product_code'] ?? '';

    if ($product_id) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM product_verifications WHERE product_id = :id AND verification_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute([':id' => $product_id]);
        if ((int) $stmt->fetchColumn() >= 100) {
            $score += 35;
            $reasons[] = 'High scan volume in 24 hours';
        }

        if ($city !== '') {
            $stmt = $db->prepare("SELECT city FROM product_verifications WHERE product_id = :id AND city IS NOT NULL AND city <> '' AND verification_date >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY verification_date DESC LIMIT 1");
            $stmt->execute([':id' => $product_id]);
            $last_city = (string) $stmt->fetchColumn();
            if ($last_city !== '' && strcasecmp($last_city, $city) !== 0) {
                $score += 40;
                $reasons[] = 'Same product scanned in different cities within 30 minutes';
            }
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM product_verifications WHERE product_id = :id AND ip_address = :ip AND verification_date >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmt->execute([':id' => $product_id, ':ip' => $ip]);
        if ((int) $stmt->fetchColumn() >= 3) {
            $score += 25;
            $reasons[] = 'Repeated scans from same IP in one minute';
        }
    } else {
        $score += 65;
        $reasons[] = 'Unknown or invalid product identity';
    }

    $level = $score >= 70 ? 'High' : ($score >= 35 ? 'Medium' : 'Low');
    if ($score > 0) {
        $stmt = $db->prepare("INSERT INTO fraud_logs (product_id, product_code, risk_score, risk_level, reason, city, state, country, ip_address)
            VALUES (:product_id, :product_code, :risk_score, :risk_level, :reason, :city, :state, :country, :ip_address)");
        $stmt->execute([
            ':product_id' => $product_id,
            ':product_code' => $product_code,
            ':risk_score' => $score,
            ':risk_level' => $level,
            ':reason' => implode('; ', $reasons),
            ':city' => $city,
            ':state' => $state,
            ':country' => $country,
            ':ip_address' => $ip,
        ]);
    }

    return ['score' => $score, 'level' => $level, 'reasons' => $reasons];
}

function export_csv($filename, array $headers, array $rows) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
?>
