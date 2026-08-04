<?php

declare(strict_types=1);

namespace Region9\LiveStudio\Automation;

use Region9\LiveStudio\ServiceProvider;

defined('ABSPATH') || exit;

final class AutomationServiceProvider implements ServiceProvider
{
    public function register(): void
    {
        add_action('r9ls_automation_action', [$this, 'handle'], 10, 2);
    }

    public function handle(string $action, array $payload = []): array
    {
        $repository = new AutomationRepository();
        if ($action === 'pending') {
            $repository->setPending($payload);
            return $repository->snapshot();
        }
        return $repository->transition($action);
    }
}
