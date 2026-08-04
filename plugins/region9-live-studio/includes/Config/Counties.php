<?php

declare(strict_types=1);

namespace Region9\LiveStudio\Config;

defined('ABSPATH') || exit;

final class Counties
{
    public const REGION9_COUNTIES = [
        'Kankakee',
        'Iroquois',
        'Ford',
        'Livingston',
        'DeWitt',
        'Piatt',
        'Champaign',
        'Vermilion',
        'McLean',
    ];

    public static function all(): array
    {
        return self::REGION9_COUNTIES;
    }
}
