<?php

namespace Hostinger\AiTheme\Builder\ElementHandlers;

use DOMElement;
use WP_Query;

defined( 'ABSPATH' ) || exit;

class ButtonHandler extends BaseElementHandler {
    public function handle_gutenberg(DOMElement &$node, array $element_structure): void {
        $links = $node->getElementsByTagName('a');

        if ($links->length > 0) {
            $link = $links->item(0);
            $link->nodeValue = $element_structure['content'];
				$link->setAttribute('href', $element_structure['link'] ?? $this->get_random_link() );
        }
    }

    public function handle_elementor(array &$element, array $element_structure): void {
        if(empty($element['widgetType'])) {
            return;
        }

        if($element['widgetType'] !== 'button') {
            return;
        }

        $element['settings']['text'] = $element_structure['content'];
        $element['settings']['link']['url'] = $element_structure['link'] ?? $this->get_random_link();
    }

	private function get_random_link( $post_type = ['post', 'page', 'product'] ): string {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'rand',
		);

		$query = new WP_Query($args);

		if ($query->have_posts()) {
			$query->the_post();
			$permalink = get_permalink();
			wp_reset_postdata();
			return $permalink;
		}

		return site_url();
	}
}
