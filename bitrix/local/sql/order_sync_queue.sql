-- Таблица очереди для повторной отправки заказов в Laravel API.
-- Выполнить в БД Bitrix.

CREATE TABLE IF NOT EXISTS order_sync_queue (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    payload    JSON         NOT NULL,
    attempts   TINYINT UNSIGNED DEFAULT 0,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_order_id (order_id),
    KEY idx_attempts (attempts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
