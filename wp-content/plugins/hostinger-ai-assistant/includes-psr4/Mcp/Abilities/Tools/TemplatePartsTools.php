<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class TemplatePartsTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai-assistant/template-parts-search',
                    'label'       => __( 'List Template Parts', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of template parts.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'List Template Parts',
                            'readonly' => true,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai-assistant/template-part-get',
                    'label'       => __( 'Get Template Part', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single template part by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Get Template Part',
                            'readonly' => true,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai-assistant/template-part-update',
                    'label'       => __( 'Update Template Part', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update a template part content or title.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Update Template Part',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai-assistant/template-part-delete',
                    'label'       => __( 'Delete Template Part', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a template part by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Template Part',
                            'readonly'        => false,
                            'destructive'     => true,
                            'destructiveHint' => true,
                            'idempotent'      => true,
                        ),
                    ),
                ),
            ),
            'WP_REST_Templates_Controller',
            '/wp/v2/template-parts',
            'wp_template_part'
        );

        $this->register_operations(
            array(
                'update' => array(
                    'tool_name'                  => 'hostinger-ai-assistant/template-part-assign-navigation',
                    'label'                      => __( 'Assign Navigation To Template Part', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Assign a navigation to a template part by providing template_part_id and navigation_id.', 'hostinger-ai-assistant' ),
                    'skip_ids'                   => true,
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'template_part_id' => array(
                                'type'        => 'integer',
                                'description' => __( 'Template part ID.', 'hostinger-ai-assistant' ),
                            ),
                            'navigation_id'    => array(
                                'type'        => 'integer',
                                'description' => __( 'Navigation (wp_navigation) post ID.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                        'required'   => array( 'template_part_id', 'navigation_id' ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'       => 'Assign Navigation',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
            ),
            'WP_REST_Templates_Controller',
            '/wp/v2/template-parts',
            'wp_template_part'
        );
    }
}
