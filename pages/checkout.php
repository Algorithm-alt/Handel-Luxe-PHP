<?php $pageTitle = 'Checkout';
$metaDescription = 'Complete your Handel Luxe GH order: choose delivery or pickup, and pay securely with Paystack or on delivery.';
$breadcrumbs = [['label' => 'Home', 'url' => '/'], ['label' => 'Cart', 'url' => '/cart'], ['label' => 'Checkout']]; ?>
<?php
require_once __DIR__ . '/../config/delivery.php';
/* The browser loads the live zones from /api/delivery/zones. If that call
   fails it falls back to this list, which is the same getDeliveryZones() the
   API itself serves, so the two cannot drift apart. The JSON flags matter:
   zone labels are editable from the admin dashboard, and a label containing
   "</script>" would otherwise close the block it is printed into. */
$fallbackZones = getDeliveryZones();
?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container checkout-wrap">
  <h1 class="checkout-title">Checkout</h1>
  <div id="checkout-root">

    <div class="checkout-layout">
      <div class="checkout-main form-card">
        <form id="checkout-form" novalidate>

          <section class="checkout-section">
            <h3><span class="step-num" aria-hidden="true">1</span> Customer Details</h3>
            <div id="guest-note" class="alert alert-info" hidden>
              <strong>Checking out as a guest</strong>
              <p>No account needed. Enter your details so we can confirm your order.</p>
            </div>
            <div class="form-grid">
              <div class="form-group form-grid-full">
                <label for="cust_name">Full Name <span class="req">*</span></label>
                <input type="text" id="cust_name" autocomplete="name" placeholder="e.g. Ama Mensah">
              </div>
              <div class="form-group">
                <label for="cust_email">Email Address <span class="req">*</span></label>
                <input type="email" id="cust_email" autocomplete="email" placeholder="e.g. ama@email.com">
              </div>
              <div class="form-group">
                <label for="cust_phone">Phone Number <span class="req">*</span></label>
                <input type="tel" id="cust_phone" autocomplete="tel" placeholder="024 123 4567">
              </div>
            </div>
          </section>

          <section class="checkout-section">
            <h3><span class="step-num" aria-hidden="true">2</span> How would you like to receive your order?</h3>
            <div class="fulfillment-options">
              <label class="choice-card" id="fulfill-delivery">
                <input type="radio" name="fulfillment" value="delivery" checked>
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <span class="choice-text"><strong>Delivery</strong><p>Have your order delivered to your location.</p></span>
              </label>
              <label class="choice-card" id="fulfill-pickup">
                <input type="radio" name="fulfillment" value="pickup">
                <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/><path d="M3 9h18"/></svg>
                <span class="choice-text"><strong>Pick Up</strong><p>Collect your order from one of our pickup locations.</p></span>
              </label>
            </div>

            <div id="delivery-fields">
              <div class="form-group">
                <label for="delivery_zone">Delivery Area <span class="req">*</span></label>
                <select id="delivery_zone">
                  <option value="">Select your delivery area...</option>
                </select>
              </div>
              <div id="delivery-fee-line" class="zone-info" hidden>
                <span>Delivery Fee</span><strong id="delivery-fee-amount"></strong>
                <span class="zone-eta" id="delivery-eta"></span>
              </div>
              <div class="form-group">
                <label for="address">Delivery Address <span class="req">*</span></label>
                <textarea id="address" rows="3" autocomplete="street-address" placeholder="Enter your full address, including landmark if possible..."></textarea>
              </div>
            </div>

            <div id="pickup-fields" hidden>
              <div class="form-group">
                <label for="pickup_location">Pickup Location <span class="req">*</span></label>
                <select id="pickup_location">
                  <option value="">Select a pickup location...</option>
                </select>
              </div>
              <div id="pickup-fee-line" class="zone-info" hidden>
                <span>Pickup Fee</span><strong class="text-success">FREE</strong>
                <span class="zone-eta" id="pickup-eta"></span>
              </div>
              <p class="text-muted pickup-note">No delivery address needed. We will contact you when your order is ready for collection.</p>
            </div>
          </section>

          <section class="checkout-section checkout-section-last">
            <h3><span class="step-num" aria-hidden="true">3</span> Payment Method</h3>
            <div class="payment-options">
              <label class="pay-option" id="opt-delivery">
                <input type="radio" name="payment_method" value="delivery">
                <div><strong>Pay on Delivery</strong><p>Pay with cash or Mobile Money when your order arrives.</p></div>
              </label>
              <label class="pay-option" id="opt-paystack">
                <input type="radio" name="payment_method" value="paystack" checked>
                <div><strong>Pay with Paystack</strong><p>Pay securely online using Mobile Money, card, or bank transfer.</p></div>
              </label>
            </div>
          </section>

          <div id="msg" role="alert"></div>
          <button type="submit" class="btn btn-primary btn-block btn-lg" id="checkout-btn">Pay with Paystack</button>
          <p class="text-muted secure-note">Payments are processed securely by Paystack. We never store your card details.</p>
        </form>
      </div>

      <aside class="checkout-side">
        <div class="order-summary" id="order-summary">
          <div class="loading-placeholder"><div class="spinner"></div><p>Loading your order...</p></div>
        </div>
      </aside>
    </div>

    <div id="confirm-box" hidden></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  let cartTotal = 0, cartItems = [], paystackKey = '', paystackLoaded = false, paystackAvailable = false, isGuest = false;
  /* Server-rendered copy of the zone list, used only if the API call below
     fails. Printed from config/delivery.php via getDeliveryZones(), so there
     is no second copy of the fees to keep in step. */
  const FALLBACK_ZONES = <?= json_encode($fallbackZones, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
  let ZONES = [];               // full zone objects from /api/delivery/zones
  let DELIVERY_FEES = {};       // zone id -> fee, display only
  let submitting = false;

  const el = (id) => document.getElementById(id);

  /* ---------- loading ---------- */
  (async function() {
    try {
      const u = await fetchJSON(B + '/api/auth/me');
      setAuthLinks(u);
      isGuest = false;
      el('cust_name').value = u.name || '';
      el('cust_email').value = u.email || '';
      el('cust_name').readOnly = true;
      el('cust_email').readOnly = true;
      el('cust_name').classList.add('input-locked');
      el('cust_email').classList.add('input-locked');
    } catch (e) {
      isGuest = true;
      setAuthLinks(null);
      el('guest-note').hidden = false;
    }
    let data;
    try {
      data = await fetchJSON(B + '/api/cart');
    } catch (e) {
      el('order-summary').innerHTML = '<h3>Order Summary</h3><p class="text-muted">We could not load your cart. Please refresh the page and try again.</p>';
      return;
    }
    // Paystack configuration is optional: an outage or missing key must not
    // take the whole checkout down when Pay on Delivery is still available.
    try {
      const config = await fetchJSON(B + '/api/payments/config');
      paystackKey = config.publicKey || '';
      paystackAvailable = !!paystackKey;
    } catch (e) {
      paystackAvailable = false;
    }
    if (!paystackAvailable) {
      const paystackOpt = el('opt-paystack');
      const paystackInput = paystackOpt.querySelector('input');
      paystackInput.checked = false;
      paystackInput.disabled = true;
      paystackOpt.classList.add('choice-disabled');
      el('opt-delivery').querySelector('input').checked = true;
      const note = document.createElement('p');
      note.className = 'text-muted';
      note.textContent = 'Online payment is temporarily unavailable. Please choose Pay on Delivery.';
      paystackOpt.insertAdjacentElement('afterend', note);
      updatePayOptionStyles();
    }
    if (data.items.length === 0) { window.location.href = B + '/cart'; return; }
    cartItems = data.items;
    cartTotal = data.total;
    renderSummary();
    updateActionButton();
  })();

  (async function() {
    try {
      ZONES = await fetchJSON(B + '/api/delivery/zones');
    } catch (e) {
      // Offline fallback so checkout stays usable if the zones API is down.
      ZONES = FALLBACK_ZONES;
    }
    ZONES.forEach(z => { DELIVERY_FEES[z.id] = Number(z.fee) || 0; });
    populateZoneSelect('delivery_zone', ZONES.filter(z => z.category !== 'pickup'));
    const pickupZones = ZONES.filter(z => z.category === 'pickup');
    populateZoneSelect('pickup_location', pickupZones);
    if (pickupZones.length === 0) {
      const card = el('fulfill-pickup');
      card.classList.add('choice-disabled');
      card.querySelector('input').disabled = true;
    }
  })();

  function populateZoneSelect(selectId, zones) {
    const select = el(selectId);
    zones.forEach(z => {
      const opt = document.createElement('option');
      opt.value = z.id;
      opt.textContent = z.label + (z.category === 'pickup' || (Number(z.fee) || 0) === 0 ? '' : ' (GH₵ ' + Number(z.fee) + ')');
      select.appendChild(opt);
    });
    select.addEventListener('change', updateDeliverySummary);
  }

  /* ---------- fulfillment choice ---------- */
  function currentFulfillment() {
    return document.querySelector('input[name="fulfillment"]:checked').value;
  }
  document.querySelectorAll('input[name="fulfillment"]').forEach(r => r.addEventListener('change', function() {
    updateChoiceStyles();
    const isDelivery = currentFulfillment() === 'delivery';
    el('delivery-fields').hidden = !isDelivery;
    el('pickup-fields').hidden = isDelivery;
    updateDeliverySummary();
    renderSummary();
    updateActionButton();
  }));
  function updateChoiceStyles() {
    document.querySelectorAll('.choice-card').forEach(c => {
      c.classList.toggle('selected', c.querySelector('input').checked);
    });
  }

  /* ---------- fee + total (display only; server recalculates everything) ---------- */
  function chosenZone() {
    const fulfillment = currentFulfillment();
    const selectId = fulfillment === 'delivery' ? 'delivery_zone' : 'pickup_location';
    const value = el(selectId).value;
    return ZONES.find(z => z.id === value) || null;
  }
  function currentFee() {
    const z = chosenZone();
    return z ? (Number(z.fee) || 0) : null;
  }
  function updateDeliverySummary() {
    const z = chosenZone();
    const isDelivery = currentFulfillment() === 'delivery';
    const feeLine = el(isDelivery ? 'delivery-fee-line' : 'pickup-fee-line');
    const otherLine = el(isDelivery ? 'pickup-fee-line' : 'delivery-fee-line');
    otherLine.hidden = true;
    if (!z) { feeLine.hidden = true; }
    else {
      feeLine.hidden = false;
      const fee = Number(z.fee) || 0;
      el('delivery-fee-amount').textContent = fee === 0 ? 'FREE' : formatPrice(fee);
      const etaEl = el(isDelivery ? 'delivery-eta' : 'pickup-eta');
      etaEl.textContent = z.eta ? (isDelivery ? 'Estimated delivery: ' : '') + z.eta : '';
    }
    renderSummary();
    updateActionButton();
  }

  function renderSummary() {
    const z = chosenZone();
    const isDelivery = currentFulfillment() === 'delivery';
    const fee = currentFee();
    const method = z
      ? (isDelivery ? 'Delivery: ' + escapeHtml(z.label) : 'Pick Up: ' + escapeHtml(z.label))
      : (isDelivery ? 'Delivery' : 'Pick Up');
    el('order-summary').innerHTML = '<h3>Order Summary</h3>' +
      cartItems.map(i => '<div class="summary-line"><span>' + escapeHtml(i.name) + ' × ' + i.quantity + '</span><span>' + formatPrice(i.price * i.quantity) + '</span></div>').join('') +
      '<hr><div class="summary-line"><span>Subtotal</span><span>' + formatPrice(cartTotal) + '</span></div>' +
      '<div class="summary-line"><span>' + method + '</span><span id="summary-fee">' + (fee === null ? 'Select ' + (isDelivery ? 'delivery area' : 'pickup location') : (fee === 0 ? 'FREE' : formatPrice(fee))) + '</span></div>' +
      '<hr><div class="summary-line summary-total"><span>Total</span><span id="summary-total">' + formatPrice(cartTotal + (fee || 0)) + '</span></div>';
  }

  /* ---------- payment method ---------- */
  function currentPayment() {
    return document.querySelector('input[name="payment_method"]:checked').value;
  }
  document.querySelectorAll('input[name="payment_method"]').forEach(r => r.addEventListener('change', function() {
    updatePayOptionStyles();
    updateActionButton();
  }));
  function updatePayOptionStyles() {
    document.querySelectorAll('.pay-option').forEach(p => {
      p.classList.toggle('selected', p.querySelector('input').checked);
    });
  }
  // Apply the selected highlight to the cards that start checked
  updateChoiceStyles();
  updatePayOptionStyles();

  function updateActionButton() {
    const btn = el('checkout-btn');
    if (submitting) return;
    if (currentPayment() === 'delivery') {
      btn.textContent = 'Place Order';
    } else {
      const fee = currentFee();
      btn.textContent = fee === null ? 'Pay with Paystack' : 'Pay ' + formatPrice(cartTotal + fee) + ' with Paystack';
    }
  }

  /* ---------- inline validation ---------- */
  function setFieldError(input, msg) {
    clearFieldError(input);
    const err = document.createElement('div');
    err.className = 'field-error';
    err.textContent = msg;
    input.closest('.form-group').appendChild(err);
    input.classList.add('input-invalid');
    input.addEventListener('input', () => clearFieldError(input), { once: true });
    input.addEventListener('change', () => clearFieldError(input), { once: true });
  }
  function clearFieldError(input) {
    input.classList.remove('input-invalid');
    const group = input.closest('.form-group');
    if (group) group.querySelectorAll('.field-error').forEach(e => e.remove());
  }

  function validateForm() {
    let firstBad = null;
    const mark = (input, msg) => { setFieldError(input, msg); if (!firstBad) firstBad = input; };

    if (!el('cust_name').value.trim()) mark(el('cust_name'), 'Please enter your full name.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el('cust_email').value.trim())) mark(el('cust_email'), 'Please enter a valid email address.');
    if (!/^[+\d][\d\s\-()]{7,17}$/.test(el('cust_phone').value.trim())) mark(el('cust_phone'), 'Please enter a valid phone number, e.g. 024 123 4567.');

    const isDelivery = currentFulfillment() === 'delivery';
    const zoneSelect = el(isDelivery ? 'delivery_zone' : 'pickup_location');
    if (!zoneSelect.value) mark(zoneSelect, isDelivery ? 'Please select your delivery area.' : 'Please select a pickup location.');
    if (isDelivery && el('address').value.trim().length < 8) mark(el('address'), 'Please enter your full delivery address.');

    if (firstBad) { firstBad.focus(); firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    return !firstBad;
  }

  /* ---------- friendly server error messages ---------- */
  function friendlyError(msg) {
    const m = msg || '';
    if (/cart is empty/i.test(m)) return 'Your cart is empty. Please add items before checking out.';
    if (/insufficient stock/i.test(m)) return 'Sorry, one of the products in your cart is no longer available in the quantity you selected. Please return to your cart and review your items.';
    if (/Failed to fetch|NetworkError|network/i.test(m)) return 'We could not reach the server. Please check your connection and try again.';
    if (/invalid response/i.test(m)) return 'The server returned an unexpected response. Please try again in a moment.';
    return m || 'Something went wrong. Please try again.';
  }
  function showMsg(text, type) {
    el('msg').innerHTML = type === 'error'
      ? '<p class="error-msg">' + escapeHtml(text) + '</p>'
      : '<p class="success-msg">' + escapeHtml(text) + '</p>';
  }

  /* ---------- submission ---------- */
  function restoreButton() {
    submitting = false;
    const btn = el('checkout-btn');
    btn.disabled = false;
    updateActionButton();
  }

  el('checkout-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (submitting) return; // double-submit guard
    el('msg').innerHTML = '';
    if (currentPayment() === 'paystack' && !paystackAvailable) {
      showMsg('Online payment is temporarily unavailable. Please choose Pay on Delivery.', 'error');
      return;
    }
    if (!validateForm()) return;

    const isDelivery = currentFulfillment() === 'delivery';
    const zoneSelect = el(isDelivery ? 'delivery_zone' : 'pickup_location');
    const zone = ZONES.find(z => z.id === zoneSelect.value);
    // Pickup orders store the location as the shipping address so the
    // existing backend keeps working unchanged (it requires an address).
    const address = isDelivery
      ? el('address').value.trim()
      : 'Pickup: ' + (zone ? zone.label : '');
    const phone = el('cust_phone').value.trim();
    const gp = isGuest
      ? { guest_name: el('cust_name').value.trim(), guest_email: el('cust_email').value.trim() }
      : {};

    submitting = true;
    const btn = el('checkout-btn');
    btn.disabled = true;

    if (currentPayment() === 'delivery') {
      btn.textContent = 'Placing your order...';
      try {
        const data = await fetchJSON(B + '/api/orders/place', {
          method: 'POST',
          body: JSON.stringify({ shipping_address: address, phone, delivery_zone: zoneSelect.value, payment_method: 'delivery', ...gp })
        });
        showConfirmation({
          orderId: data.order_id,
          paymentLabel: 'Pay on Delivery (pay when it arrives)',
          zoneLabel: (isDelivery ? 'Delivery: ' : 'Pick Up: ') + (zone ? zone.label : ''),
          total: cartTotal + (currentFee() || 0)
        });
        loadCartCount();
      } catch (err) {
        showMsg(friendlyError(err.message), 'error');
        restoreButton();
      }
    } else {
      btn.textContent = 'Starting secure payment...';
      try {
        if (!paystackLoaded) {
          await new Promise((res, rej) => {
            const s = document.createElement('script');
            s.src = 'https://js.paystack.co/v1/inline.js';
            s.onload = res;
            s.onerror = () => rej(new Error('Could not load Paystack. Please check your connection and try again.'));
            document.head.appendChild(s);
          });
          paystackLoaded = true;
        }
        const data = await fetchJSON(B + '/api/payments/initialize', {
          method: 'POST',
          body: JSON.stringify({ shipping_address: address, phone, delivery_zone: zoneSelect.value, ...gp })
        });
        btn.textContent = 'Waiting for payment...';
        PaystackPop.setup({
          key: paystackKey,
          email: el('cust_email').value.trim(),
          amount: data.total * 100,
          currency: 'GHS',
          ref: data.reference,
          onClose: function () {
            // Paystack Inline v1 rejects async functions as onClose (its
            // isFunction() check tests for "[object Function]" exactly). Keep
            // this a normal function and run the async verification inside.
            (async function () {
              // Ask the server to verify and close out this attempt. If the
              // customer actually paid before closing the iframe, take them to
              // the success page; otherwise the pending order's stock is
              // released instead of being held indefinitely.
              let verified = null;
              try {
                verified = await fetchJSON(B + '/api/payments/verify?reference=' + encodeURIComponent(data.reference));
              } catch (e) {}
              if (verified && verified.success) {
                window.location.href = B + '/payment/verify?reference=' + encodeURIComponent(data.reference);
                return;
              }
              restoreButton();
              showMsg('Payment cancelled. No charge was made and your cart is still saved.', 'error');
            })();
          },
          callback: function (r) { window.location.href = B + '/payment/verify?reference=' + r.reference; }
        }).openIframe();
      } catch (err) {
        showMsg(friendlyError(err.message), 'error');
        restoreButton();
      }
    }
  });

  /* ---------- confirmation ---------- */
  function showConfirmation(o) {
    el('checkout-root').querySelector('.checkout-layout').hidden = true;
    const box = el('confirm-box');
    box.hidden = false;
    box.innerHTML = '<div class="form-card confirm-card">' +
      '<div class="status-icon success-icon">&#10003;</div>' +
      '<h2>Order Placed Successfully</h2>' +
      '<div class="confirm-details">' +
      '<div class="confirm-row"><span>Order Number</span><strong>#' + o.orderId + '</strong></div>' +
      '<div class="confirm-row"><span>Payment</span><strong>' + o.paymentLabel + '</strong></div>' +
      '<div class="confirm-row"><span>' + (o.zoneLabel.indexOf('Pick Up') === 0 ? 'Pickup' : 'Delivery') + '</span><strong>' + escapeHtml(o.zoneLabel.replace(/^(Delivery|Pick Up): /, '')) + '</strong></div>' +
      '<div class="confirm-row"><span>Total</span><strong>' + formatPrice(o.total) + '</strong></div>' +
      '</div>' +
      (isGuest
        ? '<p class="text-muted confirm-note">Save your order number to track your order. We will contact you shortly.</p>'
        : '<p class="text-muted confirm-note">We will contact you shortly to confirm your order.</p>') +
      '<div class="status-actions">' +
      '<a href="' + B + '/thank-you" class="btn btn-outline">What Happens Next</a>' +
      (isGuest ? '<a href="' + B + '/track-order" class="btn btn-primary">Track Order</a>' : '<a href="' + B + '/orders" class="btn btn-primary">View My Orders</a>') +
      (isGuest ? ' <a href="' + waConfirmHref(o) + '" target="_blank" rel="noopener" class="btn btn-outline">Confirm on WhatsApp</a>' : '') +
      ' <a href="' + B + '/products" class="btn btn-outline">Continue Shopping</a>' +
      '</div></div>';
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* Pre-typed WhatsApp confirmation for guests. Built from the cart we
     already loaded (the place-order API only returns the order id). */
  function waConfirmHref(o) {
    const waItems = cartItems.map(i => i.quantity + 'x ' + i.name + ' - ' + formatPrice(i.price * i.quantity)).join('\n');
    const waText = 'Hello! I just placed order #' + o.orderId + ' on Handel Luxe GH.\n\nName: ' + el('cust_name').value.trim() +
      '\n\nItems:\n' + waItems + '\n' + o.zoneLabel + '\nTotal: ' + formatPrice(o.total);
    return 'https://wa.me/233542332244?text=' + encodeURIComponent(waText);
  }
})();
</script>
