<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Hosting\HpanelUrlBuilder;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class DomainNudge extends AbstractNudge {
    private const CAMPAIGN_NAME        = 'wordpress-connect-domain';
    private const MIN_SITE_AGE_DAYS    = 14;
    private const RESHOW_INTERVAL_DAYS = 14;

    private const FREE_SUBDOMAINS = array( 'hostingersite.com', 'hostingersite.dev' );

    public function __construct(
        private HpanelUrlBuilder $url_builder
    ) {
    }

    public function get_name(): string {
        return self::CAMPAIGN_NAME;
    }

    public function evaluate(): ?ReachOut {
        if ( ! $this->is_temporary_domain() ) {
            $this->reset_state();

            return null;
        }

        if ( $this->get_site_age_days() < self::MIN_SITE_AGE_DAYS ) {
            return null;
        }

        $dedup_key = $this->current_window_key( self::RESHOW_INTERVAL_DAYS );
        if ( $this->already_sent( $dedup_key ) ) {
            return null;
        }

        return new ReachOut(
            $this->build_message(),
            $dedup_key,
            $this->build_connect_domain_button( $this->url_builder->connect_domain_url() )
        );
    }

    private function is_temporary_domain(): bool {
        $site_url = preg_replace( '#^https?://#', '', (string) get_option( 'siteurl' ) );

        if ( empty( $site_url ) ) {
            return false;
        }

        foreach ( self::FREE_SUBDOMAINS as $subdomain ) {
            if ( strpos( $site_url, $subdomain ) !== false ) {
                return true;
            }
        }

        return false;
    }

    private function get_site_age_days(): int {
        $oldest_user = get_users(
            array(
                'number'  => 1,
                'orderby' => 'registered',
                'order'   => 'ASC',
                'fields'  => array( 'user_registered' ),
            )
        );

        $registered = isset( $oldest_user[0]->user_registered ) ? strtotime( (string) $oldest_user[0]->user_registered ) : false;

        if ( ! $registered ) {
            return 0;
        }

        return (int) floor( ( time() - $registered ) / DAY_IN_SECONDS );
    }

    private function build_message(): string {
        return __(
            'Hey! Your site is still running on a temporary domain. Connecting a custom domain makes your site look more professional and helps visitors find you. It only takes a few minutes!',
            'hostinger-ai-assistant'
        );
    }

    private function build_connect_domain_button( string $connect_url ): array {
        return array(
            array(
                'id'   => 'connect-domain',
                'type' => 'button',
                'data' => array(
                    'display_text' => __( 'Connect my domain', 'hostinger-ai-assistant' ),
                    'url'          => esc_url_raw( $connect_url ),
                ),
            ),
        );
    }
}
