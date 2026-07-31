<?php if(!defined('ABSPATH'))exit;
add_action('customize_register',function($c){
 $c->add_section('r9_studio',array('title'=>'Region 9 Live Studio','priority'=>20));
 $fields=array(
 'forecast_headline'=>array('Forecast Headline','text','Region 9 Daily Forecast'),
 'forecast_discussion'=>array('Homepage Discussion','textarea','Use this area for the current Region 9 forecast discussion.'),
 'latest_update_enabled'=>array('Enable Latest Weather Update','checkbox',true),
 'breaking_news'=>array('Latest Weather Update Text','text','Region 9 Weather is monitoring conditions across east-central Illinois.'),
 'social_section_enabled'=>array('Show Social Media Section on Homepage','checkbox',true),
 'facebook_url'=>array('Facebook Page URL','url',''),
 'x_url'=>array('X Profile URL','url',''),
 'instagram_url'=>array('Instagram Profile URL','url',''),
 'youtube_url'=>array('YouTube Channel URL','url',''),
 'social_feed_shortcode'=>array('Social Feed Plugin Shortcode','text',''),
 'emergency_mode'=>array('Enable Emergency Mode','checkbox',false),
 'high_contrast'=>array('Enable High Contrast Mode','checkbox',false),
 'show_system_status'=>array('Show System Status Panel','checkbox',true),
 'animated_weather'=>array('Animate Weather Graphics','checkbox',true),
 'maintenance_message'=>array('Operations Notice','textarea','Region 9 systems are operating normally.'),
 'live_broadcast_enabled'=>array('Show Live Broadcast','checkbox',false),
 'live_video_url'=>array('Live Broadcast Video URL','url',''),
 'risk_level'=>array('Risk Level','select','none'),
 'radar_url'=>array('WeatherFront Radar iframe URL','url','https://app.weatherfront.com/radar/KILX'),
 'travel_impact'=>array('Travel & Commute Impact','select','good'),'ag_impact'=>array('Agriculture Impact','select','fair'),'outdoor_impact'=>array('Outdoor Events Impact','select','good'),'fieldwork_impact'=>array('Fieldwork & Spraying Impact','select','good'),'livestock_impact'=>array('Livestock Impact','select','good'),'school_impact'=>array('School Activities Impact','select','good'),'work_impact'=>array('Construction & Outdoor Work Impact','select','good'),'confidence'=>array('Forecast Confidence','select','high'),'dashboard_note'=>array('Dashboard Note','textarea','Use the color-coded dashboard for quick operational decisions across Region 9.')
 );
 foreach($fields as $id=>$f){$san=$f[1]==='url'?'esc_url_raw':($f[1]==='textarea'?'wp_kses_post':($f[1]==='checkbox'?function($v){return (bool)$v;}:'sanitize_text_field'));$c->add_setting('r9_'.$id,array('default'=>$f[2],'sanitize_callback'=>$san));$args=array('label'=>$f[0],'section'=>'r9_studio','type'=>$f[1]);if($id==='risk_level')$args['choices']=array('none'=>'None','low'=>'Low','limited'=>'Limited','elevated'=>'Elevated','significant'=>'Significant'); if(in_array($id,array('travel_impact','ag_impact','outdoor_impact','fieldwork_impact','livestock_impact','school_impact','work_impact'),true))$args['choices']=array('good'=>'Good','fair'=>'Fair / Monitor','caution'=>'Caution','poor'=>'Poor','dangerous'=>'Dangerous / Avoid'); if($id==='confidence')$args['choices']=array('low'=>'Low','medium'=>'Medium','high'=>'High');$c->add_control('r9_'.$id,$args);}
 foreach(array('daily_image'=>'Homepage Forecast Graphic','seven_day_image'=>'Seven-Day Graphic') as $id=>$label){$c->add_setting('r9_'.$id,array('sanitize_callback'=>'absint'));$c->add_control(new WP_Customize_Media_Control($c,'r9_'.$id,array('label'=>$label,'section'=>'r9_studio','mime_type'=>'image')));}
});
