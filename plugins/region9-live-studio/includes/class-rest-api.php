<?php
defined('ABSPATH') || exit;

class R9LS_REST_API {
    const NS = 'region9-live-studio/v1';
    private $generator;
    public function __construct($generator) { $this->generator = $generator; }
    public function hooks() { add_action('rest_api_init', array($this, 'routes')); add_shortcode('r9ls_product', array($this, 'shortcode')); add_shortcode('r9ls_county_matrix', array($this, 'county_shortcode')); }
    public function routes() {
        $map = array('products'=>'all','product/(?P<id>[a-z0-9\-]+)'=>'one','todays-forecast'=>'todays-forecast','seven-day-forecast'=>'seven-day-forecast','travel'=>'travel','agriculture'=>'agriculture','fieldwork'=>'fieldwork','spraying'=>'spraying','harvest'=>'harvest','livestock'=>'livestock','outdoor'=>'outdoor','schools'=>'schools','construction'=>'construction','severe-weather-risk'=>'severe-weather-risk','threat-breakdown'=>'threat-breakdown','storm-timing'=>'storm-timing','forecast-confidence'=>'forecast-confidence','headlines'=>'headlines','morning-brief'=>'morning-brief','decision-support-brief'=>'decision-support-brief','watching'=>'watching','county-product-matrix'=>'matrix','product-history'=>'history');
        foreach ($map as $route => $kind) { register_rest_route(self::NS, '/' . $route, array('methods'=>'GET','callback'=>function($req) use ($kind) { return $this->read($kind, $req); }, 'permission_callback'=>'__return_true')); }
    }
    public function read($kind, $req = null) {
        if ($kind === 'history') { return $this->history(); }
        $products = $this->published();
        if ($kind === 'all') { return $products; }
        if ($kind === 'matrix') { return $this->matrix($products); }
        $id = $kind === 'one' && $req ? sanitize_key($req['id']) : $kind;
        return $products[$id] ?? new WP_Error('r9ls_not_found', 'Published product not found.', array('status'=>404));
    }
    private function published() { $cached = get_transient(R9LS_Product_Generator::CACHE_PREFIX . 'all'); if ($cached !== false) { return $cached; } $all = get_option(R9LS_Product_Generator::PRODUCTS, array()); $out = array(); foreach ($all as $id => $p) { if (($p['approval_state'] ?? '') === 'approved' && ($p['publication_state'] ?? '') === 'published') { $clean = $p; unset($clean['rule_trace'], $clean['override_internals'], $clean['audit_log']); $out[$id] = $clean; } } set_transient(R9LS_Product_Generator::CACHE_PREFIX . 'all', $out, 5 * MINUTE_IN_SECONDS); return $out; }
    private function matrix($products) { $out = array(); foreach ($products as $id => $p) { $out[$id] = $p['county_matrix'] ?? array(); } return $out; }
    private function history() { $h = get_option(R9LS_Product_Generator::HISTORY, array()); return array_map(function($i){ return array('product_id'=>$i['product_id'],'product_version'=>$i['product_version'],'publication_version'=>$i['publication_version'],'generated_timestamp'=>$i['generated_timestamp'],'published_timestamp'=>$i['published_timestamp'],'actor'=>$i['actor'],'approval_reference'=>$i['approval_reference'],'material_change_reason'=>$i['material_change_reason'],'previous_version'=>$i['previous_version'],'rollback_reference'=>$i['rollback_reference'],'content_hash'=>$i['content_hash']); }, $h); }
    public function shortcode($atts) { $atts = shortcode_atts(array('id'=>'todays-forecast','field'=>'summary'), $atts); $p = $this->read(sanitize_key($atts['id'])); if (is_wp_error($p)) { return ''; } $field = sanitize_key($atts['field']); return '<div class="r9ls-product r9ls-product-' . esc_attr($p['product_id']) . '">' . esc_html($p[$field] ?? $p['summary']) . '</div>'; }
    public function county_shortcode() { return '<script type="application/json" class="r9ls-county-product-matrix">' . esc_html(wp_json_encode($this->read('matrix'))) . '</script>'; }
}

function r9ls_get_published_products() { return get_option(R9LS_Product_Generator::PRODUCTS, array()); }
function r9ls_get_published_product($product_id) { $all = r9ls_get_published_products(); $id = sanitize_key($product_id); return $all[$id] ?? null; }
function r9ls_get_widget_payload($product_id = 'todays-forecast') { $p = r9ls_get_published_product($product_id); return $p && ($p['publication_state'] ?? '') === 'published' ? array('product_id'=>$p['product_id'],'title'=>$p['title'],'summary'=>$p['summary'],'risk'=>$p['risk'],'updated_at'=>$p['updated_at']) : array(); }
