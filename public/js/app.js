const API = (typeof window !== 'undefined' && window.APP_BASE_URL ? window.APP_BASE_URL : '') + '/api/';
const BASE = (typeof window !== 'undefined' && window.APP_BASE_URL ? window.APP_BASE_URL : '');

function toast(msg, type = '') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'toast show ' + type;
  setTimeout(() => t.className = 'toast', 3000);
}

function getCSRF() {
  const match = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : '';
}

async function fetchJSON(url, opts = {}) {
  const headers = { 'Content-Type': 'application/json', 'x-csrf-token': getCSRF(), ...opts.headers };
  const res = await fetch(url, { ...opts, headers });
  const text = await res.text();
  let data;
  try {
    data = text ? JSON.parse(text) : {};
  } catch (e) {
    throw new Error('Server returned an invalid response (HTTP ' + res.status + '). If this is a new feature, restart the server.');
  }
  if (!res.ok) throw new Error(data.error || 'Request failed (' + res.status + ')');
  return data;
}

/* Like fetchJSON, but for multipart/form-data uploads (file inputs).
   The CSRF token goes in as a _csrf field because FormData bodies set
   their own Content-Type and cannot carry the x-csrf-token header. */
async function fetchForm(url, fd, opts = {}) {
  if (!fd.has('_csrf')) fd.append('_csrf', getCSRF());
  const res = await fetch(url, { ...opts, method: opts.method || 'POST', body: fd });
  const text = await res.text();
  let data;
  try {
    data = text ? JSON.parse(text) : {};
  } catch (e) {
    throw new Error('Server returned an invalid response (HTTP ' + res.status + ').');
  }
  if (!res.ok) throw new Error(data.error || 'Request failed (' + res.status + ')');
  return data;
}

async function loadCartCount() {
  try {
    const data = await fetchJSON(BASE + '/api/cart');
    setCartCount(data.count);
  } catch (e) {}
}

/* Cart badge appears in the header and in the mobile bottom nav. */
function setCartCount(n) {
  const count = Math.max(0, parseInt(n, 10) || 0);
  document.querySelectorAll('[data-cart-count]').forEach(function(el) {
    el.textContent = count;
    if (el.hasAttribute('data-cart-count') && el.classList.contains('bottom-nav-badge')) {
      el.hidden = count === 0;
    }
  });
  const header = document.getElementById('cart-count');
  if (header) header.textContent = count;
}

/* ------------------------- Wishlist (localStorage) ------------------------- */
const WISHLIST_KEY = 'handel-luxe-wishlist';

function getWishlist() {
  try {
    const list = JSON.parse(localStorage.getItem(WISHLIST_KEY));
    return Array.isArray(list) ? list : [];
  } catch (e) { return []; }
}

function saveWishlist(list) {
  try { localStorage.setItem(WISHLIST_KEY, JSON.stringify(list)); } catch (e) {}
  updateWishlistCount();
  document.dispatchEvent(new CustomEvent('wishlist:changed'));
}

function isWishlisted(id) {
  id = parseInt(id, 10);
  return getWishlist().some(function(p) { return p.id === id; });
}

function updateWishlistCount() {
  const n = getWishlist().length;
  document.querySelectorAll('[data-wishlist-count]').forEach(function(el) {
    el.textContent = n;
    el.hidden = n === 0;
  });
}

function toggleWishlistItem(product) {
  const id = parseInt(product.id, 10);
  if (!id) return false;
  let list = getWishlist();
  const existing = list.findIndex(function(p) { return p.id === id; });
  if (existing >= 0) {
    list.splice(existing, 1);
    saveWishlist(list);
    toast('Removed from wishlist');
    return false;
  }
  list.push({
    id: id,
    name: String(product.name || ''),
    price: Number(product.price) || 0,
    image: String(product.image || ''),
    stock: parseInt(product.stock, 10) || 0
  });
  saveWishlist(list);
  toast('Saved to wishlist', 'success');
  return true;
}

/* Bound to heart buttons on product cards, the detail page and the
   wishlist page. The product snapshot rides along as data-* attributes. */
function toggleWishlistBtn(btn) {
  if (!btn) return;
  const saved = toggleWishlistItem({
    id: btn.dataset.productId,
    name: btn.dataset.productName,
    price: btn.dataset.productPrice,
    image: btn.dataset.productImage,
    stock: btn.dataset.productStock
  });
  btn.classList.toggle('active', saved);
  btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
  if (btn.classList.contains('pd-wishlist-btn')) {
    btn.innerHTML = wishlistHeartSvg() + ' ' + (saved ? 'Saved to wishlist' : 'Save to wishlist');
  }
}

function wishlistHeartSvg() {
  return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
}

window.toggleWishlistBtn = toggleWishlistBtn;

function formatPrice(n) { return 'GH\u20B5 ' + Number(n).toFixed(2); }

function showProductSkeletons(container, count = 4) {
  if (typeof container === 'string') container = document.querySelector(container);
  if (!container) return;
  container.innerHTML = Array.from({ length: count }, function() {
    return '<article class="product-skeleton" aria-hidden="true">' +
      '<div class="skeleton-block skeleton-image"></div>' +
      '<div class="skeleton-content"><span class="skeleton-block skeleton-line skeleton-short"></span>' +
      '<span class="skeleton-block skeleton-line"></span><span class="skeleton-block skeleton-line skeleton-line-2"></span>' +
      '<span class="skeleton-block skeleton-price"></span><span class="skeleton-block skeleton-badge"></span>' +
      '<span class="skeleton-block skeleton-button"></span></div></article>';
  }).join('');
  container.setAttribute('aria-busy', 'true');
}

function setButtonLoading(button, label) {
  if (!button) return function() {};
  const originalHtml = button.innerHTML;
  const originalDisabled = button.disabled;
  let restored = false;
  function restore() {
    if (restored) return;
    restored = true;
    button.innerHTML = originalHtml;
    button.disabled = originalDisabled;
    button.removeAttribute('aria-busy');
  }
  button.disabled = true;
  button.setAttribute('aria-busy', 'true');
  button.innerHTML = '<span class="button-spinner" aria-hidden="true"></span>' + escapeHtml(label || 'Please wait');
  return function(successLabel) {
    if (!successLabel) {
      restore();
      return;
    }
    button.innerHTML = '<span aria-hidden="true">&#10003;</span> ' + escapeHtml(successLabel);
    button.removeAttribute('aria-busy');
    window.setTimeout(restore, 1400);
  };
}

function applyTheme(theme) {
  var dark = theme === 'dark';
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';
  var toggle = document.getElementById('theme-toggle');
  if (toggle) {
    var label = dark ? 'Light' : 'Dark';
    toggle.setAttribute('aria-label', 'Switch to ' + label.toLowerCase() + ' mode');
    toggle.title = 'Switch to ' + label.toLowerCase() + ' mode';
    toggle.innerHTML = '<span aria-hidden="true">' + (dark ? '&#9728;' : '&#9790;') + '</span><span class="theme-toggle-label">' + label + '</span>';
  }
}

function initThemeToggle() {
  var toggle = document.getElementById('theme-toggle');
  if (!toggle) return;
  var current = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
  applyTheme(current);
  toggle.addEventListener('click', function() {
    current = current === 'dark' ? 'light' : 'dark';
    applyTheme(current);
    try {
      localStorage.setItem('handel-luxe-theme', current);
    } catch (e) {
      toast('Theme preference could not be saved in this browser.', 'error');
    }
  });
}

/* Escape a value before inserting it into an innerHTML template. Admin
   pages render customer-entered text (names, addresses, messages), so
   everything user-controlled must pass through here. */
function escapeHtml(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

function addPasswordToggle(inputEl) {
  const wrapper = document.createElement('div');
  wrapper.className = 'password-toggle';
  inputEl.parentNode.insertBefore(wrapper, inputEl);
  wrapper.appendChild(inputEl);
  inputEl.classList.add('password-input');
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'password-toggle-btn';
  btn.setAttribute('aria-label', 'Toggle password visibility');
  btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  btn.onclick = () => {
    const show = inputEl.type === 'password';
    inputEl.type = show ? 'text' : 'password';
    btn.innerHTML = show ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  };
  wrapper.appendChild(btn);
}

function productCard(p) {
  const placeholder = (typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '') + '/images/placeholder.png';
  const saved = isWishlisted(p.id);
  return `<div class="product-card">
    <div class="img-wrap">
      <a href="${BASE}/product/${p.id}" aria-label="${escapeHtml(p.name)}">
        <img loading="lazy" decoding="async" src="${escapeHtml(p.image || placeholder)}" alt="${escapeHtml(p.name)}">
      </a>
      <button type="button" class="wishlist-btn${saved ? ' active' : ''}" aria-pressed="${saved ? 'true' : 'false'}"
        aria-label="${saved ? 'Remove' : 'Save'} ${escapeHtml(p.name)} ${saved ? 'from' : 'to'} wishlist"
        data-product-id="${escapeHtml(p.id)}" data-product-name="${escapeHtml(p.name)}"
        data-product-price="${escapeHtml(p.price)}" data-product-image="${escapeHtml(p.image || placeholder)}"
        data-product-stock="${escapeHtml(p.stock)}" onclick="toggleWishlistBtn(this)">
        ${wishlistHeartSvg()}
      </button>
    </div>
    <div class="info">
      <div class="category">${escapeHtml(p.category_name || 'General')}</div>
      <a href="${BASE}/product/${p.id}"><div class="name">${escapeHtml(p.name)}</div></a>
      <div class="price">${formatPrice(p.price)}</div>
      <div class="stock-badge ${p.stock > 0 ? 'in' : 'out'}">${p.stock > 0 ? p.stock + ' in stock' : 'Out of stock'}</div>
      <button type="button" class="btn btn-primary btn-sm" onclick="addToCart(${p.id}, this)" ${p.stock <= 0 ? 'disabled' : ''}>Add to Cart</button>
    </div>
  </div>`;
}

async function addToCart(productId, button) {
  const finishLoading = setButtonLoading(button, 'Adding to cart');
  try {
    await fetchJSON(BASE + '/api/cart/add', { method: 'POST', body: JSON.stringify({ product_id: productId, quantity: 1 }) });
    finishLoading('Added to cart');
    toast('Added to cart!', 'success');
    loadCartCount();
  } catch (e) {
    finishLoading();
    toast(e.message, 'error');
  }
}

async function doLogout() {
  await fetchJSON(BASE + '/api/auth/logout', { method: 'POST' });
  window.location.href = BASE + '/';
}

/* Ask before logging out â€” an accidental click on the dropdown link
   should never end the session. Uses the site's modal styling. */
function confirmLogout() {
  closeLogoutConfirm();
  var overlay = document.createElement('div');
  overlay.id = 'logout-confirm';
  overlay.className = 'modal-overlay';
  overlay.innerHTML =
    '<div class="modal" style="max-width:400px;text-align:center;">' +
    '<h2>Log out?</h2>' +
    '<p class="text-muted" style="margin-bottom:24px;">You will need to sign in again to place orders or access your dashboard.</p>' +
    '<div class="modal-actions" style="justify-content:center;">' +
    '<button type="button" class="btn btn-outline" id="logout-cancel">Cancel</button>' +
    '<button type="button" class="btn btn-danger" id="logout-ok">Logout</button>' +
    '</div></div>';
  document.body.appendChild(overlay);
  function onKey(e) { if (e.key === 'Escape') close(); }
  function close() {
    document.removeEventListener('keydown', onKey);
    overlay.remove();
  }
  document.addEventListener('keydown', onKey);
  overlay.querySelector('#logout-cancel').addEventListener('click', close);
  overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });
  overlay.querySelector('#logout-ok').addEventListener('click', function() { close(); doLogout(); });
}

function closeLogoutConfirm() {
  var ov = document.getElementById('logout-confirm');
  if (ov) ov.remove();
}

function setAuthLinks(u, opts) {
  opts = opts || {};
  const el = document.getElementById('auth-links');
  // Mobile bottom-nav account slot follows the same session state.
  const bottomAccount = document.getElementById('bottom-account');
  const bottomLabel = document.getElementById('bottom-account-label');
  if (bottomAccount && bottomLabel) {
    if (u) {
      if (u.role === 'admin' && !opts.hideAdmin) {
        bottomAccount.href = BASE + '/admin';
        bottomLabel.textContent = 'Admin';
      } else {
        bottomAccount.href = BASE + '/profile';
        bottomLabel.textContent = 'Account';
      }
    } else {
      bottomAccount.href = BASE + '/login';
      bottomLabel.textContent = 'Account';
    }
  }
  if (!el) return;
  if (!u) {
    el.innerHTML = '<a href="' + BASE + '/login">Login</a> <a href="' + BASE + '/register" class="btn btn-primary btn-sm">Register</a>';
    return;
  }
  // Greet with the first name only â€” full names make the header button long
  var safeName = String(u.name || '');
  var shortName = safeName.trim().split(/\s+/)[0] || safeName;
  var adminItem = (u.role === 'admin' && !opts.hideAdmin) ? ' <a href="' + BASE + '/admin">Admin Dashboard</a>' : '';
  el.innerHTML = '<div class="user-dropdown"><span class="user-name btn btn-primary btn-sm">' + escapeHtml(shortName) + '</span><div class="user-dropdown-menu"><span class="dropdown-title">' + escapeHtml(shortName) + '</span>' + adminItem + '<a href="' + BASE + '/profile">My Profile</a><a href="' + BASE + '/orders">My Orders</a><a href="' + BASE + '/track-order">Track a Guest Order</a><a href="#" class="logout-link" onclick="confirmLogout()">Logout</a></div></div>';
  var dd = el.querySelector('.user-dropdown');
  var navLinks = document.querySelector('.nav-links');
  var hamburger = document.querySelector('.hamburger');
  var mainNav = document.querySelector('.main-nav');
  dd.querySelector('.user-name').addEventListener('click', function(e) {
    e.stopPropagation();
    var opening = !dd.classList.contains('open');
    dd.classList.toggle('open', opening);
    if (window.matchMedia('(max-width: 768px)').matches && navLinks && navLinks.classList.contains('open')) {
      if (opening) {
        navLinks.classList.add('account-menu-open');
        if (mainNav) mainNav.classList.add('account-menu-open');
        if (hamburger) hamburger.classList.remove('active');
      } else {
        navLinks.classList.remove('open', 'account-menu-open');
        if (mainNav) mainNav.classList.remove('account-menu-open');
        if (hamburger) hamburger.classList.remove('active');
      }
    }
  });
  document.addEventListener('click', function() {
    dd.classList.remove('open');
    if (navLinks && navLinks.classList.contains('account-menu-open')) {
      navLinks.classList.remove('open', 'account-menu-open');
      if (mainNav) mainNav.classList.remove('account-menu-open');
      if (hamburger) hamburger.classList.remove('active');
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  initThemeToggle();
  initAnnouncement();
  initSearchSuggest(document.getElementById('nav-search-input'), document.getElementById('search-suggest'));
  initSearchSuggest(document.getElementById('modal-search-input'), document.getElementById('modal-search-suggest'));
  initBottomNav();
  initReveal();
  initImageFade();
  updateWishlistCount();
  initBottomNavActive();


  // New product grids (home sections, filters) reveal + fade in as they arrive.
  if ('MutationObserver' in window) {
    var rescan = debounce2(function() {
      if (window.__revealScan) window.__revealScan();
      if (window.__imageFadeScan) window.__imageFadeScan();
    }, 200);
    new MutationObserver(rescan).observe(document.body, { childList: true, subtree: true });
  }

  var btn = document.querySelector('.scroll-top');
  if (btn) {
    window.addEventListener('scroll', function() { btn.classList.toggle('visible', window.scrollY > 400); });
    btn.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  }
  var ham = document.querySelector('.hamburger');
  var navLinks = document.querySelector('.nav-links');
  if (ham && navLinks) {
    ham.addEventListener('click', function() {
      ham.classList.toggle('active');
      navLinks.classList.toggle('open');
      navLinks.classList.remove('account-menu-open');
      var mainNav = document.querySelector('.main-nav');
      if (mainNav) mainNav.classList.remove('account-menu-open');
    });
    navLinks.querySelectorAll('a').forEach(function(a) { a.addEventListener('click', function() {
      ham.classList.remove('active');
      navLinks.classList.remove('open', 'account-menu-open');
      var mainNav = document.querySelector('.main-nav');
      if (mainNav) mainNav.classList.remove('account-menu-open');
    }); });
  }
});

function debounce2(fn, ms) {
  var t;
  return function() {
    clearTimeout(t);
    var args = arguments, self = this;
    t = setTimeout(function() { fn.apply(self, args); }, ms);
  };
}

/* --- Announcement bar: rotating messages + session dismiss --- */
function initAnnouncement() {
  var bar = document.getElementById('announcement-bar');
  if (!bar) return;
  try {
    if (sessionStorage.getItem('handel-luxe-announce-off')) { bar.hidden = true; return; }
  } catch (e) {}
  var msgs = bar.querySelectorAll('[data-announcement]');
  if (msgs.length > 1) {
    var i = 0;
    setInterval(function() {
      msgs[i].classList.remove('is-active');
      i = (i + 1) % msgs.length;
      msgs[i].classList.add('is-active');
    }, 4500);
  }
  var dismiss = document.getElementById('announcement-dismiss');
  if (dismiss) {
    dismiss.addEventListener('click', function() {
      bar.hidden = true;
      try { sessionStorage.setItem('handel-luxe-announce-off', '1'); } catch (e) {}
    });
  }
}

/* --- Live search suggestions (desktop nav + mobile modal) --- */
function initSearchSuggest(input, panel) {
  if (!input || !panel) return;
  var idx = -1;
  var req = null;
  function rows() { return panel.querySelectorAll('.suggest-row'); }
  function close() { panel.hidden = true; input.setAttribute('aria-expanded', 'false'); idx = -1; }
  function open() { panel.hidden = false; input.setAttribute('aria-expanded', 'true'); }
  async function run() {
    var q = input.value.trim();
    if (q.length < 2) { close(); return; }
    panel.innerHTML = '<div class="suggest-state"><div class="spinner"></div><p>Searching&hellip;</p></div>';
    open();
    try {
      if (req) req.abort();
      req = new AbortController();
      var data = await fetchJSON(API + 'products?search=' + encodeURIComponent(q), { signal: req.signal });
      var items = (data.products || []).slice(0, 5);
      if (!items.length) {
        panel.innerHTML = '<div class="suggest-state">No fragrances match &ldquo;' + escapeHtml(q) + '&rdquo;.' +
          '<a class="btn btn-primary suggest-request" href="' + BASE + '/products?search=' + encodeURIComponent(q) + '&amp;request=1">Request a Fragrance</a></div>';
        return;
      }
      var placeholder = BASE + '/images/placeholder.png';
      panel.innerHTML = items.map(function(p) {
        return '<a class="suggest-row" role="option" href="' + BASE + '/product/' + p.id + '">' +
          '<img class="suggest-thumb" src="' + escapeHtml(p.image || placeholder) + '" alt="" loading="lazy">' +
          '<span class="suggest-meta"><span class="suggest-name">' + escapeHtml(p.name) + '</span>' +
          '<span class="suggest-price">' + formatPrice(p.price) + '</span></span></a>';
      }).join('');
    } catch (e) {
      if (e.name === 'AbortError') return;
      panel.innerHTML = '<div class="suggest-state">Search is temporarily unavailable.</div>';
    }
  }
  input.addEventListener('input', debounce2(run, 260));
  input.addEventListener('focus', function() { if (input.value.trim().length >= 2) run(); });
  input.addEventListener('keydown', function(e) {
    var list = rows();
    if (e.key === 'Escape') { close(); return; }
    if (!list.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      idx += e.key === 'ArrowDown' ? 1 : -1;
      if (idx < 0) idx = list.length - 1;
      if (idx >= list.length) idx = 0;
      list.forEach(function(r, i) { r.classList.toggle('suggest-active', i === idx); });
      if (list[idx].scrollIntoView) list[idx].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter' && idx >= 0) {
      e.preventDefault();
      window.location.assign(list[idx].href);
    }
  });
  document.addEventListener('click', function(e) {
    if (!panel.contains(e.target) && e.target !== input) close();
  });
  if (input.form) input.form.addEventListener('submit', function() { close(); });
}

/* --- Mobile search modal + bottom-nav wiring --- */
function initBottomNav() {
  var modal = document.getElementById('search-modal');
  var btn = document.getElementById('bottom-search-btn');
  var input = document.getElementById('modal-search-input');
  var closeBtn = document.getElementById('search-modal-close');
  if (btn && modal) {
    btn.addEventListener('click', function() {
      modal.hidden = false;
      if (input) input.focus();
    });
  }
  function closeModal() { if (modal) modal.hidden = true; }
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });
}

function initBottomNavActive() {
  var path = window.location.pathname.replace(/\/+$/, '') || '/';
  var appPath = new URL(BASE || '/', window.location.origin).pathname.replace(/\/+$/, '');
  document.querySelectorAll('[data-bottom-nav]').forEach(function(item) {
    var key = item.getAttribute('data-bottom-nav');
    var active = false;
    if (key === 'home') active = path === (appPath || '/');
    else if (key === 'cart') active = path === appPath + '/cart';
    else if (key === 'wishlist') active = path === appPath + '/wishlist';
    item.classList.toggle('is-active', active);
  });
}

/* --- Reveal on scroll: JS opts elements in; nothing is hidden without JS --- */
function initReveal() {
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var io = null;
  if ('IntersectionObserver' in window) {
    io = new IntersectionObserver(function(entries) {
      entries.forEach(function(en) {
        if (en.isIntersecting) {
          en.target.classList.add('is-visible');
          io.unobserve(en.target);
        }
      });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.05 });
  }
  window.__revealScan = function() {
    document.querySelectorAll('.section-title, .section-sub, .product-card, .category-card, .case-card, .trust-item, .faq-item, .newsletter-band, .response-promise, .order-card, .pd-grid, .footer-col, .hero-text')
      .forEach(function(el) {
        if (el.classList.contains('reveal-item') || el.classList.contains('is-visible')) return;
        el.classList.add('reveal-item');
        if (io) io.observe(el); else el.classList.add('is-visible');
      });
  };
  window.__revealScan();
}

/* --- Product image fade-in once decoded --- */
function initImageFade() {
  function mark(img) {
    if (img.hasAttribute('data-fade')) {
      if (img.complete && img.naturalWidth) img.classList.add('img-loaded');
      return;
    }
    if (!img.closest('.img-wrap, .pd-image, .category-card, .case-img')) return;
    img.setAttribute('data-fade', '');
    if (img.complete && img.naturalWidth) {
      img.classList.add('img-loaded');
    } else {
      img.addEventListener('load', function() { img.classList.add('img-loaded'); }, { once: true });
      img.addEventListener('error', function() { img.classList.add('img-loaded'); }, { once: true });
    }
  }
  window.__imageFadeScan = function() {
    document.querySelectorAll('img').forEach(mark);
  };
  window.__imageFadeScan();
}

function showLoading(el) {
  if (typeof el === 'string') el = document.querySelector(el);
  if (el) el.innerHTML = '<div class="loading-placeholder"><div class="spinner"></div><p>Loading...</p></div>';
}
