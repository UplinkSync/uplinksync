<?php
/**
 * UplinkSync product page — pure helpers (no hooks, no WordPress calls), so they are unit-testable.
 * Loaded by ../uplinksync-product-page.php; required directly by tests/test-product-page.php.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Facts read from a product's description text at render time (orchestrator decision 2026-10-28:
 * no new meta yet). Structured meta wins when it exists (see uls_pp_facts()).
 *
 * @param string $text Description and/or short description (HTML allowed).
 * @return array{mp:string,captured:string,full_res:bool,drone:bool} '' when not found.
 */
function uls_pp_parse_facts( $text ) {
	$plain = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) ) );
	$out   = array(
		'mp'       => '',
		'captured' => '',
		'full_res' => false,
		'drone'    => false,
	);
	if ( preg_match( '/\b(\d{1,3}(?:\.\d{1,2})?)\s*MP\b/', $plain, $m ) ) {
		$out['mp'] = $m[1];
	}
	if ( preg_match( '/\bcaptured\s+(\d{4})-(\d{2})-(\d{2})\b/i', $plain, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		$out['captured'] = $m[1] . '-' . $m[2] . '-' . $m[3];
	}
	$out['full_res'] = (bool) preg_match( '/full[- ]resolution/i', $plain );
	$out['drone']    = (bool) preg_match( '/\bdrone\b/i', $plain );
	return $out;
}

/**
 * "27 Sep 2025" from an ISO date ('' when invalid).
 *
 * @param string $iso Y-m-d.
 * @return string
 */
function uls_pp_format_date( $iso ) {
	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $iso, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return '';
	}
	$months = array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );
	return (int) $m[3] . ' ' . $months[ (int) $m[2] - 1 ] . ' ' . $m[1];
}

/**
 * Named aspect ratio + orientation from pixel dimensions. Snaps to common photo ratios within 2%;
 * otherwise "1.85:1". Empty array when dimensions are missing.
 *
 * @param int $w Width.
 * @param int $h Height.
 * @return array{ratio:string,orient:string}|array{}
 */
function uls_pp_aspect( $w, $h ) {
	$w = (int) $w;
	$h = (int) $h;
	if ( $w <= 0 || $h <= 0 ) {
		return array();
	}
	$orient = $w > $h ? 'Landscape' : ( $w < $h ? 'Portrait' : 'Square' );
	$r      = $w / $h;
	$named  = array( '1:1', '5:4', '4:3', '3:2', '16:10', '16:9', '2:1', '21:9', '3:1' );
	foreach ( $named as $n ) {
		list( $a, $b ) = array_map( 'intval', explode( ':', $n ) );
		foreach ( array( array( $a, $b ), array( $b, $a ) ) as $ab ) {
			if ( abs( $r - $ab[0] / $ab[1] ) / ( $ab[0] / $ab[1] ) <= 0.02 ) {
				return array(
					'ratio'  => $ab[0] . ':' . $ab[1],
					'orient' => $orient,
				);
			}
		}
	}
	return array(
		'ratio'  => $r >= 1 ? rtrim( rtrim( number_format( $r, 2, '.', '' ), '0' ), '.' ) . ':1' : '1:' . rtrim( rtrim( number_format( 1 / $r, 2, '.', '' ), '0' ), '.' ),
		'orient' => $orient,
	);
}

/**
 * The description as "About this photo": drops the licence/availability sentences the interim
 * migration (tools/licence-tiers/personal-only.php) and the original catalogue wrote into the copy,
 * because the buy box, table and FAQ now say that once, data-driven. Everything else is kept as written.
 *
 * @param string $html Product description HTML.
 * @return string HTML (unchanged when no known licence copy is present).
 */
function uls_pp_about_html( $html ) {
	$html = (string) $html;
	// Interim copy: "Licence: Personal (...). Commercial licences are coming soon; ... Request a quote</a>." (links optional).
	$html = preg_replace( '#\s*Licence:\s*Personal\b.*?Request a quote(?:</a>)?\.\s*#is', ' ', $html );
	// Pre-tier copy (the line the migration replaces).
	$html = str_replace( 'Personal + commercial license; no physical item is shipped.', 'No physical item is shipped.', $html );
	$html = preg_replace( '/ {2,}/', ' ', $html );
	return trim( preg_replace( '#\s+</p>#', '</p>', $html ) );
}

/**
 * The collection-strip window: up to $n products that follow $idx in collection order, wrapping
 * around, never the current product, never a duplicate.
 *
 * @param int[] $ids Collection product ids in order.
 * @param int   $idx Index of the current product in $ids.
 * @param int   $n   How many.
 * @return int[]
 */
function uls_pp_strip_window( array $ids, $idx, $n = 6 ) {
	$ids   = array_values( $ids );
	$total = count( $ids );
	$out   = array();
	for ( $k = 1; $k < $total && count( $out ) < $n; $k++ ) {
		$out[] = (int) $ids[ ( $idx + $k ) % $total ];
	}
	return $out;
}
