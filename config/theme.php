<?php

return [
    'themes_path'   => resource_path('themes'),
    'default_theme' => env('DEFAULT_THEME', 'hello-world'),
    'cache_ttl'     => env('THEME_CACHE_TTL', 3600),
];
