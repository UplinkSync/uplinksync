<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 105
 * name  : UPLAA-179 verify render harness (single-use)
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
  register_rest_route('uplaa179verify/v1','/render', array(
    'methods'=>'GET',
    'permission_callback'=>function(){ return current_user_can('manage_woocommerce'); },
    'callback'=>function($req){
      $uid=(int)$req->get_param('user');
      $slug=sanitize_key($req->get_param('album'));
      $prev=get_current_user_id();
      wp_set_current_user($uid);
      $html=do_shortcode('[uplaa_client_album slug="'.$slug.'"]');
      wp_set_current_user($prev);
      // classify
      $has_grid = (strpos($html,'uplaa179-grid')!==false);
      $imgcount = substr_count($html,'uplaa179-card__title');
      $denied = (strpos($html,'belongs to another client')!==false);
      $signin = (strpos($html,'Client sign-in required')!==false);
      $cta = (strpos($html,'Request this frame')!==false);
      return array('user'=>$uid,'album'=>$slug,'has_grid'=>$has_grid,'frame_cards'=>$imgcount,'denied_notice'=>$denied,'signin_notice'=>$signin,'quote_cta'=>$cta,'len'=>strlen($html));
    }
  ));
});
