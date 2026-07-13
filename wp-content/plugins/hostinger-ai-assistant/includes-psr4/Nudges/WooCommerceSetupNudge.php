<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Hosting\EasyOnboardingRepository;
use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class WooCommerceSetupNudge extends AbstractNudge {
    private const CAMPAIGN_NAME        = 'wordpress-setup-woocommerce';
    private const RESHOW_INTERVAL_DAYS = 14;
    private const CHECKLIST_PAGE       = 'admin.php?page=hostinger-get-onboarding';

    public function __construct(
        private EasyOnboardingRepository $onboarding_repository
    ) {
    }

    public function get_name(): string {
        return self::CAMPAIGN_NAME;
    }

    public function evaluate(): ?ReachOut {
        if ( ! $this->onboarding_repository->is_easy_onboarding_active()
            || ! $this->onboarding_repository->is_woocommerce_active()
            || $this->onboarding_repository->is_store_setup_complete()
        ) {
            $this->reset_state();

            return null;
        }

        $dedup_key = $this->current_window_key( self::RESHOW_INTERVAL_DAYS );
        if ( $this->already_sent( $dedup_key ) ) {
            return null;
        }

        return new ReachOut(
            $this->build_message(),
            $dedup_key,
            $this->build_setup_button( admin_url( self::CHECKLIST_PAGE ) )
        );
    }

    private function build_message(): string {
        return __(
            'Hey! It looks like your WooCommerce store isn\'t fully set up yet. Complete the remaining steps to make sure your store is ready to sell.',
            'hostinger-ai-assistant'
        );
    }

    private function build_setup_button( string $checklist_url ): array {
        return array(
            array(
                'id'   => 'setup-woocommerce',
                'type' => 'button',
                'data' => array(
                    'display_text' => __( 'Complete WooCommerce setup', 'hostinger-ai-assistant' ),
                    'url'          => esc_url_raw( $checklist_url ),
                ),
            ),
        );
    }
}
