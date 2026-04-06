<?php
namespace Hostinger\AiTheme;

use Hostinger\AiTheme\Admin\Assets as AdminAssets;
use Hostinger\AiTheme\Admin\Hooks as AdminHooks;
use Hostinger\AiTheme\Admin\Menu as AdminMenu;
use Hostinger\AiTheme\Builder\AffiliateBuilder;
use Hostinger\AiTheme\Compatibility\LiteSpeedCache;
use Hostinger\AiTheme\Builder\ElementorBuilder;
use Hostinger\AiTheme\Builder\Fonts;
use Hostinger\AiTheme\Builder\HostingerReachBuilder;
use Hostinger\AiTheme\Builder\ImageManager;
use Hostinger\AiTheme\Builder\RequestClient;
use Hostinger\AiTheme\Builder\Seo;
use Hostinger\AiTheme\Builder\WebsiteBuilder;
use Hostinger\AiTheme\Builder\WooBuilder;
use Hostinger\AiTheme\Builder\Elementor\PostBuildCustomizations;
use Hostinger\AiTheme\Elementor\WidgetManager;
use Hostinger\AiTheme\Elementor\ScrollAnimation;
use Hostinger\AiTheme\Rest\BlockTypeRoutes;
use Hostinger\AiTheme\Rest\BuilderRoutes;
use Hostinger\AiTheme\Rest\LogoRoutes;
use Hostinger\AiTheme\Rest\Routes;
use Hostinger\AiTheme\Settings\Theme as ThemeSettings;
use Hostinger\AiTheme\Shortcodes\ShortcodesManager;
use Hostinger\AiTheme\Admin\Surveys\RateAiSite;
use Hostinger\AiTheme\Admin\Surveys\WebsiteBuilderExperience;
use Hostinger\Amplitude\AmplitudeLoader;
use Hostinger\Surveys\Loader;
use Hostinger\Surveys\Rest as SurveysRest;
use Hostinger\Surveys\SurveyManager;
use Hostinger\WpHelper\Config;
use Hostinger\WpHelper\Constants;
use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\WpMenuManager\Manager;

defined( 'ABSPATH' ) || exit;

class Boot {
    public function run(): void {
        $this->load_hostinger_packages();
        $this->load_dependencies();
        $this->set_locale();
    }

    /**
     * @return void
     */
    public function hostinger_load_menus(): void {
        $manager = Manager::getInstance();
        $manager->boot();
    }

    /**
     * @return void
     */
    public function hostinger_load_amplitude(): void {
        $amplitude = AmplitudeLoader::getInstance();
        $amplitude->boot();
    }

    public function hostinger_add_surveys(): void {
        $surveys = Loader::getInstance();
        $surveys->boot();
    }

    /**
     * @return void
     */
    private function load_hostinger_packages(): void {
        if ( ! has_action( 'plugins_loaded', 'hostinger_load_menus' ) ) {
            add_action( 'after_setup_theme', array( $this, 'hostinger_load_menus' ) );
        }

        if ( ! has_action( 'plugins_loaded', 'hostinger_load_amplitude' ) ) {
            add_action( 'after_setup_theme', array( $this, 'hostinger_load_amplitude' ) );
        }

        if ( ! has_action( 'plugins_loaded', 'hostinger_add_surveys' ) ) {
            add_action( 'after_setup_theme', array( $this, 'hostinger_add_surveys' ) );
        }
    }

    private function load_dependencies(): void {
        $assets          = new Assets();
        $theme_settings  = new ThemeSettings();
        $updates         = new Updates();
        $updates->updates();

        $gutenbergBlocks = new GutenbergBlocks();
        $litespeedCache = new LiteSpeedCache();

        if ( did_action( 'elementor/loaded' ) ) {
            $elementorWidgets = new WidgetManager();
            $elementorScrollAnimation = new ScrollAnimation();
            $elementorPostBuildCustomizations = new PostBuildCustomizations();
        }

        $shortcodes = new ShortcodesManager();

        $helper         = new Helper();
        $config_handler = new Config();
        $default_headers = [
            Config::TOKEN_HEADER  => $helper::getApiToken(),
            Config::DOMAIN_HEADER => $helper->getHostInfo(),
            'Content-Type' => 'application/json'
        ];
        $client         = new Client( $config_handler->getConfigValue( 'base_rest_uri', HOSTINGER_AI_WEBSITES_REST_URI ), $default_headers );

        $request_client = new RequestClient( $client );
        $image_manager = new ImageManager();
        $affiliate_builder = new AffiliateBuilder();
        $elementor_builder = new ElementorBuilder();
        $woo_builder = new WooBuilder( $image_manager );
        $hostinger_reach_builder = new HostingerReachBuilder();
        $fonts = new Fonts();
        $wh_api_client = new RequestClient( new Client( $config_handler->getConfigValue( 'base_proxy_rest_uri', HOSTINGER_WP_PROXY_API_URI ), $default_headers ) );

        $website_builder = new WebsiteBuilder( $request_client, $image_manager, $affiliate_builder, $elementor_builder, $woo_builder, $hostinger_reach_builder, $fonts, $wh_api_client );
        $website_builder->init();

        $builder_routes = new BuilderRoutes( $website_builder );

        $block_type_routes = new BlockTypeRoutes();
        $logo_routes = new LogoRoutes();
        $routes = new Routes( $builder_routes, $block_type_routes, $logo_routes );
        $routes->init();

        $utils = new Helper();
        $redirects = new Redirects( $utils );
        $seo = new Seo();
        $hooks = new Hooks( $image_manager );

        if ( is_admin() ) {
            $this->load_admin_dependencies();
        }
    }

    private function set_locale() {
        $plugin_i18n = new I18n();
    }


    private function load_admin_dependencies(): void
    {
        $image_manager = new ImageManager();
        $helper        = new Helper();

        $config = new Config();
        $client = new Client(
            $config->getConfigValue( 'base_rest_uri', Constants::HOSTINGER_REST_URI ),
            [
                Config::TOKEN_HEADER  => $helper->getApiToken(),
                Config::DOMAIN_HEADER => $helper->getHostInfo(),
            ]
        );

        $rateAiSite = null;

        if ( class_exists( SurveyManager::class ) ) {
            $surveysRest   = new SurveysRest( $client );
            $surveyManager = new SurveyManager( $helper, $config, $surveysRest );

            $surveys = new WebsiteBuilderExperience( $surveyManager );
            $surveys->init();

            $rateAiSite = new RateAiSite( $surveyManager );
        }

        new AdminAssets( $helper, $rateAiSite );
        new AdminMenu();
        new AdminHooks( $image_manager );
    }
}
