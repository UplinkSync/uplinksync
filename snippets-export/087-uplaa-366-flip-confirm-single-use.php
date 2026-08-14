<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 87
 * name  : UPLAA-366 flip confirm (single-use)
 * scope : single-use
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

update_option('woocommerce_coming_soon','no');
update_option('woocommerce_store_pages_only','no');
update_option('woocommerce_private_link','no');
global $wpdb;
$raw=array();
foreach (array('woocommerce_coming_soon','woocommerce_store_pages_only','woocommerce_private_link') as $k){
  $raw[$k]=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$k));
}
update_option('uls_flip_confirm', wp_json_encode($raw), false);
