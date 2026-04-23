-- Create database
CREATE DATABASE IF NOT EXISTS anti_counterfeit;
USE anti_counterfeit;

-- Create manufacturers table
CREATE TABLE IF NOT EXISTS manufacturers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manufacturer_id INT,
    name VARCHAR(255) NOT NULL,
    product_code VARCHAR(50) NOT NULL UNIQUE,
    batch_number VARCHAR(50) NOT NULL,
    manufacturing_date DATE NOT NULL,
    expiry_date DATE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id)
);

-- Create product verifications table
CREATE TABLE IF NOT EXISTS product_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    verification_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manufacturer', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create manufacturer_users table (for linking manufacturers with their users)
CREATE TABLE IF NOT EXISTS manufacturer_users (
    manufacturer_id INT,
    user_id INT,
    PRIMARY KEY (manufacturer_id, user_id),
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert sample manufacturer
INSERT INTO manufacturers (name, email, phone, address) VALUES
('Sample Manufacturer', 'manufacturer@example.com', '+1234567890', '123 Manufacturing St, Industrial Park');

-- Insert sample product
INSERT INTO products (manufacturer_id, name, product_code, batch_number, manufacturing_date, expiry_date, description) VALUES
(1, 'Sample Product', 'PROD001', 'BATCH001', '2024-01-01', '2025-01-01', 'This is a sample product for testing');

-- Insert sample admin user (password: admin123)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@example.com', '$2y$10$8K1p/a0dL1LXMIZoIqPK6.U/BOkNGx1k3hU9V3UF9T3HJGQZsuHhO', 'admin'); 