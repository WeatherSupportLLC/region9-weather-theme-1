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

<section class="r9-section r9-home-daily-primary">
  <div class="r9-wrap">
    <div class="r9-section-title"><div><span class="r9-eyebrow">DAILY FORECAST</span><h2>Today's Forecast</h2><p>The latest approved Region 9 forecast for east-central Illinois.</p></div></div>
    <?php echo r9ls_theme_card('todays-forecast'); ?>
  </div>
</section>

<section class="r9-section r9-home-daily-secondary">
  <div class="r9-wrap">
    <div class="r9-section-title"><div><span class="r9-eyebrow">FORECAST CENTER</span><h2>More Daily Weather</h2><p>Open any graphic for a full-size view.</p></div><a class="r9-home-daily-link" href="<?php echo esc_url(home_url('/daily/'));?>">Open Daily Forecast →</a></div>
    <div class="r9-home-daily-boxes">
      <?php foreach(array('seven-day-forecast'=>'Seven-Day Forecast','morning-weather-brief'=>'Morning Weather Brief','current-conditions'=>'Current Conditions') as $pid=>$label): $p=r9ls_theme_product($pid); $url=$p?($p['graphic_url']??''):''; ?>
      <article class="r9-home-daily-box">
        <button type="button" class="r9-home-daily-expand" <?php if($url): ?>data-r9-home-full="<?php echo esc_url($url);?>"<?php endif; ?> aria-label="Expand <?php echo esc_attr($label);?>">
          <div class="r9-home-daily-image-frame">
            <?php if($url): ?>
              <img src="<?php echo esc_url($url);?>" alt="<?php echo esc_attr($label);?>" loading="lazy" decoding="async">
            <?php else: ?>
              <div class="r9-home-daily-image-missing">Graphic temporarily unavailable.</div>
            <?php endif; ?>
          </div>
          <div class="r9-home-daily-box-copy"><span><?php echo esc_html(strtoupper($label));?></span><strong><?php echo esc_html($label);?></strong><small>Click to enlarge</small></div>
        </button>
      </article>
      <?php endforeach; ?>
    </div>
    <dialog class="r9-home-daily-lightbox" id="r9-home-daily-lightbox"><button type="button" class="r9-home-daily-lightbox-close" aria-label="Close expanded graphic">×</button><img alt="Expanded Region 9 forecast graphic"></dialog>
  </div>
</section>
<script>
(function(){
  const dlg=document.getElementById('r9-home-daily-lightbox');
  if(!dlg) return;
  const img=dlg.querySelector('img');
  document.querySelectorAll('[data-r9-home-full]').forEach(function(btn){
    btn.addEventListener('click',function(){img.src=btn.getAttribute('data-r9-home-full');dlg.showModal();});
  });
  dlg.querySelector('.r9-home-daily-lightbox-close').addEventListener('click',function(){dlg.close();});
  dlg.addEventListener('click',function(e){if(e.target===dlg)dlg.close();});
})();
</script>

<section class="r9-top-dashboard">
  <div class="r9-wrap r9-operations-grid">
    <article class="r9-panel r9-impact-dashboard">
      <div class="r9-panel-head r9-panel-head-brand"><div><span class="r9-eyebrow">DECISION SUPPORT</span><h2>Decision Impact Dashboard</h2><small>Latest publication: <?php echo esc_html(r9ls_theme_latest_publication_time()); ?></small></div><?php echo r9ls_theme_risk_badge(r9ls_theme_home_value('severe-weather-risk','risk',array('label'=>r9_risk_label()))); ?></div>
      <div class="r9-impact-list">
        <?php foreach(array('Travel & Commute'=>'travel','Agriculture'=>'agriculture','Outdoor Events'=>'outdoor','Fieldwork & Spraying'=>'fieldwork','Livestock'=>'livestock','School Activities'=>'schools','Construction & Outdoor Work'=>'construction','Forecast Confidence'=>'forecast-confidence') as $label=>$pid): $product=r9ls_theme_product($pid); $value=$product?($product['risk']['label']??($product['score']??'good')):r9_setting($pid,'good'); ?>
          <div class="r9-impact r9-impact-<?php echo esc_attr(r9_impact_class($value));?>"><strong><?php echo esc_html($label);?></strong><span><?php echo esc_html($product?($product['summary']??r9_impact_label($value)):r9_impact_label($value));?></span></div>
        <?php endforeach;?>
      </div>
      <div class="r9-dashboard-note"><strong>Morning Weather Brief:</strong> <?php echo esc_html(r9ls_theme_home_value('morning-brief')); ?><br><strong>Weather Headlines:</strong> <?php echo esc_html(r9ls_theme_home_value('headlines')); ?><br><strong>Affected counties:</strong> <?php $r9hp=r9ls_theme_product('severe-weather-risk'); echo esc_html($r9hp && !empty($r9hp['affected_counties']) ? implode(', ', $r9hp['affected_counties']) : 'None specified'); ?></div>
    </article>

    <article class="r9-panel r9-current-panel">
      <div class="r9-panel-head r9-panel-head-brand"><div><span class="r9-eyebrow">LIVE OBSERVATIONS</span><h2>Current Conditions</h2><small class="r9-click-hint">Select a city for its full forecast</small></div><div class="r9-mini-clock"><span id="r9-clock">--:--</span><small id="r9-date"></small></div></div>
      <div class="r9-condition-stage r9-condition-stage-compact"><button class="r9-slide-button prev" aria-label="Previous city">‹</button><div id="r9-conditions" class="r9-conditions-slider"><div class="r9-condition-slide is-active"><div class="r9-weather-scene scene-loading"><div class="r9-retro-screen"><div class="r9-retro-icon">R9</div></div></div><div class="r9-condition-copy"><strong>Loading observations…</strong></div></div></div><button class="r9-slide-button next" aria-label="Next city">›</button></div><div id="r9-condition-dots" class="r9-slide-dots"></div>
    </article>
  </div>
</section>

<section class="r9-section r9-risk-operations-section">
  <div class="r9-wrap r9-risk-operations-grid">
    <article class="r9-panel r9-risk-guide-panel">
      <div class="r9-panel-head"><div><span class="r9-eyebrow">PUBLIC SAFETY</span><h2>Severe Weather Risk Level Guide</h2></div><span class="r9-risk <?php echo esc_attr(r9_risk_class());?>">Current: <?php echo esc_html(r9_risk_label());?></span></div>
      <img class="r9-risk-guide-image" src="<?php echo esc_url(get_stylesheet_directory_uri().'/assets/images/severe-weather-risk-level-guide.png'); ?>" alt="Region 9 Weather severe weather risk level guide for east-central Illinois">
    </article>
    <aside class="r9-panel-dark r9-operations-explainer">
      <div class="r9-eyebrow">REGION 9 LIVE</div>
      <h2>Weather Operations</h2>
      <p class="r9-operations-summary">Weather Operations is the public access point for Region 9 Weather's active monitoring tools. Use it to review the latest forecast, understand current hazards, track storms on radar, and check watches or warnings affecting the area.</p>
      <div class="r9-operations-links">
        <a href="<?php echo esc_url(home_url('/daily/'));?>"><strong>Forecast</strong><span>Latest outlook</span></a>
        <a href="<?php echo esc_url(home_url('/hazards/'));?>"><strong>Hazards</strong><span>Threat details</span></a>
        <a href="<?php echo esc_url(home_url('/radar/'));?>"><strong>Radar</strong><span>Storm tracking</span></a>
        <a href="<?php echo esc_url(home_url('/alerts/'));?>"><strong>Alerts</strong><span>Watches & warnings</span></a>
      </div>
    </aside>
  </div>
</section>

<?php if(r9_setting('social_section_enabled',true)): ?>
<section class="r9-section r9-social-section">
  <div class="r9-wrap">
    <div class="r9-panel r9-social-hub">
      <div class="r9-panel-head"><div><span class="r9-eyebrow">CONNECT WITH REGION 9</span><h2>Social Media & Forecast Updates</h2><p>Follow, share, and stay connected with the latest Region 9 Weather information.</p></div></div>
      <div class="r9-social-links">
        <?php foreach(array('facebook'=>'Facebook','x'=>'X','instagram'=>'Instagram','youtube'=>'YouTube') as $network=>$label): $url=trim((string)r9_setting($network.'_url','')); if($url): ?>
          <a class="r9-social-profile r9-social-<?php echo esc_attr($network); ?>" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><strong><?php echo esc_html($label); ?></strong><span>Follow Region 9 Weather</span></a>
        <?php endif; endforeach; ?>
      </div>
      <?php $social_shortcode=trim((string)r9_setting('social_feed_shortcode','')); if($social_shortcode): ?><div class="r9-social-feed"><?php echo do_shortcode($social_shortcode); ?></div><?php else: ?><div class="r9-social-feed-placeholder"><strong>Social feed area</strong><span>Paste a supported social-feed plugin shortcode in Region 9 Studio → Live Controls.</span></div><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="r9-section r9-radar"><div class="r9-wrap"><div class="r9-section-title r9-light-title"><div><h2>Live KILX Radar</h2><p>Interactive WeatherFront radar remains active.</p></div></div><div class="r9-panel"><iframe title="KILX WeatherFront Radar" loading="lazy" allowfullscreen src="<?php echo esc_url(r9_setting('radar_url','https://app.weatherfront.com/radar/KILX'));?>"></iframe></div></div></section>
