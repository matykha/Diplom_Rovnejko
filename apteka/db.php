<?php
/* db.php — подключение к БД, используется в index.php и product.php */
define('DB_HOST',    'localhost');
define('DB_NAME',    'apteka_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function getProducts(): array {
    return db()->query("
        SELECT p.id, p.slug, p.name, p.inn, p.dosage, p.form, p.icon,
               p.badge_type, p.category_id, p.description,
               p.usage_instructions, p.contraindications,
               COALESCE(pp.price, 0)     AS price,
               pp.old_price,
               COALESCE(SUM(ps.quantity),0) AS total_stock,
               MIN(ph.address)           AS pharmacy_address
        FROM products p
        LEFT JOIN product_prices  pp ON pp.product_id = p.id
        LEFT JOIN pharmacy_stocks ps ON ps.product_id = p.id
        LEFT JOIN pharmacies      ph ON ph.id = ps.pharmacy_id
        WHERE p.is_active = 1
        GROUP BY p.id, pp.price, pp.old_price
        ORDER BY p.id
    ")->fetchAll();
}

$CAT_SLUGS = [
    1 => 'pain', 2 => 'antibiotics', 3 => 'allergy',
    4 => 'vitamins', 5 => 'gastro', 6 => 'antiviral', 7 => 'hygiene',
];
$BADGE_LABELS = [
    'otc'     => 'Без рецепта',
    'rx'      => 'Рецептурный',
    'sale'    => 'Акция',
    'generic' => 'Дженерик',
];
