<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 88
 * name  : UPLAA-366 raw read (single-use)
 * scope : single-use
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

global $wpdb;
$raw=array();
foreach (array('woocommerce_coming_soon','woocommerce_store_pages_only','woocommerce_private_link') as $k){
  $raw[$k]=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$k));
}
$raw['shop_gate_fn_exists']=function_exists('uplinksync_shop_gate_redirects')?'yes':'no';
$raw['quote_only_fn_exists']=function_exists('uplinksync_quote_only_active')?'yes':'no';
$raw['collection_mask_removed']= has_filter('pre_option_woocommerce_coming_soon')===false ? 'yes' : 'no';
update_option('uls_rawread', wp_json_encode($raw), false);
