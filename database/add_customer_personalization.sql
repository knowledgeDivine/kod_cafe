-- K.O.D Phase 9: customer personalization, favourites and birthday rewards.
-- Import ONCE into the existing kod_cafe database after the Phase 5 loyalty migration.
USE kod_cafe;

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
