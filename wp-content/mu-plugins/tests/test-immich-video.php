<?php
/**
 * Standalone tests for [immich_video] (themes/uplinksync-child/inc/immich-embed.php):
 * the default click-to-play player and the loop="1" ambient mode used by the drone page's
 * vertical/social cuts, plus a guard that the drone pattern wires the right posters.
 *
 * Run: php wp-content/mu-plugins/tests/test-immich-video.php
 */

error_reporting( E_ALL );

define( 'ABSPATH', __DIR__ );
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function add_shortcode( ...$a ) {}
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, array_intersect_key( (array) $atts, $pairs ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_enqueue_style( ...$a ) {}
function get_stylesheet_directory_uri() { return 'https://example.test/wp-content/themes/uplinksync-child'; }
function uplinksync_child_asset_ver( $p ) { return '1'; }

require __DIR__ . '/../../themes/uplinksync-child/inc/immich-embed.php';

$failures = 0;
function iv_check( $name, $cond ) {
	global $failures;
	echo ( $cond ? '  PASS  ' : '  FAIL  ' ) . $name . "\n";
	if ( ! $cond ) {
		$failures++;
	}
}

$asset = 'b688e5d2-aa13-49a8-9683-70400bd89196';
$share = 'https://media.uplinksync.com/share/abc_DEF-123';
$base  = array( 'asset' => $asset, 'share' => $share, 'poster' => '/wp-content/x.webp', 'label' => 'Clip' );

// Default (reel): native controls, click to play, nothing downloaded up front.
$reel = uplinksync_immich_video_shortcode( $base );
iv_check( 'default player keeps native controls', false !== strpos( $reel, ' controls ' ) );
iv_check( 'default player preload none', false !== strpos( $reel, 'preload="none"' ) );
iv_check( 'default player is not muted/looping', false === strpos( $reel, 'muted' ) && false === strpos( $reel, ' loop' ) );
iv_check( 'default player not marked for the loop script', false === strpos( $reel, 'data-uls-loop' ) );
iv_check( 'poster kept', false !== strpos( $reel, 'poster="/wp-content/x.webp"' ) );
iv_check( 'MP4 source on the media host', false !== strpos( $reel, 'src="https://media.uplinksync.com/api/assets/' . $asset . '/video/playback?key=abc_DEF-123" type="video/mp4"' ) );

// loop="1" (vertical/social): silent ambient loop, no controls, JS-driven start.
$loop = uplinksync_immich_video_shortcode( $base + array( 'loop' => '1', 'ratio' => '9/16' ) );
iv_check( 'loop player has NO controls attribute', ! preg_match( '/<video[^>]*\scontrols[\s>]/', $loop ) );
iv_check( 'loop player muted + loop + playsinline', false !== strpos( $loop, 'muted loop playsinline' ) );
iv_check( 'loop player preload metadata', false !== strpos( $loop, 'preload="metadata"' ) );
iv_check( 'loop player has no HTML autoplay (reduced motion is decided in JS)', false === strpos( $loop, 'autoplay' ) );
iv_check( 'loop player marked data-uls-loop', false !== strpos( $loop, 'data-uls-loop' ) );
iv_check( 'loop figure gets the modifier class', false !== strpos( $loop, 'uls-immich-video--loop' ) );
iv_check( 'loop player keeps an accessible name', false !== strpos( $loop, 'aria-label="Clip"' ) );
iv_check( 'loop player keeps its poster', false !== strpos( $loop, 'poster="/wp-content/x.webp"' ) );
iv_check( 'loop="0" stays the default player', false !== strpos( uplinksync_immich_video_shortcode( $base + array( 'loop' => '0' ) ), ' controls ' ) );

// src="..." light rendition: first <source>, Immich stream kept as fallback.
$lt = uplinksync_immich_video_shortcode( $base + array( 'loop' => '1', 'src' => '/wp-content/themes/uplinksync-child/assets/media/video/a.mp4' ) );
iv_check( 'light src is the first source', 1 === preg_match( '#<source src="/wp-content/themes/uplinksync-child/assets/media/video/a\.mp4" type="video/mp4"><source src="https://media\.uplinksync\.com/#', $lt ) );
foreach ( array( 'https://evil.test/a.mp4', '//evil.test/wp-content/themes/a.mp4', '/wp-content/themes/../../wp-config.mp4', '/wp-content/plugins/x.mp4', '/wp-content/themes/a.mp4?x=1', '/wp-content/themes/a.php' ) as $bad ) {
	iv_check( "bad light src dropped: $bad", '' === uplinksync_immich_light_src( $bad ) );
}
iv_check( 'no src -> single Immich source', 1 === substr_count( $reel, '<source ' ) );

// Fail closed.
iv_check( 'bad asset -> comment only', 0 === strpos( uplinksync_immich_video_shortcode( array( 'asset' => 'nope', 'share' => $share, 'loop' => '1' ) ), '<!--' ) );

// Drone pattern: footage-cut posters, verticals looped, reel click-to-play.
$pat = file_get_contents( __DIR__ . '/../../themes/uplinksync-child/patterns/site-drone.php' );
iv_check( 'pattern: no AI vertical posters left', false === strpos( $pat, 'uplinksync-vertical-' ) );
iv_check( 'pattern: reel uses its own poster', false !== strpos( $pat, 'uplinksync-reel-poster-1280.webp' ) );
foreach ( array( 'forest', 'valley', 'town' ) as $n ) {
	iv_check( "pattern: clip-$n poster wired", false !== strpos( $pat, "uplinksync-clip-$n-460.webp" ) );
	iv_check( "poster file clip-$n exists and <= 60 KB", ( $sz = @filesize( __DIR__ . "/../../themes/uplinksync-child/assets/images/site/uplinksync-clip-$n-460.webp" ) ) && $sz <= 60000 );
}
$rp = @filesize( __DIR__ . '/../../themes/uplinksync-child/assets/images/site/uplinksync-reel-poster-1280.webp' );
iv_check( 'reel poster exists and <= 150 KB', $rp && $rp <= 150000 );
iv_check( 'pattern: three looped 9:16 verticals', 3 === preg_match_all( "/uplinksync-clip-[a-z]+-460\\.webp', '9\\/16', '[^']+', true \\)/", $pat ) );
iv_check( 'pattern: reel stays click-to-play', 1 === preg_match( "/uplinksync-reel-poster-1280\\.webp', '16\\/9', '[^']+' \\)/", $pat ) );
// Light renditions: present, wired, within budget (loops <= 4 MB, reel <= 16 MB), MP4 faststart (moov before mdat).
$vdir = __DIR__ . '/../../themes/uplinksync-child/assets/media/video/';
foreach ( array( 'uplinksync-loop-forest-720x1280.mp4' => 4000000, 'uplinksync-loop-valley-720x1280.mp4' => 4000000, 'uplinksync-loop-town-720x1280.mp4' => 4000000, 'uplinksync-reel-1080p.mp4' => 16000000 ) as $f => $max ) {
	$sz = @filesize( $vdir . $f );
	iv_check( "light $f wired in pattern", false !== strpos( $pat, "'$f'" ) );
	iv_check( "light $f exists and <= $max bytes", $sz && $sz <= $max );
	$head = (string) @file_get_contents( $vdir . $f, false, null, 0, 4096 );
	iv_check( "light $f is faststart", false !== strpos( $head, 'moov' ) );
}

echo $failures ? "\n$failures FAILED\n" : "\nALL PASS\n";
exit( $failures ? 1 : 0 );
