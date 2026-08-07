<?php
defined('ABSPATH') || exit;

final class R9LS_Product_Catalog {
    public static function definitions() {
        return array(
            'morning-weather-brief' => array('title'=>'Morning Weather Brief','category'=>'daily-core','base'=>'Decision Support Brief'),
            'todays-forecast' => array('title'=>"Today's Forecast",'category'=>'daily-core','base'=>'Travel'),
            'seven-day-forecast' => array('title'=>'Seven-Day Forecast','category'=>'daily-core','base'=>'Forecast Confidence'),
            'evening-weather-update' => array('title'=>'Evening Weather Update','category'=>'daily-core','base'=>'Decision Support Brief'),

            'weekly-weather-hazards' => array('title'=>'Weekly Weather Hazards','category'=>'hazards','base'=>'Severe Weather Risk'),
            'severe-weather-outlook' => array('title'=>'Severe Weather Outlook','category'=>'hazards','base'=>'Severe Weather Risk'),
            'storm-timing' => array('title'=>'Storm Timing','category'=>'hazards','base'=>'Severe Weather Risk'),
            'threat-breakdown' => array('title'=>'Threat Breakdown','category'=>'hazards','base'=>'Severe Weather Risk'),
            'watch-warning-explainer' => array('title'=>'Watch / Warning Explainer','category'=>'hazards','base'=>'Emergency Operations'),

            'seven-day-heat-outlook' => array('title'=>'Seven-Day Heat Outlook','category'=>'temperature-health','base'=>'Forecast Confidence'),
            'heat-safety-alert' => array('title'=>'Heat Safety Alert','category'=>'temperature-health','base'=>'Emergency Operations'),
            'wind-chill-outlook' => array('title'=>'Wind Chill Outlook','category'=>'temperature-health','base'=>'Emergency Operations'),
            'frost-freeze-outlook' => array('title'=>'Frost / Freeze Outlook','category'=>'temperature-health','base'=>'Agriculture'),

            'agriculture-weather-outlook' => array('title'=>'Agriculture Weather Outlook','category'=>'agriculture','base'=>'Agriculture'),
            'spray-window-forecast' => array('title'=>'Spray Window Forecast','category'=>'agriculture','base'=>'Spraying'),
            'fieldwork-outlook' => array('title'=>'Fieldwork Outlook','category'=>'agriculture','base'=>'Fieldwork'),
            'livestock-weather-stress' => array('title'=>'Livestock Weather Stress','category'=>'agriculture','base'=>'Livestock'),

            'travel' => array('title'=>'Rural Travel Outlook','category'=>'travel-outdoor','base'=>'Travel'),
            'commute-forecast' => array('title'=>'Commute Forecast','category'=>'travel-outdoor','base'=>'Travel'),
            'outdoor-event-planner' => array('title'=>'Outdoor Event Planner','category'=>'travel-outdoor','base'=>'Outdoor Events'),
            'lightning-risk-outlook' => array('title'=>'Lightning Risk Outlook','category'=>'travel-outdoor','base'=>'Outdoor Events'),

            'forecast-rainfall' => array('title'=>'Forecast Rainfall','category'=>'rain-drought-water','base'=>'Agriculture'),
            'observed-rainfall-totals' => array('title'=>'Observed Rainfall Totals','category'=>'rain-drought-water','base'=>'Agriculture'),
            'drought-dryness-update' => array('title'=>'Drought / Dryness Update','category'=>'rain-drought-water','base'=>'Agriculture'),

            'storm-anxiety-outlook' => array('title'=>'Storm Anxiety Outlook','category'=>'specialty','base'=>'Severe Weather Risk'),
            'what-were-watching' => array('title'=>"What We're Watching",'category'=>'specialty','base'=>'Severe Weather Risk'),
            'forecast-confidence-meter' => array('title'=>'Forecast Confidence Meter','category'=>'specialty','base'=>'Forecast Confidence'),
            'decision-support-brief' => array('title'=>'Decision Support Brief','category'=>'specialty','base'=>'Emergency Operations'),
        );
    }

    public static function count() { return count(self::definitions()); }
    public static function categories() {
        $out = array();
        foreach (self::definitions() as $id => $def) { $out[$def['category']][$id] = $def; }
        return $out;
    }
}
