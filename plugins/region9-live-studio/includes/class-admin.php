<?php
defined('ABSPATH') || exit;

class R9LS_Admin {
    private $scheduler;
    private $changes;
    private $audit;
    private $products;

    public function __construct($scheduler, $changes, $audit, $products = null) {
        $this->scheduler = $scheduler;
        $this->changes = $changes;
        $this->audit = $audit;
        $this->products = $products;
    }

    public function hooks() {
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_post_r9ls_validate', array($this, 'validate'));
        add_action('admin_post_r9ls_settings', array($this, 'settings'));
        add_action('admin_post_r9ls_change', array($this, 'change_action'));
        add_action('admin_post_r9ls_product_action', array($this, 'product_action'));
        add_action('admin_post_r9ls_product_batch', array($this, 'product_batch'));
        add_action('admin_post_r9ls_override', array($this, 'override'));
        add_action('admin_post_r9ls_live_controls', array($this, 'save_live_controls'));
        add_action('admin_post_r9ls_admin_repair', array($this, 'repair'));
        add_action('admin_post_r9ls_backup_export', array($this, 'backup_export'));
        add_action('admin_post_r9ls_backup_import', array($this, 'backup_import'));
    }

    public function menu() {
        add_menu_page('Region 9 Studio Automation', 'Region 9 Studio', 'manage_options', 'r9ls', array($this, 'page'), 'dashicons-cloud', 58);
        foreach ($this->menu_pages() as $slug => $page) {
            add_submenu_page('r9ls', $page[0], $page[1], 'manage_options', $slug, array($this, $page[2]));
        }
    }

    public function page() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Administrator access required.', 'r9ls')); }
        $this->screen('Automation Dashboard', array($this, 'render_dashboard'));
    }

    public function forecast_production_page() { $this->screen('Forecast Production', array($this, 'render_workspace')); }
    public function forecast_products_page() { $this->screen('Forecast Products', array($this, 'render_forecast_products')); }
    public function approval_queue_page() { $this->screen('Approval Queue', array($this, 'render_approval_queue')); }
    public function decision_dashboard_page() { $this->screen('Decision Dashboard', array($this, 'render_decision_dashboard')); }
    public function decision_history_page() { $this->screen('Decision History', array($this, 'render_decision_history')); }
    public function publication_history_page() { $this->screen('Publication History', array($this, 'render_publication_history')); }
    public function audit_log_page() { $this->screen('Audit Log', array($this, 'render_audit')); }
    public function alert_center_page() { $this->screen('Alert Center', array($this, 'render_alert_center')); }
    public function source_health_page() { $this->screen('Source Health', array($this, 'render_source_health')); }
    public function scheduler_health_page() { $this->screen('Scheduler Health', array($this, 'render_scheduler_health')); }
    public function automation_page() { $this->screen('Automation', array($this, 'render_automation')); }
    public function theme_setup_page() { $this->screen('Theme Site Setup', array($this, 'render_theme_setup')); }
    public function theme_health_page() { $this->screen('Theme System Health', array($this, 'render_theme_health')); }
    public function backup_page() { $this->screen('Backup & Restore', array($this, 'render_backup')); }
    public function live_controls_page() { $this->screen('Live Controls', array($this, 'render_live_controls')); }
    public function partners_page() { $this->screen('Partners', function(){ $this->render_operations_page('partners'); }); }
    public function clients_page() { $this->screen('Clients', function(){ $this->render_operations_page('clients'); }); }
    public function production_page() { $this->screen('Production', function(){ $this->render_operations_page('production'); }); }
    public function rural_operations_page() { $this->screen('Rural Operations', function(){ $this->render_operations_page('rural-operations'); }); }
    public function rural_reports_page() { $this->screen('Rural Reports', function(){ $this->render_operations_page('rural-reports'); }); }
    public function protection_page() { $this->screen('Protection', function(){ $this->render_operations_page('protection'); }); }
    public function system_health_page() { $this->screen('System Health', array($this, 'render_system_health')); }
    public function backup_protection_page() { $this->screen('Backup & Protection', array($this, 'render_backup_protection')); }

    private function menu_pages() {
        return array(
            'r9ls-automation'=>array('Automation','Automation','automation_page'),
            'r9ls-forecast-production'=>array('Forecast Production','Forecast Production','forecast_production_page'),
            'r9ls-forecast-products'=>array('Forecast Products','Forecast Products','forecast_products_page'),
            'r9ls-approval-queue'=>array('Pending Changes / Approval Queue','Approval Queue','approval_queue_page'),
            'r9ls-decision-dashboard'=>array('Decision Dashboard','Decision Dashboard','decision_dashboard_page'),
            'r9ls-decision-history'=>array('Decision History','Decision History','decision_history_page'),
            'r9ls-publication-history'=>array('Publication History','Publication History','publication_history_page'),
            'r9ls-alert-center'=>array('Alert Center','Alert Center','alert_center_page'),
            'r9ls-source-health'=>array('Source Health','Source Health','source_health_page'),
            'r9ls-scheduler-health'=>array('Scheduler Health','Scheduler Health','scheduler_health_page'),
            'r9ls-audit-log'=>array('Audit Log','Audit Log','audit_log_page'),
            'r9-studio-setup'=>array('Theme Site Setup','Theme Site Setup','theme_setup_page'),
            'r9-studio-health'=>array('Theme System Health','Theme System Health','theme_health_page'),
            'r9-studio-backup'=>array('Backup & Restore','Backup & Restore','backup_page'),
            'r9-studio-live-controls'=>array('Live Controls','Live Controls','live_controls_page'),
            'r9-studio-partners'=>array('Partners','Partners','partners_page'),
            'r9-studio-clients'=>array('Clients','Clients','clients_page'),
            'r9-studio-production'=>array('Production','Production','production_page'),
            'r9-studio-rural-operations'=>array('Rural Operations','Rural Operations','rural_operations_page'),
            'r9-studio-rural-reports'=>array('Rural Reports','Rural Reports','rural_reports_page'),
            'r9-studio-protection'=>array('Protection','Protection','protection_page'),
            'r9ls-system-health'=>array('System Health','System Health','system_health_page'),
            'r9ls-backup-protection'=>array('Backup & Protection','Backup & Protection','backup_protection_page'),
        );
    }

    private function screen($title, $renderer) {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Administrator access required.', 'r9ls')); }
        echo '<div class="wrap r9ls-admin"><h1>' . esc_html($title) . '</h1>';
        $this->notice(); $this->admin_styles(); call_user_func($renderer); echo '</div>';
    }

    private function render_dashboard() {
        $decision = get_option(R9LS_Scheduler::CACHE, array());
        $health = $this->scheduler->health();
        $last = get_option(R9LS_Scheduler::LAST, array());
        $queue = $this->changes->queue();
        $rows = array(
            'Scheduler status'=>($health['status'] ?? 'unknown') . ' — ' . ($health['message'] ?? ''),
            'Current publication version'=>$this->publication_version(), 'Pending changes'=>count($queue),
            'Workspace products'=>count($this->generator()->workspace()['products']), 'Generation duration'=>$this->duration_label(),
            'Current Region 9 risk'=>$this->current_risk($decision), 'Forecast confidence'=>$decision['Forecast Confidence']['confidence'] ?? 'N/A',
            'Last validation'=>$last['time'] ?? 'Never', 'Next validation'=>$this->next_validation_label(),
            'Validation duration'=>isset($last['duration']) ? number_format((float)$last['duration'], 3) . 's' : 'N/A',
        );
        echo '<h2>Operational Status</h2><table class="widefat"><tbody>';
        foreach ($rows as $label=>$value) { echo '<tr><th>' . esc_html($label) . '</th><td>' . esc_html((string)$value) . '</td></tr>'; }
        echo '</tbody></table><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="r9ls_validate">';
        wp_nonce_field('r9ls_validate'); submit_button('Run Manual Validation'); echo '</form>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=r9ls-forecast-production')) . '">Open Forecast Production Workspace</a></p>';
        $this->render_automation();
    }

    private function render_workspace() {
        $workspace = $this->generator()->workspace();
        echo '<h2>Forecast Production Workspace</h2><p>Generation duration: <strong>' . esc_html($this->duration_label($workspace)) . '</strong></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="r9ls_product_batch">';
        wp_nonce_field('r9ls_product_batch');
        echo '<div class="tablenav top"><select name="batch_action"><option value="">Batch actions</option><option value="approve">Approve selected</option><option value="approve_all">Approve all eligible</option><option value="reject">Reject selected</option><option value="publish">Publish approved</option></select> <input name="rejection_reason" type="text" placeholder="Rejection reason"> <button class="button">Apply</button></div>';
        echo '<table class="widefat striped"><thead><tr><th>Select</th><th>Product</th><th>Version</th><th>Workspace state</th><th>Approval state</th><th>Publication state</th><th>Risk</th><th>Confidence</th><th>Counties</th><th>Timing</th><th>Generated</th><th>Duration</th><th>Change count</th><th>Summary</th><th>Actions</th></tr></thead><tbody>';
        if (empty($workspace['products'])) { echo '<tr><td colspan="15">No workspace has been generated. Run a successful manual validation to create review products.</td></tr>'; }
        foreach ($workspace['products'] as $id=>$row) {
            $timing = is_array($row['timing'] ?? '') ? ($row['timing']['label'] ?? $row['timing']['local'] ?? '') : ($row['timing'] ?? '');
            echo '<tr><td><input type="checkbox" name="product_ids[]" value="' . esc_attr($id) . '" aria-label="Select ' . esc_attr($row['title'] ?? $id) . '"></td>';
            echo '<th>' . esc_html($row['title'] ?? $id) . '<details><summary>Details and preview</summary><div class="r9ls-product-preview"><h3>' . esc_html($row['title'] ?? $id) . '</h3><p>' . esc_html($row['summary'] ?? '') . '</p><p><strong>Discussion:</strong> ' . esc_html($row['discussion'] ?? '') . '</p></div><p><strong>Content hash:</strong> <code>' . esc_html($row['content_hash'] ?? '') . '</code></p><p><strong>Previous version:</strong> ' . esc_html((string)($row['previous_version'] ?? 0)) . '</p><p><strong>Source reference:</strong> ' . esc_html($row['source_version_reference'] ?? '') . '</p><p><strong>Publication reference:</strong> ' . esc_html($row['publication_reference'] ?? '') . '</p>' . $this->grouped_changes($row['grouped_changes'] ?? array()) . '</details></th>';
            foreach (array($row['product_version']??'', $row['workspace_state']??'', $row['approval_state']??'', $row['publication_state']??'') as $value) { echo '<td>' . esc_html((string)$value) . '</td>'; }
            echo '<td>' . $this->status_badge($row['risk_label'] ?? ($row['risk']['label'] ?? 'None'), $row['score'] ?? 0) . '</td><td>' . esc_html((string)($row['confidence'] ?? 0)) . '%</td><td>' . esc_html(implode(', ', (array)($row['affected_counties'] ?? array()))) . '</td><td>' . esc_html($timing) . '</td><td>' . esc_html($row['generated_at'] ?? '') . '</td><td>' . esc_html(number_format((float)($row['generation_duration'] ?? 0), 4)) . 's</td><td>' . esc_html((string)($row['grouped_change_count'] ?? 0)) . '</td><td>' . esc_html($row['summary'] ?? '') . '</td><td>' . $this->product_buttons($id, $row) . '</td></tr>';
        }
        echo '</tbody></table></form>';
        echo '<div class="r9ls-admin-links"><a href="' . esc_url(admin_url('admin.php?page=r9ls-decision-dashboard')) . '">Decision Dashboard</a><a href="' . esc_url(admin_url('admin.php?page=r9ls-decision-history')) . '">Decision History</a><a href="' . esc_url(admin_url('admin.php?page=r9ls-publication-history')) . '">Publication History</a></div>';
        $this->theme_integration_card();
    }

    private function render_forecast_products() {
        $products = get_option(R9LS_Product_Generator::PRODUCTS, array());
        $this->cards(array('Published products'=>count((array)$products),'Latest publication'=>$this->publication_version(),'Public boundary'=>'Approved + published only'));
        echo '<table class="widefat striped"><thead><tr><th>Product</th><th>Version</th><th>Risk</th><th>Confidence</th><th>Published</th><th>Public state</th></tr></thead><tbody>';
        foreach ((array)$products as $row) { echo '<tr><th>' . esc_html($row['title']??$row['product_id']??'') . '</th><td>' . esc_html((string)($row['product_version']??'')) . '</td><td>' . esc_html($row['risk_label']??$row['risk']['label']??'None') . '</td><td>' . esc_html((string)($row['confidence']??0)) . '%</td><td>' . esc_html($row['published_at']??$row['updated_at']??'') . '</td><td>' . esc_html($row['publication_state']??'unavailable') . '</td></tr>'; }
        if (!$products) { echo '<tr><td colspan="6">No products have been explicitly published.</td></tr>'; }
        echo '</tbody></table>';
    }

    private function render_approval_queue() {
        $workspace=$this->generator()->workspace();$pending=array_filter($workspace['products'],function($row){return ($row['approval_state']??'pending_review')==='pending_review';});
        $this->cards(array('Pending products'=>count($pending),'Material changes'=>count($this->changes->queue()),'Automatic publication'=>empty(get_option(R9LS_Scheduler::SETTINGS,array())['automatic_publishing'])?'Disabled':'Enabled'));
        echo '<p>Product approvals are isolated by canonical product ID. Use Forecast Production for individual or batch approval, rejection, and publication.</p><p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=r9ls-forecast-production')).'">Review workspace</a></p>';
        $this->render_change_queue();
    }

    private function render_decision_dashboard() {
        $decision=get_option(R9LS_Scheduler::CACHE,array());$risk=$decision['Severe Weather Risk']??array();$confidence=$decision['Forecast Confidence']['confidence']??0;
        $this->cards(array('Region 9 risk'=>$risk['rating']??'None','Operational score'=>(int)($risk['score']??0),'Forecast confidence'=>(int)$confidence.'%','Affected counties'=>count((array)($risk['affected_counties']??array()))));
        echo '<h2>Current decision output</h2><table class="widefat striped"><thead><tr><th>Decision product</th><th>Rating</th><th>Score</th><th>Confidence</th><th>Counties</th><th>Timing</th></tr></thead><tbody>';
        foreach((array)$decision as $name=>$row){echo '<tr><th>'.esc_html($name).'</th><td>'.esc_html($row['rating']??'None').'</td><td>'.esc_html((string)($row['score']??0)).'</td><td>'.esc_html((string)($row['confidence']??0)).'%</td><td>'.esc_html(implode(', ',(array)($row['affected_counties']??array()))).'</td><td>'.esc_html(is_array($row['timing']??'')?wp_json_encode($row['timing']):($row['timing']??'')).'</td></tr>';}
        echo '</tbody></table>';
    }

    private function render_alert_center() {
        $state=get_option('r9ls_canonical_alert_state',array());$alerts=array_values(array_filter((array)($state['alerts']??array()),'is_array'));
        $counts=array('Warnings'=>0,'Watches'=>0,'Advisories'=>0);$counties=array();
        foreach($alerts as $alert){$event=strtolower((string)($alert['event']??''));if(strpos($event,'warning')!==false)$counts['Warnings']++;elseif(strpos($event,'watch')!==false)$counts['Watches']++;else $counts['Advisories']++;$counties=array_merge($counties,(array)($alert['affected_counties']??array()));}
        $this->cards(array('Active alerts'=>count($alerts),'Warnings'=>$counts['Warnings'],'Watches'=>$counts['Watches'],'Advisories'=>$counts['Advisories'],'Alert health'=>ucfirst($state['status']??'unavailable'),'Source health'=>ucfirst($state['source_health']??'unavailable')));
        echo '<p><strong>Last update:</strong> '.esc_html($state['updated']??'Never').' · <strong>Refresh status:</strong> '.esc_html(($state['source_health']??'unavailable')==='healthy'?'Current canonical refresh completed':'Refresh unavailable or degraded').'</p>';
        echo '<h2>County summary</h2><p>'.esc_html($counties?implode(', ',array_values(array_unique($counties))):'No counties are included in active canonical alerts.').'</p>';
        echo '<h2>Canonical normalized alerts</h2><table class="widefat striped"><thead><tr><th>Event</th><th>Severity</th><th>Counties</th><th>Effective</th><th>Expires</th><th>Headline / instruction</th></tr></thead><tbody>';
        foreach($alerts as $alert){echo '<tr><th>'.esc_html($alert['event']??'Weather alert').'</th><td>'.esc_html($alert['severity']??'Unknown').'</td><td>'.esc_html(implode(', ',(array)($alert['affected_counties']??array()))).'</td><td>'.esc_html($alert['effective']??$alert['onset']??'').'</td><td>'.esc_html($alert['ends']??'').'</td><td>'.esc_html($alert['headline']??$alert['description']??'').'<br><em>'.esc_html($alert['instruction']??'').'</em></td></tr>';}
        if(!$alerts)echo '<tr><td colspan="6">No active canonical alerts. Raw source payloads are never displayed.</td></tr>';echo '</tbody></table>';
        echo '<h2>Alert timeline</h2><ol class="r9ls-timeline">';foreach($alerts as $alert){echo '<li><strong>'.esc_html($alert['onset']??$alert['effective']??'Current').'</strong> '.esc_html($alert['event']??'Alert').' — ends '.esc_html($alert['ends']??'when cancelled').'</li>';}if(!$alerts)echo '<li>All clear at the last normalized update.</li>';echo '</ol>';
    }

    private function render_source_health() {
        $health=get_option(R9LS_National_Guidance::HEALTH,array());$sources=array('NWS'=>'nws_alerts','SPC'=>'spc_day1','WPC'=>'wpc_day1_ero','MRMS'=>'mrms','Radar'=>'radar','Grid Forecast'=>'nws_points_grid_hourly','Hourly Forecast'=>'nws_points_grid_hourly');
        echo '<p>Health telemetry is read from normalized source metadata; unavailable integrations are shown explicitly and are not fabricated.</p><table class="widefat striped"><thead><tr><th>Source</th><th>Health</th><th>Last success</th><th>Last failure</th><th>Response time</th><th>Cached age</th><th>Retry status</th><th>Error count</th></tr></thead><tbody>';
        foreach($sources as $label=>$key){$row=$health[$key]??array();$updated=$row['updated']??'';$age=$updated&&strtotime($updated)?human_time_diff(strtotime($updated),time()).' ago':'N/A';$status=$row['source_health']??$row['status']??'unavailable';echo '<tr><th>'.esc_html($label).'</th><td>'.$this->health_badge($status).'</td><td>'.esc_html($row['last_success_time']??'Never').'</td><td>'.esc_html($row['last_failure_time']??($row['error']??'None recorded')).'</td><td>'.esc_html(isset($row['latency'])?number_format((float)$row['latency'],3).'s':'N/A').'</td><td>'.esc_html($age).'</td><td>'.esc_html($row['retry_status']??($status==='healthy'?'Not required':'Scheduled validation retry')).'</td><td>'.esc_html((string)($row['error_count']??(!empty($row['error'])?1:0))).'</td></tr>';}
        echo '</tbody></table>';
    }

    private function render_scheduler_health() {
        $last=get_option(R9LS_Scheduler::LAST,array());$generation=get_option(R9LS_Product_Generator::GENERATION_LAST,array());$latest=get_option(R9LS_Product_Generator::LATEST_PUBLICATION,array());$health=$this->scheduler->health();$next=wp_next_scheduled(R9LS_Scheduler::HOOK);$running=(bool)get_transient(R9LS_Scheduler::LOCK);$failures=array_filter($this->audit->all(),function($entry){return in_array($entry['level']??'',array('error','critical'),true);});
        $this->cards(array('Cron status'=>$next?'Scheduled':'Not scheduled','Running status'=>$running?'Running':'Idle','Queue state'=>count($this->changes->queue()).' material changes','Scheduler health'=>ucfirst($health['status']??'unknown')));
        $this->key_values(array('Last validation'=>$last['time']??'Never','Last generation'=>$generation['generated_at']??'Never','Last publication'=>$latest['published_at']??'Never','Next validation'=>$this->next_validation_label(),'Next scheduler run'=>$next?date_i18n('Y-m-d H:i:s',$next):'Unavailable'));
        echo '<h2>Failure history</h2>';$this->audit_table(array_slice($failures,0,20));
    }

    private function render_automation() {
        $workspace=$this->generator()->workspace();$last=get_option(R9LS_Scheduler::LAST,array());$generation=get_option(R9LS_Product_Generator::GENERATION_LAST,array());$latest=get_option(R9LS_Product_Generator::LATEST_PUBLICATION,array());$alert=get_option('r9ls_canonical_alert_state',array());$source=get_option(R9LS_National_Guidance::HEALTH,array());
        $approved=count(array_filter($workspace['products'],function($r){return ($r['approval_state']??'')==='approved';}));
        $this->cards(array('Validation'=>$last['time']??'Never','Generation'=>$generation['generated_at']??'Never','Approval'=>$approved.' approved','Publication'=>$latest['published_at']??'Never','Alert refresh'=>$alert['updated']??'Never','Source refresh'=>count($source).' tracked','Scheduler'=>$this->scheduler->health()['status']??'unknown','Queue'=>count($this->changes->queue()),'Workspace'=>count($workspace['products']).' products'));
        echo '<h2>Current automation state</h2><progress max="5" value="'.esc_attr($latest?5:($approved?4:($workspace['products']?3:($last?2:1)))).'">Pipeline progress</progress><p>Validation → generation → approval → publication. Publication remains an explicit protected action.</p>';
    }

    private function render_decision_history() {
        echo '<h2>Decision History</h2><table class="widefat striped"><thead><tr><th>Decision</th><th>Mode</th><th>Timestamp</th><th>Risk</th><th>Confidence</th><th>Changes</th><th>Products</th><th>Generation</th><th>Validation</th><th>References</th></tr></thead><tbody>';
        foreach ((array)get_option(R9LS_Product_Generator::DECISION_HISTORY, array()) as $record) {
            if (($record['record_type'] ?? '') !== 'validation_decision') { continue; }
            echo '<tr><th>' . esc_html($record['decision_id']) . '</th><td>' . esc_html($record['validation_mode']) . '</td><td>' . esc_html($record['timestamp']) . '</td><td>' . esc_html($record['region9_risk']['label'] ?? 'None') . '</td><td>' . esc_html((string)$record['confidence']) . '%</td><td>' . esc_html((string)$record['material_change_count']) . '</td><td>' . esc_html(implode(', ', (array)$record['generated_workspace_product_ids'])) . '</td><td>' . esc_html(number_format((float)$record['generation_duration'], 3)) . 's</td><td>' . esc_html(number_format((float)$record['validation_duration'], 3)) . 's</td><td>' . esc_html($record['prior_publication_reference'] . ' → ' . $record['resulting_workspace_reference']) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function render_publication_history() {
        echo '<h2>Publication History</h2><table class="widefat striped"><thead><tr><th>Event</th><th>Product</th><th>Version</th><th>Published</th><th>Actor</th><th>Hash</th><th>Rollback</th></tr></thead><tbody>';
        foreach ((array)get_option(R9LS_Product_Generator::PUBLICATION_HISTORY, array()) as $event) {
            echo '<tr><th>' . esc_html($event['event_type'] ?? '') . '</th><td>' . esc_html($event['product_id'] ?? '') . '</td><td>' . esc_html((string)($event['product_version'] ?? '')) . '</td><td>' . esc_html($event['timestamp'] ?? '') . '</td><td>' . esc_html($event['actor'] ?? '') . '</td><td><code>' . esc_html(substr((string)($event['content_hash'] ?? ''), 0, 12)) . '</code></td><td>' . $this->rollback_button($event) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function render_audit() {
        echo '<h2>Audit Log</h2><table class="widefat striped"><thead><tr><th>Time</th><th>Level</th><th>Event</th><th>Message</th></tr></thead><tbody>';
        foreach ((array)$this->audit->all() as $entry) { echo '<tr><td>' . esc_html($entry['time'] ?? '') . '</td><td>' . esc_html($entry['level'] ?? '') . '</td><td>' . esc_html($entry['context']['event'] ?? '') . '</td><td>' . esc_html($entry['message'] ?? '') . '</td></tr>'; }
        echo '</tbody></table>';
    }

    private function render_theme_setup() {
        $migration=get_option(R9LS_Product_Generator::MIGRATION,array());$required=array('daily','hazards','radar','alerts','travel-outdoor','agriculture','protection','rural-operations');$pages=array();foreach($required as $slug){$pages[$slug]=get_page_by_path($slug)?'Ready':'Missing';}
        $checks=array('GeneratePress parent'=>wp_get_theme('generatepress')->exists()?'Installed':'Missing','GP Premium'=>defined('GP_PREMIUM_VERSION')?'Active':'Not detected','Theme version'=>defined('R9_STUDIO_VERSION')?R9_STUDIO_VERSION:wp_get_theme()->get('Version'),'Plugin version'=>R9LS_VERSION,'Migration version'=>$migration['version']??'Pending','Scheduler'=>$this->scheduler->health()['status']??'unknown','Alert Center'=>get_option('r9ls_canonical_alert_state',array())?'Initialized':'Awaiting refresh','Forecast pages'=>count(array_filter($pages,function($v){return $v==='Ready';})).'/'.count($pages).' ready');
        $this->key_values($checks);echo '<h2>Required pages</h2>';$this->key_values($pages);
        $this->cards(array('Menus'=>has_nav_menu('primary')?'Primary assigned':'Needs assignment','Widgets'=>is_active_sidebar('r9-live-sidebar')?'Operations sidebar active':'Fallback modules active','Shortcodes'=>'r9ls_product, r9ls_county_matrix, r9ls_public_product','REST routes'=>count($this->rest_route_inventory()).' registered definitions'));
        echo '<h2>Repair actions</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="r9ls_admin_repair">';wp_nonce_field('r9ls_admin_repair');echo '<button class="button button-primary">Repair migration and scheduler state</button></form>';
    }

    private function render_theme_health() {
        global $wp_version;$upload=wp_upload_dir();$memory=ini_get('memory_limit');$rest=function_exists('rest_get_server')?'Available':'Unavailable';$warnings=array();if(version_compare(PHP_VERSION,'8.0','<'))$warnings[]='PHP 8.0 or newer is recommended.';if(!wp_next_scheduled(R9LS_Scheduler::HOOK))$warnings[]='The validation cron event is not scheduled.';if(!is_writable(WP_CONTENT_DIR))$warnings[]='WordPress content directory is not writable.';
        $this->key_values(array('PHP'=>PHP_VERSION,'WordPress'=>$wp_version?:get_bloginfo('version'),'Memory'=>$memory,'Cron'=>wp_next_scheduled(R9LS_Scheduler::HOOK)?'Scheduled':'Unavailable','REST'=>$rest,'Filesystem'=>is_writable(WP_CONTENT_DIR)?'Writable':'Read only','Uploads'=>empty($upload['error'])?'Available':$upload['error'],'Plugin compatibility'=>class_exists('R9LS_Plugin')?'Compatible':'Unavailable','Theme compatibility'=>function_exists('r9ls_theme_integration_status')?r9ls_theme_integration_status()['status']:'Unavailable'));
        echo '<h2>Warnings and errors</h2>';if(!$warnings&&!$this->audit->errors())echo '<p>No current platform warnings or plugin errors.</p>';else{echo '<ul>';foreach(array_merge($warnings,array_map(function($e){return $e['message']??'Unknown error';},$this->audit->errors())) as $warning)echo '<li>'.esc_html($warning).'</li>';echo '</ul>';}
        echo '<h2>Recovery suggestions</h2><ol><li>Run the repair action from Theme Site Setup.</li><li>Run manual validation after source health recovers.</li><li>Review and approve generated products before publication.</li><li>Export a backup before importing configuration.</li></ol>';
    }

    private function render_system_health() {
        $this->render_theme_health();echo '<h2>Operational services</h2>';$this->render_source_health();
    }

    private function render_backup() {
        $backup=get_option('r9ls_last_backup',array());$history=get_option(R9LS_Product_Generator::PUBLICATION_HISTORY,array());
        $this->cards(array('Last backup'=>$backup['created_at']??'Never','Restore points'=>count((array)$history),'Configuration backup'=>'Included','Workspace backup'=>'Included','Published products backup'=>'Included'));
        echo '<h2>Export</h2><p>Download a JSON backup of settings, live controls, workspace, canonical publications, and immutable histories.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="r9ls_backup_export">';wp_nonce_field('r9ls_backup_export');echo '<button class="button button-primary">Export Region 9 backup</button></form>';
        echo '<h2>Import</h2><p>Import validates the backup schema and updates only the approved Region 9 option allowlist.</p><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="r9ls_backup_import">';wp_nonce_field('r9ls_backup_import');echo '<input type="file" name="r9ls_backup" accept="application/json,.json" required> <button class="button">Import backup</button></form>';
        echo '<h2>Rollback shortcuts</h2><p><a class="button" href="'.esc_url(admin_url('admin.php?page=r9ls-publication-history')).'">Open publication restore points</a></p>';
    }

    private function render_backup_protection() {
        $this->render_backup();echo '<h2>Protection state</h2>';$this->key_values(array('Publication lock'=>!empty(get_option('r9ls_live_controls',array())['publication_lock'])?'Locked':'Unlocked','Automatic publication'=>empty(get_option(R9LS_Scheduler::SETTINGS,array())['automatic_publishing'])?'Disabled':'Enabled','Current errors'=>count($this->audit->errors()),'Migration protection'=>get_option(R9LS_Product_Generator::MIGRATION,array())?'Complete':'Pending'));
    }

    private function render_live_controls() {
        $controls=wp_parse_args(get_option('r9ls_live_controls',array()),array('emergency_mode'=>0,'banner'=>'','ticker'=>'','broadcast'=>'','homepage_hero'=>'operations','radar_mode'=>'standard','alert_override'=>'','maintenance_mode'=>0,'publication_lock'=>0,'override_expiration'=>''));
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('r9ls_live_controls');echo '<input type="hidden" name="action" value="r9ls_live_controls"><div class="r9ls-control-grid">';
        foreach(array('emergency_mode'=>'Emergency Mode','maintenance_mode'=>'Maintenance Mode','publication_lock'=>'Publication Lock') as $key=>$label){echo '<label><input type="checkbox" name="'.$key.'" value="1" '.checked(!empty($controls[$key]),true,false).'> <strong>'.$label.'</strong></label>';}
        foreach(array('banner'=>'Banner','ticker'=>'Ticker','broadcast'=>'Broadcast','alert_override'=>'Alert override') as $key=>$label){echo '<label><strong>'.$label.'</strong><textarea name="'.$key.'">'.esc_textarea($controls[$key]).'</textarea></label>';}
        echo '<label><strong>Homepage hero</strong><select name="homepage_hero"><option '.selected($controls['homepage_hero'],'operations',false).' value="operations">Operations</option><option '.selected($controls['homepage_hero'],'alerts',false).' value="alerts">Alerts</option><option '.selected($controls['homepage_hero'],'radar',false).' value="radar">Radar</option></select></label>';
        echo '<label><strong>Radar mode</strong><select name="radar_mode"><option '.selected($controls['radar_mode'],'standard',false).' value="standard">Standard</option><option '.selected($controls['radar_mode'],'severe',false).' value="severe">Severe operations</option><option '.selected($controls['radar_mode'],'fallback',false).' value="fallback">Fallback</option></select></label>';
        echo '<label><strong>Override expiration</strong><input type="datetime-local" name="override_expiration" value="'.esc_attr($controls['override_expiration']).'"></label></div>';submit_button('Save live controls');echo '</form>';
    }

    private function render_operations_page($kind) {
        $config=array(
            'partners'=>array('Partner readiness','Coordinate public agencies, media, utilities, and weather partners.',array('Active alerts'=>'r9ls-alert-center','Source health'=>'r9ls-source-health','Protection'=>'r9-studio-protection')),
            'clients'=>array('Client operations','Review published decision support products and operational delivery status.',array('Forecast products'=>'r9ls-forecast-products','Production'=>'r9-studio-production','Publication history'=>'r9ls-publication-history')),
            'production'=>array('Production control','Monitor validation, workspace review, approval, and publication.',array('Workspace'=>'r9ls-forecast-production','Decision Dashboard'=>'r9ls-decision-dashboard','Automation'=>'r9ls-automation')),
            'rural-operations'=>array('Rural operations','Coordinate fieldwork, spraying, harvest, livestock, and rural travel guidance.',array('Products'=>'r9ls-forecast-products','Rural reports'=>'r9-studio-rural-reports','Source health'=>'r9ls-source-health')),
            'rural-reports'=>array('Rural reports','Review canonical rural products and publication readiness without exposing private drafts.',array('Approval queue'=>'r9ls-approval-queue','Rural operations'=>'r9-studio-rural-operations','Publication history'=>'r9ls-publication-history')),
            'protection'=>array('Protection operations','Coordinate alert, preparedness, continuity, and emergency messaging.',array('Alert Center'=>'r9ls-alert-center','Live Controls'=>'r9-studio-live-controls','Backup & Protection'=>'r9ls-backup-protection')),
        );$item=$config[$kind];
        $workspace=$this->generator()->workspace();$this->cards(array($item[0]=>'Operational','Workspace products'=>count($workspace['products']),'Published products'=>count((array)get_option(R9LS_Product_Generator::PRODUCTS,array())),'Current alerts'=>count((array)get_option('r9ls_canonical_alert_state',array())['alerts']??array())));echo '<p>'.esc_html($item[1]).'</p><div class="r9ls-admin-links">';foreach($item[2] as $label=>$slug)echo '<a href="'.esc_url(admin_url('admin.php?page='.$slug)).'">'.esc_html($label).'</a>';echo '</div>';
    }

    public function validate() { $this->guard('r9ls_validate'); $result=$this->scheduler->manual_validate(); $this->redirect(($result['status']??'error')==='ok'?'validation_complete':'validation_failed'); }
    public function settings() { $this->guard('r9ls_settings'); $clean=array(); foreach($this->settings_fields() as $key=>$field){$raw=wp_unslash($_POST[$key]??$field['default']);if($field['type']==='number'){$clean[$key]=max((int)($field['min']??0),absint($raw));}elseif(!empty($field['csv'])){$clean[$key]=array_values(array_filter(array_map('sanitize_key',array_map('trim',explode(',',(string)$raw)))));}elseif($key==='nws_contact_email'){$clean[$key]=sanitize_email($raw);}else{$clean[$key]=sanitize_text_field($raw);}}$clean['automatic_publishing']=!empty($_POST['automatic_publishing'])?1:0;update_option(R9LS_Scheduler::SETTINGS,$clean,false);$this->redirect('settings_saved'); }
    public function change_action() { $this->guard('r9ls_change'); $id=sanitize_text_field(wp_unslash($_POST['change_id']??''));$do=sanitize_key($_POST['do']??'');if($do==='approve'||$do==='reject'){$this->changes->decide($id,$do,sanitize_text_field(wp_unslash($_POST['reason']??'')));}elseif($do==='publish'){$this->changes->publish($id);}elseif($do==='rollback'){$this->changes->rollback($id);}$this->redirect('change_updated'); }

    public function product_action() {
        $this->guard('r9ls_product_action');
        $id=sanitize_key(wp_unslash($_POST['product_id']??''));$do=sanitize_key(wp_unslash($_POST['do']??''));$actor=$this->current_actor();
        if($do==='approve'){$result=$this->generator()->approve($id,$actor);}elseif($do==='reject'){$result=$this->generator()->reject($id,sanitize_text_field(wp_unslash($_POST['rejection_reason']??'')),$actor);}elseif($do==='publish'){$result=$this->generator()->publish($id,$actor);}elseif($do==='rollback'){$result=$this->generator()->rollback($id,sanitize_text_field(wp_unslash($_POST['publication_reference']??'')),$actor);}else{$result=array('success'=>false,'code'=>'invalid_action');}
        $this->redirect($result['code']??'action_failed','r9ls-forecast-production');
    }

    public function product_batch() {
        $this->guard('r9ls_product_batch');
        $action=sanitize_key(wp_unslash($_POST['batch_action']??''));$ids=array_map('sanitize_key',(array)wp_unslash($_POST['product_ids']??array()));
        $results=$this->generator()->batch($action,$ids,$this->current_actor(),sanitize_text_field(wp_unslash($_POST['rejection_reason']??'')));
        $success=count(array_filter($results,function($result){return !empty($result['success']);}));
        $this->redirect($success?'batch_complete':'batch_no_actions','r9ls-forecast-production');
    }

    public function override() { $this->guard('r9ls_override');$overrides=get_option('r9ls_editor_overrides',array());$overrides[md5(time().wp_rand())]=array('summary'=>sanitize_text_field(wp_unslash($_POST['summary']??'')),'expires'=>sanitize_text_field(wp_unslash($_POST['expires']??'')));update_option('r9ls_editor_overrides',$overrides,false);$this->redirect('override_saved'); }

    public function save_live_controls() {
        $this->guard('r9ls_live_controls');$controls=array();
        foreach(array('emergency_mode','maintenance_mode','publication_lock') as $key){$controls[$key]=!empty($_POST[$key])?1:0;}
        foreach(array('banner','ticker','broadcast','alert_override') as $key){$controls[$key]=sanitize_textarea_field(wp_unslash($_POST[$key]??''));}
        $controls['homepage_hero']=in_array(sanitize_key($_POST['homepage_hero']??''),array('operations','alerts','radar'),true)?sanitize_key($_POST['homepage_hero']):'operations';
        $controls['radar_mode']=in_array(sanitize_key($_POST['radar_mode']??''),array('standard','severe','fallback'),true)?sanitize_key($_POST['radar_mode']):'standard';
        $controls['override_expiration']=sanitize_text_field(wp_unslash($_POST['override_expiration']??''));$controls['updated_at']=current_time('mysql');$controls['actor']=$this->current_actor();
        update_option('r9ls_live_controls',$controls,false);$this->audit->write('info','Live controls updated.',array('event'=>'live_controls_updated','actor'=>$controls['actor']));$this->redirect('live_controls_saved','r9-studio-live-controls');
    }

    public function repair() {
        $this->guard('r9ls_admin_repair');R9LS_Product_Generator::migrate_17_1($this->audit);$this->scheduler->ensure_defaults();$this->scheduler->schedule_event();$this->audit->write('info','Administrative repair completed.',array('event'=>'admin_repair_completed','actor'=>$this->current_actor()));$this->redirect('repair_complete','r9-studio-setup');
    }

    public function backup_export() {
        $this->guard('r9ls_backup_export');$data=array('schema'=>'region9-live-studio-backup','version'=>R9LS_Product_Generator::VERSION,'created_at'=>current_time('mysql'),'options'=>array());foreach($this->backup_options() as $option){$data['options'][$option]=get_option($option,array());}update_option('r9ls_last_backup',array('created_at'=>$data['created_at'],'actor'=>$this->current_actor(),'type'=>'export'),false);$this->audit->write('info','Configuration backup exported.',array('event'=>'backup_exported','actor'=>$this->current_actor()));nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename=region9-live-studio-backup-'.gmdate('Ymd-His').'.json');echo wp_json_encode($data,JSON_PRETTY_PRINT);exit;
    }

    public function backup_import() {
        $this->guard('r9ls_backup_import');$file=$_FILES['r9ls_backup']['tmp_name']??'';$size=absint($_FILES['r9ls_backup']['size']??0);if(!$file||$size<1||$size>5*1024*1024){$this->redirect('invalid_backup','r9-studio-backup');}$data=json_decode(file_get_contents($file),true);if(!is_array($data)||($data['schema']??'')!=='region9-live-studio-backup'||!is_array($data['options']??null)){$this->redirect('invalid_backup','r9-studio-backup');}foreach($this->backup_options() as $option){if(array_key_exists($option,$data['options']))update_option($option,$data['options'][$option],false);}R9LS_Product_Generator::migrate_17_1($this->audit);update_option('r9ls_last_backup',array('created_at'=>current_time('mysql'),'actor'=>$this->current_actor(),'type'=>'import'),false);$this->audit->write('info','Configuration backup imported.',array('event'=>'backup_imported','actor'=>$this->current_actor()));$this->redirect('backup_imported','r9-studio-backup');
    }

    private function render_change_queue() { $queue=$this->changes->queue();echo '<h2>Grouped material-change queue</h2><table class="widefat striped"><thead><tr><th>Product</th><th>Field</th><th>Reason</th><th>Counties</th><th>Detected</th></tr></thead><tbody>';foreach($queue as $row)echo '<tr><th>'.esc_html($row['product']??'').'</th><td>'.esc_html($row['field']??'').'</td><td>'.esc_html($row['reason']??'').'</td><td>'.esc_html(implode(', ',(array)($row['affected_counties']??array()))).'</td><td>'.esc_html($row['timestamp']??'').'</td></tr>';if(!$queue)echo '<tr><td colspan="5">No pending material changes.</td></tr>';echo '</tbody></table>'; }
    private function theme_integration_card() { $status=function_exists('r9ls_theme_integration_status')?r9ls_theme_integration_status():array('status'=>'unavailable','message'=>'Theme helper unavailable.');echo '<h2>Theme integration status</h2><div class="r9ls-status-card">'.$this->health_badge($status['status']??'unavailable').'<p>'.esc_html($status['message']??'').'</p></div>'; }
    private function cards($items) { echo '<div class="r9ls-status-grid">';foreach($items as $label=>$value)echo '<section class="r9ls-status-card"><span>'.esc_html($label).'</span><strong>'.esc_html((string)$value).'</strong></section>';echo '</div>'; }
    private function key_values($items) { echo '<table class="widefat striped r9ls-key-values"><tbody>';foreach($items as $label=>$value)echo '<tr><th>'.esc_html($label).'</th><td>'.esc_html((string)$value).'</td></tr>';echo '</tbody></table>'; }
    private function health_badge($status) { $status=sanitize_key($status?:'unknown');$good=in_array($status,array('healthy','ok','active','scheduled','complete'),true);return '<span class="r9ls-health-badge '.($good?'is-good':'is-warning').'">'.esc_html(ucfirst(str_replace('_',' ',$status))).'</span>'; }
    private function audit_table($entries) { echo '<table class="widefat striped"><thead><tr><th>Time</th><th>Level</th><th>Event</th><th>Message</th></tr></thead><tbody>';foreach((array)$entries as $entry)echo '<tr><td>'.esc_html($entry['time']??'').'</td><td>'.esc_html($entry['level']??'').'</td><td>'.esc_html($entry['context']['event']??'').'</td><td>'.esc_html($entry['message']??'').'</td></tr>';if(!$entries)echo '<tr><td colspan="4">No failures recorded.</td></tr>';echo '</tbody></table>'; }
    private function rest_route_inventory() { return array('/products','/product/{id}','/todays-forecast','/seven-day-forecast','/travel','/agriculture','/county-product-matrix','/product-history','/alerts'); }
    private function backup_options() { return array(R9LS_Scheduler::SETTINGS,R9LS_Product_Generator::WORKSPACE,R9LS_Product_Generator::GENERATION_LAST,R9LS_Product_Generator::PRODUCTS,R9LS_Product_Generator::HISTORY,R9LS_Product_Generator::DECISION_HISTORY,R9LS_Product_Generator::PUBLICATION_HISTORY,R9LS_Product_Generator::LATEST_PUBLICATION,'r9ls_live_controls','r9ls_editor_overrides','r9ls_canonical_alert_state'); }
    private function admin_styles() { echo '<style>.r9ls-admin .r9ls-status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:16px 0}.r9ls-status-card{background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;border-radius:6px;padding:14px}.r9ls-status-card span{display:block;color:#50575e;font-size:12px;text-transform:uppercase}.r9ls-status-card strong{display:block;font-size:18px;margin-top:6px}.r9ls-health-badge{display:inline-block;border-radius:999px;padding:3px 9px;font-weight:700;background:#fff3cd;color:#7a4b00}.r9ls-health-badge.is-good{background:#d7f0df;color:#0a5c2d}.r9ls-admin-links{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0}.r9ls-admin-links a{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:8px 12px;text-decoration:none}.r9ls-control-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.r9ls-control-grid label{background:#fff;border:1px solid #dcdcde;padding:14px}.r9ls-control-grid textarea,.r9ls-control-grid input,.r9ls-control-grid select{display:block;width:100%;margin-top:8px}.r9ls-product-preview{background:#f6f7f7;border-left:3px solid #2271b1;padding:10px;margin:8px 0}.r9ls-timeline{border-left:3px solid #2271b1;padding-left:24px}.r9ls-admin progress{width:100%;height:22px}@media(max-width:782px){.r9ls-admin .widefat{display:block;overflow-x:auto}.r9ls-admin .r9ls-status-grid,.r9ls-control-grid{grid-template-columns:1fr}}</style>'; }

    private function product_buttons($id,$row) { $buttons='';foreach(array('approve'=>'Approve','reject'=>'Reject','publish'=>'Publish') as $action=>$label){$buttons.='<form style="display:inline" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="r9ls_product_action"><input type="hidden" name="product_id" value="'.esc_attr($id).'"><input type="hidden" name="do" value="'.$action.'">'.wp_nonce_field('r9ls_product_action','_wpnonce',true,false);if($action==='reject'){$buttons.='<input class="small-text" name="rejection_reason" aria-label="Rejection reason" placeholder="Reason">';}$buttons.='<button class="button">'.$label.'</button></form> ';}return $buttons; }
    private function rollback_button($event) { if(empty($event['publication_reference'])||empty($event['product_id']))return '';return '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="r9ls_product_action"><input type="hidden" name="do" value="rollback"><input type="hidden" name="product_id" value="'.esc_attr($event['product_id']).'"><input type="hidden" name="publication_reference" value="'.esc_attr($event['publication_reference']).'">'.wp_nonce_field('r9ls_product_action','_wpnonce',true,false).'<button class="button">Rollback</button></form>'; }
    private function grouped_changes($changes) { if(!$changes)return '<p><strong>Grouped changes:</strong> None.</p>';$html='<ul>';foreach($changes as $change){$html.='<li><strong>'.esc_html($change['field']??'change').':</strong> '.esc_html($change['reason']??'').'</li>'; }return '<p><strong>Grouped changes:</strong></p>'.$html.'</ul>'; }
    private function generator() { if(!$this->products){$plugin=R9LS_Plugin::instance();$this->products=$plugin->products;}return $this->products; }
    private function duration_label($workspace=array()) { $value=$workspace['generation_duration']??$workspace['duration']??null;if($value===null){$last=get_option(R9LS_Product_Generator::GENERATION_LAST,array());$value=$last['generation_duration']??$last['duration']??null;}if($value===null){$validation=get_option(R9LS_Scheduler::LAST,array());$value=isset($validation['changes'])?$validation['duration']??null:null;}return $value===null?'N/A':number_format((float)$value,3).'s'; }
    private function notice() { if(empty($_GET['r9ls_notice']))return;echo '<div class="notice notice-info is-dismissible"><p>'.esc_html(ucwords(str_replace('_',' ',sanitize_key($_GET['r9ls_notice'])))).'.</p></div>'; }
    private function redirect($notice,$page='r9ls') { wp_safe_redirect(add_query_arg(array('page'=>$page,'r9ls_notice'=>sanitize_key($notice)),admin_url('admin.php')));exit; }
    private function guard($nonce) { if(!current_user_can('manage_options'))wp_die('Administrator capability required.');check_admin_referer($nonce); }
    private function current_actor() { $user=wp_get_current_user();return $user&&$user->exists()?$user->user_login:'system'; }
    private function next_validation_label() { $next=$this->scheduler->next_validation();return $next?date_i18n('Y-m-d H:i:s',$next):'Scheduling unavailable'; }
    private function status_badge($label,$score=0) { return '<span class="r9ls-admin-badge r9ls-admin-badge-'.esc_attr($this->score_class($score)).'">'.esc_html($label).'</span>'; }
    private function score_class($score) { $score=(int)$score;if($score>=75)return'high-risk';if($score>=50)return'limited';if($score>=25)return'low';return'good'; }
    private function current_risk($products) { return $products['Severe Weather Risk']['rating']??'None'; }
    private function publication_version() { $latest=get_option(R9LS_Product_Generator::LATEST_PUBLICATION,array());return $latest['publication_reference']??'None'; }
    private function settings_fields() { return array('nws_contact_email'=>array('label'=>'NWS contact email','type'=>'email','default'=>'weather@region9weather.com'),'normal_validation_interval_minutes'=>array('label'=>'Normal validation interval minutes','type'=>'number','default'=>180,'min'=>15),'active_interval_minutes'=>array('label'=>'Active-weather interval minutes','type'=>'number','default'=>60,'min'=>15),'source_timeout_seconds'=>array('label'=>'Source timeout seconds','type'=>'number','default'=>10,'min'=>1),'cache_duration_minutes'=>array('label'=>'Cache duration minutes','type'=>'number','default'=>5,'min'=>1),'stale_data_threshold_minutes'=>array('label'=>'Stale-data threshold minutes','type'=>'number','default'=>720,'min'=>30),'confidence_threshold'=>array('label'=>'Confidence threshold','type'=>'number','default'=>60,'min'=>0),'material_change_threshold'=>array('label'=>'Material-change threshold','type'=>'number','default'=>10,'min'=>1),'required_healthy_sources'=>array('label'=>'Required healthy sources CSV','type'=>'text','default'=>'nws_alerts,spc_day1,wpc_day1_ero,wpc_day1_qpf','csv'=>true),'enabled_products'=>array('label'=>'Enabled products CSV','type'=>'text','default'=>implode(',',array_keys(R9LS_Product_Generator::product_definitions())),'csv'=>true),'fallback_language'=>array('label'=>'Fallback language','type'=>'text','default'=>'Region 9 Live Studio publication is temporarily unavailable.'),'public_display_options'=>array('label'=>'Public display options CSV','type'=>'text','default'=>'show_scores,show_counties','csv'=>true),'stale_banner_behavior'=>array('label'=>'Stale-data banner behavior','type'=>'text','default'=>'label')); }
}
