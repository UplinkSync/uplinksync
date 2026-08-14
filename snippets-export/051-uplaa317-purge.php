<?php
/**
 * MIRROR OF A DATABASE-RESIDENT SNIPPET - DO NOT EDIT HERE, DO NOT LOAD.
 *
 * id    : 51
 * name  : UPLAA317 purge
 * scope : single-use
 * state : inactive
 *
 * Exported from wp_snippets on the live Hostinger host, 2026-08-14.
 * The AUTHORITATIVE copy is the database row; editing this file changes NOTHING on the site.
 * This mirror exists so ~173 KB of otherwise invisible PHP is reviewable, diffable and
 * secret-scannable. See ecosystem-docs current/117-detail-uplinksync-web-database-resident-code.md
 *
 */

 do_action("litespeed_purge_url", home_url("/")); if(function_exists("do_action")){do_action("litespeed_purge_post",278);} do_action("litespeed_purge_all"); file_put_contents(wp_upload_dir()["basedir"]."/uplaa317-purge.json", json_encode(["purged"=>true,"home"=>home_url("/"),"time"=>time()]));
