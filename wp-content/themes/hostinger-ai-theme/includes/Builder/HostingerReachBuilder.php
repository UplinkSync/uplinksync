<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class HostingerReachBuilder extends AbstractPluginBuilder {
    use HostingerPluginUpdateUri;

    private const PLUGIN_FILE = 'hostinger-reach/hostinger-reach.php';
    private const PLUGIN_NAME = 'Hostinger Reach';
    private const PLUGIN_SLUG = 'hostinger-reach';
    private const FORM_ID = 'ai-theme-footer-form';

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
        if ( ! $this->is_plugin_active() ) {
            return;
        }

        add_filter( 'hostinger_reach_default_forms', array( $this, 'add_form' ) );
    }

    public function add_form( array $forms ): array {
        $forms[] = self::FORM_ID;
        return $forms;
    }
}
