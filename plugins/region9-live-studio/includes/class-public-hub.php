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
    }

    public function assets() {
        wp_enqueue_style('r9ls-public-hub', R9LS_URL.'assets/public-hub.css', array(), R9LS_VERSION);
        wp_enqueue_script('r9ls-public-hub', R9LS_URL.'assets/public-hub.js', array(), R9LS_VERSION, true);
        wp_localize_script('r9ls-public-hub','R9Hub',array(
            'mapEndpoint'=>esc_url_raw(rest_url('region9-live-studio/v1/weather-intelligence-map')),
            'alertUrl'=>esc_url_raw(home_url('/alerts/')),
        ));
    }

    public function routes() {
        register_rest_route('region9-live-studio/v1','/weather-intelligence-map',array(
            'methods'=>'GET','callback'=>array($this,'rest_map'),'permission_callback'=>'__return_true'
        ));
    }

    public function rest_map() { return rest_ensure_response($this->snapshot()); }

    public function snapshot() {
        $live=$this->alerts->live();
        $county_status=array();
        foreach($this->gis->county_names() as $county){$county_status[$county]=array('rank'=>0,'event'=>'All Clear','count'=>0);}
        $map_alerts=array();
        foreach((array)($live['alerts']??array()) as $alert){
            $rank=$this->event_rank($alert['event']??'', $alert['severity']??'');
            foreach((array)($alert['region9_counties']??array()) as $county){
                if(!isset($county_status[$county]))continue;
                $county_status[$county]['count']++;
                if($rank>$county_status[$county]['rank']){$county_status[$county]['rank']=$rank;$county_status[$county]['event']=$alert['event']??'Weather Alert';}
            }
            $row=$alert;$row['rank']=$rank;$map_alerts[]=$row;
        }
        $features=array();
        foreach($this->gis->county_geometries() as $name=>$geometry){$features[]=array('type'=>'Feature','properties'=>array('name'=>$name,'NAME'=>$name.' County'),'geometry'=>$geometry);}
        return array(
            'updated'=>current_time('timestamp'),
            'counties'=>$county_status,
            'alerts'=>$map_alerts,
            'geojson'=>array('type'=>'FeatureCollection','name'=>'Region 9 Illinois Counties','features'=>$features),
            'source'=>'National Weather Service + authoritative Region 9 county GIS',
        );
    }

    public function map_shortcode() {
        return '<section class="r9wi-shell" aria-label="Region 9 county alert map"><div class="r9wi-head"><div><span>REGION 9 WEATHER INTELLIGENCE</span><h2>Live 9-County Alert Map</h2><p>County shading shows the highest active NWS product affecting Region 9. Official NWS alert polygons are overlaid when available.</p></div><a href="'.esc_url(home_url('/alerts/')).'">Full Alert Center</a></div><div class="r9wi-body"><div class="r9wi-map" data-r9wi-map role="img" aria-label="Map of Region 9 counties and active NWS alerts"><div class="r9wi-loading">Loading county alerts…</div></div><div class="r9wi-side"><div data-r9wi-summary></div><div class="r9wi-legend"><span><i class="r0"></i>All clear</span><span><i class="r1"></i>Statement / Outlook</span><span><i class="r2"></i>Advisory</span><span><i class="r3"></i>Watch</span><span><i class="r4"></i>Warning / Emergency</span><span><i class="polygon"></i>Official NWS polygon</span></div></div></div><small class="r9wi-source">Region 9 county boundaries use the installed authoritative GIS dataset. Alert data and zone/polygon geometry: National Weather Service.</small></section>';
    }

    public function live_shortcode() {
        $state=$this->alerts->live();$alerts=(array)($state['alerts']??array());
        if(!$alerts)return '<div class="r9-live-alerts r9-all-clear"><strong>Region 9 Live Alerts:</strong> No active NWS watches, warnings or advisories are currently affecting the nine Region 9 counties.</div>';
        $html='<div class="r9-live-alerts" aria-label="Region 9 live alerts"><strong>REGION 9 LIVE ALERTS</strong><div class="r9-live-alert-list">';
        foreach(array_slice($alerts,0,10) as $a){$counties=implode(', ',(array)($a['region9_counties']??array()));$html.='<a href="'.esc_url(home_url('/alerts/')).'"><b>'.esc_html($a['event']??'Weather Alert').'</b><span>'.esc_html($counties).'</span></a>';}
        return $html.'</div></div>';
    }

    public function crawl_shortcode() {
        $state=$this->alerts->crawl();$alerts=(array)($state['alerts']??array());
        $items=array();
        foreach($alerts as $a){
            $near=($a['scope']??'')==='within-50-miles'?'Nearby: ':'';
            $where=!empty($a['region9_counties'])?implode(', ',(array)$a['region9_counties']):(($a['distance_to_region9_miles']??null)!==null?round((float)$a['distance_to_region9_miles']).' mi from Region 9':'near Region 9');
            $items[]='<span><b>'.esc_html($near.($a['event']??'Weather Alert')).'</b> — '.esc_html($where).' — '.esc_html($a['headline']??'').'</span>';
        }
        if(!$items)$items[]='<span>No active NWS alerts in Region 9 or within 50 miles of the Region 9 boundary.</span>';
        return '<div class="r9-latest-crawl" role="region" aria-label="Latest weather update and nearby alerts"><div class="r9-latest-crawl-label">LATEST WEATHER UPDATE</div><div class="r9-latest-crawl-window"><div class="r9-latest-crawl-track">'.implode('<span class="r9-crawl-separator"> • </span>',$items).'</div></div></div>';
    }

    public function outage_shortcode() {
        $hub=class_exists('R9LS_Automation_Admin')?get_option(R9LS_Automation_Admin::HUB_SETTINGS,array()):array();
        $configured=$hub['outage_iframe_url']??'https://outage-pro.com/widget/illinois-storm-chaser/CrYfAWSk';
        $host=strtolower((string)wp_parse_url($configured,PHP_URL_HOST));
        if(!in_array($host,array('outage-pro.com','www.outage-pro.com'),true))$configured='https://outage-pro.com/widget/illinois-storm-chaser/CrYfAWSk';
        $src=apply_filters('r9ls_power_outage_iframe_url',$configured);
        if(!$src || !wp_http_validate_url($src))return '<div class="r9-outage-fallback">Power outage tracking is temporarily unavailable.</div>';
        return '<article class="r9-outage-widget-card" aria-label="Illinois Storm Chaser power outage map"><div class="r9-outage-widget-head"><div><span>REGION 9 COMMUNITY STATUS</span><h2>Power Outage Tracker</h2><p>Live Illinois outage information from Illinois Storm Chaser and Outage Pro.</p></div></div><div class="r9-outage-widget-frame"><iframe src="'.esc_url($src).'" title="Illinois Storm Chaser live power outage tracker" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" style="border:none;overflow:hidden" allowfullscreen></iframe></div><p class="r9-outage-fallback-link"><a href="https://outage-pro.com/" target="_blank" rel="noopener noreferrer">Open Outage Pro if the embedded tracker does not load</a></p></article>';
    }

    private function event_rank($event,$severity=''){
        $e=strtolower($event.' '.$severity);
        if(strpos($e,'warning')!==false||strpos($e,'emergency')!==false||strpos($e,'extreme')!==false)return 4;
        if(strpos($e,'watch')!==false)return 3;
        if(strpos($e,'advisory')!==false||strpos($e,'moderate')!==false)return 2;
        if(strpos($e,'statement')!==false||strpos($e,'outlook')!==false||strpos($e,'minor')!==false)return 1;
        return 1;
    }
}
