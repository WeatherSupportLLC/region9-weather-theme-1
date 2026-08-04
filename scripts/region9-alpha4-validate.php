<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__); }
function absint($v){ return abs((int)$v); } function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','', strtolower((string)$v)); }
function sanitize_text_field($v){ return is_array($v) ? '' : trim(strip_tags((string)$v)); } function wp_json_encode($v,$o=0){ return json_encode($v,$o); }
function apply_filters($tag,$value){ return $value; } function current_time(){ return date('Y-m-d H:i:s'); }
$GLOBALS['wp_options'] = array(); $GLOBALS['wp_transients'] = array(); $GLOBALS['scheduled'] = array();
function get_option($k,$d=false){ return $GLOBALS['wp_options'][$k] ?? $d; } function update_option($k,$v,$a=false){ $GLOBALS['wp_options'][$k]=$v; return true; } function delete_transient($k){ unset($GLOBALS['wp_transients'][$k]); }
function set_transient($k,$v,$ttl){ $GLOBALS['wp_transients'][$k]=array($v,time()+$ttl); } function get_transient($k){ if(!isset($GLOBALS['wp_transients'][$k])) return false; if($GLOBALS['wp_transients'][$k][1] < time()) return false; return $GLOBALS['wp_transients'][$k][0]; }
function wp_next_scheduled($h){ return $GLOBALS['scheduled'][$h] ?? false; } function wp_schedule_event($t,$r,$h){ if(isset($GLOBALS['scheduled'][$h])) return false; $GLOBALS['scheduled'][$h]=$t; return true; } function wp_clear_scheduled_hook($h){ unset($GLOBALS['scheduled'][$h]); } function wp_parse_args($a,$d){ return array_merge($d,$a); }
define('MINUTE_IN_SECONDS',60); define('HOUR_IN_SECONDS',3600); define('R9LS_DIR', dirname(__DIR__) . '/plugins/region9-live-studio/');
require R9LS_DIR.'includes/class-audit-log.php'; require R9LS_DIR.'includes/class-gis-engine.php'; require R9LS_DIR.'includes/class-rule-engine.php'; require R9LS_DIR.'includes/class-material-change-engine.php'; require R9LS_DIR.'includes/class-scheduler.php';
function assert_true($name,$condition){ if(!$condition){ fwrite(STDERR,"FAIL: $name\n"); exit(1);} echo "PASS: $name\n"; }
$a=new R9LS_Audit_Log(); $g=new R9LS_GIS_Engine(R9LS_DIR.'data/region9-counties.geojson'); $r=new R9LS_Rule_Engine($g,$a); $m=new R9LS_Material_Change_Engine($a); $s=new R9LS_Scheduler($a,$r,$m);
$s->ensure_defaults(); assert_true('minimum interval', $s->active_interval_minutes()===60); $s->schedule_event(); $first=wp_next_scheduled(R9LS_Scheduler::HOOK); $s->schedule_event(); assert_true('scheduler duplicate prevention', $first===wp_next_scheduled(R9LS_Scheduler::HOOK));
set_transient(R9LS_Scheduler::LOCK, time()-2000, 1); assert_true('lock expiration', $s->locked()===false);
$poly=json_decode(file_get_contents(R9LS_DIR.'tests/fixtures/polygon.json'),true); $multi=json_decode(file_get_contents(R9LS_DIR.'tests/fixtures/multipolygon.json'),true); $none=json_decode(file_get_contents(R9LS_DIR.'tests/fixtures/no-intersection.json'),true);
assert_true('Polygon intersection', in_array('Boone',$g->intersect_source($poly)['affected_counties'],true)); assert_true('MultiPolygon intersection', count($g->intersect_source($multi)['affected_counties'])>=2); assert_true('no-intersection result', $g->intersect_source($none)['highest_risk']===0);
$healthy=array('spc_day1'=>array('status'=>'healthy','hazards'=>array()),'wpc_day1_ero'=>array('status'=>'healthy','hazards'=>array()),'nws_alerts'=>array('status'=>'healthy','hazards'=>array())); $products=$r->evaluate_all($healthy); assert_true('healthy source with zero hazards', $products['Travel']['score']===0 && $products['Travel']['confidence']===100);
$bad=$healthy; $bad['spc_day1']=array('status'=>'stale'); $p2=$r->evaluate_all($bad); assert_true('stale cached source confidence degradation', $p2['Travel']['confidence']<100); $bad['spc_day1']=array('status'=>'unavailable'); assert_true('unavailable source handled', $r->evaluate_all($bad)['Travel']['confidence']<100);
assert_true('scoring boundaries', $products['Travel']['rating']==='Good' && $r->region9_risk(100)['label']==='Significant');
$prev=$products; $cur=$products; $cur['Travel']['score']=80; $cur['Travel']['rating']='Dangerous'; $changes=$m->detect($prev,$cur); assert_true('material-change detection', count($changes)>=2); assert_true('approval-before-publish requirement', $m->publish($changes[0]['id'])===false); $m->decide($changes[0]['id'],'approved'); assert_true('publish after approval', $m->publish($changes[0]['id'])===true); assert_true('rollback behavior', $m->rollback($changes[0]['id'])===true);
update_option('r9ls_editor_overrides',array('x'=>array('expires'=>'2000-01-01T00:00'))); assert_true('override expiration', $m->expire_overrides()===array());
echo "Alpha 4 validation complete.\n";
