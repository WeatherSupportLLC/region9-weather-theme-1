<?php
/**
 * Region 9 Live Studio uninstall safeguard.
 *
 * Plugin operational data is intentionally retained on uninstall unless the site
 * owner explicitly opts in by defining R9LS_DELETE_DATA_ON_UNINSTALL as true.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

if (!defined('R9LS_DELETE_DATA_ON_UNINSTALL') || R9LS_DELETE_DATA_ON_UNINSTALL !== true) {
    return;
}

foreach ([
    'r9ls_source_health',
    'r9ls_validation_results',
    'r9ls_county_matrix',
    'r9ls_decision_history',
    'r9ls_pending_changes',
    'r9ls_overrides',
    'r9ls_audit_log',
    'r9ls_contact_email',
] as $option) {
    delete_option($option);
}
