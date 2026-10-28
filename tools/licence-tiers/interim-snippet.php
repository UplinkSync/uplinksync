/**
 * ULS licence tiers — INTERIM item-page text (2026-10-28, owner instruction). Installed as a Code Snippet on
 * production by install-interim-snippet.php so no code merge is needed. Only touches variable products with the
 * pa_licence attribute. Steps aside automatically once MR !189 (uplinksync-licence-tiers.php) is live with its
 * flag on; deactivate/delete this snippet then.
 */
if ( ! function_exists( 'uls_lic_interim_on' ) ) {
	function uls_lic_interim_on( $product ) {
		if ( function_exists( 'uls_lic_live' ) && uls_lic_live() ) {
			return false;
		}
		return $product instanceof WC_Product && $product->is_type( 'variable' ) && array_key_exists( 'pa_licence', $product->get_attributes() );
	}
}
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	global $product;
	if ( isset( $tabs['uls_licence'] ) && uls_lic_interim_on( $product ) ) {
		$tabs['uls_licence']['callback'] = function () {
			echo '<h2>Licence</h2>';
			echo '<p>Your purchase is an instant digital download: one full-resolution JPEG, no physical item shipped.</p>';
			echo '<p>The <strong>Personal</strong> licence covers private, non-commercial use only: prints for your own home, gifts you don&#8217;t sell, and personal posts that earn no money. It does not cover business, client or monetised use.</p>';
			echo '<p><strong>Commercial licences are coming soon</strong> (Commercial with credit, Commercial and Extended). For business use now, <a href="' . esc_url( home_url( '/contact/?topic=licensing' ) ) . '">contact us</a>. Need exclusive rights? <a href="' . esc_url( home_url( '/contact/?topic=licensing' ) ) . '">Request a quote</a>.</p>';
			echo '<p>Reselling, sharing or redistributing the file itself is not permitted under any licence.</p>';
		};
	}
	return $tabs;
}, 100 );
add_filter( 'woocommerce_get_availability_text', function ( $text, $p ) {
	if ( $p instanceof WC_Product_Variation && ! $p->is_in_stock() && 'personal' !== $p->get_attribute( 'pa_licence' ) && uls_lic_interim_on( wc_get_product( $p->get_parent_id() ) ) ) {
		return 'Available soon';
	}
	return $text;
}, 10, 2 );
