/* ============================================================
   cart.js — всё в одном файле: авторизация + корзина
   ============================================================ */

const CART_KEY = 'apteka_cart';
const AUTH_KEY = 'apteka_user';
const API_BASE = 'api.php';

/* ══ АВТОРИЗАЦИЯ ══════════════════════════════════════════ */

function authGetUser() {
  try { return JSON.parse(localStorage.getItem(AUTH_KEY)); } catch { return null; }
}

function authUpdateHeader() {
  const user      = authGetUser();
  const actionsEl = document.querySelector('.header-actions');
  if (!actionsEl) return;
  const old = actionsEl.querySelector('.auth-btn-wrap');
  if (old) old.remove();
  const wrap = document.createElement('div');
  wrap.className = 'auth-btn-wrap';
  wrap.style.cssText = 'display:flex;align-items:center;gap:8px;';
  if (user) {
    wrap.innerHTML = `
      <a href="profile.html" class="auth-user-btn" title="Личный кабинет">
        <span class="auth-avatar">${user.login.charAt(0).toUpperCase()}</span>
        <span class="auth-username">${user.fullName || user.login}</span>
      </a>
      <button class="auth-logout-btn" onclick="authLogout()">↪ Выйти</button>`;
  } else {
    wrap.innerHTML = `
      <a href="login.html" class="auth-login-btn">Войти</a>
      <a href="login.html#register" class="auth-register-btn">Регистрация</a>`;
  }
  actionsEl.insertBefore(wrap, actionsEl.firstChild);
}

function authLogout() {
  const user = authGetUser();
  if (user && user.token && !user.token.startsWith('demo_')) {
    fetch(API_BASE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'logout', token: user.token })
    }).catch(() => {});
  }
  localStorage.removeItem(AUTH_KEY);
  localStorage.removeItem(CART_KEY);
  showToast('👋 Вы вышли из аккаунта');
  setTimeout(() => { window.location.href = 'index.html'; }, 800);
}

/* ══ КОРЗИНА ══════════════════════════════════════════════ */

function cartLoad() {
  try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
  catch { return []; }
}

function cartSave(items) {
  localStorage.setItem(CART_KEY, JSON.stringify(items));
  syncCartToDB(items);
}

function cartAdd(product) {
  const user = authGetUser();
  if (!user) {
    showToast('⚠️ Войдите, чтобы добавить товар в корзину');
    setTimeout(() => {
      window.location.href = 'login.html?back=' + encodeURIComponent(location.pathname + location.search);
    }, 1200);
    return;
  }
  const items = cartLoad();
  const idx   = items.findIndex(i => i.slug === product.slug);
  if (idx >= 0) items[idx].qty += 1;
  else items.push({ ...product, qty: 1 });
  cartSave(items);
  cartUpdateBadge();
  showToast('✅ ' + product.name + ' добавлен в корзину');
}

function cartRemove(slug) {
  const items = cartLoad().filter(i => i.slug !== slug);
  cartSave(items);
  cartUpdateBadge();
  renderCartModal();
}

function cartSetQty(slug, qty) {
  const items = cartLoad();
  const idx   = items.findIndex(i => i.slug === slug);
  if (idx < 0) return;
  if (qty < 1) { cartRemove(slug); return; }
  items[idx].qty = qty;
  cartSave(items);
  cartUpdateBadge();
  renderCartModal();
}

function cartClear() {
  cartSave([]);
  cartUpdateBadge();
  renderCartModal();
}

function cartTotal() {
  return cartLoad().reduce(function(s, i) {
    return s + parseFloat(String(i.price).replace(',', '.')) * i.qty;
  }, 0);
}

function cartCount() {
  return cartLoad().reduce(function(s, i) { return s + i.qty; }, 0);
}

function cartUpdateBadge() {
  var cnt = cartCount();
  document.querySelectorAll('.cart-count').forEach(function(el) {
    el.textContent   = cnt;
    el.style.display = cnt > 0 ? 'flex' : 'none';
  });
}

/* ══ СИНХРОНИЗАЦИЯ С БД ═══════════════════════════════════ */

function syncCartToDB(items) {
  var user = authGetUser();
  if (!user || !user.token || user.token.indexOf('demo_') === 0) return;
  fetch(API_BASE, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'cart_sync', token: user.token, items: items || cartLoad() })
  }).catch(function() {});
}

function loadCartFromServer() {
  var user = authGetUser();
  if (!user || !user.token || user.token.indexOf('demo_') === 0) return;
  fetch(API_BASE, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'cart_get', token: user.token })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success && Array.isArray(data.items) && data.items.length > 0) {
      var local  = cartLoad();
      var merged = data.items.slice();
      local.forEach(function(loc) {
        var idx = merged.findIndex(function(s) { return s.slug === loc.slug; });
        if (idx >= 0) merged[idx].qty = Math.max(merged[idx].qty, loc.qty);
        else merged.push(loc);
      });
      localStorage.setItem(CART_KEY, JSON.stringify(merged));
      cartUpdateBadge();
    }
  })
  .catch(function() {});
}

/* ══ МОДАЛКА КОРЗИНЫ ══════════════════════════════════════ */

function openCart() {
  var user = authGetUser();
  if (!user) {
    showToast('⚠️ Войдите, чтобы открыть корзину');
    setTimeout(function() {
      window.location.href = 'login.html?back=' + encodeURIComponent(location.pathname);
    }, 1000);
    return;
  }
  var modal = document.getElementById('cartModal');
  if (modal) modal.classList.add('open');
  renderCartModal();
  var nameEl  = document.getElementById('orderName');
  var phoneEl = document.getElementById('orderPhone');
  if (nameEl  && !nameEl.value  && user.fullName) nameEl.value  = user.fullName;
  if (phoneEl && !phoneEl.value && user.phone)    phoneEl.value = user.phone;
}

function closeCart() {
  var modal = document.getElementById('cartModal');
  if (modal) modal.classList.remove('open');
}

function renderCartModal() {
  var items  = cartLoad();
  var body   = document.getElementById('cartBody');
  var footer = document.getElementById('cartFooter');
  if (!body) return;

  if (items.length === 0) {
    body.innerHTML =
      '<div class="cart-empty">' +
      '<div class="cart-empty-icon">🛒</div>' +
      '<div class="cart-empty-title">Корзина пуста</div>' +
      '<div class="cart-empty-sub">Добавьте препараты из каталога</div>' +
      '<a href="index.html" class="btn-to-catalog">Перейти в каталог</a>' +
      '</div>';
    if (footer) footer.style.display = 'none';
    return;
  }

  if (footer) footer.style.display = 'block';

  var html = '';
  items.forEach(function(item) {
    var unitPrice  = parseFloat(String(item.price).replace(',', '.'));
    var lineTotal  = (unitPrice * item.qty).toFixed(2).replace('.', ',');
    html +=
      '<div class="cart-item">' +
      '<div class="ci-icon">' + (item.icon || '💊') + '</div>' +
      '<div class="ci-info">' +
        '<a href="product.php?slug=' + item.slug + '" class="ci-name">' + item.name + '</a>' +
        '<div class="ci-form">' + (item.form || '') + '</div>' +
        '<div class="ci-price-unit">' + item.price + ' руб. / уп.</div>' +
      '</div>' +
      '<div class="ci-controls">' +
        '<button class="qty-btn" onclick="cartSetQty(\'' + item.slug + '\',' + (item.qty - 1) + ')">−</button>' +
        '<span class="qty-val">' + item.qty + '</span>' +
        '<button class="qty-btn" onclick="cartSetQty(\'' + item.slug + '\',' + (item.qty + 1) + ')">+</button>' +
      '</div>' +
      '<div class="ci-total">' + lineTotal + ' руб.</div>' +
      '<button class="ci-remove" onclick="cartRemove(\'' + item.slug + '\')">✕</button>' +
      '</div>';
  });
  body.innerHTML = html;

  var totalEl = document.getElementById('cartTotalAmt');
  var countEl = document.getElementById('cartItemsCount');
  if (totalEl) totalEl.textContent = cartTotal().toFixed(2).replace('.', ',') + ' руб.';
  if (countEl) countEl.textContent = cartCount() + ' ' + plural(cartCount(), 'товар', 'товара', 'товаров');
}

function plural(n, one, few, many) {
  var m = n % 10, m100 = n % 100;
  if (m === 1 && m100 !== 11) return one;
  if (m >= 2 && m <= 4 && (m100 < 10 || m100 >= 20)) return few;
  return many;
}

/* ══ ОФОРМЛЕНИЕ БРОНИ ═════════════════════════════════════ */

function submitOrder() {
  var nameEl  = document.getElementById('orderName');
  var phoneEl = document.getElementById('orderPhone');
  var name    = nameEl  ? nameEl.value.trim()  : '';
  var phone   = phoneEl ? phoneEl.value.trim() : '';

  if (!name || !phone) { showToast('⚠️ Укажите имя и телефон'); return; }
  if (cartLoad().length === 0) { showToast('⚠️ Корзина пуста'); return; }

  var user = authGetUser();
  if (!user) { showToast('⚠️ Войдите в аккаунт'); return; }

  var btn = document.querySelector('.btn-checkout');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Оформляем...'; }

  var pharmacySelect = document.getElementById('orderPharmacy');
  var pharmacyText   = pharmacySelect ? pharmacySelect.value : '';
  var pharmacySlugMap = {
    'Ленина':        'lenina-12',
    'Независимости': 'nezavisimosti-45',
    'Притыцкого':    'pritytskogo-101'
  };
  var pharmacySlug = 'lenina-12';
  for (var key in pharmacySlugMap) {
    if (pharmacyText.indexOf(key) >= 0) { pharmacySlug = pharmacySlugMap[key]; break; }
  }

  fetch(API_BASE, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'checkout', token: user.token, pharmacy_slug: pharmacySlug })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (btn) { btn.disabled = false; btn.textContent = '🔒 Оформить бронь'; }
    if (data.success) {
      closeCart();
      cartClear();
      var osText = document.querySelector('.os-text');
      if (osText && data.order_number) {
        osText.innerHTML = 'Заказ <strong>' + data.order_number + '</strong> зарезервирован на 3 часа.';
      }
      var successModal = document.getElementById('orderSuccessModal');
      if (successModal) successModal.classList.add('open');
    } else {
      showToast('❌ ' + (data.error || 'Ошибка оформления'));
    }
  })
  .catch(function() {
    if (btn) { btn.disabled = false; btn.textContent = '🔒 Оформить бронь'; }
    closeCart();
    cartClear();
    var successModal = document.getElementById('orderSuccessModal');
    if (successModal) successModal.classList.add('open');
  });
}

/* ══ БРОНИРОВАНИЕ СО СТРАНИЦЫ ТОВАРА ═════════════════════ */

function submitBook() {
  var user    = authGetUser();
  var nameEl  = document.getElementById('bName');
  var phoneEl = document.getElementById('bPhone');
  if (user) {
    if (nameEl  && !nameEl.value  && user.fullName) nameEl.value  = user.fullName;
    if (phoneEl && !phoneEl.value && user.phone)    phoneEl.value = user.phone;
  }
  var name  = nameEl  ? nameEl.value.trim()  : '';
  var phone = phoneEl ? phoneEl.value.trim() : '';
  if (!name || !phone) { showToast('⚠️ Укажите имя и телефон'); return; }
  var overlay = document.getElementById('successOverlay');
  if (overlay) overlay.classList.add('open');
}

/* ══ ПОИСК ════════════════════════════════════════════════ */

function doSearch(val) {
  var q     = (val || '').toLowerCase().trim();
  var cards = document.querySelectorAll('.product-card');
  var found = 0;
  cards.forEach(function(c) {
    var match = !q || c.textContent.toLowerCase().indexOf(q) >= 0;
    c.style.display = match ? '' : 'none';
    if (match) found++;
  });
  var noRes = document.getElementById('noResults');
  if (!noRes) {
    noRes = document.createElement('div');
    noRes.id = 'noResults';
    noRes.style.cssText = 'grid-column:1/-1;text-align:center;padding:40px;color:#6b7a74;font-size:15px;';
    var grid = document.getElementById('productsGrid');
    if (grid) grid.appendChild(noRes);
  }
  noRes.style.display = (q && found === 0) ? 'block' : 'none';
  noRes.innerHTML = '🔍 По запросу «' + val + '» ничего не найдено.';
}

function doHeroSearch() {
  var hs = document.getElementById('heroSearch');
  var ms = document.getElementById('mainSearch');
  if (!hs) return;
  if (ms) ms.value = hs.value;
  doSearch(hs.value);
  var grid = document.getElementById('productsGrid');
  if (grid) grid.scrollIntoView({ behavior: 'smooth' });
}

function filterCat(cat, btn) {
  var ms = document.getElementById('mainSearch');
  if (ms) ms.value = '';
  document.querySelectorAll('.product-card').forEach(function(c) {
    c.style.display = (cat === 'all' || c.dataset.cat === cat) ? '' : 'none';
  });
  var noRes = document.getElementById('noResults');
  if (noRes) noRes.style.display = 'none';
  if (btn) {
    document.querySelectorAll('.filter-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
  }
}

/* ══ TOAST ════════════════════════════════════════════════ */

function showToast(msg) {
  var t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(function() { t.classList.remove('show'); }, 3000);
}

/* ══ КОНСУЛЬТАЦИЯ ═════════════════════════════════════════ */

function selectPill(el) {
  document.querySelectorAll('.cat-pill').forEach(function(p) { p.classList.remove('selected'); });
  el.classList.add('selected');
}

function sendConsult() {
  var name    = document.getElementById('consultName').value.trim();
  var contact = document.getElementById('consultContact').value.trim();
  if (!name || !contact) { showToast('⚠️ Укажите имя и контакт'); return; }
  showToast('✅ Вопрос отправлен! Ответим в течение 2 часов.');
  document.getElementById('consultName').value    = '';
  document.getElementById('consultContact').value = '';
  var q = document.getElementById('consultQuestion');
  if (q) q.value = '';
}

/* ══ ИНИЦИАЛИЗАЦИЯ ════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', function() {
  authUpdateHeader();
  loadCartFromServer();
  cartUpdateBadge();

  var cartModal = document.getElementById('cartModal');
  if (cartModal) {
    cartModal.addEventListener('click', function(e) {
      if (e.target === cartModal) closeCart();
    });
  }
  var orderModal = document.getElementById('orderSuccessModal');
  if (orderModal) {
    orderModal.addEventListener('click', function(e) {
      if (e.target === orderModal) orderModal.classList.remove('open');
    });
  }
  var successOverlay = document.getElementById('successOverlay');
  if (successOverlay) {
    successOverlay.addEventListener('click', function(e) {
      if (e.target === successOverlay) successOverlay.classList.remove('open');
    });
  }

  var ms = document.getElementById('mainSearch');
  if (ms) ms.addEventListener('keydown', function(e) { if (e.key === 'Enter') doSearch(ms.value); });
  var hs = document.getElementById('heroSearch');
  if (hs) hs.addEventListener('keydown', function(e) { if (e.key === 'Enter') doHeroSearch(); });
});
