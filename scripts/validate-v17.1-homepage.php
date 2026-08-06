<?php
$root = dirname(__DIR__);
$checks = 0; $fails = 0;
function v171_ok($label, $condition) { global $checks, $fails; $checks++; echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . PHP_EOL; if (!$condition) { $fails++; } }
function v171_read($file) { return file_get_contents(dirname(__DIR__) . '/' . $file); }
$front = v171_read('front-page.php');
$home = v171_read('template-parts-studio-home.php');
$functions = v171_read('functions.php');
$style = v171_read('style.css');
$page = v171_read('page.php');
$sidebar = is_file($root . '/sidebar.php') ? v171_read('sidebar.php') : '';

v171_ok('front page remains template driven', strpos($front, 'template-parts-studio-home.php') !== false);
v171_ok('prominent live radar/map module exists', strpos($home, 'r9-home-radar-hero') !== false && strpos($home, 'data-r9-home-module="primary-radar"') !== false && strpos($home, "r9_setting('radar_url'") !== false);
v171_ok('radar is before decision dashboard', strpos($home, 'r9-home-radar-hero') !== false && strpos($home, 'r9-impact-dashboard') !== false && strpos($home, 'r9-home-radar-hero') < strpos($home, 'r9-impact-dashboard'));
v171_ok('outage tracker module exists', strpos($home, 'r9_home_outage_module') !== false && strpos($functions, 'function r9_home_outage_module') !== false && strpos($functions, 'data-r9-home-module="outage-tracker"') !== false);
v171_ok('true right sidebar exists', strpos($home, 'r9-home-sidebar') !== false && strpos($home, 'data-r9-home-module="right-sidebar"') !== false && is_file($root . '/sidebar.php'));
v171_ok('Alert Center sidebar module exists', strpos($functions, 'function r9_home_alert_sidebar_module') !== false && strpos($functions, 'data-r9-sidebar-module="alert-center"') !== false && strpos($functions, 'r9ls_get_canonical_alert_state') !== false);
v171_ok('Weather Operations sidebar module exists', strpos($functions, 'function r9_home_operations_sidebar_module') !== false && strpos($functions, 'data-r9-sidebar-module="weather-operations"') !== false);
v171_ok('risk and confidence sidebar module exists', strpos($functions, 'function r9_home_risk_sidebar_module') !== false && strpos($functions, 'Current Region 9 Risk') !== false && strpos($functions, 'Forecast confidence') !== false);
v171_ok('forecast image placeholder supported', strpos($functions, 'r9_media_placeholder($title') !== false || strpos($functions, "r9_media_placeholder($title.' Forecast Graphic')") !== false);
v171_ok('forecast discussion panel exists', strpos($functions, 'r9-forecast-discussion-panel') !== false && strpos($functions, 'Forecast Discussion') !== false);
v171_ok('latest update timestamp included', strpos($home, 'r9_updated_label()') !== false && strpos($functions, 'Latest update:') !== false);
v171_ok('mobile stacking markup/classes present', strpos($home, 'r9-mobile-stack-home') !== false && strpos($home, 'r9-home-mobile-priority') !== false && strpos($style, '@media(max-width:1050px)') !== false);
v171_ok('GeneratePress parent declaration preserved', strpos(v171_read('style.css'), 'Template: generatepress') !== false);
v171_ok('GeneratePress parent enqueue preserved', strpos($functions, "wp_enqueue_style('generatepress-parent'") !== false);
v171_ok('sidebar fallback cannot disappear', strpos($functions, 'function r9_home_sidebar_fallback') !== false && strpos($sidebar, 'r9_home_sidebar_fallback') !== false);
v171_ok('Alert page can render alert sidebar fallback', strpos($page, "slug==='alerts'") !== false && strpos($page, 'r9_home_alert_sidebar_module') !== false);
v171_ok('homepage template introduces no render-time HTTP calls', strpos($home, 'wp_remote_') === false && strpos($home, 'wp_safe_remote_') === false);
v171_ok('sidebar template introduces no render-time HTTP calls', strpos($sidebar, 'wp_remote_') === false && strpos($sidebar, 'wp_safe_remote_') === false);
foreach (array('r9_home_alert_sidebar_module','r9_home_operations_sidebar_module','r9_home_outage_module','r9_home_risk_sidebar_module','r9_home_forecast_module','r9_home_sidebar_fallback') as $fn) {
    v171_ok('helper defined ' . $fn, strpos($functions, 'function ' . $fn) !== false);
    v171_ok('helper referenced ' . $fn, strpos($home . $sidebar . $page, $fn) !== false);
}
if ($fails) { fwrite(STDERR, "v17.1 homepage validation failed with $fails failure(s) across $checks checks.\n"); exit(1); }
$build=$root.'/build';if(!is_dir($build)){mkdir($build,0777,true);}file_put_contents($build.'/v17.1-homepage-report.json',json_encode(array('version'=>'17.1.0','assertions'=>$checks,'failures'=>$fails,'status'=>'PASS','generated_at'=>date('c')),JSON_PRETTY_PRINT));
echo "v17.1 homepage validation complete with $checks assertions." . PHP_EOL;
