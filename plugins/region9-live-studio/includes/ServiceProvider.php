<?php

declare(strict_types=1);

namespace Region9\LiveStudio;

defined('ABSPATH') || exit;

interface ServiceProvider
{
    public function register(): void;
}
