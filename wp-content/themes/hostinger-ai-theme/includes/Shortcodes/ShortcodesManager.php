<?php

namespace Hostinger\AiTheme\Shortcodes;

defined( 'ABSPATH' ) || exit;

class ShortcodesManager {
    public function __construct() {
        add_action( 'init', array( $this, 'register_shortcodes' ) );
    }

    public function register_shortcodes(): void {
        add_shortcode( 'hostinger_contact_form', array( $this, 'render_contact_form' ) );
    }

    public function render_contact_form( array $atts ): string {
        $atts = shortcode_atts(
            array(
                'title'               => '',
                'description'         => '',
                'show_title'          => 'true',
                'show_description'    => 'true',
                'button_text'         => __( 'Send Message', 'hostinger-ai-theme' ),
                'name_label'          => __( 'Name', 'hostinger-ai-theme' ),
                'email_label'         => __( 'Email', 'hostinger-ai-theme' ),
                'message_label'       => __( 'Message', 'hostinger-ai-theme' ),
                'name_placeholder'    => __( "What's your name?", 'hostinger-ai-theme' ),
                'email_placeholder'   => __( "What's your email?", 'hostinger-ai-theme' ),
                'message_placeholder' => __( 'Write your message...', 'hostinger-ai-theme' ),
                'privacy_policy_text' => '',
            ),
            $atts
        );

        $attributes = array(
            'title'              => $atts['title'],
            'description'        => $atts['description'],
            'showTitle'          => $atts['show_title'] === 'true',
            'showDescription'    => $atts['show_description'] === 'true',
            'buttonText'         => $atts['button_text'],
            'nameLabel'          => $atts['name_label'],
            'emailLabel'         => $atts['email_label'],
            'messageLabel'       => $atts['message_label'],
            'namePlaceholder'    => $atts['name_placeholder'],
            'emailPlaceholder'   => $atts['email_placeholder'],
            'messagePlaceholder' => $atts['message_placeholder'],
            'privacyPolicyText'  => $atts['privacy_policy_text'],
        );

        wp_enqueue_script( 'hostinger-contact-form-block' );

        ob_start();
        include get_template_directory() . '/gutenberg-blocks/ContactForm/render.php';
        return ob_get_clean();
    }
}
