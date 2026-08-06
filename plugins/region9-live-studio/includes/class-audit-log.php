<?php
defined('ABSPATH') || exit;

class R9LS_Audit_Log {
    const OPTION = 'r9ls_audit_log';
    const ERRORS = 'r9ls_current_errors';
    const LIMIT = 300;

    public function write($level, $message, $context = array()) {
        $entry = array(
            'time' => current_time('mysql'),
            'level' => sanitize_key($level),
            'message' => sanitize_text_field($message),
            'context' => $this->sanitize_context($context),
        );
        $log = get_option(self::OPTION, array());
        array_unshift($log, $entry);
        update_option(self::OPTION, array_slice($log, 0, self::LIMIT), false);
        if (in_array($entry['level'], array('error', 'critical'), true)) {
            $errors = get_option(self::ERRORS, array());
            array_unshift($errors, $entry);
            update_option(self::ERRORS, array_slice($errors, 0, 50), false);
        }
        return $entry;
    }

    public function all() {
        return get_option(self::OPTION, array());
    }

    public function errors() {
        return get_option(self::ERRORS, array());
    }

    private function sanitize_context($context) {
        if (!is_array($context)) {
            return sanitize_text_field((string) $context);
        }
        return json_decode(wp_json_encode($context), true);
    }
}
