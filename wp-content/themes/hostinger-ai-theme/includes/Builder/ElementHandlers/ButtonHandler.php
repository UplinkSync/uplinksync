<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use DOMElement;

defined( 'ABSPATH' ) || exit;

class ButtonHandler extends BaseElementHandler {
    public function handle_gutenberg( DOMElement &$node, array $element_structure ): void {
        $links = $node->getElementsByTagName( 'a' );

        if ( $links->length > 0 ) {
            $link            = $links->item( 0 );
            $link->nodeValue = $element_structure['content'];
            $link->setAttribute( 'href', $element_structure['link'] ?? home_url( '/' ) );
        }
    }

    public function handle_elementor( array &$element, array $element_structure ): void {
        if ( empty( $element['widgetType'] ) ) {
            return;
        }

        if ( $element['widgetType'] !== 'button' ) {
            return;
        }

        $element['settings']['text']        = $element_structure['content'];
        $element['settings']['link']['url'] = $element_structure['link'] ?? home_url( '/' );
    }
}
