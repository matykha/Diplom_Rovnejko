<?php
require_once 'db.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: index.php'); exit; }

$stmt = db()->prepare("
    SELECT p.*, pp.price, pp.old_price,
           COALESCE(SUM(ps.quantity),0) AS total_stock
    FROM products p
    LEFT JOIN product_prices  pp ON pp.product_id = p.id
    LEFT JOIN pharmacy_stocks ps ON ps.product_id = p.id
    WHERE p.slug = ? AND p.is_active = 1
    GROUP BY p.id, pp.price, pp.old_price
    LIMIT 1
");
$stmt->execute([$slug]);
$p = $stmt->fetch();
if (!$p) { header('Location: index.php'); exit; }

$stocks = db()->prepare("
    SELECT ph.address, ph.working_hours, ps.quantity, ps.price
    FROM pharmacy_stocks ps
    JOIN pharmacies ph ON ph.id = ps.pharmacy_id
    WHERE ps.product_id = ?
    ORDER BY ps.price
");
$stocks->execute([$p['id']]);
$stockRows = $stocks->fetchAll();

$analogs = db()->prepare("
    SELECT analog_name, analog_price, economy_percent
    FROM analogs WHERE product_id = ? ORDER BY sort_order
");
$analogs->execute([$p['id']]);
$analogRows = $analogs->fetchAll();

$price    = number_format((float)$p['price'],    2, ',', '');
$oldPrice = $p['old_price'] ? number_format((float)$p['old_price'], 2, ',', '') : null;
$name     = htmlspecialchars($p['name']);
$icon     = htmlspecialchars($p['icon'] ?? '💊');
$BADGE_LABELS = ['otc'=>'Без рецепта','rx'=>'Рецептурный','sale'=>'Акция','generic'=>'Дженерик'];
$badgeLabel = $BADGE_LABELS[$p['badge_type']] ?? 'Без рецепта';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $name ?> — Аптека</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--green:#1a6b4a;--green-light:#2e9b6f;--green-pale:#e8f5ef;--cream:#faf9f6;--dark:#162520;--gray:#6b7a74;--border:#d4e4dc;--white:#fff;--red:#d64040;--orange:#e07a30;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'IBM Plex Sans',sans-serif;background:var(--cream);color:var(--dark);}
header{background:var(--white);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.header-top{background:var(--green);color:white;text-align:center;font-size:12px;padding:6px;}
.header-main{max-width:1200px;margin:0 auto;padding:14px 24px;display:flex;align-items:center;gap:24px;}
.logo{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--green);text-decoration:none;display:flex;align-items:center;gap:8px;}
.search-bar{flex:1;display:flex;border:2px solid var(--green);border-radius:8px;overflow:hidden;}
.search-bar input{flex:1;border:none;padding:10px 16px;font-size:14px;font-family:inherit;outline:none;background:#f7faf8;}
.search-bar button{background:var(--green);border:none;padding:10px 20px;color:white;cursor:pointer;font-size:16px;}
.header-actions{display:flex;gap:12px;align-items:center;}
.cart-btn{background:var(--green-pale);border:none;padding:10px 18px;border-radius:8px;cursor:pointer;font-family:inherit;font-size:13px;font-weight:500;color:var(--green);display:flex;align-items:center;gap:6px;}
.cart-count{background:var(--red);color:white;border-radius:50%;width:18px;height:18px;font-size:10px;display:none;align-items:center;justify-content:center;font-weight:600;}
.auth-btn-wrap{display:flex;align-items:center;gap:8px;}
.auth-user-btn{display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--dark);background:var(--green-pale);padding:8px 14px;border-radius:8px;font-size:13px;font-weight:500;}
.auth-avatar{width:28px;height:28px;background:var(--green);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;}
.auth-logout-btn{background:none;border:1.5px solid var(--border);padding:8px 14px;border-radius:8px;cursor:pointer;font-family:inherit;font-size:13px;color:var(--gray);}
.auth-login-btn{background:var(--green);color:white;text-decoration:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;}
.auth-register-btn{background:none;color:var(--green);text-decoration:none;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:500;border:1.5px solid var(--green);}
.breadcrumb{max-width:1200px;margin:0 auto;padding:16px 24px;font-size:13px;color:var(--gray);}
.breadcrumb a{color:var(--green);text-decoration:none;}
.product-page{max-width:1200px;margin:0 auto;padding:0 24px 60px;display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start;}
.product-left{background:white;border:1px solid var(--border);border-radius:20px;padding:40px;text-align:center;}
.product-big-icon{font-size:120px;margin-bottom:20px;}
.product-badge-big{display:inline-block;font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px;margin-bottom:16px;}
.badge-otc{background:var(--green-pale);color:var(--green);}
.badge-rx{background:#fce8e8;color:var(--red);}
.badge-sale{background:#fff3e0;color:var(--orange);}
.badge-generic{background:#e8eaf6;color:#3949ab;}
.product-inn-line{font-size:14px;color:var(--gray);margin-bottom:8px;}
.btn-primary{background:var(--green);color:white;border:none;padding:14px 28px;border-radius:10px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;margin:8px 4px;}
.btn-secondary{background:var(--green-pale);color:var(--green);border:none;padding:14px 28px;border-radius:10px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;margin:8px 4px;}
.product-right{}
.product-title{font-family:'Playfair Display',serif;font-size:36px;font-weight:700;margin-bottom:8px;}
.product-subtitle{font-size:15px;color:var(--gray);margin-bottom:24px;}
.price-block{margin-bottom:24px;}
.price-main{font-family:'Playfair Display',serif;font-size:42px;font-weight:700;color:var(--green);}
.price-old{font-size:20px;color:var(--gray);text-decoration:line-through;margin-left:8px;}
.info-section{margin-bottom:24px;}
.info-section-title{font-weight:700;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;color:var(--gray);margin-bottom:8px;}
.info-text{font-size:14px;line-height:1.7;color:var(--dark);}
.avail-table{width:100%;border-collapse:collapse;font-size:13px;}
.avail-table th{text-align:left;color:var(--gray);font-weight:500;padding:8px;border-bottom:2px solid var(--border);}
.avail-table td{padding:10px 8px;border-bottom:1px solid var(--border);}
.stock-ok{color:var(--green);font-weight:600;}
.stock-low{color:var(--orange);font-weight:600;}
.stock-no{color:var(--red);font-weight:600;}
.analog-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);}
.analog-name{font-weight:500;font-size:14px;}
.economy-tag{font-size:11px;background:var(--green-pale);color:var(--green);padding:2px 8px;border-radius:10px;font-weight:500;}
/* Cart */
.cart-modal{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;display:none;}
.cart-modal.open{display:block;}
.cart-panel{position:absolute;right:0;top:0;bottom:0;width:420px;background:white;display:flex;flex-direction:column;box-shadow:-8px 0 32px rgba(0,0,0,.15);}
.cart-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.cart-header h2{font-family:'Playfair Display',serif;font-size:20px;}
.cart-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray);}
.cart-body{flex:1;overflow-y:auto;padding:16px;}
.cart-empty{text-align:center;padding:40px 20px;color:var(--gray);}
.cart-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
.ci-icon{font-size:28px;width:44px;height:44px;background:var(--green-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ci-info{flex:1;}.ci-name{font-weight:600;font-size:13px;text-decoration:none;color:var(--dark);}
.ci-form,.ci-price-unit{font-size:11px;color:var(--gray);}
.ci-controls{display:flex;align-items:center;gap:6px;}
.qty-btn{width:26px;height:26px;border:1.5px solid var(--border);border-radius:6px;background:white;cursor:pointer;}
.qty-val{font-weight:600;font-size:13px;min-width:20px;text-align:center;}
.ci-total{font-weight:700;color:var(--green);font-size:13px;font-family:'Playfair Display',serif;min-width:60px;text-align:right;}
.ci-remove{background:none;border:none;cursor:pointer;color:var(--gray);font-size:14px;padding:4px;}
.cart-footer{padding:16px 24px;border-top:2px solid var(--border);}
.cart-summary{display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;}
.cart-total-amt{font-weight:700;color:var(--green);}
.cart-checkout-title{font-size:12px;font-weight:600;color:var(--gray);margin-bottom:8px;text-transform:uppercase;}
.cart-form-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;}
.cart-form-row input,.cart-form-row select{border:1.5px solid var(--border);border-radius:8px;padding:9px 12px;font-family:inherit;font-size:13px;outline:none;width:100%;}
.cart-form-row select{grid-column:1/-1;}
.btn-checkout{width:100%;background:var(--green);color:white;border:none;padding:13px;border-radius:10px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;margin-bottom:8px;}
.btn-clear{width:100%;background:none;color:var(--gray);border:1.5px solid var(--border);padding:10px;border-radius:10px;font-size:13px;font-family:inherit;cursor:pointer;}
.toast{position:fixed;bottom:24px;right:24px;background:var(--green);color:white;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:500;z-index:999;transform:translateY(80px);opacity:0;transition:all .3s;pointer-events:none;}
.toast.show{transform:translateY(0);opacity:1;}
.order-success-modal{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:600;display:none;align-items:center;justify-content:center;}
.order-success-modal.open{display:flex;}
.os-box{background:white;border-radius:20px;padding:40px;text-align:center;max-width:400px;}
.os-icon{font-size:56px;margin-bottom:16px;}
.os-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:700;margin-bottom:8px;}
.os-text{font-size:14px;color:var(--gray);margin-bottom:24px;line-height:1.7;}
.os-btn{background:var(--green);color:white;border:none;padding:13px 32px;border-radius:10px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;}
/* Booking form */
.book-form-section{background:white;border:1px solid var(--border);border-radius:16px;padding:28px;margin-top:24px;}
.book-form-title{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;margin-bottom:16px;}
.book-form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;}
.book-form-row input,.book-form-row select{border:1.5px solid var(--border);border-radius:9px;padding:11px 14px;font-family:inherit;font-size:14px;outline:none;width:100%;}
.book-form-row input:focus,.book-form-row select:focus{border-color:var(--green);}
.book-form-row select{grid-column:1/-1;}
.btn-book-full{width:100%;background:var(--green);color:white;border:none;padding:14px;border-radius:10px;font-size:15px;font-weight:600;font-family:inherit;cursor:pointer;}
/* Success overlay */
.success-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:600;display:none;align-items:center;justify-content:center;}
.success-overlay.open{display:flex;}
</style>
</head>
<body>

<header>
  <div class="header-top">🕐 Пн–Пт 8:00–20:00, Сб–Вс 9:00–18:00 &nbsp;|&nbsp; 📞 +375 (17) 123-45-67</div>
  <div class="header-main">
    <a href="index.php" class="logo"><span>💊</span> Аптека</a>
    <form class="search-bar" method="get" action="index.php">
      <input type="text" name="q" placeholder="Поиск препаратов...">
      <button type="submit">🔍</button>
    </form>
    <div class="header-actions">
      <button class="cart-btn" onclick="openCart()">
        🛒 Корзина <span class="cart-count" id="cartCount">0</span>
      </button>
      <div id="headerAuth"></div>
    </div>
  </div>
</header>

<div class="breadcrumb">
  <a href="index.php">Каталог</a> → <?= $name ?>
</div>

<div class="product-page">

  <!-- Левая колонка -->
  <div>
    <div class="product-left">
      <div class="product-big-icon"><?= $icon ?></div>
      <span class="product-badge-big badge-<?= $p['badge_type'] ?>"><?= $badgeLabel ?></span>
      <div class="product-inn-line">
        <?= htmlspecialchars(trim(($p['inn']??'').' '.($p['dosage']??'').' '.($p['form']??''))) ?>
      </div>
      <div style="margin-top:16px;">
        <button class="btn-secondary" onclick="cartAdd({slug:'<?= $p['slug'] ?>',name:'<?= addslashes($p['name']) ?>',icon:'<?= addslashes($p['icon']??'💊') ?>',price:'<?= $price ?>',form:'<?= addslashes($p['form']??'') ?>'})">🛒 В корзину</button>
        <button class="btn-primary" onclick="document.getElementById('bookForm').scrollIntoView({behavior:'smooth'})">🔒 Забронировать</button>
      </div>
    </div>

    <!-- Наличие по аптекам -->
    <?php if ($stockRows): ?>
    <div class="product-left" style="margin-top:16px;text-align:left;">
      <div class="info-section-title" style="margin-bottom:12px">Наличие в аптеках</div>
      <table class="avail-table">
        <tr><th>Адрес</th><th>Режим</th><th>Цена</th><th>Кол-во</th></tr>
        <?php foreach($stockRows as $row):
          $qty = (int)$row['quantity'];
          $cls = $qty === 0 ? 'stock-no' : ($qty < 5 ? 'stock-low' : 'stock-ok');
          $label = $qty === 0 ? 'Нет' : ($qty < 5 ? "Мало ($qty уп.)" : "$qty уп.");
        ?>
        <tr>
          <td><?= htmlspecialchars($row['address']) ?></td>
          <td><?= htmlspecialchars($row['working_hours'] ?? '') ?></td>
          <td><?= number_format((float)$row['price'],2,',','') ?> руб.</td>
          <td class="<?= $cls ?>"><?= $label ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- Аналоги -->
    <?php if ($analogRows): ?>
    <div class="product-left" style="margin-top:16px;text-align:left;">
      <div class="info-section-title" style="margin-bottom:12px">Аналоги и дженерики</div>
      <?php foreach($analogRows as $a): ?>
      <div class="analog-row">
        <span class="analog-name"><?= htmlspecialchars($a['analog_name']) ?></span>
        <span>
          <strong><?= number_format((float)$a['analog_price'],2,',','') ?> руб.</strong>
          <?php if ($a['economy_percent']): ?>
            <span class="economy-tag">Экономия <?= $a['economy_percent'] ?>%</span>
          <?php endif; ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Правая колонка -->
  <div class="product-right">
    <div class="product-title"><?= $name ?></div>
    <div class="product-subtitle"><?= htmlspecialchars(($p['inn']??'').' '.($p['dosage']??'')) ?></div>

    <div class="price-block">
      <span class="price-main"><?= $price ?> руб.</span>
      <?php if ($oldPrice): ?>
        <span class="price-old"><?= $oldPrice ?> руб.</span>
      <?php endif; ?>
    </div>

    <?php if ($p['description']): ?>
    <div class="info-section">
      <div class="info-section-title">О препарате</div>
      <div class="info-text"><?= nl2br(htmlspecialchars($p['description'])) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($p['usage_instructions']): ?>
    <div class="info-section">
      <div class="info-section-title">Способ применения</div>
      <div class="info-text"><?= nl2br(htmlspecialchars($p['usage_instructions'])) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($p['contraindications']): ?>
    <div class="info-section">
      <div class="info-section-title">Противопоказания</div>
      <div class="info-text"><?= nl2br(htmlspecialchars($p['contraindications'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- Форма бронирования -->
    <div class="book-form-section" id="bookForm">
      <div class="book-form-title">🔒 Забронировать <?= $name ?></div>
      <div class="book-form-row">
        <input type="text" id="bName"  placeholder="Ваше имя">
        <input type="tel"  id="bPhone" placeholder="+375 (XX) XXX-XX-XX">
      </div>
      <div class="book-form-row">
        <select id="bPharmacy">
          <option>ул. Ленина, 12 (8:00–20:00)</option>
          <option>пр. Независимости, 45 (9:00–21:00)</option>
          <option>ул. Притыцкого, 101 (8:00–22:00)</option>
        </select>
      </div>
      <button class="btn-book-full" onclick="submitBook()">🔒 Забронировать <?= $name ?></button>
    </div>
  </div>

</div>

<!-- Cart drawer -->
<div class="cart-modal" id="cartModal">
  <div class="cart-panel">
    <div class="cart-header">
      <h2>🛒 Корзина</h2>
      <button class="cart-close" onclick="closeCart()">✕</button>
    </div>
    <div class="cart-body" id="cartBody"></div>
    <div class="cart-footer" id="cartFooter" style="display:none">
      <div class="cart-summary">
        <span id="cartItemsCount"></span>
        <span>Итого: <span class="cart-total-amt" id="cartTotalAmt"></span></span>
      </div>
      <div class="cart-checkout-title">Данные для бронирования:</div>
      <div class="cart-form-row">
        <input type="text" id="orderName" placeholder="Ваше имя">
        <input type="tel"  id="orderPhone" placeholder="+375 (XX) XXX-XX-XX">
      </div>
      <div class="cart-form-row">
        <select id="orderPharmacy">
          <option>ул. Ленина, 12 (8:00–20:00)</option>
          <option>пр. Независимости, 45 (9:00–21:00)</option>
          <option>ул. Притыцкого, 101 (8:00–22:00)</option>
        </select>
      </div>
      <button class="btn-checkout" onclick="submitOrder()">🔒 Оформить бронь</button>
      <button class="btn-clear" onclick="if(confirm('Очистить?'))cartClear()">Очистить корзину</button>
    </div>
  </div>
</div>

<div class="order-success-modal" id="orderSuccessModal">
  <div class="os-box">
    <div class="os-icon">✅</div>
    <div class="os-title">Бронь оформлена!</div>
    <div class="os-text" id="osText">Заказ зарезервирован на 3 часа.</div>
    <button class="os-btn" onclick="document.getElementById('orderSuccessModal').classList.remove('open')">Закрыть</button>
  </div>
</div>

<div class="success-overlay" id="successOverlay">
  <div class="os-box">
    <div class="os-icon">✅</div>
    <div class="os-title">Забронировано!</div>
    <div class="os-text"><?= $name ?> зарезервирован на 3 часа. Ждём вас!</div>
    <button class="os-btn" onclick="document.getElementById('successOverlay').classList.remove('open')">Закрыть</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="cart.js"></script>
</body>
</html>
