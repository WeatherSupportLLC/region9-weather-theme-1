<?php
/**
 * Plugin Name: Region 9 Live Studio
 * Description: Automated Region 9 weather operations engine (RC1).
 * Version: 17.0.0-rc.1
 * Author: Weather Support LLC
 */

defined('ABSPATH') || exit;

define('R9LS_VERSION', '17.0.0-rc.1');
define('R9LS_FILE', __FILE__);
define('R9LS_DIR', plugin_dir_path(__FILE__));
define('R9LS_URL', plugin_dir_url(__FILE__));

require_once R9LS_DIR . 'includes/class-audit-log.php';
require_once R9LS_DIR . 'includes/class-gis-engine.php';
require_once R9LS_DIR . 'includes/class-rule-engine.php';
require_once R9LS_DIR . 'includes/class-national-guidance.php';
require_once R9LS_DIR . 'includes/class-alert-feed.php';
require_once R9LS_DIR . 'includes/class-material-change-engine.php';
require_once R9LS_DIR . 'includes/class-timing-engine.php';
require_once R9LS_DIR . 'includes/class-product-catalog.php';
require_once R9LS_DIR . 'includes/class-product-generator.php';
require_once R9LS_DIR . 'includes/class-graphic-renderer.php';
require_once R9LS_DIR . 'includes/class-publication-policy.php';
require_once R9LS_DIR . 'includes/class-social-publisher.php';
require_once R9LS_DIR . 'includes/class-public-hub.php';
require_once R9LS_DIR . 'includes/class-rest-api.php';
require_once R9LS_DIR . 'includes/class-scheduler.php';
require_once R9LS_DIR . 'includes/class-admin.php';
require_once R9LS_DIR . 'includes/class-automation-admin.php';

final class R9LS_Plugin {
    private static $instance;
    public $audit;
    public $gis;
    public $rules;
    public $changes;
    public $guidance;
    public $alerts;
    public $scheduler;
    public $admin;
    public $automation_admin;
    public $timing;
    public $products;
    public $graphics;
    public $publication_policy;
    public $social;
    public $public_hub;
    public $rest;

    public static function instance() {
        if (!self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        $this->audit = new R9LS_Audit_Log();
        $this->gis = new R9LS_GIS_Engine(R9LS_DIR . 'data/region9-counties.geojson');
        $this->rules = new R9LS_Rule_Engine($this->gis, $this->audit);
        $this->changes = new R9LS_Material_Change_Engine($this->audit);
        $this->guidance = new R9LS_National_Guidance($this->gis, $this->audit);
        $this->alerts = new R9LS_Alert_Feed($this->gis, $this->audit);
        $this->scheduler = new R9LS_Scheduler($this->audit, $this->rules, $this->changes, $this->guidance);
        $this->timing = new R9LS_Timing_Engine();
        $this->products = new R9LS_Product_Generator($this->rules, $this->changes, $this->audit, $this->timing);
        $this->graphics = new R9LS_Graphic_Renderer($this->audit);
        $this->publication_policy = new R9LS_Publication_Policy($this->products, $this->audit);
        $this->social = new R9LS_Social_Publisher($this->audit);
        $this->public_hub = new R9LS_Public_Hub($this->gis, $this->alerts);
        $this->rest = new R9LS_REST_API($this->products);
        $this->admin = new R9LS_Admin($this->scheduler, $this->changes, $this->audit);
        $this->automation_admin = new R9LS_Automation_Admin($this->audit);
    }

    public function boot() {
        $this->alerts->hooks();
        $this->scheduler->hooks();
        $this->publication_policy->hooks();
        $this->graphics->hooks();
        $this->social->hooks();
        $this->public_hub->hooks();
        $this->admin->hooks();
        $this->automation_admin->hooks();
        $this->rest->hooks();
    }

    public static function activate() {
        self::instance()->scheduler->activate();
        self::instance()->social->ensure_schedule();
        self::instance()->alerts->refresh(true);
    }

    public static function deactivate() {
        self::instance()->scheduler->deactivate();
        wp_clear_scheduled_hook('r9ls_dispatch_social_outbox');
    }
}

add_action('plugins_loaded', array(R9LS_Plugin::instance(), 'boot'));
register_activation_hook(__FILE__, array('R9LS_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('R9LS_Plugin', 'deactivate'));
