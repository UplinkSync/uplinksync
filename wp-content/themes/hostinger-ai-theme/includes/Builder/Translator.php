<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class Translator {
    public function translate_string( string $content ): string {
        if ( ! empty( $this->get_translations() ) ) {
            foreach ( $this->get_translations() as $key => $translation ) {
                $content = str_replace( 'trans-' . $key, $translation, $content );
            }
        }

        return $content;
    }

    public function translate_array( array $data ): array {
        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $data[ $key ] = $this->translate_array( $value );
            } elseif ( is_string( $value ) && str_starts_with( $value, 'trans-' ) ) {
                $translation_key = substr( $value, 6 );
                if ( isset( $this->get_translations()[ $translation_key ] ) ) {
                    $data[ $key ] = $this->get_translations()[ $translation_key ];
                }
            }
        }

        return $data;
    }

    private function get_translations(): array {
        return array(
            // Navigation translations.
            'menu'                     => __( 'Menu', 'hostinger-ai-theme' ),
            'contacts'                 => __( 'Contacts', 'hostinger-ai-theme' ),
            'socials'                  => __( 'Socials', 'hostinger-ai-theme' ),
            'newsletter'               => __( 'Subscribe to our newsletter', 'hostinger-ai-theme' ),

            // Contact form widget translations.
            'contact_title'            => __( 'Get in Touch', 'hostinger-ai-theme' ),
            'contact_description'      => __( 'We\'d love to hear from you. Send us a message and we\'ll respond as soon as possible.', 'hostinger-ai-theme' ),
            'button_text'              => __( 'Send Message', 'hostinger-ai-theme' ),
            'name_label'               => __( 'Name', 'hostinger-ai-theme' ),
            'name_placeholder'         => __( 'What\'s your name?', 'hostinger-ai-theme' ),
            'email_label'              => __( 'Email', 'hostinger-ai-theme' ),
            'email_placeholder'        => __( 'What\'s your email?', 'hostinger-ai-theme' ),
            'message_label'            => __( 'Message', 'hostinger-ai-theme' ),
            'message_placeholder'      => __( 'Write your message...', 'hostinger-ai-theme' ),
            'top_rated_by_100_clients' => __( 'Top rated by 100+ clients', 'hostinger-ai-theme' ),
        );
    }
}
