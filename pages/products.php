<?php $pageTitle = 'All Fragrances';
$metaDescription = 'Browse 61 authentic perfumes, body sprays and gift sets from Handel Luxe GH. Filter by category, sort by price, and order with nationwide delivery across Ghana.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Fragrances']]; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
  <div class="section">
    <h2 class="section-title">All Fragrances</h2>
    <div class="filters-bar">
      <select id="category-filter" class="filter-select" aria-label="Filter by category">
        <option value="">All Categories</option>
      </select>
      <select id="sort-filter" class="filter-select" aria-label="Sort fragrances">
        <option value="newest">Newest</option>
        <option value="price_asc">Price: Low to High</option>
        <option value="price_desc">Price: High to Low</option>
      </select>
      <input type="search" id="search-filter" class="filter-input" placeholder="Search fragrances..." aria-label="Search fragrances">
    </div>
    <p class="results-count" id="results-count" role="status" aria-live="polite">Loading the collection…</p>
    <div class="product-grid" id="products" aria-label="Fragrances" aria-busy="true"><div class="loading-placeholder"><div class="spinner"></div><p>Loading fragrances...</p></div></div>
    <div id="pagination" class="pagination-bar"></div>
    <section class="fragrance-request" aria-labelledby="fragrance-request-heading">
      <h2 id="fragrance-request-heading">Can&rsquo;t find your fragrance?</h2>
      <p>Tell us what you are looking for and we will try to get it for you.</p>
      <button type="button" class="btn btn-primary" data-open-fragrance-request>Request a Fragrance</button>
    </section>
  </div>
</div>
<div class="modal-overlay" id="fragrance-request-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="fragrance-request-modal-title">
    <h2 id="fragrance-request-modal-title">Request a Fragrance</h2>
    <p class="text-muted">Tell us the fragrance you are looking for. We will open WhatsApp so you can send the completed request to our team.</p>
    <form id="fragrance-request-form" novalidate>
      <div class="form-group">
        <label for="fr-title">Perfume Title <span class="req" aria-hidden="true">*</span></label>
        <input type="text" id="fr-title" name="fragrance_title" maxlength="150" autocomplete="off" required>
        <span class="field-error" data-error-for="fragrance_title" role="alert"></span>
      </div>
      <div class="form-group">
        <label for="fr-brand">Brand</label>
        <input type="text" id="fr-brand" name="brand" maxlength="100" autocomplete="off">
        <span class="field-error" data-error-for="brand" role="alert"></span>
      </div>
      <div class="form-group">
        <label for="fr-budget">Target Budget / Size</label>
        <input type="text" id="fr-budget" name="budget_size" maxlength="100" placeholder="e.g., GH&#8373;300 - 100ml" autocomplete="off">
        <span class="field-error" data-error-for="budget_size" role="alert"></span>
      </div>
      <div class="form-group">
        <label for="fr-name">Client Name <span class="req" aria-hidden="true">*</span></label>
        <input type="text" id="fr-name" name="customer_name" maxlength="100" autocomplete="name" required>
        <span class="field-error" data-error-for="customer_name" role="alert"></span>
      </div>
      <div class="form-message" id="fr-form-message" role="status" aria-live="polite"></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" id="fr-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary" id="fr-submit">Request a Fragrance</button>
      </div>
    </form>
    <div id="fr-status" hidden>
      <p class="fr-notice">Opening WhatsApp with your request. Press send to submit it.</p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  let currentPage = 1;
  let requestId = 0;
  let activeRequest = null;
  let hasLoaded = false;
  async function loadProducts(page, scrollToResults) {
    if (page) currentPage = page;
    const cat = document.getElementById('category-filter').value;
    const sort = document.getElementById('sort-filter').value;
    const search = document.getElementById('search-filter').value;
    const productGrid = document.getElementById('products');
    const resultsCount = document.getElementById('results-count');
    const thisRequest = ++requestId;
    if (activeRequest) activeRequest.abort();
    activeRequest = new AbortController();
    let url = B + '/api/products?page=' + currentPage + '&';
    if (cat) url += 'category=' + encodeURIComponent(cat) + '&';
    if (sort) url += 'sort=' + encodeURIComponent(sort) + '&';
    if (search) url += 'search=' + encodeURIComponent(search) + '&';
    if (!hasLoaded) showProductSkeletons(productGrid, 8);
    else {
      productGrid.setAttribute('aria-busy', 'true');
      productGrid.classList.add('is-loading');
    }
    resultsCount.textContent = search ? 'Searching fragrances…' : 'Updating the collection…';
    try {
      const data = await fetchJSON(url, { signal: activeRequest.signal });
      if (thisRequest !== requestId) return;
      const { products, totalPages, page: p, total } = data;
      productGrid.innerHTML = products.length ? products.map(productCard).join('') : '<div class="empty-state"><h3>We couldn\u2019t find that fragrance.</h3><button type="button" class="btn btn-primary" data-open-fragrance-request data-prefill-search>Request a Fragrance</button></div>';
      resultsCount.textContent = total + (total === 1 ? ' fragrance' : ' fragrances');
      const pag = document.getElementById('pagination');
      if (totalPages <= 1) pag.innerHTML = '';
      else {
        let btns = '<button onclick="loadProducts(' + (p - 1) + ', true)" ' + (p <= 1 ? 'disabled' : '') + ' class="btn btn-outline btn-sm" aria-label="Previous page">Previous</button>';
        for (let i = 1; i <= totalPages; i++) {
          const active = i === p ? 'btn-primary' : 'btn-outline';
          btns += '<button onclick="loadProducts(' + i + ', true)" class="btn ' + active + ' btn-sm"' + (i === p ? ' aria-current="page"' : '') + '>' + i + '</button>';
        }
        btns += '<button onclick="loadProducts(' + (p + 1) + ', true)" ' + (p >= totalPages ? 'disabled' : '') + ' class="btn btn-outline btn-sm" aria-label="Next page">Next</button>';
        pag.innerHTML = btns;
      }
      hasLoaded = true;
      if (scrollToResults) productGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
      if (e.name === 'AbortError' || thisRequest !== requestId) return;
      productGrid.innerHTML = '<div class="load-error" role="alert"><p>We couldn’t load the fragrances.</p><button type="button" class="btn btn-outline btn-sm load-retry">Try again</button></div>';
      productGrid.querySelector('.load-retry').addEventListener('click', function() { loadProducts(); });
      resultsCount.textContent = 'The collection could not be loaded.';
    } finally {
      if (thisRequest === requestId) {
        productGrid.setAttribute('aria-busy', 'false');
        productGrid.classList.remove('is-loading');
      }
    }
  }
  (async function() {
    const params = new URLSearchParams(window.location.search);
    const searchParam = params.get('search') || '';
    if (searchParam) document.getElementById('search-filter').value = searchParam;
    const catParam = params.get('category') || '';
    try {
      const cats = await fetchJSON(B + '/api/products/categories');
      document.getElementById('category-filter').innerHTML += cats.map(c => '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>').join('');
      if (catParam) document.getElementById('category-filter').value = catParam;
    } catch (e) {
      toast('Category filters are temporarily unavailable.', 'error');
    }
    loadProducts();
    loadCartCount();
    try { const u = await fetchJSON(B + '/api/auth/me'); setAuthLinks(u); } catch(e) { setAuthLinks(null); }
  })();
  document.getElementById('category-filter').onchange = () => loadProducts(1);
  document.getElementById('sort-filter').onchange = () => loadProducts(1);
  document.getElementById('search-filter').oninput = debounce(() => loadProducts(1), 300);
  function debounce(fn, ms) { let t; return function() { clearTimeout(t); t = setTimeout(fn, ms); }; }
  // Pagination buttons use inline onclick, which needs a global reference
  window.loadProducts = loadProducts;
})();
</script>
<script>
/* "Request a Fragrance": validate server-side, then open a prefilled WhatsApp
   deep link (wa.me/<number>?text=<message>). Nothing is saved; the chat is the
   only record. The link is built in PHP so the number never lives in markup. */
(function () {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  const overlay = document.getElementById('fragrance-request-modal');
  const form = document.getElementById('fragrance-request-form');
  const statusBox = document.getElementById('fr-status');
  const formMessage = document.getElementById('fr-form-message');
  const submitBtn = document.getElementById('fr-submit');
  let lastFocused = null;

  if (!overlay || !form) return;

  function clearErrors() {
    form.querySelectorAll('.field-error').forEach(function (el) { el.textContent = ''; });
    form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
    formMessage.textContent = '';
    formMessage.className = 'form-message';
  }

  function showErrors(errors) {
    let firstInvalid = null;
    Object.keys(errors).forEach(function (key) {
      const span = form.querySelector('[data-error-for="' + key + '"]');
      if (span) span.textContent = errors[key];
      const field = form.elements[key];
      if (field) {
        field.setAttribute('aria-invalid', 'true');
        if (!firstInvalid) firstInvalid = field;
      }
    });
    if (firstInvalid) firstInvalid.focus();
  }

  function resetModal() {
    form.reset();
    clearErrors();
    form.hidden = false;
    statusBox.hidden = true;
    submitBtn.disabled = false;
    submitBtn.textContent = 'Request a Fragrance';
  }

  function openModal(prefill) {
    resetModal();
    const first = document.getElementById('fr-title');
    if (first && prefill) first.value = prefill;
    lastFocused = document.activeElement;
    overlay.hidden = false;
    if (first) first.focus();
  }

  function closeModal() {
    overlay.hidden = true;
    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
  }

  // Delegated so the button the product loader renders into the empty state
  // opens the same modal as the permanent block below the grid.
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest && e.target.closest('[data-open-fragrance-request]');
    if (!trigger) return;
    e.preventDefault();
    let prefill = '';
    if (trigger.hasAttribute('data-prefill-search')) {
      const input = document.getElementById('search-filter');
      // Carry the search text into the form so the customer doesn't retype it.
      prefill = input ? input.value.trim().slice(0, 150) : '';
    }
    openModal(prefill);
  });

  document.getElementById('fr-cancel').addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !overlay.hidden) closeModal();
  });

  function showStatusAndRedirect(url) {
    form.hidden = true;
    statusBox.hidden = false;
    // A brief pause lets the "press send" note register before the tab changes.
    window.setTimeout(function () { window.location.href = url; }, 1200);
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    clearErrors();
    submitBtn.disabled = true;
    submitBtn.textContent = 'Preparing\u2026';
    const payload = {
      fragrance_title: form.elements['fragrance_title'].value,
      brand: form.elements['brand'].value,
      budget_size: form.elements['budget_size'].value,
      customer_name: form.elements['customer_name'].value
    };
    try {
      const res = await fetch(B + '/api/request-fragrance', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'x-csrf-token': getCSRF() },
        body: JSON.stringify(payload)
      });
      const data = await res.json().catch(function () { return {}; });
      if (!res.ok || !data.whatsapp_url) {
        if (data.errors) showErrors(data.errors);
        formMessage.textContent = data.error || 'Something went wrong. Please try again.';
        formMessage.className = 'form-message error';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Request a Fragrance';
        return;
      }
      showStatusAndRedirect(data.whatsapp_url);
    } catch (err) {
      formMessage.textContent = 'We could not send your request. Please try again.';
      formMessage.className = 'form-message error';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Request a Fragrance';
    }
  });

  // A search suggestion that found nothing links here with ?request=1. Open the
  // form straight away with the searched term already filled in.
  const params = new URLSearchParams(window.location.search);
  if (params.get('request') === '1') {
    const searchInput = document.getElementById('search-filter');
    openModal(searchInput ? searchInput.value.trim().slice(0, 150) : '');
  }
})();
</script>
