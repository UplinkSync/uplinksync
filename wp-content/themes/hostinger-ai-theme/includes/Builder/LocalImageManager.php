<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class LocalImageManager {
    private const TESTIMONIALS_DIR = 'assets/images/testimonials/';
    private const COUNTER_OPTION = 'hostinger_ai_image_counter';

    public function get_local_image_data( bool $random = false ): array {
        $testimonials_path = get_template_directory() . '/' . self::TESTIMONIALS_DIR;
        $available_files   = $this->scan_testimonial_directory( $testimonials_path );

        if ( empty( $available_files ) ) {
            return [];
        }

        $current_counter = get_option( self::COUNTER_OPTION, 0 );
        $image_index     = $current_counter % count( $available_files );
        $filename        = $available_files[$image_index];
        $new_counter     = $current_counter + 1;

        update_option( self::COUNTER_OPTION, $new_counter, false );

        $image_url = get_template_directory_uri() . '/' . self::TESTIMONIALS_DIR . $filename;

        return [
            'image' => $image_url,
            'alt_description' => ''
        ];
    }

    private function scan_testimonial_directory( string $directory_path ): array {
        if ( ! is_dir( $directory_path ) ) {
            return [];
        }

        $files              = [];
        $allowed_extensions = [ 'jpg', 'jpeg', 'png', 'webp' ];
        $directory_files    = scandir( $directory_path );

        foreach ( $directory_files as $file ) {
            if ( $file === '.' || $file === '..' ) {
                continue;
            }

            $file_path = $directory_path . $file;
            if ( ! is_file( $file_path ) ) {
                continue;
            }

            $extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
            if ( ! in_array( $extension, $allowed_extensions ) ) {
                continue;
            }

            // Skip already resized images
            if ( preg_match( '/-\d+x\d+(-c)?\./', $file ) ) {
                continue;
            }

            $files[] = $file;
        }

        sort( $files );

        return $files;
    }

    public function modify_image_url( string $url, array $element_structure = null ): string {
        if ( empty( $element_structure['image_size'] ) ) {
            return $url;
        }

        $image_size = $element_structure['image_size'];
        $width      = intval( $image_size['width'] ?? 0 );
        $height     = intval( $image_size['height'] ?? 0 );
        $crop       = $image_size['crop'] ?? false;

        if ( $width <= 0 || $height <= 0 ) {
            return $url;
        }

        return $this->get_resized_image_url( $url, $width, $height, $crop );
    }

    private function get_resized_image_url( string $original_url, int $width, int $height, bool $crop ): string {
        $filename = basename( parse_url( $original_url, PHP_URL_PATH ) );
        $original_path = get_template_directory() . '/' . self::TESTIMONIALS_DIR . $filename;

        if ( ! file_exists( $original_path ) ) {
            return $original_url;
        }


        $file_info        = pathinfo( $filename );
        $crop_suffix      = $crop ? '-c' : '';
        $resized_filename = $file_info['filename'] . "-{$width}x{$height}{$crop_suffix}." . $file_info['extension'];
        $resized_path     = get_template_directory() . '/' . self::TESTIMONIALS_DIR . $resized_filename;
        $resized_url      = get_template_directory_uri() . '/' . self::TESTIMONIALS_DIR . $resized_filename;

        if ( file_exists( $resized_path ) ) {
            return $resized_url;
        }

        if ( $this->create_resized_image( $original_path, $resized_path, $width, $height, $crop ) ) {
            return $resized_url;
        }

        return $original_url;
    }

    private function create_resized_image( string $source_path, string $dest_path, int $width, int $height, bool $crop ): bool {
        $image_editor = wp_get_image_editor( $source_path );

        if ( is_wp_error( $image_editor ) ) {
            return false;
        }

        $resize_result = $image_editor->resize( $width, $height, $crop );

        if ( is_wp_error( $resize_result ) ) {
            return false;
        }

        $save_result = $image_editor->save( $dest_path );

        if ( is_wp_error( $save_result ) ) {
            return false;
        }

        return true;
    }
}
