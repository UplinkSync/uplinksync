<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

defined( 'ABSPATH' ) || exit;

use DOMElement;

class BaseElementHandler implements ElementHandler {
    protected const HERO_SECTION_TYPES = ['hero', 'hero-video', 'about', 'about-us', 'competitive_edge'];

    protected string $builder_type;
    protected string $section_type = '';
    protected int $element_index = 0;

    protected static array $raw_image_cache = [];

    public function __construct(string $builder_type) {
        $this->builder_type = $builder_type;
    }

    public function set_section_context(string $section_type, int $element_index = 0): void {
        $this->section_type = $section_type;
        $this->element_index = $element_index;
    }

    public function handle_gutenberg(DOMElement &$node, array $element_structure): void {

    }

    public function handle_elementor(array &$element, array $element_structure): void {

    }

    public function pre_resolve_image(array $element_structure): void {

    }

    protected function cache_raw_url(string $class, string $index, string $url): void {
        self::$raw_image_cache["{$class}:{$index}"] = $url;
    }

    protected function get_cached_raw_url(string $class, string $index): ?string {
        return self::$raw_image_cache["{$class}:{$index}"] ?? null;
    }
}
