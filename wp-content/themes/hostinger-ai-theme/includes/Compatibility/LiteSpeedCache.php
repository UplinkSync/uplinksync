<?php

namespace Hostinger\AiTheme\Compatibility;

defined( 'ABSPATH' ) || exit;

class LiteSpeedCache {
    public function __construct() {
        if ( ! defined( 'LSCWP_V' ) ) {
            return;
        }

        add_action( 'init', array( $this, 'setup_woocommerce_compatibility' ) );
        add_action( 'woocommerce_ajax_get_refreshed_fragments', array( $this, 'set_nocache' ), 1 );
    }

    public function setup_woocommerce_compatibility(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_action( 'woocommerce_ajax_added_to_cart', array( $this, 'set_nocache' ) );
        add_action( 'woocommerce_add_to_cart', array( $this, 'set_nocache' ) );
        add_action( 'woocommerce_cart_updated', array( $this, 'set_nocache' ) );
    }

    public function set_nocache(): void {
        if ( has_action( 'litespeed_control_set_nocache' ) ) {
            do_action( 'litespeed_control_set_nocache', 'WooCommerce cart action' );
        }
    }
}
