<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Data\SectionData;

defined( 'ABSPATH' ) || exit;

class Structure {
    private const EXCLUDED_FROM_MENU_SECTION_TYPES = array(
        'terms-and-conditions',
        'privacy-policy',
        'cookie-policy',
    );

    /**
     * @var string
     */
    private string $brand_name;
    /**
     * @var array
     */
    private array $website_type;
    /**
     * @var string
     */
    private string $description;
    /**
     * @var RequestClient
     */
    private RequestClient $request_client;
    /**
     * @var string|null
     */
    private ?string $builder_type = null;

    /**
     * @param string      $brand_name
     * @param array       $website_type
     * @param string      $description
     * @param string|null $builder_type Specifies which builder type to use (gutenberg or elementor), overriding the default option value if provided
     */
    public function __construct( string $brand_name, array $website_type, string $description, ?string $builder_type = null ) {
        $this->brand_name   = $brand_name;
        $this->website_type = $website_type;
        $this->description  = $description;
        $this->builder_type = $builder_type;
    }

    /**
     * @param RequestClient $request_client
     *
     * @return void
     */
    public function set_request_client( RequestClient $request_client ): void {
        $this->request_client = $request_client;
    }

    public function generate_structure( array $website_type ): array {
        $website_types = array_map( fn( $t ) => $t === 'booking' ? 'business' : $t, $this->website_type );

        $params = array(
            'brand_name'   => $this->brand_name,
            'website_type' => $website_types[0] ?? 'business',
            'description'  => $this->description,
            'language'     => $this->get_site_locale(),
            'sections'     => SectionData::get_sections_for_website_type( $this->website_type ),
        );

        $structure = $this->request_client->post( '/v3/wordpress/plugin/builder/structure', $params );

        $structure = $this->promote_hero_video( $structure );

        $structure = $this->ensure_single_hero_per_page( $structure );

        return $this->generate_unique_identifiers( $structure );
    }

    /**
     * @param array $page_data
     *
     * @return array
     */
    public function generate_content( array $page_data ): array {
        $website_types = array_map( fn( $t ) => $t === 'booking' ? 'business' : $t, $this->website_type );

        $params = array(
            'brand_name'        => $this->brand_name,
            'website_type'      => $website_types[0] ?? 'business',
            'description'       => $this->description,
            'image_with_prompt' => false,
            'language'          => $this->get_site_locale(),
            'pages'             => $this->format_page_data( $page_data ),
        );

        return $this->request_client->post( '/v3/wordpress/plugin/builder/ai-builder-v3', $params );
    }

    /**
     * @param $structure
     *
     * @return array
     * @throws \Exception
     */
    public function generate_builder_data( $structure ): array {
        foreach ( $structure as &$data ) {
            foreach ( $data['sections'] as &$section_data ) {
                $section_builder = new SectionBuilder( $section_data['section'], $this->builder_type );
                $section_builder->setHelper( new Helper() );

                $generate = $section_builder->generate();
                if ( ! empty( $generate ) ) {
                    $section_data['content']   = $section_builder->get_block_content();
                    $section_data['structure'] = $this->resolve_section_links(
                        $section_data['section'],
                        $section_builder->get_block_used_elements(),
                        false
                    );
                }
            }
        }

        $structure = $this->append_products_data( $structure );

        return $this->append_seo_data( $structure );
    }

    /**
     * @param array $structure
     * @param array $content
     *
     * @return array
     */
    public function merge_content( array $structure, array $content ): array {
        foreach ( $content['pages'] as $page => &$content_data ) {
            foreach ( $content_data['sections'] as $section_index => &$section_data ) {
                $section_data['html'] = $this->find_section_content( $page, $structure, $section_index );

                if ( ! empty( $section_data['elements'] ) ) {
                    $section_type             = $section_data['type'] ?? $section_data['section'] ?? '';
                    $section_data['elements'] = $this->resolve_section_links( $section_type, $section_data['elements'], true );
                }
            }

            $content_data['exclude_from_menu'] = $this->should_exclude_page_from_menu( $page, $structure );
        }

        return $content;
    }

    private function should_exclude_page_from_menu( string $page, array $structure ): bool {
        foreach ( $structure as $structure_data ) {
            if ( $structure_data['page'] === $page ) {
                return ! empty( $structure_data['exclude_from_menu'] );
            }
        }

        return false;
    }

    /**
     * @param string $page
     * @param array  $structure
     * @param string $section_index
     *
     * @return string
     */
    private function find_section_content( string $page, array $structure, string $section_index ): string {

        foreach ( $structure as $structure_data ) {
            if ( $structure_data['page'] === $page ) {
                foreach ( $structure_data['sections'] as $section_data ) {
                    if ( $section_data['id'] === $section_index ) {
                        return ! empty( $section_data['content'] ) ? $section_data['content'] : '';
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param array $page_data
     *
     * @return array
     */
    private function format_page_data( array $page_data ): array {
        $formatted_data = array();

        foreach ( $page_data as $data ) {
            $sections = array();

            foreach ( $data['sections'] as $section_data ) {

                if ( empty( $section_data['structure'] ) ) {
                    continue;
                }

                $sections[ $section_data['id'] ] = array(
                    'type'     => $section_data['section'],
                    'elements' => $section_data['structure'],
                );
            }

            if ( ! empty( $sections ) ) {
                $formatted_data[ $data['page'] ] = array(
                    'sections' => $sections,
                );
            }
        }

        return $formatted_data;
    }

    /**
     * @param array $structure
     *
     * @return array
     */
    private function generate_unique_identifiers( array $structure ): array {
        $result = array();

        if ( get_option( 'hostinger_ai_woo', false ) ) {
            foreach ( $structure as $page => $sections ) {
                if ( strtolower( $page ) === 'shop' ) {
                    update_option( 'hostinger_ai_woo_shop_page_key', $page );
                    break;
                }
            }
        }

        foreach ( $structure as $page => $sections ) {
            $exclude_from_menu = false;

            foreach ( $sections as $section ) {
                if ( in_array( $section, self::EXCLUDED_FROM_MENU_SECTION_TYPES, true ) ) {
                    $exclude_from_menu = true;
                    break;
                }
            }

            $page_data = array(
                'page'              => $page,
                'sections'          => array(),
                'exclude_from_menu' => $exclude_from_menu,
            );

            foreach ( $sections as $section ) {
                $page_data['sections'][] = array(
                    'id'      => uniqid(),
                    'section' => $section,
                );
            }

            $result[] = $page_data;
        }

        return $result;
    }

    private function get_site_locale(): string {
        $wp_locale = get_option( 'hostinger_ai_selected_language', 'en_US' );

        return Helper::get_locale_name( $wp_locale );
    }

    private function resolve_section_links( string $section_type, array $elements, bool $allow_category_creation = false ): array {
        if ( str_starts_with( $section_type, 'hero-for-online-store' ) ) {
            return $this->assign_online_store_hero_links( $elements );
        }

        if ( str_starts_with( $section_type, 'product-list' ) ) {
            return $this->assign_link_to_buttons( $elements, $this->get_shop_link() );
        }

        if ( $allow_category_creation && str_starts_with( $section_type, 'product-categories' ) ) {
            return $this->assign_product_category_links( $elements );
        }

        return $elements;
    }

    private function assign_online_store_hero_links( array $elements ): array {
        $shop_link = $this->get_shop_link();

        foreach ( $elements as &$element ) {
            if ( $this->is_button_element( $element ) && ! empty( $shop_link ) ) {
                $element['link'] = $shop_link;
            }
        }

        return $elements;
    }

    private function assign_link_to_buttons( array $elements, string $link ): array {
        if ( empty( $link ) ) {
            return $elements;
        }

        foreach ( $elements as &$element ) {
            if ( $this->is_button_element( $element ) ) {
                $element['link'] = $link;
            }
        }

        return $elements;
    }

    private function assign_product_category_links( array $elements ): array {
        $category_names = $this->extract_category_names_by_index( $elements );

        if ( empty( $category_names ) ) {
            return $this->assign_link_to_buttons( $elements, $this->get_shop_link() );
        }

        $category_links = ( new ProductCategoryManager() )->ensure_category_links_by_name( array_values( $category_names ) );

        if ( empty( $category_links ) ) {
            return $this->assign_link_to_buttons( $elements, $this->get_shop_link() );
        }

        $fallback_link = reset( $category_links ) ?: $this->get_shop_link();

        foreach ( $elements as &$element ) {
            if ( ! $this->is_button_element( $element ) ) {
                continue;
            }

            $category_name = $category_names[ (string) ( $element['index'] ?? '' ) ] ?? '';
            $element['link'] = $category_links[ $category_name ] ?? $fallback_link;
        }

        return $elements;
    }

    private function extract_category_names_by_index( array $elements ): array {
        $category_names = array();

        foreach ( $elements as $element ) {
            $class   = $element['class'] ?? '';
            $content = sanitize_text_field( $element['content'] ?? '' );
            $index   = (string) ( $element['index'] ?? '' );

            if ( $class !== 'hostinger-ai-title' || $content === '' || $index === '' ) {
                continue;
            }

            $category_names[ $index ] = $content;
        }

        return $category_names;
    }

    private function is_button_element( array $element ): bool {
        $class = $element['class'] ?? '';
        $type  = strtolower( (string) ( $element['type'] ?? '' ) );

        return $class === 'hostinger-ai-cta-button' || str_contains( $type, 'button' );
    }

    private function get_shop_link(): string {
        $shop_page_id = $this->get_valid_shop_page_id();

        if ( $shop_page_id > 0 ) {
            $permalink = get_permalink( $shop_page_id );
            if ( ! empty( $permalink ) ) {
                return $permalink;
            }
        }

        return $this->get_shop_link_from_slug();
    }

    private function get_valid_shop_page_id(): int {
        $shop_page_id = (int) ( function_exists( 'wc_get_page_id' )
            ? wc_get_page_id( 'shop' )
            : get_option( 'woocommerce_shop_page_id' ) );

        if ( $shop_page_id > 0 ) {
            $shop_page = get_post( $shop_page_id );
            if ( $shop_page && $shop_page->post_type === 'page' && $shop_page->post_status === 'publish' ) {
                return $shop_page_id;
            }
        }

        $shop_page_slug = $this->get_shop_page_slug();
        $shop_page      = get_page_by_path( $shop_page_slug, OBJECT, 'page' );

        if ( ! $shop_page ) {
            return 0;
        }

        if ( $shop_page->post_status !== 'publish' ) {
            return 0;
        }

        update_option( 'woocommerce_shop_page_id', $shop_page->ID );
        update_option( 'hostinger_ai_woo_shop_page_key', $shop_page->post_name );

        return (int) $shop_page->ID;
    }

    private function get_shop_link_from_slug(): string {
        $shop_page_slug = $this->get_shop_page_slug();

        if ( empty( $shop_page_slug ) ) {
            return home_url( '/' );
        }

        return home_url( user_trailingslashit( $shop_page_slug ) );
    }

    private function get_shop_page_slug(): string {
        $shop_page_slug = sanitize_title( (string) get_option( 'hostinger_ai_woo_shop_page_key', 'shop' ) );

        return $shop_page_slug !== '' ? $shop_page_slug : 'shop';
    }

    private function append_seo_data( array $structure ): array {
        foreach ( $structure as &$page ) {

            if ( ! isset( $page['sections'] ) ) {
                $page['sections'] = array();
            }

            $page['sections'][] = array(
                'id'        => uniqid(),
                'section'   => 'seo',
                'structure' => array(
                    'seo_title' . uniqid()       => array(
                        'type'      => 'SEO Title',
                        'max_words' => 10,
                    ),
                    'seo_keywords' . uniqid()    => array(
                        'type'      => 'Comma separated list of 3 SEO keywords',
                        'max_words' => 10,
                    ),
                    'seo_description' . uniqid() => array(
                        'type'      => 'SEO Description',
                        'max_words' => 50,
                    ),
                ),
            );
        }

        return $structure;
    }

    private function append_products_data( array $structure ): array {
        if ( ! get_option( 'hostinger_ai_woo', false ) ) {
            return $structure;
        }

        $product_count    = 5;
        $product_elements = array();

        for ( $i = 1; $i <= $product_count; $i++ ) {
            $product_elements[ 'product_' . $i . '_image-' . uniqid() ] = array(
                'type'      => 'Image',
                'max_words' => '5',
            );
            $product_elements[ 'product_' . $i . '_title-' . uniqid() ] = array(
                'type'      => 'Title',
                'max_words' => '5',
            );
            $product_elements[ 'product_' . $i . '_description-' . uniqid() ] = array(
                'type'      => 'Description',
                'max_words' => '80',
            );
            $product_elements[ 'product_' . $i . '_price-' . uniqid() ] = array(
                'type'      => 'Price number',
                'max_words' => '1',
            );
        }

        $ecommerce_page = array(
            'page'     => 'ecommercePagesGroup',
            'sections' => array(
                array(
                    'id'        => uniqid(),
                    'section'   => 'products',
                    'structure' => $product_elements,
                ),
            ),
        );

        $structure[] = $ecommerce_page;

        return $structure;
    }

    public function generate_page_structure( array $website_type, string $page_name ): array {
        $website_types = array_map( fn( $t ) => $t === 'booking' ? 'business' : $t, $website_type );

        $params = array(
            'brand_name'   => $this->brand_name,
            'website_type' => $website_types[0] ?? 'business',
            'description'  => $this->description,
            'language'     => $this->get_site_locale(),
            'sections'     => SectionData::get_sections_for_website_type( $website_type ),
            'pages'        => array( $page_name ),
        );

        $structure = $this->request_client->post( '/v3/wordpress/plugin/builder/structure', $params );

        if ( empty( $structure ) ) {
            return array();
        }

        $structure = $this->promote_hero_video( $structure );

        $structure = $this->ensure_single_hero_per_page( $structure );

        $page_structure = array();

        if ( isset( $structure[ $page_name ] ) ) {
            $page_structure = array( $page_name => $structure[ $page_name ] );
        } else {
            $random_key     = array_rand( $structure );
            $page_structure = array( $random_key => $structure[ $random_key ] );
        }

        return $this->generate_unique_identifiers( $page_structure );
    }

    private function promote_hero_video( array $structure ): array {
        foreach ( $structure as &$sections ) {
            if ( ! is_array( $sections ) ) {
                continue;
            }

            foreach ( $sections as &$section ) {
                if ( $section === 'hero' && wp_rand( 1, 100 ) <= 70 ) {
                    $section = 'hero-video';
                }
            }
        }

        return $structure;
    }

    private function ensure_single_hero_per_page( array $structure ): array {
        $hero_section_types = [ 'hero', 'hero-video', 'hero-portfolio', 'hero-for-online-store', 'about', 'about-us', 'competitive_edge' ];

        foreach ( $structure as $page_name => &$sections ) {
            if ( ! is_array( $sections ) ) {
                continue;
            }

            $hero_found = false;
            $filtered_sections = [];

            foreach ( $sections as $section ) {
                $is_hero_section = in_array( $section, $hero_section_types, true );

                if ( $is_hero_section ) {
                    if ( ! $hero_found ) {
                        $filtered_sections[] = $section;
                        $hero_found = true;
                    }
                } else {
                    $filtered_sections[] = $section;
                }
            }

            $sections = $filtered_sections;
        }

        return $structure;
    }
}
