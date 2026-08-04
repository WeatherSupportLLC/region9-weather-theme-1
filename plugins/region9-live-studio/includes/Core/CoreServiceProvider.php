<?php

declare(strict_types=1);

namespace Region9\LiveStudio\Core;

use Region9\LiveStudio\Config\Counties;
use Region9\LiveStudio\ServiceProvider;

defined('ABSPATH') || exit;

final class CoreServiceProvider implements ServiceProvider
{
    public function register(): void
    {
        register_activation_hook(R9LS_PLUGIN_FILE, [$this, 'activate']);
    }

    public function activate(): void
    {
        add_option('r9ls_validation_results', ['status' => 'pending', 'auto_publish' => false], '', false);
        add_option('r9ls_county_matrix', array_fill_keys(Counties::all(), []), '', false);
        add_option('r9ls_contact_email', get_option('admin_email'), '', false);
    }
}
