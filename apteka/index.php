<?php
require_once 'db.php';
$products = getProducts();
$search   = trim($_GET['q'] ?? '');
if ($search) {
    $sq = mb_strtolower($search);
    $products = array_filter($products, function($p) use ($sq) {
        return mb_stripos($p['name'], $sq) !== false
            || mb_stripos($p['inn'] ?? '', $sq) !== false
            || mb_stripos($p['form'] ?? '', $sq) !== false;
    });
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Аптека — Лекарства и товары для здоровья</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<?php /* Вставляем весь CSS из оригинального index.html */ ?>
<style>
  :root{--green:#1a6b4a;--green-light:#2e9b6f;--green-pale:#e8f5ef;--teal:#0d7a6e;--cream:#faf9f6;--dark:#162520;--gray:#6b7a74;--border:#d4e4dc;--white:#fff;--red:#d64040;--orange:#e07a30;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'IBM Plex Sans',sans-serif;background:var(--cream);color:var(--dark);font-size:15px;line-height:1.6;}
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
  .hero{background:linear-gradient(135deg,#0e3d2c 0%,#1a6b4a 60%,#2e9b6f 100%);color:white;padding:60px 24px;}
  .hero-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;}
  .hero h1{font-family:'Playfair Display',serif;font-size:42px;line-height:1.2;margin-bottom:16px;font-weight:700;}
  .hero p{font-size:15px;opacity:.85;margin-bottom:28px;line-height:1.7;}
  .hero-search{display:flex;border:2px solid rgba(255,255,255,.4);border-radius:10px;overflow:hidden;background:rgba(255,255,255,.1);}
  .hero-search input{flex:1;border:none;background:transparent;padding:14px 18px;font-size:15px;color:white;font-family:inherit;outline:none;}
  .hero-search input::placeholder{color:rgba(255,255,255,.6);}
  .hero-search button{background:white;border:none;padding:14px 24px;color:var(--green);font-weight:600;font-size:14px;font-family:inherit;cursor:pointer;}
  .quick-tag{display:inline-flex;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:white;font-size:12px;padding:6px 14px;border-radius:20px;margin:3px;cursor:pointer;text-decoration:none;}
  .main-content{max-width:1200px;margin:0 auto;padding:40px 24px;}
  .section-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:700;color:var(--dark);margin-bottom:20px;display:flex;align-items:center;gap:10px;}
  .section-title::after{content:'';flex:1;height:1px;background:var(--border);}
  .categories{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:48px;}
  .cat-card{background:white;border:1px solid var(--border);border-radius:12px;padding:20px 12px;text-align:center;cursor:pointer;transition:all .2s;text-decoration:none;color:var(--dark);}
  .cat-card:hover{border-color:var(--green);transform:translateY(-2px);}
  .cat-icon{font-size:32px;margin-bottom:8px;}
  .cat-name{font-size:12px;font-weight:500;color:var(--gray);}
  .filter-bar{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;}
  .filter-btn{background:white;border:1.5px solid var(--border);padding:8px 16px;border-radius:20px;cursor:pointer;font-size:13px;font-family:inherit;transition:all .2s;}
  .filter-btn:hover,.filter-btn.active{background:var(--green);color:white;border-color:var(--green);}
  .products-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:48px;}
  .product-card{background:white;border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:all .25s;position:relative;text-decoration:none;color:var(--dark);display:block;}
  .product-card:hover{box-shadow:0 8px 24px rgba(26,107,74,.12);transform:translateY(-3px);}
  .product-badge{position:absolute;top:12px;left:12px;font-size:10px;font-weight:600;padding:3px 8px;border-radius:4px;text-transform:uppercase;}
  .badge-rx{background:#fce8e8;color:var(--red);}
  .badge-otc{background:var(--green-pale);color:var(--green);}
  .badge-sale{background:#fff3e0;color:var(--orange);}
  .badge-generic{background:#e8eaf6;color:#3949ab;}
  .product-img{background:linear-gradient(135deg,#f0f7f3,#e8f5ef);height:140px;display:flex;align-items:center;justify-content:center;font-size:56px;}
  .product-body{padding:14px;}
  .product-name{font-weight:600;font-size:14px;margin-bottom:4px;}
  .product-inn{font-size:11px;color:var(--gray);margin-bottom:8px;}
  .product-form{font-size:11px;color:var(--teal);background:#e8f5ef;display:inline-block;padding:2px 8px;border-radius:4px;margin-bottom:10px;}
  .product-footer{display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid var(--border);}
  .product-price{font-size:18px;font-weight:700;color:var(--green);font-family:'Playfair Display',serif;}
  .product-price-old{font-size:12px;color:var(--gray);text-decoration:line-through;margin-left:4px;}
  .btn-book{background:var(--green);color:white;border:none;padding:8px 14px;border-radius:7px;font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;}
  .pharmacy-nearest{font-size:11px;color:var(--gray);margin-top:6px;}
  .no-results{grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--gray);}
  .no-results-icon{font-size:48px;margin-bottom:12px;}
  footer{background:#162520;color:rgba(255,255,255,.7);padding:48px 24px 24px;}
  .footer-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:32px;}
  .footer-logo{font-family:'Playfair Display',serif;font-size:22px;color:white;margin-bottom:12px;}
  .footer-desc{font-size:13px;line-height:1.7;margin-bottom:16px;}
  .footer-col h4{color:white;font-size:13px;font-weight:600;margin-bottom:12px;}
  .footer-col a{display:block;color:rgba(255,255,255,.6);text-decoration:none;font-size:12px;margin-bottom:6px;}
  .footer-bottom{max-width:1200px;margin:0 auto;padding-top:24px;border-top:1px solid rgba(255,255,255,.1);display:flex;justify-content:space-between;font-size:12px;}
  /* Cart drawer */
  .cart-modal{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;display:none;}
  .cart-modal.open{display:block;}
  .cart-panel{position:absolute;right:0;top:0;bottom:0;width:420px;background:white;display:flex;flex-direction:column;box-shadow:-8px 0 32px rgba(0,0,0,.15);}
  .cart-header{padding:20px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
  .cart-header h2{font-family:'Playfair Display',serif;font-size:20px;}
  .cart-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray);}
  .cart-body{flex:1;overflow-y:auto;padding:16px;}
  .cart-empty{text-align:center;padding:40px 20px;color:var(--gray);}
  .cart-empty-icon{font-size:48px;margin-bottom:12px;}
  .cart-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
  .ci-icon{font-size:28px;width:44px;height:44px;background:var(--green-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
  .ci-info{flex:1;}
  .ci-name{font-weight:600;font-size:13px;text-decoration:none;color:var(--dark);}
  .ci-form{font-size:11px;color:var(--gray);}
  .ci-price-unit{font-size:11px;color:var(--green);}
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
</style>
</head>
<body>

<header>
  <div class="header-top">🕐 Пн–Пт 8:00–20:00, Сб–Вс 9:00–18:00 &nbsp;|&nbsp; 📞 +375 (17) 123-45-67</div>
  <div class="header-main">
    <a href="index.php" class="logo"><span>💊</span> Аптека</a>
    <form class="search-bar" method="get" action="index.php">
      <input type="text" name="q" placeholder="Поиск по названию или МНН..." value="<?= htmlspecialchars($search) ?>">
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

<section class="hero">
  <div class="hero-inner">
    <div>
      <h1>Лекарства с доставкой в аптеку рядом с вами</h1>
      <p>Бронируйте препараты онлайн и забирайте в удобное время. Без очередей.</p>
      <form class="hero-search" method="get" action="index.php">
        <input type="text" name="q" placeholder="Введите название препарата или МНН..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Найти</button>
      </form>
      <div style="margin-top:20px;">
        <?php foreach(array_slice($products, 0, 5) as $p): ?>
          <a class="quick-tag" href="product.php?slug=<?= $p['slug'] ?>"><?= htmlspecialchars($p['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div></div>
  </div>
</section>

<main class="main-content">

  <?php if ($search): ?>
    <h2 class="section-title">Результаты поиска: «<?= htmlspecialchars($search) ?>»</h2>
  <?php else: ?>
    <h2 class="section-title">Популярные препараты</h2>
    <div class="filter-bar">
      <button class="filter-btn active" onclick="filterCat('all',this)">Все</button>
      <button class="filter-btn" onclick="filterCat('antibiotics',this)">Антибиотики</button>
      <button class="filter-btn" onclick="filterCat('pain',this)">Обезболивающие</button>
      <button class="filter-btn" onclick="filterCat('vitamins',this)">Витамины</button>
      <button class="filter-btn" onclick="filterCat('hygiene',this)">Гигиена</button>
      <button class="filter-btn" onclick="filterCat('antiviral',this)">Противовирусные</button>
      <button class="filter-btn" onclick="filterCat('allergy',this)">Аллергия</button>
    </div>
  <?php endif; ?>

  <div class="products-grid" id="productsGrid">
    <?php if (empty($products)): ?>
      <div class="no-results">
        <div class="no-results-icon">🔍</div>
        <div>По запросу «<?= htmlspecialchars($search) ?>» ничего не найдено</div>
      </div>
    <?php else: ?>
      <?php foreach($products as $p):
        $catSlug  = $CAT_SLUGS[$p['category_id']] ?? 'other';
        $badgeLabel = $BADGE_LABELS[$p['badge_type']] ?? 'Без рецепта';
        $price    = number_format((float)$p['price'], 2, ',', '');
        $oldPrice = $p['old_price'] ? number_format((float)$p['old_price'], 2, ',', '') : null;
        $inn      = trim(($p['inn'] ?? '') . ' ' . ($p['dosage'] ?? ''));
        $addr     = $p['pharmacy_address'] ? htmlspecialchars($p['pharmacy_address']) : 'ул. Ленина, 12';
        $icon     = htmlspecialchars($p['icon'] ?? '💊');
        $form     = htmlspecialchars($p['form'] ?? '');
        $name     = htmlspecialchars($p['name']);
        $slug     = $p['slug'];
        $jsPrice  = $price;
      ?>
      <a href="product.php?slug=<?= $slug ?>" class="product-card" data-cat="<?= $catSlug ?>">
        <div class="product-badge badge-<?= $p['badge_type'] ?>"><?= $badgeLabel ?></div>
        <div class="product-img"><?= $icon ?></div>
        <div class="product-body">
          <div class="product-name"><?= $name ?></div>
          <?php if ($inn): ?><div class="product-inn"><?= htmlspecialchars($inn) ?></div><?php endif; ?>
          <?php if ($form): ?><span class="product-form"><?= $form ?></span><?php endif; ?>
          <div class="pharmacy-nearest">📍 <?= $addr ?> — <?= $price ?> руб.</div>
          <div class="product-footer">
            <div>
              <span class="product-price"><?= $price ?> руб.</span>
              <?php if ($oldPrice): ?><span class="product-price-old"><?= $oldPrice ?></span><?php endif; ?>
            </div>
            <button class="btn-book" onclick="event.preventDefault(); cartAdd({slug:'<?= $slug ?>',name:'<?= addslashes($p['name']) ?>',icon:'<?= addslashes($p['icon'] ?? '💊') ?>',price:'<?= $jsPrice ?>',form:'<?= addslashes($form) ?>'})">В корзину</button>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</main>

<footer>
  <div class="footer-inner">
    <div>
      <div class="footer-logo">💊 Аптека</div>
      <div class="footer-desc">Бронирование лекарственных средств с самовывозом из аптечных пунктов.</div>
    </div>
    <div class="footer-col">
      <h4>Каталог</h4>
      <?php foreach(array_slice($products, 0, 5) as $p): ?>
        <a href="product.php?slug=<?= $p['slug'] ?>"><?= htmlspecialchars($p['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="footer-col">
      <h4>Аккаунт</h4>
      <a href="login.html">Войти</a>
      <a href="login.html#register">Регистрация</a>
      <a href="profile.html">Личный кабинет</a>
    </div>
    <div class="footer-col">
      <h4>Контакты</h4>
      <a href="#">+375 (17) 123-45-67</a>
      <a href="#">Пн–Пт: 8:00–20:00</a>
      <a href="#">Сб–Вс: 9:00–18:00</a>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© 2026 Аптека</span>
  </div>
</footer>

<!-- CART DRAWER -->
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
    <div class="os-text" id="osText">Заказ зарезервирован на 3 часа. Мы свяжемся с вами для подтверждения.</div>
    <button class="os-btn" onclick="document.getElementById('orderSuccessModal').classList.remove('open')">Закрыть</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="cart.js"></script>
<script>
function filterCat(cat, btn) {
  document.querySelectorAll('.product-card').forEach(function(c) {
    c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
  });
  if (btn) {
    document.querySelectorAll('.filter-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
  }
}
</script>
</body>
</html>
