<?php
/**
 * Shared first-paint loader and page-to-page progress bar.
 *
 * Included once from includes/header.php, immediately after <body>. The overlay
 * markup is present in the HTML so there is never an uncovered first paint.
 *
 *   PageLoader.show()   Show the overlay for a slow action (AJAX request or
 *                       navigation started in JavaScript). Do not use it for
 *                       ordinary form posts: those use the site's button
 *                       loading states instead.
 *   PageLoader.hide()   Hide the overlay. Call it after the action, including
 *                       in error paths, so nobody is left behind it.
 *
 *   PageLoader.barStart()  Start the 2px accent bar (used by the footer link
 *                          handler); barDone() finishes and fades it.
 *
 * The overlay appears on the first page view of a browser session only, fades
 * in the logo, then waits for window load (minimum 400ms) before fading out.
 * Later visits in the same session skip it. Page-to-page links never show the
 * overlay: they use only the top bar.
 */

require_once __DIR__ . '/../config/app.php';

$loaderConfig = require __DIR__ . '/../config/loader.php';
$loaderEsc = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$loaderLogo = $loaderEsc(base_url($loaderConfig['logo']));
$loaderAlt  = $loaderEsc($loaderConfig['shop_name']);
?>
<style>
  .hl-loader {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--background);
    opacity: 1;
    visibility: visible;
    transition: opacity 300ms ease-out, visibility 0s linear;
  }
  .hl-loader--done {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 300ms ease-out, visibility 0s linear 300ms;
  }
  .hl-loader--instant { transition: none; }
  .hl-loader__mark {
    display: block;
    width: 56px;
    height: 56px;
    object-fit: contain;
    animation: hl-loader-mark-in 300ms ease-out both;
  }
  @keyframes hl-loader-mark-in {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  .hl-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    z-index: 10000;
    opacity: 0;
    pointer-events: none;
    transition: opacity 300ms ease-out;
  }
  .hl-bar.is-active { opacity: 1; }
  .hl-bar span {
    display: block;
    width: 0;
    height: 100%;
    background: var(--accent);
    transition: width 300ms ease-out;
  }
  @media (prefers-reduced-motion: reduce) {
    .hl-loader,
    .hl-bar,
    .hl-bar span { transition: none; }
    .hl-loader__mark { animation: none; opacity: 1; }
  }
</style>
<div class="hl-loader" id="hlLoader" role="status" aria-label="Loading" data-min="<?= $loaderEsc($loaderConfig['min_display_ms']) ?>">
  <img class="hl-loader__mark" src="<?= $loaderLogo ?>" alt="<?= $loaderAlt ?>" width="56" height="56" decoding="async">
</div>
<noscript><style>.hl-loader { display: none !important; }</style></noscript>
<div class="hl-bar" id="hlBar" aria-hidden="true"><span></span></div>
<script>
(function () {
  var loader = document.getElementById('hlLoader');
  if (!loader) return;
  var min = parseInt(loader.getAttribute('data-min'), 10) || 400;
  var start = Date.now();
  var timer = null;
  var seen = false;
  try {
    seen = sessionStorage.getItem('hl-loader-seen') === '1';
    if (!seen) sessionStorage.setItem('hl-loader-seen', '1');
  } catch (e) {}
  function hide(instant) {
    if (timer) clearTimeout(timer);
    loader.classList.toggle('hl-loader--instant', !!instant);
    loader.classList.add('hl-loader--done');
    loader.setAttribute('aria-hidden', 'true');
  }
  if (seen) {
    hide(true);
  } else {
    var complete = function () {
      var wait = Math.max(0, min - (Date.now() - start));
      timer = setTimeout(function () { hide(false); }, wait);
    };
    if (document.readyState === 'complete') complete();
    else window.addEventListener('load', complete, { once: true });
  }
  window.PageLoader = {
    show: function () {
      if (timer) clearTimeout(timer);
      loader.classList.remove('hl-loader--done', 'hl-loader--instant');
      loader.removeAttribute('aria-hidden');
    },
    hide: function () { hide(true); }
  };
})();
</script>


