<?php
/**
 * Regression test: the estimator's rate model and price ranges must not move.
 *
 * The rates moved from DB Code Snippet #82 into uplinksync-estimator/config.php. This pins
 * every rate AND the resulting ranges, computed with a PHP reference of snippet #82's
 * computeUav()/computeIt() formulas (JS Math.round = floor(x + 0.5)). If a rate or formula
 * changes, this fails. Also asserts the estimator is location-neutral and on-palette.
 *
 * Run: php wp-content/mu-plugins/tests/test-estimator-pricing.php
 */

error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../uplinksync-estimator/config.php';

$failures = 0;
function check( $name, $cond, $detail = '' ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . ( ! $cond && '' !== $detail ? "  ({$detail})" : '' ) . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}

function js_round( $n ) {
	return (int) floor( $n + 0.5 );
}
function money( $n ) {
	return '$' . number_format( js_round( $n ) );
}

/**
 * Reference of snippet #82 computeUav().
 *
 * @param string $line    Line key.
 * @param array  $choices option id => choice v (selects).
 * @param array  $checks  option ids ticked.
 * @param float  $miles   Round-trip miles.
 * @param string $timing  standard|rush|same_day.
 */
function uav_range( $line, $choices = array(), $checks = array(), $miles = 0, $timing = 'standard' ) {
	$cfg = uls_estimator_uav_rates();
	$l   = $cfg['lines'][ $line ];
	if ( 'contact' === $l['posture'] ) {
		return 'contact';
	}
	$total = $l['base'];
	foreach ( $l['options'] as $o ) {
		if ( 'select' === $o['type'] ) {
			$pick = isset( $choices[ $o['id'] ] ) ? $choices[ $o['id'] ] : $o['choices'][0]['v'];
			foreach ( $o['choices'] as $c ) {
				if ( $c['v'] === $pick ) {
					$total += $c['add'];
				}
			}
		} elseif ( in_array( $o['id'], $checks, true ) ) {
			$total += $o['add'];
		}
	}
	$over  = max( 0, $miles - $cfg['travel']['freeRadiusMi'] );
	$fee   = $over * $cfg['travel']['perMileOver'];
	$mult  = isset( $cfg['rush'][ $timing ] ) ? $cfg['rush'][ $timing ] : 1;
	$with  = ( $total + $fee ) * $mult;
	if ( 'band' === $l['posture'] ) {
		return money( $with * ( 1 - $l['bandSpread'] ) ) . ' – ' . money( $with * ( 1 + $l['bandSpread'] ) );
	}
	return money( $with ) . ' – ' . money( $with * ( 1 + $l['startingSpread'] ) );
}

/**
 * Reference of snippet #82 computeIt() (monthly mode).
 */
function it_range( $area, $args = array() ) {
	$cfg = uls_estimator_it_rates();
	$a   = $cfg[ $area ];
	if ( 'contact' === $a['posture'] ) {
		return 'contact';
	}
	if ( 'perUser' === $a['posture'] ) {
		$rate = 0;
		foreach ( $a['tiers'] as $t ) {
			if ( $t['v'] === $args['tier'] ) {
				$rate = $t['rate'];
			}
		}
		$m = $rate * max( 1, $args['users'] ) + $a['perServer'] * max( 0, $args['servers'] );
		return money( $m ) . ' – ' . money( $m * ( 1 + $a['spread'] ) );
	}
	$total = 0;
	foreach ( $a['options'] as $o ) {
		if ( 'select' === $o['type'] ) {
			$pick = isset( $args[ $o['id'] ] ) ? $args[ $o['id'] ] : $o['choices'][0]['v'];
			foreach ( $o['choices'] as $c ) {
				if ( $c['v'] === $pick ) {
					$total += $c['add'];
				}
			}
		} elseif ( ! empty( $args[ $o['id'] ] ) ) {
			$total += $o['add'];
		}
	}
	return money( $total ) . ' – ' . money( $total * ( 1 + $a['spread'] ) );
}

echo "Rate model (verbatim from snippet #82):\n";
$u = uls_estimator_uav_rates();
check( 'currency $', '$' === $u['currency'] );
check( 'travel: 25 free miles, $0.75/mi over', 25 === $u['travel']['freeRadiusMi'] && 0.75 === $u['travel']['perMileOver'] );
check( 'rush multipliers 1.00 / 1.30 / 1.50', array( 'standard' => 1.00, 'rush' => 1.30, 'same_day' => 1.50 ) === $u['rush'] );
check( 'real-estate base 175, band ±35%', 175 === $u['lines']['real_estate']['base'] && 0.35 === $u['lines']['real_estate']['bandSpread'] );
check( 'mapping base 500 +40%', 500 === $u['lines']['mapping']['base'] && 0.40 === $u['lines']['mapping']['startingSpread'] );
check( 'inspection base 300 +40%', 300 === $u['lines']['inspection']['base'] && 0.40 === $u['lines']['inspection']['startingSpread'] );
check( 'tower = contact (no number)', 'contact' === $u['lines']['tower']['posture'] );
check( 'add-ons: size 0/75/200, video 150, twilight 90', array( 0, 75, 200 ) === array_column( $u['lines']['real_estate']['options'][0]['choices'], 'add' ) && 150 === $u['lines']['real_estate']['options'][1]['add'] && 90 === $u['lines']['real_estate']['options'][2]['add'] );
check( 'mapping adds: acreage 0/250/750, deliverable 0/300/450', array( 0, 250, 750 ) === array_column( $u['lines']['mapping']['options'][0]['choices'], 'add' ) && array( 0, 300, 450 ) === array_column( $u['lines']['mapping']['options'][1]['choices'], 'add' ) );
check( 'inspection adds: structures 0/150/350, report 120', array( 0, 150, 350 ) === array_column( $u['lines']['inspection']['options'][0]['choices'], 'add' ) && 120 === $u['lines']['inspection']['options'][1]['add'] );
check( 'disclaimer = LIVE wording ("can change")', 'Estimate only — a starting figure based on typical projects, not a quote. Your firm price is confirmed by a UplinkSync team member and can change with site conditions, access, and deliverables.' === $u['disclaimer'] );
$i = uls_estimator_it_rates();
check( 'IT managed tiers 60/150/275 per user, +150/server, +25%', array( 60, 150, 275 ) === array_column( $i['managed']['tiers'], 'rate' ) && 150 === $i['managed']['perServer'] && 0.25 === $i['managed']['spread'] );
check( 'IT automation 500/750/1000/3000 + 0/500/1500 + 0/400/1000, +30%', array( 500, 750, 1000, 3000 ) === array_column( $i['automation']['options'][0]['choices'], 'add' ) && array( 0, 500, 1500 ) === array_column( $i['automation']['options'][1]['choices'], 'add' ) && array( 0, 400, 1000 ) === array_column( $i['automation']['options'][2]['choices'], 'add' ) && 0.30 === $i['automation']['spread'] );
check( 'IT web 149/349/900 + 0/50/150 + 50/250/100, +30%', array( 149, 349, 900 ) === array_column( $i['web']['options'][0]['choices'], 'add' ) && array( 0, 50, 150 ) === array_column( $i['web']['options'][1]['choices'], 'add' ) && 50 === $i['web']['options'][2]['add'] && 250 === $i['web']['options'][3]['add'] && 100 === $i['web']['options'][4]['add'] && 0.30 === $i['web']['spread'] );
check( 'IT mixed = contact', 'contact' === $i['mixed']['posture'] );

echo "\nPrice ranges (representative inputs):\n";
$cases = array(
	array( 'Drone real-estate <2,000 sq ft, standard, 0 mi', uav_range( 'real_estate' ), '$114 – $236' ),
	array( 'Drone real-estate <2,000 sq ft, 30 mi round trip', uav_range( 'real_estate', array(), array(), 30 ), '$116 – $241' ),
	array( 'Drone real-estate <2,000 sq ft + video', uav_range( 'real_estate', array(), array( 'video' ) ), '$211 – $439' ),
	array( 'Drone real-estate estate parcel + twilight, rush', uav_range( 'real_estate', array( 'sqft' => 'large' ), array( 'twilight' ), 0, 'rush' ), '$393 – $816' ),
	array( 'Drone mapping 20-100 acres + 3D model', uav_range( 'mapping', array( 'acres' => 'm', 'deliverable' => 'model' ) ), '$1,050 – $1,470' ),
	array( 'Drone inspection 2-3 structures + report, same-day, 45 mi', uav_range( 'inspection', array( 'structures' => '2' ), array( 'report' ), 45, 'same_day' ), '$878 – $1,229' ),
	array( 'Drone tower = scoped on a call', uav_range( 'tower' ), 'contact' ),
	array( 'IT help desk 5 users, 0 servers', it_range( 'managed', array( 'tier' => 'basic', 'users' => 5, 'servers' => 0 ) ), '$300 – $375' ),
	array( 'IT managed+security 12 users, 1 server', it_range( 'managed', array( 'tier' => 'security', 'users' => 12, 'servers' => 1 ) ), '$3,450 – $4,313' ),
	array( 'IT web brochure, 6-15 pages, SEO', it_range( 'web', array( 'stype' => 'brochure', 'pages' => 'm', 'feat_seo' => 1 ) ), '$299 – $389' ),
	array( 'IT automation workflow, simple, 1 process', it_range( 'automation' ), '$500 – $650' ),
	array( 'IT a mix = scoped on a call', it_range( 'mixed' ), 'contact' ),
);
foreach ( $cases as $c ) {
	check( $c[0] . ' => ' . $c[2], $c[1] === $c[2], 'got ' . $c[1] );
}

echo "\nBooking defaults (pre-selected, visitor can change):\n";
check( 'managed help desk => Systems / help-desk support', 'Systems / help-desk support' === uls_estimator_default_goal( 'managed', 'basic' ) );
check( 'managed security => Security', 'Security' === uls_estimator_default_goal( 'managed', 'security' ) );
check( 'web => New website; automation/mixed => Other', 'New website' === uls_estimator_default_goal( 'web' ) && 'Other' === uls_estimator_default_goal( 'automation' ) && 'Other' === uls_estimator_default_goal( 'mixed' ) );
check( 'site types map to Cloud values', 'Residential / roof' === uls_estimator_default_site_type( 'real_estate', 'small' ) && 'Commercial building' === uls_estimator_default_site_type( 'real_estate', 'large' ) && 'Land / acreage (mapping)' === uls_estimator_default_site_type( 'mapping' ) && 'Other' === uls_estimator_default_site_type( 'tower' ) );
check( 'deliverables derive from answers', array( 'Photos', 'Video' ) === uls_estimator_default_deliverables( 'real_estate', true ) && array( 'Orthomosaic / mapping' ) === uls_estimator_default_deliverables( 'mapping' ) && array( 'Photos', 'Inspection report' ) === uls_estimator_default_deliverables( 'inspection', false, true ) );
$bo = uls_estimator_booking_options();
$ok = true;
foreach ( array( array( 'managed', 'basic' ), array( 'managed', 'security' ), array( 'web', '' ), array( 'automation', '' ), array( 'mixed', '' ) ) as $g ) {
	$ok = $ok && in_array( uls_estimator_default_goal( $g[0], $g[1] ), $bo['goal'], true );
}
foreach ( array( array( 'real_estate', 'small' ), array( 'real_estate', 'large' ), array( 'mapping', '' ), array( 'inspection', '2' ), array( 'tower', '' ) ) as $s ) {
	$ok = $ok && in_array( uls_estimator_default_site_type( $s[0], $s[1] ), $bo['site-type'], true );
}
foreach ( array( 'real_estate', 'mapping', 'inspection', 'tower' ) as $l ) {
	$ok = $ok && ! array_diff( uls_estimator_default_deliverables( $l, true, true ), $bo['deliverables'] );
}
check( 'every default is a whitelisted Cal.com option value', $ok );

echo "\nLocation-neutral + palette:\n";
$dir   = __DIR__ . '/../uplinksync-estimator/';
$blob  = file_get_contents( $dir . 'estimator.js' ) . file_get_contents( $dir . 'markup.php' ) . file_get_contents( $dir . 'config.php' ) . file_get_contents( $dir . 'estimator.css' ) . file_get_contents( __DIR__ . '/../uplinksync-estimator.php' );
$blob .= json_encode( uls_estimator_uav_rates() ) . json_encode( uls_estimator_it_rates() );
$places = array( 'Idaho', 'Falls', 'Kaysville', 'Utah', 'Provo', 'Orem', 'Lehi', 'Boise', 'Denver', 'Pocatello', 'Soda Springs', 'Palisades', 'Saratoga', 'American Fork', 'Pleasant Grove', 'Spanish Fork', 'Springville' );
$hit    = array();
foreach ( $places as $p ) {
	if ( false !== stripos( $blob, $p ) ) {
		$hit[] = $p;
	}
}
check( 'no place names in estimator labels, comments or JS', ! $hit, implode( ',', $hit ) );
check( 'travel label stays "Round-trip travel (miles)"', false !== strpos( file_get_contents( $dir . 'markup.php' ), 'Round-trip travel (miles)' ) );

$lint = @file_get_contents( __DIR__ . '/../../../tools/ci/lint_palette_hex.py' );
if ( $lint && preg_match( '/PALETTE = \{(.*?)\n\}/s', $lint, $m ) && preg_match_all( '/"(#[0-9a-f]{6})"/i', $m[1], $pm ) ) {
	$palette = array_map( 'strtolower', $pm[1] );
	preg_match_all( '/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', preg_replace( '#/\*.*?\*/#s', '', file_get_contents( $dir . 'estimator.css' ) ), $cm );
	$off = array_diff( array_unique( array_map( 'strtolower', $cm[0] ) ), $palette );
	check( 'estimator.css uses palette hexes only (lint_palette_hex PALETTE)', ! $off, implode( ',', $off ) );
} else {
	check( 'palette list readable from tools/ci/lint_palette_hex.py', false );
}
check( 'book.uplinksync.com fallback links opt out of booking-ctas adoption', 2 === substr_count( file_get_contents( $dir . 'markup.php' ), 'data-uls-book-direct="1"' ) );
$js = file_get_contents( $dir . 'estimator.js' );
check( 'fallback link carries no PII (no name/email/notes query)', false === strpos( $js, '"name=" + encodeURIComponent' ) && false === strpos( $js, '"email=" + encodeURIComponent' ) && false === strpos( $js, '"notes=" + encodeURIComponent' ) );
check( 'call length is not hard-coded in the UI (comes from GET /slots lengthMinutes)', false === strpos( $js, 'BOOT.length' ) && false !== strpos( $js, 'j.lengthMinutes' ) );
check( 'every booking trigger routes to the native "Book a call" mode', false !== strpos( $js, 'a[href^="https://book.uplinksync.com/"]' ) && false !== strpos( $js, 'mode: "book"' ) && false !== strpos( file_get_contents( $dir . 'markup.php' ), 'data-pane="b"' ) );
check( 'no royal #0d266b anywhere', false === stripos( $blob, '0d266b' ) );
check( 'no Cal.com embed SDK in the flow', false === strpos( $blob, 'embed/embed.js' ) && false === strpos( file_get_contents( $dir . 'estimator.js' ), 'Cal(' ) );

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "{$failures} FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
