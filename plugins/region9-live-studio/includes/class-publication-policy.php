<?php
defined('ABSPATH') || exit;

final class R9LS_Publication_Policy {
    const SETTINGS = 'r9ls_publication_policy';
    const LAST = 'r9ls_publication_policy_last';

    private $generator;
    private $audit;

    public function __construct($generator, $audit = null) { $this->generator = $generator; $this->audit = $audit; }

    public function hooks() { add_action('r9ls_validation_complete', array($this,'validation_complete'), 10, 3); }

    public static function defaults() {
        return array(
            'routine_auto_publish'=>1,
            'material_change_auto_publish'=>1,
            'review_elevated'=>1,
            'review_significant'=>1,
            'emergency_warning_auto_publish'=>1,
            'minimum_confidence'=>40,
        );
    }

    public function settings() { return wp_parse_args(get_option(self::SETTINGS,array()), self::defaults()); }

    public function validation_complete($decision, $changes, $mode) {
        $settings=$this->settings();
        $mode=sanitize_key($mode);
        $is_six_hour=$mode==='six-hour-production';
        $is_material=!empty($changes) && !$is_six_hour;
        if(!$is_six_hour && !$is_material) return;
        if($is_six_hour && empty($settings['routine_auto_publish'])) return $this->hold('routine-auto-publish-disabled',$mode,$decision,$changes);
        if($is_material && empty($settings['material_change_auto_publish'])) return $this->hold('material-change-auto-publish-disabled',$mode,$decision,$changes);

        $max_level=0; $min_confidence=100;
        foreach((array)$decision as $row){
            $risk=R9LS_Plugin::instance()->rules->region9_risk((int)($row['score']??0));
            $max_level=max($max_level,(int)$risk['level']);
            $min_confidence=min($min_confidence,(int)($row['confidence']??0));
        }
        if($min_confidence < (int)$settings['minimum_confidence']) return $this->hold('confidence-below-auto-publish-threshold',$mode,$decision,$changes);

        $emergency=$this->has_region9_warning();
        if($max_level>=4 && !empty($settings['review_significant']) && !($emergency && !empty($settings['emergency_warning_auto_publish']))) {
            return $this->hold('significant-risk-review-required',$mode,$decision,$changes);
        }
        if($max_level===3 && !empty($settings['review_elevated']) && !($emergency && !empty($settings['emergency_warning_auto_publish']))) {
            return $this->hold('elevated-risk-review-required',$mode,$decision,$changes);
        }

        $version='auto-'.gmdate('Ymd-His');
        update_option(R9LS_Product_Generator::STATE,array(
            'publication_version'=>$version,
            'approved_at'=>current_time('mysql'),
            'effective_start'=>gmdate('c'),
            'effective_end'=>gmdate('c',time()+12*HOUR_IN_SECONDS),
            'source_times'=>array('automation'=>gmdate('c')),
            'approval_state'=>'approved',
            'publication_state'=>'published',
            'history_id'=>$version,
            'rollback_reference'=>'',
            'decision_output'=>$decision,
        ),false);
        $products=$this->generator->generate_from_approved_state('automation',$version);
        $result=array('status'=>'published','reason'=>$emergency?'emergency-warning-auto-publish':($is_six_hour?'six-hour-production':'material-change'),'time'=>current_time('mysql'),'publication_version'=>$version,'risk_level'=>$max_level,'product_count'=>count($products));
        update_option(self::LAST,$result,false);
        if($this->audit)$this->audit->write('info','Automated Region 9 publication completed.',$result);
        return $result;
    }

    private function hold($reason,$mode,$decision,$changes) {
        $result=array('status'=>'held-for-review','reason'=>$reason,'mode'=>sanitize_key($mode),'time'=>current_time('mysql'),'change_count'=>count((array)$changes));
        update_option(self::LAST,$result,false);
        if($this->audit)$this->audit->write('warning','Automated publication held for review.',$result);
        return $result;
    }

    private function has_region9_warning() {
        $state=class_exists('R9LS_Alert_Feed')?get_option(R9LS_Alert_Feed::LIVE_OPTION,array()):array();
        foreach((array)($state['alerts']??array()) as $alert){
            $event=strtolower((string)($alert['event']??''));
            if(strpos($event,'warning')!==false || strpos($event,'emergency')!==false) return true;
        }
        return false;
    }
}
