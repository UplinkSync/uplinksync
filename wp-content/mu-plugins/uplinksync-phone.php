<?php
/**
 * Plugin Name: UplinkSync — Public phone number (single source of truth)
 * Description: One switch for the published business phone: the UPLINKSYNC_PHONE constant (wp-config.php) or, if that is not defined, the `uplinksync_phone` option. The value is VALIDATED here once (E.164 + display format); anything invalid or empty = no phone anywhere (header, footer, contact page, JSON-LD). A last-priority JSON-LD pass makes every `telephone` equal the validated number (or removes it), whichever other filter wrote it first. The location-neutral layer lets exactly this number through and still strips the retired Idaho number.
 * Version: 1.1.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Raw configured value: the constant wins (even empty, so wp-config can force it off),
 * otherwise the autoloaded option. Never output directly — use the validated helpers.
 *
 * @return string
 */
function uplinksync_phone_raw() {
	if ( defined( 'UPLINKSYNC_PHONE' ) ) {
		$raw = UPLINKSYNC_PHONE;
	} elseif ( function_exists( 'get_option' ) ) {
		$raw = get_option( 'uplinksync_phone', '' );
	} else {
		$raw = '';
	}
	return is_string( $raw ) ? trim( strip_tags( $raw ) ) : '';
}

/**
 * The published phone in E.164 (`+18015550134`), or '' when unset or invalid.
 *
 * Accepted: a 10-digit North American number (optionally with a leading 1 / +1), or an
 * explicit international number written with a leading "+" and 8–15 digits. Letters or
 * other junk ("TBD", "call us", "555-0100") make the value invalid, never half-published.
 *
 * @return string
 */
function uplinksync_phone_e164() {
	$raw = uplinksync_phone_raw();
	if ( '' === $raw || preg_match( '/[^\d\s()+.\-]/', $raw ) ) {
		return '';
	}
	$digits = preg_replace( '/\D+/', '', $raw );
	if ( 10 === strlen( $digits ) && ! in_array( $digits[0], array( '0', '1' ), true ) ) {
		return '+1' . $digits;
	}
	if ( 11 === strlen( $digits ) && '1' === $digits[0] && ! in_array( $digits[1], array( '0', '1' ), true ) ) {
		return '+' . $digits;
	}
	if ( '+' === $raw[0] && '1' !== $digits[0] && strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ) {
		return '+' . $digits;
	}
	return '';
}

/**
 * Display form of the published phone — "(801) 555-0134" for North American numbers, the
 * E.164 string otherwise — or '' when unset/invalid. Always derived from the validated
 * E.164 value, never from the raw input.
 *
 * @return string
 */
function uplinksync_phone() {
	$e164 = uplinksync_phone_e164();
	if ( 12 === strlen( $e164 ) && 0 === strpos( $e164, '+1' ) ) {
		return sprintf( '(%s) %s-%s', substr( $e164, 2, 3 ), substr( $e164, 5, 3 ), substr( $e164, 8 ) );
	}
	return $e164;
}

/**
 * True when $value (a tel: href, display text or schema value) is the published number.
 *
 * @param string $value Any phone representation.
 * @return bool
 */
function uplinksync_phone_is_public( $value ) {
	$public = ltrim( uplinksync_phone_e164(), '+' );
	if ( '' === $public ) {
		return false;
	}
	$digits = preg_replace( '/\D+/', '', (string) $value );
	if ( 10 === strlen( $digits ) ) {
		$digits = '1' . $digits;
	}
	return $digits === $public;
}

/**
 * JSON-LD: every `telephone` becomes the validated number, or is removed when no valid
 * number is published. Runs LAST on rank_math/json_ld, so it also corrects values written
 * by other layers (the theme's Organization enrichment, the location switch's org extras or
 * its Utah ProfessionalService node), whichever order the MRs merge in. A ContactPoint left
 * without any contact route is dropped (invalid schema).
 *
 * @param mixed $value Schema graph or node.
 * @return mixed
 */
function uplinksync_phone_schema( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	if ( array_key_exists( 'telephone', $value ) ) {
		$phone = uplinksync_phone_e164();
		if ( '' === $phone ) {
			unset( $value['telephone'] );
		} else {
			$value['telephone'] = $phone;
		}
	}
	foreach ( $value as $prop => $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$item = uplinksync_phone_schema( $item );
		if ( 'contactPoint' === $prop && isset( $item['@type'] ) && empty( $item['telephone'] ) && empty( $item['email'] ) && empty( $item['url'] ) ) {
			unset( $value[ $prop ] );
			continue;
		}
		$value[ $prop ] = $item;
	}
	return $value;
}
if ( function_exists( 'add_filter' ) ) {
	add_filter( 'rank_math/json_ld', 'uplinksync_phone_schema', PHP_INT_MAX );
}
