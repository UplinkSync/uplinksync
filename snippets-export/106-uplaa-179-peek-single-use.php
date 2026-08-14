<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 106
 * name  : UPLAA-179 peek (single-use)
 * scope : global
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

add_action('rest_api_init', function(){
  register_rest_route('uplaa179verify/v1','/peek', array('methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_woocommerce');},
   'callback'=>function($r){ $p=get_current_user_id(); wp_set_current_user(5); $h=do_shortcode('[uplaa_client_album slug="aurumgrove"]'); wp_set_current_user($p);
     // pull first card CTA href
     if(preg_match('/href="([^"]*contact[^"]*)"/',$h,$m)){ $href=$m[1]; } else {$href='NONE';}
     $wm = (strpos($h,'ag-proof-')!==false); $master=(strpos($h,'-master-')!==false);
     return array('first_cta_href'=>$href,'uses_watermarked_previews'=>$wm,'references_any_master'=>$master);
   }));
});
