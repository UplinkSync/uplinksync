<?php

namespace Hostinger\AiTheme\Data;

defined( 'ABSPATH' ) || exit;

class SectionData {
    public static function get_sections_for_website_type(array $website_type = []): array {
        $sections = array(
            'hero-video'              => 'Title, subtitle, cta button with a fullscreen background video. Preferred hero section for modern and dynamic websites.',
            'hero'                    => 'Title, subtitle, cta buttons, optional video background.',
            'about-us'                => 'Title, subtitle, image.',
            'services'                => 'Title, subtitle, cards about services.',
            'contact'                 => 'Title, subtitle, contact information, form.',
            'location'                => 'Title, subtitle, address, map.',
            'projects'                => 'Title, subtitle, project cards.',
            'customer-reviews'        => 'Title, subtitle, single customer review.',
            'call-to-action'          => 'Title, description, cta and illustration.',
            'my-background'           => 'My Background section used for personal or portfolio sites, showing details about education, work, skills, and achievements.',
            'gallery'                 => 'Gallery section displays images.',
            'blog-posts'              => 'Contains the content of the blog post.',
            'faq'                     => 'Title, FAQ questions and answers.',
            'real-estate-list'        => 'Real Estate Title, description, cta and real estate image.',
            'ticket-list'             => 'Ticket Title, description, cta and image.',
            'hotel-room-list'         => 'Hotel Room Title, description, cta and image.',
            'travel-destination-list' => 'Title, subtitle, description, cta and image.',
        );

        if (in_array('booking', $website_type, true)) {
            $sections['booking'] = 'Title, description, image.';
        }

        if (in_array('portfolio', $website_type, true)) {
            $sections['hero-portfolio'] = 'Title, subtitle, cta, social icons, portfolio images or video background.';
        }

        if (in_array('online store', $website_type, true)) {
            $sections['hero-for-online-store'] = 'Title, subtitle and cta buttons.';
            $sections['product-categories'] = 'Contains the product category CTAs.';
            $sections['product-list'] = 'Contains product list CTAs.';
        }

        return $sections;
    }
}
