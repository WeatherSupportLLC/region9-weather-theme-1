<?php
defined('ABSPATH') || exit;

final class R9LS_Alert_Feed {
    const LIVE_OPTION = 'r9ls_live_region9_alerts';
    const CRAWL_OPTION = 'r9ls_crawl_50mi_alerts';
    const MAP_OPTION = 'r9ls_public_map_alerts';
    const HEALTH_OPTION = 'r9ls_alert_scope_health';
    const CACHE = 'r9ls_alert_scope_cache';
    const URL = 'https://api.weather.gov/alerts/active?area=IL';
    const BUFFER_MILES = 50.0;

    private $gis;
    private $audit;

    public function __construct($gis, $audit = null) { $this->gis = $gis; $this->audit = $audit; }

    public function hooks() {
        add_filter('r9ls_weather_sources', array($this, 'refresh_from_validation'), 20);
    }

    public function refresh_from_validation($sources) {
        $this->refresh();
        return $sources;
    }

    public function refresh($force = false) {
        if (!$force) {
            $cached = get_transient(self::CACHE);
            if (is_array($cached)) { return $cached; }
        }
        $response = wp_remote_get(self::URL, array(
            'timeout'=>12,
            'redirection'=>2,
            'headers'=>array('Accept'=>'application/geo+json','User-Agent'=>'Region9Weather/17 (WeatherSupportLLC; weather@region9weather.com)'),
        ));
        if (is_wp_error($response)) { return $this->failure($response->get_error_message()); }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if ($code < 200 || $code >= 300 || !is_array($json) || ($json['type'] ?? '') !== 'FeatureCollection') {
            return $this->failure('NWS active alerts returned an invalid response.');
        }

        $live = array(); $crawl = array(); $map = array(); $seen = array();
        foreach ((array)($json['features'] ?? array()) as $feature) {
            $props = (array)($feature['properties'] ?? array());
            $id = sanitize_text_field((string)($props['id'] ?? $feature['id'] ?? ''));
            if (!$id || isset($seen[$id])) { continue; }
            $seen[$id] = true;
            $ends = (string)($props['ends'] ?? $props['expires'] ?? '');
            if ($ends && strtotime($ends) && strtotime($ends) < time()) { continue; }
            if (stripos((string)($props['messageType'] ?? ''), 'cancel') !== false) { continue; }

            $geometry = $this->normalize_geometry($feature['geometry'] ?? null);
            $geometry_source = $geometry ? 'alert-polygon' : '';
            if (!$geometry) {
                $geometry = $this->affected_zone_geometry((array)($props['affectedZones'] ?? array()));
                if ($geometry) { $geometry_source = 'nws-zone-geometry'; }
            }
            if (!$geometry) { continue; } // Accuracy first: never invent missing geometry.

            $region_counties = $this->gis->affected_region9_counties($geometry);
            $distance = $this->gis->distance_to_region9_miles($geometry);
            $in_region = !empty($region_counties);
            $in_crawl = $in_region || ($distance !== null && $distance <= self::BUFFER_MILES);
            if (!$in_crawl) { continue; }

            $record = array(
                'id'=>$id,
                'event'=>sanitize_text_field($props['event'] ?? 'Weather Alert'),
                'severity'=>sanitize_text_field($props['severity'] ?? ''),
                'urgency'=>sanitize_text_field($props['urgency'] ?? ''),
                'certainty'=>sanitize_text_field($props['certainty'] ?? ''),
                'headline'=>sanitize_text_field($props['headline'] ?? $props['event'] ?? 'Weather Alert'),
                'description'=>wp_strip_all_tags((string)($props['description'] ?? '')),
                'instruction'=>wp_strip_all_tags((string)($props['instruction'] ?? '')),
                'effective'=>sanitize_text_field($props['effective'] ?? ''),
                'onset'=>sanitize_text_field($props['onset'] ?? ''),
                'ends'=>sanitize_text_field($ends),
                'sent'=>sanitize_text_field($props['sent'] ?? ''),
                'sender'=>sanitize_text_field($props['senderName'] ?? $props['sender'] ?? 'National Weather Service'),
                'region9_counties'=>$region_counties,
                'distance_to_region9_miles'=>$distance === null ? null : round((float)$distance, 2),
                'scope'=>$in_region ? 'region9' : 'within-50-miles',
                'geometry_source'=>$geometry_source,
                'geometry'=>$geometry,
            );
            $crawl[$id] = $record;
            $map[$id] = $record;
            if ($in_region) { $live[$id] = $record; }
        }

        $state = array(
            'status'=>'healthy','updated'=>current_time('mysql'),'buffer_miles'=>self::BUFFER_MILES,
            'live_count'=>count($live),'crawl_count'=>count($crawl),'map_count'=>count($map),
        );
        update_option(self::LIVE_OPTION, array('status'=>'healthy','updated'=>$state['updated'],'alerts'=>array_values($live)), false);
        update_option(self::CRAWL_OPTION, array('status'=>'healthy','updated'=>$state['updated'],'buffer_miles'=>self::BUFFER_MILES,'alerts'=>array_values($crawl)), false);
        update_option(self::MAP_OPTION, array('status'=>'healthy','updated'=>$state['updated'],'alerts'=>array_values($map)), false);
        update_option(self::HEALTH_OPTION, $state, false);
        set_transient(self::CACHE, $state, 5 * MINUTE_IN_SECONDS);
        return $state;
    }

    public function live() { return get_option(self::LIVE_OPTION, array('status'=>'unknown','updated'=>'','alerts'=>array())); }
    public function crawl() { return get_option(self::CRAWL_OPTION, array('status'=>'unknown','updated'=>'','buffer_miles'=>self::BUFFER_MILES,'alerts'=>array())); }
    public function map() { return get_option(self::MAP_OPTION, array('status'=>'unknown','updated'=>'','alerts'=>array())); }
    public function health() { return get_option(self::HEALTH_OPTION, array('status'=>'unknown')); }

    private function failure($message) {
        $state = array('status'=>'failure','updated'=>current_time('mysql'),'message'=>sanitize_text_field($message));
        update_option(self::HEALTH_OPTION, $state, false);
        if ($this->audit) { $this->audit->write('warning', 'Alert scope refresh failed.', array('error'=>$message)); }
        return $state;
    }

    private function affected_zone_geometry($urls) {
        $geometries = array();
        foreach (array_slice(array_values(array_filter($urls, 'is_string')), 0, 100) as $url) {
            if (strpos($url, 'https://api.weather.gov/zones/') !== 0) { continue; }
            $key = 'r9ls_zone_geometry_' . md5($url);
            $geometry = get_transient($key);
            if ($geometry === false) {
                $response = wp_remote_get($url, array('timeout'=>8,'redirection'=>1,'headers'=>array('Accept'=>'application/geo+json','User-Agent'=>'Region9Weather/17 (WeatherSupportLLC; weather@region9weather.com)')));
                if (is_wp_error($response) || (int)wp_remote_retrieve_response_code($response) < 200 || (int)wp_remote_retrieve_response_code($response) >= 300) { continue; }
                $json = json_decode(wp_remote_retrieve_body($response), true);
                $geometry = $this->normalize_geometry($json['geometry'] ?? null);
                if ($geometry) { set_transient($key, $geometry, DAY_IN_SECONDS); }
            }
            if ($this->gis->valid_geometry($geometry)) { $geometries[] = $geometry; }
        }
        if (!$geometries) { return null; }
        $polygons = array();
        foreach ($geometries as $geometry) {
            if (($geometry['type'] ?? '') === 'Polygon') { $polygons[] = $geometry['coordinates']; }
            elseif (($geometry['type'] ?? '') === 'MultiPolygon') { foreach ($geometry['coordinates'] as $poly) { $polygons[] = $poly; } }
        }
        return $polygons ? array('type'=>'MultiPolygon','coordinates'=>$polygons) : null;
    }

    private function normalize_geometry($geometry) {
        return $this->gis->valid_geometry($geometry) ? $geometry : null;
    }
}
