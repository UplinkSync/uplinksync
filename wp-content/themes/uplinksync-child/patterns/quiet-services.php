<?php
/**
 * Title: Quiet Light — Services rows
 * Slug: uplinksync-child/quiet-services
 * Categories: services
 * Description: Four hairline service rows, each linking to its page.
 * Keywords: services, rows
 * Inserter: true
 */

$uls_ql_arrow = '<svg class="uls-ql-row__arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$uls_ql_icon  = static function ( $paths ) {
	return '<svg class="uls-ql-row__icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};
$uls_ql_rows  = array(
	array( '/services/managed-it/', 'Managed IT &amp; security', 'Setup, monitoring, backups and help when something breaks.', '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4M9 10l2 2 4-4"/>' ),
	array( '/services/', 'Microsoft 365 &amp; networking', 'Email, accounts, Wi-Fi and the network, set up properly.', '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 8l9 6 9-6"/>' ),
	array( '/services/web/', 'Websites &amp; hosting', 'Fast, secure sites built and looked after by the same team.', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>' ),
	array( '/drone-services/', 'Aerial photo &amp; video', 'Listing photos, progress records and inspections from above.', '<rect x="9" y="10" width="6" height="4" rx="1"/><path d="M9.5 10.5L5 6M14.5 10.5L19 6M9.5 13.5L5 18M14.5 13.5L19 18"/><circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="5" cy="18" r="2"/><circle cx="19" cy="18" r="2"/>' ),
);
?>
<!-- wp:group {"tagName":"section","className":"uls-ql-section uls-ql-services","layout":{"type":"default"}} -->
<section class="wp-block-group uls-ql-section uls-ql-services"><!-- wp:group {"className":"uls-ql-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group uls-ql-wrap"><!-- wp:heading {"className":"uls-ql-rule"} -->
<h2 class="wp-block-heading uls-ql-rule">Services</h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="uls-ql-rows">
<?php foreach ( $uls_ql_rows as $uls_ql_row ) : ?>
<a class="uls-ql-row" href="<?php echo esc_attr( $uls_ql_row[0] ); ?>"><?php echo $uls_ql_icon( $uls_ql_row[3] ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><span><span class="uls-ql-row__name"><?php echo $uls_ql_row[1]; // phpcs:ignore WordPress.Security.EscapeOutput -- static copy. ?></span><span class="uls-ql-row__desc"><?php echo $uls_ql_row[2]; // phpcs:ignore WordPress.Security.EscapeOutput -- static copy. ?></span></span><?php echo $uls_ql_arrow; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></a>
<?php endforeach; ?>
</div>
<!-- /wp:html --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
