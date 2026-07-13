<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class HostingerReachBuilder extends AbstractPluginBuilder {
    use HostingerPluginUpdateUri;

    private const PLUGIN_FILE = 'hostinger-reach/hostinger-reach.php';
    private const PLUGIN_NAME = 'Hostinger Reach';
    private const PLUGIN_SLUG = 'hostinger-reach';
    public const FORM_ID = 'ai-theme-footer-form';
    private const FORMS_TABLE = 'hostinger_reach_forms';

    protected function get_plugin_file(): string {
        return self::PLUGIN_FILE;
    }

    protected function get_plugin_name(): string {
        return self::PLUGIN_NAME;
    }

    protected function get_download_url(): string {
        return $this->build_hostinger_download_url( self::PLUGIN_SLUG );
    }

    protected function get_error_code(): string {
        return 'hostinger_reach';
    }

    protected function after_activation(): void {
        $this->generate_form();
    }

    public function generate_form(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . self::FORMS_TABLE;

        $form_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE form_id = %s",
                self::FORM_ID
            )
        );

        if ( ! $form_exists ) {
            $footer_post = get_posts(
                array(
                    'name'        => 'footer',
                    'post_type'   => 'wp_template_part',
                    'numberposts' => 1,
                    'fields'      => 'ids',
                )
            );

            $post_id = ! empty( $footer_post ) ? array_shift( $footer_post ) : 0;

            $wpdb->insert(
                $table_name,
                array(
                    'form_id'    => self::FORM_ID,
                    'post_id'    => $post_id,
                    'type'       => 'hostinger-reach',
                    'is_active'  => 1,
                    'form_title' => 'Footer',
                )
            );
        }
    }
}
