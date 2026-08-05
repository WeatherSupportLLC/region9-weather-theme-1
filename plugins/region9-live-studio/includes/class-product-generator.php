<?php
defined('ABSPATH') || exit;

/** Authoritative draft-review-publication pipeline for Region 9 products. */
class R9LS_Product_Generator {
    const PRODUCTS = 'r9ls_published_products';
    const HISTORY = 'r9ls_product_history';
    const STATE = 'r9ls_approved_publication_state';
    const WORKSPACE = 'r9ls_forecast_production_workspace';
    const GENERATION_LAST = 'r9ls_product_generation_last';
    const DECISION_HISTORY = 'r9ls_decision_history';
    const PUBLICATION_HISTORY = 'r9ls_publication_history';
    const LATEST_PUBLICATION = 'r9ls_latest_publication';
    const MIGRATION = 'r9ls_17_1_migration';
    const CACHE_PREFIX = 'r9ls_public_product_';
    const HISTORY_LIMIT = 500;
    const DECISION_LIMIT = 300;
    const VERSION = '17.1';
    const COUNTIES = array('Kankakee','Iroquois','Ford','Livingston','DeWitt','Piatt','Champaign','Vermilion','McLean');

    private $rules;
    private $changes;
    private $timing;
    private $audit;

    public function __construct($rules, $changes, $audit = null, $timing = null) {
        $this->rules = $rules;
        $this->changes = $changes;
        $this->audit = $audit;
        $this->timing = $timing ?: new R9LS_Timing_Engine();
    }

    public static function product_definitions() {
        return array(
            'morning-brief'=>array('title'=>'Morning Weather Brief','base'=>'Decision Support Brief'),
            'todays-forecast'=>array('title'=>"Today’s Forecast",'base'=>'Travel'),
            'seven-day-forecast'=>array('title'=>'Seven Day Forecast','base'=>'Forecast Confidence'),
            'headlines'=>array('title'=>'Weather Headlines','base'=>'Severe Weather Risk'),
            'severe-weather-risk'=>array('title'=>'Severe Weather Risk','base'=>'Severe Weather Risk'),
            'threat-breakdown'=>array('title'=>'Threat Breakdown','base'=>'Severe Weather Risk'),
            'storm-timing'=>array('title'=>'Storm Timing','base'=>'Severe Weather Risk'),
            'travel'=>array('title'=>'Travel & Commute','base'=>'Travel'),
            'agriculture'=>array('title'=>'Agriculture','base'=>'Agriculture'),
            'fieldwork'=>array('title'=>'Fieldwork','base'=>'Fieldwork'),
            'spraying'=>array('title'=>'Spraying','base'=>'Spraying'),
            'harvest'=>array('title'=>'Harvest','base'=>'Harvest'),
            'livestock'=>array('title'=>'Livestock','base'=>'Livestock'),
            'outdoor'=>array('title'=>'Outdoor Events','base'=>'Outdoor Events'),
            'schools'=>array('title'=>'School Activities','base'=>'School Activities'),
            'construction'=>array('title'=>'Construction','base'=>'Construction'),
            'forecast-confidence'=>array('title'=>'Forecast Confidence','base'=>'Forecast Confidence'),
            'decision-support-brief'=>array('title'=>'Decision Support Brief','base'=>'Emergency Operations'),
            'watching'=>array('title'=>"What We’re Watching",'base'=>'Severe Weather Risk'),
        );
    }

    /** Sanitize configuration and fail safe to every canonical definition. */
    public static function enabled_product_ids($saved = null) {
        $definitions = self::product_definitions();
        if ($saved === null) {
            $settings = get_option(R9LS_Scheduler::SETTINGS, array());
            $saved = $settings['enabled_products'] ?? array();
        }
        if (!is_array($saved)) {
            $saved = is_string($saved) ? explode(',', $saved) : array();
        }
        $valid = array();
        foreach ($saved as $id) {
            $id = sanitize_key($id);
            if ($id && isset($definitions[$id])) { $valid[$id] = $id; }
        }
        return $valid ? array_values($valid) : array_keys($definitions);
    }

    /** Generate private review rows directly from a successful decision run. */
    public function refresh_workspace_from_decision($decision, $changes = array(), $mode = 'scheduled', $approval_ref = '', $context = array()) {
        $started = microtime(true);
        $definitions = array_intersect_key(self::product_definitions(), array_flip(self::enabled_product_ids()));
        $workspace_before = $this->workspace();
        $published = $this->published_products();
        $products = array();
        $changed_ids = array();
        $actor = $this->actor($context['actor'] ?? $mode);
        $workspace_reference = 'workspace-' . gmdate('YmdHis') . '-' . substr(md5(serialize($decision)), 0, 8);
        $publication_reference = $this->latest_publication_reference();

        foreach ($definitions as $id => $definition) {
            $product_started = microtime(true);
            $previous = $workspace_before['products'][$id] ?? ($published[$id] ?? null);
            $row = $this->build_product($id, $definition, $decision, $previous);
            $same = is_array($previous) && !empty($previous['content_hash']) && hash_equals((string) $previous['content_hash'], $row['content_hash']);
            $previous_version = (int) ($previous['product_version'] ?? 0);
            $row['previous_version'] = $previous_version;
            $row['product_version'] = $same ? max(1, $previous_version) : max(1, $previous_version + 1);
            $row['workspace_state'] = $same ? 'unchanged_available_for_review' : 'changed_pending_review';
            $row['approval_state'] = 'pending_review';
            $row['publication_state'] = 'private';
            $row['grouped_changes'] = $this->changes_for_product($definition['base'], $changes);
            $row['grouped_change_count'] = count($row['grouped_changes']);
            $row['generated_at'] = current_time('mysql');
            $row['updated_at'] = $row['generated_at'];
            $row['generation_duration'] = round(microtime(true) - $product_started, 4);
            $row['actor'] = $actor;
            $row['approval_reference'] = sanitize_text_field($approval_ref);
            $row['source_version_reference'] = sanitize_text_field($context['source_version_reference'] ?? $workspace_reference);
            $row['publication_version'] = $publication_reference;
            $row['publication_reference'] = $publication_reference;
            $row['workspace_reference'] = $workspace_reference;
            if (!$same) { $changed_ids[] = $id; }
            $products[$id] = $row;
            $this->audit_event($same ? 'product_unchanged' : 'product_changed', $same ? 'Product unchanged.' : 'Product changed.', array('product_id'=>$id,'product_version'=>$row['product_version'],'workspace_reference'=>$workspace_reference));
        }

        $duration = round(microtime(true) - $started, 3);
        $workspace = array(
            'schema_version'=>self::VERSION,
            'workspace_reference'=>$workspace_reference,
            'generated_at'=>current_time('mysql'),
            'generation_duration'=>$duration,
            'duration'=>$duration,
            'actor'=>$actor,
            'approval_reference'=>sanitize_text_field($approval_ref),
            'source_version_reference'=>sanitize_text_field($context['source_version_reference'] ?? $workspace_reference),
            'prior_publication_reference'=>$publication_reference,
            'changed_products'=>$changed_ids,
            'approval_state'=>'pending_review',
            'products'=>$products,
        );
        update_option(self::WORKSPACE, $workspace, false);
        update_option(self::GENERATION_LAST, array('schema_version'=>self::VERSION,'generated_at'=>$workspace['generated_at'],'duration'=>$duration,'generation_duration'=>$duration,'changed_products'=>$changed_ids,'workspace_rows'=>count($products),'workspace_reference'=>$workspace_reference,'validation_mode'=>sanitize_key($mode)), false);
        $this->append_decision_history($decision, $changes, $products, $mode, $actor, $duration, $context, $workspace_reference, $publication_reference);
        $this->audit_event('workspace_generated', 'Forecast Production Workspace generated.', array('workspace_reference'=>$workspace_reference,'workspace_rows'=>count($products),'duration'=>$duration));
        return $workspace;
    }

    public function workspace() {
        $workspace = get_option(self::WORKSPACE, array());
        if (!is_array($workspace)) { return array('products'=>array()); }
        if (!isset($workspace['products']) || !is_array($workspace['products'])) { $workspace['products'] = array(); }
        return $workspace;
    }

    public function approve($product_id, $actor = 'system') {
        return $this->review($product_id, 'approved', '', $actor);
    }

    public function reject($product_id, $reason, $actor = 'system') {
        $reason = sanitize_text_field($reason);
        if ($reason === '') { return $this->result(false, 'rejection_reason_required'); }
        return $this->review($product_id, 'rejected', $reason, $actor);
    }

    private function review($product_id, $state, $reason, $actor) {
        $id = $this->valid_product_id($product_id);
        $workspace = $this->workspace();
        if (!$id || empty($workspace['products'][$id])) { return $this->result(false, 'workspace_product_missing'); }
        $row = $workspace['products'][$id];
        $reference = 'approval-' . gmdate('YmdHis') . '-' . substr(md5($id . microtime()), 0, 8);
        $row['approval_state'] = $state;
        $row['approval_actor'] = $this->actor($actor);
        $row['approval_at'] = current_time('mysql');
        $row['approval_reference'] = $reference;
        $row['rejection_reason'] = $state === 'rejected' ? $reason : '';
        if ($state === 'rejected') { $row['publication_state'] = 'blocked_rejected'; }
        $workspace['products'][$id] = $row;
        update_option(self::WORKSPACE, $workspace, false);
        $this->audit_event('product_' . $state, 'Product ' . $state . '.', array('product_id'=>$id,'approval_reference'=>$reference,'reason'=>$reason));
        return $this->result(true, $state, $row);
    }

    public function publish($product_id, $actor = 'system') {
        $id = $this->valid_product_id($product_id);
        $workspace = $this->workspace();
        if (!$id || empty($workspace['products'][$id])) { return $this->blocked('workspace_product_missing', $product_id); }
        $row = $workspace['products'][$id];
        if (($row['approval_state'] ?? '') !== 'approved') { return $this->blocked(($row['approval_state'] ?? '') === 'rejected' ? 'rejected_product' : 'approval_required', $id); }
        if (($row['publication_state'] ?? '') === 'blocked_rejected') { return $this->blocked('rejected_product', $id); }
        $published = $this->published_products();
        $published_before = $published;
        $prior = $published[$id] ?? null;
        if ($prior && (string)($prior['content_hash'] ?? '') === (string)$row['content_hash'] && (int)($prior['product_version'] ?? 0) === (int)$row['product_version']) {
            return $this->blocked('duplicate_publication', $id);
        }
        try {
            $event_id = 'publication-' . gmdate('YmdHis') . '-' . substr(md5($id . microtime()), 0, 8);
            $public = $row;
            $public['approval_state'] = 'approved';
            $public['publication_state'] = 'published';
            $public['published_at'] = current_time('mysql');
            $public['updated_at'] = $public['published_at'];
            $public['publication_version'] = $event_id;
            $public['publication_reference'] = $event_id;
            $public['publication_actor'] = $this->actor($actor);
            unset($public['rejection_reason']);
            $published[$id] = $public;
            update_option(self::PRODUCTS, $published, false);
            $this->append_publication_event('publish', $public, $prior, $actor, $event_id);
            update_option(self::LATEST_PUBLICATION, array('publication_reference'=>$event_id,'product_id'=>$id,'product_version'=>$public['product_version'],'content_hash'=>$public['content_hash'],'published_at'=>$public['published_at'],'actor'=>$public['publication_actor']), false);
            $workspace['products'][$id]['publication_state'] = 'published';
            $workspace['products'][$id]['publication_reference'] = $event_id;
            $workspace['products'][$id]['published_at'] = $public['published_at'];
            update_option(self::WORKSPACE, $workspace, false);
            $this->invalidate(array($id));
            $this->audit_event('product_published', 'Product published.', array('product_id'=>$id,'publication_reference'=>$event_id));
            return $this->result(true, 'published', $public);
        } catch (Exception $e) {
            update_option(self::PRODUCTS, $published_before, false);
            $this->audit_event('publication_failed', 'Publication failed.', array('product_id'=>$id,'error'=>$e->getMessage()), 'error');
            return $this->result(false, 'publication_failed');
        }
    }

    public function rollback($product_id, $publication_reference, $actor = 'system') {
        $id = $this->valid_product_id($product_id);
        $target_ref = sanitize_text_field($publication_reference);
        $history = get_option(self::PUBLICATION_HISTORY, array());
        $target = null;
        foreach ((array)$history as $event) {
            if (($event['product_id'] ?? '') === $id && ($event['publication_reference'] ?? '') === $target_ref && !empty($event['snapshot'])) { $target = $event['snapshot']; break; }
        }
        if (!$id || !$target) {
            $this->audit_event('rollback_failed', 'Rollback failed.', array('product_id'=>$id,'target'=>$target_ref), 'warning');
            return $this->result(false, 'rollback_target_missing');
        }
        $published = $this->published_products();
        $prior = $published[$id] ?? null;
        $event_id = 'rollback-' . gmdate('YmdHis') . '-' . substr(md5($id . microtime()), 0, 8);
        $target['publication_state'] = 'published';
        $target['approval_state'] = 'approved';
        $target['publication_reference'] = $event_id;
        $target['publication_version'] = $event_id;
        $target['rollback_reference'] = $target_ref;
        $target['published_at'] = current_time('mysql');
        $target['updated_at'] = $target['published_at'];
        $published[$id] = $target;
        update_option(self::PRODUCTS, $published, false);
        $this->append_publication_event('rollback', $target, $prior, $actor, $event_id);
        update_option(self::LATEST_PUBLICATION, array('publication_reference'=>$event_id,'product_id'=>$id,'product_version'=>$target['product_version'],'content_hash'=>$target['content_hash'],'published_at'=>$target['published_at'],'actor'=>$this->actor($actor),'rollback_reference'=>$target_ref), false);
        $this->invalidate(array($id));
        $this->audit_event('rollback_completed', 'Rollback completed.', array('product_id'=>$id,'target'=>$target_ref,'publication_reference'=>$event_id));
        return $this->result(true, 'rolled_back', $target);
    }

    public function batch($action, $product_ids, $actor = 'system', $reason = '') {
        $ids = $action === 'approve_all' ? array_keys($this->workspace()['products']) : (array)$product_ids;
        if ($action === 'publish' && !$ids) {
            foreach ($this->workspace()['products'] as $id => $row) { if (($row['approval_state'] ?? '') === 'approved') { $ids[] = $id; } }
        }
        $results = array();
        foreach (array_values(array_unique(array_map('sanitize_key', $ids))) as $id) {
            if ($action === 'approve' || $action === 'approve_all') { $results[$id] = $this->approve($id, $actor); }
            elseif ($action === 'reject') { $results[$id] = $this->reject($id, $reason, $actor); }
            elseif ($action === 'publish') { $results[$id] = $this->publish($id, $actor); }
        }
        return $results;
    }

    /** Backward-compatible explicit approved-state publication used by RC1 integrations. */
    public function generate_from_approved_state($actor = 'system', $approval_ref = '') {
        $state = $this->approved_state();
        $workspace = $this->refresh_workspace_from_decision($state['decision_output'], array(), 'approved_state', $approval_ref, array('actor'=>$actor,'source_version_reference'=>$state['publication_version']));
        foreach (array_keys($workspace['products']) as $id) { $this->approve($id, $actor); $this->publish($id, $actor); }
        return $this->published_products();
    }

    public function approved_state() {
        $state = get_option(self::STATE, array());
        $decision = $state['decision_output'] ?? get_option(R9LS_Scheduler::CACHE, array());
        return wp_parse_args($state, array('publication_version'=>'pub-' . gmdate('Ymd'),'decision_output'=>$decision));
    }

    public static function migrate_17_1($audit = null) {
        $marker = get_option(self::MIGRATION, array());
        if (($marker['version'] ?? '') === self::VERSION && ($marker['status'] ?? '') === 'complete') { return false; }
        $workspace = get_option(self::WORKSPACE, array());
        if (!is_array($workspace)) { $workspace = array(); }
        if (isset($workspace[0]) || (!isset($workspace['products']) && $workspace)) {
            $rows = array();
            foreach ($workspace as $key => $row) { if (is_array($row)) { $id = sanitize_key($row['product_id'] ?? $key); if (isset(self::product_definitions()[$id])) { $rows[$id] = $row; } } }
            $workspace = array('schema_version'=>self::VERSION,'generated_at'=>'','generation_duration'=>null,'products'=>$rows);
        }
        if (!isset($workspace['products']) || !is_array($workspace['products'])) { $workspace['products'] = array(); }
        $workspace['schema_version'] = self::VERSION;
        update_option(self::WORKSPACE, $workspace, false);
        foreach (array(self::DECISION_HISTORY, self::PUBLICATION_HISTORY, self::HISTORY) as $option) {
            $value = get_option($option, array());
            if (!is_array($value)) { update_option($option, array(), false); }
        }
        $marker = array('version'=>self::VERSION,'status'=>'complete','completed_at'=>current_time('mysql'),'preserved_published_products'=>count((array)get_option(self::PRODUCTS, array())));
        update_option(self::MIGRATION, $marker, false);
        if ($audit) { $audit->write('info', 'Migration completed.', array('event'=>'migration_completed','version'=>self::VERSION)); }
        return true;
    }

    private function build_product($id, $definition, $decision, $previous) {
        $base = $decision[$definition['base']] ?? array();
        $risk = $this->rules->region9_risk($base['score'] ?? 0);
        $timing = $this->normalize_timing($base['timing'] ?? '');
        $counties = array_values(array_intersect(self::COUNTIES, (array)($base['affected_counties'] ?? array())));
        $row = array(
            'product_id'=>$id,'title'=>$definition['title'],'product_version'=>1,'previous_version'=>(int)($previous['product_version'] ?? 0),
            'content_hash'=>'','workspace_state'=>'changed_pending_review','approval_state'=>'pending_review','publication_state'=>'private',
            'score'=>(int)($base['score'] ?? 0),'risk'=>$risk,'risk_label'=>$risk['label'],'risk_class'=>$this->status_class($base['score'] ?? 0),
            'confidence'=>(int)($base['confidence'] ?? 0),'affected_counties'=>$counties,'counties'=>$counties,'timing'=>$timing,
            'summary'=>$this->summary($definition['title'], $risk, $base, $timing),
            'discussion'=>$this->discussion($definition['title'], $risk, $base, $timing, $counties),
            'effective_start'=>gmdate('c'),'effective_end'=>gmdate('c',time()+12*HOUR_IN_SECONDS),'source_times'=>array(),
            'history_id'=>'','rollback_reference'=>'',
            'graphic_id'=>absint($previous['graphic_id'] ?? 0),'graphic_url'=>esc_url_raw($previous['graphic_url'] ?? ''),
            'primary_drivers'=>$this->public_drivers($base['primary_drivers'] ?? array()),'secondary_drivers'=>$this->public_drivers($base['secondary_drivers'] ?? array()),
            'county_matrix'=>$this->county_matrix($base),'grouped_changes'=>array(),'grouped_change_count'=>0,
        );
        $row['content_hash'] = $this->content_hash($row);
        return $row;
    }

    public function content_hash($row) {
        $stable = array_intersect_key($row, array_flip(array('product_id','title','score','risk','risk_label','risk_class','confidence','affected_counties','timing','summary','discussion','graphic_id','graphic_url','primary_drivers','secondary_drivers','county_matrix')));
        return hash('sha256', wp_json_encode($this->stable_sort($stable)));
    }

    private function stable_sort($value) {
        if (!is_array($value)) { return $value; }
        if (array_keys($value) !== range(0, count($value) - 1)) { ksort($value); }
        foreach ($value as $key => $item) { $value[$key] = $this->stable_sort($item); }
        return $value;
    }

    private function append_decision_history($decision, $changes, $products, $mode, $actor, $generation_duration, $context, $workspace_reference, $publication_reference) {
        $risk_source = $decision['Severe Weather Risk'] ?? array();
        $confidence_source = $decision['Forecast Confidence'] ?? $risk_source;
        $record = array(
            'record_type'=>'validation_decision','decision_id'=>'decision-' . gmdate('YmdHis') . '-' . substr(md5($workspace_reference), 0, 8),
            'validation_mode'=>sanitize_key($mode),'timestamp'=>current_time('mysql'),'actor'=>$actor,
            'source_health_summary'=>$this->sanitize_source_health($context['source_health_summary'] ?? array()),
            'region9_risk'=>$this->rules->region9_risk($risk_source['score'] ?? 0),'confidence'=>(int)($confidence_source['confidence'] ?? 0),
            'affected_counties'=>array_values(array_intersect(self::COUNTIES, (array)($risk_source['affected_counties'] ?? array()))),
            'timing'=>$this->normalize_timing($risk_source['timing'] ?? ''),'material_change_count'=>count((array)$changes),
            'grouped_product_changes'=>$this->group_changes($products),'generated_workspace_product_ids'=>array_keys($products),
            'generation_duration'=>$generation_duration,'validation_duration'=>(float)($context['validation_duration'] ?? 0),
            'prior_publication_reference'=>$publication_reference,'resulting_workspace_reference'=>$workspace_reference,
        );
        $history = get_option(self::DECISION_HISTORY, array());
        if (!is_array($history)) { $history = array(); }
        array_unshift($history, $record);
        update_option(self::DECISION_HISTORY, array_slice($history, 0, self::DECISION_LIMIT), false);
        $this->audit_event('validation_completed', 'Validation decision recorded.', array('decision_id'=>$record['decision_id'],'mode'=>$record['validation_mode']));
    }

    private function append_publication_event($type, $product, $prior, $actor, $reference) {
        $event = array('event_type'=>$type,'publication_reference'=>$reference,'timestamp'=>current_time('mysql'),'actor'=>$this->actor($actor),'product_id'=>$product['product_id'],'product_version'=>$product['product_version'],'previous_version'=>(int)($prior['product_version'] ?? 0),'content_hash'=>$product['content_hash'],'approval_reference'=>$product['approval_reference'] ?? '','rollback_reference'=>$product['rollback_reference'] ?? '','snapshot'=>$product);
        $history = get_option(self::PUBLICATION_HISTORY, array());
        if (!is_array($history)) { $history = array(); }
        array_unshift($history, $event);
        update_option(self::PUBLICATION_HISTORY, array_slice($history, 0, self::HISTORY_LIMIT), false);
        $legacy = get_option(self::HISTORY, array());
        if (!is_array($legacy)) { $legacy = array(); }
        array_unshift($legacy, array_diff_key($event, array('snapshot'=>true)));
        update_option(self::HISTORY, array_slice($legacy, 0, self::HISTORY_LIMIT), false);
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

    private function group_changes($products) { $out = array(); foreach ($products as $id => $row) { $out[$id] = $row['grouped_changes']; } return $out; }
    private function sanitize_source_health($health) { $out=array(); foreach ((array)$health as $key=>$value) { $out[sanitize_key($key)] = sanitize_key(is_array($value) ? ($value['status'] ?? $value['source_health'] ?? 'unknown') : $value); } return $out; }
    private function valid_product_id($id) { $id=sanitize_key($id); return isset(self::product_definitions()[$id]) ? $id : ''; }
    private function published_products() { $products=get_option(self::PRODUCTS,array()); return is_array($products) ? $products : array(); }
    private function latest_publication_reference() { $latest=get_option(self::LATEST_PUBLICATION,array()); return sanitize_text_field($latest['publication_reference'] ?? ''); }
    private function actor($actor) { return sanitize_text_field($actor ?: 'system'); }
    private function result($success, $code, $product = null) { return array('success'=>(bool)$success,'code'=>sanitize_key($code),'product'=>$product); }
    private function blocked($code, $id) { $this->audit_event('publication_blocked','Publication blocked.',array('product_id'=>sanitize_key($id),'reason'=>$code),'warning'); return $this->result(false,$code); }
    private function audit_event($event, $message, $context=array(), $level='info') { if ($this->audit) { $context=array_merge(array('event'=>$event),$context); $this->audit->write($level,$message,$context); } }
    private function normalize_timing($raw) { return $this->timing->normalize($raw ?: ''); }
    private function public_drivers($drivers) { $map=array('spc'=>'thunderstorm risk','ero'=>'heavy rainfall potential','alerts'=>'active weather alerts','qpf'=>'rainfall forecast'); $out=array(); foreach((array)$drivers as $driver){$out[]=$map[$driver]??preg_replace('/\s+no hazards$/',' quiet',(string)$driver);} return array_values(array_unique($out)); }
    private function summary($title,$risk,$base,$timing) { $confidence=(int)($base['confidence']??0); $time=$timing['local']?:$timing['label']; if(($base['score']??0)<=0){return "$title is quiet for Region 9, with no organized weather hazards identified. Confidence is $confidence%.";} return "$title carries {$risk['label']} risk for Region 9, mainly $time. Confidence is $confidence%, and residents should monitor updates for timing changes."; }
    private function discussion($title,$risk,$base,$timing,$counties) { $drivers=$this->public_drivers($base['primary_drivers']??array()); $driver_text=$drivers?implode(', ',$drivers):'limited organized weather signals'; $county_text=$counties?implode(', ',$counties):'the Region 9 area'; $time=$timing['local']?:$timing['label']; return "$title is generated from normalized Region 9 decision data. The current risk category is {$risk['label']} with an operational score of " . (int)($base['score']??0) . ". The main drivers are $driver_text. Expected timing is $time, with impacts focused on $county_text. Confidence is " . (int)($base['confidence']??0) . '%. Exact placement and timing remain the primary forecast uncertainties; monitor explicitly approved updates.'; }
    public function county_matrix($base) { $scores=$base['county_scores']??array(); $out=array(); foreach(self::COUNTIES as $county){$score=(int)($scores[$county]??0);$risk=$this->rules->region9_risk($score);$out[$county]=array('county'=>$county,'score'=>$score,'rating'=>$risk['label'],'confidence'=>(int)($base['confidence']??0),'drivers'=>$this->public_drivers($base['primary_drivers']??array()),'timing'=>$this->normalize_timing($base['timing']??''),'status_icon'=>$this->status_icon($score),'status_class'=>$this->status_class($score),'summary'=>$county.' has '.$risk['label'].' risk with a score of '.$score.'.');} return $out; }
    private function status_icon($score) { if($score>=75)return '●';if($score>=50)return '▲';if($score>=25)return '◆';return '✓'; }
    private function status_class($score) { if($score>=75)return 'high-risk';if($score>=50)return 'limited';if($score>=25)return 'low';return 'good'; }
    private function invalidate($ids) { foreach((array)$ids as $id){delete_transient(self::CACHE_PREFIX.sanitize_key($id));} delete_transient(self::CACHE_PREFIX.'all'); }
}
