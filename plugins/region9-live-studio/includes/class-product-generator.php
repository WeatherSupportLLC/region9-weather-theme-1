<?php
defined('ABSPATH') || exit;

class R9LS_Product_Generator {
    const PRODUCTS = 'r9ls_published_products';
    const HISTORY = 'r9ls_product_history';
    const STATE = 'r9ls_approved_publication_state';
    const CACHE_PREFIX = 'r9ls_public_product_';
    const WORKSPACE = 'r9ls_forecast_production_workspace';
    const VERSION = '17.0.0-rc.2';
    const COUNTIES = array('Kankakee','Iroquois','Ford','Livingston','DeWitt','Piatt','Champaign','Vermilion','McLean');
    private $rules; private $changes; private $timing; private $audit; private $graphics;

    public function __construct($rules, $changes, $audit = null, $timing = null, $graphics = null) {
        $this->rules = $rules;
        $this->changes = $changes;
        $this->audit = $audit;
        $this->timing = $timing ?: new R9LS_Timing_Engine();
        $this->graphics = $graphics ?: (class_exists('R9LS_Graphics_Engine') ? new R9LS_Graphics_Engine($audit) : null);
    }

    public static function product_definitions() { return array(
        'morning-brief'=>array('title'=>'Morning Weather Brief','base'=>'Decision Support Brief'),
        'todays-forecast'=>array('title'=>"Today’s Forecast",'base'=>'Travel'),
        'seven-day-forecast'=>array('title'=>'7-Day Forecast','base'=>'Forecast Confidence'),
        'headlines'=>array('title'=>'Weather Headlines','base'=>'Severe Weather Risk'),
        'severe-weather-risk'=>array('title'=>'Severe Weather Outlook','base'=>'Severe Weather Risk'),
        'threat-breakdown'=>array('title'=>'Severe Weather Threat Breakdown','base'=>'Severe Weather Risk'),
        'storm-timing'=>array('title'=>'Severe Weather Timeline','base'=>'Severe Weather Risk'),
        'travel'=>array('title'=>'Travel & Commute','base'=>'Travel'),
        'agriculture'=>array('title'=>'Agriculture Weather Outlook','base'=>'Agriculture'),
        'fieldwork'=>array('title'=>'Fieldwork Outlook','base'=>'Fieldwork'),
        'spraying'=>array('title'=>'Spray Conditions','base'=>'Spraying'),
        'harvest'=>array('title'=>'Harvest Outlook','base'=>'Harvest'),
        'livestock'=>array('title'=>'Livestock Weather Outlook','base'=>'Livestock'),
        'outdoor'=>array('title'=>'Outdoor Events','base'=>'Outdoor Events'),
        'schools'=>array('title'=>'School Activities','base'=>'School Activities'),
        'construction'=>array('title'=>'Construction & Outdoor Work','base'=>'Construction'),
        'forecast-confidence'=>array('title'=>'Forecast Confidence','base'=>'Forecast Confidence'),
        'decision-support-brief'=>array('title'=>'Decision Support Brief','base'=>'Emergency Operations'),
        'watching'=>array('title'=>"What We’re Watching",'base'=>'Severe Weather Risk'),
        'tornado-risk'=>array('title'=>'Tornado Risk Outlook','base'=>'Severe Weather Risk'),
        'damaging-wind'=>array('title'=>'Damaging Wind Outlook','base'=>'Severe Weather Risk'),
        'large-hail'=>array('title'=>'Large Hail Outlook','base'=>'Severe Weather Risk'),
        'lightning-risk'=>array('title'=>'Lightning Risk Outlook','base'=>'Severe Weather Risk'),
        'flood-heavy-rain'=>array('title'=>'Flash Flood / Heavy Rain Outlook','base'=>'Emergency Operations'),
        'forecast-rainfall'=>array('title'=>'Forecast Rainfall','base'=>'Emergency Operations'),
        'weekly-hazards'=>array('title'=>'Weekly Weather Hazards','base'=>'Forecast Confidence'),
        'rural-travel'=>array('title'=>'Rural Travel Outlook','base'=>'Travel'),
        'outdoor-planner'=>array('title'=>'Outdoor Event Planner','base'=>'Outdoor Events')
    ); }

    public function generate_from_approved_state($actor = 'system', $approval_ref = '', $force_graphics = true) {
        $started = microtime(true);
        $state = $this->approved_state();
        $publication_version = $state['publication_version'];
        $decision = $state['decision_output'];
        $previous = get_option(self::PRODUCTS, array());
        $products = array();
        foreach (self::product_definitions() as $id => $def) {
            $p = $this->product($id, $def, $decision, $state, $previous[$id] ?? null);
            if ($this->graphics) { $p = $this->graphics->render_product($p, $force_graphics); }
            $products[$id] = $p;
        }
        $changed = array();
        foreach ($products as $id => $p) {
            $missing_graphic = $this->graphics && empty($previous[$id]['graphic_url']);
            if (($previous[$id]['content_hash'] ?? '') !== $p['content_hash'] || $missing_graphic) { $changed[] = $id; }
            elseif (!$force_graphics) { $products[$id] = array_merge($p, array_intersect_key($previous[$id], array_flip(array('graphic_url','graphic_path','graphic_hash','graphic_generated_at','graphic_renderer','forecaster','discussion_state_hash')))); }
        }
        update_option(self::PRODUCTS, $products, false);
        $this->append_history($products, $changed, $actor, $approval_ref, $publication_version);
        $this->invalidate($changed);
        $duration = round(microtime(true)-$started,3);
        update_option('r9ls_product_generation_last', array('generated_at'=>current_time('mysql'),'duration'=>$duration,'changed_products'=>$changed), false);
        $this->populate_workspace($products, $changed, $actor, $approval_ref, $duration);
        return $products;
    }

    public function refresh_workspace_from_decision($decision, $changes = array(), $actor = 'scheduler', $approval_ref = '', $render_graphics = false, $automatic_publish = false) {
        $started = microtime(true);
        $settings = get_option(R9LS_Scheduler::SETTINGS, array());
        $enabled = array_values(array_filter((array)($settings['enabled_products'] ?? array_keys(self::product_definitions())), 'sanitize_key'));
        $defs = array_intersect_key(self::product_definitions(), array_flip($enabled));
        if (!$defs) { $defs = self::product_definitions(); }
        $previous = get_option(self::PRODUCTS, array());
        $state = array(
            'publication_version'=>'workspace-' . gmdate('YmdHis'),
            'effective_start'=>gmdate('c'),'effective_end'=>gmdate('c', time()+12*3600),
            'source_times'=>array(),'approval_state'=>$automatic_publish?'approved':'pending_review',
            'publication_state'=>$automatic_publish?'published':'private',
            'history_id'=>'workspace-' . gmdate('YmdHis'),'rollback_reference'=>'','decision_output'=>$decision
        );
        $products = array(); $changed = array();
        foreach ($defs as $id => $def) {
            $product_started = microtime(true);
            $draft = $this->product($id, $def, $decision, $state, $previous[$id] ?? null);
            $changed_fields = $this->changes_for_product($def['base'], $changes);
            $draft['grouped_changes'] = $changed_fields;
            $draft['grouped_change_count'] = count($changed_fields);
            $is_changed = (($previous[$id]['content_hash'] ?? '') !== $draft['content_hash']);
            if ($render_graphics && $this->graphics) { $draft = $this->graphics->render_product($draft, $is_changed); }
            elseif (!$is_changed && !empty($previous[$id]['graphic_url'])) {
                foreach (array('graphic_url','graphic_path','graphic_hash','graphic_generated_at','graphic_renderer','forecaster','discussion_state_hash') as $k) if (isset($previous[$id][$k])) $draft[$k]=$previous[$id][$k];
            }
            $draft['generation_duration'] = round(microtime(true) - $product_started, 4);
            if ($is_changed) { $draft['workspace_state'] = $automatic_publish ? 'changed_auto_published' : 'changed_pending_review'; $changed[] = $id; }
            else { $draft['product_version'] = (int)($previous[$id]['product_version'] ?? $draft['product_version']); $draft['workspace_state'] = 'unchanged_reused'; }
            $products[$id] = $draft;
        }
        $duration = round(microtime(true) - $started, 3);
        $this->populate_workspace($products, $changed, $actor, $approval_ref, $duration);
        if ($automatic_publish && $render_graphics) {
            update_option(self::PRODUCTS, $products, false);
            $this->append_history($products, $changed, $actor, $approval_ref, $state['publication_version']);
            $this->invalidate($changed);
        }
        update_option('r9ls_product_generation_last', array('generated_at'=>current_time('mysql'),'duration'=>$duration,'changed_products'=>$changed,'workspace_rows'=>count($products),'graphics_rendered'=>(bool)$render_graphics,'automatic_publish'=>(bool)$automatic_publish), false);
        return get_option(self::WORKSPACE, array());
    }

    public function approved_state() {
        $state = get_option(self::STATE, array());
        $decision = $state['decision_output'] ?? get_option(R9LS_Scheduler::CACHE, array());
        return wp_parse_args($state, array('publication_version'=>'pub-' . gmdate('Ymd'), 'approved_at'=>current_time('mysql'), 'effective_start'=>gmdate('c'), 'effective_end'=>gmdate('c', time()+12*3600), 'source_times'=>array(), 'approval_state'=>'approved', 'publication_state'=>'published', 'history_id'=>'initial', 'rollback_reference'=>'', 'decision_output'=>$decision));
    }

    private function product($id, $def, $decision, $state, $previous) {
        $base = $decision[$def['base']] ?? array();
        $risk = $this->rules->region9_risk($base['score'] ?? 0);
        $counties = $this->county_matrix($base);
        $timing = $this->normalize_timing($base['timing'] ?? '');
        $summary = $this->summary($def['title'], $risk, $base, $timing);
        $discussion = $this->discussion($def['title'], $risk, $base, $timing, $counties);
        $p = array(
            'product_id'=>$id,'title'=>$def['title'],'product_version'=>$this->next_version($previous),'publication_version'=>$state['publication_version'],
            'updated_at'=>current_time('mysql'),'effective_start'=>$state['effective_start'],'effective_end'=>$state['effective_end'],
            'risk'=>$risk,'score'=>(int)($base['score'] ?? 0),'confidence'=>(int)($base['confidence'] ?? 0),
            'affected_counties'=>array_values(array_intersect(self::COUNTIES, (array)($base['affected_counties'] ?? array()))),
            'timing'=>$timing,'summary'=>$summary,'discussion'=>$discussion,
            'primary_drivers'=>$this->public_drivers($base['primary_drivers'] ?? array()),'secondary_drivers'=>$this->public_drivers($base['secondary_drivers'] ?? array()),
            'source_times'=>$state['source_times'],'approval_state'=>$state['approval_state'],'publication_state'=>$state['publication_state'],
            'history_id'=>$state['history_id'],'rollback_reference'=>$state['rollback_reference'],'county_matrix'=>$counties,
            'forecaster'=>'NEAL','generation_duration'=>0,'content_hash'=>''
        );
        $hashable = $p; unset($hashable['updated_at'],$hashable['product_version'],$hashable['generation_duration'],$hashable['content_hash']);
        $p['content_hash'] = hash('sha256', wp_json_encode($hashable));
        $p['discussion_state_hash'] = hash('sha256', wp_json_encode(array('content_hash'=>$p['content_hash'],'discussion'=>$p['discussion'])));
        return $p;
    }

    private function next_version($previous) { return empty($previous['product_version']) ? 1 : ((int)$previous['product_version'] + 1); }
    private function normalize_timing($raw) { return $this->timing->normalize($raw ?: ''); }
    private function public_drivers($drivers) { $map=array('spc'=>'thunderstorm risk','ero'=>'heavy rainfall potential','alerts'=>'active weather alerts','qpf'=>'rainfall forecast'); $out=array(); foreach((array)$drivers as $d){$out[]=$map[$d]??preg_replace('/\s+no hazards$/',' quiet',(string)$d);} return array_values(array_unique($out)); }
    private function summary($title,$risk,$base,$timing) { $label=$risk['label']; $conf=(int)($base['confidence']??0); $time=$timing['local']?:$timing['label']; if(($base['score']??0)<=0)return "$title is quiet for Region 9, with no organized weather hazards identified. Confidence is $conf%."; return "$title carries $label risk for Region 9, mainly $time. Confidence is $conf%, and residents should monitor updates for timing changes."; }
    private function discussion($title,$risk,$base,$timing,$counties) {
        $drivers=$this->public_drivers($base['primary_drivers']??array()); $driver_text=$drivers?implode(', ',$drivers):'limited organized weather signals';
        $affected=$base['affected_counties']??array(); $county_text=$affected?implode(', ',array_intersect(self::COUNTIES,$affected)):'the Region 9 area';
        $score=(int)($base['score']??0); $confidence=(int)($base['confidence']??0); $time=$timing['local']?:$timing['label'];
        if ($score <= 0) return "$title remains in a routine monitoring posture across $county_text. No organized high-impact weather signal is currently identified. Confidence is $confidence%. The forecast will be regenerated on the routine six-hour cycle, or sooner if a meaningful weather change is detected.";
        $parts=array();
        $parts[]="$title is currently categorized as {$risk['label']} for Region 9, with the most relevant period $time.";
        $parts[]="The main forecast signals are $driver_text, with the greatest impacts focused on $county_text.";
        $parts[]="The current operational score is $score and forecast confidence is $confidence%. Planning should account for changing timing, coverage, and local intensity where weather develops.";
        $parts[]="This discussion and the associated Region 9 graphic are generated from the same normalized forecast state so the risk, timing, counties, confidence, and primary message remain synchronized.";
        return implode(' ',$parts);
    }

    public function county_matrix($base) { $scores=$base['county_scores']??array(); $out=array(); foreach(self::COUNTIES as $county){$score=(int)($scores[$county]??0);$risk=$this->rules->region9_risk($score);$out[$county]=array('county'=>$county,'score'=>$score,'rating'=>$risk['label'],'confidence'=>(int)($base['confidence']??0),'drivers'=>$this->public_drivers($base['primary_drivers']??array()),'timing'=>$this->normalize_timing($base['timing']??''),'status_icon'=>$this->status_icon($score),'status_class'=>$this->status_class($score),'summary'=>$county.' has '.$risk['label'].' risk with a score of '.$score.'.');} return $out; }

    private function changes_for_product($base,$changes){$out=array();foreach((array)$changes as $change){if(($change['product']??'')===$base||($change['product']??'')==='Source Health'){$out[]=array('field'=>sanitize_key($change['field']??''),'reason'=>sanitize_text_field($change['reason']??''),'previous'=>$change['previous']??null,'new'=>$change['new']??null);}}return $out;}

    private function populate_workspace($products,$changed,$actor,$approval_ref,$duration){
        $workspace=array('generated_at'=>current_time('mysql'),'actor'=>sanitize_text_field($actor),'approval_reference'=>sanitize_text_field($approval_ref),'duration'=>$duration,'changed_products'=>$changed,'approval_state'=>'pending_review','products'=>array());
        foreach($products as $id=>$product){$draft=$product;$draft['workspace_state']=$draft['workspace_state']??(in_array($id,$changed,true)?'changed_pending_review':'unchanged_available_for_review');$draft['review_action']='Review generated product, then approve or reject related material changes before publication.';$draft['grouped_change_count']=(int)($draft['grouped_change_count']??(in_array($id,$changed,true)?1:0));$draft['grouped_changes']=(array)($draft['grouped_changes']??array());$draft['generation_duration']=(float)($draft['generation_duration']??$duration);$workspace['products'][$id]=$draft;}
        update_option(self::WORKSPACE,$workspace,false);
    }
    private function status_icon($score){if($score>=75)return'●';if($score>=50)return'▲';if($score>=25)return'◆';return'✓';}
    private function status_class($score){if($score>=75)return'high-risk';if($score>=50)return'limited';if($score>=25)return'low';return'good';}
    private function append_history($products,$changed,$actor,$approval_ref,$publication_version){$h=get_option(self::HISTORY,array());foreach($changed as $id){$p=$products[$id];array_unshift($h,array('product_id'=>$id,'product_version'=>$p['product_version'],'publication_version'=>$publication_version,'generated_timestamp'=>$p['updated_at'],'published_timestamp'=>current_time('mysql'),'actor'=>sanitize_text_field($actor),'approval_reference'=>sanitize_text_field($approval_ref),'material_change_reason'=>'production graphic/discussion generation','previous_version'=>$p['product_version']-1,'rollback_reference'=>$p['rollback_reference'],'content_hash'=>$p['content_hash'],'graphic_hash'=>$p['graphic_hash']??''));}update_option(self::HISTORY,array_slice($h,0,1000),false);}
    private function invalidate($changed){foreach($changed as $id){delete_transient(self::CACHE_PREFIX.$id);}delete_transient(self::CACHE_PREFIX.'all');}
}
