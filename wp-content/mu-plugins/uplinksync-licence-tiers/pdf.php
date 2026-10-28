<?php
/**
 * Minimal single-page PDF 1.4 writer (no dependencies, no paid plugin): Helvetica / Helvetica-Bold
 * text in WinAnsi, filled rectangles, lines and one optional baseline JPEG. Enough for a licence
 * certificate; deliberately tiny so it can be unit-tested without WordPress.
 */

defined( 'ABSPATH' ) || exit;

class ULS_Lic_Pdf {
	const W = 612; // US Letter, points.
	const H = 792;

	/** @var string[] */
	private $ops = array();
	/** @var array|null */
	private $jpeg = null;

	/** Helvetica widths for chars 32..126 (1/1000 em). */
	private static $wr = array( 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584 );
	/** Helvetica-Bold widths for chars 32..126. */
	private static $wb = array( 278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584 );

	/** UTF-8 -> WinAnsi (cp1252), unknown chars become '?'. */
	public static function ansi( $s ) {
		$s = (string) $s;
		if ( function_exists( 'iconv' ) ) {
			$r = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $s );
			if ( false !== $r ) {
				return $r;
			}
		}
		return preg_replace( '/[^\x20-\x7e]/', '?', $s );
	}

	/** Width in points of UTF-8 text. */
	public static function width( $s, $size, $bold = false ) {
		$t = $bold ? self::$wb : self::$wr;
		$a = self::ansi( $s );
		$w = 0;
		for ( $i = 0, $n = strlen( $a ); $i < $n; $i++ ) {
			$o  = ord( $a[ $i ] );
			$w += ( $o >= 32 && $o <= 126 ) ? $t[ $o - 32 ] : ( 0x97 === $o ? 1000 : ( 0x96 === $o ? 556 : 600 ) );
		}
		return $w * $size / 1000;
	}

	/** Greedy word-wrap to a width in points. */
	public static function wrap( $s, $size, $max, $bold = false ) {
		$lines = array();
		foreach ( preg_split( "/\n/", (string) $s ) as $para ) {
			$cur = '';
			foreach ( preg_split( '/\s+/', trim( $para ) ) as $word ) {
				$try = '' === $cur ? $word : $cur . ' ' . $word;
				if ( '' !== $cur && self::width( $try, $size, $bold ) > $max ) {
					$lines[] = $cur;
					$cur     = $word;
				} else {
					$cur = $try;
				}
			}
			$lines[] = $cur;
		}
		return $lines;
	}

	private static function esc( $s ) {
		return str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', '', ' ' ), self::ansi( $s ) );
	}

	private static function rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		return sprintf( '%.3F %.3F %.3F', hexdec( substr( $hex, 0, 2 ) ) / 255, hexdec( substr( $hex, 2, 2 ) ) / 255, hexdec( substr( $hex, 4, 2 ) ) / 255 );
	}

	/** Text at (x, y-from-top). */
	public function text( $x, $y, $s, $size = 10, $bold = false, $hex = '#173258' ) {
		$this->ops[] = sprintf( 'BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', self::rgb( $hex ), $bold ? 'F2' : 'F1', $size, $x, self::H - $y, self::esc( $s ) );
	}

	/** Wrapped text; returns the y below the last line. */
	public function para( $x, $y, $s, $size, $max, $bold = false, $hex = '#173258', $lead = 1.35 ) {
		foreach ( self::wrap( $s, $size, $max, $bold ) as $line ) {
			$this->text( $x, $y, $line, $size, $bold, $hex );
			$y += $size * $lead;
		}
		return $y;
	}

	public function rect( $x, $y, $w, $h, $hex ) {
		$this->ops[] = sprintf( '%s rg %.2F %.2F %.2F %.2F re f', self::rgb( $hex ), $x, self::H - $y - $h, $w, $h );
	}

	public function line( $x1, $y1, $x2, $y2, $hex, $w = 0.75 ) {
		$this->ops[] = sprintf( '%s RG %.2F w %.2F %.2F m %.2F %.2F l S', self::rgb( $hex ), $w, $x1, self::H - $y1, $x2, self::H - $y2 );
	}

	/** Place a baseline JPEG (file path) in a box; returns false if not a usable JPEG. */
	public function jpeg( $path, $x, $y, $maxw, $maxh ) {
		$info = @getimagesize( $path );
		if ( ! $info || IMAGETYPE_JPEG !== $info[2] || null !== $this->jpeg ) {
			return false;
		}
		$ch = isset( $info['channels'] ) ? (int) $info['channels'] : 3;
		$cs = 1 === $ch ? '/DeviceGray' : ( 4 === $ch ? '/DeviceCMYK' : '/DeviceRGB' );
		$sc = min( $maxw / $info[0], $maxh / $info[1] );
		$w  = $info[0] * $sc;
		$h  = $info[1] * $sc;
		$this->jpeg  = array( 'data' => file_get_contents( $path ), 'w' => $info[0], 'h' => $info[1], 'cs' => $cs, 'cmyk' => 4 === $ch );
		$this->ops[] = sprintf( 'q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q', $w, $h, $x, self::H - $y - $h );
		return array( $w, $h );
	}

	/** Serialise to a PDF byte string. */
	public function output( $title = '' ) {
		$content = implode( "\n", $this->ops );
		$objs    = array();
		$objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
		$xobj    = $this->jpeg ? ' /XObject << /Im1 7 0 R >>' : '';
		$objs[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . '] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >>' . $xobj . ' >> >>';
		$objs[4] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream";
		$objs[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objs[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		if ( $this->jpeg ) {
			$j       = $this->jpeg;
			$objs[7] = '<< /Type /XObject /Subtype /Image /Width ' . $j['w'] . ' /Height ' . $j['h'] . ' /ColorSpace ' . $j['cs'] . ' /BitsPerComponent 8 /Filter /DCTDecode' . ( $j['cmyk'] ? ' /Decode [1 0 1 0 1 0 1 0]' : '' ) . ' /Length ' . strlen( $j['data'] ) . " >>\nstream\n" . $j['data'] . "\nendstream";
		}
		$info               = count( $objs ) + 1;
		$objs[ $info ]      = '<< /Title (' . self::esc( $title ) . ') /Producer (UplinkSync licence certificate) >>';
		$pdf                = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$off                = array();
		foreach ( $objs as $n => $body ) {
			$off[ $n ] = strlen( $pdf );
			$pdf      .= $n . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= 'xref' . "\n0 " . ( count( $objs ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $off as $o ) {
			$pdf .= sprintf( "%010d 00000 n \n", $o );
		}
		$pdf .= 'trailer << /Size ' . ( count( $objs ) + 1 ) . ' /Root 1 0 R /Info ' . $info . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
		return $pdf;
	}
}
