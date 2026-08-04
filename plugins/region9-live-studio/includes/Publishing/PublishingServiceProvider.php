<?php

declare(strict_types=1);

namespace Region9\LiveStudio\Publishing;

use Region9\LiveStudio\ServiceProvider;

defined('ABSPATH') || exit;

final class PublishingServiceProvider implements ServiceProvider
{
    public function register(): void
    {
        do_action('r9ls_module_registered', 'Publishing');
    }
}
