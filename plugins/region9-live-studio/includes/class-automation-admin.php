<?php
defined('ABSPATH') || exit;

final class R9LS_Automation_Admin {
    const HUB_SETTINGS = 'r9ls_public_hub_settings';
    private $audit;

    public function __construct($audit = null) { $this->audit=$audit; }
    public function hooks() {
        add_action('admin_menu', array($this,'menu'), 30);
        add_action('admin_post_r9ls_automation_settings', array($this,'save'));
    }
    public function menu() {
        add_submenu_page('r9ls','Automation & Social','Automation & Social','manage_options','r9ls-automation-social',array($this,'page'));
    }

    public function page() {
        if(!current_user_can('manage_options')) wp_die(esc_html__('Administrator access required.','r9ls'));
        $policy=wp_parse_args(get_option(R9LS_Publication_Policy::SETTINGS,array()),R9LS_Publication_Policy::defaults());
        $social=wp_parse_args(get_option(R9LS_Social_Publisher::SETTINGS,array()),R9LS_Social_Publisher::defaults());
        $hub=wp_parse_args(get_option(self::HUB_SETTINGS,array()),array('outage_iframe_url'=>'https://outage-pro.com/widget/illinois-storm-chaser/CrYfAWSk'));
        $last=get_option(R9LS_Publication_Policy::LAST,array()); $graphic=get_option(R9LS_Graphic_Renderer::LAST,array());
        $social_history=get_option(R9LS_Social_Publisher::HISTORY,array()); $social_outbox=get_option(R9LS_Social_Publisher::OUTBOX,array());
        $alert_health=get_option(R9LS_Alert_Feed::HEALTH_OPTION,array());
        echo '<div class="wrap"><h1>Region 9 Automation & Social Publishing</h1><p>Configure the six-hour production cycle, material-change publication controls, social delivery, and public intelligence embeds. Credentials are stored only in protected WordPress options and are not exposed by public REST responses.</p>';
        echo '<h2>Automation health</h2><table class="widefat striped"><tbody>';
        $rows=array('Last publication policy result'=>($last['status']??'Not run').' — '.($last['reason']??''),'Last graphic generation'=>($graphic['status']??'Not run').' — '.($graphic['count']??0).' graphics','Social outbox'=>count((array)$social_outbox),'Social history'=>count((array)$social_history),'Region 9 live alert count'=>$alert_health['live_count']??'Unknown','50-mile crawl alert count'=>$alert_health['crawl_count']??'Unknown','Alert scope status'=>$alert_health['status']??'Unknown');
        foreach($rows as $label=>$value) echo '<tr><th>'.esc_html($label).'</th><td>'.esc_html((string)$value).'</td></tr>';
        echo '</tbody></table>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="r9ls_automation_settings">'; wp_nonce_field('r9ls_automation_settings');
        echo '<h2>Website publication policy</h2><p>Routine forecasts can publish automatically. Elevated and Significant risk remain review-gated by default, except active Region 9 warnings/emergencies can be configured for immediate publication.</p>';
        $this->check('routine_auto_publish','Automatically publish the routine six-hour forecast cycle',!empty($policy['routine_auto_publish']));
        $this->check('material_change_auto_publish','Automatically publish material changes below review thresholds',!empty($policy['material_change_auto_publish']));
        $this->check('review_elevated','Require review for Elevated Region 9 risk',!empty($policy['review_elevated']));
        $this->check('review_significant','Require review for Significant Region 9 risk',!empty($policy['review_significant']));
        $this->check('emergency_warning_auto_publish','Immediately publish when an active Region 9 NWS Warning/Emergency is present',!empty($policy['emergency_warning_auto_publish']));
        echo '<p><label>Minimum confidence for automatic website publication <input type="number" min="0" max="100" name="minimum_confidence" value="'.esc_attr((int)$policy['minimum_confidence']).'">%</label></p>';

        echo '<h2>Social publishing policy</h2><p><label>Mode <select name="social_mode">';
        foreach(array('manual'=>'Manual only','routine'=>'Routine auto','weather-aware'=>'Weather-aware auto','emergency'=>'Emergency-focused auto') as $v=>$label) echo '<option value="'.esc_attr($v).'" '.selected($social['mode']??'manual',$v,false).'>'.esc_html($label).'</option>';
        echo '</select></label></p>';
        echo '<p><label>Minimum Region 9 risk for automatic social post <select name="minimum_risk_level">';
        foreach(array(0=>'None',1=>'Low',2=>'Limited',3=>'Elevated',4=>'Significant') as $v=>$label) echo '<option value="'.$v.'" '.selected((int)($social['minimum_risk_level']??0),$v,false).'>'.esc_html($label).'</option>';
        echo '</select></label></p>';
        $this->check('post_on_six_hour_cycle','Post eligible routine six-hour products to social channels',!empty($social['post_on_six_hour_cycle']));
        $this->check('post_on_material_change','Post eligible material weather changes',!empty($social['post_on_material_change']));
        $this->check('post_emergency_alerts','Allow emergency alert-driven social posts',!empty($social['post_emergency_alerts']));
        $this->check('require_review_elevated','Require social approval for Elevated products',!empty($social['require_review_elevated']));
        $this->check('require_review_significant','Require social approval for Significant products',!empty($social['require_review_significant']));
        echo '<p><label>Eligible product IDs (comma separated)<br><textarea name="allowed_products" rows="4" class="large-text code">'.esc_textarea(implode(',',(array)($social['allowed_products']??array()))).'</textarea></label></p>';

        echo '<h2>Social channel connectors</h2><p>Each channel can send a signed JSON publication payload to a trusted connector such as Buffer, Make, Zapier, or your own service. This keeps provider-specific credentials outside public theme files.</p>';
        $labels=array('facebook'=>'Facebook Page','instagram'=>'Instagram','x'=>'X','bluesky'=>'Bluesky','mastodon'=>'Mastodon','broker'=>'General webhook / broker');
        foreach($labels as $key=>$label){$c=(array)($social['channels'][$key]??array());echo '<fieldset style="border:1px solid #ccd0d4;padding:12px;margin:12px 0"><legend><strong>'.esc_html($label).'</strong></legend>';$this->check('channel_'.$key.'_enabled','Enable this channel',!empty($c['enabled']));echo '<input type="hidden" name="channel_'.$key.'_provider" value="webhook"><p><label>Webhook URL <input class="large-text" type="url" name="channel_'.$key.'_webhook_url" value="'.esc_attr($c['webhook_url']??'').'" placeholder="https://..."></label></p><p><label>Signing secret <input class="regular-text" type="password" autocomplete="new-password" name="channel_'.$key.'_secret" value="" placeholder="Leave blank to keep existing secret"></label></p></fieldset>';}

        echo '<h2>Power outage embed</h2><p><label>Outage iframe URL <input class="large-text" type="url" name="outage_iframe_url" value="'.esc_attr($hub['outage_iframe_url']).'"></label></p><p class="description">Default restored provider: Illinois Storm Chaser / Outage Pro. Only HTTPS outage-pro.com embed URLs are accepted.</p>';
        submit_button('Save Automation Settings'); echo '</form></div>';
    }

    public function save() {
        if(!current_user_can('manage_options')) wp_die('Administrator capability required.'); check_admin_referer('r9ls_automation_settings');
        $p=array(); foreach(array('routine_auto_publish','material_change_auto_publish','review_elevated','review_significant','emergency_warning_auto_publish') as $k)$p[$k]=!empty($_POST[$k])?1:0; $p['minimum_confidence']=max(0,min(100,absint($_POST['minimum_confidence']??40))); update_option(R9LS_Publication_Policy::SETTINGS,$p,false);
        $old=wp_parse_args(get_option(R9LS_Social_Publisher::SETTINGS,array()),R9LS_Social_Publisher::defaults());
        $mode=sanitize_key(wp_unslash($_POST['social_mode']??'manual')); if(!in_array($mode,array('manual','routine','weather-aware','emergency'),true))$mode='manual';
        $s=$old; $s['mode']=$mode; $s['minimum_risk_level']=max(0,min(4,absint($_POST['minimum_risk_level']??0)));
        foreach(array('post_on_six_hour_cycle','post_on_material_change','post_emergency_alerts','require_review_elevated','require_review_significant') as $k)$s[$k]=!empty($_POST[$k])?1:0;
        $s['allowed_products']=array_values(array_intersect(array_keys(R9LS_Product_Catalog::definitions()),array_filter(array_map('sanitize_key',array_map('trim',explode(',',(string)wp_unslash($_POST['allowed_products']??'')))))));
        $s['channels']=array(); foreach(array('facebook','instagram','x','bluesky','mastodon','broker') as $key){$previous=(array)($old['channels'][$key]??array());$secret=sanitize_text_field(wp_unslash($_POST['channel_'.$key.'_secret']??''));$s['channels'][$key]=array('provider'=>'webhook','enabled'=>!empty($_POST['channel_'.$key.'_enabled'])?1:0,'webhook_url'=>esc_url_raw(wp_unslash($_POST['channel_'.$key.'_webhook_url']??'')),'secret'=>$secret!==''?$secret:($previous['secret']??''));}
        update_option(R9LS_Social_Publisher::SETTINGS,$s,false);
        $outage=esc_url_raw(wp_unslash($_POST['outage_iframe_url']??''));$host=strtolower((string)wp_parse_url($outage,PHP_URL_HOST)); if($host!=='outage-pro.com' && $host!=='www.outage-pro.com')$outage='https://outage-pro.com/widget/illinois-storm-chaser/CrYfAWSk'; update_option(self::HUB_SETTINGS,array('outage_iframe_url'=>$outage),false);
        if($this->audit)$this->audit->write('info','Automation and social settings updated.',array('social_mode'=>$mode));
        wp_safe_redirect(admin_url('admin.php?page=r9ls-automation-social&updated=1')); exit;
    }

    private function check($name,$label,$checked){echo '<p><label><input type="checkbox" name="'.esc_attr($name).'" value="1" '.checked($checked,true,false).'> '.esc_html($label).'</label></p>';}
}
