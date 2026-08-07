<?php if (!defined('ABSPATH')) exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="r9-header-stack">
  <header class="r9-studio-bar">
    <div class="r9-wrap r9-studio-bar-inner">
      <a class="r9-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Region 9 Weather home">
        <img class="r9-header-logo" src="<?php echo esc_url(get_stylesheet_directory_uri().'/assets/images/region9-logo-transparent.png'); ?>" alt="Region 9 Weather">
      </a>
      <button class="r9-menu-toggle" type="button" aria-expanded="false" aria-controls="r9-main-nav">Menu</button>
      <nav id="r9-main-nav" class="r9-nav" aria-label="Region 9 Weather primary navigation">
        <?php wp_nav_menu(array('theme_location'=>'r9_studio_menu','container'=>false,'fallback_cb'=>'r9_menu_fallback','depth'=>2)); ?>
      </nav>
      <div class="r9-header-tools">
        <div class="r9-header-clock" aria-label="Current Central Time"><strong id="r9-header-clock">--:--</strong><small id="r9-header-date">Central Time</small></div>
        <div class="r9-header-risk <?php echo esc_attr(r9_risk_class()); ?>" aria-label="Current Region 9 risk level"><span class="r9-header-risk-dot" aria-hidden="true"></span><span class="r9-header-risk-copy"><small>Risk Level</small><strong><?php echo esc_html(r9_risk_label()); ?></strong></span></div>
      </div>
    </div>
  </header>
  <?php if(shortcode_exists('r9_latest_weather_crawl')): ?>
    <?php echo do_shortcode('[r9_latest_weather_crawl]'); ?>
  <?php elseif(r9_setting('latest_update_enabled',true)): ?>
    <div class="r9-breaking"><div class="r9-breaking-label">LATEST WEATHER UPDATE</div><div class="r9-breaking-window"><div class="r9-breaking-track"><?php echo esc_html(r9_setting('breaking_news','Region 9 Weather is monitoring conditions across east-central Illinois.')); ?></div></div><div class="r9-breaking-time">Updated <?php echo esc_html(r9_updated_label()); ?></div></div>
  <?php endif; ?>
  <?php if(shortcode_exists('r9_live_region9_alerts')): ?>
    <?php echo do_shortcode('[r9_live_region9_alerts]'); ?>
  <?php else: ?>
    <div id="r9-alert-bug" class="r9-alert-bug is-loading" role="region" aria-label="Region 9 severe weather alerts"><div class="r9-alert-label"><span class="r9-alert-pulse"></span><strong>LIVE ALERTS</strong></div><div class="r9-alert-viewport" aria-live="polite"><div id="r9-alert-track" class="r9-alert-track">Checking Region 9 counties for active alerts…</div></div><div id="r9-alert-status" class="r9-alert-status">LIVE</div></div>
  <?php endif; ?>
</div>
