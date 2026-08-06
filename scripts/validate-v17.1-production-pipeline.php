<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__); }
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
function absint($v){return abs((int)$v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return is_array($v)?'':trim(strip_tags((string)$v));} function wp_json_encode($v,$o=0){return json_encode($v,$o);}
function esc_url_raw($v){return filter_var((string)$v,FILTER_SANITIZE_URL);} function current_time($type='mysql'){return $type==='timestamp'?time():date('Y-m-d H:i:s');}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function apply_filters($tag,$value){return $value;}
$GLOBALS['options']=array();$GLOBALS['transients']=array();$GLOBALS['deleted_transients']=array();$GLOBALS['scheduled']=array();
function get_option($k,$d=false){return array_key_exists($k,$GLOBALS['options'])?$GLOBALS['options'][$k]:$d;} function update_option($k,$v,$autoload=false){$GLOBALS['options'][$k]=$v;return true;}
function set_transient($k,$v,$ttl){$GLOBALS['transients'][$k]=array($v,time()+$ttl);return true;} function get_transient($k){return isset($GLOBALS['transients'][$k])?$GLOBALS['transients'][$k][0]:false;}
function delete_transient($k){$GLOBALS['deleted_transients'][]=$k;unset($GLOBALS['transients'][$k]);return true;} function wp_next_scheduled($h){return $GLOBALS['scheduled'][$h]??false;}
function wp_schedule_event($t,$r,$h){$GLOBALS['scheduled'][$h]=$t;return true;} function wp_clear_scheduled_hook($h){unset($GLOBALS['scheduled'][$h]);}
define('MINUTE_IN_SECONDS',60);define('HOUR_IN_SECONDS',3600);
require dirname(__DIR__).'/plugins/region9-live-studio/includes/class-audit-log.php';
require dirname(__DIR__).'/plugins/region9-live-studio/includes/class-timing-engine.php';
require dirname(__DIR__).'/plugins/region9-live-studio/includes/class-material-change-engine.php';
require dirname(__DIR__).'/plugins/region9-live-studio/includes/class-scheduler.php';
require dirname(__DIR__).'/plugins/region9-live-studio/includes/class-product-generator.php';
require dirname(__DIR__).'/plugins/region9-live-studio/includes/class-rest-api.php';

class PipelineRules {
    public function region9_risk($score){if($score<=0)return array('level'=>0,'label'=>'None');if($score<50)return array('level'=>1,'label'=>'Low');return array('level'=>3,'label'=>'Elevated');}
    public function evaluate_all($sources){return pipeline_decision((int)($sources['score']??10));}
}
class PipelineGuidance { private $score;public function __construct($score){$this->score=$score;}public function collect_all(){return array('score'=>$this->score,'spc_day1'=>array('status'=>'healthy'),'wpc_day1_ero'=>array('status'=>'healthy'),'wpc_day1_qpf'=>array('status'=>'healthy'),'nws_alerts'=>array('status'=>'healthy'),'nws_points_grid_hourly'=>array('status'=>'healthy'));} }
function pipeline_decision($score=10){$base=array('score'=>$score,'rating'=>$score>=50?'Elevated':'Low','confidence'=>80,'affected_counties'=>array('Champaign'),'timing'=>'18Z','primary_drivers'=>array('spc'),'secondary_drivers'=>array(),'county_scores'=>array('Champaign'=>$score));$names=array('Decision Support Brief','Travel','Forecast Confidence','Severe Weather Risk','Agriculture','Fieldwork','Spraying','Harvest','Livestock','Outdoor Events','School Activities','Construction','Emergency Operations');return array_fill_keys($names,$base);}
$passes=0;function check_pipeline($name,$condition){global $passes;if(!$condition){fwrite(STDERR,"FAIL: $name\n");exit(1);}$passes++;echo "PASS: $name\n";}

$audit=new R9LS_Audit_Log();$changes=new R9LS_Material_Change_Engine($audit);$rules=new PipelineRules();$generator=new R9LS_Product_Generator($rules,$changes,$audit,new R9LS_Timing_Engine());
check_pipeline('migration clean install',R9LS_Product_Generator::migrate_17_1($audit)===true&&get_option(R9LS_Product_Generator::MIGRATION,array())['version']==='17.1.0');
update_option(R9LS_Scheduler::SETTINGS,array('enabled_products'=>array()),false);
$first=$generator->refresh_workspace_from_decision(pipeline_decision(),array(),'manual','validation-first',array('validation_duration'=>0.2,'source_health_summary'=>array('nws'=>'healthy')));
check_pipeline('1 first-run generation with no prior products',count($first['products'])===count(R9LS_Product_Generator::product_definitions()));
update_option(R9LS_Product_Generator::WORKSPACE,'broken',false);$repair=$generator->refresh_workspace_from_decision(pipeline_decision(),array(),'manual');check_pipeline('2 empty workspace repair',!empty($repair['products']));
$scheduler=new R9LS_Scheduler($audit,$rules,$changes,new PipelineGuidance(20),$generator);$manual=$scheduler->manual_validate();check_pipeline('3 manual validation creates workspace rows',$manual['status']==='ok'&&!empty($manual['workspace']['products']));
$scheduled=$scheduler->scheduled_validate();check_pipeline('4 scheduled validation creates workspace rows',$scheduled['status']==='ok'&&!empty($scheduled['workspace']['products']));
check_pipeline('5 enabled product fallback',R9LS_Product_Generator::enabled_product_ids(array())===array_keys(R9LS_Product_Generator::product_definitions()));
check_pipeline('6 malformed enabled product settings',R9LS_Product_Generator::enabled_product_ids(array('../bad','not-real'))===array_keys(R9LS_Product_Generator::product_definitions()));
update_option(R9LS_Scheduler::SETTINGS,array('enabled_products'=>array('travel','travel','INVALID','headlines')),false);$limited=$generator->refresh_workspace_from_decision(pipeline_decision(),array(),'manual');check_pipeline('7 one row per canonical product',array_keys($limited['products'])===array('headlines','travel'));
$material=array(array('product'=>'Travel','field'=>'score','reason'=>'score movement','previous'=>10,'new'=>60));$grouped=$generator->refresh_workspace_from_decision(pipeline_decision(60),$material,'manual');check_pipeline('8 grouped changes',count($grouped['products']['travel']['grouped_changes'])===1&&$grouped['products']['travel']['grouped_change_count']===1);
$v2=$grouped['products']['travel']['product_version'];check_pipeline('9 changed-product version increment',$v2>1);
$unchanged=$generator->refresh_workspace_from_decision(pipeline_decision(60),array(),'manual');check_pipeline('10 unchanged-product version reuse',$unchanged['products']['travel']['product_version']===$v2&&$unchanged['products']['travel']['workspace_state']==='unchanged_available_for_review');
$hashrow=$unchanged['products']['travel'];$hash=$generator->content_hash($hashrow);$hashrow['generated_at']='2099-01-01';$hashrow['generation_duration']=99;$hashrow['approval_reference']='volatile';check_pipeline('11 stable content hashing',$generator->content_hash($hashrow)===$hash);
$last=get_option(R9LS_Product_Generator::GENERATION_LAST,array());check_pipeline('12 generation duration storage',array_key_exists('duration',$last)&&$last['duration']>=0);
$decisions=get_option(R9LS_Product_Generator::DECISION_HISTORY,array());check_pipeline('13 Decision History creation',($decisions[0]['record_type']??'')==='validation_decision'&&!empty($decisions[0]['generated_workspace_product_ids']));
$approved=$generator->approve('travel','editor');check_pipeline('14 approve action',$approved['success']&&$approved['product']['approval_state']==='approved');
$rejected=$generator->reject('headlines','Not suitable','editor');check_pipeline('15 reject action',$rejected['success']&&$rejected['product']['rejection_reason']==='Not suitable');
$published=$generator->publish('travel','publisher');check_pipeline('16 publish approved action',$published['success']&&$published['product']['publication_state']==='published');
$unapproved=$generator->refresh_workspace_from_decision(pipeline_decision(70),array(),'manual');check_pipeline('17 block unapproved publication',$generator->publish('travel')['code']==='approval_required');
$generator->reject('headlines','No','editor');check_pipeline('18 block rejected publication',$generator->publish('headlines')['code']==='rejected_product');
$generator->approve('travel','editor');$generator->publish('travel','publisher');check_pipeline('19 duplicate publication prevention',$generator->publish('travel')['code']==='duplicate_publication');
$before=get_option(R9LS_Product_Generator::PRODUCTS,array());$missing=$generator->publish('not-a-product');check_pipeline('20 failed publication preserves previous public state',!$missing['success']&&get_option(R9LS_Product_Generator::PRODUCTS,array())===$before);
$history=get_option(R9LS_Product_Generator::PUBLICATION_HISTORY,array());check_pipeline('21 publication-history creation',count($history)>=2&&!empty($history[0]['snapshot']));
$target=end($history);$rollback=$generator->rollback($target['product_id'],$target['publication_reference'],'editor');check_pipeline('22 rollback',$rollback['success']&&$rollback['product']['rollback_reference']===$target['publication_reference']);
$history2=get_option(R9LS_Product_Generator::PUBLICATION_HISTORY,array());check_pipeline('23 rollback-history creation',$history2[0]['event_type']==='rollback'&&count($history2)===count($history)+1);
$rest_source=file_get_contents(dirname(__DIR__).'/plugins/region9-live-studio/includes/class-rest-api.php');check_pipeline('24 public REST excludes drafts',strpos($rest_source,"publication_state'] ?? '') === 'published'")!==false&&strpos($rest_source,'WORKSPACE')===false);
$theme_source=file_get_contents(dirname(__DIR__).'/inc/live-studio-integration.php');check_pipeline('25 theme helpers exclude drafts',strpos($theme_source,"publication_state']??'')==='published'")!==false&&strpos($theme_source,'forecast_production_workspace')===false);
$public_before=get_option(R9LS_Product_Generator::PRODUCTS,array());update_option(R9LS_Product_Generator::WORKSPACE,array(array('product_id'=>'travel','title'=>'Legacy')),false);update_option(R9LS_Product_Generator::MIGRATION,array(),false);check_pipeline('26 17.0.0-rc.1 migration',R9LS_Product_Generator::migrate_17_1($audit)===true&&isset(get_option(R9LS_Product_Generator::WORKSPACE,array())['products']['travel'])&&get_option(R9LS_Product_Generator::PRODUCTS,array())===$public_before);
update_option(R9LS_Product_Generator::MIGRATION,array(),false);update_option(R9LS_Product_Generator::PUBLICATION_HISTORY,'malformed',false);check_pipeline('partially migrated 17.0.0-rc.1 repair',R9LS_Product_Generator::migrate_17_1($audit)===true&&is_array(get_option(R9LS_Product_Generator::PUBLICATION_HISTORY,array()))&&get_option(R9LS_Product_Generator::PRODUCTS,array())===$public_before);
check_pipeline('27 migration idempotency',R9LS_Product_Generator::migrate_17_1($audit)===false);
update_option('theme_mods_region9',array('primary_color'=>'blue'),false);$scheduler->deactivate();$scheduler->activate();check_pipeline('plugin deactivate reactivate preserves state',get_option(R9LS_Product_Generator::PRODUCTS,array())===$public_before&&get_option('theme_mods_region9',array())['primary_color']==='blue');
check_pipeline('theme update without data loss',R9LS_Product_Generator::migrate_17_1($audit)===false&&get_option('theme_mods_region9',array())['primary_color']==='blue');
check_pipeline('28 cache invalidation scope',in_array(R9LS_Product_Generator::CACHE_PREFIX.'travel',$GLOBALS['deleted_transients'],true)&&!in_array(R9LS_Product_Generator::CACHE_PREFIX.'headlines',$GLOBALS['deleted_transients'],true));
$admin_source=file_get_contents(dirname(__DIR__).'/plugins/region9-live-studio/includes/class-admin.php');check_pipeline('29 capability and nonce enforcement',strpos($admin_source,"current_user_can('manage_options')")!==false&&strpos($admin_source,'check_admin_referer')!==false&&strpos($admin_source,"sanitize_key(wp_unslash(\$_POST['product_id']")!==false);
$events=array();foreach($audit->all() as $entry){$events[]=$entry['context']['event']??'';}check_pipeline('30 audit events',in_array('workspace_generated',$events,true)&&in_array('product_approved',$events,true)&&in_array('product_rejected',$events,true)&&in_array('product_published',$events,true)&&in_array('publication_blocked',$events,true)&&in_array('rollback_completed',$events,true)&&in_array('migration_completed',$events,true));
$build=dirname(__DIR__).'/build';if(!is_dir($build)){mkdir($build,0777,true);}file_put_contents($build.'/v17.1-production-pipeline-report.json',json_encode(array('version'=>'17.1.0','assertions'=>$passes,'failures'=>0,'status'=>'PASS','generated_at'=>date('c')),JSON_PRETTY_PRINT));
echo "v17.1 production pipeline validation complete with $passes assertions.\n";
