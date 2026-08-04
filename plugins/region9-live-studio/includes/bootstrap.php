<?php

declare(strict_types=1);

namespace Region9\LiveStudio;

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = R9LS_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

final class Bootstrap
{
    private static bool $booted = false;

    /** @var ServiceProvider[] */
    private static array $providers = [];

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;
        self::$providers = [
            new Core\CoreServiceProvider(),
            new Logging\LoggingServiceProvider(),
            new WeatherSources\WeatherSourcesServiceProvider(),
            new Scoring\ScoringServiceProvider(),
            new DecisionEngine\DecisionEngineServiceProvider(),
            new Overrides\OverridesServiceProvider(),
            new Publishing\PublishingServiceProvider(),
            new Automation\AutomationServiceProvider(),
            new Rest\RestServiceProvider(),
            new Admin\AdminServiceProvider(),
        ];

        foreach (self::$providers as $provider) {
            $provider->register();
        }
    }
}
