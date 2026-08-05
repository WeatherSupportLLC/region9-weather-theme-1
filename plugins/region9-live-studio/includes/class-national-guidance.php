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
        'timeout' => 12,
        'user_agent' => 'Region9LiveStudio/17 Alpha7 (WeatherSupportLLC; WordPress wp_remote_get)',
        'cache_ttl' => 1800,
        'stale_ttl' => 21600,
        'max_age' => 86400,
    );
    private $rank_spc = array('TSTM' => 1, 'GENERAL THUNDER' => 1, 'MRGL' => 2, 'MARGINAL' => 2, 'SLGT' => 3, 'SLIGHT' => 3, 'ENH' => 4, 'ENHANCED' => 4, 'MDT' => 5, 'MODERATE' => 5, 'HIGH' => 6);
    private $rank_ero = array('MRGL' => 1, 'MARGINAL' => 1, 'SLGT' => 2, 'SLIGHT' => 2, 'MDT' => 3, 'MODERATE' => 3, 'HIGH' => 4);
    private $labels_spc = array(0 => 'None', 1 => 'General Thunder', 2 => 'Marginal', 3 => 'Slight', 4 => 'Enhanced', 5 => 'Moderate', 6 => 'High');
    private $labels_ero = array(0 => 'None', 1 => 'Marginal', 2 => 'Slight', 3 => 'Moderate', 4 => 'High');

    public function __construct($gis, $audit, $settings = array()) { $this->gis = $gis; $this->audit = $audit; $this->settings = wp_parse_args($settings, $this->defaults); }
    public function collect_all() { return array('spc_day1' => $this->spc_day1(), 'wpc_day1_ero' => $this->wpc_ero_day1(), 'wpc_day1_qpf' => $this->wpc_qpf_day1()); }
    public function spc_day1() { return $this->collect_outlook('spc_day1', $this->settings['spc_day1_url'], $this->rank_spc, $this->labels_spc); }
    public function wpc_ero_day1() { return $this->collect_outlook('wpc_day1_ero', $this->settings['wpc_ero_day1_url'], $this->rank_ero, $this->labels_ero); }

    private function collect_outlook($key, $url, $ranks, $labels) {
        $fetched = $this->fetch_json($key, $url);
        if ($fetched['status'] !== 'healthy' && empty($fetched['payload'])) { return $this->finish($key, $fetched); }
        $parsed = $this->parse_outlook($key, $fetched['payload'], $ranks, $labels);
        if ($parsed['status'] !== 'healthy') { return $this->finish($key, array_merge($fetched, $parsed)); }
        $geo = $this->gis->intersect_source($parsed);
        $parsed['affected_counties'] = $geo['affected_counties']; $parsed['county_risks'] = $geo['county_risks']; $parsed['highest_risk'] = $geo['highest_risk']; $parsed['highest_category'] = $labels[$geo['highest_risk']] ?? 'None';
        if (($fetched['status'] ?? '') === 'stale') { $parsed['status'] = 'stale'; $parsed['source_health'] = 'stale_cached_result'; }
        return $this->finish($key, array_merge($fetched, $parsed));
    }

    public function wpc_qpf_day1() {
        $key = 'wpc_day1_qpf'; $fetched = $this->fetch_json($key, $this->settings['wpc_qpf_day1_url']);
        if ($fetched['status'] !== 'healthy' && empty($fetched['payload'])) { return $this->finish($key, $fetched); }
        $parsed = $this->parse_qpf($fetched['payload']);
        return $this->finish($key, array_merge($fetched, $parsed));
    }

    private function fetch_json($key, $url) {
        $cache = get_transient(self::CACHE_PREFIX . $key); if (is_array($cache)) { $cache['cache'] = 'hit'; return $cache; }
        $started = microtime(true); $last_error = '';
        for ($i=0; $i<3; $i++) {
            $res = wp_remote_get($url, array('timeout' => (int)$this->settings['timeout'], 'user-agent' => $this->settings['user_agent']));
            if (!is_wp_error($res)) {
                $code = (int) wp_remote_retrieve_response_code($res); $body = wp_remote_retrieve_body($res);
                if ($code >= 200 && $code < 300 && is_string($body) && $body !== '') {
                    $json = json_decode($body, true);
                    if (is_array($json)) { $out = array('status' => 'healthy', 'source_health' => 'healthy', 'latency' => round(microtime(true)-$started,3), 'last_success_time' => current_time('mysql'), 'payload' => $json, 'cache' => 'miss'); set_transient(self::CACHE_PREFIX.$key, $out, (int)$this->settings['cache_ttl']); update_option(self::CACHE_PREFIX.$key.'_stale', array_merge($out, array('stored_at'=>time())), false); return $out; }
                    $last_error = 'invalid json';
                } else { $last_error = 'http '.$code; }
            } else { $last_error = $res->get_error_message(); }
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
        foreach ($features as $feature) { $geom = $feature['geometry'] ?? null; if (!$this->valid_geometry($geom)) { return array('status'=>'malformed','source_health'=>'malformed_geometry','hazards'=>array()); } $props = (array)($feature['properties'] ?? array()); $name = strtoupper((string)($props['LABEL'] ?? $props['label'] ?? $props['DN'] ?? $props['risk'] ?? $props['OUTLOOK'] ?? '')); $risk = $ranks[$name] ?? (is_numeric($name) ? (int)$name : 0); if ($risk > 0) { $hazards[] = array('risk'=>$risk, 'category'=>$labels[$risk] ?? $name, 'geometry'=>$geom, 'timing'=>$this->period($valid_from,$valid_to)); } }
        if (!$this->fresh($issue, $valid_from, $valid_to)) { return array('status'=>'stale','source_health'=>'stale_cached_result','hazards'=>$hazards,'issue_time'=>$issue,'valid_from'=>$valid_from,'valid_to'=>$valid_to); }
        return array('status'=>'healthy','source_health'=>'healthy','hazards'=>$hazards,'issue_time'=>$issue,'valid_from'=>$valid_from,'valid_to'=>$valid_to);
    }
    private function parse_qpf($json) { if (($json['type'] ?? '') !== 'FeatureCollection') { return array('status'=>'malformed','source_health'=>'malformed_geometry','county_precipitation'=>array()); } $vals = array_fill_keys($this->gis->county_names(), null); foreach ((array)$json['features'] as $feature) { $p=(array)($feature['properties']??array()); $v=$p['qpf_in'] ?? (isset($p['qpf_mm']) ? ((float)$p['qpf_mm']/25.4) : ($p['amount'] ?? null)); if ($v===null || !is_numeric($v)) { continue; } $src=array('status'=>'healthy','hazards'=>array(array('risk'=>1,'geometry'=>$feature['geometry'] ?? null))); $hits=$this->gis->intersect_source($src); foreach ($hits['affected_counties'] as $c) { $vals[$c]=max((float)($vals[$c] ?? 0), round((float)$v,2)); } } $valid = $this->time_prop($json, array('valid','valid_time','valid_to','end')); return array('status'=>'healthy','source_health'=>'healthy','county_precipitation'=>$vals,'unit'=>'in','source_valid_time'=>$valid,'source_age'=>max(0, time()-strtotime($valid)),'confidence'=>in_array(null,$vals,true)?70:90); }
    private function time_prop($json,$keys){ foreach(array_merge(array($json['properties']??array()), (array)($json['features'][0]['properties']??array())) as $props){ foreach($keys as $k){ if(!empty($props[$k])) return date('c', strtotime($props[$k])); }} return current_time('mysql'); }
    private function fresh($issue,$from,$to){ foreach(array($issue,$from,$to) as $t){ if(!$t || strtotime($t)===false) return false; } return strtotime($issue) > time() - (int)$this->settings['max_age'] && strtotime($to) > time() - 3600; }
    private function period($a,$b){ return trim($a.' to '.$b); }
    private function valid_geometry($g){ return is_array($g) && in_array($g['type'] ?? '', array('Polygon','MultiPolygon'), true) && !empty($g['coordinates']); }
    private function finish($key,$data){ unset($data['payload']); $old = get_option(self::HEALTH, array()); $prev = $old[$key]['source_health'] ?? 'unknown'; $now = $data['source_health'] ?? $data['status']; if ($prev !== 'unknown' && $prev !== $now) { $this->audit->write($now === 'healthy' ? 'info' : 'warning', 'Source health changed.', array('source'=>$key,'from'=>$prev,'to'=>$now)); } $old[$key] = array_merge($data, array('updated'=>current_time('mysql'))); update_option(self::HEALTH, $old, false); return $data; }
}
