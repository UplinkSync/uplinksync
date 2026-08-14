<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 93
 * name  : UPLAA-179 Client Proofing Gallery — access engine + [uplaa_client_album]
 * scope : front-end
 * state : ACTIVE
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

/**
 * UPLAA-179 — Client-only proofing gallery (PRODUCTION ENGINE v2).
 *
 * Owner GREENLIT 2026-07-30 (WooCommerce path; NOT Imagely Pro). Reuses the live
 * store's watermarked-preview -> clean-master pattern (#45) + WooCommerce + Stripe.
 *
 * WHAT THIS DOES
 *  1. Whole-surface gating: a client album's hidden WooCommerce product CATEGORY
 *     archive (/product-category/<slug>/) AND every product URL in it are gated.
 *     Only the entitled logged-in client (+ admin/shop-manager) may reach them;
 *     anonymous -> redirected to login; logged-in-but-not-entitled -> redirected
 *     home. Zero content leakage either way. Gated surfaces are noindex.
 *  2. Entitlement engine reads wp_options 'uplaa179_albums'.
 *  3. [uplaa_client_album slug="..."] shortcode renders a client's album (from an
 *     explicit item list for the DEMO, or from the album's product category for
 *     real albums), in the store navy card style.
 *
 * ALBUM SCHEMA  (wp_options 'uplaa179_albums' = assoc array keyed by slug)
 *   'album-slug' => array(
 *       'label'       => 'Proofing Gallery — <Shoot>',   // client-facing heading
 *       'client'      => '<Client display name>',
 *       'clients'     => array( <WP user IDs entitled> ), // client sees ONLY their album(s)
 *       'product_cat' => '<woocommerce product_cat slug>', // gated surface + product source
 *       'items'       => array( array('img'=>URL,'title'=>..,'buy'=>URL), ... ), // optional (demo)
 *   )
 *  Admin/shop-manager (manage_woocommerce) is always allowed, for support.
 *
 * REVERSIBLE: deactivate/delete this snippet + the bootstrap artefacts
 * (product category, demo products, demo users) + delete option 'uplaa179_albums'
 * + delete the private demo page. No theme deploy, no plugin. Public store untouched.
 */
if ( ! function_exists( 'uplaa179_albums' ) ) {

	function uplaa179_albums() {
		$opt = get_option( 'uplaa179_albums' );
		return is_array( $opt ) ? $opt : array();
	}

	// Which album (if any) owns this product_cat slug.
	function uplaa179_album_for_cat( $cat_slug ) {
		foreach ( uplaa179_albums() as $slug => $a ) {
			if ( ! empty( $a['product_cat'] ) && $a['product_cat'] === $cat_slug ) {
				return $slug;
			}
		}
		return null;
	}

	// All product_cat slugs that are proofing (gated) categories.
	function uplaa179_gated_cat_slugs() {
		$out = array();
		foreach ( uplaa179_albums() as $a ) {
			if ( ! empty( $a['product_cat'] ) ) { $out[] = $a['product_cat']; }
		}
		return $out;
	}

	// Entitlement: admin/shop-manager always; else user must be listed on the album.
	function uplaa179_can_view( $album_slug, $user_id = null ) {
		$albums = uplaa179_albums();
		if ( ! isset( $albums[ $album_slug ] ) ) { return false; }
		if ( null === $user_id ) { $user_id = get_current_user_id(); }
		if ( user_can( $user_id, 'manage_woocommerce' ) ) { return true; }
		$ids = array_map( 'intval', (array) ( isset( $albums[ $album_slug ]['clients'] ) ? $albums[ $album_slug ]['clients'] : array() ) );
		return in_array( (int) $user_id, $ids, true );
	}

	/* ---------- 1. WHOLE-SURFACE GATE (category archive + product URLs) ---------- */
	add_action( 'template_redirect', function () {
		if ( is_admin() ) { return; }
		if ( ! function_exists( 'is_product_category' ) ) { return; } // Woo inactive safety

		$album_slug = null;

		if ( is_product_category() ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) && isset( $term->slug ) ) {
				$album_slug = uplaa179_album_for_cat( $term->slug );
			}
		} elseif ( is_product() ) {
			$pid   = get_queried_object_id();
			$slugs = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $slugs ) ) {
				foreach ( $slugs as $cs ) {
					$a = uplaa179_album_for_cat( $cs );
					if ( $a ) { $album_slug = $a; break; }
				}
			}
		}

		if ( null === $album_slug ) { return; } // not a gated surface -> leave the public store alone

		// Never cache a gated surface.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'uplaa179 gated surface' );

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( trailingslashit( $GLOBALS['wp']->request ) ) ), 302 );
			exit;
		}
		if ( ! uplaa179_can_view( $album_slug ) ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}

		// Entitled viewer: allow, but keep it out of search indexes.
		add_filter( 'wp_robots', function ( $r ) { $r['noindex'] = true; $r['nofollow'] = true; return $r; } );
	}, 1 );

	/* ---------- 2. BRANDED UI ---------- */
	function uplaa179_notice( $html, $url = '', $cta = '' ) {
		$btn = ( $url && $cta ) ? '<a class="uplaa179-btn" href="' . esc_url( $url ) . '">' . esc_html( $cta ) . '</a>' : '';
		return '<div class="uplaa179-wrap"><div class="uplaa179-notice">' . wp_kses_post( $html ) . $btn . '</div></div>' . uplaa179_css();
	}

	function uplaa179_css() {
		static $done = false;
		if ( $done ) { return ''; }
		$done = true;
		return '<style>
.uplaa179-wrap{--navy:#173258;--acc:#95D5DD;--bd:#2a4a72;max-width:1080px;margin:0 auto;padding:8px 0}
.uplaa179-head{background:var(--navy);color:#fff;border-radius:16px;padding:26px 28px;margin-bottom:22px}
.uplaa179-eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--acc);margin:0 0 6px}
.uplaa179-head h2{margin:0;color:#fff;font-size:26px;line-height:1.15}
.uplaa179-head p{margin:10px 0 0;color:rgba(255,255,255,.9);font-size:15px}
.uplaa179-grid{display:grid;gap:20px;grid-template-columns:repeat(3,1fr)}
.uplaa179-card{background:var(--navy);border:1px solid var(--bd);border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(16,42,76,.08);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease}
.uplaa179-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px -12px rgba(16,42,76,.45);border-color:var(--acc)}
.uplaa179-card img{display:block;width:100%;height:clamp(160px,18vw,220px);object-fit:cover;background:#0f2547}
.uplaa179-card__body{padding:14px 16px;color:#fff}
.uplaa179-card__title{font-size:15px;font-weight:700;margin:0 0 4px}
.uplaa179-card__price{font-size:13px;color:rgba(255,255,255,.82);margin:0 0 10px}
.uplaa179-btn{display:inline-flex;align-items:center;gap:6px;background:var(--acc);color:var(--navy);font-weight:700;font-size:14px;padding:9px 16px;border-radius:999px;text-decoration:none;border:0;cursor:pointer}
.uplaa179-btn:hover{filter:brightness(.94)}
.uplaa179-notice{background:var(--navy);color:#fff;border:1px solid var(--bd);border-radius:16px;padding:30px 28px;font-size:16px;line-height:1.5;text-align:center}
.uplaa179-notice .uplaa179-btn{margin-top:16px}
@media(max-width:899px){.uplaa179-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){.uplaa179-grid{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.uplaa179-card{transition:none}}
</style>';
	}

	/* ---------- 3. CLIENT ALBUM SHORTCODE ---------- */
	add_shortcode( 'uplaa_client_album', function ( $atts ) {
		$atts   = shortcode_atts( array( 'slug' => '' ), $atts, 'uplaa_client_album' );
		$slug   = sanitize_key( $atts['slug'] );
		$albums = uplaa179_albums();

		if ( ! $slug || ! isset( $albums[ $slug ] ) ) {
			return uplaa179_notice( 'This proofing gallery is not available.' );
		}
		$album = $albums[ $slug ];

		if ( ! is_user_logged_in() ) {
			return uplaa179_notice(
				'<strong>Client sign-in required.</strong><br>Please sign in to view your watermarked proofing gallery.',
				wp_login_url( get_permalink() ), 'Sign in to view'
			);
		}
		if ( ! uplaa179_can_view( $slug ) ) {
			return uplaa179_notice(
				'This proofing gallery belongs to another client account.<br>If you believe this is an error, please contact UplinkSync.',
				'/contact/', 'Contact us'
			);
		}

		$out  = '<div class="uplaa179-wrap"><div class="uplaa179-head">';
		$out .= '<p class="uplaa179-eyebrow">Client proofing · watermarked previews</p>';
		$out .= '<h2>' . esc_html( isset( $album['label'] ) ? $album['label'] : 'Your proofing gallery' ) . '</h2>';
		$intro = isset( $album['intro'] ) && $album['intro'] !== ''
			? $album['intro']
			: 'Review your shoot below. Previews are watermarked. Request the frames you want and we\'ll send you the clean, full-resolution files.';
		$out .= '<p>' . esc_html( $intro ) . ' ';
		$out .= esc_html( isset( $album['client'] ) ? $album['client'] : '' ) . '</p></div>';

		$cards = '';

		// Prefer real WooCommerce products from the album's category.
		if ( ! empty( $album['product_cat'] ) && function_exists( 'wc_get_product' ) ) {
			$q = get_posts( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'fields'         => 'ids',
				'tax_query'      => array( array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $album['product_cat'],
				) ),
			) );
			foreach ( $q as $pid ) {
				$p = wc_get_product( $pid );
				if ( ! $p ) { continue; }
				$img   = get_the_post_thumbnail_url( $pid, 'woocommerce_single' );
				if ( ! $img ) { $img = wc_placeholder_img_src(); }
				$title = get_the_title( $pid );
				$buy   = get_permalink( $pid ); // product page (itself gated) -> Add to cart -> checkout
				$price = $p->get_price_html();
				$cards .= '<div class="uplaa179-card"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . ' — watermarked proofing preview" loading="lazy" decoding="async" oncontextmenu="return false;">';
				$cards .= '<div class="uplaa179-card__body"><p class="uplaa179-card__title">' . esc_html( $title ) . '</p>';
				if ( $price ) { $cards .= '<p class="uplaa179-card__price">' . wp_kses_post( $price ) . '</p>'; }
				$cards .= '<a class="uplaa179-btn" href="' . esc_url( $buy ) . '">Purchase clean version</a></div></div>';
			}
		}

		// Fallback / list mode: explicit item list. Supports preview-then-quote
		// (album-level cta_url/cta_label) OR per-item direct buy links (demo).
		if ( '' === $cards && ! empty( $album['items'] ) && is_array( $album['items'] ) ) {
			$cta_label = isset( $album['cta_label'] ) && $album['cta_label'] !== '' ? $album['cta_label'] : 'Purchase clean version';
			$cta_url   = isset( $album['cta_url'] ) ? $album['cta_url'] : '';
			foreach ( $album['items'] as $it ) {
				$img = isset( $it['img'] ) ? esc_url( $it['img'] ) : '';
				if ( ! $img ) { continue; }
				$title_raw = isset( $it['title'] ) ? $it['title'] : '';
				$title = esc_html( $title_raw );
				// Per-item buy link wins; else album-level quote CTA carrying the frame title.
				if ( ! empty( $it['buy'] ) ) {
					$href = esc_url( $it['buy'] );
				} elseif ( $cta_url ) {
					$href = esc_url( add_query_arg( array( 'frame' => rawurlencode( $title_raw ) ), $cta_url ) );
				} else {
					$href = '';
				}
				$cards .= '<div class="uplaa179-card"><img src="' . $img . '" alt="' . $title . ' — watermarked proofing preview" loading="lazy" decoding="async" oncontextmenu="return false;">';
				$cards .= '<div class="uplaa179-card__body"><p class="uplaa179-card__title">' . $title . '</p>';
				if ( $href ) { $cards .= '<a class="uplaa179-btn" href="' . $href . '">' . esc_html( $cta_label ) . '</a>'; }
				$cards .= '</div></div>';
			}
		}

		$out .= $cards ? '<div class="uplaa179-grid">' . $cards . '</div>'
		              : '<div class="uplaa179-notice">Your frames are being prepared and will appear here shortly.</div>';
		$out .= '</div>' . uplaa179_css();
		return $out;
	} );

	/* ---------- 4. ADMIN DIAGNOSTIC (headless verification of gate logic) ---------- */
	add_action( 'rest_api_init', function () {
		register_rest_route( 'uplaa179/v1', '/status', array(
			'methods'             => 'GET',
			'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
			'callback'            => function ( $req ) {
				$albums = uplaa179_albums();
				$out    = array( 'albums' => array() );
				foreach ( $albums as $slug => $a ) {
					$term = ! empty( $a['product_cat'] ) ? get_term_by( 'slug', $a['product_cat'], 'product_cat' ) : null;
					$count = 0;
					if ( $term && ! is_wp_error( $term ) ) {
						$count = (int) $term->count;
					}
					$out['albums'][ $slug ] = array(
						'label'       => isset( $a['label'] ) ? $a['label'] : '',
						'clients'     => isset( $a['clients'] ) ? array_map( 'intval', (array) $a['clients'] ) : array(),
						'product_cat' => isset( $a['product_cat'] ) ? $a['product_cat'] : '',
						'cat_term_id' => ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : null,
						'product_ct'  => $count,
					);
				}
				// Optional: gatecheck a user vs album -> ?user=ID&album=slug
				$uid = (int) $req->get_param( 'user' );
				$alb = sanitize_key( (string) $req->get_param( 'album' ) );
				if ( $uid && $alb ) {
					$out['gatecheck'] = array(
						'user'    => $uid,
						'album'   => $alb,
						'can_view'=> uplaa179_can_view( $alb, $uid ),
					);
				}
				return $out;
			},
		) );
	} );
}
