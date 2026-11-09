-- K.O.D customer order tracking upgrade
-- Import this ONE TIME into the existing kod_cafe database after the online-ordering upgrade.
-- This migration preserves existing menu items, admins and orders.
USE kod_cafe;

ALTER TABLE orders
    MODIFY status ENUM('pending','confirmed','preparing','ready','out_for_delivery','completed','cancelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN estimated_ready_at DATETIME NULL AFTER status;

CREATE TABLE IF NOT EXISTS order_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(32) NULL,
    to_status VARCHAR(32) NOT NULL,
    note VARCHAR(255) NULL,
    changed_by_admin_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_history_order_created (order_id, created_at),
    CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_history_admin FOREIGN KEY (changed_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Give existing orders a starting tracking event without duplicating history on re-import.
INSERT INTO order_status_history (order_id, from_status, to_status, note, created_at)
SELECT o.id, NULL, o.status, 'Tracking history started', o.created_at
FROM orders o
WHERE NOT EXISTS (
    SELECT 1 FROM order_status_history h WHERE h.order_id = o.id
);
