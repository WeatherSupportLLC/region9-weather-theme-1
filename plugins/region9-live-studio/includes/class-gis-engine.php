<?php
defined('ABSPATH') || exit;

class R9LS_GIS_Engine {
    private $counties = array();

    public function __construct($county_file) {
        $this->load_counties($county_file);
    }

    public function county_names() {
        return array_keys($this->counties);
    }

    public function intersect_source($source) {
        if (!isset($source['status']) || $source['status'] !== 'healthy') {
            return array('status' => 'source_failure', 'affected_counties' => array(), 'highest_risk' => 0, 'county_risks' => array());
        }
        $county_risks = array_fill_keys($this->county_names(), 0);
        foreach ((array) ($source['hazards'] ?? array()) as $hazard) {
            $risk = isset($hazard['risk']) ? absint($hazard['risk']) : 1;
            foreach ((array)($hazard['affected_counties'] ?? array()) as $county) {
                if (isset($county_risks[$county])) { $county_risks[$county] = max($county_risks[$county], $risk); }
            }
            $geometry = $hazard['geometry'] ?? null;
            if (!$this->valid_geometry($geometry)) {
                continue;
            }
            $source_polys = $this->polygons($geometry);
            foreach ($this->counties as $county => $county_geometry) {
                foreach ($source_polys as $source_poly) {
                    foreach ($this->polygons($county_geometry) as $county_poly) {
                        if ($this->bbox_intersects($this->bbox($source_poly), $this->bbox($county_poly)) && $this->polygon_intersects($source_poly, $county_poly)) {
                            $county_risks[$county] = max($county_risks[$county], $risk);
                        }
                    }
                }
            }
        }
        $affected = array_keys(array_filter($county_risks));
        return array('status' => 'ok', 'affected_counties' => $affected, 'highest_risk' => empty($county_risks) ? 0 : max($county_risks), 'county_risks' => $county_risks);
    }

    private function load_counties($file) {
        $json = json_decode(file_get_contents($file), true);
        foreach (($json['features'] ?? array()) as $feature) {
            $name = $feature['properties']['name'] ?? '';
            if ($name && $this->valid_geometry($feature['geometry'] ?? null)) {
                $this->counties[$name] = $feature['geometry'];
            }
        }
    }

    private function valid_geometry($geometry) {
        return is_array($geometry) && in_array($geometry['type'] ?? '', array('Polygon', 'MultiPolygon'), true) && !empty($geometry['coordinates']);
    }

    private function polygons($geometry) {
        return $geometry['type'] === 'Polygon' ? array($geometry['coordinates'][0]) : array_map(function($poly){ return $poly[0]; }, $geometry['coordinates']);
    }

    private function bbox($poly) {
        $xs = array_column($poly, 0);
        $ys = array_column($poly, 1);
        return array(min($xs), min($ys), max($xs), max($ys));
    }

    private function bbox_intersects($a, $b) {
        return !($a[2] < $b[0] || $a[0] > $b[2] || $a[3] < $b[1] || $a[1] > $b[3]);
    }

    private function polygon_intersects($a, $b) {
        foreach ($a as $p) { if ($this->point_in_polygon($p, $b)) { return true; } }
        foreach ($b as $p) { if ($this->point_in_polygon($p, $a)) { return true; } }
        for ($i = 0; $i < count($a) - 1; $i++) {
            for ($j = 0; $j < count($b) - 1; $j++) {
                if ($this->segments_intersect($a[$i], $a[$i + 1], $b[$j], $b[$j + 1])) { return true; }
            }
        }
        return false;
    }

    private function point_in_polygon($point, $poly) {
        $inside = false; $x = $point[0]; $y = $point[1]; $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $poly[$i][0]; $yi = $poly[$i][1]; $xj = $poly[$j][0]; $yj = $poly[$j][1];
            $intersect = (($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1.0e-12) + $xi);
            if ($intersect) { $inside = !$inside; }
        }
        return $inside;
    }

    private function segments_intersect($p1, $p2, $p3, $p4) {
        $d1 = $this->direction($p3, $p4, $p1); $d2 = $this->direction($p3, $p4, $p2); $d3 = $this->direction($p1, $p2, $p3); $d4 = $this->direction($p1, $p2, $p4);
        return (($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0)) && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0));
    }

    private function direction($a, $b, $c) {
        return (($c[0] - $a[0]) * ($b[1] - $a[1])) - (($b[0] - $a[0]) * ($c[1] - $a[1]));
    }
}
