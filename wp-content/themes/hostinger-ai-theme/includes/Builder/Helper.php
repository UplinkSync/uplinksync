<?php

namespace Hostinger\AiTheme\Builder;

use Language_Pack_Upgrader;
use Automatic_Upgrader_Skin;

defined( 'ABSPATH' ) || exit;

class Helper {
    /**
     * @param array $structure
     * @param array $element_data
     *
     * @return false|mixed
     */
    public function find_structure( array $structure, array $element_data ): mixed
    {
        foreach($structure as $index => $data) {
            if($data['class'] == $element_data['class'] && $data['index'] == $element_data['index']) {
                return $data;
            }
        }

        return array();
    }

    /**
     * @param string $string
     * @param string $pattern
     *
     * @return array
     */
    public function extract_class_names( string $string, string $pattern ): array {
        preg_match_all( $pattern, $string, $matches );
        return $matches[0];
    }

    /**
     * @param string $string
     *
     * @return int
     */
    public function extract_index_number( string $string ): int
    {
        $pattern = '/hostinger-index-(\d+)/';

        if (preg_match($pattern, $string, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    public static function get_elementor_version(): string {

        if ( defined('ELEMENTOR_VERSION') ) {
            return ELEMENTOR_VERSION;
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = WP_PLUGIN_DIR . '/elementor/elementor.php';

        if ( file_exists( $plugin_file ) ) {
            $plugin_data = get_plugin_data( $plugin_file, false, false );
            if ( ! empty( $plugin_data['Version'] ) ) {
                return $plugin_data['Version'];
            }
        }

        return '3.31.3';
    }

    public static function get_locale_name( string $wp_locale ): string {
        $locale_map = self::get_locale_mapping();

        return $locale_map[ $wp_locale ] ?? 'English';
    }

    public static function get_locale_mapping(): array {
        return array(
            'af' => 'Afrikaans',
            'ak' => 'Akan',
            'sq' => 'Albanian',
            'arq' => 'Algerian Arabic',
            'am' => 'Amharic',
            'ar' => 'Arabic',
            'hy' => 'Armenian',
            'rup_MK' => 'Aromanian',
            'frp' => 'Arpitan',
            'as' => 'Assamese',
            'az' => 'Azerbaijani',
            'az_TR' => 'Azerbaijani (Turkey)',
            'bcc' => 'Balochi Southern',
            'ba' => 'Bashkir',
            'eu' => 'Basque',
            'bel' => 'Belarusian',
            'bn_BD' => 'Bengali',
            'bs_BA' => 'Bosnian',
            'bre' => 'Breton',
            'bg_BG' => 'Bulgarian',
            'ca' => 'Catalan',
            'bal' => 'Catalan (Balear)',
            'ceb' => 'Cebuano',
            'zh_CN' => 'Chinese (China)',
            'zh_HK' => 'Chinese (Hong Kong)',
            'zh_TW' => 'Chinese (Taiwan)',
            'co' => 'Corsican',
            'hr' => 'Croatian',
            'cs_CZ' => 'Czech',
            'da_DK' => 'Danish',
            'dv' => 'Dhivehi',
            'nl_NL' => 'Dutch',
            'nl_BE' => 'Dutch (Belgium)',
            'dzo' => 'Dzongkha',
            'art_xemoji' => 'Emoji',
            'en_US' => 'English',
            'en_AU' => 'English (Australia)',
            'en_CA' => 'English (Canada)',
            'en_NZ' => 'English (New Zealand)',
            'en_SA' => 'English (South Africa)',
            'en_GB' => 'English (UK)',
            'eo' => 'Esperanto',
            'et' => 'Estonian',
            'fo' => 'Faroese',
            'fi' => 'Finnish',
            'fr_BE' => 'French (Belgium)',
            'fr_CA' => 'French (Canada)',
            'fr_FR' => 'French (France)',
            'fy' => 'Frisian',
            'fur' => 'Friulian',
            'fuc' => 'Fulah',
            'gl_ES' => 'Galician',
            'ka_GE' => 'Georgian',
            'de_DE' => 'German',
            'de_CH' => 'German (Switzerland)',
            'el' => 'Greek',
            'kal' => 'Greenlandic',
            'gn' => 'Guaraní',
            'gu' => 'Gujarati',
            'haw_US' => 'Hawaiian',
            'haz' => 'Hazaragi',
            'he_IL' => 'Hebrew',
            'hi_IN' => 'Hindi',
            'hu_HU' => 'Hungarian',
            'is_IS' => 'Icelandic',
            'ido' => 'Ido',
            'id_ID' => 'Indonesian',
            'ga' => 'Irish',
            'it_IT' => 'Italian',
            'ja' => 'Japanese',
            'jv_ID' => 'Javanese',
            'kab' => 'Kabyle',
            'kn' => 'Kannada',
            'kk' => 'Kazakh',
            'km' => 'Khmer',
            'kin' => 'Kinyarwanda',
            'ky_KY' => 'Kirghiz',
            'ko_KR' => 'Korean',
            'ckb' => 'Kurdish (Sorani)',
            'lo' => 'Lao',
            'lv' => 'Latvian',
            'li' => 'Limburgish',
            'lin' => 'Lingala',
            'lt_LT' => 'Lithuanian',
            'lb_LU' => 'Luxembourgish',
            'mk_MK' => 'Macedonian',
            'mg_MG' => 'Malagasy',
            'ms_MY' => 'Malay',
            'ml_IN' => 'Malayalam',
            'mri' => 'Maori',
            'mr' => 'Marathi',
            'xmf' => 'Mingrelian',
            'mn' => 'Mongolian',
            'me_ME' => 'Montenegrin',
            'ary' => 'Moroccan Arabic',
            'my_MM' => 'Myanmar (Burmese)',
            'ne_NP' => 'Nepali',
            'nb_NO' => 'Norwegian (Bokmål)',
            'nn_NO' => 'Norwegian (Nynorsk)',
            'oci' => 'Occitan',
            'ory' => 'Oriya',
            'os' => 'Ossetic',
            'ps' => 'Pashto',
            'fa_IR' => 'Persian',
            'fa_AF' => 'Persian (Afghanistan)',
            'pl_PL' => 'Polish',
            'pt_BR' => 'Portuguese (Brazil)',
            'pt_PT' => 'Portuguese (Portugal)',
            'pa_IN' => 'Punjabi',
            'rhg' => 'Rohingya',
            'ro_RO' => 'Romanian',
            'roh' => 'Romansh Vallader',
            'ru_RU' => 'Russian',
            'rue' => 'Rusyn',
            'sah' => 'Sakha',
            'sa_IN' => 'Sanskrit',
            'srd' => 'Sardinian',
            'gd' => 'Scottish Gaelic',
            'sr_RS' => 'Serbian',
            'szl' => 'Silesian',
            'snd' => 'Sindhi',
            'si_LK' => 'Sinhala',
            'sk_SK' => 'Slovak',
            'sl_SI' => 'Slovenian',
            'so_SO' => 'Somali',
            'azb' => 'South Azerbaijani',
            'es_AR' => 'Spanish (Argentina)',
            'es_CL' => 'Spanish (Chile)',
            'es_CO' => 'Spanish (Colombia)',
            'es_GT' => 'Spanish (Guatemala)',
            'es_MX' => 'Spanish (Mexico)',
            'es_PE' => 'Spanish (Peru)',
            'es_PR' => 'Spanish (Puerto Rico)',
            'es_ES' => 'Spanish (Spain)',
            'es_VE' => 'Spanish (Venezuela)',
            'su_ID' => 'Sundanese',
            'sw' => 'Swahili',
            'sv_SE' => 'Swedish',
            'gsw' => 'Swiss German',
            'tl' => 'Tagalog',
            'tah' => 'Tahitian',
            'tg' => 'Tajik',
            'tzm' => 'Tamazight (Central Atlas)',
            'ta_IN' => 'Tamil',
            'ta_LK' => 'Tamil (Sri Lanka)',
            'tt_RU' => 'Tatar',
            'te' => 'Telugu',
            'th' => 'Thai',
            'bo' => 'Tibetan',
            'tir' => 'Tigrinya',
            'tr_TR' => 'Turkish',
            'tuk' => 'Turkmen',
            'twd' => 'Tweants',
            'ug_CN' => 'Uighur',
            'uk' => 'Ukrainian',
            'ur' => 'Urdu',
            'uz_UZ' => 'Uzbek',
            'vi' => 'Vietnamese',
            'wa' => 'Walloon',
            'cy' => 'Welsh',
            'yor' => 'Yoruba',
        );
    }

    public static function install_and_set_language( string $locale = '' ): void {
        if ( $locale === 'en_US' ) {
            update_option( 'WPLANG', '' );
            return;
        }

        if ( class_exists( '\LiteSpeed\Conf' ) ){
            $conf = new \LiteSpeed\Conf();
            $conf->update_confs( array( 'object-admin' => 0, 'object-transients' => 0 ) );

            $purge = new \LiteSpeed\Purge();
            $purge::purge_all('Hostinger AI Theme Generation');
        }

        require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $available_languages = get_available_languages();
        if ( ! in_array( $locale, $available_languages, true ) ) {
            $language = wp_download_language_pack( $locale );

            if ( ! $language ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "Hostinger AI theme: Core language pack not installed: " . $locale );
                }
            }
        }

        $language_set = update_option( 'WPLANG', $locale );

        wp_cache_flush();

        delete_transient( 'update_core' );
        delete_transient( 'update_plugins' );
        delete_transient( 'update_themes' );

        delete_site_transient( 'update_core' );
        delete_site_transient( 'update_plugins' );
        delete_site_transient( 'update_themes' );

        if ( $language_set ) {
            unload_textdomain( 'default' );
            load_default_textdomain( $locale );
        }

        wp_clean_update_cache();
        wp_version_check();
        wp_update_plugins();
        wp_update_themes();

        $updates = wp_get_translation_updates();
        if ( ! empty( $updates ) ) {
            if ( ! class_exists( 'Language_Pack_Upgrader' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                require_once ABSPATH . 'wp-admin/includes/class-language-pack-upgrader.php';
            }

            if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
            }

            if ( ! class_exists( 'Automatic_Upgrader_Skin' ) || ! class_exists( 'Language_Pack_Upgrader' ) || ! class_exists( 'WP_Upgrader' ) ) {
                return;
            }

            $upgrader = new Language_Pack_Upgrader( new Automatic_Upgrader_Skin() );

            foreach ( $updates as $update ) {
                $result = $upgrader->upgrade( $update );

                if ( is_wp_error( $result ) ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log('Hostinger AI Theme: Translation install error: ' . $result->get_error_message());
                    }
                }
            }
        }
    }
}
