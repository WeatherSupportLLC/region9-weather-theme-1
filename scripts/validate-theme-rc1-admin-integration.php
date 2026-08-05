<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__); }
$actions = array(); $menus = array(); $removed = array(); $submenus = array(); $redirect = '';
function add_action($hook, $callback, $priority = 10) { $GLOBALS['actions'][$hook][] = $callback; }
function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null) { $GLOBALS['menus'][$menu_slug] = compact('page_title','menu_title','capability','menu_slug','callback','icon_url','position'); }
function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '') { $GLOBALS['submenus'][] = compact('parent_slug','page_title','menu_title','capability','menu_slug','callback'); }
function remove_menu_page($menu_slug) { $GLOBALS['removed'][] = $menu_slug; unset($GLOBALS['menus'][$menu_slug]); }
function remove_submenu_page($parent_slug, $menu_slug) { $GLOBALS['removed'][] = $parent_slug . ':' . $menu_slug; }
function current_user_can($capability) { return $capability === 'manage_options'; }
function admin_url($path = '') { return 'admin.php/' . ltrim($path, '/'); }
function wp_safe_redirect($url) { $GLOBALS['redirect'] = $url; throw new RuntimeException('redirect'); }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function esc_url($v) { return esc_attr($v); }
function wp_die($message = '') { throw new RuntimeException((string) $message); }
function get_bloginfo($key) { return '6.6-test'; }
function wp_date($format) { return date($format); }
function is_ssl() { return true; }
function r9_status_endpoint() { return new class { public function get_data() { return array('services'=>array()); } }; }
function get_theme_mods() { return array(); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function current_time($type = 'mysql') { return date('c'); }
function wp_nonce_url($url, $action) { return $url . '&_wpnonce=test'; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function check_admin_referer($action) { return true; }
function wp_verify_nonce($nonce, $action) { return true; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function set_theme_mod($name, $value) {}
function get_stylesheet_directory() { return dirname(__DIR__); }
function shortcode_atts($pairs, $atts) { return array_merge($pairs, (array) $atts); }
function add_shortcode($tag, $callback) {}
function wp_kses_post($value) { return (string) $value; }
function wpautop($value) { return '<p>' . $value . '</p>'; }
function sanitize_html_class($value) { return sanitize_key($value); }
function r9_media_placeholder($title = 'Forecast Graphic', $key = '') { return '<div></div>'; }
function r9_setting($key, $default = '') { return $default; }
define('HOUR_IN_SECONDS', 3600); define('MINUTE_IN_SECONDS', 60); define('R9LS_VERSION', '17.1.0');
require dirname(__DIR__) . '/inc/live-studio-integration.php';
require dirname(__DIR__) . '/inc/admin-studio.php';
foreach ($GLOBALS['actions']['admin_menu'] as $callback) { $callback(); }
function assert_true($name, $condition) { if (!$condition) { fwrite(STDERR, "FAIL: $name\n"); exit(1); } echo "PASS: $name\n"; }
assert_true('17.1 plugin detection', r9ls_theme_rc1_active());
assert_true('legacy top-level dashboard not registered', empty($GLOBALS['menus']['r9-studio']));
assert_true('legacy menu removal attempted', in_array('r9-studio', $GLOBALS['removed'], true));
$parents = array_column($GLOBALS['submenus'], 'parent_slug');
assert_true('active plugin exclusively owns support submenus', !in_array('r9ls', $parents, true) && !in_array('r9-studio', $parents, true));
try { r9_studio_admin(); } catch (Throwable $e) {}
assert_true('legacy dashboard redirects to Live Studio admin', strpos($GLOBALS['redirect'], 'page=r9ls') !== false);
echo "Theme 17.1 admin integration validation complete.\n";
