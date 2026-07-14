USE anti_counterfeit;

CREATE TABLE IF NOT EXISTS user_profiles (
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
);

CREATE TABLE IF NOT EXISTS companies (
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
);

CREATE TABLE IF NOT EXISTS product_batches (
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
);

CREATE TABLE IF NOT EXISTS business_messages (
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
);

CREATE TABLE IF NOT EXISTS workflow_requests (
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
);

CREATE TABLE IF NOT EXISTS customer_purchases (
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
);

CREATE TABLE IF NOT EXISTS warranty_claims (
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
);

CREATE TABLE IF NOT EXISTS product_recalls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NULL,
    batch_number VARCHAR(100),
    manufacturer_user_id INT NULL,
    reason ENUM('Manufacturing defect','Safety issue','Quality issue','Fake batch detected') NOT NULL,
    description TEXT,
    recall_status ENUM('active','completed','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
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
);

ALTER TABLE products ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS warranty_period_months INT DEFAULT 12;
ALTER TABLE products ADD COLUMN IF NOT EXISTS warranty_start DATE NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS warranty_expiry DATE NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS retailer_user_id INT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS distributor_user_id INT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS warehouse_user_id INT NULL;
ALTER TABLE inventory_movements ADD COLUMN IF NOT EXISTS acknowledged_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE inventory_movements ADD COLUMN IF NOT EXISTS receiver_user_id INT NULL;
ALTER TABLE business_messages ADD COLUMN IF NOT EXISTS is_pinned TINYINT(1) DEFAULT 0;
