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
	// 1. The exact sentence tools/licence-tiers/personal-only.php wrote ($new there), links included or stripped.
	$exact = array(
		'Licence: Personal (private, non-commercial use). Commercial licences are coming soon; for business use, <a href="/contact/?topic=licensing">contact us</a>. Need exclusive rights? <a href="/contact/?topic=licensing">Request a quote</a>. No physical item is shipped.',
		'Licence: Personal (private, non-commercial use). Commercial licences are coming soon; for business use, contact us. Need exclusive rights? Request a quote. No physical item is shipped.',
		'Personal + commercial license; no physical item is shipped.',
	);
	$html = str_replace( $exact, 'No physical item is shipped.', $html );
	// 2. Lightly edited variants of the same sentence: anchored on its fixed opening and never allowed to
	//    cross a block tag, so owner copy in other paragraphs/lists can't be swallowed (review Medium-1).
	$r = preg_replace(
		'#\s*Licence:\s*Personal\s*\(private, non-commercial use\)\.\s*Commercial licences are coming soon;(?:(?!</?(?:p|li|ul|ol|div|h\d|br|table|blockquote)\b).){0,400}?Request a quote(?:</a>)?\.#is',
		'',
		$html
	);
	$html = null === $r ? $html : $r; // PCRE limit hit: keep the copy as written rather than blank it.
	$r    = preg_replace( '/ {2,}/', ' ', $html );
	$html = null === $r ? $html : $r;
	$r    = preg_replace( '#\s+</p>#', '</p>', $html );
	$html = null === $r ? $html : $r;
	return trim( $html );
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

/**
 * Copy for the table lede, the business FAQ answer and the help card, derived from tier state so a
 * partial launch never says "commercial licences aren't on sale" next to a buyable commercial tier.
 * `{contact}` in the returned strings is replaced by the caller with the escaped contact URL.
 *
 * @param string[] $on     Available slugs.
 * @param string[] $off    Coming-soon slugs.
 * @param array    $labels slug => label.
 * @return array{lede:string,business:string,help:array{0:string,1:string,2:string}}
 */
function uls_pp_band_copy( array $on, array $off, array $labels ) {
	$commercial_on  = (bool) array_diff( $on, array( 'personal' ) );
	$commercial_off = array_values( array_diff( $off, array( 'personal' ) ) );
	$names          = array();
	foreach ( $off as $s ) {
		$names[] = isset( $labels[ $s ] ) ? $labels[ $s ] : $s;
	}
	$soon = $off ? htmlspecialchars( implode( ' and ', $names ), ENT_QUOTES, 'UTF-8' ) . ( count( $off ) > 1 ? ' are' : ' is' ) . ' coming soon — <a href="{contact}">ask us</a> if you need ' . ( count( $off ) > 1 ? 'them' : 'it' ) . ' now.' : '';

	if ( ! $off ) {
		$lede = 'Every licence is perpetual, worldwide and non-exclusive, for one licensee.';
	} elseif ( array( 'personal' ) === $on ) {
		$lede = 'Personal is available today. Commercial licences are coming soon — <a href="{contact}">ask us</a> if you need business use now.';
	} else {
		$lede = $soon;
	}

	$exclusive = array( 'Need exclusive rights or a bigger run?', 'We license exclusive use and larger print or merchandise runs directly. Tell us where the photo will appear and we\'ll reply within one business day.', 'Request a quote →' );
	if ( ! $commercial_on && $commercial_off ) {
		$business = 'Commercial licences are coming soon. Until then, <a href="{contact}">contact us about licensing</a> and we\'ll sort it out with you directly.';
		$help     = array( 'Need it for a business today?', 'Commercial licences aren\'t on sale yet, but we license business use directly. Tell us where the photo will appear and we\'ll reply within one business day.', 'Ask about commercial use →' );
	} elseif ( $commercial_on && $commercial_off ) {
		$business = 'Choose a commercial licence above; the table shows what each one covers. ' . $soon;
		$help     = $exclusive;
	} else {
		$business = 'Choose a commercial licence above; the table shows what each one covers. Not sure which fits? <a href="{contact}">Ask us</a>.';
		$help     = $exclusive;
	}
	return array(
		'lede'     => $lede,
		'business' => $business,
		'help'     => $help,
	);
}
