<?php
/**
 * Plugin Name: UplinkSync — Collection Archive Design + Admin Preview Bypass (***-178)
 * Description: Applies the owner-approved collection-detail design (photo hero + uniform product grid) to the location product_cat archives. ***-366 (storefront launch, owner authorization 2026-07-30): the public store is now LIVE — the shop-gate 301s are neutralized and WooCommerce Coming Soon is off, so these archives render publicly. The admin/preview helper (uplinksync_store_preview_allowed) and the pre_option Coming Soon mask have been retired; the design/template path is unchanged. Renders through the real FSE canvas + wp:template-part blocks so the themed (blue) header/footer + full block styles come through. Fully reversible: restore prior body from MR history.
 * Version: 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Admin (manage_options) or signed ?uls_preview token => may view the gated archives. */
function uplinksync_store_preview_allowed() {
	if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		return true;
	}
	$key = get_option( 'uls_collection_preview_key' );
	if ( $key && isset( $_GET['uls_preview'] ) && is_string( $_GET['uls_preview'] ) && hash_equals( (string) $key, (string) $_GET['uls_preview'] ) ) {
		return true;
	}
	return false;
}

add_action( 'init', function () {
	if ( ! get_option( 'uls_collection_preview_key' ) ) {
		update_option( 'uls_collection_preview_key', wp_generate_password( 24, false, false ), false );
	}
} );

/**
 * ***-366 (storefront launch, owner authorization 2026-07-30): the public
 * store is now live, so the WooCommerce Coming Soon masking filter that used to
 * force 'no' only for admin/preview requests has been removed — Coming Soon is
 * turned off in the DB for everyone. The shop-gate mu-plugin is neutralized, so
 * the preview-only lift below is now a harmless no-op kept for history.
 */
add_action( 'template_redirect', function () {
	if ( uplinksync_store_preview_allowed() ) {
		remove_action( 'template_redirect', 'uplinksync_shop_gate_redirects', 5 );
	}
}, 1 );

/**
 * Render the location archive through the canonical FSE path: inject a block
 * template (header part + our shortcode + footer part) into the current template
 * content and return the block template canvas. This makes WordPress emit the
 * real header/footer template parts (themed blue) and the full block-supports
 * style pipeline, identical to a normal FSE page.
 */
add_filter( 'template_include', function ( $template ) {
	if ( function_exists( 'is_product_category' ) && is_product_category() ) {
		global $_wp_current_template_content, $_wp_current_template_id;
		$_wp_current_template_content =
			// *** store polish (2026-07-30) fix #1: emit the header with the
			// "site-header" className exactly as single-product.html/page.html do.
			// Without it the header renders as a bare <header class="wp-block-template-part">
			// and the navy-header repaint (Code Snippet #85), which is scoped under
			// `.site-header`, misses it — so the collection/category header rendered
			// white. Adding the class makes the existing navy repaint apply here too.
			'<!-- wp:template-part {"slug":"header","tagName":"header","className":"site-header"} /-->' .
			'<!-- wp:shortcode -->[uls_collection_archive]<!-- /wp:shortcode -->' .
			'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		if ( empty( $_wp_current_template_id ) ) {
			$_wp_current_template_id = get_stylesheet() . '//taxonomy-product_cat';
		}
		return ABSPATH . WPINC . '/template-canvas.php';
	}
	return $template;
}, 99 );

/** The dynamic hero + uniform product grid, emitted as a shortcode so it renders inside the FSE template. */
add_shortcode( 'uls_collection_archive', function () {
	$term = get_queried_object();
	if ( ! $term || empty( $term->term_id ) ) {
		return '';
	}

	$slug    = isset( $term->slug ) ? $term->slug : '';
	$emap    = array( 'palisades-reservoir' => 'Mountainscape' );
	$eyebrow = isset( $emap[ $slug ] ) ? $emap[ $slug ] : 'Cityscape';
	$name    = $term->name;
	$blurb   = trim( wp_strip_all_tags( term_description( $term->term_id, 'product_cat' ) ) );

	$q = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'tax_query'      => array( array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $term->term_id,
		) ),
	) );

	$count    = (int) $q->post_count;
	$min      = null;
	$hero_img = '';
	foreach ( $q->posts as $p ) {
		$pr = wc_get_product( $p->ID );
		if ( ! $pr ) {
			continue;
		}
		$price = $pr->get_price();
		if ( '' !== $price && is_numeric( $price ) ) {
			$price = (float) $price;
			if ( null === $min || $price < $min ) {
				$min = $price;
			}
		}
		if ( '' === $hero_img && has_post_thumbnail( $p->ID ) ) {
			// ***-247 (2026-07-30): serve the CLEAN master for the hero, not the
			// watermarked preview. The product's featured image is the watermarked
			// preview attachment; Code Snippet #45 holds the preview->master map and
			// only swaps SMALL sizes, so the 'large' hero was still watermarked. Map
			// it to the master here (guarded — no-op if the snippet is deactivated,
			// which cleanly reverts to the previous watermarked-but-working hero).
			$hero_tid = get_post_thumbnail_id( $p->ID );
			if ( function_exists( '***247_preview_to_master' ) ) {
				$hero_map = ***247_preview_to_master();
				if ( isset( $hero_map[ $hero_tid ] ) ) {
					$hero_tid = $hero_map[ $hero_tid ];
				}
			}
			$hero_img = wp_get_attachment_image_url( $hero_tid, 'large' );
		}
	}
	if ( '' === $hero_img ) {
		$thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
		if ( $thumb_id ) {
			$hero_img = wp_get_attachment_image_url( $thumb_id, 'large' );
		}
	}
	$floor      = ( null !== $min ) ? '$' . rtrim( rtrim( number_format( $min, 2, '.', '' ), '0' ), '.' ) : '';
	$hero_bg    = 'linear-gradient(90deg,rgba(11,27,51,.86),rgba(11,27,51,.45))' . ( $hero_img ? ",url('" . esc_url( $hero_img ) . "')" : '' );
	$prints_url = home_url( '/shop/' ); // /prints/ retired as a destination (owner 2026-07-30); point "All collections" at the full catalog.

	ob_start();
	?>
	<style>
	.uls-carch{--bg:#f5f6fb;--panel:#fff;--ink:#0b1b33;--muted:#5a6685;--line:#e6eaf3;--navy:#0b1b33;--teal:#17b9ab;--accent:#3358e0;--disp:"Georgia","Iowan Old Style",serif;background:var(--bg);color:var(--ink)}
	.uls-carch .wrap{max-width:1080px;margin:0 auto;padding:28px 20px 64px}
	.uls-carch .note{font-size:11.5px;line-height:1.55;color:var(--muted);text-align:center;max-width:66ch;margin:34px auto 0;padding:0 6px;opacity:.9}
	.uls-carch .back{display:inline-block;font-size:12.5px;color:var(--accent);text-decoration:none;margin-bottom:12px}
	.uls-carch .back:hover{text-decoration:underline}
	.uls-carch .hero{border-radius:16px;padding:26px 30px;background-size:cover;background-position:center;color:#fff;box-shadow:0 8px 26px rgba(11,27,51,.2)}
	.uls-carch .h-eyebrow{font-family:ui-monospace,Menlo,monospace;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--teal)}
	.uls-carch .h-name{font-family:var(--disp);font-weight:600;font-size:clamp(26px,4vw,40px);margin:4px 0 6px;letter-spacing:-.01em;color:#fff}
	.uls-carch .h-blurb{margin:0;max-width:56ch;color:#dbe6fb}
	.uls-carch .h-meta{margin-top:11px;font-size:13px;color:#c3d1ef;display:flex;flex-wrap:wrap;gap:9px;align-items:center}
	.uls-carch .h-meta .d{opacity:.45}
	.uls-carch .toolbar{display:flex;justify-content:space-between;align-items:center;margin:18px 2px 14px;font-size:13px;color:var(--muted)}
	.uls-carch .pgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
	@media(max-width:820px){.uls-carch .pgrid{grid-template-columns:repeat(2,1fr)}}
	@media(max-width:500px){.uls-carch .pgrid{grid-template-columns:1fr}}
	.uls-carch .prod{background:var(--panel);border:1px solid var(--line);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 3px 12px rgba(11,27,51,.07);transition:transform .16s,box-shadow .16s}
	.uls-carch .prod:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(11,27,51,.16)}
	.uls-carch .pimg{aspect-ratio:4/3;overflow:hidden;background:#0b1b33;display:block}
	.uls-carch .pimg img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s}
	.uls-carch .prod:hover .pimg img{transform:scale(1.05)}
	.uls-carch .pinfo{padding:12px 14px 4px;display:flex;flex-direction:column;gap:3px;flex:1}
	.uls-carch .ptitle{font-size:13.5px;font-weight:600;line-height:1.3}
	.uls-carch .ptitle a{color:var(--ink);text-decoration:none}
	.uls-carch .pprice{font-family:var(--disp);font-size:17px;color:var(--accent)}
	.uls-carch .addbtn{margin:10px 14px 14px;padding:9px;border:1px solid var(--navy);background:var(--navy);color:#fff;border-radius:9px;font-weight:600;font-size:13px;cursor:pointer;text-align:center;text-decoration:none;display:block;transition:.15s}
	.uls-carch .addbtn:hover{background:transparent;color:var(--ink);border-color:var(--teal)}
	.uls-carch .addbtn:focus-visible{outline:3px solid var(--teal);outline-offset:2px}
	.uls-carch .empty{padding:40px;text-align:center;color:var(--muted)}
	@media(prefers-reduced-motion:reduce){.uls-carch *{transition:none!important}}
	</style>
	<div class="uls-carch"><div class="wrap">
		<a class="back" href="<?php echo esc_url( $prints_url ); ?>">&larr; All collections</a>
		<div class="hero" style="background-image:<?php echo esc_attr( $hero_bg ); ?>">
			<span class="h-eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
			<h1 class="h-name"><?php echo esc_html( $name ); ?></h1>
			<?php if ( $blurb ) : ?><p class="h-blurb"><?php echo esc_html( $blurb ); ?></p><?php endif; ?>
			<div class="h-meta">
				<span><?php echo esc_html( $count ); ?> prints</span>
				<?php if ( $floor ) : ?><span class="d">&middot;</span><span>from <?php echo esc_html( $floor ); ?></span><?php endif; ?>
				<span class="d">&middot;</span><span>Archival matte &amp; canvas</span>
			</div>
		</div>
		<div class="toolbar"><span class="shown">Showing <?php echo esc_html( $count ); ?> of <?php echo esc_html( $count ); ?> prints</span><span class="sort">Sort: Featured</span></div>
		<?php if ( $count ) : ?>
		<div class="pgrid">
			<?php foreach ( $q->posts as $p ) :
				$pr = wc_get_product( $p->ID );
				if ( ! $pr ) { continue; }
				$permalink = get_permalink( $p->ID );
				$img = has_post_thumbnail( $p->ID )
					? get_the_post_thumbnail( $p->ID, 'woocommerce_thumbnail', array( 'alt' => esc_attr( $pr->get_name() ), 'loading' => 'lazy' ) )
					: wc_placeholder_img( 'woocommerce_thumbnail' );
				$classes = 'addbtn';
				if ( $pr->is_purchasable() && $pr->is_in_stock() && ! $pr->is_type( 'variable' ) ) {
					$classes .= ' add_to_cart_button ajax_add_to_cart';
				}
				?>
				<div class="prod">
					<a class="pimg" href="<?php echo esc_url( $permalink ); ?>"><?php echo $img; // phpcs:ignore ?></a>
					<div class="pinfo">
						<span class="ptitle"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $pr->get_name() ); ?></a></span>
						<span class="pprice"><?php echo $pr->get_price_html(); // phpcs:ignore ?></span>
					</div>
					<a href="<?php echo esc_url( $pr->add_to_cart_url() ); ?>" data-product_id="<?php echo esc_attr( $pr->get_id() ); ?>" data-quantity="1" rel="nofollow" class="<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( $pr->add_to_cart_text() ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
		<?php else : ?>
			<div class="empty">No prints in this collection yet.</div>
		<?php endif; ?>
		<p class="note">Prices shown are &ldquo;from&rdquo; floors. Watermarked previews are displayed; the clean full-resolution master is delivered on purchase.</p>
	</div></div>
	<?php
	wp_reset_postdata();
	return ob_get_clean();
} );
