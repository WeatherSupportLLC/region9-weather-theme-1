<?php
defined('ABSPATH') || exit;

class R9LS_Rule_Engine {
    private $gis;
    private $audit;
    private $products = array('Travel','Agriculture','Fieldwork','Spraying','Harvest','Livestock','Outdoor Events','School Activities','Construction','Utilities','Emergency Operations','Forecast Confidence','Severe Weather Risk');

    public function __construct($gis, $audit) {
        $this->gis = $gis;
        $this->audit = $audit;
    }

    public function evaluate_all($sources) {
        $geo = array(
            'spc' => $this->gis->intersect_source($sources['spc_day1'] ?? array('status' => 'failure')),
            'ero' => $this->gis->intersect_source($sources['wpc_day1_ero'] ?? array('status' => 'failure')),
            'alerts' => $this->gis->intersect_source($sources['nws_alerts'] ?? array('status' => 'failure')),
            'qpf' => $sources['wpc_day1_qpf'] ?? array('status' => 'failure'),
        );
        $out = array();
        foreach ($this->products as $product) {
            $out[$product] = $this->evaluate_product($product, $sources, $geo);
        }
        return $out;
    }

    public function evaluate_product($product, $sources, $geo) {
        $rules = $this->rules($product);
        $score = 0; $confidence = 100; $primary = array(); $secondary = array(); $trace = array(); $county_scores = array_fill_keys($this->gis->county_names(), 0);
        foreach (array('spc','ero','alerts') as $source_key) {
            $result = $geo[$source_key];
            if ($result['status'] !== 'ok') {
                $confidence -= 20;
                $trace[] = array('source' => $source_key, 'effect' => 'confidence -20', 'reason' => 'source unavailable or malformed');
                continue;
            }
            $weight = $rules['weights'][$source_key] ?? 0;
            $source_score = $result['highest_risk'] * $weight;
            $score += $source_score;
            foreach ($result['county_risks'] as $county => $risk) {
                $county_scores[$county] = min(100, $county_scores[$county] + ($risk * $weight));
            }
            if ($result['highest_risk'] > 0) { $primary[] = $source_key; } else { $secondary[] = $source_key . ' no hazards'; }
            $trace[] = array('source' => $source_key, 'risk' => $result['highest_risk'], 'weight' => $weight, 'score_added' => $source_score);
        }
        $qpf = $geo['qpf'];
        if (($qpf['status'] ?? 'failure') !== 'healthy') {
            $confidence -= 10;
            $trace[] = array('source' => 'qpf', 'effect' => 'confidence -10', 'reason' => 'national QPF unavailable');
        } else {
            $qpf_weight = $rules['weights']['qpf'] ?? 4;
            foreach ((array)($qpf['county_precipitation'] ?? array()) as $county => $amount) {
                if ($amount !== null) { $county_scores[$county] = min(100, $county_scores[$county] + ((float)$amount * $qpf_weight)); }
            }
            $max_qpf = empty($qpf['county_precipitation']) ? 0 : max(array_map('floatval', array_filter($qpf['county_precipitation'], 'is_numeric')) ?: array(0));
            $added = min(20, $max_qpf * $qpf_weight); $score += $added;
            $trace[] = array('source' => 'qpf', 'max_inches' => $max_qpf, 'weight' => $qpf_weight, 'score_added' => $added);
        }
        $score = max($score, empty($county_scores) ? 0 : max($county_scores));
        $score = $this->clamp(apply_filters('r9ls_product_score', $score, $product, $sources, $geo));
        $confidence = $this->clamp(apply_filters('r9ls_product_confidence', $confidence, $product, $sources, $geo));
        return array(
            'score' => $score,
            'rating' => $this->rating($product, $score),
            'confidence' => $confidence,
            'primary_drivers' => array_values(array_unique($primary)),
            'secondary_drivers' => array_values(array_unique($secondary)),
            'affected_counties' => $this->affected_counties($county_scores),
            'timing' => $this->timing($sources),
            'controlled_summary' => $this->summary($product, $score, $confidence),
            'rule_trace' => $trace,
            'county_scores' => $county_scores,
            'region9_score_logic' => 'Region 9 score is the maximum affected county score so the public region risk reflects the highest impacted county.',
        );
    }

    public function region9_risk($score) {
        if ($score <= 0) { return array('level' => 0, 'label' => 'None'); }
        if ($score < 25) { return array('level' => 1, 'label' => 'Low'); }
        if ($score < 50) { return array('level' => 2, 'label' => 'Limited'); }
        if ($score < 75) { return array('level' => 3, 'label' => 'Elevated'); }
        return array('level' => 4, 'label' => 'Significant');
    }

    private function rules($product) {
        $defaults = array('weights' => array('spc' => 14, 'ero' => 10, 'alerts' => 12, 'qpf' => 4));
        $product_rules = array(
            'Spraying' => array('weights' => array('spc' => 10, 'ero' => 18, 'alerts' => 18)),
            'Emergency Operations' => array('weights' => array('spc' => 20, 'ero' => 16, 'alerts' => 20)),
            'Severe Weather Risk' => array('weights' => array('spc' => 22, 'ero' => 8, 'alerts' => 18)),
            'Forecast Confidence' => array('weights' => array('spc' => 6, 'ero' => 6, 'alerts' => 6)),
        );
        return apply_filters('r9ls_product_rules', $product_rules[$product] ?? $defaults, $product);
    }

    private function rating($product, $score) {
        if ($product === 'Travel') {
            if ($score < 25) { return 'Good'; }
            if ($score < 50) { return 'Caution'; }
            if ($score < 75) { return 'Difficult'; }
            return 'Dangerous';
        }
        return $this->region9_risk($score)['label'];
    }

    private function affected_counties($scores) {
        return array_keys(array_filter($scores, function($score) { return $score > 0; }));
    }

    private function timing($sources) {
        $times = array();
        foreach ($sources as $source) {
            foreach (($source['hazards'] ?? array()) as $hazard) {
                if (!empty($hazard['timing'])) { $times[] = sanitize_text_field($hazard['timing']); }
            }
        }
        return $times ? implode('; ', array_unique($times)) : 'No hazardous timing identified.';
    }

    private function summary($product, $score, $confidence) {
        return sprintf('%s operational score %d with %d%% confidence. Automatic publishing remains disabled pending approval.', $product, $score, $confidence);
    }

    private function clamp($value) {
        return max(0, min(100, (int) round($value)));
    }
}
