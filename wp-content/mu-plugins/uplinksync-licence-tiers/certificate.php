<?php
/**
 * Licence certificate layout (pure: data array in, PDF bytes out). See uls_lic_certificate_data()
 * in ../uplinksync-licence-tiers.php for how the data is snapshotted from an order line.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render one certificate.
 *
 * @param array $d licence_id, version, order_number, date, licensee, image_title, asset_id, tier,
 *                 verify_url, terms_url, thumb_path (optional, JPEG).
 * @return string PDF bytes.
 */
function uls_lic_certificate_pdf( array $d ) {
	$tiers = uls_lic_tiers();
	$t     = isset( $tiers[ $d['tier'] ] ) ? $tiers[ $d['tier'] ] : $tiers['personal'];
	$ink   = '#173258';
	$muted = '#46628a';
	$line  = '#dbe2eb';
	$pdf   = new ULS_Lic_Pdf();

	// Header band.
	$pdf->rect( 0, 0, ULS_Lic_Pdf::W, 92, $ink );
	$pdf->text( 48, 42, 'UplinkSync', 22, true, '#ffffff' );
	$pdf->text( 48, 66, 'Image licence certificate', 13, false, '#b2c6e2' );
	$pdf->text( 400, 42, $d['licence_id'], 12, true, '#ffffff' );
	$pdf->text( 400, 66, $d['version'], 10, false, '#b2c6e2' );

	$y     = 130;
	$textw = 330;
	$thumb = ! empty( $d['thumb_path'] ) ? $pdf->jpeg( $d['thumb_path'], 396, 116, 168, 168 ) : false;

	$rows = array(
		'Licensee'     => $d['licensee'],
		'Image'        => $d['image_title'],
		'Image ID'     => $d['asset_id'],
		'Licence tier' => $t['label'],
		'Order'        => '#' . $d['order_number'],
		'Date issued'  => $d['date'],
	);
	foreach ( $rows as $k => $v ) {
		$pdf->text( 48, $y, strtoupper( $k ), 8, true, $muted );
		$y = $pdf->para( 48, $y + 15, (string) $v, 12, $textw, 'Licence tier' === $k, $ink );
		$y += 8;
	}
	$y = max( $y, $thumb ? 116 + $thumb[1] + 18 : 0 );

	$sections = array(
		'Permitted use'      => $t['allowed'],
		'Not permitted'      => $t['not'] . ' ' . implode( ' ', uls_lic_base_restrictions() ),
		'Credit line'        => uls_lic_credit_text( $d['tier'] ),
	);
	foreach ( $sections as $k => $v ) {
		$pdf->line( 48, $y, ULS_Lic_Pdf::W - 48, $y, $line );
		$y += 20;
		$pdf->text( 48, $y, $k, 11, true, $ink );
		$y = $pdf->para( 48, $y + 16, $v, 10, ULS_Lic_Pdf::W - 96, false, $ink ) + 8;
	}

	$pdf->line( 48, $y, ULS_Lic_Pdf::W - 48, $y, $line );
	$y = $pdf->para(
		48,
		$y + 20,
		'UplinkSync keeps the copyright. This certificate grants the licensee named above a perpetual, worldwide, non-exclusive, non-transferable licence to use the image named above for the uses its tier allows, under the UplinkSync Image Licence ' . $d['version'] . '. You may edit, crop and colour the file. Upgrade to a higher tier any time for the current price difference; a new certificate then replaces this one.',
		9,
		ULS_Lic_Pdf::W - 96,
		false,
		$muted
	);
	$y = $pdf->para( 48, $y + 6, 'Full terms: ' . $d['terms_url'], 9, ULS_Lic_Pdf::W - 96, false, $muted );
	$pdf->para( 48, $y + 2, 'Verify this licence: ' . $d['verify_url'], 9, ULS_Lic_Pdf::W - 96, false, $muted );

	$pdf->rect( 0, ULS_Lic_Pdf::H - 6, ULS_Lic_Pdf::W, 6, '#0c5fc1' );
	return $pdf->output( 'UplinkSync image licence ' . $d['licence_id'] );
}
