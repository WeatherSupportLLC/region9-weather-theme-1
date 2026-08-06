<?php
defined('ABSPATH') || exit;

class R9LS_National_Guidance {
    const CACHE_PREFIX = 'r9ls_guidance_';
    const HEALTH = 'r9ls_source_health';
    private $gis;
    private $audit;
    private $settings;
    private $defaults = array(
        'spc_day1_url' => 'https://www.spc.noaa.gov/products/outlook/day1otlk_cat.nolyr.geojson',
        'wpc_ero_day1_url' => 'https://www.wpc.ncep.noaa.gov/qpf/ero_day1.geojson',
        'wpc_qpf_day1_url' => 'https://www.wpc.ncep.noaa.gov/qpf/day1_qpf.geojson',
        'nws_alerts_url' => 'https://api.weather.gov/alerts/active?area=IL',
        'nws_points_url' => 'https://api.weather.gov/points/40.1164,-88.2434',
        'nws_grid_url' => '',
        'nws_hourly_url' => '',
        'timeout' => 12,
        'user_agent' => 'Region9LiveStudio/17.1.0 (WeatherSupportLLC; WordPress wp_remote_get)',
        'cache_ttl' => 1800,
        'stale_ttl' => 21600,
        'max_age' => 86400,
    );
    private $rank_spc = array('TSTM' => 1, 'GENERAL THUNDER' => 1, 'MRGL' => 2, 'MARGINAL' => 2, 'SLGT' => 3, 'SLIGHT' => 3, 'ENH' => 4, 'ENHANCED' => 4, 'MDT' => 5, 'MODERATE' => 5, 'HIGH' => 6);
    private $rank_ero = array('MRGL' => 1, 'MARGINAL' => 1, 'SLGT' => 2, 'SLIGHT' => 2, 'MDT' => 3, 'MODERATE' => 3, 'HIGH' => 4);
    private $labels_spc = array(0 => 'None', 1 => 'General Thunder', 2 => 'Marginal', 3 => 'Slight', 4 => 'Enhanced', 5 => 'Moderate', 6 => 'High');
    private $labels_ero = array(0 => 'None', 1 => 'Marginal', 2 => 'Slight', 3 => 'Moderate', 4 => 'High');

    public function __construct($gis, $audit, $settings = array()) { $this->gis = $gis; $this->audit = $audit; $this->settings = wp_parse_args($settings, $this->defaults); }
    public function collect_all() { return array('spc_day1' => $this->spc_day1(), 'wpc_day1_ero' => $this->wpc_ero_day1(), 'wpc_day1_qpf' => $this->wpc_qpf_day1(), 'nws_alerts' => $this->nws_alerts(), 'nws_points_grid_hourly' => $this->nws_points_grid_hourly()); }
    public function spc_day1() { return $this->collect_outlook('spc_day1', $this->settings['spc_day1_url'], $this->rank_spc, $this->labels_spc); }
    public function wpc_ero_day1() { return $this->collect_outlook('wpc_day1_ero', $this->settings['wpc_ero_day1_url'], $this->rank_ero, $this->labels_ero); }

    private function collect_outlook($key, $url, $ranks, $labels) {
        $fetched = $this->fetch_json($key, $url);
        if ($fetched['status'] !== 'healthy' && empty($fetched['payload'])) { return $this->finish($key, $fetched); }
        $parsed = $this->parse_outlook($key, $fetched['payload'], $ranks, $labels);
        if ($parsed['status'] !== 'healthy' && $parsed['status'] !== 'stale') { return $this->finish($key, array_merge($fetched, $parsed)); }
        $geo = $this->gis->intersect_source($parsed);
        $parsed['affected_counties'] = $geo['affected_counties']; $parsed['county_risks'] = $geo['county_risks']; $parsed['highest_risk'] = $geo['highest_risk']; $parsed['highest_category'] = $labels[$geo['highest_risk']] ?? 'None';
        if (($fetched['status'] ?? '') === 'stale') { $parsed['status'] = 'stale'; $parsed['source_health'] = 'stale_cached_result'; }
        return $this->finish($key, array_merge($fetched, $parsed));
    }

    public function wpc_qpf_day1() {
        $key = 'wpc_day1_qpf'; $fetched = $this->fetch_json($key, $this->settings['wpc_qpf_day1_url']);
        if ($fetched['status'] !== 'healthy' && empty($fetched['payload'])) { return $this->finish($key, $fetched); }
        $parsed = $this->parse_qpf($fetched['payload']);
        if (($fetched['status'] ?? '') === 'stale' && ($parsed['status'] ?? '') === 'healthy') { $parsed['status'] = 'stale'; $parsed['source_health'] = 'stale_cached_result'; }
        return $this->finish($key, array_merge($fetched, $parsed));
    }

    public function nws_alerts() {
        $key = 'nws_alerts'; $fetched = $this->fetch_json($key, $this->settings['nws_alerts_url']);
        if ($fetched['status'] !== 'healthy' && empty($fetched['payload'])) { return $this->finish($key, $fetched); }
        $parsed = $this->parse_nws_alerts($fetched['payload']);
        if (($fetched['status'] ?? '') === 'stale' && ($parsed['status'] ?? '') === 'healthy') { $parsed['status'] = 'stale'; $parsed['source_health'] = 'stale_cached_result'; }
        return $this->finish($key, array_merge($fetched, $parsed));
    }

    public function nws_points_grid_hourly() {
        $key = 'nws_points_grid_hourly';
        $grid_url = $this->settings['nws_grid_url']; $hourly_url = $this->settings['nws_hourly_url'];
        if (!$grid_url || !$hourly_url) {
            $points = $this->fetch_json($key . '_points', $this->settings['nws_points_url']);
            if ($points['status'] !== 'healthy' && empty($points['payload'])) { return $this->finish($key, array_merge($points, array('forecast_periods'=>array(), 'hourly_periods'=>array()))); }
            $props = (array)($points['payload']['properties'] ?? array());
            $grid_url = $grid_url ?: (string)($props['forecast'] ?? ''); $hourly_url = $hourly_url ?: (string)($props['forecastHourly'] ?? '');
        }
        $grid = $grid_url ? $this->fetch_json($key . '_grid', $grid_url) : array('status'=>'unavailable','source_health'=>'unavailable_source','error'=>'missing grid forecast url');
        $hourly = $hourly_url ? $this->fetch_json($key . '_hourly', $hourly_url) : array('status'=>'unavailable','source_health'=>'unavailable_source','error'=>'missing hourly forecast url');
        if (($grid['status'] ?? '') !== 'healthy' && empty($grid['payload']) && ($hourly['status'] ?? '') !== 'healthy' && empty($hourly['payload'])) { return $this->finish($key, array_merge($grid, array('forecast_periods'=>array(), 'hourly_periods'=>array()))); }
        $parsed = $this->parse_nws_grid_hourly($grid['payload'] ?? array(), $hourly['payload'] ?? array());
        if (($grid['status'] ?? '') === 'stale' || ($hourly['status'] ?? '') === 'stale') { $parsed['status'] = 'stale'; $parsed['source_health'] = 'stale_cached_result'; }
        return $this->finish($key, array_merge($parsed, array('grid_status'=>$grid['status'] ?? 'unknown', 'hourly_status'=>$hourly['status'] ?? 'unknown')));
    }

    private function fetch_json($key, $url) {
        $cache = get_transient(self::CACHE_PREFIX . $key); if (is_array($cache)) { $cache['cache'] = 'hit'; return $cache; }
        $started = microtime(true); $last_error = '';
        for ($i=0; $i<3; $i++) {
            $res = wp_remote_get($url, array('timeout' => (int)$this->settings['timeout'], 'user-agent' => $this->settings['user_agent']));
            if (!is_wp_error($res)) {
                $code = (int) wp_remote_retrieve_response_code($res); $body = wp_remote_retrieve_body($res); $ctype = function_exists('wp_remote_retrieve_header') ? strtolower((string) wp_remote_retrieve_header($res, 'content-type')) : strtolower((string)($res['headers']['content-type'] ?? $res['headers']['Content-Type'] ?? ''));
                if ($code >= 200 && $code < 300 && $ctype && strpos($ctype, 'json') === false && strpos($ctype, 'geo') === false && strpos($ctype, 'text/plain') === false) { $last_error = 'unexpected content-type ' . $ctype; }
                elseif ($code >= 200 && $code < 300 && is_string($body) && $body !== '') {
                    $json = json_decode($body, true);
                    if (is_array($json)) { $out = array('status' => 'healthy', 'source_health' => 'healthy', 'latency' => round(microtime(true)-$started,3), 'last_success_time' => current_time('mysql'), 'payload' => $json, 'cache' => 'miss'); set_transient(self::CACHE_PREFIX.$key, $out, (int)$this->settings['cache_ttl']); update_option(self::CACHE_PREFIX.$key.'_stale', array_merge($out, array('stored_at'=>time())), false); return $out; }
                    $last_error = 'invalid json';
                } else { $last_error = 'http '.$code; }
            } else { $last_error = method_exists($res, 'get_error_message') ? $res->get_error_message() : 'wp_error'; }
            if ($i < 2) { usleep((int) pow(2, $i) * 100000); }
        }
        $stale = get_option(self::CACHE_PREFIX.$key.'_stale', array());
        if (is_array($stale) && !empty($stale['payload']) && (time() - (int)($stale['stored_at'] ?? 0)) <= (int)$this->settings['stale_ttl']) { $stale['status'] = 'stale'; $stale['source_health'] = 'stale_cached_result'; $stale['error'] = $last_error; return $stale; }
        return array('status' => 'unavailable', 'source_health' => 'unavailable_source', 'error' => $last_error, 'latency' => round(microtime(true)-$started,3));
    }

    private function parse_outlook($key, $json, $ranks, $labels) {
        if (($json['type'] ?? '') !== 'FeatureCollection') { return array('status'=>'malformed','source_health'=>'malformed_geometry','hazards'=>array()); }
        $issue = $this->time_prop($json, array('issue','issued','issuance','issue_time')); $valid_from = $this->time_prop($json, array('valid_from','valid_start','start','validTime')); $valid_to = $this->time_prop($json, array('valid_to','valid_end','end','expire','expiration'));
        $features = (array)($json['features'] ?? array()); $hazards = array();
        foreach ($features as $feature) { $geom = $feature['geometry'] ?? null; if (!$this->valid_geometry($geom)) { return array('status'=>'malformed','source_health'=>'malformed_geometry','hazards'=>array()); } $props = (array)($feature['properties'] ?? array()); $name = strtoupper((string)($props['LABEL'] ?? $props['label'] ?? $props['DN'] ?? $props['risk'] ?? $props['OUTLOOK'] ?? $props['CATEGORY'] ?? '')); $risk = $ranks[$name] ?? (is_numeric($name) ? (int)$name : 0); if ($risk > 0) { $hazards[] = array('risk'=>$risk, 'category'=>$labels[$risk] ?? $name, 'geometry'=>$geom, 'timing'=>$this->period($valid_from,$valid_to)); } }
        if (!$this->fresh($issue, $valid_from, $valid_to)) { return array('status'=>'stale','source_health'=>'stale_cached_result','hazards'=>$hazards,'issue_time'=>$issue,'valid_from'=>$valid_from,'valid_to'=>$valid_to); }
        return array('status'=>'healthy','source_health'=>'healthy','hazards'=>$hazards,'issue_time'=>$issue,'valid_from'=>$valid_from,'valid_to'=>$valid_to);
    }

    private function parse_qpf($json) {
        if (($json['type'] ?? '') !== 'FeatureCollection') { return array('status'=>'malformed','source_health'=>'malformed_geometry','county_precipitation'=>array(),'error'=>'QPF was not GeoJSON'); }
        $vals = array_fill_keys($this->gis->county_names(), null); $valid_features = 0; $ambiguous = 0;
        foreach ((array)$json['features'] as $feature) {
            $geom = $feature['geometry'] ?? null; if (!$this->valid_geometry($geom)) { continue; }
            $p=(array)($feature['properties']??array()); $unit = strtolower((string)($p['unit'] ?? $p['units'] ?? $p['UNIT'] ?? ''));
            $v = null;
            if (isset($p['qpf_in']) || isset($p['QPF_IN']) || isset($p['inches'])) { $v = $p['qpf_in'] ?? $p['QPF_IN'] ?? $p['inches']; $unit = 'in'; }
            elseif (isset($p['qpf_mm']) || isset($p['QPF_MM']) || isset($p['millimeters'])) { $v = $p['qpf_mm'] ?? $p['QPF_MM'] ?? $p['millimeters']; $unit = 'mm'; }
            elseif (isset($p['VALUE'])) { $v = $p['VALUE']; $unit = 'in'; }
            elseif (isset($p['amount']) || isset($p['qpf'])) { $v = $p['amount'] ?? $p['qpf']; }
            if (is_string($v) && preg_match('/([0-9.]+)/', $v, $m)) { $v = $m[1]; }
            if ($v===null || !is_numeric($v)) { continue; }
            if ($unit && in_array($unit, array('mm','millimeter','millimeters'), true)) { $v = (float)$v / 25.4; }
            elseif ($unit && in_array($unit, array('in','inch','inches'), true)) { $v = (float)$v; }
            elseif (isset($p['amount']) || isset($p['qpf'])) { $ambiguous++; continue; }
            $valid_features++;
            $src=array('status'=>'healthy','hazards'=>array(array('risk'=>1,'geometry'=>$geom))); $hits=$this->gis->intersect_source($src);
            foreach ($hits['affected_counties'] as $c) { $vals[$c]=max((float)($vals[$c] ?? 0), round((float)$v,2)); }
        }
        if ($ambiguous && !$valid_features) { return array('status'=>'malformed','source_health'=>'schema_change','county_precipitation'=>array(),'error'=>'QPF units were ambiguous'); }
        $valid = $this->time_prop($json, array('valid','valid_time','valid_to','end'));
        return array('status'=>'healthy','source_health'=>'healthy','county_precipitation'=>$vals,'unit'=>'in','source_valid_time'=>$valid,'source_age'=>max(0, time()-strtotime($valid)),'confidence'=>in_array(null,$vals,true)?70:90,'healthy_zero_qpf'=>!array_filter($vals));
    }
    private function parse_nws_alerts($json) {
        if (($json['type'] ?? '') !== 'FeatureCollection') { return array('status'=>'malformed','source_health'=>'malformed_payload','hazards'=>array()); }
        $hazards=array(); $seen=array(); $now=time();
        foreach ((array)($json['features'] ?? array()) as $feature) {
            $props=(array)($feature['properties'] ?? array()); $geom=$feature['geometry'] ?? null; $event=(string)($props['event'] ?? 'Weather Alert'); $severity=(string)($props['severity'] ?? ''); $risk=$this->alert_risk($event, $severity); $counties=$this->alert_counties($props);
            if ($geom && $this->valid_geometry($geom)) { $hits=$this->gis->intersect_source(array('status'=>'healthy','hazards'=>array(array('risk'=>$risk,'geometry'=>$geom)))); $counties=array_values(array_unique(array_merge($counties, $hits['affected_counties']))); }
            if (!$counties) { continue; }
            $id = sanitize_text_field((string)($props['id'] ?? $props['@id'] ?? $feature['id'] ?? md5($event . serialize($counties)))); if (isset($seen[$id])) { continue; } $seen[$id]=true;
            $ends = (string)($props['ends'] ?? $props['expires'] ?? ''); $status = (stripos($event, 'cancellation') !== false || stripos((string)($props['messageType'] ?? ''), 'cancel') !== false) ? 'cancelled' : 'active';
            if ($ends && strtotime($ends) && strtotime($ends) < $now) { $status = 'expired'; }
            $hazards[]=array('id'=>$id,'risk'=>$risk,'event'=>$event,'headline'=>(string)($props['headline'] ?? $event),'description'=>(function_exists('wp_strip_all_tags') ? wp_strip_all_tags((string)($props['description'] ?? '')) : strip_tags((string)($props['description'] ?? ''))),'instruction'=>(function_exists('wp_strip_all_tags') ? wp_strip_all_tags((string)($props['instruction'] ?? '')) : strip_tags((string)($props['instruction'] ?? ''))),'severity'=>$severity,'urgency'=>(string)($props['urgency'] ?? ''),'certainty'=>(string)($props['certainty'] ?? ''),'office'=>(string)($props['senderName'] ?? $props['sender'] ?? 'National Weather Service'),'updated'=>(string)($props['sent'] ?? $props['updated'] ?? ''),'effective'=>(string)($props['effective'] ?? ''),'onset'=>(string)($props['onset'] ?? ''),'ends'=>$ends,'status'=>$status,'affected_counties'=>$counties,'timing'=>$this->period((string)($props['effective'] ?? ''),$ends),'geometry'=>$geom ? array('type'=>$geom['type']) : null);
        }
        return array('status'=>'healthy','source_health'=>'healthy','hazards'=>$hazards,'alert_count'=>count($hazards),'healthy_zero_alerts'=>count($hazards)===0,'affected_counties'=>array_values(array_unique(array_merge(...array_map(function($h){ return $h['affected_counties']; }, $hazards ?: array(array('affected_counties'=>array())))))));
    }
    private function parse_nws_grid_hourly($grid, $hourly) { $forecast=(array)($grid['properties']['periods'] ?? array()); $hours=(array)($hourly['properties']['periods'] ?? array()); if (!$forecast && !$hours) { return array('status'=>'malformed','source_health'=>'malformed_payload','forecast_periods'=>array(),'hourly_periods'=>array()); } return array('status'=>'healthy','source_health'=>'healthy','forecast_periods'=>array_slice($forecast,0,14),'hourly_periods'=>array_slice($hours,0,48),'summary'=>$forecast[0]['detailedForecast'] ?? $forecast[0]['shortForecast'] ?? $hours[0]['shortForecast'] ?? 'NWS forecast available'); }
    private function alert_risk($event, $severity) { $text=strtolower($event.' '.$severity); if (strpos($text,'warning')!==false || strpos($text,'extreme')!==false) return 5; if (strpos($text,'watch')!==false || strpos($text,'severe')!==false || strpos($text,'flood')!==false) return 4; if (strpos($text,'advisory')!==false || strpos($text,'moderate')!==false) return 3; if (strpos($text,'statement')!==false || strpos($text,'minor')!==false) return 2; return 1; }
    private function alert_counties($props) { $out=array(); $text=implode(' ', array_merge((array)($props['areaDesc'] ?? ''), (array)($props['geocode']['SAME'] ?? array()), (array)($props['geocode']['UGC'] ?? array()))); foreach ($this->gis->county_names() as $county) { if (stripos($text, $county) !== false) { $out[]=$county; } } return $out; }
    private function time_prop($json,$keys){ foreach(array_merge(array($json['properties']??array()), (array)($json['features'][0]['properties']??array())) as $props){ foreach($keys as $k){ if(!empty($props[$k])) return date('c', strtotime($props[$k])); }} return current_time('mysql'); }
    private function fresh($issue,$from,$to){ foreach(array($issue,$from,$to) as $t){ if(!$t || strtotime($t)===false) return false; } return strtotime($issue) > time() - (int)$this->settings['max_age'] && strtotime($to) > time() - 3600; }
    private function period($a,$b){ return trim($a.' to '.$b); }
    private function valid_geometry($g){ return is_array($g) && in_array($g['type'] ?? '', array('Polygon','MultiPolygon'), true) && !empty($g['coordinates']); }
    private function finish($key,$data){ unset($data['payload']); if ($key === 'nws_alerts') { update_option('r9ls_canonical_alert_state', array('status'=>$data['status'] ?? 'unknown','source_health'=>$data['source_health'] ?? 'unknown','alerts'=>$data['hazards'] ?? array(),'updated'=>current_time('mysql'),'error'=>$data['error'] ?? ''), false); } $old = get_option(self::HEALTH, array()); $prior=$old[$key]??array(); $prev = $prior['source_health'] ?? 'unknown'; $now = $data['source_health'] ?? $data['status']; $healthy=$now==='healthy'; $telemetry=array('updated'=>current_time('mysql'),'last_success_time'=>$healthy?($data['last_success_time']??current_time('mysql')):($prior['last_success_time']??''),'last_failure_time'=>$healthy?($prior['last_failure_time']??''):current_time('mysql'),'error_count'=>$healthy?0:((int)($prior['error_count']??0)+1),'retry_status'=>$healthy?'not_required':'scheduled_validation_retry'); if ($prev !== 'unknown' && $prev !== $now) { $this->audit->write($now === 'healthy' ? 'info' : 'warning', 'Source health changed.', array('source'=>$key,'from'=>$prev,'to'=>$now)); } $old[$key] = array_merge($prior,$data,$telemetry); update_option(self::HEALTH, $old, false); return $data; }
}
