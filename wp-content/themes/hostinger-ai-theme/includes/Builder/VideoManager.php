<?php

namespace Hostinger\AiTheme\Builder;

use stdClass;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\AiTheme\Rest\Endpoints;

defined( 'ABSPATH' ) || exit;

class VideoManager {
    private const USED_VIDEOS_OPTION = 'hostinger_ai_used_videos';
    private const FALLBACK_VIDEO_URL = 'https://videos.pexels.com/video-files/3992590/3992590-uhd_4096_2160_25fps.mp4';
    private const TARGET_VIDEO_WIDTH = 1920;
    public string $keyword;
    public string $keyword_slug = '';
    private Utils $helper;
    private Client $client;
    private Client $ai_client;
    private Config $config_handler;

    public function __construct( string $keyword = '' ) {
        $this->keyword = $keyword;
        if ( ! empty( $this->keyword ) ) {
            $this->keyword_slug = sanitize_title( $this->keyword );
        }
        $this->helper         = new Helper();
        $this->config_handler = new Config();
        $this->client         = new Client(
            $this->config_handler->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ),
            [
                Config::TOKEN_HEADER  => $this->helper::getApiToken(),
                Config::DOMAIN_HEADER => $this->helper->getHostInfo(),
                'Content-Type'        => 'application/json'
            ]
        );
        $this->ai_client = new Client(
            $this->config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ),
            [
                Config::TOKEN_HEADER  => $this->helper::getApiToken(),
                Config::DOMAIN_HEADER => $this->helper->getHostInfo(),
                'Content-Type'        => 'application/json'
            ]
        );
    }

    public function get_video_data( bool $random = false ): object {
        $video_list = $this->fetch_video_list();

        return $this->pick_video_from_list( $video_list, $random );
    }

    public function pick_video_from_list( array $video_list, bool $random = false ): object {
        $used_videos = get_option( self::USED_VIDEOS_OPTION, [] );

        if ( empty( $video_list ) ) {
            return new stdClass();
        }

        if ( ! empty( $random ) ) {
            shuffle( $video_list );
        }

        $all_used_urls = $this->get_all_used_video_urls( $used_videos );

        foreach ( $video_list as $video ) {
            if ( ! isset( $video->url ) || $video->url === '' ) {
                continue;
            }

            if ( ! in_array( $video->url, $all_used_urls, true ) ) {
                $used_videos[ $this->keyword_slug ][ $video->url ] = $video;
                update_option( self::USED_VIDEOS_OPTION, $used_videos, false );

                return $video;
            }
        }

        update_option( self::USED_VIDEOS_OPTION, [], false );

        foreach ( $video_list as $video ) {
            if ( isset( $video->url ) && $video->url !== '' ) {
                return $video;
            }
        }

        return new stdClass();
    }

    private function get_all_used_video_urls( array $used_videos ): array {
        $all_urls = [];

        foreach ( $used_videos as $keyword_videos ) {
            if ( is_array( $keyword_videos ) ) {
                $all_urls = array_merge( $all_urls, array_keys( $keyword_videos ) );
            }
        }

        return array_unique( $all_urls );
    }

    private function get_software_id(): ?string {
        $software_id = get_option( 'hostinger_sfid' );
        if ( ! empty( $software_id ) ) {
            return (string) $software_id;
        }

        $config_software_id = $this->config_handler->getConfigValue( 'software_id', '' );
        if ( ! empty( $config_software_id ) ) {
            update_option( 'hostinger_sfid', $config_software_id, true );

            return (string) $config_software_id;
        }

        $siteurl = get_option( 'siteurl', '' );
        $domain  = parse_url( $siteurl, PHP_URL_HOST );
        if ( empty( $domain ) ) {
            return null;
        }

        $response = $this->client->get( '/api/v1/installations', [
            'domain' => $domain,
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        if ( $response_code !== 200 || empty( $response_data['data'][0]['id'] ) ) {
            return null;
        }

        $software_id = (string) $response_data['data'][0]['id'];

        update_option( 'hostinger_sfid', $software_id, true );

        return $software_id;
    }

    public function fetch_video_list(): array {
        try {
            $software_id = $this->get_software_id();

            if ( empty( $software_id ) ) {
                return [];
            }

            $search_query = $this->extract_search_query( $this->keyword );

            if ( empty( $search_query ) ) {
                $search_query = $this->keyword;
            }

            $endpoint = '/api/v1/installations/' . $software_id . '/content/videos';

            $response = $this->client->get( $endpoint, [
                'query' => $search_query,
            ] );

            if ( is_wp_error( $response ) ) {
                return [];
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            $response_data = json_decode( $response_body, true );

            if ( $response_code !== 200 ) {
                return [];
            }

            $video_items = $response_data['data'] ?? [];

            if ( empty( $video_items ) || ! is_array( $video_items ) ) {
                return [];
            }

            $videos = [];

            foreach ( $video_items as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                $video_data = $this->extract_video_data( $item );

                if ( ! empty( $video_data['url'] ) ) {
                    $videos[] = (object) [
                        'url'         => $video_data['url'],
                        'thumbnail'   => $video_data['thumbnail'] ?? '',
                        'description' => $this->keyword,
                    ];
                }
            }

            return $videos;
        } catch ( \Exception $exception ) {
            error_log( 'Hostinger AI Theme - Error fetching video list: ' . $exception->getMessage() );
        }

        return [];
    }

    private function extract_search_query( string $description ): string {
        if ( empty( $description ) ) {
            return '';
        }

        $word_count = count( array_filter( preg_split( '/\s+/', trim( $description ) ) ) );
        if ( $word_count <= 3 ) {
            return $description;
        }

        try {
            $system_message = [
                'role'    => 'system',
                'content' => 'From the provided text, extract 1-3 concise keywords suitable for searching stock videos on Pexels. '
                             . 'Focus on the main topic or theme — it can be a business type, activity, scene, or subject. '
                             . 'Examples: "italian restaurant", "hair salon", "fitness training", "coffee shop", "wedding ceremony", "office teamwork", "nature hiking". '
                             . 'Output only the keywords as plain text, nothing else.',
            ];

            $user_message = [
                'role'    => 'user',
                'content' => $description,
            ];

            $domain = parse_url( get_site_url(), PHP_URL_HOST );
            $domain = preg_replace( '/^www\./', '', $domain );

            $request_body = [
                'domain'   => $domain,
                'messages' => [ $system_message, $user_message ],
            ];

            $response = $this->ai_client->post( Endpoints::GENERATE_BLOCKS_ENDPOINT, json_encode( $request_body ) );

            if ( is_wp_error( $response ) ) {
                return mb_substr($description, 0, 250);
            }

            $response_code = wp_remote_retrieve_response_code( $response );

            if ( $response_code !== 200 ) {
                return mb_substr($description, 0, 250);
            }

            $body    = json_decode( wp_remote_retrieve_body( $response ), true );
            $content = trim( $body['data']['content'] ?? '' );

            $content = trim( $content, '"\'' );
            $content = preg_replace( '/\s+/', ' ', $content );

            if ( empty( $content ) ) {
                return mb_substr($description, 0, 250);
            }

            $words = explode( ' ', $content );
            if ( count( $words ) > 5 ) {
                $content = implode( ' ', array_slice( $words, 0, 5 ) );
            }

            return $content;
        } catch ( \Exception $e ) {
            return $description;
        }
    }

    private function extract_video_data( array $item ): array {
        return [
            'url'       => $this->extract_best_video_url( $item ),
            'thumbnail' => $item['imageLink'] ?? '',
        ];
    }

    private function extract_best_video_url( array $item ): string {
        if ( ! empty( $item['videoFiles'] ) && is_array( $item['videoFiles'] ) ) {
            $best_file  = null;
            $best_width = 0;

            foreach ( $item['videoFiles'] as $file ) {
                if ( empty( $file['videoLink'] ) ) {
                    continue;
                }

                $width = (int) ( $file['width'] ?? 0 );

                if ( $best_file === null || abs( $width - self::TARGET_VIDEO_WIDTH ) < abs( $best_width - self::TARGET_VIDEO_WIDTH ) ) {
                    $best_file  = $file['videoLink'];
                    $best_width = $width;
                }
            }

            if ( ! empty( $best_file ) ) {
                return $best_file;
            }
        }

        return $item['url'] ?? $item['video_url'] ?? $item['videoLink'] ?? self::FALLBACK_VIDEO_URL;
    }
}
