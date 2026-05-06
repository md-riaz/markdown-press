<?php

return [
    'disk'           => env('MEDIA_DISK', 'public'),
    'max_size'       => env('MEDIA_MAX_SIZE', 20 * 1024 * 1024),
    'allowed_mimes'  => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'audio/mpeg', 'audio/wav', 'audio/ogg',
        'video/mp4',
    ],
    'variants' => [
        'webp'   => ['format' => 'webp', 'quality' => 82],
        'thumb'  => ['width' => 300, 'height' => 300, 'fit' => 'contain', 'format' => 'webp'],
        'medium' => ['width' => 800, 'format' => 'webp'],
    ],
    'stock_photos' => [
        'unsplash' => [
            'access_key' => env('UNSPLASH_ACCESS_KEY'),
            'base_url'   => 'https://api.unsplash.com',
        ],
        'pixabay' => [
            'api_key'  => env('PIXABAY_API_KEY'),
            'base_url' => 'https://pixabay.com/api',
        ],
        'pexels' => [
            'api_key'  => env('PEXELS_API_KEY'),
            'base_url' => 'https://api.pexels.com/v1',
        ],
    ],
    'ssrf_allowed_hosts' => [
        'images.unsplash.com',
        'pixabay.com',
        'images.pexels.com',
        'cdn.pixabay.com',
    ],
];
