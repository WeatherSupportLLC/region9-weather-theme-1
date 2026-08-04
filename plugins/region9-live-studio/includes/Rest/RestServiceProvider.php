<?php

declare(strict_types=1);

namespace Region9\LiveStudio\Rest;

use Region9\LiveStudio\Automation\AutomationRepository;
use Region9\LiveStudio\DecisionEngine\DecisionEngine;
use Region9\LiveStudio\ServiceProvider;
use Region9\LiveStudio\WeatherSources\LiveWeatherAdapters;
use WP_REST_Request;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class RestServiceProvider implements ServiceProvider
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route('region9-live-studio/v1', '/weather', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'weather'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('region9-live-studio/v1', '/decisions', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'decisions'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('region9-live-studio/v1', '/automation', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'automation'],
            'permission_callback' => [$this, 'canOperate'],
        ]);
        foreach (['approve', 'reject', 'publish', 'rollback'] as $action) {
            register_rest_route('region9-live-studio/v1', '/automation/' . $action, [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => fn () => (new AutomationRepository())->transition($action),
                'permission_callback' => [$this, 'canOperate'],
            ]);
        }
        register_rest_route('region9-live-studio/v1', '/pending', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'pending'],
            'permission_callback' => [$this, 'canOperate'],
        ]);
        register_rest_route('region9-live-studio/v1', '/overrides', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'overrides'],
            'permission_callback' => [$this, 'canOperate'],
        ]);
    }

    public function canOperate(): bool
    {
        return is_user_logged_in() && current_user_can('manage_options');
    }

    public function weather(WP_REST_Request $request): array
    {
        return (new LiveWeatherAdapters())->collect((float) ($request['lat'] ?: 40.6331), (float) ($request['lon'] ?: -89.3985));
    }

    public function decisions(WP_REST_Request $request): array
    {
        $weather = $this->weather($request);
        return (new DecisionEngine())->evaluate($weather, get_option('r9ls_overrides', []));
    }

    public function automation(): array
    {
        return (new AutomationRepository())->snapshot();
    }

    public function pending(WP_REST_Request $request): array
    {
        $data = (array) $request->get_json_params();
        $repository = new AutomationRepository();
        $repository->setPending($data);
        return $repository->snapshot();
    }

    public function overrides(WP_REST_Request $request): array
    {
        $data = (array) $request->get_json_params();
        $overrides = (new AutomationRepository())->saveOverrides($data);
        return ['overrides' => $overrides];
    }
}
