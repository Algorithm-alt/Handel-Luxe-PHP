<?php $pageTitle = 'Admin Dashboard';
$metaDescription = 'Handel Luxe GH administration dashboard.';
$noIndex = true; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="admin-layout">
  <nav class="admin-sidebar" id="admin-sidebar" aria-label="Admin dashboard">
    <a href="#" class="active" data-tab="dashboard">Dashboard</a>
    <a href="#" data-tab="analytics">Analytics</a>
    <a href="#" data-tab="products">Products</a>
    <a href="#" data-tab="categories">Categories</a>
    <a href="#" data-tab="orders">Orders</a>
    <a href="#" data-tab="users">Users</a>
    <a href="#" data-tab="messages">Messages <span id="msg-badge" class="badge"></span></a>
    <a href="#" data-tab="delivery">Delivery Zones</a>
    <a href="#" data-tab="newsletter">Newsletter</a>
    <a href="#" data-tab="export">Export Data</a>
    <a href="#" data-tab="profile">My Profile</a>
    <a href="<?= base_url('/') ?>" class="admin-back">Back to Store</a>
  </nav>
  <main class="admin-content" id="admin-content">
    <div class="loading-placeholder"><div class="spinner"></div><p>Loading admin panel...</p></div>
  </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  let currentTab = 'dashboard';
  async function checkAdmin() {
    try { const u = await fetchJSON(B + '/api/auth/me'); if (u.role !== 'admin') throw new Error('Not admin'); setAuthLinks(u, { hideAdmin: true, prefix: 'Admin: ' }); pollNotifications(); }
    catch(e) { window.location.href = B + '/login'; }
  }
  async function pollNotifications() {
    const updateBadge = async () => {
      try {
        const n = await fetchJSON(B + '/api/admin/notifications');
        const badge = document.getElementById('msg-badge');
        if (badge) { if (n.unread_messages > 0) { badge.classList.add('show'); badge.textContent = n.unread_messages; } else badge.classList.remove('show'); }
        const total = n.unread_messages + n.pending_payments + n.pending_orders;
        document.title = total > 0 ? '(' + total + ') Admin Panel - Handel Luxe GH' : 'Admin Panel - Handel Luxe GH';
      } catch (e) {}
    };
    // Exposed so message actions (open/mark unread/delete) can refresh the
    // badge instantly instead of waiting for the next 30s poll.
    window.refreshAdminBadge = updateBadge;
    updateBadge();
    setInterval(updateBadge, 30000);
  }
  function showTab(tab) {
    currentTab = tab;
    const links = document.querySelectorAll('.admin-sidebar a[data-tab]');
    const tabs = ['dashboard','analytics','products','categories','orders','users','messages','delivery','newsletter','export','profile'];
    const idx = tabs.indexOf(tab);
    links.forEach(a => a.classList.remove('active'));
    if (idx >= 0 && links[idx]) links[idx].classList.add('active');
    // On mobile the sections are a horizontal strip; keep the active tab visible
    if (idx >= 0 && links[idx] && links[idx].scrollIntoView) links[idx].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    showLoading('#admin-content');
    const guard = (fn) => fn().catch(e => { document.getElementById('admin-content').innerHTML = '<h2></h2><p class="error-msg">Failed to load: ' + escapeHtml(e.message) + '</p><button class="btn btn-outline" onclick="showTab(\'' + tab + '\')">Retry</button>'; });
    if (tab === 'dashboard') guard(loadDashboard);
    else if (tab === 'analytics') guard(loadAnalytics);
    else if (tab === 'products') guard(loadAdminProducts);
    else if (tab === 'categories') guard(loadCategories);
    else if (tab === 'orders') guard(loadAdminOrders);
    else if (tab === 'users') guard(loadUsers);
    else if (tab === 'messages') guard(loadMessages);
    else if (tab === 'delivery') guard(loadDeliveryZones);
    else if (tab === 'newsletter') showNewsletter();
    else if (tab === 'export') showExport();
    else if (tab === 'profile') guard(loadProfile);
  }
  async function loadDashboard() {
    const s = await fetchJSON(B + '/api/admin/stats');
    document.getElementById('admin-content').innerHTML = '<h2>Dashboard</h2>' +
      (s.low_stock > 0 ? '<div class="alert alert-warning">' + s.low_stock + ' product(s) are low on stock (5 or less remaining). <a href="#" class="text-link" data-tab="products">Review now</a></div>' : '') +
      '<div class="stat-cards">' +
      '<div class="stat-card"><div class="label">Total Users</div><div class="value">' + s.users + '</div></div>' +
      '<div class="stat-card"><div class="label">Total Fragrances</div><div class="value">' + s.products + '</div></div>' +
      '<div class="stat-card"><div class="label">Total Stock</div><div class="value">' + s.total_stock + '</div></div>' +
      '<div class="stat-card"><div class="label">Total Orders</div><div class="value">' + s.orders + '</div></div>' +
      '<div class="stat-card"><div class="label">Total Revenue</div><div class="value">' + formatPrice(s.revenue) + '</div></div>' +
      '<div class="stat-card"><div class="label">Orders Today</div><div class="value">' + s.orders_today + '</div></div>' +
      '<div class="stat-card"><div class="label">Revenue Today</div><div class="value">' + formatPrice(s.revenue_today) + '</div></div>' +
      (s.pending_payments > 0 ? '<div class="stat-card stat-card-warning"><div class="label">Awaiting Payment</div><div class="value">' + s.pending_payments + '</div></div>' : '') +
      '</div>' +
      (s.recent_orders && s.recent_orders.length ? '<h3 class="section-subtitle">Recent Orders</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead><tbody>' +
      s.recent_orders.map(o => '<tr><td>#' + o.id + '</td><td>' + escapeHtml(o.customer_name) + '</td><td>' + formatPrice(o.total) + '</td><td>' + (o.payment_method === 'delivery' ? 'Delivery' : 'Paystack') + '</td><td><span class="status status-' + o.status + '">' + o.status + '</span></td><td>' + new Date(o.created_at).toLocaleString() + '</td></tr>').join('') +
      '</tbody></table></div>' : '');
  }
  async function loadAdminProducts() {
    const products = await fetchJSON(B + '/api/admin/all-products');
    window._adminProducts = products;
    renderAdminProducts(products);
  }
  /* One row renderer for both the initial paint and the local search
     filter — the two used to carry duplicate markup that could drift. */
  function productRow(p) {
    const cover = p.image || (B + '/images/placeholder.png');
    const count = p.image_count || 1;
    return '<tr><td>' + p.id + '</td>' +
      '<td><div class="cell-product"><img src="' + escapeHtml(cover) + '" alt="" class="cell-thumb" loading="lazy"><span>' + escapeHtml(p.name) + '</span></div></td>' +
      '<td>' + escapeHtml(p.category_name || '-') + '</td>' +
      '<td>' + formatPrice(p.price) + '</td>' +
      '<td><div class="cell-stock"><input type="number" id="stock-' + p.id + '" value="' + p.stock + '" min="0" class="input-sm"> <button class="btn btn-primary btn-sm" onclick="updateStock(' + p.id + ')">Save</button></div></td>' +
      '<td><span class="img-count' + (count < 1 ? ' img-count-warn' : '') + '">' + count + '</span></td>' +
      '<td>' + (p.featured ? 'Yes' : 'No') + '</td>' +
      '<td><div class="cell-actions"><button class="btn btn-primary btn-sm" onclick="showEditProduct(' + p.id + ')">Edit</button> <button class="btn btn-danger btn-sm" onclick="deleteProduct(' + p.id + ')">Delete</button></div></td></tr>';
  }
  function renderAdminProducts(products) {
    document.getElementById('admin-content').innerHTML = '<h2>Manage Fragrances</h2>' +
      '<div class="admin-toolbar"><input type="text" id="admin-product-search" placeholder="Search fragrances..." oninput="filterAdminProducts()">' +
      '<button type="button" class="btn btn-primary" onclick="showAddProduct()">+ Add Product</button></div>' +
      '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Images</th><th>Featured</th><th>Actions</th></tr></thead>' +
      '<tbody id="admin-products-tbody">' + products.map(productRow).join('') + '</tbody></table></div>';
  }
  function filterAdminProducts() {
    const q = document.getElementById('admin-product-search').value.toLowerCase();
    const filtered = window._adminProducts.filter(p => p.name.toLowerCase().includes(q) || (p.category_name || '').toLowerCase().includes(q));
    document.getElementById('admin-products-tbody').innerHTML = filtered.map(productRow).join('');
  }
  async function updateStock(id) {
    const stock = parseInt(document.getElementById('stock-' + id).value, 10);
    if (isNaN(stock) || stock < 0) { toast('Enter a valid stock number', 'error'); return; }
    try { await fetchJSON(B + '/api/admin/products/' + id + '/stock', { method: 'PUT', body: JSON.stringify({ stock }) }); toast('Stock updated!', 'success'); } catch(e) { toast(e.message, 'error'); }
  }
  async function deleteProduct(id) {
    if (!confirm('Delete this fragrance?')) return;
    await fetchJSON(B + '/api/admin/products/' + id, { method: 'DELETE' });
    toast('Fragrance deleted', 'success');
    loadAdminProducts();
  }
  /* ---- shared product-gallery editor ----
     Used by both the Add and Edit product forms. Tracks three sets:
     'existing' (already saved on the server), 'pending' (chosen but not
     yet uploaded) and 'removed' (saved images marked for deletion).
     The server re-validates the 1-6 rule either way; the counters here
     exist so the admin sees the limit before uploading. */
  const MAX_PRODUCT_IMAGES = 6;

  // Inline onclick handlers cannot close over the editor instance, and mount
  // ids contain characters that are not valid in identifiers — so editors
  // register here and the handlers look them up by mount id.
  window.__galleries = {};
  window.galleryAction = function(mountId, action, arg) {
    const g = window.__galleries[mountId];
    if (!g) return;
    if (action === 'setCover') g.setCover(arg);
    else if (action === 'toggleRemove') g.toggleRemove(arg);
    else if (action === 'dropPending') g.dropPending(arg);
  };

  function createGalleryEditor(mountId, fileInputId, opts) {
    opts = opts || {};
    const mount = document.getElementById(mountId);
    const input = document.getElementById(fileInputId);
    const state = { existing: [], pending: [], removed: [] };

    function activeCount() {
      return state.existing.length - state.removed.length + state.pending.length;
    }
    function firstLiveId() {
      for (const img of state.existing) {
        if (state.removed.indexOf(img.id) === -1) return img.id;
      }
      return -1;
    }
    function render() {
      const total = activeCount();
      const coverId = firstLiveId();
      let html = '<div class="gallery-counter">' + total + ' of ' + MAX_PRODUCT_IMAGES + ' images' +
        (total < 1 ? ' <span class="gallery-warn">at least 1 required</span>' : '') + '</div>';
      html += '<div class="gallery-grid">';
      state.existing.forEach((img) => {
        const gone = state.removed.indexOf(img.id) !== -1;
        const isCover = !gone && state.removed.length === 0 && img.id === coverId;
        html += '<figure class="gallery-thumb' + (gone ? ' marked-removed' : '') + '">' +
          '<img src="' + escapeHtml(img.image) + '" alt="Product image">' +
          (isCover ? '<figcaption class="cover-badge">Cover</figcaption>' : '') +
          '<div class="gallery-thumb-actions">' +
          (img.id > 0 && !isCover && !gone ? '<button type="button" class="btn btn-outline btn-xs" onclick="galleryAction(\'' + mountId + '\',\'setCover\',' + img.id + ')">Set cover</button>' : '') +
          '<button type="button" class="btn ' + (gone ? 'btn-outline' : 'btn-danger') + ' btn-xs" onclick="galleryAction(\'' + mountId + '\',\'toggleRemove\',' + img.id + ')">' + (gone ? 'Undo' : 'Remove') + '</button>' +
          '</div></figure>';
      });
      state.pending.forEach((f, i) => {
        html += '<figure class="gallery-thumb pending">' +
          '<img src="' + URL.createObjectURL(f) + '" alt="New image preview">' +
          '<figcaption class="pending-badge">New</figcaption>' +
          '<div class="gallery-thumb-actions">' +
          '<button type="button" class="btn btn-danger btn-xs" onclick="galleryAction(\'' + mountId + '\',\'dropPending\',' + i + ')">Remove</button>' +
          '</div></figure>';
      });
      html += '</div>';
      html += '<p class="text-muted text-small gallery-note">JPG, PNG, GIF or WebP up to 5MB each. Images are resized and compressed automatically. The first image is the cover shown in listings.</p>';
      mount.innerHTML = html;
    }

    input.addEventListener('change', () => {
      const chosen = Array.from(input.files || []);
      if (!chosen.length) return;
      const room = MAX_PRODUCT_IMAGES - activeCount();
      if (chosen.length > room) {
        toast('Only ' + Math.max(0, room) + ' more image(s) can be added (max ' + MAX_PRODUCT_IMAGES + ').', 'error');
      }
      state.pending = state.pending.concat(chosen.slice(0, Math.max(0, room)));
      input.value = ''; // let the same file be re-picked after a remove
      render();
    });

    const api = {
      setExisting(images) { state.existing = images.slice(); render(); },
      setCover(imageId) { opts.onSetCover && opts.onSetCover(imageId); },
      toggleRemove(imageId) {
        const i = state.removed.indexOf(imageId);
        if (i === -1) state.removed.push(imageId); else state.removed.splice(i, 1);
        render();
      },
      dropPending(index) { state.pending.splice(index, 1); render(); },
      appendTo(fd) {
        state.pending.forEach(f => fd.append('images[]', f));
        state.removed.forEach(id => fd.append('remove_images[]', id));
      },
      count() { return activeCount(); },
      hasPending() { return state.pending.length > 0 || state.removed.length > 0; },
      sortOrderForNew() {
        return state.existing.reduce((m, i) => Math.max(m, i.sort_order || 0), -1) + 1;
      }
    };
    window.__galleries[mountId] = api;
    render();
    return api;
  }

  async function showEditProduct(id) {
    try {
    showLoading('#admin-content');
    const [products, cats] = await Promise.all([fetchJSON(B + '/api/admin/all-products'), fetchJSON(B + '/api/products/categories')]);
    const p = products.find(x => String(x.id) === String(id));
    if (!p) { toast('Product not found', 'error'); return; }
    document.getElementById('admin-content').innerHTML = '<h2>Edit Fragrance</h2><div class="admin-form-card"><form id="edit-product-form" enctype="multipart/form-data">' +
      '<div class="form-group"><label>Name</label><input type="text" id="ep-name" value="' + escapeHtml(p.name) + '" required></div>' +
      '<div class="form-group"><label>Description</label><textarea id="ep-desc" rows="3">' + escapeHtml(p.description || '') + '</textarea></div>' +
      '<div class="form-group"><label>Price (GH&#8373;)</label><input type="number" id="ep-price" step="0.01" value="' + p.price + '" required></div>' +
      '<div class="form-group"><label>Category</label><select id="ep-cat"><option value="">Select category</option>' + cats.map(c => '<option value="' + c.id + '" ' + (p.category_id == c.id ? 'selected' : '') + '>' + escapeHtml(c.name) + '</option>').join('') + '</select></div>' +
      '<div class="form-group"><label>Stock</label><input type="number" id="ep-stock" value="' + p.stock + '"></div>' +
      '<div class="form-group"><label>Images</label><div id="ep-gallery"></div>' +
      '<input type="file" id="ep-images" accept="image/*" multiple class="gallery-file-input"></div>' +
      '<div class="form-group"><label class="form-checkbox-label"><input type="checkbox" id="ep-featured" ' + (p.featured ? 'checked' : '') + '> Featured</label></div>' +
      '<div id="msg"></div><button type="submit" class="btn btn-primary">Save Changes</button> ' +
      '<button type="button" class="btn btn-outline" onclick="loadAdminProducts()">Cancel</button></form></div>';

    const gallery = createGalleryEditor('ep-gallery', 'ep-images', {
      onSetCover: async (imageId) => {
        try {
          await fetchJSON(B + '/api/admin/products/' + id + '/images/' + imageId + '/cover', { method: 'PUT' });
          toast('Cover updated', 'success');
          showEditProduct(id); // reload so ordering reflects the server
        } catch (e) { toast(e.message, 'error'); }
      }
    });
    gallery.setExisting(p.images || []);

    document.getElementById('edit-product-form').onsubmit = async (e) => {
      e.preventDefault();
      const btn = e.target.querySelector('button[type="submit"]');
      if (gallery.count() < 1) {
        document.getElementById('msg').innerHTML = '<p class="error-msg">A product must have at least 1 image.</p>';
        return;
      }
      btn.disabled = true; btn.textContent = 'Saving...';
      const fd = new FormData();
      fd.append('name', document.getElementById('ep-name').value);
      fd.append('description', document.getElementById('ep-desc').value);
      fd.append('price', document.getElementById('ep-price').value);
      fd.append('category_id', document.getElementById('ep-cat').value || '');
      fd.append('stock', document.getElementById('ep-stock').value);
      fd.append('featured', document.getElementById('ep-featured').checked);
      gallery.appendTo(fd);
      /* Sent as POST with a _csrf field (fetchForm) — PHP cannot parse
         multipart bodies on PUT requests. */
      try { await fetchForm(B + '/api/admin/products/' + id, fd); toast('Updated!', 'success'); loadAdminProducts(); }
      catch(err) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>'; btn.disabled = false; btn.textContent = 'Save Changes'; }
    };
    } catch(e) { toast('Failed to load product: ' + e.message, 'error'); loadAdminProducts(); }
  }
  async function loadCategories() {
    const cats = await fetchJSON(B + '/api/admin/categories');
    document.getElementById('admin-content').innerHTML = '<h2>Manage Categories</h2>' +
      '<div class="admin-form-card"><h3>Add Category</h3>' +
      '<form id="add-cat-form" class="admin-inline-form"><input type="text" id="cat-name" placeholder="Category name" required>' +
      '<input type="text" id="cat-desc" placeholder="Description (optional)"><button type="submit" class="btn btn-primary">Add</button></form></div>' +
      '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Products</th><th>Actions</th></tr></thead><tbody>' +
      (cats.length === 0 ? '<tr><td colspan="5" class="text-center text-muted">No categories yet</td></tr>' :
      cats.map(c => '<tr><td>' + c.id + '</td><td>' + escapeHtml(c.name) + '</td><td>' + escapeHtml(c.description || '-') + '</td><td>' + c.product_count + '</td><td><div class="cell-actions"><button class="btn btn-primary btn-sm" onclick="showEditCategory(' + c.id + ')">Edit</button> <button class="btn btn-danger btn-sm" onclick="deleteCategory(' + c.id + ')">Delete</button></div></td></tr>').join('')) +
      '</tbody></table></div>';
    document.getElementById('add-cat-form').onsubmit = async (e) => {
      e.preventDefault();
      const btn = e.target.querySelector('button[type="submit"]');
      btn.disabled = true; btn.textContent = 'Adding...';
      try { await fetchJSON(B + '/api/admin/categories', { method: 'POST', body: JSON.stringify({ name: document.getElementById('cat-name').value, description: document.getElementById('cat-desc').value }) }); toast('Category added!', 'success'); loadCategories(); }
      catch(e) { toast(e.message, 'error'); btn.disabled = false; btn.textContent = 'Add'; }
    };
  }
  async function showEditCategory(id) {
    try {
    showLoading('#admin-content');
    // Fetch fresh values by id instead of injecting name/description into
    // the onclick attribute (quotes in names broke the inline handler).
    const cats = await fetchJSON(B + '/api/admin/categories');
    const c = cats.find(x => String(x.id) === String(id));
    if (!c) { toast('Category not found', 'error'); return; }
    document.getElementById('admin-content').innerHTML = '<h2>Edit Category</h2><div class="admin-form-card"><form id="edit-cat-form">' +
      '<div class="form-group"><label>Name</label><input type="text" id="ec-name" value="' + escapeHtml(c.name) + '" required></div>' +
      '<div class="form-group"><label>Description</label><input type="text" id="ec-desc" value="' + escapeHtml(c.description || '') + '"></div>' +
      '<button type="submit" class="btn btn-primary">Save</button> <button type="button" class="btn btn-outline" onclick="loadCategories()">Cancel</button></form></div>';
    document.getElementById('edit-cat-form').onsubmit = async (e) => {
      e.preventDefault();
      const btn = e.target.querySelector('button[type="submit"]');
      btn.disabled = true; btn.textContent = 'Saving...';
      try { await fetchJSON(B + '/api/admin/categories/' + id, { method: 'PUT', body: JSON.stringify({ name: document.getElementById('ec-name').value, description: document.getElementById('ec-desc').value }) }); toast('Updated!', 'success'); loadCategories(); }
      catch(e) { toast(e.message, 'error'); btn.disabled = false; btn.textContent = 'Save'; }
    };
    } catch(e) { toast('Failed to load category: ' + e.message, 'error'); loadCategories(); }
  }
  async function deleteCategory(id) {
    if (!confirm('Delete this category?')) return;
    await fetchJSON(B + '/api/admin/categories/' + id, { method: 'DELETE' }); toast('Deleted', 'success'); loadCategories();
  }
  async function loadAdminOrders() {
    const orders = await fetchJSON(B + '/api/admin/orders');
    window._adminOrders = orders;
    renderAdminOrders(orders);
  }
  function renderAdminOrders(orders) {
    const paidCount = orders.filter(o => o.payment_status === 'success').length;
    const pendingPayCount = orders.filter(o => o.payment_method === 'paystack' && o.payment_status === 'pending').length;
    const failedCount = orders.filter(o => o.payment_status === 'failed').length;
    document.getElementById('admin-content').innerHTML = '<h2>Manage Orders</h2>' +
      '<div class="filter-bar">' +
      '<button class="btn btn-outline btn-sm" onclick="filterOrders(\'all\')">All (' + orders.length + ')</button>' +
      '<button class="btn btn-outline btn-sm btn-success" onclick="filterOrders(\'paid\')">Paid (' + paidCount + ')</button>' +
      '<button class="btn btn-outline btn-sm btn-warning" onclick="filterOrders(\'pending_pay\')">Awaiting Payment (' + pendingPayCount + ')</button>' +
      '<button class="btn btn-outline btn-sm btn-danger" onclick="filterOrders(\'failed\')">Failed (' + failedCount + ')</button></div>' +
      '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Method</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>' +
      '<tbody id="admin-orders-tbody">' + renderOrderRows(orders) + '</tbody></table></div>';
  }
  function renderOrderRows(orders) {
    return orders.map(o => '<tr><td>#' + o.id + '</td><td>' + escapeHtml(o.customer_name) + '<br><small class="text-muted">' + escapeHtml(o.customer_email) + '</small></td><td>' + formatPrice(o.total) + '</td>' +
      '<td><span class="badge badge-gray">' + (o.payment_method === 'delivery' ? 'Delivery' : 'Paystack') + '</span></td>' +
      '<td><span class="status status-' + (o.payment_status === 'success' ? 'delivered' : o.payment_status === 'failed' ? 'cancelled' : 'pending') + '">' + (o.payment_status === 'success' ? 'Paid' : o.payment_status === 'failed' ? 'Failed' : 'Pending') + '</span></td>' +
      '<td><span class="status status-' + o.status + '">' + o.status + '</span></td><td>' + new Date(o.created_at).toLocaleDateString() + '</td>' +
      '<td><div class="cell-actions"><button class="btn btn-primary btn-sm" onclick="viewOrderDetail(' + o.id + ')">View</button> ' +
      '<select onchange="updateOrderStatus(' + o.id + ', this.value)" class="select-sm">' +
      ['pending','processing','shipped','delivered','cancelled'].map(s => '<option value="' + s + '" ' + (o.status === s ? 'selected' : '') + '>' + s + '</option>').join('') + '</select>' +
      (o.payment_method === 'delivery' && o.payment_status !== 'success' ? ' <button class="btn btn-primary btn-sm" onclick="markPaid(' + o.id + ')">Mark Paid</button>' : '') + '</div></td></tr>').join('');
  }
  function filterOrders(filter) {
    let filtered = filter === 'paid' ? window._adminOrders.filter(o => o.payment_status === 'success') : filter === 'pending_pay' ? window._adminOrders.filter(o => o.payment_method === 'paystack' && o.payment_status === 'pending') : filter === 'failed' ? window._adminOrders.filter(o => o.payment_status === 'failed') : window._adminOrders;
    document.getElementById('admin-orders-tbody').innerHTML = renderOrderRows(filtered);
  }
  async function viewOrderDetail(id) {
    const o = await fetchJSON(B + '/api/admin/orders/' + id);
    document.getElementById('admin-content').innerHTML = '<h2>Order #' + o.id + '</h2>' +
      '<div class="admin-detail-grid">' +
      '<div class="admin-detail-card"><h3>Customer Info</h3><p><strong>' + escapeHtml(o.customer_name) + '</strong></p><p class="text-muted">' + escapeHtml(o.customer_email) + '</p><p class="text-muted">Phone: ' + escapeHtml(o.phone || 'N/A') + '</p></div>' +
      '<div class="admin-detail-card"><h3>Order Info</h3><p>Date: ' + new Date(o.created_at).toLocaleString() + '</p><p>Status: <span class="status status-' + o.status + '">' + o.status + '</span></p><p>Payment: <span class="status status-' + (o.payment_status === 'success' ? 'delivered' : 'pending') + '">' + o.payment_status + '</span> (' + o.payment_method + ')</p></div></div>' +
      '<div class="admin-detail-card admin-detail-wide"><h3>Items</h3>' +
      o.items.map(i => '<div class="order-item"><img src="' + escapeHtml(i.image || B + '/images/placeholder.png') + '" alt="' + escapeHtml(i.name) + '"><div class="order-item-info"><div class="name">' + escapeHtml(i.name) + '</div><div class="detail">Qty: ' + i.quantity + ' &times; ' + formatPrice(i.price) + '</div></div><div class="order-item-total">' + formatPrice(i.price * i.quantity) + '</div></div>').join('') +
      '<hr><div class="order-summary-line">Subtotal: ' + formatPrice(o.total - (o.shipping_fee || 0)) + '</div><div class="order-summary-line">Delivery Fee (' + escapeHtml(o.delivery_zone || 'N/A') + '): ' + formatPrice(o.shipping_fee || 0) + '</div><div class="order-summary-line order-summary-total">Total: ' + formatPrice(o.total) + '</div></div>' +
      '<div class="admin-detail-card admin-detail-wide"><h3>Shipping Address</h3><p class="text-muted">' + escapeHtml(o.shipping_address) + '</p></div>' +
      '<div class="admin-actions"><a href="' + B + '/api/admin/orders/' + o.id + '/invoice" target="_blank" class="btn btn-primary">Download Invoice</a></div>' +
      '<button class="btn btn-outline btn-back-margin" onclick="showTab(\'orders\')">Back to Orders</button>';
  }
  async function markPaid(id) {
    if (!confirm('Mark order #' + id + ' as paid? Only do this after you have actually received the money.')) return;
    await fetchJSON(B + '/api/admin/orders/' + id + '/payment', { method: 'PUT', body: JSON.stringify({ payment_status: 'success' }) }); toast('Marked as paid', 'success'); loadAdminOrders();
  }
  async function updateOrderStatus(id, status) {
    if (status === 'cancelled' && !confirm('Cancel order #' + id + '? The items in this order will be returned to stock.')) { loadAdminOrders(); return; }
    try { await fetchJSON(B + '/api/admin/orders/' + id + '/status', { method: 'PUT', body: JSON.stringify({ status }) }); toast('Status updated', 'success'); }
    catch(e) { toast(e.message, 'error'); loadAdminOrders(); }
  }
  async function loadUsers() {
    const users = await fetchJSON(B + '/api/admin/users');
    document.getElementById('admin-content').innerHTML = '<h2>Manage Users</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead><tbody>' +
      users.map(u => '<tr><td>' + u.id + '</td><td>' + escapeHtml(u.name) + '</td><td>' + escapeHtml(u.email) + '</td><td><span class="status status-' + (u.role === 'admin' ? 'processing' : 'pending') + '">' + u.role + '</span></td><td>' + new Date(u.created_at).toLocaleDateString() + '</td>' +
      '<td><div class="cell-actions"><button class="btn btn-primary btn-sm" onclick="viewUserOrders(' + u.id + ')">Orders</button> <select onchange="updateUserRole(' + u.id + ', this.value)" class="select-sm">' +
      '<option value="customer" ' + (u.role === 'customer' ? 'selected' : '') + '>Customer</option><option value="admin" ' + (u.role === 'admin' ? 'selected' : '') + '>Admin</option></select> <button class="btn btn-danger btn-sm" onclick="deleteUser(' + u.id + ')">Delete</button></div></td></tr>').join('') + '</tbody></table></div>';
  }
  async function updateUserRole(id, role) { await fetchJSON(B + '/api/admin/users/' + id + '/role', { method: 'PUT', body: JSON.stringify({ role }) }); toast('Role updated', 'success'); }
  async function deleteUser(id) {
    if (!confirm('Delete this user? Accounts with orders that are not completed and paid cannot be deleted.')) return;
    try {
      await fetchJSON(B + '/api/admin/users/' + id, { method: 'DELETE' });
      toast('Deleted', 'success');
      loadUsers();
    } catch (e) {
      toast(e.message, 'error');
    }
  }
  async function viewUserOrders(id) {
    const data = await fetchJSON(B + '/api/admin/users/' + id + '/orders');
    document.getElementById('admin-content').innerHTML = '<h2>Orders - ' + escapeHtml(data.user.name) + '</h2>' +
      '<p class="text-muted">' + escapeHtml(data.user.email) + ' | ' + data.total_orders + ' order(s) | Total spent: <strong>' + formatPrice(data.total_spent) + '</strong></p>' +
      (data.orders.length ? '<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>ID</th><th>Total</th><th>Zone</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead><tbody>' +
      data.orders.map(o => '<tr><td>#' + o.id + '</td><td>' + formatPrice(o.total) + '</td><td>' + escapeHtml(o.delivery_zone || 'N/A') + '</td><td>' + (o.payment_method === 'delivery' ? 'Delivery' : 'Paystack') + '</td><td><span class="status status-' + o.status + '">' + o.status + '</span></td><td>' + new Date(o.created_at).toLocaleDateString() + '</td></tr>').join('') + '</tbody></table></div>' : '<p class="text-muted">No orders.</p>') +
      '<button class="btn btn-outline" onclick="showTab(\'users\')">Back to Users</button>';
  }
  /* ---- Messages inbox ---- */
  let msgState = { filter: 'all', search: '', page: 1 };
  let msgSearchTimer = null;

  // "2026-09-08 01:03:00" from MySQL — the T form is parsed as local time
  // by every browser, the space form is implementation-specific.
  function msgParseDate(iso) { return new Date(String(iso).replace(' ', 'T')); }
  const MSG_MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const MSG_MONTHS_FULL = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  function msgShortDate(d) { return d.getDate() + ' ' + MSG_MONTHS[d.getMonth()] + ' ' + d.getFullYear(); }
  function msgFullDate(iso) {
    const d = msgParseDate(iso);
    let h = d.getHours(); const ampm = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
    return d.getDate() + ' ' + MSG_MONTHS_FULL[d.getMonth()] + ' ' + d.getFullYear() + ', ' + h + ':' + String(d.getMinutes()).padStart(2, '0') + ' ' + ampm;
  }
  function msgRelativeTime(iso) {
    const diff = Date.now() - msgParseDate(iso).getTime();
    if (diff < 60000) return 'Just now';
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return mins + (mins === 1 ? ' minute' : ' minutes') + ' ago';
    const hours = Math.floor(mins / 60);
    if (hours < 48) return hours + (hours === 1 ? ' hour' : ' hours') + ' ago';
    return msgShortDate(msgParseDate(iso));
  }

  async function loadMessages() {
    const params = new URLSearchParams({ filter: msgState.filter, page: msgState.page });
    if (msgState.search) params.set('search', msgState.search);
    const data = await fetchJSON(B + '/api/admin/messages?' + params.toString());
    window._msgData = data;
    const c = data.counts;
    const filterBtn = (key, label) =>
      '<button type="button" class="btn btn-sm ' + (msgState.filter === key ? 'btn-primary' : 'btn-outline') + '" onclick="msgFilter(\'' + key + '\')">' + label + (c[key] > 0 ? ' (' + c[key] + ')' : '') + '</button>';
    // Keep focus in the search box across re-renders (debounced search
    // rebuilds the list while the admin is still typing).
    const hadSearchFocus = document.activeElement && document.activeElement.id === 'msg-search';
    let html = '<h2>Messages</h2>' +
      '<div class="inbox-toolbar">' +
      '<input type="search" id="msg-search" placeholder="Search messages..." value="' + escapeHtml(msgState.search) + '" oninput="msgSearchInput(this.value)" aria-label="Search messages">' +
      '<div class="msg-filters">' + filterBtn('all', 'All') + filterBtn('unread', 'Unread') + filterBtn('read', 'Read') + '</div>' +
      '</div>';
    if (!data.messages.length) {
      const empty = msgState.search ? 'No messages match your search.' :
        msgState.filter === 'unread' ? 'No unread messages.' :
        msgState.filter === 'read' ? 'No read messages.' : 'No messages yet.';
      html += '<div class="empty-state"><h3>' + empty + '</h3></div>';
    } else {
      html += '<div class="inbox-list">' + data.messages.map(m =>
        '<button type="button" class="inbox-item' + (m.is_read ? '' : ' unread') + '" onclick="viewMessage(' + m.id + ')">' +
        '<span class="inbox-dot" aria-hidden="true"></span>' +
        '<span class="inbox-main">' +
        '<span class="inbox-top"><span class="inbox-name">' + escapeHtml(m.name) + '</span><span class="inbox-date">' + escapeHtml(msgRelativeTime(m.created_at)) + '</span></span>' +
        '<span class="inbox-subject">' + escapeHtml(m.subject) + '</span>' +
        '<span class="inbox-preview">' + escapeHtml(m.message.replace(/\s+/g, ' ').slice(0, 110)) + '</span>' +
        '<span class="inbox-email">' + escapeHtml(m.email) + '</span>' +
        '</span>' +
        '<span class="inbox-open">View</span>' +
        '</button>').join('') + '</div>';
      if (data.pages > 1) {
        html += '<div class="inbox-pagination">' +
          '<button type="button" class="btn btn-outline btn-sm"' + (data.page <= 1 ? ' disabled' : '') + ' onclick="msgPage(' + (data.page - 1) + ')">Previous</button>' +
          '<span class="text-muted">Page ' + data.page + ' of ' + data.pages + '</span>' +
          '<button type="button" class="btn btn-outline btn-sm"' + (data.page >= data.pages ? ' disabled' : '') + ' onclick="msgPage(' + (data.page + 1) + ')">Next</button>' +
          '</div>';
      }
    }
    document.getElementById('admin-content').innerHTML = html;
    if (hadSearchFocus) {
      const el = document.getElementById('msg-search');
      el.focus();
      el.setSelectionRange(el.value.length, el.value.length);
    }
  }

  async function viewMessage(id) {
    const data = window._msgData;
    const m = data && data.messages ? data.messages.find(x => String(x.id) === String(id)) : null;
    if (!m) { loadMessages(); return; }
    // Opening an unread message marks it read and updates the badge right away.
    if (!m.is_read) {
      try {
        await fetchJSON(B + '/api/admin/messages/' + id + '/read', { method: 'PUT' });
        m.is_read = 1;
        if (window.refreshAdminBadge) window.refreshAdminBadge();
      } catch (e) { /* the message still renders if the update fails */ }
    }
    document.getElementById('admin-content').innerHTML =
      '<button type="button" class="btn btn-outline btn-sm msg-back" onclick="msgBackToList()">&larr; Back to Messages</button>' +
      '<div class="message-detail">' +
      '<h2 class="msg-detail-subject">' + escapeHtml(m.subject) + '</h2>' +
      '<div class="msg-detail-meta">' +
      '<p><span class="msg-detail-label">From:</span> ' + escapeHtml(m.name) + ' &lt;' + escapeHtml(m.email) + '&gt;</p>' +
      '<p><span class="msg-detail-label">Date:</span> ' + escapeHtml(msgFullDate(m.created_at)) + '</p>' +
      '</div>' +
      '<div class="msg-detail-body">' + escapeHtml(m.message).replace(/\n/g, '<br>') + '</div>' +
      '<div class="msg-detail-actions">' +
      '<button type="button" class="btn btn-primary btn-sm" onclick="replyMessage(' + m.id + ')">Reply</button>' +
      '<button type="button" class="btn btn-outline btn-sm" onclick="markUnreadMessage(' + m.id + ')">Mark as Unread</button>' +
      '<button type="button" class="btn btn-danger btn-sm" onclick="deleteMessage(' + m.id + ')">Delete</button>' +
      '</div></div>';
  }

  function replyMessage(id) {
    const data = window._msgData;
    const m = data && data.messages ? data.messages.find(x => String(x.id) === String(id)) : null;
    if (!m) return;
    const subject = /^re:/i.test(m.subject) ? m.subject : 'Re: ' + m.subject;
    // encodeURIComponent keeps anything hostile in the stored email/subject
    // from breaking out of the mailto URL.
    window.location.href = 'mailto:' + encodeURIComponent(m.email) + '?subject=' + encodeURIComponent(subject);
  }

  async function markUnreadMessage(id) {
    try {
      await fetchJSON(B + '/api/admin/messages/' + id + '/unread', { method: 'PUT' });
      toast('Marked as unread', 'success');
      if (window.refreshAdminBadge) window.refreshAdminBadge();
      loadMessages();
    } catch (e) { toast(e.message, 'error'); }
  }

  function deleteMessage(id) {
    // Styled confirm (same modal pattern as logout) — never delete on a
    // single accidental click.
    closeDeleteMsgConfirm();
    const overlay = document.createElement('div');
    overlay.id = 'delete-msg-confirm';
    overlay.className = 'modal-overlay';
    overlay.innerHTML = '<div class="modal" style="max-width:400px;text-align:center;">' +
      '<h2>Delete this message?</h2>' +
      '<p class="text-muted" style="margin-bottom:24px;">Are you sure you want to delete this message? This cannot be undone.</p>' +
      '<div class="modal-actions" style="justify-content:center;">' +
      '<button type="button" class="btn btn-outline" id="dm-cancel">Cancel</button>' +
      '<button type="button" class="btn btn-danger" id="dm-ok">Delete</button>' +
      '</div></div>';
    document.body.appendChild(overlay);
    function close() { document.removeEventListener('keydown', onKey); overlay.remove(); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', onKey);
    overlay.querySelector('#dm-cancel').addEventListener('click', close);
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    overlay.querySelector('#dm-ok').addEventListener('click', async () => {
      close();
      try {
        await fetchJSON(B + '/api/admin/messages/' + id, { method: 'DELETE' });
        toast('Message deleted', 'success');
        if (window.refreshAdminBadge) window.refreshAdminBadge();
        loadMessages();
      } catch (e) { toast(e.message, 'error'); }
    });
  }
  function closeDeleteMsgConfirm() {
    const ov = document.getElementById('delete-msg-confirm');
    if (ov) ov.remove();
  }
  function showAddProduct() {
    document.getElementById('admin-content').innerHTML = '<h2>Add New Fragrance</h2><button type="button" class="btn btn-outline btn-sm" onclick="showTab(\'products\')">&larr; Back to Products</button><div class="admin-form-card" style="margin-top:15px;"><form id="add-product-form" enctype="multipart/form-data">' +
      '<div class="form-group"><label>Name</label><input type="text" id="p-name" required></div>' +
      '<div class="form-group"><label>Description</label><textarea id="p-desc" rows="3"></textarea></div>' +
      '<div class="form-group"><label>Price (GH&#8373;)</label><input type="number" id="p-price" step="0.01" required></div>' +
      '<div class="form-group"><label>Category</label><select id="p-cat"><option value="">Select category</option></select></div>' +
      '<div class="form-group"><label>Stock</label><input type="number" id="p-stock" value="0"></div>' +
      '<div class="form-group"><label>Images</label><div id="p-gallery"></div>' +
      '<input type="file" id="p-images" accept="image/*" multiple class="gallery-file-input"></div>' +
      '<div class="form-group"><label class="form-checkbox-label"><input type="checkbox" id="p-featured"> Featured</label></div>' +
      '<div id="msg"></div><button type="submit" class="btn btn-primary">Add Fragrance</button></form></div>';
    fetch(B + '/api/products/categories').then(r=>r.json()).then(cats => { document.getElementById('p-cat').innerHTML += cats.map(c => '<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>').join(''); });

    // A brand-new product has no saved images, so the counter starts at 0
    // and the submit handler enforces the minimum of 1.
    const gallery = createGalleryEditor('p-gallery', 'p-images');

    document.getElementById('add-product-form').onsubmit = async (e) => {
      e.preventDefault();
      const btn = e.target.querySelector('button[type="submit"]');
      if (gallery.count() < 1) {
        document.getElementById('msg').innerHTML = '<p class="error-msg">Please add at least 1 image for this product.</p>';
        return;
      }
      btn.disabled = true; btn.textContent = 'Adding...';
      const fd = new FormData();
      fd.append('name', document.getElementById('p-name').value);
      fd.append('description', document.getElementById('p-desc').value);
      fd.append('price', document.getElementById('p-price').value);
      fd.append('category_id', document.getElementById('p-cat').value || '');
      fd.append('stock', document.getElementById('p-stock').value);
      fd.append('featured', document.getElementById('p-featured').checked);
      gallery.appendTo(fd);
      try { await fetchForm(B + '/api/admin/products', fd); toast('Added!', 'success'); showTab('products'); }
      catch(e) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(e.message) + '</p>'; btn.disabled = false; btn.textContent = 'Add Fragrance'; }
    };
  }
  function showExport() {
    document.getElementById('admin-content').innerHTML = '<h2>Export Data</h2><p class="text-muted">Download selected store data as spreadsheets.</p>' +
      '<div class="export-cards">' +
      '<a href="' + B + '/api/admin/export/orders" class="btn btn-primary">Export Orders</a>' +
      '<a href="' + B + '/api/admin/export/products" class="btn btn-primary">Export Products</a>' +
      '<a href="' + B + '/api/admin/export/messages" class="btn btn-primary">Export Messages</a></div>';
  }
  function showNewsletter() {
    document.getElementById('admin-content').innerHTML = '<h2>Newsletter / Promo Email</h2><p class="text-muted">Send a marketing email only to subscribers who opted in on the newsletter form and have not unsubscribed. Registered customers are not included unless they also subscribed. Every send includes an unsubscribe link.</p>' +
      '<div class="admin-form-card"><form id="newsletter-form"><div class="form-group"><label>Subject</label><input type="text" id="nl-subject" required placeholder="e.g. New Arrivals!"></div>' +
      '<div class="form-group"><label>Message</label><textarea id="nl-message" rows="6" required placeholder="Write your promo message..."></textarea></div>' +
      '<button type="submit" class="btn btn-primary" id="nl-btn">Send Newsletter</button></form><div id="msg"></div></div>';
    document.getElementById('newsletter-form').onsubmit = async (e) => {
      e.preventDefault();
      const btn = document.getElementById('nl-btn'); btn.disabled = true; btn.textContent = 'Sending...';
      try { const r = await fetchJSON(B + '/api/admin/newsletter', { method: 'POST', body: JSON.stringify({ subject: document.getElementById('nl-subject').value, message: document.getElementById('nl-message').value }) });
        document.getElementById('msg').innerHTML = '<p class="success-msg">Sent to ' + r.sent + ' customer(s)' + (r.failed ? ' (' + r.failed + ' failed)' : '') + '.</p>'; btn.textContent = 'Send Newsletter'; btn.disabled = false; }
      catch(err) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(err.message) + '</p>'; btn.textContent = 'Send Newsletter'; btn.disabled = false; }
    };
  }
  async function loadProfile() {
    try {
      const p = await fetchJSON(B + '/api/admin/profile');
      document.getElementById('admin-content').innerHTML = '<h2>My Profile</h2><div class="admin-form-card"><form id="profile-form">' +
        '<div class="form-group"><label>Full Name</label><input type="text" id="prof-name" value="' + escapeHtml(p.name) + '" required></div>' +
        '<div class="form-group"><label>Email</label><input type="email" id="prof-email" value="' + escapeHtml(p.email) + '" required></div>' +
        '<hr class="admin-hr"><p class="text-muted text-small">Leave password fields blank to keep current.</p>' +
        '<div class="form-group"><label>Current Password</label><input type="password" id="prof-current-pw" placeholder="Enter current password to change"></div>' +
        '<div class="form-group"><label>New Password</label><input type="password" id="prof-new-pw" placeholder="Min 8 characters"></div>' +
        '<div id="msg"></div><button type="submit" class="btn btn-primary">Update Profile</button></form></div>';
      addPasswordToggle(document.getElementById('prof-current-pw'));
      addPasswordToggle(document.getElementById('prof-new-pw'));
      document.getElementById('profile-form').onsubmit = async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true; btn.textContent = 'Saving...';
        try { await fetchJSON(B + '/api/admin/profile', { method: 'PUT', body: JSON.stringify({ name: document.getElementById('prof-name').value, email: document.getElementById('prof-email').value, current_password: document.getElementById('prof-current-pw').value, new_password: document.getElementById('prof-new-pw').value }) }); toast('Profile updated!', 'success'); btn.disabled = false; btn.textContent = 'Update Profile'; }
        catch(e) { document.getElementById('msg').innerHTML = '<p class="error-msg">' + escapeHtml(e.message) + '</p>'; btn.disabled = false; btn.textContent = 'Update Profile'; }
      };
    } catch(e) { document.getElementById('admin-content').innerHTML = '<h2>My Profile</h2><p class="error-msg">' + escapeHtml(e.message) + '</p>'; }
  }
  document.addEventListener('click', function(e) {
    if (e.target.matches('.admin-sidebar a[data-tab]')) {
      e.preventDefault();
      showTab(e.target.getAttribute('data-tab'));
    }
    if (e.target.matches('a[data-tab]')) {
      const tab = e.target.getAttribute('data-tab');
      if (tab) showTab(tab);
    }
  });
  async function loadAnalytics() {
    const s = await fetchJSON(B + '/api/admin/stats');
    const maxDay = s.daily_revenue.reduce((m, d) => Math.max(m, Number(d.total)), 0) || 1;
    const bar = (v) => 'height:' + Math.max(4, Math.round((Number(v) / maxDay) * 160)) + 'px;';
    document.getElementById('admin-content').innerHTML = '<h2 style="margin-bottom:25px;">Analytics</h2>' +
      '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:25px;">' +
      '<div class="stat-card"><div class="label">Orders Today</div><div class="value">' + s.orders_today + '</div></div>' +
      '<div class="stat-card"><div class="label">Revenue Today</div><div class="value">' + formatPrice(s.revenue_today) + '</div></div>' +
      '<div class="stat-card"><div class="label">Low Stock Items</div><div class="value" style="' + (s.low_stock > 0 ? 'color:#dc2626;' : '') + '">' + s.low_stock + '</div></div>' +
      '</div>' +
      '<div style="background:var(--surface);padding:20px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:20px;">' +
      '<h3 style="margin-bottom:15px;">Revenue - Last 7 Days</h3>' +
      '<div style="display:flex;align-items:flex-end;gap:14px;height:180px;padding:10px 0;">' +
      s.daily_revenue.map(d => '<div style="flex:1;text-align:center;"><div style="background:var(--primary);border-radius:6px 6px 0 0;' + bar(d.total) + 'min-width:24px;margin:0 auto;"></div><div style="font-size:0.7rem;color:var(--text-muted);margin-top:6px;">' + (d.day ? new Date(d.day).toLocaleDateString(undefined, { weekday: 'short' }) : '') + '</div></div>').join('') +
      '</div></div>' +
      '<div style="background:var(--surface);padding:20px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:20px;">' +
      '<h3 style="margin-bottom:15px;">Top Selling Fragrances</h3>' +
      (s.top_sellers && s.top_sellers.length ? '<table class="admin-table"><thead><tr><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead><tbody>' + s.top_sellers.map(t => '<tr><td>' + escapeHtml(t.name) + '</td><td>' + t.qty + '</td><td>' + formatPrice(t.total) + '</td></tr>').join('') + '</tbody></table>' : '<p style="color:var(--text-muted);">No sales yet.</p>') +
      '</div>';
  }

  async function loadDeliveryZones() {
    const zones = await fetchJSON(B + '/api/admin/delivery-zones');
    const cats = { pickup: [], delivery: [] };
    zones.forEach(z => (cats[z.category] || cats.delivery).push(z));
    const zoneCard = (z) => '<div style="background:var(--surface);padding:20px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:15px;">' +
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">' +
        '<div class="form-group" style="margin:0;"><label>Zone Label</label><input type="text" id="dz-label-' + z.id + '" value="' + escapeHtml(z.label) + '"></div>' +
        '<div class="form-group" style="margin:0;"><label>Fee (GHS)</label><input type="number" id="dz-fee-' + z.id + '" value="' + z.fee + '" min="0" step="0.01"></div>' +
        '</div>' +
        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">' +
        '<div class="form-group" style="margin:0;"><label>ETA note</label><input type="text" id="dz-eta-' + z.id + '" value="' + escapeHtml(z.eta || '') + '"></div>' +
        '<div class="form-group" style="margin:0;"><label>Category</label><select id="dz-cat-' + z.id + '">' +
        '<option value="delivery"' + (z.category === 'pickup' ? '' : ' selected') + '>Delivery</option>' +
        '<option value="pickup"' + (z.category === 'pickup' ? ' selected' : '') + '>Pick Up</option>' +
        '</select></div>' +
        '</div>' +
        '<div style="display:flex;gap:10px;margin-top:12px;">' +
        '<button class="btn btn-primary btn-sm" onclick="saveDeliveryZone(\'' + z.id + '\')">Save Zone</button>' +
        '<button class="btn btn-outline btn-sm" onclick="deleteDeliveryZone(\'' + z.id + '\')" style="color:var(--danger);border-color:var(--danger);">Delete</button>' +
        '</div></div>';
    document.getElementById('admin-content').innerHTML = '<h2 style="margin-bottom:15px;">Delivery Zones & Fees</h2>' +
      '<p style="color:var(--text-muted);margin-bottom:20px;">Zones are grouped under the two main delivery categories. These fees are used at checkout. Changes apply to new orders immediately.</p>' +
      '<div style="max-width:700px;">' +
      '<button type="button" class="btn btn-primary btn-sm" id="add-zone-toggle" aria-expanded="false" aria-controls="add-zone-form" style="margin-bottom:15px;">+ Add Zone</button>' +
      '<div id="add-zone-form" hidden style="background:var(--surface);padding:20px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:15px;border:1px dashed var(--border);">' +
      '<h3 style="margin:0 0 12px;font-size:1rem;">Add New Zone</h3>' +
      '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">' +
      '<div class="form-group" style="margin:0;"><label>Zone ID (unique, lowercase)</label><input type="text" id="nz-id" placeholder="e.g. kumasi"></div>' +
      '<div class="form-group" style="margin:0;"><label>Zone Label</label><input type="text" id="nz-label" placeholder="e.g. Ashanti Region"></div>' +
      '<div class="form-group" style="margin:0;"><label>Fee (GHS)</label><input type="number" id="nz-fee" min="0" step="0.01" value="0"></div>' +
      '<div class="form-group" style="margin:0;"><label>ETA note</label><input type="text" id="nz-eta" placeholder="e.g. 2-4 business days"></div>' +
      '</div>' +
      '<div class="form-group" style="margin:12px 0 0;"><label>Category</label><select id="nz-cat">' +
      '<option value="delivery">Delivery</option>' +
      '<option value="pickup">Pick Up</option>' +
      '</select></div>' +
      '<div style="display:flex;gap:10px;margin-top:12px;">' +
      '<button class="btn btn-primary btn-sm" id="add-zone-btn" onclick="addDeliveryZone()">Save New Zone</button>' +
      '<button type="button" class="btn btn-outline btn-sm" id="cancel-add-zone">Cancel</button></div>' +
      '</div>' +
      '<h3 style="font-size:1.05rem;margin:25px 0 12px;">Pick Up</h3>' +
      (cats.pickup.length ? cats.pickup.map(zoneCard).join('') : '<p class="text-muted" style="margin-bottom:15px;">No pick-up zones yet.</p>') +
      '<h3 style="font-size:1.05rem;margin:25px 0 12px;">Delivery</h3>' +
      (cats.delivery.length ? cats.delivery.map(zoneCard).join('') : '<p class="text-muted" style="margin-bottom:15px;">No delivery zones yet.</p>') +
      '</div>';
    const addZoneToggle = document.getElementById('add-zone-toggle');
    const addZoneForm = document.getElementById('add-zone-form');
    const setAddZoneFormVisible = (visible) => {
      addZoneForm.hidden = !visible;
      addZoneToggle.setAttribute('aria-expanded', String(visible));
      addZoneToggle.textContent = visible ? 'Close Add Zone Form' : '+ Add Zone';
      if (visible) document.getElementById('nz-id').focus();
    };
    addZoneToggle.addEventListener('click', () => setAddZoneFormVisible(addZoneForm.hidden));
    document.getElementById('cancel-add-zone').addEventListener('click', () => setAddZoneFormVisible(false));
  }

  async function addDeliveryZone() {
    const id = document.getElementById('nz-id').value.trim().toLowerCase();
    const label = document.getElementById('nz-label').value.trim();
    const fee = document.getElementById('nz-fee').value;
    const eta = document.getElementById('nz-eta').value.trim();
    const category = document.getElementById('nz-cat').value;
    if (!id || !label) return toast('Zone ID and label are required', 'error');
    const btn = document.getElementById('add-zone-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Adding...'; }
    try {
      await fetchJSON(B + '/api/admin/delivery-zones', { method: 'POST', body: JSON.stringify({ id, label, fee, eta, category }) });
      toast('Delivery zone added!', 'success');
      loadDeliveryZones();
    } catch (e) {
      toast(e.message, 'error');
      if (btn) { btn.disabled = false; btn.textContent = '+ Add Zone'; }
    }
  }

  async function deleteDeliveryZone(id) {
    if (!confirm('Delete this delivery zone? This cannot be undone.')) return;
    try {
      await fetchJSON(B + '/api/admin/delivery-zones/' + id, { method: 'DELETE' });
      toast('Delivery zone deleted', 'success');
      loadDeliveryZones();
    } catch (e) { toast(e.message, 'error'); }
  }

  async function saveDeliveryZone(id) {
    try {
      await fetchJSON(B + '/api/admin/delivery-zones/' + id, { method: 'PUT', body: JSON.stringify({
        label: document.getElementById('dz-label-' + id).value,
        fee: document.getElementById('dz-fee-' + id).value,
        eta: document.getElementById('dz-eta-' + id).value,
        category: document.getElementById('dz-cat-' + id).value
      }) });
      toast('Delivery zone updated!', 'success');
      loadDeliveryZones();
    } catch (e) { toast(e.message, 'error'); }
  }

  // Admin tables use inline onclick/onchange handlers, which need global references
  window.showTab = showTab;
  window.showAddProduct = showAddProduct;
  window.filterAdminProducts = filterAdminProducts;
  window.updateStock = updateStock;
  window.showEditProduct = showEditProduct;
  window.deleteProduct = deleteProduct;
  window.loadAdminProducts = loadAdminProducts;
  window.showEditCategory = showEditCategory;
  window.deleteCategory = deleteCategory;
  window.loadCategories = loadCategories;
  window.filterOrders = filterOrders;
  window.viewOrderDetail = viewOrderDetail;
  window.updateOrderStatus = updateOrderStatus;
  window.markPaid = markPaid;
  window.viewUserOrders = viewUserOrders;
  window.updateUserRole = updateUserRole;
  window.deleteUser = deleteUser;
  window.deleteMessage = deleteMessage;
  window.viewMessage = viewMessage;
  window.replyMessage = replyMessage;
  window.markUnreadMessage = markUnreadMessage;
  window.msgFilter = (f) => { msgState.filter = f; msgState.page = 1; loadMessages(); };
  window.msgSearchInput = (v) => { msgState.search = v; msgState.page = 1; clearTimeout(msgSearchTimer); msgSearchTimer = setTimeout(loadMessages, 350); };
  window.msgPage = (p) => { msgState.page = p; loadMessages(); };
  window.msgBackToList = () => loadMessages();
  window.addDeliveryZone = addDeliveryZone;
  window.saveDeliveryZone = saveDeliveryZone;
  window.deleteDeliveryZone = deleteDeliveryZone;

  checkAdmin();
  showTab('dashboard');
})();
</script>
