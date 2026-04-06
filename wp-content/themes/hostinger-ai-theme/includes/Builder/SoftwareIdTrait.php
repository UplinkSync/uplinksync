<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

trait SoftwareIdTrait {

    private function get_software_id(): ?string {
        $software_id = get_option( 'hostinger_sfid' );
        if ( ! empty( $software_id ) ) {
            return (string) $software_id;
        }

        $siteurl = get_option( 'siteurl', '' );
        $domain  = parse_url( $siteurl, PHP_URL_HOST );
        if ( empty( $domain ) ) {
            return null;
        }

        $response = $this->wh_api_client->get( '/api/v1/installations', array( 'domain' => $domain ) );
        if ( empty( $response[0]['id'] ) ) {
            return null;
        }

        $software_id = (string) $response[0]['id'];

        update_option( 'hostinger_sfid', $software_id, true );

        return $software_id;
    }
}
