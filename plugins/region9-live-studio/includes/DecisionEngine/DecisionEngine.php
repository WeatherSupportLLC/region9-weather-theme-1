<?php

declare(strict_types=1);

namespace Region9\LiveStudio\DecisionEngine;

defined('ABSPATH') || exit;

final class DecisionEngine
{
    private const CATEGORIES = [
        'travel' => ['wind', 'ice', 'snow', 'visibility', 'flooding'],
        'agriculture' => ['freeze', 'heat', 'rain', 'wind'],
        'fieldwork' => ['rain', 'lightning', 'wind', 'mud'],
        'livestock' => ['heat', 'cold', 'wind_chill', 'lightning'],
        'construction' => ['wind', 'lightning', 'heat', 'ice'],
        'outdoor' => ['lightning', 'heat', 'wind', 'rain'],
        'schools' => ['snow', 'ice', 'wind_chill', 'visibility'],
        'forecast_confidence' => ['model_spread', 'office_consistency', 'timing'],
        'utilities' => ['wind', 'ice', 'lightning', 'heat'],
        'emergency_operations' => ['tornado', 'flash_flood', 'damaging_wind', 'confidence'],
    ];

    public function evaluate(array $weather, array $overrides = []): array
    {
        $drivers = $this->extractDrivers($weather);
        $decisions = [];
        foreach (self::CATEGORIES as $category => $weights) {
            $score = $this->score($drivers, $weights);
            if (isset($overrides[$category]) && $this->overrideIsActive((array) $overrides[$category])) {
                $score = max(0, min(100, (int) $overrides[$category]['score']));
            }
            $ranked = $this->rankDrivers($drivers, $weights);
            $decisions[$category] = [
                'score' => $score,
                'rating' => $this->rating($score),
                'confidence' => $this->confidence($weather, $score),
                'primary_drivers' => array_values(array_slice($ranked, 0, 4)),
                'secondary_drivers' => array_values(array_slice($ranked, 4)),
                'summary' => $this->summary($category, $score),
            ];
        }
        return ['generated_at' => gmdate('c'), 'decisions' => $decisions, 'drivers' => $drivers];
    }

    private function overrideIsActive(array $override): bool
    {
        $expiresAt = strtotime((string) ($override['expires_at'] ?? ''));
        return isset($override['score']) && $expiresAt > time();
    }

    private function extractDrivers(array $weather): array
    {
        $alerts = $weather['nws_alerts']['data']['features'] ?? [];
        $hours = $weather['hourly_forecast']['data']['properties']['periods'] ?? [];
        $events = strtolower(wp_json_encode(array_column(array_column($alerts, 'properties'), 'event')) ?: '');
        $first = $hours[0] ?? [];
        $wind = (int) preg_replace('/\D+/', '', (string) ($first['windSpeed'] ?? '0'));
        $temp = (int) ($first['temperature'] ?? 60);
        $short = strtolower((string) ($first['shortForecast'] ?? ''));

        return [
            'tornado' => str_contains($events, 'tornado') ? 95 : 0,
            'flash_flood' => str_contains($events, 'flash flood') || str_contains($events, 'flood') ? 85 : 0,
            'damaging_wind' => max(str_contains($events, 'severe') ? 80 : 0, min(100, $wind * 3)),
            'wind' => min(100, $wind * 3),
            'ice' => str_contains($events . $short, 'ice') ? 80 : 0,
            'snow' => str_contains($events . $short, 'snow') ? 75 : 0,
            'visibility' => str_contains($events . $short, 'fog') ? 55 : 0,
            'flooding' => str_contains($events . $short, 'flood') ? 85 : 0,
            'freeze' => $temp <= 32 ? 70 : 0,
            'heat' => $temp >= 90 ? min(100, ($temp - 80) * 5) : 0,
            'rain' => str_contains($short, 'rain') || str_contains($short, 'shower') ? 45 : 0,
            'lightning' => str_contains($short, 'thunder') || str_contains($events, 'severe') ? 70 : 0,
            'cold' => $temp <= 15 ? 60 : 0,
            'wind_chill' => $temp <= 25 && $wind >= 15 ? 65 : 0,
            'mud' => str_contains($short, 'rain') ? 40 : 0,
            'model_spread' => $this->hasUnavailableSource($weather) ? 70 : 20,
            'office_consistency' => !empty($weather['office_detection']['cwa']) ? 15 : 45,
            'timing' => empty($hours) ? 55 : 20,
            'confidence' => 100 - ($this->hasUnavailableSource($weather) ? 35 : 10),
        ];
    }

    private function hasUnavailableSource(array $weather): bool
    {
        foreach (['nws_alerts', 'nws_points', 'hourly_forecast', 'spc_day1', 'wpc_ero'] as $key) {
            if (($weather[$key]['status'] ?? '') === 'unavailable') {
                return true;
            }
        }
        return false;
    }

    private function score(array $drivers, array $weights): int
    {
        $values = array_map(static fn ($key) => (int) ($drivers[$key] ?? 0), $weights);
        return max($values ?: [0]);
    }

    private function rankDrivers(array $drivers, array $weights): array
    {
        $ranked = [];
        foreach ($weights as $key) {
            $ranked[$key] = (int) ($drivers[$key] ?? 0);
        }
        arsort($ranked);
        return array_map(static fn ($key, $value) => ['driver' => $key, 'score' => $value], array_keys($ranked), $ranked);
    }

    private function rating(int $score): string
    {
        return $score >= 80 ? 'critical' : ($score >= 60 ? 'high' : ($score >= 35 ? 'elevated' : 'normal'));
    }

    private function confidence(array $weather, int $score): int
    {
        $base = empty($weather['hourly_forecast']['data']['properties']['periods']) ? 55 : 82;
        return max(0, min(100, $base - (int) floor($score / 10)));
    }

    private function summary(string $category, int $score): string
    {
        return sprintf('%s operating posture is %s with a %d risk score.', ucwords(str_replace('_', ' ', $category)), $this->rating($score), $score);
    }
}
