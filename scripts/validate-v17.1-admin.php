<?php
$root=dirname(__DIR__);$admin=file_get_contents($root.'/plugins/region9-live-studio/includes/class-admin.php');$theme_admin=file_get_contents($root.'/inc/admin-studio.php');$guidance=file_get_contents($root.'/plugins/region9-live-studio/includes/class-national-guidance.php');$rest=file_get_contents($root.'/plugins/region9-live-studio/includes/class-rest-api.php');$scheduler=file_get_contents($root.'/plugins/region9-live-studio/includes/class-scheduler.php');
$passes=0;function admin_pass($name,$condition){global $passes;if(!$condition){fwrite(STDERR,"FAIL: $name\n");exit(1);}$passes++;echo "PASS: $name\n";}
admin_pass('one authoritative top-level menu',substr_count($admin,"add_menu_page('Region 9 Studio Automation'")===1&&strpos($theme_admin,"The active plugin owns every Region 9 Studio submenu")!==false);
admin_pass('dedicated submenu callbacks',strpos($admin,"add_submenu_page('r9ls'")!==false&&strpos($admin,'array($this, $page[2])')!==false);
foreach(array('forecast_production_page','alert_center_page','source_health_page','scheduler_health_page','automation_page','theme_setup_page','theme_health_page','backup_page','live_controls_page','partners_page','clients_page','production_page','rural_operations_page','rural_reports_page','protection_page','system_health_page','backup_protection_page') as $method){admin_pass('dashboard renderer '.$method,strpos($admin,'function '.$method.'(')!==false);}
admin_pass('Alert Center normalized records',strpos($admin,"r9ls_canonical_alert_state")!==false&&strpos($admin,'Canonical normalized alerts')!==false&&strpos($admin,'Alert timeline')!==false&&strpos($admin,'raw payload')===false);
admin_pass('source health operational fields',strpos($admin,'MRMS')!==false&&strpos($admin,'Grid Forecast')!==false&&strpos($admin,'Last success')!==false&&strpos($admin,'Retry status')!==false&&strpos($guidance,"last_failure_time")!==false);
admin_pass('scheduler health operational fields',strpos($admin,'Next scheduler run')!==false&&strpos($admin,'Running status')!==false&&strpos($admin,'Failure history')!==false&&strpos($scheduler,'r9ls_validation_lock')!==false);
admin_pass('automation lifecycle dashboard',strpos($admin,'Current automation state')!==false&&strpos($admin,'Alert refresh')!==false&&strpos($admin,'Source refresh')!==false);
admin_pass('Forecast Production controls',strpos($admin,'Approve selected')!==false&&strpos($admin,'Reject selected')!==false&&strpos($admin,'Publish approved')!==false&&strpos($admin,'Details and preview')!==false);
admin_pass('Theme Site Setup inventory',strpos($admin,'GeneratePress parent')!==false&&strpos($admin,'GP Premium')!==false&&strpos($admin,'Required pages')!==false&&strpos($admin,'REST routes')!==false);
admin_pass('Theme System Health inventory',strpos($admin,"'PHP'=>PHP_VERSION")!==false&&strpos($admin,"'Filesystem'=>")!==false&&strpos($admin,'Recovery suggestions')!==false);
admin_pass('backup import and export',strpos($admin,'r9ls_backup_export')!==false&&strpos($admin,'r9ls_backup_import')!==false&&strpos($admin,'backup_options')!==false);
admin_pass('live controls',strpos($admin,'Emergency Mode')!==false&&strpos($admin,'Homepage hero')!==false&&strpos($admin,'Publication Lock')!==false&&strpos($admin,'Override expiration')!==false);
admin_pass('REST public boundary retained',strpos($rest,"approval_state'] ?? '') === 'approved'")!==false&&strpos($rest,"publication_state'] ?? '') === 'published'")!==false);
admin_pass('nonce enforcement',strpos($admin,"check_admin_referer(\$nonce)")!==false&&substr_count($admin,'wp_nonce_field(')>=7);
admin_pass('capability enforcement',strpos($admin,"current_user_can('manage_options')")!==false);
admin_pass('responsive admin design',strpos($admin,'r9ls-status-grid')!==false&&strpos($admin,'@media(max-width:782px)')!==false);
echo "v17.1 admin experience validation complete with $passes assertions.\n";
