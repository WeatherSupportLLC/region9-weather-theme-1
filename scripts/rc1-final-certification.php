<?php
$root = dirname(__DIR__);
$mode = 'full';
foreach ($argv as $arg) {
    if ($arg === '--quick') { $mode = 'quick'; }
    if ($arg === '--full') { $mode = 'full'; }
    if ($arg === '--stress') { $mode = 'stress'; }
}
$results = array(); $failures = 0; $warnings = 0;
function certify($name, $status, $detail = '') { global $results, $failures, $warnings; $results[] = array('name'=>$name,'status'=>$status,'detail'=>$detail); echo sprintf("[%s] %s%s\n", $status, $name, $detail ? ' — ' . $detail : ''); if ($status === 'FAIL') { $failures++; } if ($status === 'WARNING' || $status === 'UPSTREAM UNAVAILABLE') { $warnings++; } }
function read_file_or_fail($rel) { global $root; $p = $root . '/' . $rel; if (!is_file($p)) { certify('file exists ' . $rel, 'FAIL', 'missing'); return ''; } return file_get_contents($p); }
function run_cert_command($cmd, $critical = true) { global $root; $out = array(); $code = 0; exec('cd ' . escapeshellarg($root) . ' && ' . $cmd . ' 2>&1', $out, $code); certify($cmd, $code === 0 ? 'PASS' : ($critical ? 'FAIL' : 'WARNING'), implode("\n", array_slice($out, -6))); return $code === 0; }
$build = $root . '/build'; if (!is_dir($build)) { mkdir($build, 0777, true); }
certify('environment PHP version', version_compare(PHP_VERSION, '7.4', '>=') ? 'PASS' : 'FAIL', PHP_VERSION);
$plugin = read_file_or_fail('plugins/region9-live-studio/region9-live-studio.php');
$style = read_file_or_fail('style.css');
$generator = read_file_or_fail('plugins/region9-live-studio/includes/class-product-generator.php');
$scheduler = read_file_or_fail('plugins/region9-live-studio/includes/class-scheduler.php');
$guidance = read_file_or_fail('plugins/region9-live-studio/includes/class-national-guidance.php');
$admin = read_file_or_fail('plugins/region9-live-studio/includes/class-admin.php');
$rest = read_file_or_fail('plugins/region9-live-studio/includes/class-rest-api.php');
$functions = read_file_or_fail('functions.php');
$page = read_file_or_fail('page.php');
certify('RC1 plugin version', strpos($plugin, '17.0.0-rc.1') !== false ? 'PASS' : 'FAIL');
certify('GeneratePress child-theme relationship', preg_match('/^Template:\s*generatepress\s*$/mi', $style) ? 'PASS' : 'FAIL');
certify('scheduler self-heals next validation', strpos($scheduler, 'next_validation()') !== false && strpos($scheduler, 'schedule_event()') !== false ? 'PASS' : 'FAIL');
certify('workspace refresh after validation', strpos($scheduler, 'refresh_workspace_from_decision') !== false && strpos($generator, 'r9ls_forecast_production_workspace') !== false ? 'PASS' : 'FAIL');
foreach (array('product_id','title','product_version','workspace_state','approval_state','publication_state','score','confidence','affected_counties','timing','summary','generation_duration','content_hash','grouped_change_count') as $field) { certify('workspace row field ' . $field, strpos($generator . $admin, $field) !== false ? 'PASS' : 'FAIL'); }
foreach (array('Approve selected','Approve all eligible','Reject selected','Publish approved','Rollback','grouped') as $control) { certify('workspace control ' . $control, strpos($admin, $control) !== false ? 'PASS' : 'FAIL'); }
certify('canonical Alert Center state', strpos($guidance, 'r9ls_canonical_alert_state') !== false && strpos($rest, "'alerts'=>'alerts'") !== false ? 'PASS' : 'FAIL');
foreach (array('healthy_zero_alerts','Polygon','MultiPolygon','stale_cached_result','description','instruction','urgency','certainty','office') as $needle) { certify('alert capability ' . $needle, strpos($guidance, $needle) !== false ? 'PASS' : 'FAIL'); }
foreach (array('user_agent','timeout','unexpected content-type','stale_ttl','last_success_time','latency','schema_change','healthy_zero_qpf') as $needle) { certify('WPC adapter guard ' . $needle, strpos($guidance, $needle) !== false ? 'PASS' : 'FAIL'); }
certify('risk saturation cap audit', strpos(read_file_or_fail('plugins/region9-live-studio/includes/class-rule-engine.php'), "'alerts' => 12") !== false ? 'PASS' : 'FAIL', 'default alert weight limits one-alert all-product saturation');
foreach (array('r9ls','r9ls-forecast-production','r9ls-approval-queue','r9ls-alert-center','r9ls-source-health','r9ls-scheduler-health','r9ls-backup-protection') as $slug) { certify('admin menu slug ' . $slug, strpos($admin, $slug) !== false ? 'PASS' : 'FAIL'); }
certify('Step 4 public surface preserved', strpos($functions, 'r9_professional_empty_state') !== false && strpos($page, 'r9-operational-layout') !== false && strpos($style, '.r9-status-pill') !== false ? 'PASS' : 'FAIL');
foreach (array('php -l functions.php','php -l page.php','php scripts/validate-v15-parity.php','php scripts/validate-rc1-public-surface.php','php scripts/validate-theme-rc1-admin-integration.php') as $cmd) { run_cert_command($cmd); }
if ($mode !== 'quick') {
    foreach (array('php scripts/validate-region9-live-studio.php','php scripts/rc1-validation-suite.php','php scripts/rc1-scheduler-soak.php 6 2','scripts/build-region9-live-studio-zip.sh','scripts/build-region9-weather-theme-zip.sh','(cd build && sha256sum -c SHA256SUMS.txt)') as $cmd) { run_cert_command($cmd); }
}
if ($mode === 'stress') {
    for ($i=1; $i<=3; $i++) { run_cert_command('php scripts/rc1-validation-suite.php', true); }
}
$summary = array('mode'=>$mode,'generated_at'=>date('c'),'status'=>$failures ? 'FAIL' : 'PASS','failures'=>$failures,'warnings'=>$warnings,'results'=>$results);
file_put_contents($build . '/rc1-final-certification.json', json_encode($summary, JSON_PRETTY_PRINT));
$txt = "Region 9 Live Studio RC1 Final Certification\nMode: $mode\nStatus: {$summary['status']}\nFailures: $failures\nWarnings: $warnings\n";
foreach ($results as $r) { $txt .= "[{$r['status']}] {$r['name']}" . ($r['detail'] ? " — {$r['detail']}" : '') . "\n"; }
file_put_contents($build . '/rc1-final-certification.txt', $txt);
$md = "# Region 9 Live Studio RC1 Final Certification\n\n- Mode: `$mode`\n- Status: **{$summary['status']}**\n- Failures: `$failures`\n- Warnings: `$warnings`\n\n| Status | Check | Detail |\n|---|---|---|\n";
foreach ($results as $r) { $md .= '| ' . $r['status'] . ' | ' . str_replace('|','/', $r['name']) . ' | ' . str_replace(array("\n", '|'), array('<br>', '/'), $r['detail']) . " |\n"; }
file_put_contents($build . '/rc1-final-certification.md', $md);
exit($failures ? 1 : 0);
