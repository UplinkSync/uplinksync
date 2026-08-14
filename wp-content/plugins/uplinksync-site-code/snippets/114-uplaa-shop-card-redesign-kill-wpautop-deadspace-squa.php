<?php
/**
 * UPLAA /shop/ card redesign — kill wpautop deadspace, square hero image, compact footer (2026-07-31)
 *
 * Migrated from database-resident Code Snippets row id=114 (DR-004 tranche 1).
 * scope: front-end   priority: 105
 *
 * Owner-requested /shop/ product-card redesign (2026-07-31). DIAGNOSIS: each 471px card wasted ~104px to dead whitespace — an empty wpautop <p> between image and info (36px margin), the addbtn's wrapping <p> (36px margin), and .pinfo flex:1 stretch (~32px slack); image was only 44% of card height; des
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', function () {
	echo '<style id="uplaa-shop-card-redesign-20260731">
/* UPLAA /shop/ card redesign — scoped to .uls-shop. Reversible: deactivate this snippet. */
/* Grid: tighter + fuller; mobile becomes a 2-up gallery (was 1 oversized column) */
.uls-shop .pgrid{gap:14px!important}
@media (max-width:600px){.uls-shop .pgrid{grid-template-columns:1fr 1fr!important;gap:12px!important}}
/* Card frame + hover lift */
.uls-shop .prod{border:1px solid #e4eaf1!important;border-radius:12px!important;box-shadow:0 1px 3px rgba(16,42,76,.06)!important;transition:transform .18s ease,box-shadow .22s ease,border-color .18s ease!important}
.uls-shop .prod:hover{transform:translateY(-3px)!important;box-shadow:0 12px 26px rgba(16,42,76,.15)!important;border-color:#95D5DD!important}
/* Image: full square thumb (no crop), more visual weight, gentle zoom on hover */
.uls-shop .prod .pimg{aspect-ratio:1/1!important;display:block!important;border-bottom:1px solid #eef2f6!important}
.uls-shop .prod .pimg img{width:100%!important;height:100%!important;object-fit:cover!important;transition:transform .4s ease!important}
.uls-shop .prod:hover .pimg img{transform:scale(1.055)!important}
/* Kill wpautop dead space: empty <p> above info + paragraph margins on both stray <p>; and the phantom <br> line inside .pinfo */
.uls-shop .prod>p{margin:0!important}
.uls-shop .prod>p:empty{display:none!important}
.uls-shop .prod .pinfo br{display:none!important}
/* Info: tight, no flex stretch; reserve 2 title lines so price/button align across cards */
.uls-shop .prod .pinfo{flex:0 0 auto!important;padding:11px 13px 4px!important;gap:5px!important}
.uls-shop .prod .ptitle{font-size:13.5px!important;line-height:1.32!important;font-weight:600!important;display:-webkit-box!important;-webkit-line-clamp:2!important;-webkit-box-orient:vertical!important;overflow:hidden!important;min-height:2.64em!important}
.uls-shop .prod .ptitle a{color:#173258!important;text-decoration:none!important;transition:color .15s ease!important}
.uls-shop .prod:hover .ptitle a{color:#2F6FC4!important}
.uls-shop .prod .pprice{font-size:15.5px!important;font-weight:700!important;color:#2F6FC4!important;line-height:1.2!important}
/* Footer button: pinned to bottom, compact 40px pill, high-contrast (was low-contrast navy-on-navy) */
.uls-shop .prod>p:last-of-type{margin-top:auto!important;padding:0!important}
.uls-shop .prod .addbtn{display:block!important;margin:9px 13px 13px!important;min-height:40px!important;height:40px!important;line-height:40px!important;padding:0 12px!important;border-radius:9px!important;background:#173258!important;border:1px solid #173258!important;color:#fff!important;font-size:12.5px!important;font-weight:600!important;text-align:center!important;text-decoration:none!important;transition:background .16s ease,border-color .16s ease!important}
.uls-shop .prod .addbtn:hover,.uls-shop .prod .addbtn:focus{background:#2F6FC4!important;border-color:#2F6FC4!important;color:#fff!important}
/* Mobile: >=44px tap target; let long product names use up to 3 lines (no forced reserve) */
@media (max-width:600px){.uls-shop .prod .addbtn{min-height:44px!important;height:44px!important;line-height:44px!important}.uls-shop .prod .ptitle{-webkit-line-clamp:3!important;min-height:0!important}}
</style>';
}, 105 );
