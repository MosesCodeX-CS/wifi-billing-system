-- MySQL schema for Hotspot System
CREATE DATABASE IF NOT EXISTS bellamy_hotspot DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bellamy_hotspot;

-- Payments table (stores M-Pesa CheckoutRequestID and callback)
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  checkout_request_id VARCHAR(128) UNIQUE,
  merchant_request_id VARCHAR(128),
  phone VARCHAR(32),
  amount DECIMAL(10,2),
  status ENUM('PENDING','SUCCESS','FAILED') DEFAULT 'PENDING',
  receipt_number VARCHAR(64),
  result_code INT DEFAULT NULL,
  result_desc VARCHAR(255) DEFAULT NULL,
  raw_callback JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Vouchers table
CREATE TABLE IF NOT EXISTS vouchers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) UNIQUE,
  plan VARCHAR(32),
  duration_seconds INT,
  created_by_payment_id INT DEFAULT NULL,
  used BOOL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  FOREIGN KEY (created_by_payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Sessions / usage table (optional)
CREATE TABLE IF NOT EXISTS sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  voucher_id INT,
  username VARCHAR(128),
  started_at TIMESTAMP NULL,
  ended_at TIMESTAMP NULL,
  uploaded_bytes BIGINT DEFAULT 0,
  downloaded_bytes BIGINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE CASCADE
) ENGINE=InnoDB;
