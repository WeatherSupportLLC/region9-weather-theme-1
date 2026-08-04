<?php

declare(strict_types=1);

namespace Region9\LiveStudio\Automation;

use Region9\LiveStudio\Config\Counties;

defined('ABSPATH') || exit;

final class AutomationRepository
{
    public function snapshot(): array
    {
        $this->expireOverrides();
        return [
            'source_health' => get_option('r9ls_source_health', []),
            'validation_results' => get_option('r9ls_validation_results', ['status' => 'pending', 'auto_publish' => false]),
            'county_matrix' => get_option('r9ls_county_matrix', array_fill_keys(Counties::all(), [])),
            'decision_history' => get_option('r9ls_decision_history', []),
            'pending_changes' => get_option('r9ls_pending_changes', []),
            'overrides' => get_option('r9ls_overrides', []),
            'audit_log' => get_option('r9ls_audit_log', []),
        ];
    }

    public function log(string $action, array $payload = []): array
    {
        $entry = ['action' => $action, 'payload' => $payload, 'at' => gmdate('c'), 'user' => get_current_user_id()];
        $log = get_option('r9ls_audit_log', []);
        array_unshift($log, $entry);
        $log = array_slice($log, 0, 200);
        update_option('r9ls_audit_log', $log, false);
        return $entry;
    }

    public function setPending(array $changes): void
    {
        update_option('r9ls_pending_changes', $changes, false);
        $this->log('pending_changes_saved', $changes);
    }

    public function saveOverrides(array $overrides): array
    {
        $safe = [];
        foreach ($overrides as $category => $override) {
            $override = (array) $override;
            $expiresAt = strtotime((string) ($override['expires_at'] ?? '+4 hours')) ?: strtotime('+4 hours');
            $safe[sanitize_key((string) $category)] = [
                'score' => max(0, min(100, (int) ($override['score'] ?? 0))),
                'reason' => sanitize_text_field((string) ($override['reason'] ?? 'Editor override')),
                'expires_at' => gmdate('c', $expiresAt),
                'updated_at' => gmdate('c'),
                'updated_by' => get_current_user_id(),
            ];
        }
        update_option('r9ls_overrides', $safe, false);
        $this->log('overrides_updated', $safe);
        return $safe;
    }

    public function transition(string $action): array
    {
        $allowed = ['approve', 'reject', 'publish', 'rollback'];
        if (!in_array($action, $allowed, true)) {
            $this->log('invalid_transition', ['action' => $action]);
            return $this->snapshot();
        }

        $pending = get_option('r9ls_pending_changes', []);
        if ($action === 'approve') {
            update_option('r9ls_validation_results', ['status' => 'approved', 'approved_at' => gmdate('c'), 'auto_publish' => false], false);
        } elseif ($action === 'reject') {
            update_option('r9ls_pending_changes', [], false);
            update_option('r9ls_validation_results', ['status' => 'rejected', 'rejected_at' => gmdate('c'), 'auto_publish' => false], false);
        } elseif ($action === 'publish') {
            $validation = get_option('r9ls_validation_results', []);
            if (($validation['status'] ?? '') !== 'approved') {
                $this->log('publish_blocked_unapproved', $pending);
                return $this->snapshot();
            }
            $history = get_option('r9ls_decision_history', []);
            array_unshift($history, ['published_at' => gmdate('c'), 'changes' => $pending]);
            update_option('r9ls_decision_history', array_slice($history, 0, 100), false);
            update_option('r9ls_pending_changes', [], false);
            update_option('r9ls_validation_results', ['status' => 'published', 'published_at' => gmdate('c'), 'auto_publish' => false], false);
        } elseif ($action === 'rollback') {
            $history = get_option('r9ls_decision_history', []);
            $last = array_shift($history);
            update_option('r9ls_pending_changes', $last['changes'] ?? [], false);
            update_option('r9ls_decision_history', $history, false);
            update_option('r9ls_validation_results', ['status' => 'rolled_back', 'rolled_back_at' => gmdate('c'), 'auto_publish' => false], false);
        }
        $this->log($action, $pending);
        return $this->snapshot();
    }

    private function expireOverrides(): void
    {
        $overrides = get_option('r9ls_overrides', []);
        $active = [];
        foreach ((array) $overrides as $category => $override) {
            $expiresAt = strtotime((string) ($override['expires_at'] ?? ''));
            if ($expiresAt > time()) {
                $active[$category] = $override;
            }
        }
        if ($active !== $overrides) {
            update_option('r9ls_overrides', $active, false);
            $this->log('overrides_expired');
        }
    }
}
