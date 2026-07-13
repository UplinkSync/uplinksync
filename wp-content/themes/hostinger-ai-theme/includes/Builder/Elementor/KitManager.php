<?php

namespace Hostinger\AiTheme\Builder\Elementor;

use Hostinger\AiTheme\Builder\Dto\ColorPaletteDto;

class KitManager {
    private int $kit_id;
    private array $kit_settings = array();

    public function __construct() {
        $this->kit_id = get_option('elementor_active_kit');

        if ($this->kit_id) {
            $settings = get_post_meta($this->kit_id, '_elementor_page_settings', true);
            $this->kit_settings = is_array($settings) ? $settings : [];

            if ( empty( $this->kit_settings['custom_colors'] ) ) {
                $this->transform_color_palette( self::get_default_palette() );
            }
        }
    }

    public function set_custom_colors( array $colors ): bool {
        $this->kit_settings['custom_colors'] = $colors;

        return $this->save();
    }

    public function set_page_title_selector( string $selector ): bool {
        $this->kit_settings['page_title_selector'] = $selector;

        return $this->save();
    }

    public function transform_color_palette( ColorPaletteDto $color_palette ) : bool {
        $colors = [
            [
                '_id' => 'b5aeb33',
                'title' => 'Color 1 (Section backgrounds)',
                'color' => $color_palette->get_color_1() ?? ''
            ],
            [
                '_id' => 'c58817e',
                'title' => 'Color 2 (Section backgrounds)',
                'color' => $color_palette->get_color_2() ?? ''
            ],
            [
                '_id' => '5420d44',
                'title' => 'Color 3 (Button background)',
                'color' => $color_palette->get_color_3() ?? ''
            ],
            [
                '_id' => '58be983',
                'title' => 'Light (Text on Color 2 and Gradient)',
                'color' => $color_palette->get_light() ?? ''
            ],
            [
                '_id' => '09cc561',
                'title' => 'Dark (Text on Light and Color 1)',
                'color' => $color_palette->get_dark() ?? ''
            ],
            [
                '_id' => 'a495fd4',
                'title' => 'Grey (Form borders)',
                'color' => $color_palette->get_grey() ?? ''
            ],
            [
                '_id' => 'dff8941',
                'title' => 'Gradient color 1',
                'color' => $color_palette->get_main_gradient() ? $color_palette->get_main_gradient()->get_main_color() : ''
            ],
        ];

        return $this->set_custom_colors( $colors );
    }

    public function transform_custom_typography( string $heading_font, string $body_font ): bool {
        $heading = trim( explode( ',', $heading_font )[0] );
        $body    = trim( explode( ',', $body_font )[0] );

        $this->kit_settings['custom_typography'] = [
            [
                '_id'                         => '5535e8e',
                'title'                       => 'H1 Heading',
                'typography_typography'       => 'custom',
                'typography_font_family'      => $heading,
                'typography_font_size'        => [ 'unit' => 'rem', 'size' => 3, 'sizes' => [] ],
                'typography_font_weight'      => '700',
            ],
            [
                '_id'                          => 'c83476d',
                'title'                        => 'H2 Heading',
                'typography_typography'        => 'custom',
                'typography_font_family'       => $heading,
                'typography_font_size'         => [ 'unit' => 'rem', 'size' => 2.5, 'sizes' => [] ],
                'typography_font_size_mobile'  => [ 'unit' => 'rem', 'size' => 1.9, 'sizes' => [] ],
                'typography_font_weight'       => 'bold',
            ],
            [
                '_id'                          => 'ff8f921',
                'title'                        => 'H1 Heading (400)',
                'typography_typography'        => 'custom',
                'typography_font_family'       => $heading,
                'typography_font_size'         => [ 'unit' => 'rem', 'size' => 2.3, 'sizes' => [] ],
                'typography_font_size_mobile'  => [ 'unit' => 'rem', 'size' => 1.9, 'sizes' => [] ],
                'typography_font_weight'       => 'bold',
            ],
            [
                '_id'                         => '887fca2',
                'title'                       => 'Body',
                'typography_typography'       => 'custom',
                'typography_font_family'      => $body,
                'typography_font_size'        => [ 'unit' => 'rem', 'size' => 1, 'sizes' => [] ],
                'typography_font_weight'      => '400',
            ],
            [
                '_id'                         => '2142591',
                'title'                       => 'Body Bold',
                'typography_typography'       => 'custom',
                'typography_font_family'      => $body,
                'typography_font_size'        => [ 'unit' => 'rem', 'size' => 1, 'sizes' => [] ],
                'typography_font_weight'      => '700',
            ],
            [
                '_id'                         => '6e52843',
                'title'                       => 'Button',
                'typography_typography'       => 'custom',
                'typography_font_family'      => $body,
                'typography_font_size'        => [ 'unit' => 'rem', 'size' => 1, 'sizes' => [] ],
                'typography_font_weight'      => '500',
            ],
        ];

        update_option( 'hostinger_ai_body_font', $body_font );
        update_option( 'hostinger_elementor_typography_set', $heading_font );

        return $this->save();
    }

    private function save(): bool {
        if ( empty( $this->kit_id ) ) {
            return false;
        }

        $result = update_post_meta( $this->kit_id, '_elementor_page_settings', $this->kit_settings );

        if ( class_exists( '\Elementor\Plugin' ) ) {
            $elementor = \Elementor\Plugin::instance();
            if ( ! empty( $elementor->files_manager ) && method_exists( $elementor->files_manager, 'clear_cache' ) ) {
                $elementor->files_manager->clear_cache();
            }
        }

        return $result;
    }

    private static function get_default_palette(): ColorPaletteDto {
        return ColorPaletteDto::from_array( [
            'color1'    => '#F6F7F9',
            'color2'    => '#23272F',
            'color3'    => '#70777f',
            'light'     => '#ffffff',
            'dark'      => '#0d141a',
            'grey'      => '#B8C0CC',
            'gradients' => [
                'z48lj' => [
                    'gradient' => '#A8BFE4',
                ],
            ],
        ] );
    }
}
