<?php
defined('ABSPATH') || exit;

class R9LS_REST_API {
    private $publication; private $scheduler; private $changes;
    public function __construct($publication, $scheduler, $changes) { $this->publication=$publication; $this->scheduler=$scheduler; $this->changes=$changes; }
    public function hooks() { add_action('rest_api_init', array($this, 'routes')); add_shortcode('region9_live_studio', array($this, 'shortcode')); }
    public function routes() {
        register_rest_route('region9-live-studio/v1', '/publication', array('methods'=>'GET','callback'=>array($this,'publication'),'permission_callback'=>'__return_true'));
        register_rest_route('region9-live-studio/v1', '/products', array('methods'=>'GET','callback'=>array($this,'products'),'permission_callback'=>'__return_true'));
        register_rest_route('region9-live-studio/v1', '/products/(?P<product>[A-Za-z0-9%20%_-]+)', array('methods'=>'GET','callback'=>array($this,'product'),'permission_callback'=>'__return_true'));
        register_rest_route('region9-live-studio/v1', '/publish', array('methods'=>'POST','callback'=>array($this,'publish'),'permission_callback'=>array($this,'admin')));
        register_rest_route('region9-live-studio/v1', '/rollback', array('methods'=>'POST','callback'=>array($this,'rollback'),'permission_callback'=>array($this,'admin')));
        register_rest_route('region9-live-studio/v1', '/overrides', array('methods'=>'POST','callback'=>array($this,'override'),'permission_callback'=>array($this,'admin')));
    }
    public function publication() { return rest_ensure_response($this->publication->public_state()); }
    public function products() { $s = $this->publication->public_state(); return rest_ensure_response($s['products']); }
    public function product($request) { $p = rawurldecode($request['product']); $products = $this->publication->public_state()['products']; $product = $products[$p] ?? null; return $product ? rest_ensure_response($product) : new WP_Error('r9ls_not_found','Product not found.',array('status'=>404)); }
    public function publish($request) { $id = sanitize_text_field($request['change_id'] ?? ''); if (!$id && !empty($this->changes->queue())) { return new WP_Error('r9ls_pending_changes', 'Pending changes must be approved before publishing.', array('status' => 409)); } if ($id && !$this->changes->approved($id)) { return new WP_Error('r9ls_approval_required', 'Manual approval is required before publishing.', array('status' => 403)); } if ($id) { $this->changes->publish($id); } return rest_ensure_response($this->publication->publish_products(get_option(R9LS_Scheduler::CACHE, array()), $id, get_current_user_id())); }
    public function rollback($request) { $state=$this->publication->rollback(absint($request['version'] ?? 0), get_current_user_id()); return $state ? rest_ensure_response($state) : new WP_Error('r9ls_rollback_missing','Rollback version not found.',array('status'=>404)); }
    public function override($request) { $id=$this->publication->save_override($request['product'] ?? '', $request['summary'] ?? '', $request['expires'] ?? '', get_current_user_id()); return rest_ensure_response(array('id'=>$id)); }
    public function admin() { return current_user_can('manage_options'); }
    public function shortcode($atts) { $atts = shortcode_atts(array('product' => 'Severe Weather Risk'), $atts); $p = $this->publication->product($atts['product']); if (!$p) { return ''; } return '<div class="r9ls-public-card" data-r9ls-product="' . esc_attr($atts['product']) . '"><strong>' . esc_html($atts['product'] . ': ' . ($p['rating'] ?? '')) . '</strong><p>' . esc_html($p['controlled_summary'] ?? '') . '</p></div>'; }
}
