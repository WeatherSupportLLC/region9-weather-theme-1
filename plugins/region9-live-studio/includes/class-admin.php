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
    }

    public function menu() {
        add_menu_page('Region 9 Studio Automation', 'Region 9 Studio', 'manage_options', 'r9ls', array($this, 'page'), 'dashicons-cloud', 58);
        $pages = array(
            array('Forecast Production','Forecast Production','r9ls-forecast-production'), array('Forecast Products','Forecast Products','r9ls-forecast-products'),
            array('Pending Changes / Approval Queue','Approval Queue','r9ls-approval-queue'), array('Decision History','Decision History','r9ls-decision-history'),
            array('Publication History','Publication History','r9ls-publication-history'), array('Audit Log','Audit Log','r9ls-audit-log'),
            array('Alert Center','Alert Center','r9ls-alert-center'), array('Source Health','Source Health','r9ls-source-health'),
            array('Scheduler Health','Scheduler Health','r9ls-scheduler-health'), array('Temporary Overrides','Temporary Overrides','r9ls-overrides'),
            array('System Health','System Health','r9ls-system-health'), array('Backup & Protection','Backup & Protection','r9ls-backup-protection'),
        );
        foreach ($pages as $page) { add_submenu_page('r9ls', $page[0], $page[1], 'manage_options', $page[2], array($this, 'page')); }
    }

    public function page() {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('Administrator access required.', 'r9ls')); }
        $page = sanitize_key($_GET['page'] ?? 'r9ls');
        echo '<div class="wrap"><h1>Region 9 Studio Automation</h1>';
        $this->notice();
        if ($page === 'r9ls-forecast-production') { $this->render_workspace(); }
        elseif ($page === 'r9ls-decision-history') { $this->render_decision_history(); }
        elseif ($page === 'r9ls-publication-history') { $this->render_publication_history(); }
        elseif ($page === 'r9ls-audit-log') { $this->render_audit(); }
        else { $this->render_dashboard(); }
        echo '</div>';
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
            echo '<th>' . esc_html($row['title'] ?? $id) . '<details><summary>Details</summary><p><strong>Discussion:</strong> ' . esc_html($row['discussion'] ?? '') . '</p><p><strong>Content hash:</strong> <code>' . esc_html($row['content_hash'] ?? '') . '</code></p><p><strong>Previous version:</strong> ' . esc_html((string)($row['previous_version'] ?? 0)) . '</p><p><strong>Source reference:</strong> ' . esc_html($row['source_version_reference'] ?? '') . '</p><p><strong>Publication reference:</strong> ' . esc_html($row['publication_reference'] ?? '') . '</p>' . $this->grouped_changes($row['grouped_changes'] ?? array()) . '</details></th>';
            foreach (array($row['product_version']??'', $row['workspace_state']??'', $row['approval_state']??'', $row['publication_state']??'') as $value) { echo '<td>' . esc_html((string)$value) . '</td>'; }
            echo '<td>' . $this->status_badge($row['risk_label'] ?? ($row['risk']['label'] ?? 'None'), $row['score'] ?? 0) . '</td><td>' . esc_html((string)($row['confidence'] ?? 0)) . '%</td><td>' . esc_html(implode(', ', (array)($row['affected_counties'] ?? array()))) . '</td><td>' . esc_html($timing) . '</td><td>' . esc_html($row['generated_at'] ?? '') . '</td><td>' . esc_html(number_format((float)($row['generation_duration'] ?? 0), 4)) . 's</td><td>' . esc_html((string)($row['grouped_change_count'] ?? 0)) . '</td><td>' . esc_html($row['summary'] ?? '') . '</td><td>' . $this->product_buttons($id, $row) . '</td></tr>';
        }
        echo '</tbody></table></form>';
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
