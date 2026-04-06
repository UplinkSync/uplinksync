<?php

namespace Hostinger\AiTheme\Admin;

use Hostinger\AiTheme\Admin\Menu as AdminMenu;
use Hostinger\AiTheme\Admin\Surveys\RateAiSite;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\WpHelper\Utils;
use Hostinger\WpMenuManager\Menus;

defined( 'ABSPATH' ) || exit;

class Assets {
    private CONST WOO_I18N_PATH = HOSTINGER_AI_WEBSITES_THEME_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'Builder' . DIRECTORY_SEPARATOR . 'Woo' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR;
    private const VALID_WEBSITE_TYPES = array(
        'business',
        'online store',
        'blog',
        'landing page',
        'booking',
        'portfolio',
        'affiliate-marketing',
        'other',
    );
    private Utils $utils;
    private ?RateAiSite $rateAiSite;

    public function __construct( Utils $utils, ?RateAiSite $rateAiSite = null ) {
        $this->utils = $utils;
        $this->rateAiSite = $rateAiSite;

        $admin_path = parse_url( admin_url(), PHP_URL_PATH );
        if ( $this->utils->isThisPage( $admin_path . 'admin.php?page=' . AdminMenu::AI_BUILDER_MENU_SLUG ) || $this->utils->isThisPage( $admin_path . 'admin.php?page=' . Menus::MENU_SLUG ) ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        }
    }

	/**
	 * Enqueues styles for the Hostinger admin pages.
	 */
	public function admin_styles(): void {
		wp_enqueue_style( 'hostinger_ai_websites_main_styles', HOSTINGER_AI_WEBSITES_ASSETS_URL . '/css/main.min.css', array(), wp_get_theme()->get( 'Version' ) );

        $hide_menu_item = '.hsr-list__item a.' . Menu::AI_BUILDER_MENU_SLUG . ', .toplevel_page_hostinger a[href="admin.php?page=' . Menu::AI_BUILDER_MENU_SLUG . '"] { display: none !important; }';
        wp_add_inline_style( 'hostinger_ai_websites_main_styles', $hide_menu_item );
    }

	/**
	 * Enqueues scripts for the Hostinger admin pages.
	 */
	public function admin_scripts(): void {
        wp_enqueue_script(
            'hostinger_ai_websites_main_scripts',
            HOSTINGER_AI_WEBSITES_ASSETS_URL . '/js/main.min.js',
            array(
                'jquery',
                'wp-i18n',
            ),
            wp_get_theme()->get( 'Version' ),
            false
        );

        $site_url = add_query_arg( 'LSCWP_CTRL', 'before_optm', get_site_url() . '/' );

        $language_code = 'en';
        $locale        = get_locale();
        if ( ! empty( $locale ) ) {
            $language_code = substr( $locale, 0, 2 );
        }

        $localize_data = array(
            'site_url'             => $site_url,
            'plugin_url'           => get_stylesheet_directory_uri() . '/',
            'hostinger_admin_url'  => admin_url( 'admin.php?page=hostinger' ),
            'admin_url'            => admin_url( 'admin-ajax.php' ),
            'website_type'         => WebsiteTypeHelper::get_website_types(),
            'translations'         => AdminTranslations::getValues(),
            'content_generated'    => (int) ! empty( get_option( 'hostinger_ai_version' ) ),
            'rest_base_url'        => esc_url_raw( rest_url() ),
            'nonce'                => wp_create_nonce( 'wp_rest' ),
            'ajax_nonce'           => wp_create_nonce( 'updates' ),
            'homepage_editor_url'  => $this->get_homepage_site_editor_url(),
            'countries_and_states' => $this->get_countries_and_states(),
            'is_survey_enabled'    => $this->rateAiSite && $this->rateAiSite->isSurveyEnabled(),
            'site_locale' => $language_code,
            'prefill_website_type' => $this->get_prefill_option( 'hostinger_login_website_type', true ),
            'prefill_brand_name' => $this->get_prefill_option( 'hostinger_login_brand_name' ),
            'prefill_website_description' => $this->get_prefill_option( 'hostinger_login_website_description' ),
        );

        wp_localize_script(
            'hostinger_ai_websites_main_scripts',
            'hostinger_ai_websites',
            $localize_data
        );

        wp_enqueue_script(
            'hostinger_ai_websites_admin_scripts',
            HOSTINGER_AI_WEBSITES_ASSETS_URL . '/js/admin.min.js',
            array(
                'jquery',
                'wp-i18n',
            ),
            wp_get_theme()->get( 'Version' ),
            false
        );
	}

    private function get_homepage_site_editor_url(): string {
        $query_args = array(
            'hostinger_builder_redirect' => 'edit',
        );

        return add_query_arg( $query_args, admin_url( 'index.php' ) );
    }

    private function get_countries_and_states(): array {
        $countries = include self::WOO_I18N_PATH . 'countries.php';
        if ( ! $countries ) {
            return array();
        }
        $output = array();
        foreach ( $countries as $key => $value ) {
            $states = include self::WOO_I18N_PATH . 'states.php';

            if ( ! empty( $states[ $key ] ) ) {
                $states = $states[ $key ];

                foreach ( $states as $state_key => $state_value ) {
                    $output[ $key . ':' . $state_key ] = $value . ' - ' . $state_value;
                }
            } else {
                $output[ $key ] = $value;
            }
        }

        return $output;
    }

    private function get_prefill_option( string $option_name, bool $validate_website_type = false ): string {
        $value = get_option( $option_name, '' );

        if ( empty( $value ) ) {
            return '';
        }

        delete_option( $option_name );

        $value = sanitize_text_field( $value );

        if ( $validate_website_type && ! in_array( $value, self::VALID_WEBSITE_TYPES, true ) ) {
            return '';
        }

        return $value;
    }
}
