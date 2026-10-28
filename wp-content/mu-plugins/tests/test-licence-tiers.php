<?php
/**
 * Licence tiers: prices, tier text, licence IDs, upgrade maths, certificate PDF structure,
 * licence page v2 wording, palette. Run: php wp-content/mu-plugins/tests/test-licence-tiers.php
 */

error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

$dir = __DIR__ . '/../uplinksync-licence-tiers/';
require $dir . 'config.php';
require $dir . 'pdf.php';
require $dir . 'certificate.php';
require $dir . 'licence-page.php';

$failures = 0;
function check( $name, $cond, $detail = '' ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . ( ! $cond && '' !== $detail ? "  ({$detail})" : '' ) . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}

echo "Tiers (owner decisions 2026-10-28):\n";
$t = uls_lic_tiers();
check( 'four tiers in order personal / commercial-credit / commercial / extended', array( 'personal', 'commercial-credit', 'commercial', 'extended' ) === array_keys( $t ) );
check( 'prices 35 / 79 / 119 / 399', array( 35, 79, 119, 399 ) === array_column( $t, 'price' ) );
check( 'only "with credit" requires credit; Personal requests it', 'required' === $t['commercial-credit']['credit'] && 'requested' === $t['personal']['credit'] && 'optional' === $t['commercial']['credit'] && 'optional' === $t['extended']['credit'] );
check( 'no Exclusive variation (quote only)', ! isset( $t['exclusive'] ) );
check( 'credit line text', 'Aerial by UplinkSync — uplinksync.com' === ULS_LIC_CREDIT_LINE && false !== strpos( uls_lic_credit_text( 'commercial-credit' ), 'Required' ) && false !== strpos( uls_lic_credit_text( 'personal' ), 'Not required' ) );
check( 'licence version ULS-LIC v2.0', 'ULS-LIC v2.0' === ULS_LIC_VERSION );
check( 'G5 download limit 5 / expiry 30 days', 5 === ULS_LIC_DL_LIMIT && 30 === ULS_LIC_DL_EXPIRY );
check( 'Commercial caps print at 5,000; Extended merch at 10,000', false !== strpos( $t['commercial']['allowed'], '5,000' ) && false !== strpos( $t['extended']['allowed'], '10,000' ) );
check( 'Personal excludes business use', false !== stripos( $t['personal']['not'], 'business' ) );
check( 'base restrictions cover resale, logos, AI training, NFTs', 4 === count( uls_lic_base_restrictions() ) && false !== strpos( implode( ' ', uls_lic_base_restrictions() ), 'AI' ) && false !== strpos( implode( ' ', uls_lic_base_restrictions() ), 'NFT' ) );

echo "\nLicence IDs + upgrades:\n";
check( 'id ULS-<order>-<item>', 'ULS-1234-56' === uls_lic_id( '1234', 56 ) );
check( 'id parses back', array( '1234', 56 ) === uls_lic_parse_id( 'ULS-1234-56' ) );
check( 'junk ids rejected', null === uls_lic_parse_id( 'ULS-12;drop-5' ) && null === uls_lic_parse_id( 'X-1-2' ) && null === uls_lic_parse_id( array() ) );
check( 'upgrade = price difference (Personal->Commercial $84, credit->Commercial $40, Commercial->Extended $280)', 84 === uls_lic_upgrade_price( 'personal', 'commercial' ) && 40 === uls_lic_upgrade_price( 'commercial-credit', 'commercial' ) && 280 === uls_lic_upgrade_price( 'commercial', 'extended' ) );
check( 'downgrade / unknown tier = 0', 0 === uls_lic_upgrade_price( 'extended', 'personal' ) && 0 === uls_lic_upgrade_price( 'personal', 'exclusive' ) );
check( 'rank order', 0 === uls_lic_rank( 'personal' ) && 3 === uls_lic_rank( 'extended' ) && -1 === uls_lic_rank( 'nope' ) );

echo "\nCertificate PDF:\n";
$d = array(
	'licence_id'   => 'ULS-1234-56',
	'version'      => ULS_LIC_VERSION,
	'order_number' => '1234',
	'date'         => '28 October 2026',
	'licensee'     => 'Example Realty (Jane O\'Neil) (test)',
	'image_title'  => 'Palisades Reservoir Mountain Vista No. 84',
	'asset_id'     => '811 · palisades',
	'tier'         => 'commercial-credit',
	'terms_url'    => 'https://uplinksync.com/image-license/',
	'verify_url'   => 'https://uplinksync.com/image-license/?verify=ULS-1234-56',
	'thumb_path'   => '',
);
$pdf = uls_lic_certificate_pdf( $d );
check( 'starts %PDF-1.4, ends %%EOF', 0 === strpos( $pdf, '%PDF-1.4' ) && "%%EOF\n" === substr( $pdf, -6 ) );
preg_match( '/startxref\n(\d+)\n%%EOF\n$/', $pdf, $m );
check( 'startxref points at the xref table', isset( $m[1] ) && 'xref' === substr( $pdf, (int) $m[1], 4 ) );
$ok = true;
if ( preg_match( '/xref\n0 (\d+)\n0000000000 65535 f \n((?:\d{10} 00000 n \n)+)/', $pdf, $x ) ) {
	foreach ( explode( "\n", trim( $x[2] ) ) as $i => $row ) {
		$ok = $ok && 0 === strpos( substr( $pdf, (int) substr( $row, 0, 10 ) ), ( $i + 1 ) . ' 0 obj' );
	}
	$ok = $ok && (int) $x[1] === count( explode( "\n", trim( $x[2] ) ) ) + 1;
} else {
	$ok = false;
}
check( 'every xref offset points at its object', $ok );
foreach ( array( 'ULS-1234-56', 'ULS-LIC v2.0', 'Commercial with credit', 'Palisades Reservoir Mountain Vista No. 84', '#1234', '28 October 2026', 'Required with every use', 'Aerial by UplinkSync', 'verify=ULS-1234-56', 'Permitted use' ) as $needle ) {
	check( 'certificate shows "' . $needle . '"', false !== strpos( $pdf, ULS_Lic_Pdf::ansi( $needle ) ) );
}
check( 'parentheses in licensee are escaped', false !== strpos( $pdf, 'Example Realty \\(Jane O\'Neil\\) \\(test\\)' ) );
check( 'no image XObject without a thumbnail', false === strpos( $pdf, '/Im1' ) );
$jpg = sys_get_temp_dir() . '/uls-lic-test-' . getmypid() . '.jpg';
if ( function_exists( 'imagecreatetruecolor' ) ) {
	$im = imagecreatetruecolor( 40, 20 );
	imagejpeg( $im, $jpg );
	$d['thumb_path'] = $jpg;
	$pdf2            = uls_lic_certificate_pdf( $d );
	@unlink( $jpg );
	check( 'thumbnail JPEG embedded as DCTDecode XObject', false !== strpos( $pdf2, '/Filter /DCTDecode' ) && false !== strpos( $pdf2, '/Im1 Do' ) );
}
$wrap = ULS_Lic_Pdf::wrap( str_repeat( 'licence ', 40 ), 10, 200 );
$wide = 0;
foreach ( $wrap as $l ) {
	$wide = max( $wide, ULS_Lic_Pdf::width( $l, 10 ) );
}
check( 'word-wrap keeps lines within width', count( $wrap ) > 3 && $wide <= 200 );
check( 'Personal certificate says credit not required', false !== strpos( uls_lic_certificate_pdf( array_merge( $d, array( 'tier' => 'personal', 'thumb_path' => '' ) ) ), 'Not required' ) );

echo "\nLicence page v2:\n";
$body = uls_lic_page_body();
check( 'single core/html block (no wpautop)', 0 === strpos( $body, '<!-- wp:html -->' ) && false !== strpos( $body, '<!-- /wp:html -->' ) );
check( 'lists all four prices + Exclusive quote', false !== strpos( $body, '$35' ) && false !== strpos( $body, '$79' ) && false !== strpos( $body, '$119' ) && false !== strpos( $body, '$399' ) && false !== strpos( $body, 'Exclusive' ) );
check( 'old single-tier wording gone', false === stripos( $body, 'personal and commercial use' ) && false === stripos( $body, 'Personal + commercial' ) );
check( 'credit format, 14-day cure, upgrades, AI ban, certificate, version', false !== strpos( $body, '14 days' ) && false !== strpos( $body, 'price difference' ) && false !== strpos( $body, 'machine-learning' ) && false !== strpos( $body, 'certificate' ) && false !== strpos( $body, 'ULS-LIC v2.0' ) );
check( 'public body carries no "Draft" marker (admin-only notice)', false === stripos( $body, 'draft' ) && false === stripos( $body, 'legal review' ) );
$main = file_get_contents( __DIR__ . '/../uplinksync-licence-tiers.php' );
check( 'draft notice gated to editors', false !== strpos( $main, "current_user_can( 'edit_pages' )" ) && false !== strpos( $main, 'Draft — pending legal review' ) );
check( 'certificate download needs nonce + ownership + login', false !== strpos( $main, "wp_verify_nonce( sanitize_text_field( wp_unslash( \$_GET['_wpnonce'] ) ), 'uls_lic_pdf_' . \$item_id )" ) && false !== strpos( $main, '(int) $order->get_customer_id() === get_current_user_id()' ) && false !== strpos( $main, '! is_user_logged_in()' ) );
$vb = substr( $main, strpos( $main, 'function uls_lic_verify_box' ) );
$vb = substr( $vb, 0, strpos( $vb, "\n}\n" ) );
check( 'verification never prints the licensee', '' !== $vb && false === stripos( $vb, 'licensee' ) );
check( 'stamping writes nothing on orders without a licence line', false !== strpos( $main, 'return; // Not a licence order: write nothing.' ) );
check( 'checkout fields + v2 page behind uls_licence_tiers_v2 flag', 2 <= substr_count( $main, '! uls_lic_live()' ) );

echo "\nPalette:\n";
$lint = @file_get_contents( __DIR__ . '/../../../tools/ci/lint_palette_hex.py' );
if ( $lint && preg_match( '/PALETTE = \{(.*?)\n\}/s', $lint, $pm0 ) && preg_match_all( '/"(#[0-9a-f]{6})"/i', $pm0[1], $pm ) ) {
	preg_match_all( '/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', preg_replace( '#/\*.*?\*/#s', '', file_get_contents( $dir . 'licence.css' ) ), $cm );
	$off = array_diff( array_unique( array_map( 'strtolower', $cm[0] ) ), array_map( 'strtolower', $pm[1] ) );
	check( 'licence.css uses palette hexes only', ! $off, implode( ',', $off ) );
	preg_match_all( '/#[0-9a-fA-F]{6}\b/', file_get_contents( $dir . 'certificate.php' ), $cm2 );
	$off2 = array_diff( array_unique( array_map( 'strtolower', $cm2[0] ) ), array_map( 'strtolower', $pm[1] ) );
	check( 'certificate colours on palette', ! $off2, implode( ',', $off2 ) );
} else {
	check( 'palette list readable', false );
}
check( 'no royal #0d266b', false === stripos( file_get_contents( $dir . 'licence.css' ) . $main, '0d266b' ) );

echo "\n" . ( 0 === $failures ? "ALL PASS\n" : "{$failures} FAILURE(S)\n" );
exit( 0 === $failures ? 0 : 1 );
