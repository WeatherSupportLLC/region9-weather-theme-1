<?php

declare(strict_types=1);

namespace Region9\LiveStudio\WeatherSources;

use Region9\LiveStudio\ServiceProvider;

defined('ABSPATH') || exit;

final class WeatherSourcesServiceProvider implements ServiceProvider
{
    public function register(): void
    {
        add_action('r9ls_refresh_source_health', [$this, 'refreshHealth']);
    }

    public function refreshHealth(): void
    {
        $data = (new LiveWeatherAdapters())->collect();
        update_option('r9ls_source_health', $data['health'] ?? [
            'status' => 'unknown',
            'checked_at' => gmdate('c'),
        ], false);
    }
}
