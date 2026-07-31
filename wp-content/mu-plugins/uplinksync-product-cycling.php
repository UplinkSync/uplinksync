<?php
/**
 * Plugin Name: UplinkSync — Single-Product Collection Cycling (store polish 2026-07-30, fix #3)
 * Description: Adds tasteful Previous / Next "print" navigation on single-product pages so a visitor can browse image-to-image within the SAME location collection (product_cat) without returning to the grid. Ordering mirrors the collection archive (menu_order ASC, title ASC). Renders on woocommerce_after_single_product_summary (before the Description tabs). On-brand: navy card, accent-teal hover, thumbnail + label. Fully reversible: remove this mu-plugin (overwrite with an inert stub — rsync deploy does not delete) or revert the MR.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pick the location collection term for a product — the child location cat
 * (e.g. "Palisades Reservoir"), not the top-level "Aerial Photography" parent.
 */
function uplinksync_pcycle_pick_term( $product_id ) {
	$terms = get_the_terms( $product_id, 'product_cat' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}
	// Prefer a child term (parent !== 0); else the first non-"aerial-photography"; else the first.
	$fallback = null;
	foreach ( $terms as $t ) {
		if ( null === $fallback && 'aerial-photography' !== $t->slug ) {
			$fallback = $t;
		}
		if ( (int) $t->parent !== 0 ) {
			return $t;
		}
	}
	return $fallback ? $fallback : $terms[0];
}

add_action( 'woocommerce_after_single_product_summary', function () {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	global $product;
	if ( ! $product instanceof WC_Product ) {
		$product = wc_get_product( get_the_ID() );
	}
	if ( ! $product ) {
		return;
	}
	$current_id = $product->get_id();
	$term       = uplinksync_pcycle_pick_term( $current_id );
	if ( ! $term ) {
		return;
	}

	// Same ordering as the collection archive.
	$q = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
		'tax_query'      => array( array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $term->term_id,
		) ),
	) );
	$ids = $q->posts;
	wp_reset_postdata();

	$total = count( $ids );
	if ( $total < 2 ) {
		return; // nothing to cycle through
	}
	$idx = array_search( $current_id, $ids, true );
	if ( false === $idx ) {
		return;
	}

	$prev_id = ( $idx > 0 ) ? $ids[ $idx - 1 ] : null;              // no wrap at the ends
	$next_id = ( $idx < $total - 1 ) ? $ids[ $idx + 1 ] : null;

	$render_link = function ( $pid, $dir ) {
		$pr = $pid ? wc_get_product( $pid ) : null;
		$is_prev = ( 'prev' === $dir );
		$eyebrow = $is_prev ? 'Previous print' : 'Next print';
		if ( ! $pr ) {
			// Disabled placeholder keeps the two-column rhythm at the ends.
			return '<span class="uls-pcyc__item is-disabled" aria-hidden="true"><span class="uls-pcyc__eye">' . esc_html( $eyebrow ) . '</span></span>';
		}
		$thumb = has_post_thumbnail( $pid )
			? get_the_post_thumbnail( $pid, 'woocommerce_gallery_thumbnail', array( 'alt' => '', 'loading' => 'lazy' ) )
			: wc_placeholder_img( 'woocommerce_gallery_thumbnail' );
		$rel   = $is_prev ? 'prev' : 'next';
		return sprintf(
			'<a class="uls-pcyc__item uls-pcyc__%1$s" rel="%2$s" href="%3$s" aria-label="%4$s: %5$s"><span class="uls-pcyc__thumb">%6$s</span><span class="uls-pcyc__txt"><span class="uls-pcyc__eye">%7$s</span><span class="uls-pcyc__name">%5$s</span></span></a>',
			esc_attr( $dir ),
			esc_attr( $rel ),
			esc_url( get_permalink( $pid ) ),
			esc_attr( $eyebrow ),
			esc_html( $pr->get_name() ),
			$thumb, // already-escaped WP markup
			esc_html( $eyebrow )
		);
	};

	$collection_url = get_term_link( $term, 'product_cat' );
	$position       = ( $idx + 1 ) . ' of ' . $total;
	?>
	<style>
	.uls-pcyc{--navy:#173258;--navy-b:#2a4a72;--teal:#95D5DD;--muted:#5B6672;--line:#E3E8ED;max-width:1200px;margin:8px auto 0;padding:0 clamp(16px,4vw,24px)}
	.uls-pcyc__bar{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:12.5px;color:var(--muted);margin:0 2px 12px}
	.uls-pcyc__bar a{color:var(--navy);text-decoration:none;font-weight:600}
	.uls-pcyc__bar a:hover{text-decoration:underline}
	.uls-pcyc__grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
	.uls-pcyc__item{display:flex;align-items:center;gap:14px;padding:12px 14px;border:1px solid var(--line);border-radius:14px;background:#fff;text-decoration:none;color:var(--navy);box-shadow:0 2px 10px rgba(16,42,76,.06);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease}
	.uls-pcyc__item:hover{transform:translateY(-2px);box-shadow:0 12px 26px -10px rgba(16,42,76,.35);border-color:var(--teal)}
	.uls-pcyc__item:focus-visible{outline:3px solid var(--teal);outline-offset:2px}
	.uls-pcyc__next{flex-direction:row-reverse;text-align:right}
	.uls-pcyc__thumb{flex:0 0 auto;width:66px;height:66px;border-radius:10px;overflow:hidden;background:var(--navy);display:block}
	.uls-pcyc__thumb img{width:100%;height:100%;object-fit:cover;display:block}
	.uls-pcyc__txt{display:flex;flex-direction:column;gap:3px;min-width:0}
	.uls-pcyc__eye{font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}
	.uls-pcyc__item:hover .uls-pcyc__eye{color:var(--navy)}
	.uls-pcyc__name{font-size:14px;font-weight:600;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
	.uls-pcyc__eye::before{content:"\2190 \00a0"}
	.uls-pcyc__next .uls-pcyc__eye::before{content:""}
	.uls-pcyc__next .uls-pcyc__eye::after{content:"\00a0 \2192"}
	.uls-pcyc__item.is-disabled{visibility:hidden}
	@media(max-width:560px){.uls-pcyc__grid{grid-template-columns:1fr}.uls-pcyc__item.is-disabled{display:none}.uls-pcyc__next{flex-direction:row;text-align:left}.uls-pcyc__next .uls-pcyc__eye::after{content:""}.uls-pcyc__next .uls-pcyc__eye::before{content:"\2192 \00a0"}}
	@media(prefers-reduced-motion:reduce){.uls-pcyc__item{transition:none}}
	</style>
	<nav class="uls-pcyc" aria-label="Browse prints in this collection">
		<div class="uls-pcyc__bar">
			<span><?php echo esc_html( $term->name ); ?> · <?php echo esc_html( $position ); ?></span>
			<?php if ( ! is_wp_error( $collection_url ) && $collection_url ) : ?>
				<a href="<?php echo esc_url( $collection_url ); ?>">View all in this collection &rarr;</a>
			<?php endif; ?>
		</div>
		<div class="uls-pcyc__grid">
			<?php
			echo $render_link( $prev_id, 'prev' ); // phpcs:ignore
			echo $render_link( $next_id, 'next' ); // phpcs:ignore
			?>
		</div>
	</nav>
	<?php
}, 15 ); // after Description/Reviews tabs (10), before Related products (20)
