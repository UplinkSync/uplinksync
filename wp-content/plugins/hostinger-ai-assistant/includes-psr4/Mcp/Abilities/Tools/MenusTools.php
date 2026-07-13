<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class MenusTools extends RestEndpointTool {
    public function register(): void {
        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai-assistant/menus-search',
                    'label'       => __( 'List Menus', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of navigation menus.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'List Menus',
                            'readonly' => true,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai-assistant/menus-get',
                    'label'       => __( 'Get Menu', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single navigation menu by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Get Menu',
                            'readonly' => true,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai-assistant/menus-create',
                    'label'       => __( 'Create Menu', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new navigation menu.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Add Menu',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai-assistant/menus-update',
                    'label'       => __( 'Update Menu', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing navigation menu by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Update Menu',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai-assistant/menus-delete',
                    'label'       => __( 'Delete Menu', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a navigation menu by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Menu',
                            'readonly'        => false,
                            'destructive'     => true,
                            'destructiveHint' => true,
                            'idempotent'      => true,
                        ),
                    ),
                ),
            ),
            'WP_REST_Menus_Controller',
            '/wp/v2/menus',
            'nav_menu'
        );

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai-assistant/menu-items-search',
                    'label'       => __( 'List Menu Items', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of menu items with filtering options.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'List Menu Items',
                            'readonly' => true,
                        ),
                    ),
                ),
                'get'    => array(
                    'tool_name'   => 'hostinger-ai-assistant/menu-items-get',
                    'label'       => __( 'Get Menu Item', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a single menu item by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'Get Menu Item',
                            'readonly' => true,
                        ),
                    ),
                ),
                'create' => array(
                    'tool_name'   => 'hostinger-ai-assistant/menu-items-create',
                    'label'       => __( 'Create Menu Item', 'hostinger-ai-assistant' ),
                    'description' => __( 'Create a new menu item. Provide title, url or object_id/object, and menu (menu_id).', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Add Menu Item',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => false,
                        ),
                    ),
                ),
                'update' => array(
                    'tool_name'   => 'hostinger-ai-assistant/menu-items-update',
                    'label'       => __( 'Update Menu Item', 'hostinger-ai-assistant' ),
                    'description' => __( 'Update an existing menu item, including label, URL, parent, and order.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'       => 'Update Menu Item',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
                'delete' => array(
                    'tool_name'   => 'hostinger-ai-assistant/menu-items-delete',
                    'label'       => __( 'Delete Menu Item', 'hostinger-ai-assistant' ),
                    'description' => __( 'Delete a menu item by ID.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'           => 'Delete Menu Item',
                            'readonly'        => false,
                            'destructive'     => true,
                            'destructiveHint' => true,
                            'idempotent'      => true,
                        ),
                    ),
                ),
            ),
            'WP_REST_Menu_Items_Controller',
            '/wp/v2/menu-items',
            'nav_menu_item'
        );

        $this->register_operations(
            array(
                'list'   => array(
                    'tool_name'   => 'hostinger-ai-assistant/menu-locations-list',
                    'label'       => __( 'List Menu Locations', 'hostinger-ai-assistant' ),
                    'description' => __( 'Get a list of theme menu locations and their assigned menus.', 'hostinger-ai-assistant' ),
                    'meta'        => array(
                        'annotations' => array(
                            'title'    => 'List Menu Locations',
                            'readonly' => true,
                        ),
                    ),
                    'skip_ids'    => true,
                ),
                'update' => array(
                    'tool_name'                  => 'hostinger-ai-assistant/menu-locations-assign',
                    'label'                      => __( 'Assign Menu To Location', 'hostinger-ai-assistant' ),
                    'description'                => __( 'Assign a menu to a given theme location (e.g., primary). Provide location and menu ID.', 'hostinger-ai-assistant' ),
                    'input_schema_modifications' => array(
                        'properties' => array(
                            'location' => array(
                                'type'        => 'string',
                                'description' => __( 'Theme location slug (e.g., primary, header, footer).', 'hostinger-ai-assistant' ),
                            ),
                            'menu'     => array(
                                'type'        => 'integer',
                                'description' => __( 'Menu ID to assign to the location.', 'hostinger-ai-assistant' ),
                            ),
                        ),
                        'required'   => array( 'location', 'menu' ),
                    ),
                    'meta'                       => array(
                        'annotations' => array(
                            'title'       => 'Assign Menu Location',
                            'readonly'    => false,
                            'destructive' => false,
                            'idempotent'  => true,
                        ),
                    ),
                ),
            ),
            'WP_REST_Menu_Locations_Controller',
            '/wp/v2/menu-locations',
            'menu_location'
        );
    }
}
