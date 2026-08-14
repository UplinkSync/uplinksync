<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 90
 * name  : UPLAA-366 ASE rest inspect (single-use)
 * scope : single-use
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

$opt = get_option('admin_site_enhancements');
$out = array();
if (is_array($opt)) {
  foreach ($opt as $k=>$v){
    if (stripos($k,'rest')!==false || stripos($k,'api')!==false){
      $out[$k] = is_scalar($v)?$v:json_decode(wp_json_encode($v),true);
    }
  }
}
$out['__all_keys'] = is_array($opt)? array_keys($opt) : 'not-array';
update_option('uls_ase_inspect', wp_json_encode($out), false);
