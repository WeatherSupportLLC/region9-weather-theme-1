<?php
$root = dirname(__DIR__);
$passes = 0;
function pass($name, $condition) { global $passes; if (!$condition) { fwrite(STDERR, "FAIL: $name\n"); exit(1); } $passes++; echo "PASS: $name\n"; }
function readf($path) { $body = file_get_contents($path); if ($body === false) { fwrite(STDERR, "FAIL: cannot read $path\n"); exit(1); } return $body; }
$style = readf($root . '/style.css');
$functions = readf($root . '/functions.php');
$header = readf($root . '/header.php');
$page = readf($root . '/page.php');
$front = readf($root . '/front-page.php');
$home = readf($root . '/template-parts-studio-home.php');
$footer = readf($root . '/footer.php');
$admin = readf($root . '/inc/admin-studio.php');
$integration = readf($root . '/inc/live-studio-integration.php');
$theme_build = readf($root . '/scripts/build-region9-weather-theme-zip.sh');

pass('GeneratePress child theme template preserved', preg_match('/^Template:\s*generatepress\s*$/mi', $style));
pass('Region 9 Weather theme name preserved', stripos($style, 'Theme Name: Region 9 Weather') !== false);
pass('Region 9 branding CSS preserved', strpos($style, '.r9-studio-bar') !== false && strpos($style, '.r9-header-logo') !== false);
pass('major public Live Studio CSS remains additive', strpos($style, '.r9ls-product-card') !== false && strpos($style, 'Region 9 Live Studio 17.1 canonical product components') !== false);
pass('primary and footer menus preserved', strpos($functions, "'r9_studio_menu'") !== false && strpos($functions, "'r9_footer_menu'") !== false);
foreach (array('r9-live-sidebar','r9-forecast-sidebar','r9-alert-sidebar','r9-footer-one','r9-footer-two') as $sidebar) { pass('sidebar preserved ' . $sidebar, strpos($functions, "'$sidebar'") !== false); }
foreach (array('daily','about','severe-weather','hazards','temperature-outlook','precipitation-outlook','travel-outdoor','agriculture','anxiety','radar','alerts','storm-timing','threat-breakdown','watches-warnings','special','contact','city-forecast','outage-tracker','partners','clients','production','rural-operations','rural-reports','protection','backup') as $slug) { pass('page inventory preserved ' . $slug, strpos($functions, "'$slug'") !== false); }
pass('header uses Region 9 logo and primary nav', strpos($header, 'region9-logo-transparent.png') !== false && strpos($header, 'r9-main-nav') !== false && strpos($header, 'r9_studio_menu') !== false);
pass('homepage template remains theme-driven', strpos($front, 'template-parts-studio-home.php') !== false);
pass('v17.1 homepage restores primary radar and operations sidebar', strpos($home, 'r9-home-radar-hero') !== false && strpos($home, 'r9-home-sidebar') !== false);
pass('v17.1 homepage restores outage and sidebar modules', strpos($home, 'r9_home_outage_module') !== false && strpos($functions, 'r9_home_alert_sidebar_module') !== false && strpos($functions, 'r9_home_operations_sidebar_module') !== false);
pass('page layout preserves content plus forecast sidebar', strpos($page, 'r9-page-grid') !== false && strpos($page, "is_active_sidebar('r9-forecast-sidebar')") !== false);
pass('legacy forecast discussion layout preserved as fallback', strpos($page, 'Forecast Discussion') !== false && strpos($page, 'r9_media_placeholder') !== false);

pass('professional operational empty-state helper exists', strpos($functions, 'function r9_professional_empty_state') !== false && strpos($functions, 'No unapproved or speculative operational details') !== false);
pass('operational pages route through polished renderer', strpos($functions, 'function r9_render_operational_page') !== false && strpos($page, '$operational=function_exists') !== false);
pass('professional sidebar fallback exists', strpos($functions, 'function r9_default_sidebar') !== false && strpos($page, 'r9_default_sidebar()') !== false);
pass('status component library CSS exists', strpos($style, '.r9-status-pill') !== false && strpos($style, '.r9-operational-layout') !== false);
pass('alert center has no-alert professional state', strpos($functions, 'No active weather alerts.') !== false && strpos($functions, 'Region 9 Alert Map') !== false);
pass('public templates avoid unfinished labels', stripos($functions . $page, 'coming soon') === false && stripos($functions . $page, 'under construction') === false);
pass('Live Studio products are inserted only within existing content area', strpos($page, 'r9ls_theme_product_grid($slug)') !== false && strpos($page, 'get_header()') < strpos($page, 'r9ls_theme_product_grid($slug)'));
pass('footer Region 9 identity preserved', strpos($footer, 'Region 9 Weather') !== false && strpos($footer, 'Weather Support LLC') !== false);
pass('legacy theme admin remains available when Live Studio inactive', strpos($admin, "add_menu_page('Region 9 Studio'") !== false && strpos($admin, 'Legacy theme controls are shown only when Region 9 Live Studio is inactive') !== false);
pass('Live Studio delegates theme tools to authoritative plugin menu', strpos($admin, 'The active plugin owns every Region 9 Studio submenu') !== false && strpos($admin, "remove_menu_page('r9-studio')") !== false && strpos($admin, "add_submenu_page('r9ls'") === false);
pass('Live Studio detection is helper-based not slug-only', strpos($integration, 'function r9ls_theme_rc1_active') !== false && strpos($integration, 'version_compare') !== false && strpos($integration, '17.1.0') !== false);
pass('theme package excludes plugin and development directories', strpos($theme_build, 'cp -R "$ROOT/inc" "$ROOT/assets"') !== false && strpos($theme_build, "(plugins|scripts|tests|docs|") !== false && strpos($theme_build, 'wp-config') !== false);
pass('no public MutationObserver DOM scanning introduced', strpos(readf($root . '/assets/js/studio.js'), 'MutationObserver') === false && strpos(readf($root . '/assets/js/v52.js'), 'MutationObserver') === false);
$report = array('passes'=>$passes,'generated_at'=>date('c'),'scope'=>'17.1 public surface preservation guardrails','reference_note'=>'No v15/v15.1 reference ZIP is present in this checkout; this static suite guards the preserved GeneratePress child-theme surface currently available in the repository.');
$build = $root . '/build'; if (!is_dir($build)) { mkdir($build, 0777, true); }
file_put_contents($build . '/rc1-public-surface-report.json', json_encode($report, JSON_PRETTY_PRINT));
echo "17.1 public surface validation complete with $passes assertions.\n";
