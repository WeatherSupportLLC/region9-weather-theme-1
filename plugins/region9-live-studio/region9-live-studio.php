<?php
/**
 * Plugin Name: Region 9 Live Studio
 * Description: Automated Region 9 weather operations engine (Alpha 4).
 * Version: 17.0.0-alpha.4
 * Author: Weather Support LLC
 */

defined('ABSPATH') || exit;

define('R9LS_VERSION', '17.0.0-alpha.4');
define('R9LS_FILE', __FILE__);
define('R9LS_DIR', plugin_dir_path(__FILE__));
define('R9LS_URL', plugin_dir_url(__FILE__));

require_once R9LS_DIR . 'includes/class-audit-log.php';
require_once R9LS_DIR . 'includes/class-gis-engine.php';
require_once R9LS_DIR . 'includes/class-rule-engine.php';
require_once R9LS_DIR . 'includes/class-material-change-engine.php';
require_once R9LS_DIR . 'includes/class-scheduler.php';
require_once R9LS_DIR . 'includes/class-admin.php';

final class R9LS_Plugin {
    private static $instance;
    public $audit;
    public $gis;
    public $rules;
    public $changes;
    public $scheduler;
    public $admin;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->audit = new R9LS_Audit_Log();
        $this->gis = new R9LS_GIS_Engine(R9LS_DIR . 'data/region9-counties.geojson');
        $this->rules = new R9LS_Rule_Engine($this->gis, $this->audit);
        $this->changes = new R9LS_Material_Change_Engine($this->audit);
        $this->scheduler = new R9LS_Scheduler($this->audit, $this->rules, $this->changes);
        $this->admin = new R9LS_Admin($this->scheduler, $this->changes, $this->audit);
    }

    public function boot() {
        $this->scheduler->hooks();
        $this->admin->hooks();
    }

    public static function activate() {
        self::instance()->scheduler->activate();
    }

    public static function deactivate() {
        self::instance()->scheduler->deactivate();
    }
}

add_action('plugins_loaded', array(R9LS_Plugin::instance(), 'boot'));
register_activation_hook(__FILE__, array('R9LS_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('R9LS_Plugin', 'deactivate'));
