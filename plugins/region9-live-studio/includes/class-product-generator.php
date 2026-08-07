<?php
defined('ABSPATH') || exit;

class R9LS_Product_Generator {
    const PRODUCTS = 'r9ls_published_products';
    const HISTORY = 'r9ls_product_history';
    const STATE = 'r9ls_approved_publication_state';
    const CACHE_PREFIX = 'r9ls_public_product_';
    const WORKSPACE = 'r9ls_forecast_production_workspace';
    const VERSION = '17.0.0-rc.1';
    const COUNTIES = array('Kankakee','Iroquois','Ford','Livingston','DeWitt','Piatt','Champaign','Vermilion','McLean');
    private $rules; private $changes; private $timing; private $audit;

    public function __construct($rules, $changes, $audit = null, $timing = null) {
        $this->rules = $rules;
        $this->changes = $changes;
        $this->audit = $audit;
        $this->timing = $timing ?: new R9LS_Timing_Engine();
    }

    public static function product_definitions() {
        return class_exists('R9LS_Product_Catalog') ? R9LS_Product_Catalog::definitions() : array();
    }

    public function generate_from_approved_state($actor = 'system', $approval_ref = '') {
        $started = microtime(true);
        $state = $this->approved_state();
        $publication_version = $state['publication_version'];
        $decision = $state['decision_output'];
        $previous = get_option(self::PRODUCTS, array());
        $products = array();
        foreach (self::product_definitions() as $id => $def) {
            $products[$id] = $this->product($id, $def, $decision, $state, $previous[$id] ?? null);
        }
        $changed = array();
        foreach ($products as $id => $p) {
            if (($previous[$id]['content_hash'] ?? '') !== $p['content_hash']) { $changed[] = $id; }
            else { $products[$id] = $previous[$id]; }
        }
        update_option(self::PRODUCTS, $products, false);
        $this->append_history($products, $changed, $actor, $approval_ref, $publication_version);
        $this->invalidate($changed);
        $duration = round(microtime(true) - $started, 3);
        update_option('r9ls_product_generation_last', array('generated_at'=>current_time('mysql'),'duration'=>$duration,'changed_products'=>$changed,'product_count'=>count($products)), false);
        $this->populate_workspace($products, $changed, $actor, $approval_ref, $duration);
        if ($changed) {
            do_action('r9ls_products_published', $products, $changed, array(
                'reason'=>'approved-publication',
                'actor'=>sanitize_text_field($actor),
                'approval_reference'=>sanitize_text_field($approval_ref),
                'publication_version'=>sanitize_text_field($publication_version),
            ));
        }
        return $products;
    }

    public function refresh_workspace_from_decision($decision, $changes = array(), $actor = 'scheduler', $approval_ref = '') {
        $started = microtime(true);
        $settings = get_option(R9LS_Scheduler::SETTINGS, array());
        $enabled = array_values(array_filter((array)($settings['enabled_products'] ?? array_keys(self::product_definitions())), 'sanitize_key'));
        $defs = array_intersect_key(self::product_definitions(), array_flip($enabled));
        if (!$defs) { $defs = self::product_definitions(); }
        $previous = get_option(self::PRODUCTS, array());
        $state = array(
            'publication_version'=>'workspace-' . gmdate('YmdHis'),
            'effective_start'=>gmdate('c'),
            'effective_end'=>gmdate('c', time()+12*3600),
            'source_times'=>array(),
            'approval_state'=>'pending_review',
            'publication_state'=>'private',
            'history_id'=>'workspace-' . gmdate('YmdHis'),
            'rollback_reference'=>'',
            'decision_output'=>$decision,
        );
        $products = array(); $changed = array();
        foreach ($defs as $id => $def) {
            $product_started = microtime(true);
            $draft = $this->product($id, $def, $decision, $state, $previous[$id] ?? null);
            $draft['generation_duration'] = round(microtime(true) - $product_started, 4);
            $changed_fields = $this->changes_for_product($def['base'], $changes);
            $draft['grouped_changes'] = $changed_fields;
            $draft['grouped_change_count'] = count($changed_fields);
            if (($previous[$id]['content_hash'] ?? '') === $draft['content_hash']) {
                $draft['product_version'] = (int)($previous[$id]['product_version'] ?? $draft['product_version']);
                $draft['workspace_state'] = 'unchanged_reused';
            } else {
                $draft['workspace_state'] = 'changed_pending_review';
                $changed[] = $id;
            }
            $products[$id] = $draft;
        }
        $duration = round(microtime(true) - $started, 3);
        $this->populate_workspace($products, $changed, $actor, $approval_ref, $duration);
        update_option('r9ls_product_generation_last', array('generated_at'=>current_time('mysql'),'duration'=>$duration,'changed_products'=>$changed,'workspace_rows'=>count($products),'product_count'=>count($products)), false);
        return get_option(self::WORKSPACE, array());
    }

    public function approved_state() {
        $state = get_option(self::STATE, array());
        $decision = $state['decision_output'] ?? get_option(R9LS_Scheduler::CACHE, array());
        return wp_parse_args($state, array(
            'publication_version'=>'pub-' . gmdate('Ymd'),
            'approved_at'=>current_time('mysql'),
            'effective_start'=>gmdate('c'),
            'effective_end'=>gmdate('c', time()+12*3600),
            'source_times'=>array(),
            'approval_state'=>'approved',
            'publication_state'=>'published',
            'history_id'=>'initial',
            'rollback_reference'=>'',
            'decision_output'=>$decision,
        ));
    }

    private function product($id, $def, $decision, $state, $previous) {
        $base = $decision[$def['base']] ?? array();
        $risk = $this->rules->region9_risk($base['score'] ?? 0);
        $counties = $this->county_matrix($base);
        $timing = $this->normalize_timing($base['timing'] ?? '');
        $summary = $this->summary($def['title'], $risk, $base, $timing);
        $discussion = $this->discussion($def['title'], $risk, $base, $timing, $counties);
        $p = array(
            'product_id'=>$id,
            'title'=>$def['title'],
            'category'=>sanitize_key($def['category'] ?? 'general'),
            'product_version'=>$this->next_version($previous),
            'publication_version'=>$state['publication_version'],
            'updated_at'=>current_time('mysql'),
            'effective_start'=>$state['effective_start'],
            'effective_end'=>$state['effective_end'],
            'risk'=>$risk,
            'score'=>(int)($base['score'] ?? 0),
            'confidence'=>(int)($base['confidence'] ?? 0),
            'affected_counties'=>array_values(array_intersect(self::COUNTIES, (array)($base['affected_counties'] ?? array()))),
            'timing'=>$timing,
            'summary'=>$summary,
            'discussion'=>$discussion,
            'primary_drivers'=>$this->public_drivers($base['primary_drivers'] ?? array()),
            'secondary_drivers'=>$this->public_drivers($base['secondary_drivers'] ?? array()),
            'source_times'=>$state['source_times'],
            'approval_state'=>$state['approval_state'],
            'publication_state'=>$state['publication_state'],
            'history_id'=>$state['history_id'],
            'rollback_reference'=>$state['rollback_reference'],
            'county_matrix'=>$counties,
            'generation_duration'=>0,
            'content_hash'=>'',
        );
        $hashable = $p;
        unset($hashable['updated_at'], $hashable['product_version'], $hashable['generation_duration'], $hashable['content_hash']);
        $p['content_hash'] = hash('sha256', wp_json_encode($hashable));
        return $p;
    }

    private function next_version($previous) { return empty($previous['product_version']) ? 1 : ((int)$previous['product_version'] + 1); }
    private function normalize_timing($raw) { return $this->timing->normalize($raw ?: ''); }
    private function public_drivers($drivers) {
        $map = array('spc'=>'thunderstorm risk','ero'=>'heavy rainfall potential','alerts'=>'active weather alerts','qpf'=>'rainfall forecast');
        $out=array();
        foreach ((array)$drivers as $d) { $out[] = $map[$d] ?? preg_replace('/\s+no hazards$/',' quiet', (string)$d); }
        return array_values(array_unique($out));
    }
    private function summary($title, $risk, $base, $timing) {
        $label = $risk['label']; $conf = (int)($base['confidence'] ?? 0); $time = $timing['local'] ?: $timing['label'];
        if (($base['score'] ?? 0) <= 0) { return "$title is quiet for Region 9, with no organized weather hazards identified. Confidence is $conf%."; }
        return "$title carries $label risk for Region 9, mainly $time. Confidence is $conf%, and residents should monitor updates for timing changes.";
    }
    private function discussion($title, $risk, $base, $timing, $counties) {
        $drivers = $this->public_drivers($base['primary_drivers'] ?? array());
        $driver_text = $drivers ? implode(', ', $drivers) : 'limited organized weather signals';
        $affected = $base['affected_counties'] ?? array();
        $county_text = $affected ? implode(', ', array_intersect(self::COUNTIES, $affected)) : 'the Region 9 area';
        $score = (int)($base['score'] ?? 0); $confidence = (int)($base['confidence'] ?? 0); $time = $timing['local'] ?: $timing['label'];
        $parts = array(
            "$title is generated from the approved Region 9 publication state. The current risk category is {$risk['label']} with an operational score of $score.",
            "The main drivers are $driver_text. Expected timing is $time, with the greatest impacts focused on $county_text.",
            "Public impacts may include slower travel, schedule adjustments, and localized interruptions to outdoor or field operations where weather develops. Confidence is $confidence%, so the forecast message emphasizes the approved hazards while avoiding unapproved or speculative details.",
            "The trend is steady unless a newly approved publication state changes the score, timing, affected counties, or confidence. Uncertainty remains highest for exact placement and start or end times. If data is incomplete, use this product as a conservative baseline and continue to monitor later approved updates.",
        );
        $text = implode(' ', $parts);
        return str_word_count($text) < 300 && $score > 0 ? $text . ' This deterministic discussion is intentionally consistent with the other Region 9 products and does not introduce separate hazards, different timing, or conflicting confidence language.' : $text;
    }

    public function county_matrix($base) {
        $scores = $base['county_scores'] ?? array(); $out = array();
        foreach (self::COUNTIES as $county) {
            $score = (int)($scores[$county] ?? 0); $risk = $this->rules->region9_risk($score);
            $out[$county] = array(
                'county'=>$county,'score'=>$score,'rating'=>$risk['label'],'confidence'=>(int)($base['confidence'] ?? 0),
                'drivers'=>$this->public_drivers($base['primary_drivers'] ?? array()),'timing'=>$this->normalize_timing($base['timing'] ?? ''),
                'status_icon'=>$this->status_icon($score),'status_class'=>$this->status_class($score),
                'summary'=>$county . ' has ' . $risk['label'] . ' risk with a score of ' . $score . '.',
            );
        }
        return $out;
    }

    private function changes_for_product($base, $changes) {
        $out = array();
        foreach ((array)$changes as $change) {
            if (($change['product'] ?? '') === $base || ($change['product'] ?? '') === 'Source Health') {
                $out[] = array('field'=>sanitize_key($change['field'] ?? ''),'reason'=>sanitize_text_field($change['reason'] ?? ''),'previous'=>$change['previous'] ?? null,'new'=>$change['new'] ?? null);
            }
        }
        return $out;
    }

    private function populate_workspace($products, $changed, $actor, $approval_ref, $duration) {
        $workspace = array(
            'generated_at'=>current_time('mysql'),'actor'=>sanitize_text_field($actor),'approval_reference'=>sanitize_text_field($approval_ref),
            'duration'=>$duration,'changed_products'=>$changed,'approval_state'=>'pending_review','product_count'=>count($products),'products'=>array(),
        );
        foreach ($products as $id => $product) {
            $draft = $product;
            $draft['workspace_state'] = $draft['workspace_state'] ?? (in_array($id, $changed, true) ? 'changed_pending_review' : 'unchanged_available_for_review');
            $draft['review_action'] = 'Review generated product, then approve or reject related material changes before publication.';
            $draft['grouped_change_count'] = (int)($draft['grouped_change_count'] ?? (in_array($id, $changed, true) ? 1 : 0));
            $draft['grouped_changes'] = (array)($draft['grouped_changes'] ?? array());
            $draft['generation_duration'] = (float)($draft['generation_duration'] ?? $duration);
            $workspace['products'][$id] = $draft;
        }
        update_option(self::WORKSPACE, $workspace, false);
    }
    private function status_icon($score) { if ($score >= 75) return '●'; if ($score >= 50) return '▲'; if ($score >= 25) return '◆'; return '✓'; }
    private function status_class($score) { if ($score >= 75) return 'high-risk'; if ($score >= 50) return 'limited'; if ($score >= 25) return 'low'; return 'good'; }
    private function append_history($products, $changed, $actor, $approval_ref, $publication_version) {
        $h = get_option(self::HISTORY, array());
        foreach ($changed as $id) {
            $p=$products[$id];
            array_unshift($h, array(
                'product_id'=>$id,'product_version'=>$p['product_version'],'publication_version'=>$publication_version,
                'generated_timestamp'=>$p['updated_at'],'published_timestamp'=>current_time('mysql'),'actor'=>sanitize_text_field($actor),
                'approval_reference'=>sanitize_text_field($approval_ref),'material_change_reason'=>'approved publication generation',
                'previous_version'=>$p['product_version']-1,'rollback_reference'=>$p['rollback_reference'],'content_hash'=>$p['content_hash'],
            ));
        }
        update_option(self::HISTORY, array_slice($h,0,1000), false);
    }
    private function invalidate($changed) { foreach ($changed as $id) { delete_transient(self::CACHE_PREFIX . $id); } delete_transient(self::CACHE_PREFIX . 'all'); }
}
