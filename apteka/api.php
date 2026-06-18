<?php
/* ============================================================
   api.php — Бэкенд аптеки (полная версия)
   ============================================================ */

define('DB_HOST',    'localhost');
define('DB_NAME',    'apteka_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        jsonError('Ошибка подключения к БД: ' . $e->getMessage(), 500);
    }
    return $pdo;
}

function jsonOk(array $data = []): void {
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}
function jsonError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function generateToken(): string {
    return bin2hex(random_bytes(32));
}
function getUserByToken(?string $token): ?array {
    if (!$token) return null;
    $stmt = db()->prepare("
        SELECT u.id, u.login, u.full_name, u.phone, u.role
        FROM user_sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.token = ? AND s.expires_at > NOW() AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}
function requireAdmin(?string $token): array {
    $user = getUserByToken($token);
    if (!$user)                    jsonError('Не авторизован', 401);
    if ($user['role'] !== 'admin') jsonError('Доступ запрещён', 403);
    return $user;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {

    /* ══════════════════════════════════════════════════════
       РЕГИСТРАЦИЯ
    ══════════════════════════════════════════════════════ */
    case 'register':
        $login    = trim($input['login']     ?? '');
        $fullName = trim($input['full_name'] ?? '');
        $phone    = trim($input['phone']     ?? '');
        $password = $input['password']       ?? '';

        if (!preg_match('/^[a-zA-Zа-яёА-ЯЁ0-9_]{3,50}$/u', $login))
            jsonError('Логин: от 3 до 50 символов (буквы, цифры, _)');
        if (strlen($password) < 6)
            jsonError('Пароль должен быть минимум 6 символов');

        $stmt = db()->prepare("SELECT id FROM users WHERE login = ? LIMIT 1");
        $stmt->execute([$login]);
        if ($stmt->fetch()) jsonError('Этот логин уже занят');

        $hash = password_hash($password, PASSWORD_BCRYPT);
        db()->prepare("INSERT INTO users (login, password_hash, full_name, phone) VALUES (?,?,?,?)")
            ->execute([$login, $hash, $fullName, $phone]);
        $userId = (int) db()->lastInsertId();

        $token     = generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        db()->prepare("INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?,?,?,?,?)")
            ->execute([$userId, $token, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), $expiresAt]);

        jsonOk(['user_id' => $userId, 'token' => $token]);

    /* ══════════════════════════════════════════════════════
       ВХОД
    ══════════════════════════════════════════════════════ */
    case 'login':
        $login    = trim($input['login']    ?? '');
        $password = $input['password']      ?? '';

        if (!$login || !$password) jsonError('Укажите логин и пароль');

        $stmt = db()->prepare("SELECT id, login, password_hash, full_name, phone, role, is_active FROM users WHERE login = ? LIMIT 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash']))
            jsonError('Неверный логин или пароль');
        if (!$user['is_active'])
            jsonError('Аккаунт заблокирован');

        db()->prepare("DELETE FROM user_sessions WHERE user_id = ? AND expires_at < NOW()")->execute([$user['id']]);

        $token     = generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        db()->prepare("INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?,?,?,?,?)")
            ->execute([$user['id'], $token, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), $expiresAt]);
        db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

        jsonOk([
            'token' => $token,
            'user'  => [
                'id'        => $user['id'],
                'login'     => $user['login'],
                'full_name' => $user['full_name'],
                'phone'     => $user['phone'],
                'role'      => $user['role'],
            ]
        ]);

    /* ══════════════════════════════════════════════════════
       ВЫХОД
    ══════════════════════════════════════════════════════ */
    case 'logout':
        $token = $input['token'] ?? '';
        if ($token) db()->prepare("DELETE FROM user_sessions WHERE token = ?")->execute([$token]);
        jsonOk();

    /* ══════════════════════════════════════════════════════
       КОРЗИНА — ПОЛУЧИТЬ
    ══════════════════════════════════════════════════════ */
    case 'cart_get':
        $user = getUserByToken($input['token'] ?? '');
        if (!$user) jsonError('Не авторизован', 401);

        $stmt = db()->prepare("
            SELECT p.slug, p.name, p.icon, p.form,
                   uc.quantity AS qty,
                   CAST(uc.price AS CHAR) AS price
            FROM user_cart uc
            JOIN products p ON p.id = uc.product_id
            WHERE uc.user_id = ?
            ORDER BY uc.added_at
        ");
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $item['price'] = number_format((float)$item['price'], 2, ',', '');
            $item['qty']   = (int)$item['qty'];
        }
        unset($item);
        jsonOk(['items' => $items]);

    /* ══════════════════════════════════════════════════════
       КОРЗИНА — СИНХРОНИЗИРОВАТЬ
    ══════════════════════════════════════════════════════ */
    case 'cart_sync':
        $user  = getUserByToken($input['token'] ?? '');
        $items = $input['items'] ?? [];
        if (!$user) jsonError('Не авторизован', 401);

        $userId = $user['id'];
        db()->prepare("DELETE FROM user_cart WHERE user_id = ?")->execute([$userId]);

        if (!empty($items)) {
            $findProd = db()->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
            $ins      = db()->prepare("INSERT INTO user_cart (user_id, product_id, quantity, price)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), price = VALUES(price)");
            foreach ($items as $item) {
                $slug  = trim($item['slug']  ?? '');
                $qty   = max(1, (int)($item['qty'] ?? 1));
                $price = (float)str_replace(',', '.', $item['price'] ?? '0');
                if (!$slug || $price <= 0) continue;
                $findProd->execute([$slug]);
                $prod = $findProd->fetch();
                if (!$prod) continue;
                $ins->execute([$userId, $prod['id'], $qty, $price]);
            }
        }
        jsonOk();

    /* ══════════════════════════════════════════════════════
       ОФОРМИТЬ ЗАКАЗ
    ══════════════════════════════════════════════════════ */
    case 'checkout':
        $user         = getUserByToken($input['token'] ?? '');
        $pharmacySlug = $input['pharmacy_slug'] ?? 'lenina-12';
        if (!$user) jsonError('Не авторизован', 401);

        $stmt = db()->prepare("CALL sp_checkout(?, ?, @order_num, @err)");
        $stmt->execute([$user['id'], $pharmacySlug]);
        $stmt->closeCursor();
        $row = db()->query("SELECT @order_num AS order_num, @err AS err")->fetch();
        if (!empty($row['err'])) jsonError($row['err']);
        jsonOk(['order_number' => $row['order_num']]);

    /* ══════════════════════════════════════════════════════
       ИСТОРИЯ ЗАКАЗОВ ПОЛЬЗОВАТЕЛЯ
    ══════════════════════════════════════════════════════ */
    case 'orders_get':
        $user = getUserByToken($input['token'] ?? '');
        if (!$user) jsonError('Не авторизован', 401);

        $stmt = db()->prepare("
            SELECT uo.order_number, uo.status,
                   CAST(uo.total_amount AS CHAR) AS total_amount,
                   uo.reserved_until, uo.created_at,
                   ph.address AS pharmacy_address,
                   GROUP_CONCAT(CONCAT(p.name,' × ',uoi.quantity,' уп.') ORDER BY p.name SEPARATOR ', ') AS items
            FROM user_orders uo
            JOIN pharmacies       ph  ON ph.id  = uo.pharmacy_id
            JOIN user_order_items uoi ON uoi.order_id = uo.id
            JOIN products         p   ON p.id   = uoi.product_id
            WHERE uo.user_id = ?
            GROUP BY uo.id, uo.order_number, uo.status, uo.total_amount, uo.reserved_until, uo.created_at, ph.address
            ORDER BY uo.created_at DESC LIMIT 50
        ");
        $stmt->execute([$user['id']]);
        jsonOk(['orders' => $stmt->fetchAll()]);

    /* ══════════════════════════════════════════════════════
       ADMIN — СТАТИСТИКА
    ══════════════════════════════════════════════════════ */
    case 'admin_stats':
        requireAdmin($input['token'] ?? '');
        $recent = db()->query("
            SELECT uo.order_number, uo.status,
                   CAST(uo.total_amount AS CHAR) AS total_amount,
                   uo.created_at, u.login, u.full_name, u.phone
            FROM user_orders uo JOIN users u ON u.id = uo.user_id
            ORDER BY uo.created_at DESC LIMIT 10
        ")->fetchAll();
        jsonOk([
            'products'      => db()->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn(),
            'orders'        => db()->query("SELECT COUNT(*) FROM user_orders")->fetchColumn(),
            'users'         => db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'active'        => db()->query("SELECT COUNT(*) FROM user_orders WHERE status IN ('new','confirmed','ready')")->fetchColumn(),
            'recent_orders' => $recent,
        ]);

    /* ══════════════════════════════════════════════════════
       ADMIN — СПИСОК ТОВАРОВ
    ══════════════════════════════════════════════════════ */
    case 'admin_products_get':
        requireAdmin($input['token'] ?? '');
        $products = db()->query("
            SELECT p.*, pp.price, pp.old_price,
                   IFNULL(SUM(ps.quantity),0) AS total_stock
            FROM products p
            LEFT JOIN product_prices  pp ON pp.product_id = p.id
            LEFT JOIN pharmacy_stocks ps ON ps.product_id = p.id
            WHERE p.is_active = 1
            GROUP BY p.id, pp.price, pp.old_price
            ORDER BY p.id
        ")->fetchAll();
        jsonOk(['products' => $products]);

    /* ══════════════════════════════════════════════════════
       ADMIN — ДОБАВИТЬ ТОВАР
    ══════════════════════════════════════════════════════ */
    case 'admin_product_add':
        requireAdmin($input['token'] ?? '');
        $name  = trim($input['name']  ?? '');
        $slug  = trim($input['slug']  ?? '');
        $price = (float)($input['price'] ?? 0);

        if (!$name)       jsonError('Укажите название');
        if (!$slug)       jsonError('Укажите slug');
        if ($price <= 0)  jsonError('Укажите цену');

        $ex = db()->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
        $ex->execute([$slug]);
        if ($ex->fetch()) jsonError('Товар с таким slug уже существует');

        db()->prepare("INSERT INTO products (slug, name, inn, dosage, form, icon, badge_type, category_id, description, usage_instructions, contraindications) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $slug, $name,
                $input['inn']                ?? null,
                $input['dosage']             ?? null,
                $input['form']               ?? null,
                $input['icon']               ?? '💊',
                $input['badge_type']         ?? 'otc',
                (int)($input['category_id']  ?? 1),
                $input['description']        ?? null,
                $input['usage_instructions'] ?? null,
                $input['contraindications']  ?? null,
            ]);
        $productId = (int) db()->lastInsertId();

        $oldPrice = isset($input['old_price']) && $input['old_price'] ? (float)$input['old_price'] : null;
        db()->prepare("INSERT INTO product_prices (product_id, price, old_price) VALUES (?,?,?)")
            ->execute([$productId, $price, $oldPrice]);

        $qty        = (int)($input['qty']         ?? 0);
        $pharmacyId = (int)($input['pharmacy_id'] ?? 1);
        if ($qty > 0) {
            db()->prepare("INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), price=VALUES(price)")
                ->execute([$productId, $pharmacyId, $price, $qty]);
        }
        jsonOk(['id' => $productId]);

    /* ══════════════════════════════════════════════════════
       ADMIN — РЕДАКТИРОВАТЬ ТОВАР
    ══════════════════════════════════════════════════════ */
    case 'admin_product_update':
        requireAdmin($input['token'] ?? '');
        $id    = (int)($input['id']   ?? 0);
        $name  = trim($input['name']  ?? '');
        $slug  = trim($input['slug']  ?? '');
        $price = (float)($input['price'] ?? 0);

        if (!$id)        jsonError('Не указан ID товара');
        if (!$name)      jsonError('Укажите название');
        if (!$slug)      jsonError('Укажите slug');
        if ($price <= 0) jsonError('Укажите цену');

        $ex = db()->prepare("SELECT id FROM products WHERE slug = ? AND id != ? LIMIT 1");
        $ex->execute([$slug, $id]);
        if ($ex->fetch()) jsonError('Товар с таким slug уже существует');

        db()->prepare("UPDATE products SET name=?, slug=?, inn=?, dosage=?, form=?, icon=?, badge_type=?, category_id=?, description=?, usage_instructions=?, contraindications=? WHERE id=?")
            ->execute([
                $name, $slug,
                $input['inn']                ?? null,
                $input['dosage']             ?? null,
                $input['form']               ?? null,
                $input['icon']               ?? '💊',
                $input['badge_type']         ?? 'otc',
                (int)($input['category_id']  ?? 1),
                $input['description']        ?? null,
                $input['usage_instructions'] ?? null,
                $input['contraindications']  ?? null,
                $id,
            ]);

        $oldPrice = isset($input['old_price']) && $input['old_price'] ? (float)$input['old_price'] : null;
        $ex2 = db()->prepare("SELECT id FROM product_prices WHERE product_id=? LIMIT 1");
        $ex2->execute([$id]);
        if ($ex2->fetch()) {
            db()->prepare("UPDATE product_prices SET price=?, old_price=? WHERE product_id=?")->execute([$price, $oldPrice, $id]);
        } else {
            db()->prepare("INSERT INTO product_prices (product_id, price, old_price) VALUES (?,?,?)")->execute([$id, $price, $oldPrice]);
        }

        $qty        = (int)($input['qty']         ?? 0);
        $pharmacyId = (int)($input['pharmacy_id'] ?? 1);
        $ex3 = db()->prepare("SELECT id FROM pharmacy_stocks WHERE product_id=? AND pharmacy_id=? LIMIT 1");
        $ex3->execute([$id, $pharmacyId]);
        if ($ex3->fetch()) {
            db()->prepare("UPDATE pharmacy_stocks SET quantity=?, price=? WHERE product_id=? AND pharmacy_id=?")->execute([$qty, $price, $id, $pharmacyId]);
        } else {
            db()->prepare("INSERT INTO pharmacy_stocks (product_id, pharmacy_id, price, quantity) VALUES (?,?,?,?)")->execute([$id, $pharmacyId, $price, $qty]);
        }
        jsonOk();

    /* ══════════════════════════════════════════════════════
       ADMIN — УДАЛИТЬ ТОВАР
    ══════════════════════════════════════════════════════ */
    case 'admin_product_delete':
        requireAdmin($input['token'] ?? '');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('Не указан ID');

        // Полное удаление всех данных товара
        db()->prepare("DELETE FROM analogs          WHERE product_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM pharmacy_stocks  WHERE product_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM product_prices   WHERE product_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM user_cart        WHERE product_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM booking_items    WHERE product_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM user_order_items WHERE product_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM products         WHERE id = ?")->execute([$id]);
        jsonOk();

    /* ══════════════════════════════════════════════════════
       ADMIN — СПИСОК ЗАКАЗОВ
    ══════════════════════════════════════════════════════ */
    case 'admin_orders_get':
        requireAdmin($input['token'] ?? '');
        $orders = db()->query("
            SELECT uo.id, uo.order_number, uo.status,
                   CAST(uo.total_amount AS CHAR) AS total_amount,
                   uo.created_at, u.login, u.full_name, u.phone,
                   ph.address AS pharmacy_address,
                   GROUP_CONCAT(CONCAT(p.name,' × ',uoi.quantity,' уп.') ORDER BY p.name SEPARATOR ', ') AS items
            FROM user_orders uo
            JOIN users u ON u.id = uo.user_id
            JOIN pharmacies ph ON ph.id = uo.pharmacy_id
            JOIN user_order_items uoi ON uoi.order_id = uo.id
            JOIN products p ON p.id = uoi.product_id
            GROUP BY uo.id, uo.order_number, uo.status, uo.total_amount, uo.created_at, u.login, u.full_name, u.phone, ph.address
            ORDER BY uo.created_at DESC LIMIT 200
        ")->fetchAll();
        jsonOk(['orders' => $orders]);

    /* ══════════════════════════════════════════════════════
       ADMIN — СМЕНИТЬ СТАТУС ЗАКАЗА
    ══════════════════════════════════════════════════════ */
    case 'admin_order_status':
        requireAdmin($input['token'] ?? '');
        $id     = (int)($input['id']     ?? 0);
        $status = $input['status'] ?? '';
        if (!$id) jsonError('Не указан ID заказа');
        if (!in_array($status, ['new','confirmed','ready','completed','cancelled','expired'])) jsonError('Недопустимый статус');
        db()->prepare("UPDATE user_orders SET status=? WHERE id=?")->execute([$status, $id]);
        jsonOk();

    /* ══════════════════════════════════════════════════════
       ADMIN — СПИСОК ПОЛЬЗОВАТЕЛЕЙ
    ══════════════════════════════════════════════════════ */
    case 'admin_users_get':
        requireAdmin($input['token'] ?? '');
        $users = db()->query("SELECT id, login, full_name, phone, email, role, is_active, created_at, last_login FROM users ORDER BY created_at DESC")->fetchAll();
        jsonOk(['users' => $users]);

    /* ══════════════════════════════════════════════════════
       НЕИЗВЕСТНОЕ ДЕЙСТВИЕ
    ══════════════════════════════════════════════════════ */
    default:
        jsonError('Неизвестное действие: ' . htmlspecialchars($action));
}
