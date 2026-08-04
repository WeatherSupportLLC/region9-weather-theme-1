<?php

declare(strict_types=1);

namespace Region9\LiveStudio\DecisionEngine;

use Region9\LiveStudio\ServiceProvider;

defined('ABSPATH') || exit;

final class DecisionEngineServiceProvider implements ServiceProvider
{
    public function register(): void
    {
        add_filter('r9ls_decision_engine_evaluate', [$this, 'evaluate'], 10, 3);
    }

    public function evaluate($result, array $weather, array $overrides = []): array
    {
        return (new DecisionEngine())->evaluate($weather, $overrides);
    }
}
