<?php
defined('ABSPATH') || exit;

class R9LS_Material_Change_Engine {
    const QUEUE = 'r9ls_pending_changes';
    const HISTORY = 'r9ls_decision_history';
    private $audit;

    public function __construct($audit) { $this->audit = $audit; }

    public function detect($previous, $current) {
        $settings = get_option(R9LS_Scheduler::SETTINGS, array());
        $score_threshold = absint($settings['score_movement_threshold'] ?? 10);
        $confidence_threshold = absint($settings['confidence_threshold'] ?? 60);
        $queue = get_option(self::QUEUE, array());
        $new = array();
        foreach ($current as $product => $now) {
            $old = $previous[$product] ?? null;
            if (!$old) { continue; }
            $checks = array(
                array('score', $old['score'], $now['score'], abs($now['score'] - $old['score']) >= $score_threshold, 'score movement'),
                array('rating', $old['rating'], $now['rating'], $old['rating'] !== $now['rating'], 'product rating change'),
                array('counties', $old['affected_counties'], $now['affected_counties'], $this->array_changed($old['affected_counties'], $now['affected_counties']), 'counties added or removed'),
                array('primary_hazards', $old['primary_drivers'], $now['primary_drivers'], $this->array_changed($old['primary_drivers'], $now['primary_drivers']), 'primary hazard change'),
                array('timing', $old['timing'], $now['timing'], $old['timing'] !== $now['timing'], 'timing change beyond tolerance'),
                array('confidence', $old['confidence'], $now['confidence'], ($old['confidence'] >= $confidence_threshold) !== ($now['confidence'] >= $confidence_threshold), 'confidence threshold crossing'),
            );
            foreach ($checks as $check) {
                if ($check[3]) { $new[] = $this->change($product, $check[0], $check[1], $check[2], $check[4], $now['affected_counties']); }
                elseif ($check[1] !== $check[2]) { $this->audit->write('info', 'Insignificant change logged only.', array('product' => $product, 'field' => $check[0])); }
            }
        }
        foreach ($new as $change) {
            if (!$this->duplicate_pending($queue, $change)) { $queue[$change['id']] = $change; }
        }
        update_option(self::QUEUE, $queue, false);
        return $new;
    }

    public function source_change($source, $previous, $new) {
        $reason = ($new === 'healthy') ? 'source degradation recovery' : 'source degradation';
        $change = $this->change('Source Health', $source, $previous, $new, $reason, array());
        $queue = get_option(self::QUEUE, array());
        if (!$this->duplicate_pending($queue, $change)) { $queue[$change['id']] = $change; update_option(self::QUEUE, $queue, false); }
        return $change;
    }

    public function queue() { return get_option(self::QUEUE, array()); }
    public function history() { return get_option(self::HISTORY, array()); }

    public function decide($id, $decision, $note = '') {
        $queue = $this->queue();
        if (empty($queue[$id])) { return false; }
        $change = $queue[$id];
        unset($queue[$id]); update_option(self::QUEUE, $queue, false);
        $change['decision'] = sanitize_key($decision); $change['decision_note'] = sanitize_text_field($note); $change['decided_at'] = current_time('mysql');
        $history = $this->history(); array_unshift($history, $change); update_option(self::HISTORY, array_slice($history, 0, 300), false);
        $this->audit->write('info', 'Material change ' . $decision . '.', array('id' => $id));
        return true;
    }

    public function publish($id) {
        foreach ($this->history() as $item) {
            if ($item['id'] === $id && $item['decision'] === 'approved') {
                update_option('r9ls_last_publish', array('change' => $item, 'published_at' => current_time('mysql')), false);
                $this->audit->write('info', 'Approved material change published.', array('id' => $id));
                return true;
            }
        }
        $this->audit->write('warning', 'Publish blocked because approval is required.', array('id' => $id));
        return false;
    }

    public function rollback($id) {
        update_option('r9ls_last_rollback', array('id' => sanitize_text_field($id), 'time' => current_time('mysql')), false);
        $this->audit->write('warning', 'Published change rolled back.', array('id' => $id));
        return true;
    }

    public function expire_overrides() {
        $overrides = get_option('r9ls_editor_overrides', array());
        foreach ($overrides as $id => $override) {
            if (!empty($override['expires']) && strtotime($override['expires']) <= time()) { unset($overrides[$id]); }
        }
        update_option('r9ls_editor_overrides', $overrides, false);
        return $overrides;
    }

    private function change($product, $field, $previous, $new, $reason, $counties) {
        $stable = md5(wp_json_encode(array($product, $field, $previous, $new, $reason, $counties)));
        return array('id' => $stable, 'product' => $product, 'field' => $field, 'previous' => $previous, 'new' => $new, 'reason' => $reason, 'timestamp' => current_time('mysql'), 'affected_counties' => $counties);
    }

    private function duplicate_pending($queue, $change) { return isset($queue[$change['id']]); }
    private function array_changed($a, $b) { sort($a); sort($b); return $a !== $b; }
}
