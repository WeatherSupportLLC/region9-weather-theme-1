<?php
defined('ABSPATH') || exit;

final class R9LS_Social_Publisher {
    const SETTINGS = 'r9ls_social_publishing_settings';
    const OUTBOX = 'r9ls_social_outbox';
    const HISTORY = 'r9ls_social_history';
    const LOCK = 'r9ls_social_dispatch_lock';

    private $audit;

    public function __construct($audit = null) { $this->audit = $audit; }

    public function hooks() {
        add_action('r9ls_products_published', array($this, 'products_published'), 10, 3);
        add_action('r9ls_dispatch_social_outbox', array($this, 'dispatch'));
        add_filter('cron_schedules', array($this, 'cron_schedules'));
        add_action('init', array($this, 'ensure_schedule'));
    }

    public function cron_schedules($schedules) {
        $schedules['r9ls_five_minutes'] = array('interval'=>5 * MINUTE_IN_SECONDS,'display'=>'Region 9 social outbox');
        return $schedules;
    }

    public function ensure_schedule() {
        if (!wp_next_scheduled('r9ls_dispatch_social_outbox')) {
            wp_schedule_event(time() + 300, 'r9ls_five_minutes', 'r9ls_dispatch_social_outbox');
        }
    }

    public static function defaults() {
        return array(
            'mode' => 'manual', // manual|routine|weather-aware|emergency
            'minimum_risk_level' => 0,
            'post_on_six_hour_cycle' => 0,
            'post_on_material_change' => 1,
            'post_emergency_alerts' => 1,
            'require_review_elevated' => 1,
            'require_review_significant' => 1,
            'site_url' => home_url('/'),
            'channels' => array(),
            'allowed_products' => array_keys(class_exists('R9LS_Product_Catalog') ? R9LS_Product_Catalog::definitions() : array()),
        );
    }

    public function settings() { return wp_parse_args(get_option(self::SETTINGS, array()), self::defaults()); }

    public function products_published($products, $changed_ids, $context = array()) {
        $settings = $this->settings();
        if (($settings['mode'] ?? 'manual') === 'manual') { return; }
        $reason = sanitize_key($context['reason'] ?? 'publication');
        foreach ((array)$changed_ids as $id) {
            if (!isset($products[$id])) { continue; }
            $product = $products[$id];
            if (!$this->eligible($product, $reason, $settings)) { continue; }
            $this->enqueue($product, $reason, $settings);
        }
        $this->dispatch();
    }

    private function eligible($product, $reason, $settings) {
        $id = sanitize_key($product['product_id'] ?? '');
        if (!$id || !in_array($id, (array)($settings['allowed_products'] ?? array()), true)) { return false; }
        if (($product['publication_state'] ?? '') !== 'published' || ($product['approval_state'] ?? '') !== 'approved') { return false; }
        $level = (int)($product['risk']['level'] ?? 0);
        if ($level < (int)($settings['minimum_risk_level'] ?? 0)) { return false; }
        if ($reason === 'six-hour-production' && empty($settings['post_on_six_hour_cycle'])) { return false; }
        if ($reason === 'material-change' && empty($settings['post_on_material_change'])) { return false; }
        if ($level >= 4 && !empty($settings['require_review_significant']) && empty($product['social_approved'])) { return false; }
        if ($level === 3 && !empty($settings['require_review_elevated']) && empty($product['social_approved'])) { return false; }
        return true;
    }

    private function enqueue($product, $reason, $settings) {
        $outbox = get_option(self::OUTBOX, array());
        $channels = array_filter((array)($settings['channels'] ?? array()), function($c){ return !empty($c['enabled']); });
        foreach ($channels as $key => $channel) {
            $fingerprint = hash('sha256', ($product['content_hash'] ?? '') . '|' . sanitize_key($key) . '|' . $reason);
            if ($this->already_sent($fingerprint) || isset($outbox[$fingerprint])) { continue; }
            $outbox[$fingerprint] = array(
                'fingerprint'=>$fingerprint,
                'channel'=>sanitize_key($key),
                'provider'=>sanitize_key($channel['provider'] ?? 'webhook'),
                'product_id'=>sanitize_key($product['product_id'] ?? ''),
                'reason'=>$reason,
                'payload'=>$this->public_payload($product, $reason, $settings),
                'attempts'=>0,
                'next_attempt'=>time(),
                'created'=>current_time('mysql'),
            );
        }
        update_option(self::OUTBOX, $outbox, false);
    }

    private function public_payload($product, $reason, $settings) {
        $risk = sanitize_text_field($product['risk']['label'] ?? 'None');
        $title = sanitize_text_field($product['title'] ?? 'Region 9 Weather Update');
        $summary = sanitize_text_field($product['summary'] ?? '');
        $url = trailingslashit($settings['site_url'] ?? home_url('/'));
        $text = trim($title . ' — ' . $risk . ' risk. ' . $summary . ' ' . $url);
        return array(
            'product_id'=>sanitize_key($product['product_id'] ?? ''),
            'title'=>$title,
            'text'=>wp_strip_all_tags($text),
            'summary'=>$summary,
            'risk'=>$risk,
            'risk_level'=>(int)($product['risk']['level'] ?? 0),
            'affected_counties'=>array_values(array_map('sanitize_text_field', (array)($product['affected_counties'] ?? array()))),
            'timing'=>$product['timing'] ?? array(),
            'updated_at'=>sanitize_text_field($product['updated_at'] ?? ''),
            'reason'=>$reason,
            'url'=>esc_url_raw($url),
            'image_url'=>esc_url_raw($product['image_url'] ?? ''),
        );
    }

    public function dispatch() {
        if (get_transient(self::LOCK)) { return; }
        set_transient(self::LOCK, 1, 4 * MINUTE_IN_SECONDS);
        try {
            $settings = $this->settings();
            $outbox = get_option(self::OUTBOX, array());
            foreach ($outbox as $fingerprint => $item) {
                if ((int)($item['next_attempt'] ?? 0) > time()) { continue; }
                $channel = $settings['channels'][$item['channel']] ?? array();
                if (empty($channel['enabled'])) { unset($outbox[$fingerprint]); continue; }
                $result = $this->send($item, $channel);
                if (!is_wp_error($result)) {
                    $this->record($item, 'sent', ''); unset($outbox[$fingerprint]); continue;
                }
                $item['attempts'] = (int)($item['attempts'] ?? 0) + 1;
                if ($item['attempts'] >= 5) {
                    $this->record($item, 'failed', $result->get_error_message()); unset($outbox[$fingerprint]);
                } else {
                    $item['next_attempt'] = time() + min(HOUR_IN_SECONDS, (5 * MINUTE_IN_SECONDS) * (2 ** ($item['attempts'] - 1)));
                    $outbox[$fingerprint] = $item;
                }
            }
            update_option(self::OUTBOX, $outbox, false);
        } finally { delete_transient(self::LOCK); }
    }

    private function send($item, $channel) {
        $provider = sanitize_key($channel['provider'] ?? 'webhook');
        if ($provider !== 'webhook') {
            $custom = apply_filters('r9ls_social_provider_send', null, $provider, $item, $channel);
            if ($custom !== null) { return $custom; }
            return new WP_Error('r9ls_social_provider_missing', 'No enabled adapter is registered for this social provider.');
        }
        $url = esc_url_raw($channel['webhook_url'] ?? '');
        if (!$url || !wp_http_validate_url($url)) { return new WP_Error('r9ls_social_webhook_invalid', 'Social webhook URL is invalid.'); }
        $json = wp_json_encode($item['payload']);
        $headers = array('Content-Type'=>'application/json','User-Agent'=>'Region9LiveStudio/' . (defined('R9LS_VERSION') ? R9LS_VERSION : 'unknown'));
        $secret = (string)($channel['secret'] ?? '');
        if ($secret !== '') { $headers['X-Region9-Signature'] = 'sha256=' . hash_hmac('sha256', $json, $secret); }
        $response = wp_remote_post($url, array('timeout'=>10,'redirection'=>2,'headers'=>$headers,'body'=>$json));
        if (is_wp_error($response)) { return $response; }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) { return new WP_Error('r9ls_social_http', 'Social webhook returned HTTP ' . $code . '.'); }
        return true;
    }

    private function already_sent($fingerprint) {
        foreach ((array)get_option(self::HISTORY, array()) as $row) { if (($row['fingerprint'] ?? '') === $fingerprint && ($row['status'] ?? '') === 'sent') { return true; } }
        return false;
    }

    private function record($item, $status, $error) {
        $history = get_option(self::HISTORY, array());
        array_unshift($history, array(
            'fingerprint'=>$item['fingerprint'], 'channel'=>$item['channel'], 'provider'=>$item['provider'],
            'product_id'=>$item['product_id'], 'reason'=>$item['reason'], 'status'=>$status,
            'error'=>sanitize_text_field($error), 'time'=>current_time('mysql')
        ));
        update_option(self::HISTORY, array_slice($history, 0, 1000), false);
        if ($this->audit) { $this->audit->write($status === 'sent' ? 'info' : 'warning', 'Social publication ' . $status . '.', array('product'=>$item['product_id'],'channel'=>$item['channel'],'provider'=>$item['provider'],'error'=>$error)); }
    }
}
