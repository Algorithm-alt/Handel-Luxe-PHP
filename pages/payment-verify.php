<?php $pageTitle = 'Payment Verification';
$metaDescription = 'Verifying your Paystack payment for your Handel Luxe GH order.'; ?>
<?php require __DIR__ . '/../includes/header.php'; ?>
<div class="container">
    <div id="status-box" class="status-box">
      <div id="spinner" class="status-spinner">Verifying your payment...</div>
      <div id="result" class="status-result"></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<script>
(async function() {
  const B = typeof window !== 'undefined' ? (window.APP_BASE_URL || '') : '';
  let isGuest = true;
  try { const u = await fetchJSON(B + '/api/auth/me'); setAuthLinks(u); isGuest = false; } catch(e) { setAuthLinks(null); }
  const params = new URLSearchParams(window.location.search);
  const reference = params.get('reference');
  const resultDiv = document.getElementById('result');
  const spinner = document.getElementById('spinner');

  if (!reference) {
    spinner.innerHTML = 'No payment reference found.';
    return;
  }

  try {
    const data = await fetchJSON(B + '/api/payments/verify?reference=' + encodeURIComponent(reference));
    spinner.style.display = 'none';
    resultDiv.style.display = 'block';

    if (data.success) {
      resultDiv.innerHTML = `
        <div class="status-icon success-icon">&#10003;</div>
        <h2 class="text-success">Payment Successful!</h2>
        <p class="text-muted">Your order has been confirmed and is being processed.</p>
        ${isGuest && data.order_id ? `<p>Your order number is <strong>#${data.order_id}</strong>. Keep it to track your order.</p>` : ''}
        <div class="status-actions">
          <a href="${B}/thank-you" class="btn btn-outline">What Happens Next</a>
          ${isGuest ? '<a href="' + B + '/track-order" class="btn btn-primary">Track My Order</a> <a href="' + B + '/products" class="btn btn-outline">Continue Shopping</a>' : ''}
          ${!isGuest ? '<a href="' + B + '/orders" class="btn btn-primary">View My Orders</a> <a href="' + B + '/products" class="btn btn-outline">Continue Shopping</a>' : ''}
        </div>`;
    } else {
      resultDiv.innerHTML = `
        <div class="status-icon error-icon">&#10007;</div>
        <h2 class="text-danger">Payment ${escapeHtml(data.status || 'Failed')}</h2>
        <p class="text-muted">Your payment was not successful. Please try again.</p>
        <a href="${B}/checkout" class="btn btn-primary">Try Again</a>`;
    }
  } catch (e) {
    spinner.innerHTML = `<p class="text-danger">Could not verify payment. Please check your orders page or contact support.</p>
      <a href="${B}/orders" class="btn btn-primary btn-back-margin">View My Orders</a>`;
  }
})();
</script>
