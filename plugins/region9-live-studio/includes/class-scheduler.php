<?php
defined('ABSPATH') || exit;

class R9LS_Scheduler {
    const HOOK = 'r9ls_validate_weather_operations';
    const SETTINGS = 'r9ls_settings';
    const HEALTH = 'r9ls_scheduler_health';
    const LOCK = 'r9ls_validation_lock';
    const LAST = 'r9ls_last_validation';
    const CACHE = 'r9ls_current_products';
    const LAST_GRAPHICS = 'r9ls_last_graphics_cycle';
    const GRAPHICS_INTERVAL = 21600;
    const VALIDATION_INTERVAL_MINUTES = 5;
    private $audit; private $rules; private $changes; private $guidance; private $products;

    public function __construct($audit, $rules, $changes, $guidance = null, $products = null) {
        $this->audit=$audit; $this->rules=$rules; $this->changes=$changes; $this->guidance=$guidance; $this->products=$products;
    }
    public function hooks(){add_filter('cron_schedules',array($this,'cron_schedules'));add_action(self::HOOK,array($this,'scheduled_validate'));}
    public function cron_schedules($schedules){$schedules['r9ls_active_weather']=array('interval'=>self::VALIDATION_INTERVAL_MINUTES*MINUTE_IN_SECONDS,'display'=>'Region 9 five-minute weather validation');$schedules['r9ls_hourly']=array('interval'=>HOUR_IN_SECONDS,'display'=>'Region 9 hourly validation');return $schedules;}
    public function activate(){$this->ensure_defaults();wp_clear_scheduled_hook(self::HOOK);$this->schedule_event();}
    public function deactivate(){wp_clear_scheduled_hook(self::HOOK);delete_transient(self::LOCK);$this->set_health('inactive','Scheduler deactivated.');}

    public function ensure_defaults(){
        $settings=get_option(self::SETTINGS,array());
        $settings=wp_parse_args($settings,array(
            'active_interval_minutes'=>60,
            'validation_interval_minutes'=>self::VALIDATION_INTERVAL_MINUTES,
            'graphics_interval_hours'=>6,
            'automatic_graphics_publishing'=>1,
            'automatic_publishing'=>0,
            'score_movement_threshold'=>10,
            'confidence_threshold'=>60,
            'timing_tolerance_minutes'=>60,
            'national_guidance_timeout'=>12,
            'national_guidance_user_agent'=>'Region9LiveStudio/17 RC2 (WeatherSupportLLC; WordPress wp_remote_get)'
        ));
        $settings['validation_interval_minutes']=self::VALIDATION_INTERVAL_MINUTES;
        update_option(self::SETTINGS,$settings,false);
    }
    public function active_interval_minutes(){ $settings=get_option(self::SETTINGS,array()); $minutes=isset($settings['active_interval_minutes'])?absint($settings['active_interval_minutes']):60; return max(15,$minutes); }
    public function operational_validation_minutes(){ return self::VALIDATION_INTERVAL_MINUTES; }
    public function schedule_event(){if(wp_next_scheduled(self::HOOK)){$this->set_health('scheduled','Existing scheduler event retained.');return false;}$ok=wp_schedule_event(time()+60,'r9ls_active_weather',self::HOOK);if(!$ok){$this->audit->write('error','Scheduler failed to schedule validation event.');$this->set_health('failure','Failed to schedule validation event.');return false;}$this->set_health('scheduled','Five-minute validation event scheduled.');return true;}
    public function scheduled_validate(){return $this->validate('scheduled');}
    public function manual_validate(){return $this->validate('manual');}

    public function validate($mode='manual'){
        $started=microtime(true);
        if($this->locked()){$this->audit->write('warning','Validation skipped because a validation lock is active.',array('mode'=>$mode));return array('status'=>'locked');}
        set_transient(self::LOCK,time(),20*MINUTE_IN_SECONDS);
        try{
            $products=$this->rules->evaluate_all($this->load_sources());
            $previous=get_option(self::CACHE,array());
            $changes=$this->changes->detect($previous,$products);
            update_option(self::CACHE,$products,false);
            $settings=get_option(self::SETTINGS,array());
            $last_graphics=(int)get_option(self::LAST_GRAPHICS,0);
            $due=(!$last_graphics||(time()-$last_graphics)>=self::GRAPHICS_INTERVAL);
            $severe=$this->severe_state($products);
            $severe_changed=$severe&&$this->severe_material_change($changes);
            $render_graphics=($mode==='manual')||$due||$severe_changed;
            $auto_graphics=!empty($settings['automatic_graphics_publishing']);
            if($this->products instanceof R9LS_Product_Generator){
                $this->products->refresh_workspace_from_decision($products,$changes,$mode,'validation-'.gmdate('YmdHis'),$render_graphics,$auto_graphics);
                if($render_graphics){update_option(self::LAST_GRAPHICS,time(),false);$this->audit->write('info','Region 9 graphics/discussion cycle completed.',array('reason'=>$mode==='manual'?'manual':($severe_changed?'severe_material_change':'six_hour_cycle'),'automatic_publish'=>$auto_graphics));}
            }
            $duration=round(microtime(true)-$started,3);
            $last=array('time'=>current_time('mysql'),'mode'=>sanitize_key($mode),'duration'=>$duration,'changes'=>count($changes),'validation_interval_minutes'=>self::VALIDATION_INTERVAL_MINUTES,'graphics_rendered'=>(bool)$render_graphics,'graphics_reason'=>$render_graphics?($severe_changed?'severe_material_change':($mode==='manual'?'manual':'six_hour_cycle')):'not_due','severe_weather_active'=>(bool)$severe,'next_graphics_due'=>gmdate('c',(int)get_option(self::LAST_GRAPHICS,time())+self::GRAPHICS_INTERVAL));
            update_option(self::LAST,$last,false);$this->set_health('healthy','Last validation completed.',$last);$this->audit->write('info','Validation completed.',$last);
            return array('status'=>'ok','products'=>$products,'changes'=>$changes,'duration'=>$duration,'graphics_rendered'=>$render_graphics,'severe_weather_active'=>$severe);
        }catch(Exception $e){$this->audit->write('error','Validation failed.',array('error'=>$e->getMessage()));$this->set_health('failure','Validation failed: '.$e->getMessage());return array('status'=>'error','message'=>$e->getMessage());}
        finally{delete_transient(self::LOCK);}
    }

    private function severe_state($products){$p=$products['Severe Weather Risk']??array();return (int)($p['score']??0)>=25;}
    private function severe_material_change($changes){foreach((array)$changes as $change){$product=(string)($change['product']??'');if($product==='Severe Weather Risk'||stripos($product,'Severe')!==false||stripos($product,'Alert')!==false)return true;}return false;}
    public function locked(){$locked_at=get_transient(self::LOCK);if(!$locked_at)return false;if((time()-absint($locked_at))>20*MINUTE_IN_SECONDS){delete_transient(self::LOCK);$this->audit->write('warning','Expired validation lock was cleared.');return false;}return true;}
    public function health(){return get_option(self::HEALTH,array('status'=>'unknown','message'=>'Scheduler not initialized.'));}
    public function next_validation(){$next=wp_next_scheduled(self::HOOK);if($next)return $next;$this->ensure_defaults();$this->schedule_event();return wp_next_scheduled(self::HOOK);}
    public function load_sources(){
        $sources=$this->guidance?$this->guidance->collect_all():array('spc_day1'=>array('status'=>'healthy','hazards'=>array()),'wpc_day1_ero'=>array('status'=>'healthy','hazards'=>array()),'wpc_day1_qpf'=>array('status'=>'healthy','county_precipitation'=>array()),'nws_alerts'=>array('status'=>'healthy','hazards'=>array()),'nws_points_grid_hourly'=>array('status'=>'healthy','forecast_periods'=>array(),'hourly_periods'=>array()));
        $sources['nws_alerts']=$sources['nws_alerts']??array('status'=>'healthy','hazards'=>array());$sources['nws_points_grid_hourly']=$sources['nws_points_grid_hourly']??array('status'=>'healthy','forecast_periods'=>array(),'hourly_periods'=>array());
        return apply_filters('r9ls_weather_sources',$sources);
    }
    private function set_health($status,$message,$extra=array()){update_option(self::HEALTH,array_merge(array('status'=>$status,'message'=>$message,'updated'=>current_time('mysql')),$extra),false);}
}
