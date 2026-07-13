<?php

namespace Hostinger\AiAssistant\Nudges;

use Hostinger\AiAssistant\Nudges\Dto\ReachOut;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Shared throttle and de-dup plumbing for nudges.
 *
 * Concrete nudges only implement {@see get_name()} and {@see evaluate()}; state
 * is namespaced per nudge via {@see get_name()} so multiple nudges never clash.
 */
abstract class AbstractNudge implements NudgeInterface {
    protected const THROTTLE_TTL = DAY_IN_SECONDS;

    abstract public function get_name(): string;

    abstract public function evaluate(): ?ReachOut;

    public function is_enabled(): bool {
        return true;
    }

    public function is_throttled(): bool {
        return (bool) get_transient( $this->throttle_key() );
    }

    public function touch_throttle(): void {
        set_transient( $this->throttle_key(), 1, static::THROTTLE_TTL );
    }

    public function mark_sent( ReachOut $reach_out ): void {
        update_option( $this->state_key(), $reach_out->get_dedup_key() );
    }

    /**
     * Whether a reach-out was already sent for this exact trigger window.
     */
    protected function already_sent( string $dedup_key ): bool {
        return (string) get_option( $this->state_key(), '' ) === $dedup_key;
    }

    /**
     * Forget the last sent window so a future window can fire again
     * (e.g. after the plan is renewed and later approaches expiry once more).
     */
    protected function reset_state(): void {
        delete_option( $this->state_key() );
    }

    protected function current_window_key( int $interval_days ): string {
        return (string) (int) floor( time() / ( $interval_days * DAY_IN_SECONDS ) );
    }

    protected function throttle_key(): string {
        return 'hostinger_ai_nudge_' . $this->get_name() . '_checked';
    }

    protected function state_key(): string {
        return 'hostinger_ai_nudge_' . $this->get_name() . '_sent';
    }
}
