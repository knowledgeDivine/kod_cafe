-- K.O.D Customer Accounts + Loyalty Club migration
-- Existing installation requirement: kod_cafe with online orders and order tracking already installed.
-- Import this file ONCE into the existing kod_cafe database using phpMyAdmin.
USE kod_cafe;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    loyalty_points INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_email (email),
    KEY idx_customer_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders
    ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER subtotal,
    ADD COLUMN loyalty_points_redeemed INT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_amount,
    ADD KEY idx_order_customer_created (customer_id, created_at),
    ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    transaction_type ENUM('welcome','earned','redeemed','refund','adjustment') NOT NULL,
    points_delta INT NOT NULL,
    naira_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_loyalty_order_type (order_id, transaction_type),
    KEY idx_loyalty_customer_created (customer_id, created_at),
    CONSTRAINT fk_loyalty_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_loyalty_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing guest orders remain guest orders. No existing administrators, menu items,
-- customer data or order records are deleted or modified by this migration.
