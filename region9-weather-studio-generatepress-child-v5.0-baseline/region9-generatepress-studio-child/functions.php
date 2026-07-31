<?php
if (!defined('ABSPATH')) exit;
define('R9_STUDIO_VERSION','4.0.0');
require_once get_stylesheet_directory().'/inc/customizer.php';
require_once get_stylesheet_directory().'/inc/admin-studio.php';
require_once get_stylesheet_directory().'/inc/widgets.php';

add_action('wp_enqueue_scripts', function(){
  wp_enqueue_style('generatepress-parent', get_template_directory_uri().'/style.css', array(), null);
  wp_enqueue_style('r9-studio', get_stylesheet_uri(), array('generatepress-parent'), R9_STUDIO_VERSION);
  wp_enqueue_script('r9-studio', get_stylesheet_directory_uri().'/assets/js/studio.js', array(), R9_STUDIO_VERSION, true);
  wp_localize_script('r9-studio','R9Studio',array(
    'rest'=>esc_url_raw(rest_url('r9/v2/conditions')),'forecastRest'=>esc_url_raw(rest_url('r9/v2/city-forecast')),'forecastPage'=>esc_url_raw(home_url('/city-forecast/')),
    'alerts'=>esc_url_raw(rest_url('r9/v2/alerts')),
    'alertDetail'=>esc_url_raw(rest_url('r9/v2/alert-detail')),
    'alertPage'=>esc_url_raw(home_url('/alerts/')),'statusRest'=>esc_url_raw(rest_url('r9/v4/status')),
    'primaryCounties'=>array_values(r9_primary_counties()),
    'neighborCounties'=>array_values(r9_neighbor_counties())
  ));
});

add_action('after_setup_theme', function(){
  register_nav_menus(array('r9_studio_menu'=>'Region 9 Streamlined Menu','r9_footer_menu'=>'Region 9 Footer Menu'));
  add_theme_support('custom-logo',array('height'=>100,'width'=>360,'flex-height'=>true,'flex-width'=>true));
  add_theme_support('title-tag'); add_theme_support('post-thumbnails');
});

add_action('widgets_init', function(){
  foreach(array('r9-live-sidebar'=>'Live Studio Sidebar','r9-forecast-sidebar'=>'Forecast Page Sidebar','r9-alert-sidebar'=>'Alert & Safety Sidebar','r9-footer-one'=>'Footer Column One','r9-footer-two'=>'Footer Column Two') as $id=>$name)
    register_sidebar(array('name'=>$name,'id'=>$id,'before_widget'=>'<section class="r9-widget r9-panel">','after_widget'=>'</section>','before_title'=>'<div class="r9-panel-head"><h3>','after_title'=>'</h3></div>'));
});


add_filter('body_class',function($classes){
  if(r9_setting('emergency_mode',false)) $classes[]='r9-emergency-mode';
  if(r9_setting('high_contrast',false)) $classes[]='r9-high-contrast';
  return $classes;
});

function r9_updated_label(){
  $ts=(int)r9_setting('last_manual_update',0);
  if(!$ts) $ts=current_time('timestamp');
  return wp_date('M j, g:i A T',$ts);
}

function r9_status_probe($url,$cache_key){
  $cached=get_transient($cache_key); if(false!==$cached) return $cached;
  $start=microtime(true); $r=wp_safe_remote_head($url,array('timeout'=>6,'redirection'=>2,'user-agent'=>'Region9Weather/4.0'));
  if(is_wp_error($r)) $out=array('ok'=>false,'latency'=>null,'code'=>0);
  else {$code=(int)wp_remote_retrieve_response_code($r);$out=array('ok'=>$code>=200&&$code<400,'latency'=>(int)round((microtime(true)-$start)*1000),'code'=>$code);}
  set_transient($cache_key,$out,5*MINUTE_IN_SECONDS); return $out;
}

function r9_status_endpoint(){
  $services=array(
    'alerts'=>r9_status_probe('https://api.weather.gov/alerts/active?area=IL','r9_status_alerts'),
    'forecast'=>r9_status_probe('https://api.weather.gov/gridpoints/ILX/95,72/forecast','r9_status_forecast'),
    'observations'=>r9_status_probe('https://api.weather.gov/stations/KCMI/observations/latest','r9_status_obs'),
    'radar'=>r9_status_probe(r9_setting('radar_url','https://app.weatherfront.com/radar/KILX'),'r9_status_radar')
  );
  $ok=0;foreach($services as $x)if(!empty($x['ok']))$ok++;
  return rest_ensure_response(array('checked'=>current_time('c'),'healthy'=>$ok,'total'=>count($services),'services'=>$services));
}

function r9_setting($key,$default=''){
  $value=get_theme_mod('r9_'.$key,null);
  if($value===null||$value==='') return $default;
  return $value;
}
function r9_risk_class(){ return 'r9-risk-'.sanitize_html_class(r9_setting('risk_level','none')); }
function r9_risk_label(){ $level=sanitize_key(r9_setting('risk_level','none')); $labels=array('none'=>'None','low'=>'Low','limited'=>'Limited','elevated'=>'Elevated','significant'=>'Significant'); return $labels[$level]??'None'; }

function r9_impact_class($value){$v=sanitize_key($value);$map=array('none'=>'none','good'=>'good','low'=>'good','fair'=>'fair','monitor'=>'fair','limited'=>'fair','caution'=>'caution','medium'=>'caution','poor'=>'poor','elevated'=>'poor','avoid'=>'danger','dangerous'=>'danger','significant'=>'danger','high'=>'high');return $map[$v]??'none';}
function r9_impact_label($value){$v=sanitize_key($value);$labels=array('none'=>'None','good'=>'Good','low'=>'Low','fair'=>'Fair','monitor'=>'Monitor','limited'=>'Limited','caution'=>'Caution','medium'=>'Medium','poor'=>'Poor','elevated'=>'Elevated','avoid'=>'Avoid','dangerous'=>'Dangerous','significant'=>'Significant','high'=>'High');return $labels[$v]??ucwords(str_replace(array('-','_'),' ',$value));}

function r9_share_buttons($title='Region 9 Weather', $image_url=''){
  $page_url=is_singular()?get_permalink():home_url('/');
  $share_url=$image_url?:$page_url;
  $text=rawurlencode($title.' | Region 9 Weather');
  $encoded=rawurlencode($share_url);
  return '<div class="r9-share-tools" aria-label="Share this weather graphic">'
    .'<span class="r9-share-label">Share:</span>'
    .'<a class="r9-share-button facebook" href="https://www.facebook.com/sharer/sharer.php?u='.$encoded.'" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook">FB</a>'
    .'<a class="r9-share-button x" href="https://twitter.com/intent/tweet?text='.$text.'&url='.$encoded.'" target="_blank" rel="noopener noreferrer" aria-label="Share on X">X</a>'
    .'<button class="r9-share-button instagram" type="button" data-r9-share="instagram" data-share-url="'.esc_attr($share_url).'" data-share-title="'.esc_attr($title).'" aria-label="Share for Instagram">IG</button>'
    .'<a class="r9-share-button email" href="mailto:?subject='.$text.'&body='.$encoded.'" aria-label="Share by email">Email</a>'
    .'<button class="r9-share-button copy" type="button" data-r9-share="copy" data-share-url="'.esc_attr($share_url).'" aria-label="Copy link">Copy</button>'
    .'</div>';
}
function r9_media_placeholder($title='Forecast Graphic', $key=''){
  $id=$key?(int)r9_setting($key):0;
  $media='';$url='';
  if($id){$url=(string)wp_get_attachment_image_url($id,'full'); if($url)$media='<img class="r9-image" src="'.esc_url($url).'" alt="'.esc_attr($title).'">';}
  if(!$media)$media='<div class="r9-media-placeholder" role="img" aria-label="'.esc_attr($title).' placeholder"><div class="r9-placeholder-icon" aria-hidden="true">＋</div><strong>'.esc_html($title).'</strong><span>Upload the latest graphic or photo in Region 9 Studio</span></div>';
  return '<div class="r9-shareable-media">'.$media.r9_share_buttons($title,$url).'</div>';
}
function r9_video_embed(){
 if(!r9_setting('live_broadcast_enabled',false)) return '';
 $url=trim((string)r9_setting('live_video_url',''));
 if(!$url) return '';
 $embed=wp_oembed_get($url,array('width'=>1280,'height'=>720));
 if($embed)return '<div class="r9-video-frame">'.$embed.'</div>';
 return '<div class="r9-video-frame"><iframe src="'.esc_url($url).'" title="Region 9 Weather live broadcast" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>';
}

function r9_primary_counties(){ return array('017091'=>'Kankakee','017075'=>'Iroquois','017053'=>'Ford','017105'=>'Livingston','017019'=>'Champaign','017183'=>'Vermilion','017113'=>'McLean','017039'=>'DeWitt','017147'=>'Piatt'); }
function r9_neighbor_counties(){ return array('017011'=>'Bureau','017023'=>'Clark','017029'=>'Coles','017033'=>'Crawford','017035'=>'Cumberland','017041'=>'Douglas','017043'=>'DuPage','017045'=>'Edgar','017063'=>'Grundy','017073'=>'Henry','017089'=>'Kane','017093'=>'Kendall','017099'=>'LaSalle','017107'=>'Logan','017115'=>'Macon','017123'=>'Marshall','017129'=>'Menard','017139'=>'Moultrie','017143'=>'Peoria','017155'=>'Putnam','017175'=>'Stark','017179'=>'Tazewell','017197'=>'Will','017203'=>'Woodford'); }
function r9_all_monitored_counties(){ return r9_primary_counties()+r9_neighbor_counties(); }
function r9_alert_rank($event,$severity){$e=strtolower($event);if(strpos($e,'tornado warning')!==false)return 110;if(strpos($e,'severe thunderstorm warning')!==false||strpos($e,'flash flood warning')!==false)return 105;if(strpos($e,'warning')!==false)return 90;if(strpos($e,'watch')!==false)return 75;if(strpos($e,'advisory')!==false)return 55;return strtolower($severity)==='extreme'?100:30;}
function r9_extract_counties($p){$all=r9_all_monitored_counties();$pri=r9_primary_counties();$m=array();foreach(($p['geocode']['SAME']??array()) as $c)if(isset($all[$c]))$m[$c]=$all[$c];foreach(($p['affectedZones']??array()) as $z)if(preg_match('/ILC(\d{3})$/',$z,$x)){ $c='017'.$x[1];if(isset($all[$c]))$m[$c]=$all[$c];}$tier='neighbor';foreach(array_keys($m) as $c)if(isset($pri[$c])){$tier='primary';break;}return array($m,$tier);}

add_action('rest_api_init',function(){register_rest_route('r9/v2','/conditions',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>'r9_conditions_endpoint'));register_rest_route('r9/v2','/city-forecast',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>'r9_city_forecast_endpoint','args'=>array('city'=>array('required'=>true,'sanitize_callback'=>'sanitize_text_field'))));register_rest_route('r9/v2','/alerts',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>'r9_alerts_endpoint'));register_rest_route('r9/v4','/status',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>'r9_status_endpoint'));register_rest_route('r9/v2','/alert-detail',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>'r9_alert_detail_endpoint','args'=>array('url'=>array('required'=>true,'sanitize_callback'=>'esc_url_raw'))));});
function r9_alerts_endpoint(){
 $cached=get_transient('r9_active_alerts_v201');if(false!==$cached)return rest_ensure_response($cached);
 $r=wp_safe_remote_get('https://api.weather.gov/alerts/active?area=IL',array('timeout'=>15,'headers'=>array('Accept'=>'application/geo+json','User-Agent'=>'Region9Weather/2.0.4 (https://region9weather.com)')));
 if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return rest_ensure_response(array('ok'=>false,'alerts'=>array()));
 $j=json_decode(wp_remote_retrieve_body($r),true);$a=array();foreach(($j['features']??array()) as $f){$p=$f['properties']??array();list($m,$tier)=r9_extract_counties($p);if(!$m)continue;$event=$p['event']??'Weather Alert';$sev=$p['severity']??'Unknown';$a[]=array('event'=>sanitize_text_field($event),'headline'=>sanitize_text_field($p['headline']??$event),'instruction'=>wp_strip_all_tags($p['instruction']??''),'severity'=>sanitize_text_field($sev),'counties'=>array_values($m),'tier'=>$tier,'ends'=>sanitize_text_field(($p['ends']??'')?:($p['expires']??'')),'url'=>esc_url_raw($p['@id']??($f['id']??'')),'rank'=>r9_alert_rank($event,$sev)+($tier==='primary'?20:0));}usort($a,function($x,$y){return $y['rank']<=>$x['rank'];});$out=array('ok'=>true,'alerts'=>$a);set_transient('r9_active_alerts_v201',$out,60);return rest_ensure_response($out);
}

function r9_alert_detail_endpoint(WP_REST_Request $request){
 $url=esc_url_raw((string)$request->get_param('url'));
 $parts=wp_parse_url($url);
 if(!$url||empty($parts['host'])||strtolower($parts['host'])!=='api.weather.gov') return new WP_Error('invalid_alert','Invalid alert source.',array('status'=>400));
 $cache='r9_alert_detail_'.md5($url);$cached=get_transient($cache);if(false!==$cached)return rest_ensure_response($cached);
 $r=wp_safe_remote_get($url,array('timeout'=>15,'headers'=>array('Accept'=>'application/geo+json','User-Agent'=>'Region9Weather/2.0.5 (https://region9weather.com)')));
 if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)return new WP_Error('alert_unavailable','The official alert is temporarily unavailable.',array('status'=>502));
 $j=json_decode(wp_remote_retrieve_body($r),true);$p=$j['properties']??array();list($m,$tier)=r9_extract_counties($p);
 $event=sanitize_text_field($p['event']??'Weather Alert');$severity=sanitize_text_field($p['severity']??'Unknown');$urgency=sanitize_text_field($p['urgency']??'Unknown');$certainty=sanitize_text_field($p['certainty']??'Unknown');
 $rank=r9_alert_rank($event,$severity)+($tier==='primary'?20:0);
 $level='limited';if($rank>=120)$level='significant';elseif($rank>=95)$level='elevated';elseif($rank<60)$level='low';
 $out=array('ok'=>true,'alert'=>array(
  'id'=>esc_url_raw($p['@id']??($j['id']??$url)),'url'=>$url,'event'=>$event,'headline'=>sanitize_text_field($p['headline']??$event),'description'=>wp_kses_post($p['description']??''),'instruction'=>wp_kses_post($p['instruction']??''),'areaDesc'=>sanitize_text_field($p['areaDesc']??''),'counties'=>array_values($m),'tier'=>$tier,'severity'=>$severity,'urgency'=>$urgency,'certainty'=>$certainty,'level'=>$level,'sender'=>sanitize_text_field($p['senderName']??'National Weather Service'),'sent'=>sanitize_text_field($p['sent']??''),'effective'=>sanitize_text_field($p['effective']??''),'onset'=>sanitize_text_field($p['onset']??''),'ends'=>sanitize_text_field(($p['ends']??'')?:($p['expires']??'')),'response'=>sanitize_text_field($p['response']??''),'parameters'=>$p['parameters']??array()
 ));set_transient($cache,$out,60);return rest_ensure_response($out);
}

function r9_conditions_endpoint(){
 $cities=array('Kankakee'=>array(41.1200,-87.8612),'Watseka'=>array(40.7761,-87.7364),'Pontiac'=>array(40.8809,-88.6298),'Paxton'=>array(40.4603,-88.0953),'Bloomington'=>array(40.4842,-88.9937),'Clinton'=>array(40.1536,-88.9645),'Monticello'=>array(40.0278,-88.5734),'Champaign'=>array(40.1164,-88.2434),'Danville'=>array(40.1245,-87.6300));$out=array();
 foreach($cities as $city=>$ll){$cache='r9_obs_'.sanitize_key($city);$d=get_transient($cache);if(false===$d){$p=wp_remote_get('https://api.weather.gov/points/'.$ll[0].','.$ll[1],array('timeout'=>8,'headers'=>array('User-Agent'=>'Region9Weather/2.0.4 region9weather.com')));if(!is_wp_error($p)&&wp_remote_retrieve_response_code($p)===200){$pj=json_decode(wp_remote_retrieve_body($p),true);$u=$pj['properties']['observationStations']??'';if($u){$s=wp_remote_get($u,array('timeout'=>8,'headers'=>array('User-Agent'=>'Region9Weather/2.0.4')));$sj=json_decode(wp_remote_retrieve_body($s),true);$sid=$sj['features'][0]['id']??'';if($sid){$o=wp_remote_get($sid.'/observations/latest',array('timeout'=>8,'headers'=>array('User-Agent'=>'Region9Weather/2.0.4')));$pr=(json_decode(wp_remote_retrieve_body($o),true)['properties']??array());$c=$pr['temperature']['value']??null;$dp=$pr['dewpoint']['value']??null;$d=array('city'=>$city,'temp'=>$c===null?'--':round($c*9/5+32),'dewpoint'=>$dp===null?'--':round($dp*9/5+32),'text'=>$pr['textDescription']??'Unavailable','icon'=>esc_url_raw($pr['icon']??''),'wind'=>$pr['windSpeed']['value']===null?'--':round($pr['windSpeed']['value']*.621371),'gust'=>$pr['windGust']['value']===null?'--':round($pr['windGust']['value']*.621371),'humidity'=>isset($pr['relativeHumidity']['value'])&&$pr['relativeHumidity']['value']!==null?round($pr['relativeHumidity']['value']):'--','lat'=>$ll[0],'lon'=>$ll[1],'forecast_page'=>home_url('/city-forecast/?city='.rawurlencode($city)));set_transient($cache,$d,10*MINUTE_IN_SECONDS);}}}}if(!$d)$d=array('city'=>$city,'temp'=>'--','dewpoint'=>'--','text'=>'Temporarily unavailable','icon'=>'','wind'=>'--','gust'=>'--','humidity'=>'--','lat'=>$ll[0],'lon'=>$ll[1],'forecast_page'=>home_url('/city-forecast/?city='.rawurlencode($city)));$out[]=$d;}return rest_ensure_response($out);
}

function r9_city_forecast_endpoint(WP_REST_Request $request){
 $cities=array('Kankakee'=>array(41.1200,-87.8612),'Watseka'=>array(40.7761,-87.7364),'Pontiac'=>array(40.8809,-88.6298),'Paxton'=>array(40.4603,-88.0953),'Bloomington'=>array(40.4842,-88.9937),'Clinton'=>array(40.1536,-88.9645),'Monticello'=>array(40.0278,-88.5734),'Champaign'=>array(40.1164,-88.2434),'Danville'=>array(40.1245,-87.6300));
 $requested=trim((string)$request->get_param('city'));$city='';foreach($cities as $name=>$ll){if(strcasecmp($name,$requested)===0){$city=$name;break;}}
 if(!$city)return new WP_Error('invalid_city','Select a supported Region 9 city.',array('status'=>400));
 $ll=$cities[$city];$cache='r9_fc_'.sanitize_key($city);$cached=get_transient($cache);if(false!==$cached)return rest_ensure_response($cached);
 $headers=array('User-Agent'=>'Region9Weather/3.0.4 region9weather.com','Accept'=>'application/geo+json');
 $point=wp_remote_get('https://api.weather.gov/points/'.$ll[0].','.$ll[1],array('timeout'=>10,'headers'=>$headers));
 if(is_wp_error($point)||wp_remote_retrieve_response_code($point)!==200)return new WP_Error('forecast_unavailable','The official forecast is temporarily unavailable.',array('status'=>503));
 $props=json_decode(wp_remote_retrieve_body($point),true)['properties']??array();$url=$props['forecast']??'';
 if(!$url)return new WP_Error('forecast_unavailable','The official forecast URL was not returned.',array('status'=>503));
 $resp=wp_remote_get($url,array('timeout'=>10,'headers'=>$headers));if(is_wp_error($resp)||wp_remote_retrieve_response_code($resp)!==200)return new WP_Error('forecast_unavailable','The official forecast is temporarily unavailable.',array('status'=>503));
 $fp=json_decode(wp_remote_retrieve_body($resp),true)['properties']??array();$periods=array();foreach(array_slice($fp['periods']??array(),0,14) as $x){$periods[]=array('name'=>sanitize_text_field($x['name']??''),'temperature'=>intval($x['temperature']??0),'temperatureUnit'=>sanitize_text_field($x['temperatureUnit']??'F'),'windSpeed'=>sanitize_text_field($x['windSpeed']??''),'windDirection'=>sanitize_text_field($x['windDirection']??''),'shortForecast'=>sanitize_text_field($x['shortForecast']??''),'detailedForecast'=>sanitize_text_field($x['detailedForecast']??''),'icon'=>esc_url_raw($x['icon']??''),'isDaytime'=>!empty($x['isDaytime']));}
 $out=array('city'=>$city,'updated'=>sanitize_text_field($fp['updated']??''),'periods'=>$periods,'source'=>$url);set_transient($cache,$out,15*MINUTE_IN_SECONDS);return rest_ensure_response($out);
}

function r9_product_catalog(){return array(
 'daily'=>array('Daily Forecast','Today, tonight, and the seven-day outlook.'),'hazards'=>array('Hazards','Severe, flooding, winter, heat, and other hazards.'),'temperature-outlook'=>array('Temperature Outlook','Heat, cold, frost, and freeze planning.'),'agriculture'=>array('Agriculture','Fieldwork, spraying, crops, and livestock.'),'travel-outdoor'=>array('Travel & Outdoor','Commutes, rural roads, events, and lightning.'),'precipitation-outlook'=>array('Precipitation','Forecast rainfall, observed rain, and dryness.'),'special'=>array('Special Briefs','Confidence and decision support.'),'severe-weather'=>array('Severe Weather','Severe outlooks, storm timing, threat details, and safety information.'),'anxiety'=>array('Storm Anxiety','Calm, public-friendly guidance before and during threatening weather.')
);}
function r9_studio_pages(){return array('daily'=>'Forecast','about'=>'About','severe-weather'=>'Severe Weather','hazards'=>'Hazards','temperature-outlook'=>'Temperature','precipitation-outlook'=>'Precipitation','travel-outdoor'=>'Travel','agriculture'=>'Agriculture','anxiety'=>'Anxiety','radar'=>'Radar','alerts'=>'Alert Center','storm-timing'=>'Storm Timing','threat-breakdown'=>'Threat Breakdown','watches-warnings'=>'Watches & Warnings','special'=>'Special Briefs','contact'=>'Contact','city-forecast'=>'City Forecast');}
function r9_menu_fallback(){
 $items=array(''=>'Home','about'=>'About','severe-weather'=>'Severe Weather','hazards'=>'Hazards','temperature-outlook'=>'Temperature','precipitation-outlook'=>'Precipitation','travel-outdoor'=>'Travel','agriculture'=>'Agriculture','anxiety'=>'Anxiety','radar'=>'Radar');
 echo '<ul>'; foreach($items as $slug=>$title){$url=$slug?home_url('/'.$slug.'/'):home_url('/');echo '<li><a href="'.esc_url($url).'">'.esc_html($title).'</a></li>';} echo '</ul>';
}
add_shortcode('region9_studio_home',function(){ob_start();include get_stylesheet_directory().'/template-parts-studio-home.php';return ob_get_clean();});
add_shortcode('region9_alert_center',function(){return '<section class="r9-alert-center-intro"><span class="r9-eyebrow">REGION 9 LIVE SAFETY</span><h2>Active Alert Center</h2><p>Official National Weather Service alerts affecting Region 9 and monitored surrounding counties appear here automatically. Select an alert for localized impacts, safety actions, radar, and the full official bulletin.</p></section><div id="r9-alert-center" class="r9-alert-center"><div class="r9-alert-card clear"><h3>Checking live alerts…</h3><p>Connecting to the official NWS alert feed.</p></div></div>';});


if ( ! defined('R9WS_VERSION') ) define('R9WS_VERSION','5.0.0');
if ( ! defined('R9WS_TIMEZONE') ) define('R9WS_TIMEZONE','America/Chicago');

add_action('after_setup_theme', function(){
    if(function_exists('date_default_timezone_set')){
        @date_default_timezone_set(R9WS_TIMEZONE);
    }
});

function r9ws_latest_update_string(){
    return wp_date('F j, Y g:i A T', null, wp_timezone());
}
