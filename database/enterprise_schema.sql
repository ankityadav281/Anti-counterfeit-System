USE anti_counterfeit;

ALTER TABLE users MODIFY role ENUM('admin','super_admin','manufacturer','distributor','warehouse_manager','retailer','auditor','user','customer') DEFAULT 'customer';

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);

INSERT IGNORE INTO roles (name, label) VALUES
('super_admin', 'Super Admin'),
('manufacturer', 'Manufacturer'),
('distributor', 'Distributor'),
('warehouse_manager', 'Warehouse Manager'),
('retailer', 'Retailer'),
('auditor', 'Auditor'),
('customer', 'Customer');

ALTER TABLE products ADD COLUMN IF NOT EXISTS product_hash CHAR(64);
ALTER TABLE products ADD COLUMN IF NOT EXISTS digital_signature CHAR(64);
ALTER TABLE products ADD COLUMN IF NOT EXISTS lifecycle_status VARCHAR(80) DEFAULT 'Manufactured';
ALTER TABLE products ADD COLUMN IF NOT EXISTS sold_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE products ADD COLUMN IF NOT EXISTS flagged TINYINT(1) DEFAULT 0;

ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS status VARCHAR(80) DEFAULT 'Genuine Product';
ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) NULL;
ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL;
ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS city VARCHAR(120);
ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS state VARCHAR(120);
ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS country VARCHAR(120);
ALTER TABLE product_verifications ADD COLUMN IF NOT EXISTS risk_score INT DEFAULT 0;

ALTER TABLE complaints ADD COLUMN IF NOT EXISTS product_image VARCHAR(255);
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS invoice_file VARCHAR(255);

CREATE TABLE IF NOT EXISTS qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL UNIQUE,
    qr_payload TEXT NOT NULL,
    secure_hash CHAR(64) NOT NULL,
    digital_signature CHAR(64) NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_logs (
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
);

CREATE TABLE IF NOT EXISTS product_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    stage VARCHAR(80) NOT NULL,
    location VARCHAR(255),
    handled_by INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ownership (
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
);

CREATE TABLE IF NOT EXISTS ownership_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ownership_id INT NOT NULL,
    from_customer VARCHAR(255),
    to_customer VARCHAR(255) NOT NULL,
    transfer_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ownership_id) REFERENCES ownership(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inventory (
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
);

CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    from_location VARCHAR(255),
    to_location VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    movement_type VARCHAR(60) NOT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    type VARCHAR(80) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id, is_read)
);

CREATE TABLE IF NOT EXISTS fraud_logs (
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
);

CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    label VARCHAR(120),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100),
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
