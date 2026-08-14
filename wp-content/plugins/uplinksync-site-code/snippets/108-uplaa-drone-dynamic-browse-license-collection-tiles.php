<?php
/**
 * UPLAA Drone: dynamic browse-&-license collection tiles
 *
 * Migrated from database-resident Code Snippets row id=108 (DR-004 tranche 2).
 * scope: front-end   priority: 11
 *
 * Shortcode [uls_browse_collections] — one tile+chip per Aerial Photography child category (dynamic; new collections auto-appear). Clean masters via UPLAA-247 map. Links to /product-category/<slug>/. Display-only, reversible by deactivating.
 *
 * Migrated VERBATIM. Any behaviour change to this snippet is a separate commit, so
 * that the migration itself can be proven byte-identical in rendered output.
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

/**
 * UPLAA — /drone-services/ "The work — browse & license" (DYNAMIC).
 *
 * Renders one tile + one filter chip per Aerial-Photography COLLECTION at request
 * time, so new/renamed product categories (e.g. Saratoga Springs, or any future
 * location) appear automatically with no template edit. Collections = the child
 * product_cat terms of the "aerial-photography" parent (the same terms that back
 * /prints/). Each tile: a CLEAN master thumbnail (reuses the UPLAA-247
 * preview->master map when active so no watermark), the location label, and a
 * link to that collection's /product-category/<slug>/. No prices. Display-only.
 *
 * Shortcode: [uls_browse_collections]  (optional: parent="aerial-photography")
 * Reversible: deactivate this snippet + restore the template shortcode block.
 */
if ( ! function_exists( 'uls_browse_collections_render' ) ) {

	function uls_browse_collections_pick_clean_image( $slug ) {
		// Prefer a product whose featured image has a known clean master.
		$map = function_exists( 'uplaa247_preview_to_master' ) ? uplaa247_preview_to_master() : array();
		if ( ! function_exists( 'wc_get_products' ) ) {
			return '';
		}
		$products = wc_get_products( array(
			'category' => array( $slug ),
			'status'   => 'publish',
			'limit'    => 12,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'return'   => 'objects',
		) );
		$fallback_preview = 0;
		foreach ( $products as $p ) {
			$pid = (int) $p->get_image_id();
			if ( ! $pid ) { continue; }
			if ( isset( $map[ $pid ] ) ) {
				$url = wp_get_attachment_image_url( (int) $map[ $pid ], 'medium_large' );
				if ( $url ) { return $url; } // clean master, correct 16:9 framing
			}
			if ( ! $fallback_preview ) { $fallback_preview = $pid; }
		}
		// Fallback: a small size is force-cleaned by the UPLAA-247 filter; else raw.
		if ( $fallback_preview ) {
			$url = wp_get_attachment_image_url( $fallback_preview, 'woocommerce_thumbnail' );
			if ( $url ) { return $url; }
		}
		return '';
	}

	function uls_browse_collections_render( $atts ) {
		$atts = shortcode_atts( array(
			'parent'  => 'aerial-photography',
			'orderby' => 'count',
			'order'   => 'DESC',
		), $atts, 'uls_browse_collections' );

		$parent = get_term_by( 'slug', $atts['parent'], 'product_cat' );
		$terms  = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => $parent ? (int) $parent->term_id : 0,
			'orderby'    => $atts['orderby'],
			'order'      => $atts['order'],
		) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$chips = '<button type="button" class="ulsbz-chip is-on" aria-pressed="true" data-filter="all">All</button>';
		$tiles = '';
		$n     = 0;
		foreach ( $terms as $t ) {
			$link = get_term_link( $t );
			if ( is_wp_error( $link ) ) { continue; }
			$img = uls_browse_collections_pick_clean_image( $t->slug );
			if ( ! $img ) { continue; }
			$label = esc_html( $t->name );
			$slug  = esc_attr( $t->slug );
			$chips .= '<button type="button" class="ulsbz-chip" aria-pressed="false" data-filter="' . $slug . '">' . $label . '</button>';
			$tiles .= '<a class="ulsbz-card" href="' . esc_url( $link ) . '" data-loc="' . $slug . '">'
				. '<img class="ulsbz-img" src="' . esc_url( $img ) . '" width="768" height="432" loading="lazy" decoding="async" alt="Aerial view — ' . esc_attr( $t->name ) . ', by UplinkSync." />'
				. '<span class="ulsbz-meta"><span class="ulsbz-place">' . $label . '</span>'
				. '<span class="ulsbz-open">View collection<span aria-hidden="true"> &rarr;</span></span></span>'
				. '</a>';
			$n++;
		}
		if ( ! $n ) { return ''; }

		$css = <<<'CSS'
<style id="uls-browse-css">
.ulsbz{margin-top:var(--wp--preset--spacing--40,32px)}
.ulsbz-filters{display:flex;flex-wrap:wrap;gap:10px;margin:0 0 24px}
.ulsbz-chip{font:600 14px/1 inherit;color:#173258;background:#fff;border:1px solid #E3E8ED;border-radius:999px;padding:10px 18px;cursor:pointer;transition:background .15s,border-color .15s,color .15s}
.ulsbz-chip:hover{border-color:#5697F3;color:#173258}
.ulsbz-chip.is-on{background:#5697F3;border-color:#5697F3;color:#fff}
.ulsbz-chip:focus-visible{outline:2px solid #5697F3;outline-offset:2px}
.ulsbz-count{font-size:13px;color:#5B6672;margin:0 0 18px}
.ulsbz-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.ulsbz-card{position:relative;display:block;border-radius:10px;overflow:hidden;border:1px solid #E3E8ED;background:#0b1b33;line-height:0;text-decoration:none;box-shadow:0 1px 2px rgba(23,50,88,.06)}
.ulsbz-card:focus-visible{outline:2px solid #5697F3;outline-offset:2px}
.ulsbz-img{display:block;width:100%;height:auto;aspect-ratio:16/9;object-fit:cover;transition:transform .35s ease}
.ulsbz-card:hover .ulsbz-img{transform:scale(1.035)}
.ulsbz-meta{position:absolute;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:22px 12px 10px;line-height:1.2;background:linear-gradient(180deg,rgba(10,20,40,0) 0%,rgba(10,20,40,.72) 70%,rgba(10,20,40,.86) 100%)}
.ulsbz-place{color:#fff;font:600 13px/1.2 inherit;text-shadow:0 1px 3px rgba(0,0,0,.45)}
.ulsbz-open{color:#fff;font:600 12.5px/1.2 inherit;background:#5697F3;border-radius:6px;padding:5px 9px;opacity:0;transform:translateY(4px);transition:opacity .18s,transform .18s;white-space:nowrap}
.ulsbz-card:hover .ulsbz-open,.ulsbz-card:focus-visible .ulsbz-open{opacity:1;transform:none}
.ulsbz-card[hidden]{display:none}
@media(max-width:900px){.ulsbz-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:560px){.ulsbz-grid{grid-template-columns:1fr}.ulsbz-open{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){.ulsbz-img,.ulsbz-open{transition:none}.ulsbz-card:hover .ulsbz-img{transform:none}}
</style>
CSS;
		$js = <<<'JS'
<script>
(function(){
  var root=document.getElementById('uls-browse');
  if(!root||root.dataset.ulsbzInit)return;root.dataset.ulsbzInit='1';
  var chips=root.querySelectorAll('.ulsbz-chip');
  var cards=root.querySelectorAll('.ulsbz-card');
  var count=root.querySelector('[data-ulsbz-count]');
  var total=cards.length;
  function apply(f){
    var shown=0,lbl='';
    cards.forEach(function(c){var m=(f==='all'||c.getAttribute('data-loc')===f);c.hidden=!m;if(m)shown++;});
    chips.forEach(function(ch){var on=ch.getAttribute('data-filter')===f;ch.classList.toggle('is-on',on);ch.setAttribute('aria-pressed',on?'true':'false');if(on&&f!=='all')lbl=ch.textContent;});
    if(count){count.textContent=(f==='all')?('Showing all '+total+' collections'):('Showing '+lbl);}
  }
  chips.forEach(function(ch){ch.addEventListener('click',function(){apply(ch.getAttribute('data-filter'));});});
})();
</script>
JS;

		$html  = $css;
		$html .= '<div class="ulsbz" id="uls-browse">';
		$html .= '<div class="ulsbz-filters" role="group" aria-label="Filter aerials by location">' . $chips . '</div>';
		$html .= '<p class="ulsbz-count" aria-live="polite" data-ulsbz-count>Showing all ' . $n . ' collections</p>';
		$html .= '<div class="ulsbz-grid" data-ulsbz-grid>' . $tiles . '</div>';
		$html .= '</div>';
		$html .= $js;
		return $html;
	}

	add_shortcode( 'uls_browse_collections', 'uls_browse_collections_render' );
}
