<?php
/**
 * Plugin Name: Region 9 Live Studio 17
 * Description: Region 9 Live Studio weather decision engine and automation plugin.
 * Version: 17.0.0-alpha.2
 * Author: Weather Support LLC
 * Text Domain: region9-live-studio
 */

defined('ABSPATH') || exit;

define('R9LS_VERSION', '17.0.0-alpha.2');
define('R9LS_PLUGIN_FILE', __FILE__);
define('R9LS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('R9LS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once R9LS_PLUGIN_DIR . 'includes/bootstrap.php';

Region9\LiveStudio\Bootstrap::boot();
