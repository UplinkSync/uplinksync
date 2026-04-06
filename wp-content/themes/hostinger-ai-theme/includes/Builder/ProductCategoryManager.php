<?php

namespace Hostinger\AiTheme\Builder;

use WP_Term;

defined( 'ABSPATH' ) || exit;

class ProductCategoryManager {
    private const CREATED_OPTION = 'hostinger_ai_created_product_categories';
    private const DEFAULT_CATEGORY_COUNT = 5;

    public function get_category_links(): array {
        return $this->get_term_links( $this->get_terms() );
    }

    public function ensure_category_links( array $names = array() ): array {
        return $this->get_term_links( $this->ensure_categories( $names ) );
    }

    public function ensure_category_links_by_name( array $names ): array {
        $terms = $this->ensure_categories( $names );
        $links = array();

        foreach ( $terms as $term ) {
            $link = get_term_link( $term );
            if ( is_wp_error( $link ) || empty( $link ) ) {
                continue;
            }

            $links[ $term->name ] = $link;
        }

        return $links;
    }

    public function ensure_category_ids( array $names = array() ): array {
        return array_map(
            static fn( WP_Term $term ): int => (int) $term->term_id,
            $this->ensure_categories( $names )
        );
    }

    public function clear_created_categories(): void {
        $category_ids = get_option( self::CREATED_OPTION, array() );

        foreach ( $category_ids as $category_id ) {
            $term = get_term( (int) $category_id, 'product_cat' );
            if ( $term instanceof WP_Term ) {
                wp_delete_term( (int) $category_id, 'product_cat' );
            }
        }

        delete_option( self::CREATED_OPTION );
    }

    private function ensure_categories( array $names ): array {
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return array();
        }

        $names = $this->normalize_names( $names );
        if ( empty( $names ) ) {
            $terms = $this->get_terms();
            if ( ! empty( $terms ) ) {
                return $terms;
            }

            $names = $this->get_default_names();
        }

        $terms       = array();
        $created_ids = array();

        foreach ( $names as $name ) {
            $term = get_term_by( 'name', $name, 'product_cat' );

            if ( ! $term instanceof WP_Term ) {
                $result = wp_insert_term( $name, 'product_cat' );
                if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
                    continue;
                }

                $term = get_term( (int) $result['term_id'], 'product_cat' );
                if ( ! $term instanceof WP_Term ) {
                    continue;
                }

                $created_ids[] = (int) $term->term_id;
            }

            $terms[] = $term;
        }

        $this->store_created_ids( $created_ids );

        return array_values( array_filter( $terms, static fn( $term ): bool => $term instanceof WP_Term ) );
    }

    private function get_terms(): array {
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return array();
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            )
        );

        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return array();
        }

        return array_values( array_filter( $terms, static fn( $term ): bool => $term instanceof WP_Term ) );
    }

    private function get_term_links( array $terms ): array {
        $links = array();

        foreach ( $terms as $term ) {
            $link = get_term_link( $term );
            if ( is_wp_error( $link ) || empty( $link ) ) {
                continue;
            }

            $links[] = $link;
        }

        return array_values( array_unique( $links ) );
    }

    private function normalize_names( array $names ): array {
        $names = array_map( static fn( $name ): string => sanitize_text_field( trim( (string) $name ) ), $names );
        $names = array_filter( $names, static fn( $name ): bool => $name !== '' );

        return array_values( array_unique( $names ) );
    }

    private function get_default_names(): array {
        $names = array();

        for ( $index = 1; $index <= self::DEFAULT_CATEGORY_COUNT; $index++ ) {
            $names[] = sprintf( __( 'Category %d', 'hostinger-ai-theme' ), $index );
        }

        return $names;
    }

    private function store_created_ids( array $created_ids ): void {
        if ( empty( $created_ids ) ) {
            return;
        }

        $stored_ids = get_option( self::CREATED_OPTION, array() );

        update_option(
            self::CREATED_OPTION,
            array_values( array_unique( array_map( 'intval', array_merge( $stored_ids, $created_ids ) ) ) )
        );
    }
}