<?php if (!defined('ABSPATH')) exit; ?>
<?php if(r9_setting('emergency_mode',false)): ?>
<section class="r9-emergency-hero" role="alert">
 <div class="r9-wrap r9-emergency-grid">
  <div><span class="r9-emergency-kicker">EMERGENCY MODE ACTIVE</span><h1>Region 9 Severe Weather Operations</h1><p><?php echo esc_html(r9_setting('maintenance_message','Active monitoring is underway. Review alerts, radar, and recommended actions.')); ?></p><div class="r9-emergency-actions"><a href="<?php echo esc_url(home_url('/alerts/')); ?>">Active Alerts</a><a href="<?php echo esc_url(home_url('/radar/')); ?>">Live Radar</a><a href="<?php echo esc_url(home_url('/storm-timing/')); ?>">Storm Timing</a></div></div>
  <div class="r9-emergency-radar"><iframe title="Emergency radar" loading="eager" allowfullscreen src="<?php echo esc_url(r9_setting('radar_url','https://app.weatherfront.com/radar/KILX')); ?>"></iframe></div>
 </div>
</section>
<?php endif; ?>
<?php if(r9_setting('live_broadcast_enabled',false) && trim((string)r9_setting('live_video_url',''))): ?>
<section class="r9-section r9-live-broadcast-top">
  <div class="r9-wrap r9-home-media-wrap">
    <div class="r9-section-title r9-live-title"><div><span class="r9-live-pill">LIVE</span><h2>Region 9 Live Broadcast</h2><p>Live weather coverage, briefings, and breaking updates.</p></div></div>
    <?php echo r9_video_embed(); ?>
  </div>
</section>
<?php endif; ?>

<main class="r9-wrap r9-home-ops-layout r9-mobile-stack-home" data-r9-home-layout="v17.1-operations">
  <section class="r9-home-mobile-priority" aria-label="Priority Region 9 status">
    <?php echo r9_home_alert_sidebar_module(); ?>
    <?php echo r9_home_risk_sidebar_module(); ?>
  </section>

  <div class="r9-home-main-column">
    <section id="radar" class="r9-panel r9-home-radar-hero r9-radar" data-r9-home-module="primary-radar">
      <div class="r9-panel-head r9-panel-head-brand"><div><span class="r9-eyebrow">LIVE MAP / RADAR</span><h1>Live Region 9 Radar</h1><p>Primary operational view for storms, precipitation, and incoming weather near east-central Illinois.</p></div><span class="r9-status-pill">Available · Updated <?php echo esc_html(r9_updated_label()); ?></span></div>
      <div class="r9-radar-frame-wrap">
        <iframe title="Region 9 live radar map" loading="lazy" allowfullscreen src="<?php echo esc_url(r9_setting('radar_url','https://app.weatherfront.com/radar/KILX')); ?>"></iframe>
        <div class="r9-radar-fallback" aria-hidden="true"><strong>Region 9 Radar</strong><span>If the live map does not load, open the full radar page.</span></div>
      </div>
      <div class="r9-status-row"><span class="r9-status-pill <?php echo esc_attr(r9_risk_class()); ?>">Current risk: <?php echo esc_html(r9_risk_label()); ?></span><span class="r9-status-pill">No render-time external HTTP</span><a class="r9-status-pill" href="<?php echo esc_url(home_url('/radar/')); ?>">Open full radar →</a></div>
    </section>

    <section class="r9-panel r9-impact-dashboard" data-r9-home-module="decision-impact-dashboard">
      <div class="r9-panel-head r9-panel-head-brand"><div><span class="r9-eyebrow">DECISION SUPPORT</span><h2>Decision Impact Dashboard</h2><small>Latest publication: <?php echo esc_html(r9ls_theme_latest_publication_time()); ?></small></div><?php echo r9ls_theme_risk_badge(r9ls_theme_home_value('severe-weather-risk','risk',array('label'=>r9_risk_label()))); ?></div>
      <div class="r9-impact-list">
        <?php foreach(array('Travel & Commute'=>'travel','Agriculture'=>'agriculture','Outdoor Events'=>'outdoor','Fieldwork & Spraying'=>'fieldwork','Livestock'=>'livestock','School Activities'=>'schools','Construction & Outdoor Work'=>'construction','Forecast Confidence'=>'forecast-confidence') as $label=>$pid): $product=r9ls_theme_product($pid); $value=$product?($product['risk']['label']??($product['score']??'good')):r9_setting($pid,'good'); ?>
          <div class="r9-impact r9-impact-<?php echo esc_attr(r9_impact_class($value));?>"><strong><?php echo esc_html($label);?></strong><span><?php echo esc_html($product?($product['summary']??r9_impact_label($value)):r9_impact_label($value));?></span></div>
        <?php endforeach;?>
      </div>
      <div class="r9-dashboard-note"><strong>Morning Weather Brief:</strong> <?php echo esc_html(r9ls_theme_home_value('morning-brief')); ?><br><strong>Weather Headlines:</strong> <?php echo esc_html(r9ls_theme_home_value('headlines')); ?><br><strong>Affected counties:</strong> <?php $r9hp=r9ls_theme_product('severe-weather-risk'); echo esc_html($r9hp && !empty($r9hp['affected_counties']) ? implode(', ', $r9hp['affected_counties']) : 'None specified'); ?></div>
    </section>

    <section class="r9-panel r9-current-panel" data-r9-home-module="current-conditions">
      <div class="r9-panel-head r9-panel-head-brand"><div><span class="r9-eyebrow">LIVE OBSERVATIONS</span><h2>Current Conditions</h2><small class="r9-click-hint">Select a city for its full forecast</small></div><div class="r9-mini-clock"><span id="r9-clock">--:--</span><small id="r9-date"></small></div></div>
      <div class="r9-condition-stage r9-condition-stage-compact"><button class="r9-slide-button prev" aria-label="Previous city">‹</button><div id="r9-conditions" class="r9-conditions-slider"><div class="r9-condition-slide is-active"><div class="r9-weather-scene scene-loading"><div class="r9-retro-screen"><div class="r9-retro-icon">R9</div></div></div><div class="r9-condition-copy"><strong>Loading observations…</strong></div></div></div><button class="r9-slide-button next" aria-label="Next city">›</button></div><div id="r9-condition-dots" class="r9-slide-dots"></div>
    </section>

    <section class="r9-home-headlines-grid" data-r9-home-module="headlines-confidence">
      <article class="r9-panel"><div class="r9-panel-head"><div><span class="r9-eyebrow">WEATHER HEADLINES</span><h2>Weather Headlines</h2></div></div><p class="r9-lead"><?php echo esc_html(r9ls_theme_home_value('headlines','summary','Region 9 Weather is monitoring conditions across the area.')); ?></p><p class="r9ls-updated">Latest update: <?php echo esc_html(r9_updated_label()); ?></p></article>
      <article class="r9-panel"><div class="r9-panel-head"><div><span class="r9-eyebrow">FORECAST CONFIDENCE</span><h2>Forecast Confidence</h2></div></div><?php $confidence_product=r9ls_theme_product('forecast-confidence'); echo r9ls_theme_confidence($confidence_product?:array('confidence'=>0)); ?><p><?php echo esc_html($confidence_product['summary']??'Forecast confidence will update with approved Live Studio products.'); ?></p></article>
    </section>

    <?php echo r9_home_forecast_module('todays-forecast', 'Today’s Forecast'); ?>
    <?php echo r9_home_forecast_module('seven-day-forecast', 'Seven-Day Forecast'); ?>

    <section class="r9-section r9-risk-operations-section">
      <div class="r9-risk-operations-grid">
        <article class="r9-panel r9-risk-guide-panel">
          <div class="r9-panel-head"><div><span class="r9-eyebrow">PUBLIC SAFETY</span><h2>Severe Weather Risk Level Guide</h2></div><span class="r9-risk <?php echo esc_attr(r9_risk_class());?>">Current: <?php echo esc_html(r9_risk_label());?></span></div>
          <img class="r9-risk-guide-image" src="<?php echo esc_url(get_stylesheet_directory_uri().'/assets/images/severe-weather-risk-level-guide.png'); ?>" alt="Region 9 Weather severe weather risk level guide for east-central Illinois">
        </article>
        <?php echo r9_home_outage_module(); ?>
      </div>
    </section>

    <?php if(r9_setting('social_section_enabled',true)): ?>
    <section class="r9-section r9-social-section">
      <div class="r9-panel r9-social-hub">
        <div class="r9-panel-head"><div><span class="r9-eyebrow">CONNECT WITH REGION 9</span><h2>Social Media & Forecast Updates</h2><p>Follow, share, and stay connected with the latest Region 9 Weather information.</p></div></div>
        <div class="r9-social-links">
          <?php foreach(array('facebook'=>'Facebook','x'=>'X','instagram'=>'Instagram','youtube'=>'YouTube') as $network=>$label): $url=trim((string)r9_setting($network.'_url','')); if($url): ?>
            <a class="r9-social-profile r9-social-<?php echo esc_attr($network); ?>" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo esc_html($label); ?></strong><span>Follow Region 9 Weather</span></a>
          <?php endif; endforeach; ?>
        </div>
        <?php $social_shortcode=trim((string)r9_setting('social_feed_shortcode','')); if($social_shortcode): ?><div class="r9-social-feed"><?php echo do_shortcode($social_shortcode); ?></div><?php else: ?><div class="r9-social-feed-placeholder"><strong>Social feed area</strong><span>Paste a supported social-feed plugin shortcode in Region 9 Studio → Live Controls.</span></div><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <aside class="r9-home-sidebar" data-r9-home-module="right-sidebar" aria-label="Region 9 operational sidebar">
    <?php if(is_active_sidebar('r9-live-sidebar')){ dynamic_sidebar('r9-live-sidebar'); } echo r9_home_sidebar_fallback(); ?>
  </aside>
</main>
