<?php
if (!defined('ABSPATH')) exit;

function r9ls_theme_rc1_active(){
    if (defined('R9LS_VERSION') && version_compare((string) R9LS_VERSION, '17.0.0-rc.1', '>=')) { return true; }
    if (class_exists('R9LS_Plugin') && defined('R9LS_VERSION')) { return true; }
    return function_exists('r9ls_get_public_products') && function_exists('r9ls_public_settings');
}
function r9ls_theme_admin_url(){ return r9ls_theme_rc1_active() ? admin_url('admin.php?page=r9ls') : admin_url('admin.php?page=r9-studio'); }
function r9ls_theme_product_map(){return array(
 'daily'=>array('morning-brief','todays-forecast','seven-day-forecast','current-conditions','community-weather-brief'),
 'hazards'=>array('weekly-weather-hazards','severe-weather-risk','threat-breakdown','storm-timing','lightning-risk','tornado-risk-outlook','damaging-wind-outlook','large-hail-outlook','flash-flood-outlook','watching'),
 'agriculture'=>array('agriculture','rural-planning-center'),
 'travel-outdoor'=>array('travel','outdoor','schools'),
 'special'=>array('forecast-confidence','decision-support-brief','watching','decision-impact-dashboard','community-weather-brief'),
 'temperature-outlook'=>array('heat-outlook','winter-weather-outlook','six-to-ten-day-outlook','eight-to-fourteen-day-outlook','monthly-outlook','forecast-confidence'),
 'precipitation-outlook'=>array('forecast-rainfall','flash-flood-outlook'),
 'severe-weather'=>array('severe-weather-risk','threat-breakdown','storm-timing','lightning-risk','tornado-risk-outlook','damaging-wind-outlook','large-hail-outlook','flash-flood-outlook'),
);}
function r9ls_theme_enabled(){return r9ls_theme_rc1_active()||function_exists('r9ls_get_published_product')||function_exists('r9ls_get_public_products');}
function r9ls_theme_products(){static $cache=null;if($cache!==null)return $cache;if(!r9ls_theme_enabled())return $cache=array();if(function_exists('r9ls_get_public_products')){$p=r9ls_get_public_products();}else{$all=function_exists('r9ls_get_published_products')?r9ls_get_published_products():array();$p=array();foreach((array)$all as $id=>$x){if(($x['approval_state']??'')==='approved'&&($x['publication_state']??'')==='published')$p[$id]=$x;}}return $cache=is_array($p)?$p:array();}
function r9ls_theme_product($id){$all=r9ls_theme_products();$id=sanitize_key($id);return $all[$id]??null;}
function r9ls_theme_is_stale($p){$threshold=12*HOUR_IN_SECONDS;if(function_exists('r9ls_public_settings')){$s=r9ls_public_settings();$threshold=max(HOUR_IN_SECONDS,(int)($s['stale_data_threshold_minutes']??720)*MINUTE_IN_SECONDS);} $ts=strtotime($p['updated_at']??'');return !$ts||(time()-$ts)>$threshold;}
function r9ls_theme_status($p){if(!$p)return array('state'=>'unavailable','label'=>r9ls_theme_fallback_language());return r9ls_theme_is_stale($p)?array('state'=>'stale','label'=>'Stale approved publication'):array('state'=>'fresh','label'=>'Approved publication');}
function r9ls_theme_fallback_language(){if(function_exists('r9ls_public_settings')){$s=r9ls_public_settings();if(!empty($s['fallback_language']))return $s['fallback_language'];}return 'Region 9 Live Studio publication is temporarily unavailable.';}
function r9ls_theme_risk_badge($risk){$label=is_array($risk)?($risk['label']??'None'):(string)$risk;$class='r9-risk-'.sanitize_html_class(strtolower($label));return '<span class="r9ls-risk-badge '.esc_attr($class).'">'.esc_html($label).' risk</span>';}
function r9ls_theme_confidence($p){$c=max(0,min(100,(int)($p['confidence']??0)));return '<div class="r9ls-confidence" aria-label="Forecast confidence '.$c.' percent"><span>Confidence</span><meter min="0" max="100" value="'.esc_attr($c).'">'.esc_html($c).'%</meter><strong>'.esc_html($c).'%</strong></div>';}
function r9ls_theme_counties($p){$c=array_filter((array)($p['affected_counties']??array()));if(!$c)return '<p class="r9ls-counties">Affected counties: Region 9 area as applicable.</p>';return '<p class="r9ls-counties">Affected counties: '.esc_html(implode(', ',$c)).'.</p>';}
function r9ls_theme_time($p){$t=$p['timing']['label']??($p['timing']['local']??'Timing not specified');return '<p class="r9ls-timing"><strong>Timing:</strong> '.esc_html($t).'</p>';}
function r9ls_theme_updated($p){$s=r9ls_theme_status($p);$u=$p?($p['updated_at']??''):'Unavailable';$txt=$p&&strtotime($u)?wp_date('M j, Y g:i A T',strtotime($u)):$u;return '<p class="r9ls-updated r9ls-source-'.esc_attr($s['state']).'"><strong>'.esc_html($s['label']).'</strong> · Last updated: '.esc_html($txt).'</p>';}
function r9ls_theme_card($id){$p=r9ls_theme_product($id);if(!$p)return '<article class="r9-panel r9ls-product-card is-unavailable"><h3>'.esc_html(ucwords(str_replace('-',' ',$id))).'</h3><p>'.esc_html(r9ls_theme_fallback_language()).'</p></article>';$graphic=!empty($p['graphic_url'])?'<img class="r9-image" src="'.esc_url($p['graphic_url']).'" alt="'.esc_attr($p['title']).' graphic">':r9_media_placeholder(($p['title']??'Forecast').' Graphic');return '<article class="r9-panel r9ls-product-card" data-product="'.esc_attr($id).'"><div class="r9-panel-head"><div><span class="r9-eyebrow">REGION 9 APPROVED PRODUCT</span><h3>'.esc_html($p['title']??$id).'</h3></div>'.r9ls_theme_risk_badge($p['risk']??array()).'</div>'.$graphic.'<p class="r9ls-summary">'.esc_html($p['summary']??'').'</p>'.r9ls_theme_confidence($p).r9ls_theme_time($p).r9ls_theme_counties($p).'<details class="r9ls-discussion"><summary>Full discussion</summary><div>'.wp_kses_post(wpautop($p['discussion']??'')).'</div></details>'.r9ls_theme_updated($p).'</article>';}
function r9ls_theme_product_grid($slug){$map=r9ls_theme_product_map();$ids=$map[$slug]??array();if(!$ids)return '';return '<section class="r9ls-product-grid">'.implode('',array_map('r9ls_theme_card',$ids)).'</section>';}
function r9ls_theme_home_value($id,$field='summary',$fallback='Unavailable'){$p=r9ls_theme_product($id);return $p?($p[$field]??$fallback):$fallback;}
function r9ls_theme_latest_publication_time(){$latest=0;foreach(r9ls_theme_products() as $p){$latest=max($latest,(int)strtotime($p['updated_at']??''));}return $latest?wp_date('M j, Y g:i A T',$latest):'Unavailable';}
add_shortcode('r9ls_public_product',function($atts){$a=shortcode_atts(array('id'=>'todays-forecast'),$atts);return r9ls_theme_card($a['id']);});

/*
 * Region 9 public-product bridge, 17.0.1.
 *
 * The automation engine keeps a canonical option-based product store while the
 * production connector publishes the finished 28 graphic products as the
 * r9ls_product post type.  The public theme must prefer the newest published
 * post-type product so a connector assignment is immediately reflected on the
 * website.  The option product remains the fallback for fields the production
 * record does not provide.
 */
function r9_public_bridge_title_map(){
    return array(
        'morning-weather-brief'=>'Morning Weather Brief',
        'todays-forecast'=>"Today’s Forecast",
        'seven-day-forecast'=>'7-Day Forecast',
        'what-we-are-watching'=>"What We’re Watching",
        'weekly-weather-hazards'=>'Weekly Weather Hazards',
        'agriculture-weather-outlook'=>'Agriculture Weather Outlook',
        'rural-travel-outlook'=>'Rural Travel Outlook',
        'outdoor-event-planner'=>'Outdoor Event Planner',
        'forecast-rainfall'=>'Forecast Rainfall',
        'lightning-risk'=>'Lightning Risk Outlook',
        'tornado-risk-outlook'=>'Tornado Risk Outlook',
        'damaging-wind-outlook'=>'Damaging Wind Outlook',
        'large-hail-outlook'=>'Large Hail Outlook',
        'flash-flood-outlook'=>'Flash Flood Outlook',
        'severe-weather-outlook'=>'Severe Weather Outlook',
        'severe-weather-threat-breakdown'=>'Severe Weather Threat Breakdown',
        'severe-weather-timeline'=>'Severe Weather Timeline',
        'six-to-ten-day-outlook'=>'6–10 Day Outlook',
        'eight-to-fourteen-day-outlook'=>'8–14 Day Outlook',
        'monthly-outlook'=>'Monthly Outlook',
        'decision-impact-dashboard'=>'Decision Impact Dashboard',
        'rural-planning-center'=>'Rural Planning Center',
        'current-conditions'=>'Current Conditions',
        'forecast-confidence'=>'Forecast Confidence',
        'heat-outlook'=>'Heat Outlook',
        'winter-weather-outlook'=>'Winter Weather Outlook',
        'school-weather-planner'=>'School Weather Planner',
        'community-weather-brief'=>'Community Weather Brief',
    );
}
function r9_public_bridge_aliases(){
    return array(
        'morning-weather-brief'=>'morning-brief',
        'what-we-are-watching'=>'watching',
        'agriculture-weather-outlook'=>'agriculture',
        'rural-travel-outlook'=>'travel',
        'outdoor-event-planner'=>'outdoor',
        'severe-weather-outlook'=>'severe-weather-risk',
        'severe-weather-threat-breakdown'=>'threat-breakdown',
        'severe-weather-timeline'=>'storm-timing',
        'school-weather-planner'=>'schools',
    );
}
function r9_public_bridge_meta_scalar($post_id,$keys){
    foreach((array)$keys as $key){
        $value=get_post_meta($post_id,$key,true);
        if(is_scalar($value)&&trim((string)$value)!=='') return trim((string)$value);
    }
    return '';
}
function r9_public_bridge_product_code($post){
    $code=r9_public_bridge_meta_scalar($post->ID,array('code','product_code','r9ls_product_code','_r9ls_product_code','r9_product_code','_r9_product_code'));
    if($code){$code=sanitize_key($code);if(isset(r9_public_bridge_title_map()[$code]))return $code;}
    $title=wp_strip_all_tags((string)$post->post_title);
    foreach(r9_public_bridge_title_map() as $candidate=>$label){
        if(stripos($title,$label)===0)return $candidate;
    }
    $slug=(string)$post->post_name;
    foreach(array_keys(r9_public_bridge_title_map()) as $candidate){
        $needle=$candidate;
        if($candidate==='six-to-ten-day-outlook')$needle='6-10-day-outlook';
        if($candidate==='eight-to-fourteen-day-outlook')$needle='8-14-day-outlook';
        if(strpos($slug,$needle)===0)return $candidate;
    }
    return '';
}
function r9_public_bridge_graphic($post_id){
    $id=(int)get_post_thumbnail_id($post_id);
    if($id){$url=wp_get_attachment_image_url($id,'full');if($url)return array($id,$url);}
    $specific=array('graphic_id','r9ls_graphic_id','_r9ls_graphic_id','production_graphic_id','_production_graphic_id','image_id','_image_id');
    foreach($specific as $key){$id=(int)get_post_meta($post_id,$key,true);if($id){$url=wp_get_attachment_image_url($id,'full');if($url)return array($id,$url);}}
    $meta=get_post_meta($post_id);
    foreach((array)$meta as $key=>$values){
        $lk=strtolower((string)$key);if(strpos($lk,'graphic')===false&&strpos($lk,'image')===false)continue;
        foreach((array)$values as $value){
            if(is_numeric($value)){$id=(int)$value;$url=wp_get_attachment_image_url($id,'full');if($url)return array($id,$url);}
            if(is_string($value)&&filter_var($value,FILTER_VALIDATE_URL))return array(0,esc_url_raw($value));
        }
    }
    return array(0,'');
}
function r9_public_bridge_risk($post_id,$fallback=array('label'=>'None')){
    $raw=r9_public_bridge_meta_scalar($post_id,array('risk','r9ls_risk','_r9ls_risk','risk_level','_risk_level'));
    if(!$raw){
        foreach((array)get_post_meta($post_id) as $key=>$values){if(stripos($key,'risk')===false)continue;$v=reset($values);if(is_scalar($v)&&trim((string)$v)!==''){$raw=(string)$v;break;}}
    }
    if(!$raw)return is_array($fallback)?$fallback:array('label'=>(string)$fallback);
    $key=sanitize_key($raw);$labels=array('none'=>'None','low'=>'Low','limited'=>'Limited','elevated'=>'Elevated','significant'=>'Significant');
    return array('label'=>$labels[$key]??ucwords(str_replace(array('-','_'),' ',$raw)));
}
function r9_public_bridge_confidence($post_id,$fallback=0){
    $raw=r9_public_bridge_meta_scalar($post_id,array('confidence','r9ls_confidence','_r9ls_confidence','forecast_confidence'));
    if(!$raw){foreach((array)get_post_meta($post_id) as $key=>$values){if(stripos($key,'confidence')===false)continue;$v=reset($values);if(is_scalar($v)&&trim((string)$v)!==''){$raw=(string)$v;break;}}}
    if(is_numeric($raw))return max(0,min(100,(int)$raw));
    $map=array('high'=>90,'medium'=>70,'moderate'=>70,'low'=>50,'official warning'=>100);
    return $map[sanitize_key($raw)]??(int)$fallback;
}
function r9_public_bridge_normalize($post,$base=array(),$code=''){
    list($graphic_id,$graphic_url)=r9_public_bridge_graphic($post->ID);
    $discussion=trim((string)$post->post_content);if($discussion==='')$discussion=(string)($base['discussion']??'');
    $summary=trim((string)$post->post_excerpt);if($summary==='')$summary=wp_trim_words(wp_strip_all_tags($discussion),45,'…');
    $valid=r9_public_bridge_meta_scalar($post->ID,array('valid_time','r9ls_valid_time','_r9ls_valid_time'));
    $updated=get_post_modified_time('Y-m-d H:i:s',false,$post,true);
    return array_merge((array)$base,array(
        'product_id'=>$code?:($base['product_id']??sanitize_key($post->post_name)),
        'title'=>get_the_title($post),
        'summary'=>$summary,
        'discussion'=>$discussion,
        'risk'=>r9_public_bridge_risk($post->ID,$base['risk']??array('label'=>'None')),
        'confidence'=>r9_public_bridge_confidence($post->ID,$base['confidence']??0),
        'updated_at'=>$updated?:current_time('mysql'),
        'approval_state'=>'approved',
        'publication_state'=>'published',
        'graphic_id'=>$graphic_id,
        'graphic_url'=>$graphic_url?:($base['graphic_url']??''),
        'timing'=>$valid?array('label'=>$valid,'local'=>$valid):($base['timing']??array('label'=>'Timing not specified','local'=>'')),
    ));
}
function r9_public_bridge_merge_products($products){
    static $running=false;if($running)return $products;$running=true;
    try{
        $base=is_array($products)?$products:array();
        $posts=get_posts(array('post_type'=>'r9ls_product','post_status'=>'publish','posts_per_page'=>200,'orderby'=>'modified','order'=>'DESC','suppress_filters'=>false));
        $seen=array();$aliases=r9_public_bridge_aliases();
        foreach((array)$posts as $post){
            $code=r9_public_bridge_product_code($post);if(!$code||isset($seen[$code]))continue;$seen[$code]=true;
            $alias=$aliases[$code]??$code;$fallback=$base[$alias]??($base[$code]??array());
            $normalized=r9_public_bridge_normalize($post,$fallback,$code);
            $base[$code]=$normalized;
            $base[$alias]=$normalized;
        }
        return $base;
    }finally{$running=false;}
}
add_filter('option_r9ls_published_products','r9_public_bridge_merge_products',20,1);

/* Fresh public alert endpoint.  It uses a new route and cache key so an old
 * page/sidebar cannot keep rendering archived alert posts or an obsolete
 * transient.  Every response also drops alerts whose official expiration is
 * already in the past.
 */
function r9_public_bridge_live_alerts(){
    $cache_key='r9_active_alerts_live_v301';$cached=get_transient($cache_key);
    if(is_array($cached)){
        $cached['alerts']=array_values(array_filter((array)($cached['alerts']??array()),function($a){$ends=$a['ends']??'';return !$ends||!strtotime($ends)||strtotime($ends)>time();}));
        return rest_ensure_response($cached);
    }
    $r=wp_safe_remote_get('https://api.weather.gov/alerts/active?area=IL',array('timeout'=>15,'headers'=>array('Accept'=>'application/geo+json','Cache-Control'=>'no-cache','User-Agent'=>'Region9Weather/17.0.1 (https://region9weather.com)')));
    if(is_wp_error($r)||(int)wp_remote_retrieve_response_code($r)!==200){
        $out=array('ok'=>false,'alerts'=>array(),'updated'=>current_time('c'),'error'=>'Official alert feed temporarily unavailable.');
    }else{
        $json=json_decode(wp_remote_retrieve_body($r),true);$alerts=array();
        foreach((array)($json['features']??array()) as $feature){
            $p=(array)($feature['properties']??array());$ends=(string)(($p['ends']??'')?:($p['expires']??''));
            if($ends&&strtotime($ends)&&strtotime($ends)<=time())continue;
            list($counties,$tier)=r9_extract_counties($p);if(!$counties)continue;
            $event=(string)($p['event']??'Weather Alert');$severity=(string)($p['severity']??'Unknown');
            $alerts[]=array(
                'event'=>sanitize_text_field($event),'headline'=>sanitize_text_field($p['headline']??$event),
                'instruction'=>wp_strip_all_tags($p['instruction']??''),'severity'=>sanitize_text_field($severity),
                'counties'=>array_values($counties),'tier'=>$tier,'ends'=>sanitize_text_field($ends),
                'url'=>esc_url_raw($p['@id']??($feature['id']??'')),'rank'=>r9_alert_rank($event,$severity)+($tier==='primary'?20:0),
            );
        }
        usort($alerts,function($a,$b){return ($b['rank']??0)<=>($a['rank']??0);});
        $out=array('ok'=>true,'alerts'=>$alerts,'updated'=>current_time('c'));
        set_transient($cache_key,$out,30);
    }
    $response=rest_ensure_response($out);
    $response->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0');
    $response->header('Pragma','no-cache');$response->header('Expires','0');
    return $response;
}
add_action('rest_api_init',function(){register_rest_route('r9/v3','/alerts-live',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>'r9_public_bridge_live_alerts'));});

/* Override the older localized endpoint without changing the public design or
 * requiring a second JavaScript bundle.  The existing 90-second UI refresh now
 * calls the fresh active-alert route above.
 */
add_action('wp_enqueue_scripts',function(){
    if(!wp_script_is('r9-studio','enqueued'))return;
    $url=esc_url_raw(rest_url('r9/v3/alerts-live'));
    wp_add_inline_script('r9-studio','window.R9Studio=window.R9Studio||{};window.R9Studio.alerts='.wp_json_encode($url).';','before');
},100);
