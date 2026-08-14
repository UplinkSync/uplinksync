<?php
/**
 * ULS DRM v1 — encrypted-HLS hero reel (endpoints + player)
 *
 * Migrated from database-resident Code Snippets row id=112 (DR-004 tranche 2).
 * scope: global   priority: 6
 *
 * Serves /wp-json/uls-drm/v1/reel.m3u8 (fresh signed key token per load, no-store) and /wp-json/uls-drm/v1/key (HMAC-token + Referer/Origin gated, returns raw 16-byte AES-128 key, 403 otherwise). Enqueues local hls.js and inits video[data-uls-hls] on /drone-services/. AES key + HMAC secret live in wp_
 *
 * Migrated VERBATIM. Any behaviour change to this snippet is a separate commit, so
 * that the migration itself can be proven byte-identical in rendered output.
 *
 * The wp_snippets row is DEACTIVATED, not deleted - re-activating it in wp-admin
 * is the rollback and needs no deploy. Exactly one copy must be active at a time.
 */
defined( 'ABSPATH' ) || exit;

if(!function_exists('uls_drm_secret')){
  function uls_drm_secret(){
    $s = get_option('uls_drm_hmac_secret');
    if(!$s){ $s = base64_encode(random_bytes(32)); update_option('uls_drm_hmac_secret', $s, false); }
    return base64_decode($s);
  }
}
if(!function_exists('uls_drm_mint')){
  function uls_drm_mint(){
    $exp = time() + 300;
    $nonce = bin2hex(random_bytes(6));
    $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $exp.'.'.$nonce, uls_drm_secret(), true)), '+/', '-_'), '=');
    return $exp.'.'.$nonce.'.'.$sig;
  }
}
if(!function_exists('uls_drm_valid')){
  function uls_drm_valid($t){
    if(!is_string($t)) return false;
    $p = explode('.', $t);
    if(count($p) !== 3) return false;
    list($exp,$nonce,$sig) = $p;
    if(!ctype_digit($exp) || $nonce==='' || !ctype_alnum($nonce)) return false;
    $exp = (int)$exp; $now = time();
    if($exp < $now || $exp > $now + 3600) return false;
    $calc = rtrim(strtr(base64_encode(hash_hmac('sha256', $exp.'.'.$nonce, uls_drm_secret(), true)), '+/', '-_'), '=');
    return hash_equals($calc, $sig);
  }
}
if(!function_exists('uls_drm_nocache')){
  function uls_drm_nocache(){
    if(!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    if(function_exists('nocache_headers')) nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('X-LiteSpeed-Cache-Control: no-cache, esi=off');
    header('X-Accel-Expires: 0');
    header('CDN-Cache-Control: no-store');
    header('Cloudflare-CDN-Cache-Control: no-store');
  }
}
add_filter('rest_authentication_errors', function($result){
  $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
  if (strpos($uri, '/uls-drm/v1/') !== false) { return true; }
  return $result;
}, 9999);
add_action('rest_api_init', function () {
  register_rest_route('uls-drm/v1', '/reel.m3u8', array(
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function($r){
      $u = wp_upload_dir();
      $tpl = @file_get_contents(rtrim($u['basedir'],'/').'/drm/reel/playlist.m3u8');
      uls_drm_nocache();
      if($tpl===false){ status_header(404); header('Content-Type: text/plain'); echo 'no playlist'; exit; }
      $keyurl = home_url('/wp-json/uls-drm/v1/key?t=' . uls_drm_mint());
      $tpl = str_replace('__ULS_KEY_URI__', $keyurl, $tpl);
      $base = rtrim($u['baseurl'],'/').'/drm/reel/';
      $lines = explode("\n", $tpl);
      foreach($lines as &$ln){ $t = trim($ln); if($t!=='' && $t[0]!=='#' && substr($t,0,4)==='seg_'){ $ln = $base.$t; } }
      unset($ln);
      $out = implode("\n", $lines);
      header('Content-Type: application/vnd.apple.mpegurl');
      header('Content-Length: '.strlen($out));
      echo $out; exit;
    },
  ));
  register_rest_route('uls-drm/v1', '/key', array(
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function($r){
      uls_drm_nocache();
      $t = (string)$r->get_param('t');
      $host = '';
      if(!empty($_SERVER['HTTP_ORIGIN'])) $host = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
      elseif(!empty($_SERVER['HTTP_REFERER'])) $host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
      $hostok = ($host==='uplinksync.com' || $host==='www.uplinksync.com');
      if(!uls_drm_valid($t) || !$hostok){ status_header(403); header('Content-Type: text/plain'); echo 'forbidden'; exit; }
      $key = base64_decode(get_option('uls_drm_aes_key_b64'), true);
      if($key===false || strlen($key)!==16){ status_header(500); header('Content-Type: text/plain'); echo 'nokey'; exit; }
      header('Content-Type: application/octet-stream');
      header('Content-Length: 16');
      echo $key; exit;
    },
  ));
});
add_action('wp_footer', function(){
  $u = wp_upload_dir();
  $lib = esc_url(rtrim($u['baseurl'],'/').'/drm/hls.min.js');
  echo '<script src="'.$lib.'" id="uls-hls-lib"></script>'."\n";
  echo '<script id="uls-drm-init">(function(){function init(){var vids=document.querySelectorAll("video[data-uls-hls]");if(!vids.length)return;vids.forEach(function(v){var src=v.getAttribute("data-uls-hls");if(v.canPlayType("application/vnd.apple.mpegurl")){return;}if(window.Hls&&window.Hls.isSupported()){var s=v.querySelector("source");if(s){s.remove();}var hls=new Hls();hls.loadSource(src);hls.attachMedia(v);}});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}})();</script>'."\n";
}, 20);
