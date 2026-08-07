<?php
defined('ABSPATH') || exit;

final class R9LS_Public_Hub {
    private $gis;
    private $alerts;

    public function __construct($gis, $alerts) { $this->gis=$gis; $this->alerts=$alerts; }

    public function hooks() {
        add_action('wp_enqueue_scripts', array($this,'assets'));
        add_action('rest_api_init', array($this,'routes'));
        add_shortcode('r9_weather_intelligence_map', array($this,'map_shortcode'));
        add_shortcode('r9_live_region9_alerts', array($this,'live_shortcode'));
        add_shortcode('r9_latest_weather_crawl', array($this,'crawl_shortcode'));
        add_shortcode('r9_power_outage_tracker', array($this,'outage_shortcode'));
        add_action('r9ls_refresh_authoritative_counties', array($this,'refresh_gis'));
        add_action('init', array($this,'ensure_gis_schedule'));
    }

    public function ensure_public_pages() {
        if (!function_exists('wp_insert_post')) return;
        $pages = array(
            'outdoor-forecast' => array('Outdoor Forecast','Region 9 outdoor activity forecast and decision support.'),
            'working-forecast' => array('Working Forecast','Region 9 weather impacts for outdoor work, construction and field operations.'),
            'agriculture-forecast' => array('Agriculture Forecast','Region 9 agriculture weather impacts, fieldwork and spray-window guidance.'),
            'school-activity-forecast' => array('School Activity Forecast','Weather guidance for school activities, practices, games and outdoor events.'),
            'bus-stop-forecast' => array('Bus Stop Forecast','Weather guidance for students and families waiting for school transportation.'),
            'timeline' => array('Severe Weather Timeline','Region 9 storm arrival, peak-impact and departure timing.'),
        );
        foreach ($pages as $slug=>$page) {
            if (get_page_by_path($slug)) continue;
            wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$page[0],'post_name'=>$slug,'post_content'=>'','comment_status'=>'closed'));
        }
    }

    public function ensure_gis_schedule() {
        if (!wp_next_scheduled('r9ls_refresh_authoritative_counties')) wp_schedule_event(time()+300, 'weekly', 'r9ls_refresh_authoritative_counties');
    }
    public function refresh_gis() { return $this->gis->refresh_authoritative_counties(true); }

    public function assets() {
        wp_enqueue_style('r9ls-public-hub', R9LS_URL.'assets/public-hub.css', array(), R9LS_VERSION);
        wp_enqueue_script('r9ls-public-hub', R9LS_URL.'assets/public-hub.js', array(), R9LS_VERSION, true);
        wp_localize_script('r9ls-public-hub','R9Hub',array(
            'mapEndpoint'=>esc_url_raw(rest_url('region9-live-studio/v1/weather-intelligence-map')),
            'alertUrl'=>esc_url_raw(home_url('/alerts/')),
            'countyBase'=>esc_url_raw(home_url('/city-forecast/')),
        ));
    }

    public function routes() { register_rest_route('region9-live-studio/v1','/weather-intelligence-map',array('methods'=>'GET','callback'=>array($this,'rest_map'),'permission_callback'=>'__return_true')); }
    public function rest_map() { return rest_ensure_response($this->snapshot()); }

    public function snapshot() {
        $live=$this->alerts->live();
        $county_status=array();
        foreach($this->gis->county_names() as $county){$county_status[$county]=array('rank'=>0,'event'=>'All Clear','count'=>0,'alerts'=>array());}
        $map_alerts=array();
        foreach((array)($live['alerts']??array()) as $alert){
            $rank=$this->event_rank($alert['event']??'', $alert['severity']??'');
            foreach((array)($alert['region9_counties']??array()) as $county){
                if(!isset($county_status[$county]))continue;
                $county_status[$county]['count']++;
                $county_status[$county]['alerts'][]=array('id'=>$alert['id']??'','event'=>$alert['event']??'Weather Alert','headline'=>$alert['headline']??'','severity'=>$alert['severity']??'','ends'=>$alert['ends']??($alert['expires']??''),'rank'=>$rank);
                if($rank>$county_status[$county]['rank']){$county_status[$county]['rank']=$rank;$county_status[$county]['event']=$alert['event']??'Weather Alert';}
            }
            $row=$alert;$row['rank']=$rank;$map_alerts[]=$row;
        }
        foreach($county_status as &$status){usort($status['alerts'],function($a,$b){return ($b['rank']??0)<=>($a['rank']??0);});} unset($status);
        $features=array();
        foreach($this->gis->county_geometries() as $name=>$geometry){$features[]=array('type'=>'Feature','properties'=>array('name'=>$name,'NAME'=>$name.' County'),'geometry'=>$geometry);}
        return array('updated'=>current_time('timestamp'),'counties'=>$county_status,'alerts'=>$map_alerts,'geojson'=>array('type'=>'FeatureCollection','name'=>'Region 9 Illinois Counties','features'=>$features),'gis'=>$this->gis->authoritative_meta(),'source'=>'National Weather Service alerts + U.S. Census Bureau TIGERweb county boundaries');
    }

    public function map_shortcode() {
        return '<section class="r9wi-shell" aria-label="Region 9 county alert map"><div class="r9wi-head"><div><span>REGION 9 WEATHER INTELLIGENCE</span><h2>Live 9-County Alert Map</h2><p>Accurate county boundaries are refreshed from U.S. Census Bureau TIGERweb. Click any county for current alerts and county information.</p></div><a href="'.esc_url(home_url('/alerts/')).'">All Region 9 Alerts</a></div><div class="r9wi-body"><div class="r9wi-map" data-r9wi-map role="group" aria-label="Interactive Region 9 county alert map"><div class="r9wi-loading">Loading county alerts…</div></div><aside class="r9wi-side"><div class="r9wi-county-detail" data-r9wi-county-detail><h3>County information</h3><p>Select a county on the map.</p></div><div class="r9wi-active-menu" data-r9wi-summary></div><div class="r9wi-legend"><strong>Alert key</strong><span><i class="r0"></i>All clear</span><span><i class="r1"></i>Statement / Outlook</span><span><i class="r2"></i>Advisory</span><span><i class="r3"></i>Watch</span><span><i class="r4"></i>Warning / Emergency</span><span><i class="polygon"></i>Official NWS polygon</span></div></aside></div><small class="r9wi-source" data-r9wi-source>County geometry source: U.S. Census Bureau TIGERweb. Alerts: National Weather Service.</small></section>';
    }

    public function live_shortcode() {
        $state=$this->alerts->live();$alerts=(array)($state['alerts']??array());
        if(!$alerts)return '<div class="r9-live-alerts r9-all-clear"><strong>LIVE ALERTS — REGION 9 ONLY:</strong> No active NWS watches, warnings or advisories are currently affecting the nine Region 9 counties.</div>';
        $html='<div class="r9-live-alerts" aria-label="Region 9 live alerts"><strong>LIVE ALERTS — REGION 9 ONLY</strong><div class="r9-live-alert-list">';
        foreach(array_slice($alerts,0,12) as $a){$counties=implode(', ',(array)($a['region9_counties']??array()));$html.='<a href="'.esc_url(home_url('/alerts/')).'"><b>'.esc_html($a['event']??'Weather Alert').'</b><span>'.esc_html($counties).'</span></a>';}
        return $html.'</div></div>';
    }

    public function crawl_shortcode() {
        $state=$this->alerts->crawl();$alerts=(array)($state['alerts']??array());$items=array();
        foreach($alerts as $a){$near=($a['scope']??'')==='within-50-miles'?'Nearby: ':'';$where=!empty($a['region9_counties'])?implode(', ',(array)$a['region9_counties']):(($a['distance_to_region9_miles']??null)!==null?round((float)$a['distance_to_region9_miles']).' mi from Region 9':'near Region 9');$items[]='<span><b>'.esc_html($near.($a['event']??'Weather Alert')).'</b> — '.esc_html($where).' — '.esc_html($a['headline']??'').'</span>';}
        if(!$items)$items[]='<span>No active NWS alerts in Region 9 or within 50 miles of the Region 9 boundary.</span>';
        return '<div class="r9-latest-crawl" role="region" aria-label="Latest weather update including alerts within 50 miles"><div class="r9-latest-crawl-label">LATEST WEATHER UPDATE</div><div class="r9-latest-crawl-window"><div class="r9-latest-crawl-track">'.implode('<span class="r9-crawl-separator"> • </span>',$items).'</div></div></div>';
    }

    public function outage_shortcode() {
        $hub=class_exists('R9LS_Automation_Admin')?get_option(R9LS_Automation_Admin::HUB_SETTINGS,array()):array();$configured=$hub['outage_iframe_url']??'https://outage-pro.com/widget/illinois-storm-chaser/CrYfAWSk';$host=strtolower((string)wp_parse_url($configured,PHP_URL_HOST));if(!in_array($host,array('outage-pro.com','www.outage-pro.com'),true))$configured='https://outage-pro.com/widget/illinois-storm-chaser/CrYfAWSk';$src=apply_filters('r9ls_power_outage_iframe_url',$configured);if(!$src||!wp_http_validate_url($src))return '<div class="r9-outage-fallback">Power outage tracking is temporarily unavailable.</div>';
        return '<article class="r9-outage-widget-card" aria-label="Illinois Storm Chaser power outage map"><div class="r9-outage-widget-head"><div><span>REGION 9 COMMUNITY STATUS</span><h2>Power Outage Tracker</h2><p>Live Illinois outage information from Illinois Storm Chaser and Outage Pro.</p></div></div><div class="r9-outage-widget-frame"><iframe src="'.esc_url($src).'" title="Illinois Storm Chaser live power outage tracker" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" style="border:none;overflow:hidden" allowfullscreen></iframe></div><p class="r9-outage-fallback-link"><a href="https://outage-pro.com/" target="_blank" rel="noopener noreferrer">Open Outage Pro if the embedded tracker does not load</a></p></article>';
    }

    private function event_rank($event,$severity=''){$e=strtolower($event.' '.$severity);if(strpos($e,'warning')!==false||strpos($e,'emergency')!==false||strpos($e,'extreme')!==false)return 4;if(strpos($e,'watch')!==false)return 3;if(strpos($e,'advisory')!==false||strpos($e,'moderate')!==false)return 2;if(strpos($e,'statement')!==false||strpos($e,'outlook')!==false||strpos($e,'minor')!==false)return 1;return 1;}
}
