<?php if (!defined('ABSPATH')) exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_head(); ?>
<link rel="stylesheet" href="<?php echo esc_url(get_stylesheet_directory_uri().'/assets/css/broadcast-global.css?v=20260828-2'); ?>">
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="r9-header-stack">
  <header class="r9-topbar">
    <div class="r9-wrap r9-topbar-inner">
      <a class="r9-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Region 9 Weather home">
        <img class="r9-header-logo" src="<?php echo esc_url(get_stylesheet_directory_uri().'/assets/images/region9-logo-white-gold.png'); ?>" alt="Region 9 Weather">
      </a>
      <div class="r9-alert-banner" aria-label="Region 9 alert status">
        <strong>⚠ ACTIVE ALERTS</strong>
        <span>Kankakee, Iroquois, Ford, Livingston, McLean, DeWitt, Piatt, Champaign, Vermilion</span>
      </div>
      <div class="r9-top-risk" aria-label="Current Region 9 risk level">
        <div><small>Risk Level</small><strong class="<?php echo esc_attr(r9_risk_class()); ?>"><?php echo esc_html(strtoupper(r9_risk_label())); ?></strong></div>
        <div class="r9-top-risk-time">Updated <?php echo esc_html(r9_updated_label()); ?></div>
      </div>
    </div>
  </header>
  <div class="r9-mainnav-bar">
    <div class="r9-wrap r9-mainnav-inner">
      <nav id="r9-main-nav" class="r9-nav r9-nav-home-only" aria-label="Region 9 Weather primary navigation">
        <ul class="menu"><li class="menu-item current-menu-item"><a href="<?php echo esc_url(home_url('/')); ?>">Home</a></li></ul>
      </nav>
    </div>
  </div>
  <?php if(r9_setting('latest_update_enabled',true)): ?>
  <div class="r9-breaking">
    <div class="r9-breaking-label">LATEST WEATHER UPDATE</div>
    <div class="r9-breaking-window"><div class="r9-breaking-track"><?php echo esc_html(r9_setting('breaking_news','Region 9 Weather is monitoring conditions across east-central Illinois.')); ?></div></div>
    <div class="r9-breaking-time">Updated <?php echo esc_html(r9_updated_label()); ?></div>
  </div>
  <?php endif; ?>
  <div id="r9-alert-bug" class="r9-alert-bug is-loading" role="region" aria-label="Region 9 severe weather alerts">
    <div class="r9-alert-label"><span class="r9-alert-pulse"></span><strong>LIVE ALERTS</strong></div>
    <div class="r9-alert-viewport" aria-live="polite"><div id="r9-alert-track" class="r9-alert-track">Checking Region 9 and surrounding counties for active alerts…</div></div>
    <div id="r9-alert-status" class="r9-alert-status">LIVE</div>
  </div>
</div>
