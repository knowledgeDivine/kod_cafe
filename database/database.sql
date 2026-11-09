-- K.O.D Cafe database
-- Compatible with MySQL 5.7+/MariaDB 10.4+ in XAMPP
CREATE DATABASE IF NOT EXISTS kod_cafe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kod_cafe;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    category ENUM('coffee','dessert','pastry','tea') NOT NULL DEFAULT 'coffee',
    description VARCHAR(500) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    image_url VARCHAR(1000) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_menu_slug (slug),
    KEY idx_menu_category_available (category, is_available),
    KEY idx_menu_featured_sort (is_featured, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NULL,
    subject VARCHAR(100) NOT NULL DEFAULT 'General enquiry',
    message TEXT NOT NULL,
    status ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_status_created (status, created_at),
    KEY idx_messages_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NULL,
    order_number VARCHAR(32) NOT NULL,
    public_token CHAR(64) NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(190) NOT NULL,
    customer_phone VARCHAR(40) NOT NULL,
    fulfillment ENUM('pickup','delivery') NOT NULL DEFAULT 'pickup',
    delivery_address VARCHAR(500) NULL,
    customer_note VARCHAR(1000) NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    loyalty_points_redeemed INT UNSIGNED NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    payment_method ENUM('pay_on_pickup','pay_on_delivery') NOT NULL,
    payment_status ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
    status ENUM('pending','confirmed','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_number (order_number),
    UNIQUE KEY uq_order_public_token (public_token),
    KEY idx_order_status_created (status, created_at),
    KEY idx_order_email_created (customer_email, created_at),
    KEY idx_order_customer_created (customer_id, created_at),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NULL,
    item_name VARCHAR(120) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_menu (menu_item_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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

CREATE TABLE IF NOT EXISTS coffee_recommendations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shade ENUM('caramel','mocha','latte','espresso','rose','matcha') NOT NULL,
    recommended_drink VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recommendation_shade_created (shade, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO menu_items (name, slug, category, description, price, image_url, is_featured, sort_order) VALUES
('Velvet Latte', 'velvet-latte', 'coffee', 'Double espresso, velvety milk and a soft caramel finish.', 3500.00, 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=900&q=85', 1, 1),
('Brown Sugar Cloud', 'brown-sugar-cloud', 'coffee', 'Cold brew, brown sugar and a cloud of cream.', 4000.00, 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?auto=format&fit=crop&w=900&q=85', 1, 2),
('Midnight Chocolate', 'midnight-chocolate', 'dessert', 'Deep cocoa layers finished with smooth chocolate ganache.', 4500.00, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=900&q=85', 1, 3),
('Butter Croissant', 'butter-croissant', 'pastry', 'Golden, flaky and baked for a slow morning.', 2800.00, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=900&q=85', 0, 4),
('Golden Hour Tea', 'golden-hour-tea', 'tea', 'A gentle botanical blend with delicate citrus notes.', 2500.00, 'https://images.unsplash.com/photo-1544787219-7f47acab7655?auto=format&fit=crop&w=900&q=85', 0, 5),
('Little Cloud Cake', 'little-cloud-cake', 'dessert', 'Soft vanilla sponge, whipped cream and berries.', 3800.00, 'https://images.unsplash.com/photo-1486427944299-d1955d23e34d?auto=format&fit=crop&w=900&q=85', 0, 6)
ON DUPLICATE KEY UPDATE name = VALUES(name);


-- Phase 9: customer personalization tables (also provided as a separate migration).


CREATE TABLE IF NOT EXISTS customer_preferences (
    customer_id BIGINT UNSIGNED NOT NULL,
    birth_date DATE NULL,
    sweetness ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    strength ENUM('mild','balanced','strong') NOT NULL DEFAULT 'balanced',
    milk_preference ENUM('dairy','oat','almond','soy','no_milk','any') NOT NULL DEFAULT 'any',
    preferred_flavour ENUM('caramel','chocolate','vanilla','nutty','fruity','floral','earthy','any') NOT NULL DEFAULT 'any',
    preferred_budget DECIMAL(10,2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id),
    KEY idx_customer_preferences_birthday (birth_date),
    CONSTRAINT fk_customer_preferences_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_favorites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_favorite_item (customer_id, menu_item_id),
    KEY idx_customer_favorites_menu (menu_item_id),
    CONSTRAINT fk_customer_favorites_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_customer_favorites_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS birthday_rewards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    reward_year SMALLINT UNSIGNED NOT NULL,
    points_awarded INT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_birthday_customer_year (customer_id, reward_year),
    CONSTRAINT fk_birthday_rewards_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
