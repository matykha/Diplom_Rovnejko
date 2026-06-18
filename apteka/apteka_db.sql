-- ============================================================
--  БАЗА ДАННЫХ АПТЕКИ (MySQL 8.0+)
--  Сгенерирована на основе данных сайта apteka
--  Содержит: товары, аптеки, остатки, аналоги,
--            корзины покупателей и бронирования
-- ============================================================

CREATE DATABASE IF NOT EXISTS apteka_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE apteka_db;

-- ============================================================
-- 1. КАТЕГОРИИ ТОВАРОВ
-- ============================================================
CREATE TABLE categories (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    slug       VARCHAR(50)  UNIQUE NOT NULL  COMMENT 'URL-идентификатор',
    name       VARCHAR(100) NOT NULL         COMMENT 'Название категории',
    icon       VARCHAR(10)                   COMMENT 'Эмодзи-иконка',
    sort_order INT DEFAULT 0
);

INSERT INTO categories (slug, name, icon, sort_order) VALUES
    ('pain',       'Обезболивающие / Противовоспалительные', '💊', 1),
    ('antibiotics','Антибиотики',                            '🔴', 2),
    ('allergy',    'Антигистаминные / От аллергии',          '🌸', 3),
    ('vitamins',   'Витамины и минералы',                    '🌿', 4),
    ('gastro',     'Гастроэнтерология / ЖКТ',               '🟡', 5),
    ('antiviral',  'Противовирусные / Иммуномодуляторы',    '🛡️', 6),
    ('hygiene',    'Антисептики / Гигиена',                  '🧴', 7);


-- ============================================================
-- 2. ТОВАРЫ (ЛЕКАРСТВЕННЫЕ ПРЕПАРАТЫ)
-- ============================================================
CREATE TABLE products (
    id                   INT PRIMARY KEY AUTO_INCREMENT,
    slug                 VARCHAR(100) UNIQUE NOT NULL  COMMENT 'URL-идентификатор страницы товара',
    name                 VARCHAR(200) NOT NULL          COMMENT 'Торговое наименование',
    inn                  VARCHAR(200)                   COMMENT 'МНН — международное непатентованное наименование',
    dosage               VARCHAR(50)                    COMMENT 'Дозировка (200 мг, 0.05% и т.д.)',
    form                 VARCHAR(100)                   COMMENT 'Лекарственная форма',
    icon                 VARCHAR(10)                    COMMENT 'Эмодзи-иконка',
    badge_type           ENUM('rx','otc','sale','generic')
                         DEFAULT 'otc'                  COMMENT 'rx=рецептурный, otc=без рецепта, sale=акция, generic=дженерик',
    category_id          INT                            COMMENT 'Ссылка на категорию',
    description          TEXT                           COMMENT 'Описание препарата',
    usage_instructions   TEXT                           COMMENT 'Способ применения и дозировка',
    contraindications    TEXT                           COMMENT 'Противопоказания',
    storage_conditions   VARCHAR(500)                   COMMENT 'Условия хранения',
    is_active            BOOLEAN DEFAULT TRUE,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

INSERT INTO products
    (slug, name, inn, dosage, form, icon, badge_type, category_id,
     description, usage_instructions, contraindications, storage_conditions)
VALUES
-- 1. Нурофен
(
    'nurofen', 'Нурофен', 'Ибупрофен', '200 мг', 'Таблетки', '💊', 'otc',
    (SELECT id FROM categories WHERE slug = 'pain'),
    'Нестероидное противовоспалительное средство на основе ибупрофена. Обладает обезболивающим, жаропонижающим и противовоспалительным действием. Применяется при болях различного происхождения: головная боль, зубная боль, боли в мышцах и суставах, менструальные боли, повышение температуры при простудных заболеваниях.',
    'По 1 таблетке (200 мг) 3–4 раза в сутки. Максимальная суточная доза — 1200 мг. Принимать после еды, запивая водой.',
    'Язвенная болезнь желудка, тяжёлая почечная/печёночная недостаточность, III триместр беременности, гиперчувствительность к ибупрофену.',
    'Хранить при температуре не выше 25 °C, в сухом месте, вне досягаемости детей.'
),
-- 2. Амоксициллин
(
    'amoxicilin', 'Амоксициллин', 'Амоксициллин', '500 мг', 'Капсулы', '🔴', 'rx',
    (SELECT id FROM categories WHERE slug = 'antibiotics'),
    'Антибиотик широкого спектра действия из группы пенициллинов. Активен в отношении большинства грамположительных и ряда грамотрицательных бактерий. Применяется при бактериальных инфекциях дыхательных путей, ЛОР-органов, мочевыводящих путей, кожи и мягких тканей. Отпускается строго по рецепту врача.',
    'По 500 мг 3 раза в сутки каждые 8 часов. Курс лечения — 5–10 дней. Принимать независимо от приёма пищи.',
    'Гиперчувствительность к пенициллинам и цефалоспоринам, инфекционный мононуклеоз, лимфолейкоз.',
    'Хранить при температуре не выше 25 °C, в сухом месте. Срок годности — 2 года.'
),
-- 3. Лоратадин
(
    'loratadin', 'Лоратадин', 'Лоратадин', '10 мг', 'Таблетки', '🌸', 'otc',
    (SELECT id FROM categories WHERE slug = 'allergy'),
    'Антигистаминный препарат второго поколения с длительным действием. Блокирует H1-гистаминовые рецепторы. Применяется при аллергическом рините, крапивнице, атопическом дерматите, аллергических реакциях на укусы насекомых. В терапевтических дозах не вызывает сонливости.',
    'Взрослым и детям старше 12 лет — по 1 таблетке (10 мг) 1 раз в сутки. Принимать независимо от еды.',
    'Гиперчувствительность к лоратадину, I триместр беременности, детский возраст до 2 лет.',
    'Хранить при температуре не выше 25 °C, в сухом месте, вне досягаемости детей.'
),
-- 4. Витамин D3
(
    'vitamind', 'Витамин D3', 'Холекальциферол', '2000 МЕ', 'Капсулы', '🌞', 'otc',
    (SELECT id FROM categories WHERE slug = 'vitamins'),
    'Жирорастворимый витамин D3 (холекальциферол) для поддержания нормального уровня кальция и фосфора в организме. Участвует в формировании костной ткани, регуляции иммунитета, работе нервной системы. Особенно важен в осенне-зимний период при недостатке солнечного света.',
    'По 1 капсуле (2000 МЕ) 1 раз в сутки во время еды. Для профилактики — курсами по 2–3 месяца. Дозировку при дефиците определяет врач.',
    'Гиперкальциемия, гипервитаминоз D, мочекаменная болезнь (оксалатные камни), тяжёлая почечная недостаточность.',
    'Хранить при температуре не выше 25 °C, в сухом защищённом от света месте. Срок годности — 2 года.'
),
-- 5. Омепразол
(
    'omeprazol', 'Омепразол', 'Омепразол', '20 мг', 'Капсулы', '🟡', 'sale',
    (SELECT id FROM categories WHERE slug = 'gastro'),
    'Ингибитор протонной помпы — снижает выработку соляной кислоты в желудке. Применяется при язвенной болезни желудка и двенадцатиперстной кишки, гастроэзофагеальном рефлюксе (ГЭРБ), хроническом гастрите с повышенной кислотностью, синдроме Золлингера–Эллисона.',
    'По 1 капсуле (20 мг) 1 раз в сутки за 30 минут до завтрака. Курс — 4–8 недель. Не разжёвывать, запивать водой.',
    'Гиперчувствительность к омепразолу, детский возраст до 1 года, одновременный приём с нелфинавиром.',
    'Хранить при температуре не выше 25 °C, в сухом месте, вне досягаемости детей.'
),
-- 6. Анаферон
(
    'anaferon', 'Анаферон', 'Антитела к интерферону гамма', NULL, 'Таблетки', '🛡️', 'otc',
    (SELECT id FROM categories WHERE slug = 'antiviral'),
    'Иммуномодулирующий препарат с противовирусной активностью. Содержит аффинно очищенные антитела к интерферону гамма. Стимулирует гуморальный и клеточный иммунный ответ. Применяется для профилактики и лечения ОРВИ и гриппа. Подходит для взрослых и детей от 1 месяца.',
    '1-й день — 8 таблеток по схеме, со 2-го дня — по 3 таблетки в сутки. Профилактика — 1 таблетка в сутки. Таблетки держать во рту до растворения.',
    'Гиперчувствительность к компонентам препарата, дефицит лактазы (содержит лактозу).',
    'Хранить при температуре не выше 25 °C, в сухом защищённом от света месте.'
),
-- 7. Витамин C
(
    'vitaminc', 'Витамин C', 'Аскорбиновая кислота', '500 мг', 'Таблетки шипучие', '🍋', 'otc',
    (SELECT id FROM categories WHERE slug = 'vitamins'),
    'Водорастворимый витамин C (аскорбиновая кислота) в удобной шипучей форме. Мощный антиоксидант, участвует в синтезе коллагена, укрепляет стенки сосудов, повышает усвоение железа. Укрепляет иммунную систему, особенно в период сезонных заболеваний.',
    'По 1 шипучей таблетке (500 мг) 1 раз в сутки. Растворить в стакане воды (200 мл), выпить сразу после растворения. Принимать во время или после еды.',
    'Гиперчувствительность к аскорбиновой кислоте, мочекаменная болезнь, сахарный диабет (с осторожностью).',
    'Хранить при температуре не выше 25 °C, в сухом месте. После вскрытия упаковки использовать в течение 2 месяцев.'
),
-- 8. Хлоргексидин
(
    'hlorgesidine', 'Хлоргексидин', 'Хлоргексидина биглюконат', '0.05%', 'Раствор', '🧴', 'otc',
    (SELECT id FROM categories WHERE slug = 'hygiene'),
    'Антисептическое средство широкого спектра действия на основе хлоргексидина биглюконата 0.05%. Активен в отношении большинства грамположительных и грамотрицательных бактерий, ряда вирусов и грибков. Применяется для обработки ран, ссадин, порезов, дезинфекции слизистых оболочек.',
    'Наружно: обработать поражённый участок кожи или слизистой, смочив ватный тампон или промыв рану. Для полоскания рта — 10–15 мл раствора 2–3 раза в сутки.',
    'Гиперчувствительность к хлоргексидину, детский возраст до 1 года (с осторожностью для обработки кожи).',
    'Хранить при температуре 5–30 °C, в защищённом от прямых солнечных лучей месте. Не замораживать.'
);


-- ============================================================
-- 3. ЦЕНЫ НА ГЛАВНОЙ СТРАНИЦЕ (рекомендованная / минимальная)
-- ============================================================
-- Цены хранятся в pharmacy_stocks; здесь отдельно фиксируем
-- «акционные» старые цены для бейджа sale
CREATE TABLE product_prices (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    product_id   INT NOT NULL,
    price        DECIMAL(10,2) NOT NULL  COMMENT 'Текущая / минимальная цена',
    old_price    DECIMAL(10,2)           COMMENT 'Зачёркнутая старая цена (для акций)',
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

INSERT INTO product_prices (product_id, price, old_price) VALUES
    ((SELECT id FROM products WHERE slug='nurofen'),      3.20, 4.10),
    ((SELECT id FROM products WHERE slug='amoxicilin'),   5.80, NULL),
    ((SELECT id FROM products WHERE slug='loratadin'),    2.40, NULL),
    ((SELECT id FROM products WHERE slug='vitamind'),     8.50, NULL),
    ((SELECT id FROM products WHERE slug='omeprazol'),    3.60, 5.20),
    ((SELECT id FROM products WHERE slug='anaferon'),     4.80, NULL),
    ((SELECT id FROM products WHERE slug='vitaminc'),     2.30, NULL),
    ((SELECT id FROM products WHERE slug='hlorgesidine'), 1.10, NULL);


-- ============================================================
-- 4. АПТЕКИ (ПУНКТЫ ВЫДАЧИ)
-- ============================================================
CREATE TABLE pharmacies (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    slug          VARCHAR(100) UNIQUE NOT NULL,
    name          VARCHAR(200) NOT NULL  COMMENT 'Название точки',
    address       VARCHAR(300) NOT NULL  COMMENT 'Адрес',
    phone         VARCHAR(50)            COMMENT 'Телефон',
    working_hours VARCHAR(100)           COMMENT 'Режим работы',
    is_active     BOOLEAN DEFAULT TRUE
);

INSERT INTO pharmacies (slug, name, address, phone, working_hours) VALUES
    ('lenina-12',          'Аптека на Ленина',         'ул. Ленина, 12',          '+375 (17) 123-45-67', '8:00–20:00'),
    ('nezavisimosti-45',   'Аптека на Независимости',  'пр. Независимости, 45',   '+375 (17) 234-56-78', '9:00–21:00'),
    ('pritytskogo-101',    'Аптека на Притыцкого',     'ул. Притыцкого, 101',     '+375 (17) 345-67-89', '8:00–22:00');


-- ============================================================
-- 5. ОСТАТКИ И ЦЕНЫ ПО АПТЕКАМ
-- ============================================================
CREATE TABLE pharmacy_stocks (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    product_id   INT NOT NULL,
    pharmacy_id  INT NOT NULL,
    price        DECIMAL(10,2) NOT NULL  COMMENT 'Цена в данной аптеке',
    quantity     INT DEFAULT 0           COMMENT 'Остаток (упаковок)',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_pharmacy (product_id, pharmacy_id),
    FOREIGN KEY (product_id)  REFERENCES products(id)   ON DELETE CASCADE,
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE CASCADE
);

-- Нурофен
INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES
    ((SELECT id FROM products WHERE slug='nurofen'), (SELECT id FROM pharmacies WHERE slug='lenina-12'),        3.20,  7),
    ((SELECT id FROM products WHERE slug='nurofen'), (SELECT id FROM pharmacies WHERE slug='nezavisimosti-45'), 3.45,  3),
    ((SELECT id FROM products WHERE slug='nurofen'), (SELECT id FROM pharmacies WHERE slug='pritytskogo-101'),  3.20, 12);

-- Амоксициллин
INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES
    ((SELECT id FROM products WHERE slug='amoxicilin'), (SELECT id FROM pharmacies WHERE slug='lenina-12'),       5.80, 5),
    ((SELECT id FROM products WHERE slug='amoxicilin'), (SELECT id FROM pharmacies WHERE slug='pritytskogo-101'), 6.10, 8);

-- Лоратадин
INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES
    ((SELECT id FROM products WHERE slug='loratadin'), (SELECT id FROM pharmacies WHERE slug='lenina-12'),        2.40, 15),
    ((SELECT id FROM products WHERE slug='loratadin'), (SELECT id FROM pharmacies WHERE slug='nezavisimosti-45'), 2.55,  6),
    ((SELECT id FROM products WHERE slug='loratadin'), (SELECT id FROM pharmacies WHERE slug='pritytskogo-101'),  2.40, 20);

-- Витамин D3
INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES
    ((SELECT id FROM products WHERE slug='vitamind'), (SELECT id FROM pharmacies WHERE slug='lenina-12'),        8.50, 9),
    ((SELECT id FROM products WHERE slug='vitamind'), (SELECT id FROM pharmacies WHERE slug='nezavisimosti-45'), 8.90, 4);

-- Омепразол
INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES
    ((SELECT id FROM products WHERE slug='omeprazol'), (SELECT id FROM pharmacies WHERE slug='lenina-12'),        3.60, 25),
    ((SELECT id FROM products WHERE slug='omeprazol'), (SELECT id FROM pharmacies WHERE slug='nezavisimosti-45'), 3.80, 10),
    ((SELECT id FROM products WHERE slug='omeprazol'), (SELECT id FROM pharmacies WHERE slug='pritytskogo-101'),  3.60, 18);

-- Анаферон
INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES
    ((SELECT id FROM products WHERE slug='anaferon'), (SELECT id FROM pharmacies WHERE slug='lenina-12'),       4.80, 11),
    ((SELECT id FROM products WHERE slug='anaferon'), (SELECT id FROM pharmacies WHERE slug='pritytskogo-101'), 5.10,  7);

-- Витамин C
INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES
    ((SELECT id FROM products WHERE slug='vitaminc'), (SELECT id FROM pharmacies WHERE slug='lenina-12'),        2.30, 30),
    ((SELECT id FROM products WHERE slug='vitaminc'), (SELECT id FROM pharmacies WHERE slug='nezavisimosti-45'), 2.50, 14),
    ((SELECT id FROM products WHERE slug='vitaminc'), (SELECT id FROM pharmacies WHERE slug='pritytskogo-101'),  2.30, 22);

-- Хлоргексидин
INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES
    ((SELECT id FROM products WHERE slug='hlorgesidine'), (SELECT id FROM pharmacies WHERE slug='lenina-12'),        1.10, 50),
    ((SELECT id FROM products WHERE slug='hlorgesidine'), (SELECT id FROM pharmacies WHERE slug='nezavisimosti-45'), 1.20, 35),
    ((SELECT id FROM products WHERE slug='hlorgesidine'), (SELECT id FROM pharmacies WHERE slug='pritytskogo-101'),  1.10, 60);


-- ============================================================
-- 6. АНАЛОГИ (СПРАВОЧНАЯ ИНФОРМАЦИЯ)
-- ============================================================
CREATE TABLE analogs (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    product_id      INT NOT NULL   COMMENT 'Основной препарат',
    analog_name     VARCHAR(200)   COMMENT 'Название аналога (может не быть в каталоге)',
    analog_price    DECIMAL(10,2)  COMMENT 'Ориентировочная цена',
    economy_percent INT            COMMENT 'Процент экономии по сравнению с основным',
    sort_order      INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

INSERT INTO analogs (product_id, analog_name, analog_price, economy_percent, sort_order) VALUES
-- Нурофен
    ((SELECT id FROM products WHERE slug='nurofen'), 'Ибупрофен (дженерик)', 1.80, 43, 1),
    ((SELECT id FROM products WHERE slug='nurofen'), 'Ибуклин',              2.60, 19, 2),
-- Амоксициллин
    ((SELECT id FROM products WHERE slug='amoxicilin'), 'Флемоксин Солютаб', 8.40, NULL, 1),
    ((SELECT id FROM products WHERE slug='amoxicilin'), 'Амоксил (дженерик)', 4.20, 28,  2),
-- Лоратадин
    ((SELECT id FROM products WHERE slug='loratadin'), 'Кларитин',           5.20, NULL, 1),
    ((SELECT id FROM products WHERE slug='loratadin'), 'Эриус (дезлоратадин)', 7.80, NULL, 2),
-- Витамин D3
    ((SELECT id FROM products WHERE slug='vitamind'), 'Аквадетрим',   11.20, NULL, 1),
    ((SELECT id FROM products WHERE slug='vitamind'), 'Detrimax 2000',  9.80, NULL, 2),
-- Омепразол
    ((SELECT id FROM products WHERE slug='omeprazol'), 'Лосек МАПС',           14.50, NULL, 1),
    ((SELECT id FROM products WHERE slug='omeprazol'), 'Нольпаза (пантопразол)', 5.90, NULL, 2),
-- Анаферон
    ((SELECT id FROM products WHERE slug='anaferon'), 'Кагоцел',             6.20, NULL, 1),
    ((SELECT id FROM products WHERE slug='anaferon'), 'Арбидол (умифеновир)', 7.40, NULL, 2),
-- Витамин C
    ((SELECT id FROM products WHERE slug='vitaminc'), 'Redoxon 1000', 5.80, NULL, 1),
    ((SELECT id FROM products WHERE slug='vitaminc'), 'Цевикап',      3.10, NULL, 2),
-- Хлоргексидин
    ((SELECT id FROM products WHERE slug='hlorgesidine'), 'Мирамистин', 6.40, NULL, 1);


-- ============================================================
-- 7. КОРЗИНА — СЕССИИ ПОКУПАТЕЛЕЙ
-- ============================================================
-- Покупатель идентифицируется по токену из localStorage браузера.
-- Регистрация не требуется.
CREATE TABLE customer_sessions (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    session_token  VARCHAR(64) UNIQUE NOT NULL  COMMENT 'UUID из localStorage (генерируется клиентом)',
    customer_name  VARCHAR(100)                 COMMENT 'Имя (заполняется при оформлении)',
    customer_phone VARCHAR(30)                  COMMENT 'Телефон (заполняется при оформлении)',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_token (session_token),
    INDEX idx_activity (last_activity)
);

-- ============================================================
-- 8. КОРЗИНА — ПОЗИЦИИ
-- ============================================================
CREATE TABLE cart_items (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    session_id  INT NOT NULL    COMMENT 'Ссылка на сессию покупателя',
    product_id  INT NOT NULL    COMMENT 'Ссылка на товар',
    quantity    INT NOT NULL DEFAULT 1  COMMENT 'Количество упаковок',
    price       DECIMAL(10,2) NOT NULL  COMMENT 'Цена на момент добавления',
    added_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_product (session_id, product_id),
    FOREIGN KEY (session_id) REFERENCES customer_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)          ON DELETE CASCADE
);

-- ============================================================
-- 9. БРОНИРОВАНИЯ (ЗАКАЗЫ)
-- ============================================================
CREATE TABLE bookings (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    order_number    VARCHAR(20) UNIQUE NOT NULL  COMMENT 'Номер заявки (BK-2026-00001)',
    session_id      INT                          COMMENT 'Сессия, из которой оформлено',
    customer_name   VARCHAR(100) NOT NULL        COMMENT 'Имя покупателя',
    customer_phone  VARCHAR(30)  NOT NULL        COMMENT 'Телефон покупателя',
    pharmacy_id     INT NOT NULL                 COMMENT 'Выбранная аптека для получения',
    status          ENUM(
                        'new',        -- только что создан
                        'confirmed',  -- подтверждён менеджером
                        'ready',      -- готов к выдаче
                        'completed',  -- выдан покупателю
                        'cancelled',  -- отменён
                        'expired'     -- истёк срок брони (3 часа)
                    ) DEFAULT 'new',
    reserved_until  DATETIME                     COMMENT 'Срок бронирования (created_at + 3 часа)',
    note            TEXT                         COMMENT 'Комментарий покупателя',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id)   REFERENCES customer_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (pharmacy_id)  REFERENCES pharmacies(id)        ON DELETE RESTRICT,
    INDEX idx_status     (status),
    INDEX idx_phone      (customer_phone),
    INDEX idx_reserved   (reserved_until),
    INDEX idx_created    (created_at)
);

-- Триггер: автоматически проставляет номер заказа и срок брони
DELIMITER $$
CREATE TRIGGER trg_booking_before_insert
BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    -- Номер вида BK-2026-00001
    IF NEW.order_number IS NULL OR NEW.order_number = '' THEN
        SET NEW.order_number = CONCAT(
            'BK-', YEAR(NOW()), '-',
            LPAD((SELECT IFNULL(MAX(id),0)+1 FROM bookings), 5, '0')
        );
    END IF;
    -- Бронь на 3 часа
    IF NEW.reserved_until IS NULL THEN
        SET NEW.reserved_until = DATE_ADD(NOW(), INTERVAL 3 HOUR);
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- 10. ПОЗИЦИИ БРОНИРОВАНИЯ
-- ============================================================
CREATE TABLE booking_items (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    booking_id  INT NOT NULL,
    product_id  INT NOT NULL,
    quantity    INT NOT NULL DEFAULT 1,
    price       DECIMAL(10,2) NOT NULL  COMMENT 'Цена на момент бронирования',
    FOREIGN KEY (booking_id) REFERENCES bookings(id)  ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)  ON DELETE RESTRICT
);

-- ============================================================
-- 11. ЛОГ ИЗМЕНЕНИЯ СТАТУСОВ БРОНИРОВАНИЯ
-- ============================================================
CREATE TABLE booking_status_log (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    booking_id  INT NOT NULL,
    old_status  VARCHAR(20),
    new_status  VARCHAR(20) NOT NULL,
    changed_by  VARCHAR(100)  COMMENT 'Кто изменил: system / имя менеджера',
    note        VARCHAR(500),
    changed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id)
);

-- Триггер: автоматически пишет лог при смене статуса
DELIMITER $$
CREATE TRIGGER trg_booking_status_log
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO booking_status_log (booking_id, old_status, new_status, changed_by)
        VALUES (NEW.id, OLD.status, NEW.status, 'system');
    END IF;
END$$
DELIMITER ;


-- ============================================================
-- 12. ПОЛЕЗНЫЕ ПРЕДСТАВЛЕНИЯ (VIEWS)
-- ============================================================

-- Полный каталог с минимальной ценой по сети
CREATE VIEW v_product_catalog AS
SELECT
    p.id,
    p.slug,
    p.name,
    p.inn,
    p.dosage,
    p.form,
    p.icon,
    p.badge_type,
    c.name           AS category_name,
    pp.price         AS min_price,
    pp.old_price,
    ROUND(((pp.old_price - pp.price) / pp.old_price) * 100)
                     AS discount_percent,
    SUM(ps.quantity) AS total_stock
FROM products p
LEFT JOIN categories      c  ON c.id  = p.category_id
LEFT JOIN product_prices  pp ON pp.product_id = p.id
LEFT JOIN pharmacy_stocks ps ON ps.product_id = p.id
WHERE p.is_active = TRUE
GROUP BY p.id, p.slug, p.name, p.inn, p.dosage, p.form,
         p.icon, p.badge_type, c.name, pp.price, pp.old_price;

-- Наличие товаров по аптекам (с адресом и временем)
CREATE VIEW v_stock_by_pharmacy AS
SELECT
    p.name        AS product_name,
    p.slug        AS product_slug,
    ph.address    AS pharmacy_address,
    ph.working_hours,
    ps.price,
    ps.quantity,
    ps.last_updated
FROM pharmacy_stocks ps
JOIN products   p  ON p.id  = ps.product_id
JOIN pharmacies ph ON ph.id = ps.pharmacy_id
WHERE p.is_active = TRUE AND ph.is_active = TRUE
ORDER BY p.name, ps.price;

-- Активные бронирования с составом заказа
CREATE VIEW v_active_bookings AS
SELECT
    b.order_number,
    b.customer_name,
    b.customer_phone,
    ph.address        AS pharmacy_address,
    b.status,
    b.reserved_until,
    GROUP_CONCAT(
        CONCAT(pr.name, ' × ', bi.quantity, ' уп. — ', bi.price, ' руб.')
        ORDER BY pr.name SEPARATOR '; '
    )                 AS items,
    SUM(bi.quantity * bi.price) AS total_amount,
    b.created_at
FROM bookings b
JOIN pharmacies    ph ON ph.id = b.pharmacy_id
JOIN booking_items bi ON bi.booking_id = b.id
JOIN products      pr ON pr.id = bi.product_id
WHERE b.status NOT IN ('completed','cancelled','expired')
GROUP BY b.id, b.order_number, b.customer_name, b.customer_phone,
         ph.address, b.status, b.reserved_until, b.created_at
ORDER BY b.created_at DESC;

-- Истёкшие брони (для автопометки)
CREATE VIEW v_expired_bookings AS
SELECT id, order_number, customer_name, customer_phone, reserved_until
FROM bookings
WHERE status IN ('new','confirmed')
  AND reserved_until < NOW();

-- ============================================================
-- 13. ХРАНИМАЯ ПРОЦЕДУРА: ОФОРМИТЬ БРОНЬ ИЗ КОРЗИНЫ
-- ============================================================
DELIMITER $$
CREATE PROCEDURE sp_create_booking(
    IN  p_session_token  VARCHAR(64),
    IN  p_customer_name  VARCHAR(100),
    IN  p_customer_phone VARCHAR(30),
    IN  p_pharmacy_slug  VARCHAR(100),
    OUT p_order_number   VARCHAR(20),
    OUT p_error          VARCHAR(200)
)
BEGIN
    DECLARE v_session_id  INT;
    DECLARE v_pharmacy_id INT;
    DECLARE v_booking_id  INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_error = 'Ошибка при создании бронирования';
    END;

    SET p_error = NULL;

    -- Найти сессию
    SELECT id INTO v_session_id
    FROM customer_sessions
    WHERE session_token = p_session_token LIMIT 1;

    IF v_session_id IS NULL THEN
        SET p_error = 'Сессия не найдена';
        LEAVE sp_create_booking;
    END IF;

    -- Найти аптеку
    SELECT id INTO v_pharmacy_id
    FROM pharmacies
    WHERE slug = p_pharmacy_slug AND is_active = TRUE LIMIT 1;

    IF v_pharmacy_id IS NULL THEN
        SET p_error = 'Аптека не найдена';
        LEAVE sp_create_booking;
    END IF;

    -- Проверить, что корзина не пуста
    IF (SELECT COUNT(*) FROM cart_items WHERE session_id = v_session_id) = 0 THEN
        SET p_error = 'Корзина пуста';
        LEAVE sp_create_booking;
    END IF;

    START TRANSACTION;

    -- Обновить данные покупателя в сессии
    UPDATE customer_sessions
    SET customer_name  = p_customer_name,
        customer_phone = p_customer_phone
    WHERE id = v_session_id;

    -- Создать бронирование
    INSERT INTO bookings (customer_name, customer_phone, pharmacy_id, session_id)
    VALUES (p_customer_name, p_customer_phone, v_pharmacy_id, v_session_id);

    SET v_booking_id = LAST_INSERT_ID();
    SELECT order_number INTO p_order_number FROM bookings WHERE id = v_booking_id;

    -- Перенести позиции из корзины в booking_items
    INSERT INTO booking_items (booking_id, product_id, quantity, price)
    SELECT v_booking_id, ci.product_id, ci.quantity, ci.price
    FROM cart_items ci
    WHERE ci.session_id = v_session_id;

    -- Очистить корзину
    DELETE FROM cart_items WHERE session_id = v_session_id;

    COMMIT;
END$$
DELIMITER ;


-- ============================================================
-- 14. СОБЫТИЕ: автоматически помечать истёкшие брони
--     (требует SET GLOBAL event_scheduler = ON)
-- ============================================================
CREATE EVENT IF NOT EXISTS evt_expire_bookings
ON SCHEDULE EVERY 15 MINUTE
DO
    UPDATE bookings
    SET status = 'expired'
    WHERE status IN ('new', 'confirmed')
      AND reserved_until < NOW();
