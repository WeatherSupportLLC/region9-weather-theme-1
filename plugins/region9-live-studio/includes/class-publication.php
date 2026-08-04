<?php
defined('ABSPATH') || exit;

class R9LS_Publication {
    const STATE = 'r9ls_publication_state_v1';
    const HISTORY = 'r9ls_publication_history_v1';
    const VERSION = 1;
    const CACHE_GROUP = 'r9ls_public';
    private $audit;
    public function __construct($audit) { $this->audit = $audit; }
    public function current() {
        $state = get_option(self::STATE, array());
        if (empty($state['schema_version'])) { $state = $this->initial_state(); update_option(self::STATE, $state, false); }
        return $this->with_active_overrides($state);
    }
    public function public_state() {
        $state = $this->current();
        return array(
            'schema_version' => $state['schema_version'],
            'publication_version' => $state['publication_version'],
            'published_at' => $state['published_at'],
            'products' => $this->public_products($state['products']),
        );
    }
    public function raw_current() { $state = get_option(self::STATE, array()); return empty($state['schema_version']) ? $this->initial_state() : $state; }
    public function publish_products($products, $change_id = '', $actor = 0) {
        $current = $this->raw_current(); $hash = $this->hash($products);
        if (!empty($current['content_hash']) && hash_equals($current['content_hash'], $hash)) { $this->audit->write('warning', 'Duplicate publish prevented.', array('change_id'=>$change_id)); return array('status'=>'duplicate', 'state'=>$current); }
        $version = absint($current['publication_version'] ?? 0) + 1;
        $state = array('schema_version'=>self::VERSION,'publication_version'=>$version,'content_hash'=>$hash,'published_at'=>current_time('mysql'),'published_by'=>absint($actor),'source_change_id'=>sanitize_text_field($change_id),'products'=>$this->sanitize_products($products),'overrides'=>array(),'rolled_back_from'=>0);
        update_option(self::STATE, $state, false); $this->append_history('publish', $state, $current); $this->invalidate($this->changed_products($current['products'] ?? array(), $state['products'])); $this->audit->write('info', 'Publication state updated.', array('version'=>$version,'change_id'=>$change_id)); return array('status'=>'published','state'=>$state);
    }
    public function rollback($target_version, $actor = 0) {
        $history = $this->history(); $current = $this->raw_current();
        foreach ($history as $entry) { if (absint($entry['state']['publication_version'] ?? 0) === absint($target_version)) { $state = $entry['state']; $state['publication_version'] = absint($current['publication_version'] ?? 0) + 1; $state['published_at'] = current_time('mysql'); $state['published_by'] = absint($actor); $state['rolled_back_from'] = absint($target_version); update_option(self::STATE, $state, false); $this->append_history('rollback', $state, $current); $this->invalidate($this->changed_products($current['products'] ?? array(), $state['products'])); $this->audit->write('warning', 'Publication rolled back.', array('target_version'=>$target_version)); return $state; } }
        return false;
    }
    public function save_override($product, $summary, $expires, $actor = 0) { $state = $this->raw_current(); $id = md5($product.$summary.$expires.time().wp_rand()); $state['overrides'][$id] = array('id'=>$id,'product'=>sanitize_text_field($product),'summary'=>sanitize_text_field($summary),'expires'=>sanitize_text_field($expires),'created_at'=>current_time('mysql'),'created_by'=>absint($actor)); update_option(self::STATE, $state, false); $this->append_history('override', $state); $this->invalidate(array(sanitize_text_field($product))); return $id; }
    public function expire_overrides() { $state = $this->raw_current(); $changed = false; foreach (($state['overrides'] ?? array()) as $id=>$o) { if (!empty($o['expires']) && strtotime($o['expires']) <= time()) { unset($state['overrides'][$id]); $changed = true; } } if ($changed) { update_option(self::STATE, $state, false); $this->append_history('expire_overrides', $state); $this->invalidate(); } return $state['overrides'] ?? array(); }
    public function history() { return get_option(self::HISTORY, array()); }
    public function product($product) { $state = $this->current(); return $state['products'][$product] ?? null; }
    public function invalidate($products = array()) { foreach ((array) $products as $product) { wp_cache_delete('product_' . sanitize_key($product), self::CACHE_GROUP); } wp_cache_delete('state', self::CACHE_GROUP); do_action('r9ls_publication_cache_invalidated', $products); }
    private function public_products($products) { $out = array(); foreach ((array) $products as $name => $product) { $out[$name] = array_intersect_key((array) $product, array_flip(array('score','rating','confidence','primary_drivers','secondary_drivers','affected_counties','timing','controlled_summary','county_scores','region9_score_logic'))); } return $out; }
    private function changed_products($previous, $current) { $changed = array(); $names = array_unique(array_merge(array_keys((array) $previous), array_keys((array) $current))); foreach ($names as $name) { if (wp_json_encode($previous[$name] ?? null) !== wp_json_encode($current[$name] ?? null)) { $changed[] = $name; } } return $changed; }
    private function with_active_overrides($state) { $now = time(); foreach (($state['overrides'] ?? array()) as $o) { if (!empty($o['expires']) && strtotime($o['expires']) <= $now) { continue; } $p = $o['product']; if (isset($state['products'][$p])) { $state['products'][$p]['controlled_summary'] = $o['summary']; $state['products'][$p]['override_active'] = true; $state['products'][$p]['override_expires'] = $o['expires']; } } return $state; }
    private function initial_state() { return array('schema_version'=>self::VERSION,'publication_version'=>0,'content_hash'=>'','published_at'=>'','published_by'=>0,'source_change_id'=>'','products'=>array(),'overrides'=>array(),'rolled_back_from'=>0); }
    private function hash($products) { return hash('sha256', wp_json_encode($this->sanitize_products($products))); }
    private function sanitize_products($products) { return json_decode(wp_json_encode($products), true) ?: array(); }
    private function append_history($event, $state, $previous = array()) { $history = $this->history(); array_unshift($history, array('event'=>sanitize_key($event),'time'=>current_time('mysql'),'state'=>$state,'previous_version'=>absint($previous['publication_version'] ?? 0))); update_option(self::HISTORY, $history, false); }
}
