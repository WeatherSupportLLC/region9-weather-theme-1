<?php
if (!defined('ABSPATH')) exit;

function r9ls_theme_rc1_active(){
    if (defined('R9LS_VERSION') && version_compare((string)R9LS_VERSION,'17.0.0-rc.1','>=')) return true;
    if (class_exists('R9LS_Plugin') && defined('R9LS_VERSION')) return true;
    return function_exists('r9ls_get_public_products') && function_exists('r9ls_public_settings');
}
function r9ls_theme_admin_url(){return r9ls_theme_rc1_active()?admin_url('admin.php?page=r9ls'):admin_url('admin.php?page=r9-studio');}
function r9ls_theme_product_map(){return array(
 'daily'=>array('morning-weather-brief','todays-forecast','seven-day-forecast','evening-weather-update'),
 'hazards'=>array('weekly-weather-hazards','severe-weather-outlook','storm-timing','threat-breakdown','watch-warning-explainer'),
 'temperature-health'=>array('seven-day-heat-outlook','heat-safety-alert','wind-chill-outlook','frost-freeze-outlook'),
 'agriculture'=>array('agriculture-weather-outlook','spray-window-forecast','fieldwork-outlook','livestock-weather-stress'),
 'travel-outdoor'=>array('rural-travel-outlook','commute-forecast','outdoor-event-planner','lightning-risk-outlook'),
 'rain-drought-water'=>array('forecast-rainfall','observed-rainfall-totals','drought-dryness-update'),
 'specialty'=>array('storm-anxiety-outlook','what-were-watching','forecast-confidence-meter','decision-support-brief'),
 'temperature-outlook'=>array('seven-day-heat-outlook','wind-chill-outlook','frost-freeze-outlook','forecast-confidence-meter'),
 'precipitation-outlook'=>array('forecast-rainfall','observed-rainfall-totals','drought-dryness-update'),
 'severe-weather'=>array('severe-weather-outlook','threat-breakdown','storm-timing','watch-warning-explainer'),
);}
function r9ls_theme_product_aliases(){return array(
 'morning-brief'=>'morning-weather-brief','headlines'=>'what-were-watching','severe-weather-risk'=>'severe-weather-outlook',
 'travel'=>'rural-travel-outlook','agriculture'=>'agriculture-weather-outlook','fieldwork'=>'fieldwork-outlook','spraying'=>'spray-window-forecast',
 'harvest'=>'agriculture-weather-outlook','livestock'=>'livestock-weather-stress','outdoor'=>'outdoor-event-planner','schools'=>'outdoor-event-planner',
 'construction'=>'rural-travel-outlook','forecast-confidence'=>'forecast-confidence-meter','watching'=>'what-were-watching'
);}
function r9ls_theme_enabled(){return r9ls_theme_rc1_active()||function_exists('r9ls_get_published_product')||function_exists('r9ls_get_public_products');}
function r9ls_theme_products(){static $cache=null;if($cache!==null)return $cache;if(!r9ls_theme_enabled())return $cache=array();if(function_exists('r9ls_get_public_products')){$p=r9ls_get_public_products();}else{$all=function_exists('r9ls_get_published_products')?r9ls_get_published_products():array();$p=array();foreach((array)$all as $id=>$x){if(($x['approval_state']??'')==='approved'&&($x['publication_state']??'')==='published')$p[$id]=$x;}}return $cache=is_array($p)?$p:array();}
function r9ls_theme_product($id){$all=r9ls_theme_products();$id=sanitize_key($id);$aliases=r9ls_theme_product_aliases();$id=$aliases[$id]??$id;return $all[$id]??null;}
function r9ls_theme_is_stale($p){$threshold=12*HOUR_IN_SECONDS;if(function_exists('r9ls_public_settings')){$s=r9ls_public_settings();$threshold=max(HOUR_IN_SECONDS,(int)($s['stale_data_threshold_minutes']??720)*MINUTE_IN_SECONDS);} $ts=strtotime($p['updated_at']??'');return !$ts||(time()-$ts)>$threshold;}
function r9ls_theme_status($p){if(!$p)return array('state'=>'unavailable','label'=>r9ls_theme_fallback_language());return r9ls_theme_is_stale($p)?array('state'=>'stale','label'=>'Stale approved publication'):array('state'=>'fresh','label'=>'Approved publication');}
function r9ls_theme_fallback_language(){if(function_exists('r9ls_public_settings')){$s=r9ls_public_settings();if(!empty($s['fallback_language']))return $s['fallback_language'];}return 'Region 9 Live Studio publication is temporarily unavailable.';}
function r9ls_theme_risk_badge($risk){$label=is_array($risk)?($risk['label']??'None'):(string)$risk;$class='r9-risk-'.sanitize_html_class(strtolower($label));return '<span class="r9ls-risk-badge '.esc_attr($class).'">'.esc_html($label).' risk</span>';}
function r9ls_theme_confidence($p){$c=max(0,min(100,(int)($p['confidence']??0)));return '<div class="r9ls-confidence" aria-label="Forecast confidence '.$c.' percent"><span>Confidence</span><meter min="0" max="100" value="'.esc_attr($c).'">'.esc_html($c).'%</meter><strong>'.esc_html($c).'%</strong></div>';}
function r9ls_theme_counties($p){$c=array_filter((array)($p['affected_counties']??array()));if(!$c)return '<p class="r9ls-counties">Affected counties: Region 9 area as applicable.</p>';return '<p class="r9ls-counties">Affected counties: '.esc_html(implode(', ',$c)).'.</p>';}
function r9ls_theme_time($p){$t=$p['timing']['label']??($p['timing']['local']??'Timing not specified');return '<p class="r9ls-timing"><strong>Timing:</strong> '.esc_html($t).'</p>';}
function r9ls_theme_updated($p){$s=r9ls_theme_status($p);$u=$p?($p['updated_at']??''):'Unavailable';$txt=$p&&strtotime($u)?wp_date('M j, Y g:i A T',strtotime($u)):$u;return '<p class="r9ls-updated r9ls-source-'.esc_attr($s['state']).'"><strong>'.esc_html($s['label']).'</strong> · Last updated: '.esc_html($txt).'</p>';}
function r9ls_theme_card($id){$p=r9ls_theme_product($id);if(!$p)return '<article class="r9-panel r9ls-product-card is-unavailable"><h3>'.esc_html(ucwords(str_replace('-',' ',$id))).'</h3><p>'.esc_html(r9ls_theme_fallback_language()).'</p></article>';$graphic=!empty($p['graphic_url'])?'<img class="r9-image" src="'.esc_url($p['graphic_url']).'" alt="'.esc_attr($p['title']).' graphic">':r9_media_placeholder(($p['title']??'Forecast').' Graphic');return '<article class="r9-panel r9ls-product-card" data-product="'.esc_attr($p['product_id']??$id).'"><div class="r9-panel-head"><div><span class="r9-eyebrow">REGION 9 APPROVED PRODUCT</span><h3>'.esc_html($p['title']??$id).'</h3></div>'.r9ls_theme_risk_badge($p['risk']??array()).'</div>'.$graphic.'<p class="r9ls-summary">'.esc_html($p['summary']??'').'</p>'.r9ls_theme_confidence($p).r9ls_theme_time($p).r9ls_theme_counties($p).'<details class="r9ls-discussion"><summary>Full discussion</summary><div>'.wp_kses_post(wpautop($p['discussion']??'')).'</div></details>'.r9ls_theme_updated($p).'</article>';}
function r9ls_theme_product_grid($slug){$map=r9ls_theme_product_map();$ids=$map[$slug]??array();if(!$ids)return '';return '<section class="r9ls-product-grid">'.implode('',array_map('r9ls_theme_card',$ids)).'</section>';}
function r9ls_theme_home_value($id,$field='summary',$fallback='Unavailable'){$p=r9ls_theme_product($id);return $p?($p[$field]??$fallback):$fallback;}
function r9ls_theme_latest_publication_time(){$latest=0;foreach(r9ls_theme_products() as $p){$latest=max($latest,(int)strtotime($p['updated_at']??''));}return $latest?wp_date('M j, Y g:i A T',$latest):'Unavailable';}
add_shortcode('r9ls_public_product',function($atts){$a=shortcode_atts(array('id'=>'todays-forecast'),$atts);return r9ls_theme_card($a['id']);});
