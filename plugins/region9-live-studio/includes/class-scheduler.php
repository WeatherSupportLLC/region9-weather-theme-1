<?php
defined('ABSPATH') || exit;

class R9LS_Scheduler {
    const HOOK = 'r9ls_validate_weather_operations';
    const PRODUCTION_HOOK = 'r9ls_six_hour_production';
    const SETTINGS = 'r9ls_settings';
    const HEALTH = 'r9ls_scheduler_health';
    const LOCK = 'r9ls_validation_lock';
    const LAST = 'r9ls_last_validation';
    const CACHE = 'r9ls_current_products';
    private $audit;
    private $rules;
    private $changes;
    private $guidance;

    public function __construct($audit, $rules, $changes, $guidance = null) {
        $this->audit = $audit;
        $this->rules = $rules;
        $this->changes = $changes;
        $this->guidance = $guidance;
    }

    public function hooks() {
        add_filter('cron_schedules', array($this, 'cron_schedules'));
        add_action(self::HOOK, array($this, 'scheduled_validate'));
        add_action(self::PRODUCTION_HOOK, array($this, 'scheduled_production'));
    }

    public function cron_schedules($schedules) {
        $minutes = $this->active_interval_minutes();
        $schedules['r9ls_active_weather'] = array('interval' => $minutes * MINUTE_IN_SECONDS, 'display' => 'Region 9 active-weather change check');
        $schedules['r9ls_six_hours'] = array('interval' => 6 * HOUR_IN_SECONDS, 'display' => 'Region 9 six-hour production cycle');
        $schedules['r9ls_hourly'] = array('interval' => HOUR_IN_SECONDS, 'display' => 'Region 9 hourly validation');
        return $schedules;
    }

    public function activate() {
        $this->ensure_defaults();
        $this->schedule_event();
        $this->schedule_production_event();
    }

    public function deactivate() {
        wp_clear_scheduled_hook(self::HOOK);
        wp_clear_scheduled_hook(self::PRODUCTION_HOOK);
        delete_transient(self::LOCK);
        $this->set_health('inactive', 'Scheduler deactivated.');
    }

    public function ensure_defaults() {
        $settings = get_option(self::SETTINGS, array());
        $settings = wp_parse_args($settings, array(
            'active_interval_minutes' => 60,
            'production_interval_hours' => 6,
            'score_movement_threshold' => 10,
            'confidence_threshold' => 60,
            'timing_tolerance_minutes' => 60,
            'automatic_publishing' => 0,
            'national_guidance_timeout' => 12,
            'national_guidance_user_agent' => 'Region9LiveStudio/17 (WeatherSupportLLC; WordPress wp_remote_get)',
        ));
        update_option(self::SETTINGS, $settings, false);
    }

    public function active_interval_minutes() {
        $settings = get_option(self::SETTINGS, array());
        $minutes = isset($settings['active_interval_minutes']) ? absint($settings['active_interval_minutes']) : 60;
        return max(15, $minutes);
    }

    public function schedule_event() {
        if (wp_next_scheduled(self::HOOK)) {
            $this->set_health('scheduled', 'Existing active-weather scheduler event retained.');
            return false;
        }
        $ok = wp_schedule_event(time() + 60, 'r9ls_active_weather', self::HOOK);
        if (!$ok) {
            $this->audit->write('error', 'Scheduler failed to schedule active-weather validation event.');
            $this->set_health('failure', 'Failed to schedule active-weather validation event.');
            return false;
        }
        $this->set_health('scheduled', 'Active-weather validation event scheduled.');
        return true;
    }

    public function schedule_production_event() {
        if (wp_next_scheduled(self::PRODUCTION_HOOK)) { return false; }
        $ok = wp_schedule_event(time() + 120, 'r9ls_six_hours', self::PRODUCTION_HOOK);
        if (!$ok) {
            $this->audit->write('error', 'Scheduler failed to schedule six-hour production event.');
            $this->set_health('failure', 'Failed to schedule six-hour production event.');
            return false;
        }
        return true;
    }

    public function scheduled_validate() { return $this->validate('scheduled-change-check'); }
    public function scheduled_production() { return $this->validate('six-hour-production'); }
    public function manual_validate() { return $this->validate('manual'); }

    public function validate($mode = 'manual') {
        $started = microtime(true);
        if ($this->locked()) {
            $this->audit->write('warning', 'Validation skipped because a validation lock is active.', array('mode' => $mode));
            return array('status' => 'locked');
        }
        set_transient(self::LOCK, time(), 20 * MINUTE_IN_SECONDS);
        try {
            $products = $this->rules->evaluate_all($this->load_sources());
            $previous = get_option(self::CACHE, array());
            $changes = $this->changes->detect($previous, $products);
            update_option(self::CACHE, $products, false);

            $full_production = in_array($mode, array('manual', 'six-hour-production'), true);
            $material_change = !empty($changes);
            if (class_exists('R9LS_Product_Generator') && ($full_production || $material_change)) {
                $generator = new R9LS_Product_Generator($this->rules, $this->changes, $this->audit);
                $reason = $full_production ? $mode : 'material-change';
                $generator->refresh_workspace_from_decision($products, $changes, $reason, 'validation-' . gmdate('YmdHis'));
            }

            $duration = round(microtime(true) - $started, 3);
            $last = array(
                'time' => current_time('mysql'),
                'mode' => sanitize_key($mode),
                'duration' => $duration,
                'changes' => count($changes),
                'production_triggered' => ($full_production || $material_change) ? 1 : 0,
                'production_reason' => $full_production ? sanitize_key($mode) : ($material_change ? 'material-change' : 'none'),
            );
            update_option(self::LAST, $last, false);
            $this->set_health('healthy', 'Last validation completed.', $last);
            $this->audit->write('info', 'Validation completed.', $last);
            do_action('r9ls_validation_complete', $products, $changes, $mode);
            return array('status' => 'ok', 'products' => $products, 'changes' => $changes, 'duration' => $duration, 'production_triggered' => (bool) $last['production_triggered']);
        } catch (Exception $e) {
            $this->audit->write('error', 'Validation failed.', array('error' => $e->getMessage()));
            $this->set_health('failure', 'Validation failed: ' . $e->getMessage());
            return array('status' => 'error', 'message' => $e->getMessage());
        } finally {
            delete_transient(self::LOCK);
        }
    }

    public function locked() {
        $locked_at = get_transient(self::LOCK);
        if (!$locked_at) { return false; }
        if ((time() - absint($locked_at)) > 20 * MINUTE_IN_SECONDS) {
            delete_transient(self::LOCK);
            $this->audit->write('warning', 'Expired validation lock was cleared.');
            return false;
        }
        return true;
    }

    public function health() {
        $health = get_option(self::HEALTH, array('status' => 'unknown', 'message' => 'Scheduler not initialized.'));
        $health['next_change_check'] = wp_next_scheduled(self::HOOK) ?: 0;
        $health['next_production'] = wp_next_scheduled(self::PRODUCTION_HOOK) ?: 0;
        return $health;
    }

    public function next_validation() {
        $next = wp_next_scheduled(self::HOOK);
        if ($next) { return $next; }
        $this->ensure_defaults();
        $this->schedule_event();
        $this->schedule_production_event();
        return wp_next_scheduled(self::HOOK);
    }

    public function load_sources() {
        $sources = $this->guidance ? $this->guidance->collect_all() : array(
            'spc_day1' => array('status' => 'healthy', 'hazards' => array()),
            'wpc_day1_ero' => array('status' => 'healthy', 'hazards' => array()),
            'wpc_day1_qpf' => array('status' => 'healthy', 'county_precipitation' => array()),
            'nws_alerts' => array('status' => 'healthy', 'hazards' => array()),
            'nws_points_grid_hourly' => array('status' => 'healthy', 'forecast_periods' => array(), 'hourly_periods' => array()),
        );
        $sources['nws_alerts'] = $sources['nws_alerts'] ?? array('status' => 'healthy', 'hazards' => array());
        $sources['nws_points_grid_hourly'] = $sources['nws_points_grid_hourly'] ?? array('status' => 'healthy', 'forecast_periods' => array(), 'hourly_periods' => array());
        return apply_filters('r9ls_weather_sources', $sources);
    }

    private function set_health($status, $message, $extra = array()) {
        update_option(self::HEALTH, array_merge(array('status' => $status, 'message' => $message, 'updated' => current_time('mysql')), $extra), false);
    }
}
