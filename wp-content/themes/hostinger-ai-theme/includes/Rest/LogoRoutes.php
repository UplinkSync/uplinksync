<?php

namespace Hostinger\AiTheme\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Http;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

class LogoRoutes {
	private const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/svg+xml' );
	private const MAX_FILE_SIZE = 2 * 1024 * 1024;

	public function set_logo( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = $request->get_param( 'attachment_id' );

		$post = get_post( $attachment_id );
		if ( ! $post || $post->post_type !== 'attachment' ) {
			return new WP_Error(
				'invalid_attachment',
				__( 'Attachment not found.', 'hostinger-ai-theme' ),
				array( 'status' => WP_Http::BAD_REQUEST )
			);
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
			return new WP_Error(
				'invalid_mime_type',
				__( 'Invalid file type. Allowed types: jpg, png, svg.', 'hostinger-ai-theme' ),
				array( 'status' => WP_Http::BAD_REQUEST )
			);
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error(
				'file_not_found',
				__( 'Attachment file not found on disk.', 'hostinger-ai-theme' ),
				array( 'status' => WP_Http::BAD_REQUEST )
			);
		}

		if ( filesize( $file_path ) > self::MAX_FILE_SIZE ) {
			return new WP_Error(
				'file_too_large',
				__( 'File size exceeds the 2 MB limit.', 'hostinger-ai-theme' ),
				array( 'status' => WP_Http::BAD_REQUEST )
			);
		}

		set_theme_mod( 'custom_logo', $attachment_id );

		$attachment_url = wp_get_attachment_image_url( $attachment_id, 'full' );

		$data = array(
			'data' => array(
				'logo_set'       => true,
				'attachment_id'  => $attachment_id,
				'attachment_url' => $attachment_url,
			),
		);

		$response = new WP_REST_Response( $data );
		$response->set_status( WP_Http::OK );

		return $response;
	}

	public function remove_logo( WP_REST_Request $request ): WP_REST_Response {
		remove_theme_mod( 'custom_logo' );

		$data = array(
			'data' => array(
				'logo_removed' => true,
			),
		);

		$response = new WP_REST_Response( $data );
		$response->set_status( WP_Http::OK );

		return $response;
	}
}
