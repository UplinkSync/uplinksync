<?php
/**
 * Builder Rest API
 *
 */

namespace Hostinger\AiTheme\Rest;

use Hostinger\AiTheme\Admin\GutenbergPreferences;
use Hostinger\AiTheme\Builder\ElementorBuilder;
use Hostinger\AiTheme\Constants\BuilderType;
use Hostinger\AiTheme\Builder\Elementor\KitManager;
use Hostinger\AiTheme\Builder\Fonts;
use Hostinger\AiTheme\Builder\WebsiteBuilder;
use Hostinger\AiTheme\Builder\SoftwareIdTrait;
use Hostinger\AiTheme\Builder\RequestClient;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Utils;
use Hostinger\Amplitude\AmplitudeManager;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Http;
use WP_Theme_JSON_Resolver;

/**
 * Avoid possibility to get file accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

/**
 * Class for handling Settings Rest API
 */
class BuilderRoutes {
    use SoftwareIdTrait;
    private const AMPLITUDE_EVENT_CREATE = 'wordpress.ai_builder.create';
    private const AMPLITUDE_EVENT_CREATED = 'wordpress.ai_builder.created';
    private const AMPLITUDE_EVENT_FAILURE = 'wordpress.ai_builder.failure';

    /**
     * @var WebsiteBuilder
     */
    private WebsiteBuilder $website_builder;

    /**
     * @var RequestClient
     */
    private RequestClient $request_client;

    /**
     * @var RequestClient
     */
    private RequestClient $wh_api_client;

    /**
     * @var AmplitudeManager
     */
    private AmplitudeManager $amplitude_manager;

    /**
     * @param WebsiteBuilder $website_builder
     */
    public function __construct( WebsiteBuilder $website_builder ) {
        $this->website_builder = $website_builder;

        $helper = new Utils();
        $config_handler = new Config();
        $default_headers = [
            Config::TOKEN_HEADER  => $helper::getApiToken(),
            Config::DOMAIN_HEADER => $helper->getHostInfo(),
            'Content-Type' => 'application/json'
        ];

        $amplitude_headers = array_diff_key( $default_headers, array( 'Content-Type' => '' ) );

        $client = new Client(
            $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
            $default_headers
        );
        $this->request_client = new RequestClient( $client );

        $wh_api_client = new Client(
            $config_handler->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ),
            $default_headers
        );
        $this->wh_api_client = new RequestClient( $wh_api_client );

        $amplitude_client = new Client(
            $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
            $amplitude_headers
        );
        $this->amplitude_manager = new AmplitudeManager( $helper, $config_handler, $amplitude_client );

        add_action(
            'hostinger_ai_color_contrast_adjusted',
            function ( string $color_role, string $original_hex, string $adjusted_hex, float $ratio_before, float $ratio_after ) {
                $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, array(
                    'action'       => 'wordpress.ai_builder.color_contrast_adjusted',
                    'color_role'   => $color_role,
                    'original_hex' => $original_hex,
                    'adjusted_hex' => $adjusted_hex,
                    'ratio_before' => round( $ratio_before, 2 ),
                    'ratio_after'  => round( $ratio_after, 2 ),
                    'builder_type' => get_option( 'hostinger_ai_builder_type', 'unknown' ),
                ) );
            },
            10,
            5
        );
    }

    public function set_fonts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $heading_font = $request->get_param( 'heading_font' );
        $body_font    = $request->get_param( 'body_font' );

        $theme_fonts    = wp_get_global_settings()['typography']['fontFamilies']['theme'] ?? [];
        $valid_families = array_column( $theme_fonts, 'fontFamily' );

        if ( ! in_array( $heading_font, $valid_families, true ) ) {
            return new WP_Error(
                'invalid_heading_font',
                __( 'Invalid heading font.', 'hostinger-ai-theme' ),
                [ 'status' => WP_Http::BAD_REQUEST ]
            );
        }

        if ( $body_font && ! in_array( $body_font, $valid_families, true ) ) {
            return new WP_Error(
                'invalid_body_font',
                __( 'Invalid body font.', 'hostinger-ai-theme' ),
                [ 'status' => WP_Http::BAD_REQUEST ]
            );
        }

        if ( $body_font ) {
            update_option( 'hostinger_ai_body_font_override', $body_font );
        } else {
            delete_option( 'hostinger_ai_body_font_override' );
        }

        update_option( 'hostinger_ai_font', $heading_font );
        update_option( 'hostinger_ai_version', time() );

        $theme_json_data = [ 'settings' => wp_get_global_settings() ];
        $resolved_body   = ( new Fonts() )->get_body_font( $theme_json_data, $heading_font );

        $builder_type = get_option( 'hostinger_ai_builder_type', 'gutenberg' );
        if ( $builder_type === 'elementor' ) {
            ( new ElementorBuilder() )->boot();
            ( new KitManager() )->transform_custom_typography( $heading_font, $resolved_body );
        } else {
            if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
                WP_Theme_JSON_Resolver::clean_cached_data();
            }
        }

        if ( class_exists( '\LiteSpeed\Purge' ) ){
            $purge = new \LiteSpeed\Purge();
            $purge::purge_all('Hostinger AI Theme Fonts change');
        }

        return new WP_REST_Response( [ 'success' => true ], WP_Http::OK );
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function generate_colors( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_params();

        $validation_error = $this->validate_required_fields( $parameters );
        if ( $validation_error ) {
            $this->send_failure_event( 'colors', 'validation', $validation_error->get_error_message() );
            return $validation_error;
        }

        $detection_error = $this->ensure_brand_and_type( $parameters );
        if ( $detection_error ) {
            $this->send_failure_event( 'brand_detection', 'ai_endpoint', $detection_error->get_error_message() );
            return $detection_error;
        }

        $this->normalize_website_type( $parameters );

        $this->clear_ai_data( $parameters['website_type'], $parameters['builder_type'] );
        $this->handle_affiliate_type( $parameters );
        $this->handle_online_store_options( $parameters );
        $this->save_website_options( $parameters );

        $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, array(
            'action'       => self::AMPLITUDE_EVENT_CREATE,
            'builder_type' => $parameters['builder_type'],
            'website_type' => implode( ', ', $parameters['website_type'] ),
        ) );

        try {
            $colors_generated = $this->website_builder->generate_colors( $parameters['description'] );

            if ( ! $colors_generated ) {
                $this->send_failure_event( 'colors', 'ai_endpoint', 'Colors generation failed' );

                return new WP_Error(
                    'colors_generation_failed',
                    __( 'Failed to generate colors.', 'hostinger-ai-theme' ),
                    array(
                        'status' => WP_Http::SERVICE_UNAVAILABLE,
                        'error'  => 'Colors generation failed',
                    )
                );
            }

            $data = array(
                'colors_generated' => $colors_generated
            );
        } catch ( Exception $e ) {
            $this->send_failure_event( 'colors', 'ai_endpoint', $e->getMessage() );

            return new WP_Error(
                'colors_generation_failed',
                __( 'Failed to generate colors.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $e->getMessage(),
                )
            );
        }

        $response = new \WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( \WP_Http::OK );

        return $response;
    }

    public function generate_color_options( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $description = $request->get_param( 'description' );

        if ( empty( $description ) ) {
            return new WP_Error(
                'data_invalid',
                __( 'Description is required.', 'hostinger-ai-theme' ),
                array( 'status' => \WP_Http::BAD_REQUEST )
            );
        }

        $description = sanitize_text_field( $description );

        $data = array(
            'data' => array(
                'color_options' => $this->website_builder->generate_color_options( $description ),
            ),
        );

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    private function validate_required_fields( array &$parameters ): ?\WP_Error {
        $required_fields = array(
            'description',
            'language',
            'builder_type',
        );

        $errors = array();

        foreach ( $required_fields as $field_key ) {
            if ( empty( $parameters[ $field_key ] ) ) {
                $errors[ $field_key ] = $field_key . ' is missing.';
            } else {
                $parameters[ $field_key ] = sanitize_text_field( $parameters[ $field_key ] );
            }
        }

        if ( ! empty( $errors ) ) {
            return new \WP_Error(
                'data_invalid',
                __( 'Sorry, something wrong with data.', 'hostinger-ai-theme' ),
                array(
                    'status' => \WP_Http::BAD_REQUEST,
                    'errors' => $errors,
                    'error'  => 'Validation failed: ' . implode( ', ', array_keys( $errors ) ) . ' missing',
                )
            );
        }

        return null;
    }

    private function normalize_website_type( array &$parameters ): void {
        if ( ! empty( $parameters['website_type'] ) && is_array( $parameters['website_type'] ) ) {
            $parameters['website_type'] = array_map( 'sanitize_text_field', $parameters['website_type'] );
        } elseif ( ! empty( $parameters['website_type'] ) && is_string( $parameters['website_type'] ) ) {
            $parameters['website_type'] = [ sanitize_text_field( $parameters['website_type'] ) ];
        } else {
            $parameters['website_type'] = [ 'other' ];
        }
    }

    private function ensure_brand_and_type( array &$parameters ): ?\WP_Error {
        $needs_detection = empty( $parameters['brand_name'] ) || empty( $parameters['website_type'] );

        if ( ! $needs_detection ) {
            $parameters['brand_name']   = sanitize_text_field( $parameters['brand_name'] );
            $parameters['website_type'] = array_map( 'sanitize_text_field', $parameters['website_type'] );
            return null;
        }

        $original_brand_name   = ! empty( $parameters['brand_name'] ) ? sanitize_text_field( $parameters['brand_name'] ) : null;
        $original_website_type = ! empty( $parameters['website_type'] ) ? array_map( 'sanitize_text_field', $parameters['website_type'] ) : null;

        $detection_error = $this->perform_brand_and_type_detection( $parameters );

        if ( $detection_error ) {
            return $detection_error;
        }

        // If brand name was provided by frontend, we need to use it instead of the one detected.
        if ( $original_brand_name !== null ) {
            $parameters['brand_name'] = $original_brand_name;
        }

        // If website type was provided by frontend, we need to use it instead of the one detected.
        if ( $original_website_type !== null ) {
            $parameters['website_type'] = $original_website_type;
        }

        return null;
    }

    private function perform_brand_and_type_detection( array &$parameters ): ?WP_Error {
        try {
            $is_multiple_types = true;

            $detection_data = $this->call_detect_brand_and_type_service( $parameters['description'], $is_multiple_types );

            if ( ! $this->is_valid_detection_data( $detection_data ) ) {
                return new WP_Error(
                    'ai_service_error',
                    __( 'Failed to detect brand name and website type.', 'hostinger-ai-theme' ),
                    array(
                        'status' => WP_Http::SERVICE_UNAVAILABLE,
                        'error'  => 'Brand detection failed - invalid detection data',
                    )
                );
            }

            $parameters['brand_name'] = sanitize_text_field( $detection_data['brandName'] );

            if ( ! empty( $detection_data['websiteType'] ) && is_array( $detection_data['websiteType'] ) ) {
                $parameters['website_type'] = array_map( function ( $t ) {
                    return strtolower( sanitize_text_field( $t ) );
                }, $detection_data['websiteType'] );
            } else {
                $parameters['website_type'] = [ strtolower( sanitize_text_field( $detection_data['websiteType'] ) ) ];
            }

            return null;
        } catch ( Exception $e ) {
            return new WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $e->getMessage(),
                )
            );
        }
    }

    private function is_valid_detection_data( array $detection_data ): bool {
        if ( empty( $detection_data ) ) {
            return false;
        }

        return isset( $detection_data['brandName'] ) && isset( $detection_data['websiteType'] );
    }

    private function handle_affiliate_type( array $parameters ): void {
        if ( ! in_array( 'affiliate-marketing', $parameters['website_type'], true ) ) {
            return;
        }

        update_option( 'hostinger_ai_affiliate', true );
    }

    private function clear_ai_data( array $website_type, string $builder_type ): void {
        $this->website_builder->clear_ai_content();
        $this->website_builder->clear_ai_data( $website_type, $builder_type );
    }

    private function handle_online_store_options( array $parameters ): void {
        if ( ! in_array( 'online store', $parameters['website_type'], true ) ) {
            return;
        }

        update_option( 'hostinger_ai_woo', true );
    }

    private function save_website_options( array $parameters ): void {
        update_option( 'blogname', $parameters['brand_name'] );
        update_option( 'hostinger_ai_brand_name', $parameters['brand_name'] );
        update_option( 'hostinger_ai_website_type', $parameters['website_type'] );
        update_option( 'hostinger_ai_description', $parameters['description'] );
        update_option( 'hostinger_ai_builder_type', $parameters['builder_type'] );
        update_option( 'hostinger_ai_selected_language', $parameters['language'] );
    }

    public function enable_plugins( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $results = $this->website_builder->enable_plugins();

        $plugin_step_map = array(
            'elementor'   => 'elementor_installation',
            'woocommerce' => 'woocommerce_installation',
            'affiliate'   => 'affiliate_installation',
            'reach'       => 'reach_installation',
        );

        $has_failures = false;
        $failed_plugins = array();
        foreach ( $results as $plugin => $result ) {
            if ( $result['status'] === 'failed' ) {
                $has_failures = true;
                $failed_plugins[] = $plugin;
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "Hostinger AI Theme: $plugin plugin installation failed - " . $result['error'] );
                }

                $failed_step = $plugin_step_map[ $plugin ] ?? 'plugin_enable';
                $this->send_failure_event( $failed_step, 'plugin_installation', 'Plugin installation failed: ' . $plugin );
            }
        }

        $data = array(
            'success' => ! $has_failures,
            'plugins' => $results,
        );

        if ( $has_failures ) {
            $failed_plugins_str = implode( ', ', $failed_plugins );
            $error_message = 'Plugin installation failed: ' . $failed_plugins_str;

            return new WP_Error(
                'plugin_installation_failed',
                __( 'Plugin installation failed.', 'hostinger-ai-theme' ),
                array(
                    'status'  => WP_Http::SERVICE_UNAVAILABLE,
                    'plugins' => $results,
                    'error'   => $error_message,
                )
            );
        }

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function generate_structure( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $colors = get_option( 'hostinger_ai_colors', false );

        if ( empty( $colors ) ) {
            $this->send_failure_event( 'structure', 'validation', 'Wrong sequence of step execution - colors missing' );

            return new WP_Error(
                'data_invalid',
                __( 'Wrong sequence of step execution.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::BAD_REQUEST,
                    'error'  => 'Wrong sequence of step execution - colors missing',
                )
            );
        }

        $brand_name   = get_option( 'hostinger_ai_brand_name' );
        $website_type = WebsiteTypeHelper::get_website_types();
        $description  = get_option( 'hostinger_ai_description' );

        try {
            $structure_generated = $this->website_builder->generate_structure( $brand_name, $website_type, $description );

            if ( ! $structure_generated ) {
                $this->send_failure_event( 'structure', 'ai_endpoint', 'Structure generation failed - empty response' );

                return new WP_Error(
                    'structure_generation_failed',
                    __( 'Failed to generate structure.', 'hostinger-ai-theme' ),
                    array(
                        'status' => WP_Http::SERVICE_UNAVAILABLE,
                        'error'  => 'Structure generation failed',
                    )
                );
            }

            $data = array(
                'structure_generated' => $structure_generated,
            );
        } catch ( Exception $e ) {
            $this->send_failure_event( 'structure', 'ai_endpoint', $e->getMessage() );

            return new WP_Error(
                'structure_generation_failed',
                __( 'Failed to generate structure.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $e->getMessage(),
                )
            );
        }

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function generate_content( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $website_structure = get_option( 'hostinger_ai_website_structure', false );

        if ( empty( $website_structure ) ) {
            $this->send_failure_event( 'content', 'validation', 'Wrong sequence of step execution - structure missing' );

            return new WP_Error(
                'data_invalid',
                __( 'Wrong sequence of step execution.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::BAD_REQUEST,
                    'error'  => 'Wrong sequence of step execution - structure missing',
                )
            );
        }
        $headers = $request->get_headers();

        if ( ! empty( $headers['x_correlation_id'] ) ) {
            update_option( 'hts_correlation_id', $headers['x_correlation_id'][0] );
        }

        $brand_name   = get_option( 'hostinger_ai_brand_name' );
        $website_type = WebsiteTypeHelper::get_website_types();
        $description  = get_option( 'hostinger_ai_description' );

        try {

            $data = array(
                'content_generated' => $this->website_builder->generate_content( $brand_name, $website_type, $description )
            );

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $error_code = 'content_generation_failed';
            $failure_type = 'ai_endpoint';

            // Check if this is a plugin installation failure
            if ( strpos( $error_message, 'plugin installation failed' ) !== false ) {
                $error_code = 'plugin_installation_failed';
                $failure_type = 'plugin_installation';
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Hostinger AI Theme: Plugin installation failed during content generation - ' . $error_message );
                }
            }

            $this->send_failure_event( 'content', $failure_type, $error_message );

            return new WP_Error(
                $error_code,
                __( 'Problem generating content.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $error_message,
                )
            );
        }

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function build_content( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $website_content = get_option( 'hostinger_ai_website_content', false );

        if ( empty( $website_content ) ) {
            $this->send_failure_event( 'buildContent', 'validation', 'Wrong sequence of step execution - content missing' );

            return new WP_Error(
                'data_invalid',
                __( 'Wrong sequence of step execution.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::BAD_REQUEST,
                    'error'  => 'Wrong sequence of step execution - content missing',
                )
            );
        }

        try {

            $data = array(
                'content_built' => $this->website_builder->build_content( $website_content )
            );

        } catch (Exception $e) {
            $this->send_failure_event( 'buildContent', 'build_content', $e->getMessage() );

            return new WP_Error(
                'build_content_failed',
                __( 'Problem building content.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                    'error'  => $e->getMessage(),
                )
            );
        }

        $builder_type = get_option( 'hostinger_ai_builder_type', '' );
        if ( $builder_type === BuilderType::GUTENBERG ) {
            ( new GutenbergPreferences() )->disable_welcome_guide();
        }

        // Purge LiteSpeed cache.
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        delete_option( 'rewrite_rules' );

        $website_type_for_amplitude = WebsiteTypeHelper::get_website_types();

        $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, array(
            'action'       => self::AMPLITUDE_EVENT_CREATED,
            'builder_type' => get_option( 'hostinger_ai_builder_type', '' ),
            'website_type' => implode( ', ', $website_type_for_amplitude ),
        ) );

        $response = new WP_REST_Response( $data );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function enhance_prompt( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_params();
        $text = $parameters['text']; // Already sanitized and validated by Routes.php

        try {
            $enhanced_text = $this->call_ai_enhancement_service( $text );

            $data = array(
                'data' => array(
                    'improved_prompt' => $enhanced_text
                )
            );

            $response = new WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( WP_Http::OK );

            return $response;

        } catch ( Exception $e ) {
            return new WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                )
            );
        }
    }

    /**
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function detect_brand_and_type( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_params();
        $description = $parameters['description']; // Already sanitized and validated by Routes.php
        $is_multiple_types = isset( $parameters['is_multiple_types'] )
            ? filter_var( $parameters['is_multiple_types'], FILTER_VALIDATE_BOOLEAN )
            : true;

        try {
            $response_data = $this->call_detect_brand_and_type_service( $description, $is_multiple_types );
            $this->validate_detect_response( $response_data );

            $website_type = [];
            if ( ! empty( $response_data['websiteType'] ) && is_array( $response_data['websiteType'] ) ) {
                $website_type = $response_data['websiteType'];
            } elseif ( ! empty( $response_data['websiteType'] ) && is_string( $response_data['websiteType'] ) ) {
                $website_type = [ $response_data['websiteType'] ];
            }

            $data = array(
                'data' => array(
                    'brandName'   => $response_data['brandName'],
                    'websiteType' => $website_type,
                )
            );

            $response = new WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( WP_Http::OK );

            return $response;

        } catch ( Exception $e ) {
            return new WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => WP_Http::SERVICE_UNAVAILABLE,
                )
            );
        }
    }

    public function set_colors( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $parameters = $request->get_json_params();
        if ( empty( $parameters['colors'] ) ) {
            return new WP_Error(
                'data_invalid',
                __( 'Colors data with color_palette is required.', 'hostinger-ai-theme' ),
                array(
                    'status' => WP_Http::BAD_REQUEST,
                )
            );
        }

        try {
            $description = get_option('hostinger_ai_description' );

            $data = array(
                'colors_set' => $this->website_builder->set_colors( $description, $parameters['colors'] ),
            );

            $response = new WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( WP_Http::OK );

            return $response;
        } catch ( Exception $e ) {
            return new WP_Error(
                'set_colors_error',
                __( 'Problem setting colors.', 'hostinger-ai-theme' ),
                array(
                    'status' => \WP_Http::BAD_REQUEST,
                    'error'  => $e->getMessage(),
                )
            );
        }
    }

    private function validate_detect_response( array $response_data ): void {
        if ( empty( $response_data ) ) {
            throw new Exception( 'Detect brand and type service returned empty response' );
        }

        if ( empty( $response_data['brandName'] ) ) {
            throw new Exception( 'Detect brand and type service did not return brandName' );
        }

        if ( empty( $response_data['websiteType'] ) ) {
            throw new Exception( 'Detect brand and type service did not return websiteType' );
        }
    }

    private function enhance_text_content( string $text ): string {
        return $this->call_ai_enhancement_service( $text );
    }

    private function call_ai_enhancement_service( string $text ): string {
        try {
            $request_params = ['text' => $text];
            $response_data = $this->request_client->post( '/v3/wordpress/plugin/builder/prompt-enhance', $request_params );
            $this->validate_enhancement_response( $response_data );

            return $response_data['improved_prompt'];

        } catch ( Exception $exception ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'AI Enhancement API Error: ' . $exception->getMessage() );
            }
            throw new Exception( 'AI enhancement service temporarily unavailable: ' . $exception->getMessage() );
        }
    }

    private function validate_enhancement_response( array $response_data ): void {
        if ( empty( $response_data ) ) {
            throw new Exception( 'AI enhancement service returned empty response' );
        }

        if ( ! isset( $response_data['improved_prompt'] ) || empty( $response_data['improved_prompt'] ) ) {
            throw new Exception( 'AI enhancement service did not return improved prompt' );
        }
    }

    private function call_detect_brand_and_type_service( string $description, bool $is_multiple_types = false ): array {
        try {
            $software_id = $this->get_software_id();
            if ( empty( $software_id ) ) {
                throw new Exception( 'Software ID not available' );
            }

            $request_params = [
                'description' => $description,
                'isMultipleTypes' => $is_multiple_types,
            ];
            $response_data = $this->wh_api_client->post( '/api/v1/installations/' . $software_id . '/content/detect/brand-and-type', $request_params );

            if ( empty( $response_data ) ) {
                throw new Exception( 'Detect brand and type service returned empty response' );
            }

            return $response_data;

        } catch ( Exception $exception ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Detect Brand and Type API Error: ' . $exception->getMessage() );
            }
            throw new Exception( 'Detect brand and type service temporarily unavailable: ' . $exception->getMessage() );
        }
    }

    /**
     * Send amplitude failure event with context.
     *
     * @param string $failed_step   The step that failed (e.g., 'colors', 'structure', 'content', 'buildContent', 'plugin_enable')
     * @param string $failure_type  The category of failure (e.g., 'ai_endpoint', 'plugin_installation', 'validation', 'build_content')
     * @param string $error_message The error message (will be sanitized)
     */
    private function send_failure_event( string $failed_step, string $failure_type, string $error_message ): void {
        $this->amplitude_manager->sendRequest( Endpoints::AMPLITUDE_ENDPOINT, array(
            'action'        => self::AMPLITUDE_EVENT_FAILURE,
            'failed_step'   => $failed_step,
            'failure_type'  => $failure_type,
            'error_message' => $this->sanitize_error_message( $error_message ),
            'builder_type'  => get_option( 'hostinger_ai_builder_type', 'unknown' ),
            'website_type'  => get_option( 'hostinger_ai_website_type', 'unknown' ),
        ) );
    }

    /**
     * Sanitize error message for amplitude tracking.
     * Removes sensitive data like file paths and long tokens.
     *
     * @param string $error_message
     *
     * @return string
     */
    private function sanitize_error_message( string $error_message ): string {
        // Remove file paths
        $sanitized = preg_replace( '#(/[a-zA-Z0-9._-]+){3,}#', '[path_redacted]', $error_message );
        // Remove long tokens/hashes (32+ chars)
        $sanitized = preg_replace( '/[a-zA-Z0-9]{32,}/', '[token_redacted]', $sanitized );
        // Truncate to 500 chars
        if ( strlen( $sanitized ) > 500 ) {
            $sanitized = substr( $sanitized, 0, 500 ) . '...';
        }

        return $sanitized;
    }

    /**
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function check_scam( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
        $parameters = $request->get_params();
        $description = $parameters['description'];
        $language = $parameters['language'] ?? '';

        try {
            $response_data = $this->call_scam_detector_service( $description, $language );

            $data = array(
                'data' => array(
                    'isScam' => $response_data['isScam'] ?? false,
                    'scamReason' => $response_data['scamReason'] ?? null,
                )
            );

            $response = new \WP_REST_Response( $data );
            $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
            $response->set_status( \WP_Http::OK );

            return $response;

        } catch ( Exception $e ) {
            return new \WP_Error(
                'ai_service_error',
                $e->getMessage(),
                array(
                    'status' => \WP_Http::SERVICE_UNAVAILABLE,
                )
            );
        }
    }

    public function skip_ai_builder(): WP_REST_Response {
        update_option( 'hostinger_ai_version', uniqid(), true );

        $response = new WP_REST_Response( array( 'success' => true ) );
        $response->set_headers( array( 'Cache-Control' => 'no-cache' ) );
        $response->set_status( WP_Http::OK );

        return $response;
    }

    private function call_scam_detector_service( string $description, string $language = '' ): array {
        try {
            $software_id = $this->get_software_id();
            if ( empty( $software_id ) ) {
                throw new Exception( 'Software ID not available' );
            }

            if ( empty( $language ) ) {
                $language = get_option( 'hostinger_ai_selected_language', 'en_US' );
            }

            $language = sanitize_text_field( $language );

            $request_params = [
                'description' => $description,
                'language' => $language,
            ];
            $response_data = $this->wh_api_client->post( '/api/v1/installations/' . $software_id . '/content/detect/scam', $request_params );

            if ( empty( $response_data ) ) {
                throw new Exception( 'Scam detector service returned empty response' );
            }

            return $response_data;

        } catch ( Exception $exception ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Scam Detector API Error: ' . $exception->getMessage() );
            }
            throw new Exception( 'Scam detector service temporarily unavailable: ' . $exception->getMessage() );
        }
    }
}
