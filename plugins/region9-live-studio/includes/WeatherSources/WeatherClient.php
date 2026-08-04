<?php

declare(strict_types=1);

namespace Region9\LiveStudio\WeatherSources;

defined('ABSPATH') || exit;

final class WeatherClient
{
    public function getJson(string $url, int $retries = 2): array
    {
        $last = null;
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            $response = wp_remote_get($url, [
                'timeout' => 12,
                'headers' => [
                    'Accept' => 'application/geo+json, application/json',
                    'User-Agent' => $this->userAgent(),
                ],
            ]);

            if (is_wp_error($response)) {
                $last = ['type' => 'wp_error', 'message' => $response->get_error_message()];
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);
                $body = (string) wp_remote_retrieve_body($response);
                $json = json_decode($body, true);
                if ($code >= 200 && $code < 300 && json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    return [
                        'status' => 'available',
                        'http_code' => $code,
                        'fetched_at' => gmdate('c'),
                        'data' => $json,
                    ];
                }
                $last = [
                    'type' => 'bad_response',
                    'http_code' => $code,
                    'json_error' => json_last_error_msg(),
                    'body_preview' => substr($body, 0, 240),
                ];
            }

            if ($attempt < $retries) {
                usleep((int) (250000 * ($attempt + 1)));
            }
        }

        return [
            'status' => 'unavailable',
            'fetched_at' => gmdate('c'),
            'error' => $last ?: ['type' => 'unknown'],
        ];
    }

    private function userAgent(): string
    {
        $contact = (string) get_option('r9ls_contact_email', 'ops@region9weather.com');
        return sprintf('Region 9 Weather Live Studio/%s (%s)', R9LS_VERSION, sanitize_email($contact));
    }
}
