<?php
/**
 * Title: Location line ("Where we work")
 * Slug: uplinksync-child/location-line
 * Categories: text
 * Description: The single swappable location slot (area served / address / phone). Ships EMPTY while the business is location-neutral: it renders nothing. To publish a location, return the text from the `uplinksync_location_line` filter (one line of plain text), or insert this pattern and replace it with a synced pattern in the editor.
 * Keywords: location, where we work, area served
 * Inserter: true
 */

$uls_ql_location = trim( (string) apply_filters( 'uplinksync_location_line', '' ) );
if ( '' === $uls_ql_location ) :
	// Location-neutral: render nothing (no HTML comment either, so the page source names no place).
	echo '';
else :
	?>
<!-- wp:paragraph {"className":"uls-ql-location"} -->
<p class="uls-ql-location"><?php echo esc_html( $uls_ql_location ); ?></p>
<!-- /wp:paragraph -->
	<?php
endif;
