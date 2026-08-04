<?php

declare(strict_types=1);

namespace Region9\LiveStudio\WeatherSources;

use Region9\LiveStudio\Config\Counties;

defined('ABSPATH') || exit;

final class LiveWeatherAdapters
{
    private WeatherClient $client;
    private Cache $cache;

    public function __construct(?WeatherClient $client = null, ?Cache $cache = null)
    {
        $this->client = $client ?: new WeatherClient();
        $this->cache = $cache ?: new Cache();
    }

    public function collect(float $lat = 40.6331, float $lon = -89.3985): array
    {
        $points = $this->points($lat, $lon);
        $pointData = $points['data'] ?? [];
        $forecastUrl = $pointData['properties']['forecastHourly'] ?? '';
        $sources = [
            'nws_alerts' => $this->alerts(),
            'nws_points' => $points,
            'hourly_forecast' => $forecastUrl ? $this->hourlyForecast($forecastUrl) : $this->notRequested('NWS hourly forecast unavailable until Points API returns forecastHourly.'),
            'spc_day1' => $this->spcDay1(),
            'wpc_ero' => $this->wpcEro(),
        ];

        return [
            'generated_at' => gmdate('c'),
            'counties' => Counties::all(),
            'nws_alerts' => $sources['nws_alerts'],
            'nws_points' => $sources['nws_points'],
            'hourly_forecast' => $sources['hourly_forecast'],
            'office_detection' => $this->detectOffices($pointData),
            'spc_day1' => $sources['spc_day1'],
            'wpc_ero' => $sources['wpc_ero'],
            'health' => $this->healthFromSources($sources),
        ];
    }

    public function alerts(string $area = 'IL'): array
    {
        return $this->cache->remember('r9ls_nws_alerts_' . strtolower($area), 300, fn () => $this->client->getJson('https://api.weather.gov/alerts/active?area=' . rawurlencode($area)));
    }

    public function points(float $lat, float $lon): array
    {
        return $this->cache->remember('r9ls_nws_points_' . md5($lat . ',' . $lon), 3600, fn () => $this->client->getJson(sprintf('https://api.weather.gov/points/%F,%F', $lat, $lon)));
    }

    public function hourlyForecast(string $url): array
    {
        return $this->cache->remember('r9ls_hourly_' . md5($url), 600, fn () => $this->client->getJson($url));
    }

    public function spcDay1(): array
    {
        return $this->cache->remember('r9ls_spc_day1', 900, fn () => $this->client->getJson('https://www.spc.noaa.gov/products/outlook/day1otlk_cat.nolyr.geojson'));
    }

    public function wpcEro(): array
    {
        return $this->cache->remember('r9ls_wpc_ero', 900, fn () => $this->client->getJson('https://www.wpc.ncep.noaa.gov/ero/day1/ero_day1.geojson'));
    }

    public function detectOffices(array $points): array
    {
        $office = strtoupper((string) ($points['properties']['cwa'] ?? ''));
        return ['cwa' => $office, 'is_ilx' => $office === 'ILX', 'is_lot' => $office === 'LOT'];
    }

    private function healthFromSources(array $sources): array
    {
        $unavailable = [];
        $stale = [];
        foreach ($sources as $name => $source) {
            if (($source['status'] ?? '') === 'unavailable') {
                $unavailable[] = $name;
            }
            if (!empty($source['cache']['stale'])) {
                $stale[] = $name;
            }
        }
        $status = $unavailable ? 'degraded' : ($stale ? 'stale' : 'healthy');
        $health = ['status' => $status, 'checked_at' => gmdate('c'), 'unavailable' => $unavailable, 'stale' => $stale];
        update_option('r9ls_source_health', $health, false);
        return $health;
    }

    private function notRequested(string $reason): array
    {
        return ['status' => 'not_requested', 'reason' => $reason, 'fetched_at' => gmdate('c')];
    }
}
