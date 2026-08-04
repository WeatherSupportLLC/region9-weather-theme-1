<?php

declare(strict_types=1);

namespace Region9\LiveStudio\WeatherSources;

defined('ABSPATH') || exit;

final class Cache
{
    public function remember(string $key, int $ttl, callable $resolver): array
    {
        $cached = get_transient($key);
        if (is_array($cached)) {
            $cached['cache'] = array_merge($cached['cache'] ?? [], [
                'hit' => true,
                'stale' => $this->isStale($cached),
            ]);
            return $cached;
        }

        $value = $resolver();
        if (($value['status'] ?? '') === 'available') {
            $value['cache'] = [
                'hit' => false,
                'stale' => false,
                'stored_at' => gmdate('c'),
                'ttl' => $ttl,
            ];
            set_transient($key, $value, $ttl);
        }

        return $value;
    }

    private function isStale(array $cached): bool
    {
        $storedAt = strtotime((string) ($cached['cache']['stored_at'] ?? ''));
        $ttl = (int) ($cached['cache']['ttl'] ?? 0);
        return $storedAt > 0 && $ttl > 0 && time() > ($storedAt + $ttl);
    }
}
