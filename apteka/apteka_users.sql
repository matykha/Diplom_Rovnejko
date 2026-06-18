-- ============================================================
--  БАЗА ДАННЫХ: ПОЛЬЗОВАТЕЛИ И КОРЗИНА
--  MariaDB / MySQL 10.4+
--  Регистрация по логину + паролю, корзина сохраняется
-- ============================================================

USE apteka_db;

-- ============================================================
-- 1. ПОЛЬЗОВАТЕЛИ
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    login          VARCHAR(50)  UNIQUE NOT NULL  COMMENT 'Логин для входа',
    password_hash  VARCHAR(255) NOT NULL          COMMENT 'Хэш пароля (bcrypt)',
    full_name      VARCHAR(100)                   COMMENT 'Полное имя',
    phone          VARCHAR(30)                    COMMENT 'Телефон',
    email          VARCHAR(100)                   COMMENT 'Email (необязательно)',
    role           ENUM('customer','admin') DEFAULT 'customer',
    is_active      BOOLEAN DEFAULT TRUE,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login     TIMESTAMP NULL,
    INDEX idx_login (login)
);

-- ============================================================
-- 2. СЕССИИ ВХОДА (токены авторизации)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    user_id      INT NOT NULL,
    token        VARCHAR(128) UNIQUE NOT NULL  COMMENT 'Токен из cookie/localStorage',
    ip_address   VARCHAR(45)                   COMMENT 'IP пользователя',
    user_agent   VARCHAR(500)                  COMMENT 'Браузер',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at   TIMESTAMP NOT NULL             COMMENT 'Срок действия сессии',
    INDEX idx_token   (token),
    INDEX idx_user    (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 3. КОРЗИНА ПОЛЬЗОВАТЕЛЯ (сохраняется между сессиями)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_cart (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    user_id     INT NOT NULL,
    product_id  INT NOT NULL,
    quantity    INT NOT NULL DEFAULT 1       COMMENT 'Количество упаковок',
    price       DECIMAL(10,2) NOT NULL        COMMENT 'Цена на момент добавления',
    added_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_product (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ============================================================
-- 4. ИСТОРИЯ ЗАКАЗОВ ПОЛЬЗОВАТЕЛЯ
-- ============================================================
CREATE TABLE IF NOT EXISTS user_orders (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    user_id        INT NOT NULL,
    order_number   VARCHAR(20) UNIQUE NOT NULL,
    pharmacy_id    INT NOT NULL,
    status         ENUM('new','confirmed','ready','completed','cancelled','expired')
                   DEFAULT 'new',
    total_amount   DECIMAL(10,2) NOT NULL,
    reserved_until DATETIME,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (user_id)     REFERENCES users(id)       ON DELETE RESTRICT,
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id)  ON DELETE RESTRICT
);

-- Позиции заказа
CREATE TABLE IF NOT EXISTS user_order_items (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    order_id    INT NOT NULL,
    product_id  INT NOT NULL,
    quantity    INT NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES user_orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)     ON DELETE RESTRICT
);

-- ============================================================
-- 5. ТРИГГЕР: номер заказа и срок брони
-- ============================================================
DELIMITER $$
CREATE TRIGGER trg_user_order_before_insert
BEFORE INSERT ON user_orders
FOR EACH ROW
BEGIN
    IF NEW.order_number IS NULL OR NEW.order_number = '' THEN
        SET NEW.order_number = CONCAT(
            'ORD-', YEAR(NOW()), '-',
            LPAD((SELECT IFNULL(MAX(id), 0) + 1 FROM user_orders), 5, '0')
        );
    END IF;
    IF NEW.reserved_until IS NULL THEN
        SET NEW.reserved_until = DATE_ADD(NOW(), INTERVAL 3 HOUR);
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- 6. ПРЕДСТАВЛЕНИЯ (VIEWS) ДЛЯ УДОБНОГО ПРОСМОТРА
-- ============================================================

-- Корзина с полной информацией о товарах
CREATE OR REPLACE VIEW v_user_cart AS
SELECT
    u.id           AS user_id,
    u.login,
    u.full_name,
    u.phone,
    p.id           AS product_id,
    p.name         AS product_name,
    p.form         AS product_form,
    p.icon         AS product_icon,
    p.slug         AS product_slug,
    uc.quantity,
    uc.price       AS unit_price,
    ROUND(uc.quantity * uc.price, 2) AS line_total,
    uc.added_at,
    uc.updated_at
FROM user_cart uc
JOIN users    u ON u.id = uc.user_id
JOIN products p ON p.id = uc.product_id
ORDER BY u.login, uc.added_at DESC;

-- Итог корзины по каждому пользователю
CREATE OR REPLACE VIEW v_cart_summary AS
SELECT
    u.id        AS user_id,
    u.login,
    u.full_name,
    u.phone,
    COUNT(uc.product_id)            AS items_count,
    SUM(uc.quantity)                AS total_qty,
    ROUND(SUM(uc.quantity * uc.price), 2) AS total_amount,
    MAX(uc.updated_at)              AS last_updated
FROM users u
JOIN user_cart uc ON uc.user_id = u.id
GROUP BY u.id, u.login, u.full_name, u.phone
ORDER BY last_updated DESC;

-- Все заказы с составом
CREATE OR REPLACE VIEW v_user_orders AS
SELECT
    uo.order_number,
    u.login,
    u.full_name,
    u.phone,
    ph.address     AS pharmacy_address,
    uo.status,
    uo.total_amount,
    uo.reserved_until,
    GROUP_CONCAT(
        CONCAT(p.name, ' × ', uoi.quantity, ' уп.')
        ORDER BY p.name SEPARATOR ', '
    )              AS items,
    uo.created_at
FROM user_orders uo
JOIN users            u   ON u.id   = uo.user_id
JOIN pharmacies       ph  ON ph.id  = uo.pharmacy_id
JOIN user_order_items uoi ON uoi.order_id  = uo.id
JOIN products         p   ON p.id   = uoi.product_id
GROUP BY uo.id, uo.order_number, u.login, u.full_name,
         u.phone, ph.address, uo.status, uo.total_amount,
         uo.reserved_until, uo.created_at
ORDER BY uo.created_at DESC;

-- ============================================================
-- 7. ПРОЦЕДУРА: ОФОРМИТЬ ЗАКАЗ ИЗ КОРЗИНЫ
-- ============================================================
DELIMITER $$
CREATE PROCEDURE sp_checkout(
    IN  p_user_id       INT,
    IN  p_pharmacy_slug VARCHAR(100),
    OUT p_order_number  VARCHAR(20),
    OUT p_error         VARCHAR(200)
)
BEGIN
    DECLARE v_pharmacy_id INT DEFAULT NULL;
    DECLARE v_cart_count  INT DEFAULT 0;
    DECLARE v_total       DECIMAL(10,2) DEFAULT 0;
    DECLARE v_order_id    INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_error = 'Ошибка при оформлении заказа';
    END;

    SET p_error = NULL;
    SET p_order_number = NULL;

    -- Найти аптеку
    SELECT id INTO v_pharmacy_id
    FROM pharmacies
    WHERE slug = p_pharmacy_slug AND is_active = TRUE LIMIT 1;

    -- Проверить корзину
    SELECT COUNT(*), IFNULL(SUM(quantity * price), 0)
    INTO v_cart_count, v_total
    FROM user_cart
    WHERE user_id = p_user_id;

    IF v_pharmacy_id IS NULL THEN
        SET p_error = 'Аптека не найдена';
    ELSEIF v_cart_count = 0 THEN
        SET p_error = 'Корзина пуста';
    ELSE
        START TRANSACTION;

        -- Создать заказ
        INSERT INTO user_orders (user_id, pharmacy_id, order_number, total_amount)
        VALUES (p_user_id, v_pharmacy_id, '', v_total);

        SET v_order_id = LAST_INSERT_ID();
        SELECT order_number INTO p_order_number
        FROM user_orders WHERE id = v_order_id;

        -- Перенести товары из корзины в заказ
        INSERT INTO user_order_items (order_id, product_id, quantity, price)
        SELECT v_order_id, product_id, quantity, price
        FROM user_cart
        WHERE user_id = p_user_id;

        -- Очистить корзину
        DELETE FROM user_cart WHERE user_id = p_user_id;

        COMMIT;
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- 8. ТЕСТОВЫЕ ДАННЫЕ
-- ============================================================

-- Тестовые пользователи
-- Пароли хранятся как bcrypt-хэш. Ниже — заглушки для демонстрации.
-- В реальном приложении хэш генерируется на сервере (PHP: password_hash()).
INSERT INTO users (login, password_hash, full_name, phone, role) VALUES
    ('admin',    '$2y$10$examplehashADMIN000000000000000000000000000000000000000', 'Администратор',   '+375291234567', 'admin'),
    ('ivanov',   '$2y$10$examplehashUSER1000000000000000000000000000000000000000', 'Иванов Иван',     '+375291111111', 'customer'),
    ('petrova',  '$2y$10$examplehashUSER2000000000000000000000000000000000000000', 'Петрова Мария',   '+375292222222', 'customer'),
    ('sidorov',  '$2y$10$examplehashUSER3000000000000000000000000000000000000000', 'Сидоров Алексей', '+375293333333', 'customer');

-- Тестовые товары в корзинах пользователей
INSERT INTO user_cart (user_id, product_id, quantity, price) VALUES
    -- Иванов: Нурофен × 2, Витамин C × 1
    (2, (SELECT id FROM products WHERE slug='nurofen'),   2, 3.20),
    (2, (SELECT id FROM products WHERE slug='vitaminc'),  1, 2.30),
    -- Петрова: Витамин D3 × 1, Лоратадин × 3
    (3, (SELECT id FROM products WHERE slug='vitamind'),  1, 8.50),
    (3, (SELECT id FROM products WHERE slug='loratadin'), 3, 2.40),
    -- Сидоров: Омепразол × 2
    (4, (SELECT id FROM products WHERE slug='omeprazol'), 2, 3.60);
