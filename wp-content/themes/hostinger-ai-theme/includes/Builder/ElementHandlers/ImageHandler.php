<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Builder\LocalImageManager;
use DOMElement;

defined( 'ABSPATH' ) || exit;

class ImageHandler extends BaseElementHandler {
    public function handle_gutenberg(DOMElement &$node, array $element_structure): void {
        $image_data = $this->prepare_image_data($element_structure);
        if (empty($image_data)) {
            return;
        }

        $imgs = $node->getElementsByTagName('img');

        if ($imgs->length > 0) {
            $img = $imgs->item(0);
            $img->setAttribute('src', $image_data['url']);
            $img->setAttribute('alt', $image_data['alt']);
        }
    }

    public function handle_elementor(array &$element, array $element_structure): void {
        if (empty($element['settings'])) {
            return;
        }

        $image_data = $this->prepare_image_data($element_structure);
        if (empty($image_data)) {
            return;
        }

        if (isset($element['settings']['image'])) {
            $element['settings']['image']['url'] = $image_data['url'];
            $element['settings']['image']['alt'] = $image_data['alt'];
        }
    }

    public function pre_resolve_image(array $element_structure): void {
        if ($this->is_customer_reviews_section($element_structure)) {
            return;
        }

        $content = $element_structure['default_content']
                   ?? $element_structure['content']
                      ?? '';

        if (empty($content)) {
            return;
        }

        $website_description = get_option('hostinger_ai_description', '');
        $image_context       = 'Image for ' . strip_tags($content);

        if (!empty($website_description)) {
            $image_context .= '. Website description: ' . strip_tags($website_description);
        }

        $prefer_hero = in_array($this->section_type, self::HERO_SECTION_TYPES, true);
        $image_manager = new ImageManager($image_context);
        $image_data = $image_manager->get_image_data($prefer_hero);

        if (!empty($image_data) && property_exists($image_data, 'image') && $image_data->image !== '') {
            $this->cache_raw_url(
                $element_structure['class'],
                $element_structure['index'],
                $image_data->image
            );
        }
    }

    private function prepare_image_data(array $element_structure): ?array {
        if (isset($element_structure['source_from'])) {
            $src = $element_structure['source_from'];
            $raw = $this->get_cached_raw_url($src['class'], $src['index']);
            if ($raw) {
                $image_url = (new ImageManager(''))->modify_image_url($raw, $element_structure);
                return ['url' => $image_url, 'alt' => ''];
            }
            return null;
        }

        $content = $element_structure['default_content']
                   ?? $element_structure['content']
                      ?? '';

        if (empty($content)) {
            return null;
        }

        $use_local_images = $this->is_customer_reviews_section($element_structure);

        if ($use_local_images) {
            $image_manager = new LocalImageManager();
            $image_data    = $image_manager->get_local_image_data(!empty($element_structure['default_content']));

            if (empty($image_data)) {
                return null;
            }

            $image_url       = $image_manager->modify_image_url($image_data['image'], $element_structure);
            $alt_description = $image_data['alt_description'] ?? '';
        } else {
            $raw = $this->get_cached_raw_url($element_structure['class'], $element_structure['index']);
            if ($raw) {
                return [
                    'url' => (new ImageManager(''))->modify_image_url($raw, $element_structure),
                    'alt' => '',
                ];
            }

            $website_description = get_option('hostinger_ai_description', '');
            $image_context       = 'Image for ' . strip_tags($content);

            if (!empty($website_description)) {
                $image_context .= '. Website description: ' . strip_tags($website_description);
            }

            $prefer_hero = in_array($this->section_type, self::HERO_SECTION_TYPES, true);
            $image_manager = new ImageManager($image_context);
            $image_data = $image_manager->get_image_data($prefer_hero);

            if (empty($image_data) || !property_exists($image_data, 'image') || $image_data->image === '') {
                return null;
            }

            $image_url       = $image_manager->modify_image_url($image_data->image, $element_structure);
            $alt_description = property_exists($image_data, 'alt_description') && !empty($image_data->alt_description)
                ? $image_data->alt_description
                : '';
        }

        return [
            'url' => $image_url,
            'alt' => $alt_description,
        ];
    }

    private function is_customer_reviews_section(array $element_structure): bool {
        if (!empty($element_structure['class'])) {
            return str_contains($element_structure['class'], 'hostinger-ai-testimonial-image');
        }
        return false;
    }
}
