<?php
defined('ABSPATH') || exit;

class R9LS_GIS_Engine {
    const CACHE_OPTION = 'r9ls_authoritative_region9_counties';
    const META_OPTION = 'r9ls_authoritative_region9_counties_meta';
    private $counties = array();
    private $local_file = '';

    public function __construct($county_file) {
        $this->local_file = $county_file;
        $this->load_counties($county_file);
        $this->load_cached_authoritative();
    }
    public function county_names() { return array_keys($this->counties); }
    public function county_geometries() { return $this->counties; }
    public function county_geometry($county) { return $this->counties[$county] ?? null; }

    public function authoritative_meta() {
        return wp_parse_args((array)get_option(self::META_OPTION, array()), array(
            'source' => 'Bundled operational fallback geometry', 'updated' => '', 'status' => 'fallback'
        ));
    }

    public function refresh_authoritative_counties($force = false) {
        $meta = $this->authoritative_meta();
        $last = !empty($meta['updated_gmt']) ? strtotime($meta['updated_gmt']) : 0;
        if (!$force && $last && (time() - $last) < 7 * DAY_IN_SECONDS) { return true; }

        $fips = array('17091','17075','17053','17105','17113','17147','17039','17019','17183');
        $where = "GEOID in ('" . implode("','", $fips) . "')";
        $url = add_query_arg(array(
            'where' => $where,
            'outFields' => 'GEOID,NAME,BASENAME,STATE,COUNTY,CENTLAT,CENTLON',
            'returnGeometry' => 'true',
            'outSR' => '4326',
            'f' => 'geojson',
        ), 'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/9/query');
        $response = wp_safe_remote_get($url, array(
            'timeout' => 20,
            'redirection' => 2,
            'headers' => array('User-Agent' => 'Region9LiveStudio/' . (defined('R9LS_VERSION') ? R9LS_VERSION : 'unknown')),
        ));
        if (is_wp_error($response) || 200 !== (int)wp_remote_retrieve_response_code($response)) {
            update_option(self::META_OPTION, array('source'=>'U.S. Census Bureau TIGERweb Counties','updated'=>current_time('mysql'),'updated_gmt'=>gmdate('c'),'status'=>'refresh-failed'), false);
            return false;
        }
        $json = json_decode(wp_remote_retrieve_body($response), true);
        $next = array();
        foreach ((array)($json['features'] ?? array()) as $feature) {
            $props = (array)($feature['properties'] ?? array());
            $name = trim((string)($props['BASENAME'] ?? $props['NAME'] ?? ''));
            $name = preg_replace('/\s+County$/i', '', $name);
            if ($name && $this->valid_geometry($feature['geometry'] ?? null)) { $next[$name] = $feature['geometry']; }
        }
        $required = array('Kankakee','Iroquois','Ford','Livingston','McLean','Piatt','DeWitt','Champaign','Vermilion');
        foreach ($required as $name) { if (empty($next[$name])) { return false; } }
        $this->counties = array_intersect_key($next, array_flip($required));
        update_option(self::CACHE_OPTION, $this->counties, false);
        update_option(self::META_OPTION, array(
            'source' => 'U.S. Census Bureau TIGERweb Counties (January 1, 2025 vintage)',
            'endpoint' => 'TIGERweb/State_County/MapServer/9',
            'updated' => current_time('mysql'), 'updated_gmt' => gmdate('c'), 'status' => 'authoritative',
        ), false);
        return true;
    }

    private function load_cached_authoritative() {
        $cached = get_option(self::CACHE_OPTION, array());
        if (!is_array($cached) || count($cached) !== 9) { return; }
        foreach ($cached as $name => $geometry) { if (!$this->valid_geometry($geometry)) { return; } }
        $this->counties = $cached;
    }

    public function intersect_source($source) {
        if (!isset($source['status']) || !in_array($source['status'], array('healthy','stale'), true)) {
            return array('status'=>'source_failure','affected_counties'=>array(),'highest_risk'=>0,'county_risks'=>array());
        }
        $county_risks = array_fill_keys($this->county_names(), 0);
        foreach ((array)($source['hazards'] ?? array()) as $hazard) {
            $risk = isset($hazard['risk']) ? absint($hazard['risk']) : 1;
            foreach ((array)($hazard['affected_counties'] ?? array()) as $county) {
                if (isset($county_risks[$county])) { $county_risks[$county] = max($county_risks[$county], $risk); }
            }
            $geometry = $hazard['geometry'] ?? null;
            if (!$this->valid_geometry($geometry)) { continue; }
            foreach ($this->counties as $county => $county_geometry) {
                if ($this->geometries_intersect($geometry, $county_geometry)) { $county_risks[$county] = max($county_risks[$county], $risk); }
            }
        }
        $affected = array_keys(array_filter($county_risks));
        return array('status'=>'ok','affected_counties'=>$affected,'highest_risk'=>empty($county_risks)?0:max($county_risks),'county_risks'=>$county_risks);
    }

    public function affected_region9_counties($geometry) {
        $out = array();
        if (!$this->valid_geometry($geometry)) { return $out; }
        foreach ($this->counties as $county => $county_geometry) {
            if ($this->geometries_intersect($geometry, $county_geometry)) { $out[] = $county; }
        }
        return $out;
    }
    public function intersects_region9($geometry) { return !empty($this->affected_region9_counties($geometry)); }
    public function distance_to_region9_miles($geometry) {
        if (!$this->valid_geometry($geometry)) { return null; }
        if ($this->intersects_region9($geometry)) { return 0.0; }
        $min = null;
        foreach ($this->polygons($geometry) as $source_poly) foreach ($this->counties as $county_geometry) foreach ($this->polygons($county_geometry) as $county_poly) {
            $d = $this->polygon_distance_miles($source_poly, $county_poly); if ($min === null || $d < $min) $min = $d;
        }
        return $min;
    }
    private function load_counties($file) {
        if (!is_readable($file)) return;
        $json = json_decode(file_get_contents($file), true);
        foreach (($json['features'] ?? array()) as $feature) {
            $name = $feature['properties']['name'] ?? '';
            if ($name && $this->valid_geometry($feature['geometry'] ?? null)) $this->counties[$name] = $feature['geometry'];
        }
    }
    public function valid_geometry($geometry) { return is_array($geometry) && in_array($geometry['type'] ?? '', array('Polygon','MultiPolygon'), true) && !empty($geometry['coordinates']); }
    private function polygons($geometry) { if (!$this->valid_geometry($geometry)) return array(); return $geometry['type'] === 'Polygon' ? array($geometry['coordinates'][0]) : array_map(function($poly){ return $poly[0]; }, $geometry['coordinates']); }
    private function geometries_intersect($a,$b) { foreach($this->polygons($a) as $pa) foreach($this->polygons($b) as $pb) if($this->bbox_intersects($this->bbox($pa),$this->bbox($pb))&&$this->polygon_intersects($pa,$pb)) return true; return false; }
    private function bbox($poly){$xs=array_column($poly,0);$ys=array_column($poly,1);return array(min($xs),min($ys),max($xs),max($ys));}
    private function bbox_intersects($a,$b){return !($a[2]<$b[0]||$a[0]>$b[2]||$a[3]<$b[1]||$a[1]>$b[3]);}
    private function polygon_intersects($a,$b){foreach($a as $p)if($this->point_in_polygon($p,$b))return true;foreach($b as $p)if($this->point_in_polygon($p,$a))return true;for($i=0;$i<count($a)-1;$i++)for($j=0;$j<count($b)-1;$j++)if($this->segments_intersect($a[$i],$a[$i+1],$b[$j],$b[$j+1]))return true;return false;}
    private function point_in_polygon($point,$poly){$inside=false;$x=$point[0];$y=$point[1];$n=count($poly);for($i=0,$j=$n-1;$i<$n;$j=$i++){[$xi,$yi]=$poly[$i];[$xj,$yj]=$poly[$j];$intersect=(($yi>$y)!==($yj>$y))&&($x<($xj-$xi)*($y-$yi)/(($yj-$yi)?:1.0e-12)+$xi);if($intersect)$inside=!$inside;}return $inside;}
    private function segments_intersect($p1,$p2,$p3,$p4){$d1=$this->direction($p3,$p4,$p1);$d2=$this->direction($p3,$p4,$p2);$d3=$this->direction($p1,$p2,$p3);$d4=$this->direction($p1,$p2,$p4);return (($d1>0&&$d2<0)||($d1<0&&$d2>0))&&(($d3>0&&$d4<0)||($d3<0&&$d4>0));}
    private function direction($a,$b,$c){return (($c[0]-$a[0])*($b[1]-$a[1]))-(($b[0]-$a[0])*($c[1]-$a[1]));}
    private function polygon_distance_miles($a,$b){$min=INF;for($i=0;$i<count($a)-1;$i++)for($j=0;$j<count($b)-1;$j++)$min=min($min,$this->segment_distance_miles($a[$i],$a[$i+1],$b[$j],$b[$j+1]));return is_finite($min)?$min:9999.0;}
    private function segment_distance_miles($a1,$a2,$b1,$b2){if($this->segments_intersect($a1,$a2,$b1,$b2))return 0.0;return min($this->point_segment_distance_miles($a1,$b1,$b2),$this->point_segment_distance_miles($a2,$b1,$b2),$this->point_segment_distance_miles($b1,$a1,$a2),$this->point_segment_distance_miles($b2,$a1,$a2));}
    private function point_segment_distance_miles($p,$a,$b){$lat0=deg2rad(($p[1]+$a[1]+$b[1])/3.0);$sx=69.172*cos($lat0);$sy=69.0;$px=$p[0]*$sx;$py=$p[1]*$sy;$ax=$a[0]*$sx;$ay=$a[1]*$sy;$bx=$b[0]*$sx;$by=$b[1]*$sy;$dx=$bx-$ax;$dy=$by-$ay;$len2=$dx*$dx+$dy*$dy;if($len2<=0)return sqrt(($px-$ax)**2+($py-$ay)**2);$t=(($px-$ax)*$dx+($py-$ay)*$dy)/$len2;$t=max(0,min(1,$t));$qx=$ax+$t*$dx;$qy=$ay+$t*$dy;return sqrt(($px-$qx)**2+($py-$qy)**2);}
}
