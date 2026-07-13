<?php
/**
 * Plugin Name: Uplink Sync - Allow App-Password REST Access
 * Description: Admin Site Enhancements' "Disable REST API" toggle blocks the REST API for non-authenticated users, but its own check does not recognize Application-Password-authenticated requests as authenticated, so they were being rejected identically to anonymous ones. This filter only lets a request through when WordPress core's own Application-Password authentication (via the determine_current_user filter) actually resolves a real user for that request. Genuinely anonymous requests are left blocked exactly as before.
 * Version: 1.0.0
 * Author: Uplink Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('rest_authentication_errors', function ($result) {
    if (!is_wp_error($result)) {
        return $result;
    }

    $user_id = apply_filters('determine_current_user', false);
    if ($user_id && get_userdata($user_id)) {
        wp_set_current_user($user_id);
        return true;
    }

    return $result;
}, PHP_INT_MAX);
