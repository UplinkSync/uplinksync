<?php
/**
 * Plugin Name: UplinkSync — Shop Catalog (sectioned full catalog) (***-247/store)
 * Description: Redesigns the main /shop/ page from the plain WooCommerce grid into the
 *   house-branded, curated catalog that matches /prints/ and the location collection
 *   archives: a navy hero header, a collection filter + sort toolbar, and the full
 *   catalog GROUPED INTO LOCATION SECTIONS using the same house product cards as the
 *   collection archive (uplinksync-collection-archive.php). Full catalog is kept — every
 *   published print renders on one page (behind /prints/), filter/sort are client-side.
 *   Small preview thumbnails are served clean by Code Snippet #45 (woocommerce_thumbnail
 *   swap); the single-product main image / zoom stay watermarked.
 *   Presentation only — no price, checkout, or gating change. Renders through the real
 *   FSE canvas + wp:template-part blocks so the navy header/footer + block styles apply,
 *   exactly like the collection archive. Fully reversible: overwrite this file with an
 *   inert stub (server rsync does not delete) or revert the MR.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Take over ONLY the main shop page (post-type archive) and render it through the
 * canonical FSE path: header part (with the "site-header" className so the navy
 * header repaint applies) + our [uls_shop_catalog] shortcode + footer part. This
 * mirrors uplinksync-collection-archive.php so /shop/ and the location archives are
 * pixel-consistent. Category archives (is_product_category) and search are untouched.
 */
add_filter( 'template_include', function ( $template ) {
	if ( ! function_exists( 'is_shop' ) ) {
		return $template;
	}
	if ( is_shop() && ! is_search() ) {
		global $_wp_current_template_content, $_wp_current_template_id;
		$_wp_current_template_content =
			'<!-- wp:template-part {"slug":"header","tagName":"header","className":"site-header"} /-->' .
			'<!-- wp:shortcode -->[uls_shop_catalog]<!-- /wp:shortcode -->' .
			'<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
		if ( empty( $_wp_current_template_id ) ) {
			$_wp_current_template_id = get_stylesheet() . '//archive-product';
		}
		return ABSPATH . WPINC . '/template-canvas.php';
	}
	return $template;
}, 98 );

/**
 * Format a price float as a compact "$NN" (drops trailing .00) for floors/meta.
 */
function uls_shop_money( $n ) {
	if ( null === $n || '' === $n || ! is_numeric( $n ) ) {
		return '';
	}
	return '$' . rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
}

/**
 * The sectioned catalog. Groups every published print by its LOCATION sub-category
 * (children of "Aerial Photography", id 22), renders a navy hero, a filter+sort
 * toolbar, and one house-card section per location. Filter/sort are client-side.
 */
add_shortcode( 'uls_shop_catalog', function () {

	// Location sub-categories, richest first (Palisades leads, then by count).
	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'child_of'   => 22,
	) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		// Fallback: any non-empty product_cat except the "uncategorized" root.
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
	}

	$emap = array( 'palisades-reservoir' => 'Mountainscapes' );

	// Assemble per-location groups.
	$groups      = array();
	$total       = 0;
	$global_min  = null;
	foreach ( (array) $terms as $t ) {
		if ( empty( $t->term_id ) || 22 === (int) $t->term_id ) {
			continue;
		}
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'tax_query'      => array( array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $t->term_id,
			) ),
			'no_found_rows'  => true,
		) );
		if ( ! $q->post_count ) {
			wp_reset_postdata();
			continue;
		}
		$min = null;
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
				if ( null === $global_min || $price < $global_min ) {
					$global_min = $price;
				}
			}
		}
		$groups[] = array(
			'term'    => $t,
			'posts'   => $q->posts,
			'count'   => (int) $q->post_count,
			'min'     => $min,
			'eyebrow' => isset( $emap[ $t->slug ] ) ? $emap[ $t->slug ] : 'Cityscapes',
		);
		$total += (int) $q->post_count;
		wp_reset_postdata();
	}

	// Richest collection first.
	usort( $groups, function ( $a, $b ) {
		return $b['count'] - $a['count'];
	} );

	$loc_count = count( $groups );
	$floor     = uls_shop_money( $global_min );

	ob_start();
	?>
	<style>
	.uls-shop{--bg:#f5f6fb;--panel:#fff;--ink:#173258;--muted:#5B6672;--line:#d6dde6;--navy:#173258;--navy800:#1c355c;--teal:#17b9ab;--accent:#2F6FC4;--accent-teal:#95D5DD;--uls-hdr:64px;background:var(--bg);color:var(--ink)}
	/* Sticky filter toolbar sits BELOW the sticky site-header (Snippet #100).
	   --uls-hdr = shrunk header height (~60px desktop / ~59px mobile) + a small
	   buffer; the gap shows the same --bg so it reads seamless. z-index stays
	   under the header's 999 so the header always wins if they ever meet. */
	@media(max-width:782px){.uls-shop{--uls-hdr:62px}}
	.uls-shop .wrap{max-width:1180px;margin:0 auto;padding:26px 20px 20px}
	.uls-shop a{color:inherit}
	/* Hero */
	.uls-shop .hero{border-radius:16px;padding:30px 34px;background:linear-gradient(135deg,var(--navy) 0%,#12385f 60%,var(--navy800) 100%);color:#fff;box-shadow:0 10px 30px rgba(16,42,76,.22)}
	.uls-shop .h-eyebrow{font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--accent-teal)}
	.uls-shop .h-name{font-weight:700;font-size:clamp(28px,4.4vw,44px);margin:6px 0 8px;letter-spacing:-.01em;line-height:1.08;color:#fff}
	.uls-shop .h-blurb{margin:0;max-width:60ch;color:#dbe6fb;font-size:15px;line-height:1.55}
	.uls-shop .h-meta{margin-top:13px;font-size:13px;color:#c3d1ef;display:flex;flex-wrap:wrap;gap:9px;align-items:center}
	.uls-shop .h-meta .d{opacity:.45}
	/* Toolbar: filter chips + sort */
	.uls-shop .toolbar{position:sticky;top:var(--uls-hdr);z-index:5;scroll-margin-top:calc(var(--uls-hdr) + 12px);display:flex;flex-wrap:wrap;gap:14px 18px;align-items:center;justify-content:space-between;margin:20px 2px 6px;padding:12px 4px;background:linear-gradient(var(--bg),var(--bg) 70%,rgba(245,246,251,0))}
	.uls-shop .chips{display:flex;flex-wrap:wrap;gap:8px}
	.uls-shop .chip{appearance:none;cursor:pointer;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:9px 14px;min-height:36px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink);transition:background .15s,border-color .15s,color .15s}
	.uls-shop .chip:hover{border-color:var(--accent)}
	.uls-shop .chip[aria-pressed="true"]{background:var(--navy);border-color:var(--navy);color:#fff}
	.uls-shop .chip .n{opacity:.6;font-weight:600;margin-left:5px}
	.uls-shop .chip[aria-pressed="true"] .n{opacity:.7}
	.uls-shop .chip:focus-visible{outline:3px solid var(--accent-teal);outline-offset:2px}
	.uls-shop .sortwrap{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)}
	.uls-shop .sortwrap select{font:inherit;font-size:13px;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:8px;padding:8px 30px 8px 12px;min-height:36px;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235B6672' stroke-width='1.6' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center}
	.uls-shop .sortwrap select:focus-visible{outline:3px solid var(--accent-teal);outline-offset:2px}
	/* Section */
	.uls-shop .coll{margin:22px 2px 6px;scroll-margin-top:calc(var(--uls-hdr) + 76px)}
	.uls-shop .coll.is-hidden{display:none}
	.uls-shop .coll-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:6px 12px;padding:14px 0 12px;border-bottom:1px solid var(--line);margin-bottom:18px}
	.uls-shop .coll-name{font-weight:700;font-size:clamp(20px,2.4vw,26px);letter-spacing:-.01em;color:var(--navy);margin:0}
	.uls-shop .coll-sub{font-size:12.5px;color:var(--muted)}
	.uls-shop .coll-sub .k{color:var(--accent);font-weight:600}
	.uls-shop .coll-sub .d{opacity:.4;margin:0 5px}
	.uls-shop .coll-view{margin-left:auto;font-size:13px;font-weight:600;color:var(--accent);text-decoration:none;white-space:nowrap}
	.uls-shop .coll-view:hover{text-decoration:underline}
	/* Grid + cards (matches collection archive .prod) */
	.uls-shop .pgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
	@media(max-width:1000px){.uls-shop .pgrid{grid-template-columns:repeat(3,1fr)}}
	@media(max-width:760px){.uls-shop .pgrid{grid-template-columns:repeat(2,1fr)}}
	@media(max-width:420px){.uls-shop .pgrid{grid-template-columns:1fr}}
	.uls-shop .prod{background:var(--panel);border:1px solid var(--line);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 3px 12px rgba(16,42,76,.06);transition:transform .16s,box-shadow .16s}
	.uls-shop .prod.is-hidden{display:none}
	.uls-shop .prod:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(16,42,76,.15)}
	.uls-shop .pimg{aspect-ratio:4/3;overflow:hidden;background:var(--navy);display:block}
	.uls-shop .pimg img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s}
	.uls-shop .prod:hover .pimg img{transform:scale(1.05)}
	.uls-shop .pinfo{padding:12px 14px 4px;display:flex;flex-direction:column;gap:3px;flex:1}
	.uls-shop .ptitle{font-size:13.5px;font-weight:600;line-height:1.3}
	.uls-shop .ptitle a{color:var(--ink);text-decoration:none}
	.uls-shop .ptitle a:hover{color:var(--accent)}
	.uls-shop .pprice{font-size:15px;font-weight:700;color:var(--accent)}
	.uls-shop .pprice .woocommerce-Price-amount{color:var(--accent)}
	.uls-shop .addbtn{margin:10px 14px 14px;padding:10px;min-height:44px;border:1px solid var(--navy);background:var(--navy);color:#fff;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;transition:background .15s,color .15s,border-color .15s}
	.uls-shop .addbtn:hover{background:#fff;color:var(--navy);border-color:var(--accent)}
	.uls-shop .addbtn:focus-visible{outline:3px solid var(--accent-teal);outline-offset:2px}
	.uls-shop .empty{padding:50px 20px;text-align:center;color:var(--muted)}
	.uls-shop .note{font-size:11.5px;line-height:1.55;color:var(--muted);text-align:center;max-width:70ch;margin:26px auto 4px;opacity:.9}
	/* CTA band */
	.uls-shop .cta{margin-top:30px;background:linear-gradient(180deg,var(--navy),var(--navy-700,#1F4375));color:#fff;text-align:center;padding:56px 20px}
	.uls-shop .cta h2{color:#fff;font-weight:700;font-size:clamp(24px,3.2vw,32px);margin:0 0 8px}
	.uls-shop .cta p{color:#dbe6fb;margin:0 auto 20px;max-width:56ch}
	.uls-shop .cta .cta-actions{display:flex;gap:14px;justify-content:center;align-items:center;flex-wrap:wrap;margin-top:4px}
	.uls-shop .cta .cta-actions p{margin:0}
	.uls-shop .cta .uls-cta-primary{display:inline-flex;align-items:center;justify-content:center;background:var(--accent);color:#fff;text-decoration:none;font-weight:600;padding:12px 24px;border-radius:8px;min-height:44px;line-height:22px;border:1.5px solid var(--accent)}
	.uls-shop .cta .uls-cta-primary:hover{background:#fff;color:var(--navy);border-color:#fff}
	.uls-shop .cta .uls-cta-primary:focus-visible{outline:3px solid var(--accent-teal);outline-offset:2px}
	.uls-shop .cta .uplinksync-book-cta{margin:0}
	.uls-shop .cta .uplinksync-book-cta .uls-book-link{display:inline-flex;align-items:center;justify-content:center;background:transparent;border:1.5px solid rgba(255,255,255,.6);color:#fff;text-decoration:none;font-weight:600;padding:12px 24px;border-radius:8px;min-height:44px;line-height:22px}
	.uls-shop .cta .uplinksync-book-cta .uls-book-link:hover{background:#fff;border-color:#fff;color:var(--navy)}
	.uls-shop .cta .uplinksync-book-cta .uls-book-link:focus-visible{outline:3px solid var(--accent-teal);outline-offset:2px}
	@media(prefers-reduced-motion:reduce){.uls-shop *{transition:none!important}}
	</style>

	<div class="uls-shop">
		<div class="wrap">
			<div class="hero">
				<span class="h-eyebrow">Aerial print catalog</span>
				<h1 class="h-name">The full catalog</h1>
				<p class="h-blurb">Every aerial print we&rsquo;ve published over the Intermountain West &mdash; color-graded, high-resolution, licensed for print. Browse the whole set below, or filter to a single location.</p>
				<div class="h-meta">
					<span><?php echo esc_html( $total ); ?> prints</span>
					<?php if ( $loc_count ) : ?><span class="d">&middot;</span><span><?php echo esc_html( $loc_count ); ?> locations</span><?php endif; ?>
					<?php if ( $floor ) : ?><span class="d">&middot;</span><span>from <?php echo esc_html( $floor ); ?></span><?php endif; ?>
					<span class="d">&middot;</span><span>Archival matte &amp; canvas</span>
				</div>
			</div>

			<?php if ( $total ) : ?>
			<div class="toolbar">
				<div class="chips" role="group" aria-label="Filter by collection">
					<button type="button" class="chip" data-filter="all" aria-pressed="true">All<span class="n"><?php echo esc_html( $total ); ?></span></button>
					<?php foreach ( $groups as $g ) : ?>
						<button type="button" class="chip" data-filter="<?php echo esc_attr( $g['term']->slug ); ?>" aria-pressed="false"><?php echo esc_html( $g['term']->name ); ?><span class="n"><?php echo esc_html( $g['count'] ); ?></span></button>
					<?php endforeach; ?>
				</div>
				<label class="sortwrap">Sort
					<select aria-label="Sort prints">
						<option value="featured">Featured</option>
						<option value="price-asc">Price: low to high</option>
						<option value="price-desc">Price: high to low</option>
						<option value="name-asc">Name: A to Z</option>
					</select>
				</label>
			</div>

			<?php foreach ( $groups as $g ) :
				$term  = $g['term'];
				$gfloor = uls_shop_money( $g['min'] );
				$curl  = get_term_link( $term );
				?>
				<section class="coll" data-coll="<?php echo esc_attr( $term->slug ); ?>" aria-label="<?php echo esc_attr( $term->name ); ?>">
					<div class="coll-head">
						<h2 class="coll-name"><?php echo esc_html( $term->name ); ?></h2>
						<span class="coll-sub"><span class="k"><?php echo esc_html( $g['eyebrow'] ); ?></span><span class="d">&middot;</span><?php echo esc_html( $g['count'] ); ?> prints<?php if ( $gfloor ) : ?><span class="d">&middot;</span>from <?php echo esc_html( $gfloor ); ?><?php endif; ?></span>
						<?php if ( ! is_wp_error( $curl ) && $curl ) : ?><a class="coll-view" href="<?php echo esc_url( $curl ); ?>">Open collection &rarr;</a><?php endif; ?>
					</div>
					<div class="pgrid">
						<?php $order = 0; foreach ( $g['posts'] as $p ) :
							$pr = wc_get_product( $p->ID );
							if ( ! $pr ) { continue; }
							$order++;
							$permalink = get_permalink( $p->ID );
							$img = has_post_thumbnail( $p->ID )
								? get_the_post_thumbnail( $p->ID, 'woocommerce_thumbnail', array( 'alt' => esc_attr( $pr->get_name() ), 'loading' => 'lazy' ) )
								: wc_placeholder_img( 'woocommerce_thumbnail' );
							$pnum  = (float) $pr->get_price();
							$pname = $pr->get_name();
							$classes = 'addbtn';
							if ( $pr->is_purchasable() && $pr->is_in_stock() && ! $pr->is_type( 'variable' ) ) {
								$classes .= ' add_to_cart_button ajax_add_to_cart';
							}
							?>
							<div class="prod" data-coll="<?php echo esc_attr( $term->slug ); ?>" data-price="<?php echo esc_attr( $pnum ); ?>" data-name="<?php echo esc_attr( strtolower( $pname ) ); ?>" data-order="<?php echo esc_attr( $order ); ?>">
								<a class="pimg" href="<?php echo esc_url( $permalink ); ?>"><?php echo $img; // phpcs:ignore ?></a>
								<div class="pinfo">
									<span class="ptitle"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $pname ); ?></a></span>
									<span class="pprice"><?php echo $pr->get_price_html(); // phpcs:ignore ?></span>
								</div>
								<a href="<?php echo esc_url( $pr->add_to_cart_url() ); ?>" data-product_id="<?php echo esc_attr( $pr->get_id() ); ?>" data-quantity="1" rel="nofollow" class="<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( $pr->add_to_cart_text() ); ?></a>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>

			<p class="note">Prices are per print. Watermarked previews are shown while you browse; the clean, full-resolution master is delivered on purchase. Every published aerial carries an &ldquo;Aerial by UplinkSync&rdquo; credit.</p>
			<?php else : ?>
				<div class="empty">No prints published yet.</div>
			<?php endif; ?>
		</div>

		<?php if ( $total ) : ?>
		<div class="cta">
			<h2>Licensing something specific?</h2>
			<p>Tell us the location and use and we&rsquo;ll scope it &mdash; commercial and large-format licensing on request.</p>
			<div class="cta-actions">
				<a class="uls-cta-primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Get in touch</a>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<script>
	(function(){
		var root=document.currentScript.previousElementSibling;
		if(!root||!root.classList.contains('uls-shop')){root=document.querySelector('.uls-shop');}
		if(!root)return;
		var chips=[].slice.call(root.querySelectorAll('.chip'));
		var sections=[].slice.call(root.querySelectorAll('.coll'));
		var sortSel=root.querySelector('.sortwrap select');
		function applyFilter(f){
			chips.forEach(function(c){c.setAttribute('aria-pressed',c.dataset.filter===f?'true':'false');});
			sections.forEach(function(s){s.classList.toggle('is-hidden',!(f==='all'||s.dataset.coll===f));});
		}
		function applySort(mode){
			sections.forEach(function(sec){
				var grid=sec.querySelector('.pgrid');if(!grid)return;
				var cards=[].slice.call(grid.querySelectorAll('.prod'));
				cards.sort(function(a,b){
					if(mode==='price-asc')return (+a.dataset.price)-(+b.dataset.price)|| (+a.dataset.order)-(+b.dataset.order);
					if(mode==='price-desc')return (+b.dataset.price)-(+a.dataset.price)|| (+a.dataset.order)-(+b.dataset.order);
					if(mode==='name-asc')return a.dataset.name.localeCompare(b.dataset.name);
					return (+a.dataset.order)-(+b.dataset.order);
				});
				cards.forEach(function(c){grid.appendChild(c);});
			});
		}
		chips.forEach(function(c){c.addEventListener('click',function(){applyFilter(c.dataset.filter);});});
		if(sortSel){sortSel.addEventListener('change',function(){applySort(sortSel.value);});}
	})();
	</script>
	<?php
	return ob_get_clean();
} );
