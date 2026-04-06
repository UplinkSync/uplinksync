<?php

namespace Hostinger\AiTheme\Data;

defined( 'ABSPATH' ) || exit;

class WebsiteTypeHelper {
    public static function get_website_types(): array {
        $value = get_option( 'hostinger_ai_website_type', [] );

        // Backward compatibility: if it's a plain string (old format), wrap in array.
        if ( is_string( $value ) ) {
            return ! empty( $value ) ? [ $value ] : [];
        }

        return is_array( $value ) ? $value : [];
    }

    public static function has_website_type( string $type ): bool {
        return in_array( $type, self::get_website_types(), true );
    }
}
