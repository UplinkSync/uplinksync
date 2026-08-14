<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 84
 * name  : UPLAA-337 Unified quote modal — wraps [uls_quote_flow] pop-up + repoints all 3 quote/estimate triggers
 * scope : global
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

/* UPLAA-337 Unified quote modal — wraps the guided flow [uls_quote_flow] in a
 * sitewide accessible POP-UP and repoints the three quote/estimate entry points
 * at it:
 *   (a) header nav "Request a Quote"        -> a[href*="request-quote"]
 *   (b) homepage "Get a fast estimate" CTAs -> .uls-consult-trigger/[data-uls-book-open] whose label says "estimate"
 *   (c) services "Estimate your project"    -> a[href*="#uls-estimator"] / .uls-est-trigger / .est-trigger
 *
 * All three now open ONE modal containing Snippet #82's 3-step guided flow
 * (multi-select -> UAV instant ballpark / IT scope -> cal.com inline embed).
 *
 * Retires the old estimate-book.js range modal by REPOINTING its triggers (not
 * dequeuing): the click handler runs in the CAPTURE phase and calls
 * stopImmediatePropagation(), so it fires BEFORE estimate-book.js's own anchor
 * listeners and BEFORE the booking-CTAs mu-plugin's [data-uls-book-open] chooser
 * handler — the old range modal / consult chooser never open for these triggers.
 *
 * Additive + fully reversible: deactivate this snippet to restore prior behaviour
 * (estimate-book.js range modal on /services/, IT-vs-UAV consult chooser on the
 * homepage "Get a fast estimate" CTA). No page, theme, or other snippet is edited.
 *
 * The /request-quote/ page (id 997) still renders [uls_quote_flow] INLINE as the
 * no-JS fallback; this snippet skips that page so there is never a duplicate
 * #uls-qf. The header link keeps its real href, so with JS off it navigates to
 * that page as before.
 */
add_action( 'wp_footer', function () {
	if ( is_admin() ) { return; }
	// /request-quote/ renders the flow inline as the no-JS fallback — do not
	// inject a second copy of #uls-qf there.
	if ( is_page( 997 ) || is_page( 'request-quote' ) ) { return; }
	if ( ! shortcode_exists( 'uls_quote_flow' ) ) { return; }
	$flow = do_shortcode( '[uls_quote_flow]' );
	?>
<div class="uls-qmodal" id="uls-qmodal" hidden>
  <div class="uls-qmodal__ov" data-uqm-close="1"></div>
  <div class="uls-qmodal__dlg" role="dialog" aria-modal="true" aria-label="Request a quote" tabindex="-1">
    <button type="button" class="uls-qmodal__x" data-uqm-close="1" aria-label="Close">&#215;</button>
    <div class="uls-qmodal__scroll"><?php echo $flow; ?></div>
  </div>
</div>
<style id="uls-qmodal-css">
.uls-qmodal[hidden]{display:none}
.uls-qmodal{position:fixed;inset:0;z-index:100001;display:flex;align-items:flex-start;justify-content:center;padding:24px 16px}
.uls-qmodal__ov{position:absolute;inset:0;background:rgba(6,15,32,.74)}
.uls-qmodal__dlg{position:relative;width:100%;max-width:772px;max-height:calc(100vh - 48px);display:flex;flex-direction:column;filter:drop-shadow(0 24px 60px rgba(0,0,0,.5))}
.uls-qmodal__scroll{overflow-y:auto;-webkit-overflow-scrolling:touch;max-height:calc(100vh - 48px);border-radius:16px;padding:2px}
.uls-qmodal__x{position:absolute;top:8px;right:8px;z-index:3;width:34px;height:34px;border-radius:50%;border:0;background:#0F2440;color:#fff;font-size:21px;line-height:1;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.4)}
.uls-qmodal__x:hover,.uls-qmodal__x:focus-visible{background:#1F7A8C;outline:2px solid #fff;outline-offset:2px}
body.uls-qmodal-open{overflow:hidden}
@media (max-width:560px){.uls-qmodal{padding:10px 6px}.uls-qmodal__x{top:3px;right:3px}}
</style>
<script id="uls-qmodal-js">
(function(){
  "use strict";
  var modal = document.getElementById("uls-qmodal");
  if (!modal) { return; }
  var dlg    = modal.querySelector(".uls-qmodal__dlg");
  var scroll = modal.querySelector(".uls-qmodal__scroll");
  var lastFocus = null;

  function foci(){
    return Array.prototype.slice.call(
      dlg.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')
    ).filter(function(el){ return el.offsetWidth || el.offsetHeight || el.getClientRects().length; });
  }
  function openM(trigger){
    lastFocus = trigger || document.activeElement;
    modal.hidden = false;
    document.body.classList.add("uls-qmodal-open");
    if (scroll) { scroll.scrollTop = 0; }
    var f = foci();
    (f[0] || dlg).focus();
  }
  function closeM(){
    if (modal.hidden) { return; }
    modal.hidden = true;
    document.body.classList.remove("uls-qmodal-open");
    if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch(e){} }
  }

  /* Which elements are quote/estimate entry points (and which are NOT). The
     booking-only CTAs — "Book a consultation" / "Book a free consultation" —
     carry no "estimate" text and are deliberately left to their own handler. */
  function match(t){
    if (!t || !t.closest) { return null; }
    var el = t.closest('a[href*="request-quote"],a[href*="#uls-estimator"],.est-trigger,.uls-est-trigger,.uls-consult-trigger,[data-uls-book-open]');
    if (!el) { return null; }
    var href = (el.getAttribute && el.getAttribute("href")) || "";
    if (href.indexOf("request-quote") !== -1 || href.indexOf("#uls-estimator") !== -1) { return el; }
    if (el.classList && (el.classList.contains("est-trigger") || el.classList.contains("uls-est-trigger"))) { return el; }
    if ((el.textContent || "").toLowerCase().indexOf("estimate") !== -1) { return el; }
    return null;
  }

  /* Capture phase + stopImmediatePropagation => we intercept BEFORE
     estimate-book.js and the booking-CTAs chooser ever see the click. */
  document.addEventListener("click", function(e){
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) { return; }
    var el = match(e.target);
    if (!el) { return; }
    e.preventDefault();
    if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
    openM(el);
  }, true);

  modal.addEventListener("click", function(e){
    if (e.target.closest && e.target.closest("[data-uqm-close]")) { e.preventDefault(); closeM(); }
  });
  document.addEventListener("keydown", function(e){
    if (modal.hidden) { return; }
    if (e.key === "Escape" || e.keyCode === 27) { e.preventDefault(); closeM(); return; }
    if (e.key === "Tab" || e.keyCode === 9) {
      var f = foci(); if (!f.length) { e.preventDefault(); return; }
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      else if (!dlg.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
    }
  });
})();
</script>
	<?php
}, 30 );
