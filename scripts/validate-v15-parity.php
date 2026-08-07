<?php
$root = dirname(__DIR__);
$passes = 0;
function ok($name, $condition) { global $passes; if (!$condition) { fwrite(STDERR, "FAIL: $name\n"); exit(1); } $passes++; echo "PASS: $name\n"; }
function body($relative) { global $root; $path = $root . '/' . $relative; $contents = file_get_contents($path); if ($contents === false) { fwrite(STDERR, "FAIL: missing $relative\n"); exit(1); } return $contents; }
$style = body('style.css'); $functions = body('functions.php'); $page = body('page.php'); $front = body('front-page.php'); $header = body('header.php'); $footer = body('footer.php'); $admin = body('inc/admin-studio.php'); $plugin_admin = body('plugins/region9-live-studio/includes/class-admin.php'); $rest = body('plugins/region9-live-studio/includes/class-rest-api.php'); $products = body('plugins/region9-live-studio/includes/class-product-generator.php'); $catalog = body('plugins/region9-live-studio/includes/class-product-catalog.php'); $integration = body('inc/live-studio-integration.php');

ok('GeneratePress Template header unchanged', preg_match('/^Template:\s*generatepress\s*$/mi', $style));
ok('Region 9 theme name remains', preg_match('/^Theme Name:\s*Region 9 Weather/mi', $style));
ok('homepage remains template-driven', strpos($front, 'template-parts-studio-home.php') !== false);
ok('header primary navigation remains', strpos($header, 'r9-main-nav') !== false && strpos($header, 'r9_studio_menu') !== false);
ok('footer branding remains', strpos($footer, 'Region 9 Weather') !== false && strpos($footer, 'Weather Support LLC') !== false);
foreach (array('r9_studio_menu','r9_footer_menu') as $menu) { ok('menu registered ' . $menu, strpos($functions, "'$menu'") !== false); }
foreach (array('r9-live-sidebar','r9-forecast-sidebar','r9-alert-sidebar','r9-footer-one','r9-footer-two') as $sidebar) { ok('sidebar registered ' . $sidebar, strpos($functions, "'$sidebar'") !== false); }
foreach (array('daily','about','severe-weather','hazards','temperature-outlook','precipitation-outlook','travel-outdoor','agriculture','anxiety','radar','alerts','storm-timing','threat-breakdown','watches-warnings','special','contact','city-forecast','outage-tracker','partners','clients','production','rural-operations','rural-reports','protection','backup') as $slug) { ok('required page slug present ' . $slug, strpos($functions, "'$slug'") !== false); }
foreach (array('daily','hazards','temperature-outlook','precipitation-outlook','travel-outdoor','agriculture','special','severe-weather') as $slug) { ok('catalog or product map keeps forecast page ' . $slug, strpos($functions . $integration, "'$slug'") !== false); }
ok('forecast page keeps graphic placeholder', strpos($page, 'r9_media_placeholder') !== false);
ok('forecast page keeps discussion placeholder', strpos($page, 'Forecast Discussion') !== false);
ok('forecast page keeps forecast sidebar', strpos($page, "is_active_sidebar('r9-forecast-sidebar')") !== false);

ok('professional public empty-state helper remains', strpos($functions, 'function r9_professional_empty_state') !== false);
ok('operational page renderer remains', strpos($functions, 'function r9_render_operational_page') !== false);
ok('public status pills remain styled', strpos($style, '.r9-status-pill') !== false);
ok('fallback pages are polished not unfinished', stripos($functions . $page, 'coming soon') === false && stripos($functions . $page, 'under construction') === false);
ok('alert center shortcode remains', strpos($functions, "add_shortcode('region9_alert_center'") !== false);
ok('studio home shortcode remains', strpos($functions, "add_shortcode('region9_studio_home'") !== false);
ok('outage tracker shortcode remains', strpos($functions, "add_shortcode('region9_outage_tracker'") !== false);
ok('public product shortcode remains', strpos($integration, "add_shortcode('r9ls_public_product'") !== false);
foreach (array('r9ls','r9-studio-setup','r9-studio-health','r9-studio-backup','r9-studio-partners','r9-studio-clients','r9-studio-production','r9-studio-rural-reports','r9-studio-rural-operations','r9-studio-protection') as $slug) { ok('admin page/menu slug present ' . $slug, strpos($admin . $plugin_admin, $slug) !== false); }
foreach (array('r9ls_validate','r9ls_settings','r9ls_change','r9ls_override') as $action) { ok('admin post action present ' . $action, strpos($plugin_admin, $action) !== false); }

// Legacy v15 identifiers may now be compatibility aliases to the canonical 28-product catalog.
$legacy_surface = $products . $catalog . $integration;
foreach (array('morning-brief','todays-forecast','seven-day-forecast','headlines','severe-weather-risk','threat-breakdown','storm-timing','travel','agriculture','fieldwork','spraying','harvest','livestock','outdoor','schools','construction','forecast-confidence','decision-support-brief','watching') as $product) { ok('legacy product/alias present ' . $product, strpos($legacy_surface, "'$product'") !== false); }
foreach (array('morning-weather-brief','todays-forecast','seven-day-forecast','evening-weather-update','weekly-weather-hazards','severe-weather-outlook','storm-timing','threat-breakdown','watch-warning-explainer','seven-day-heat-outlook','heat-safety-alert','wind-chill-outlook','frost-freeze-outlook','agriculture-weather-outlook','spray-window-forecast','fieldwork-outlook','livestock-weather-stress','travel','commute-forecast','outdoor-event-planner','lightning-risk-outlook','forecast-rainfall','observed-rainfall-totals','drought-dryness-update','storm-anxiety-outlook','what-were-watching','forecast-confidence-meter','decision-support-brief') as $product) { ok('canonical 28-product catalog present ' . $product, strpos($catalog, "'$product'") !== false); }

foreach (array('register_rest_route', 'read(', 'history') as $needle) { ok('REST surface contains ' . $needle, strpos($rest, $needle) !== false); }
foreach (array('docs/v15-v17-parity-matrix.md','docs/public-page-inventory.md','docs/sidebar-widget-inventory.md','docs/navigation-inventory.md','docs/admin-menu-inventory.md','docs/theme-audit.md','docs/plugin-audit.md','docs/image-placeholder-inventory.md','docs/forecast-page-audit.md') as $doc) { ok('inventory document exists ' . $doc, is_file($root . '/' . $doc)); }
$report = array('passes'=>$passes,'generated_at'=>date('c'),'baseline'=>'v15/v17 public surface plus canonical 28-product catalog and compatibility aliases','purpose'=>'Fail if required pages, menus, sidebars, placeholders, admin pages, REST routes, theme relationship, legacy aliases, canonical product catalog, or inventory docs disappear.');
$build = $root . '/build'; if (!is_dir($build)) { mkdir($build, 0777, true); }
file_put_contents($build . '/v15-parity-report.json', json_encode($report, JSON_PRETTY_PRINT));
echo "v15/v17 parity guard complete with $passes assertions.\n";
