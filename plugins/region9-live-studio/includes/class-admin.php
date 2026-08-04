<?php
defined('ABSPATH') || exit;

class R9LS_Admin {
    private $scheduler; private $changes; private $audit;
    public function __construct($scheduler, $changes, $audit) { $this->scheduler = $scheduler; $this->changes = $changes; $this->audit = $audit; }
    public function hooks() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_post_r9ls_validate', array($this, 'validate'));
        add_action('admin_post_r9ls_settings', array($this, 'settings'));
        add_action('admin_post_r9ls_change', array($this, 'change_action'));
        add_action('admin_post_r9ls_override', array($this, 'override'));
    }
    public function menu() { add_menu_page('Region 9 Studio Automation', 'Region 9 Studio', 'manage_options', 'r9ls', array($this, 'page'), 'dashicons-cloud', 58); }
    public function page() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Administrator access required.', 'r9ls')); }
        $products = get_option(R9LS_Scheduler::CACHE, array()); $health = $this->scheduler->health(); $last = get_option(R9LS_Scheduler::LAST, array()); $queue = $this->changes->queue(); $settings = get_option(R9LS_Scheduler::SETTINGS, array());
        echo '<div class="wrap"><h1>Region 9 Studio Automation</h1>';
        echo '<h2>Operational Status</h2><table class="widefat"><tbody>';
        $rows = array('Source health' => 'REST sources evaluated during validation', 'Scheduler health' => ($health['status'] ?? 'unknown') . ' — ' . ($health['message'] ?? ''), 'Current Region 9 risk' => $this->current_risk($products), 'Forecast confidence' => $products['Forecast Confidence']['confidence'] ?? 'N/A', 'Last validation' => $last['time'] ?? 'Never', 'Next validation' => $this->scheduler->next_validation() ? date_i18n('Y-m-d H:i:s', $this->scheduler->next_validation()) : 'Not scheduled', 'Validation duration' => isset($last['duration']) ? $last['duration'] . 's' : 'N/A');
        foreach ($rows as $k => $v) { echo '<tr><th>' . esc_html($k) . '</th><td>' . esc_html((string) $v) . '</td></tr>'; }
        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="r9ls_validate">'; wp_nonce_field('r9ls_validate'); submit_button('Run Manual Validation'); echo '</form>';
        echo '<h2>Operational Product Cards</h2><div class="r9ls-cards">';
        foreach ($products as $name => $product) { echo '<div class="card"><h3>' . esc_html($name) . '</h3><p>' . esc_html($product['controlled_summary']) . '</p><p>Rating: ' . esc_html($product['rating']) . '</p></div>'; }
        echo '</div><h2>County Score Matrix</h2><table class="widefat"><tbody>';
        foreach (($products['Travel']['county_scores'] ?? array()) as $county => $score) { echo '<tr><th>' . esc_html($county) . '</th><td>' . esc_html((string) $score) . '</td></tr>'; }
        echo '</tbody></table><h2>Pending Material Changes</h2><table class="widefat"><tbody>';
        foreach ($queue as $id => $change) { echo '<tr><td>' . esc_html($change['product'] . ' ' . $change['field'] . ': ' . $change['reason']) . '</td><td>' . $this->button($id, 'approve') . $this->button($id, 'reject') . $this->button($id, 'publish') . $this->button($id, 'rollback') . '</td></tr>'; }
        echo '</tbody></table><h2>Temporary Editor Overrides</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="r9ls_override">'; wp_nonce_field('r9ls_override'); echo '<input name="summary" placeholder="Override summary"> <input type="datetime-local" name="expires">'; submit_button('Save Override'); echo '</form>';
        echo '<h2>Decision History</h2><pre>' . esc_html(wp_json_encode($this->changes->history(), JSON_PRETTY_PRINT)) . '</pre><h2>Audit Log</h2><pre>' . esc_html(wp_json_encode($this->audit->all(), JSON_PRETTY_PRINT)) . '</pre>';
        echo '<h2>Settings</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="r9ls_settings">'; wp_nonce_field('r9ls_settings');
        printf('<label>Active interval minutes <input name="active_interval_minutes" type="number" min="15" value="%d"></label><br>', absint($settings['active_interval_minutes'] ?? 60));
        printf('<label>Score movement threshold <input name="score_movement_threshold" type="number" min="1" value="%d"></label><br>', absint($settings['score_movement_threshold'] ?? 10));
        printf('<label>Confidence threshold <input name="confidence_threshold" type="number" min="0" max="100" value="%d"></label><br>', absint($settings['confidence_threshold'] ?? 60)); submit_button('Save Settings'); echo '</form></div>';
    }
    private function button($id, $do) { return '<form style="display:inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="r9ls_change"><input type="hidden" name="change_id" value="' . esc_attr($id) . '"><input type="hidden" name="do" value="' . esc_attr($do) . '">' . wp_nonce_field('r9ls_change', '_wpnonce', true, false) . '<button class="button">' . esc_html(ucfirst($do)) . '</button></form> '; }
    public function validate() { $this->guard('r9ls_validate'); $this->scheduler->manual_validate(); wp_safe_redirect(admin_url('admin.php?page=r9ls')); exit; }
    public function settings() { $this->guard('r9ls_settings'); update_option(R9LS_Scheduler::SETTINGS, array('active_interval_minutes' => max(15, absint($_POST['active_interval_minutes'] ?? 60)), 'score_movement_threshold' => absint($_POST['score_movement_threshold'] ?? 10), 'confidence_threshold' => min(100, absint($_POST['confidence_threshold'] ?? 60)), 'timing_tolerance_minutes' => 60, 'automatic_publishing' => 0), false); wp_safe_redirect(admin_url('admin.php?page=r9ls')); exit; }
    public function change_action() { $this->guard('r9ls_change'); $id = sanitize_text_field(wp_unslash($_POST['change_id'] ?? '')); $do = sanitize_key($_POST['do'] ?? ''); if ($do === 'approve' || $do === 'reject') { $this->changes->decide($id, $do); } elseif ($do === 'publish') { $this->changes->publish($id); } elseif ($do === 'rollback') { $this->changes->rollback($id); } wp_safe_redirect(admin_url('admin.php?page=r9ls')); exit; }
    public function override() { $this->guard('r9ls_override'); $overrides = get_option('r9ls_editor_overrides', array()); $overrides[md5(time() . wp_rand())] = array('summary' => sanitize_text_field(wp_unslash($_POST['summary'] ?? '')), 'expires' => sanitize_text_field(wp_unslash($_POST['expires'] ?? ''))); update_option('r9ls_editor_overrides', $overrides, false); wp_safe_redirect(admin_url('admin.php?page=r9ls')); exit; }
    private function guard($nonce) { if (!current_user_can('manage_options')) { wp_die('Administrator capability required.'); } check_admin_referer($nonce); }
    private function current_risk($products) { return $products['Severe Weather Risk']['rating'] ?? 'None'; }
}
