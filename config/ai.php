<?php

return [
    'default_driver' => env('AI_DEFAULT_DRIVER', 'gemini'),

    'drivers' => [
        'gemini' => [
            'api_key'  => env('GEMINI_API_KEY'),
            'model'    => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'qwen' => [
            'api_key'  => env('QWEN_API_KEY'),
            'base_url' => env('QWEN_BASE_URL', 'http://localhost:11434/v1'),
            'model'    => env('QWEN_MODEL', 'qwen2.5:latest'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model'   => env('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
            'base_url' => 'https://api.anthropic.com/v1',
        ],
    ],

    'queues' => [
        'summary'     => 'ai',
        'excerpt'     => 'ai',
        'translation' => 'ai',
    ],

    'timeouts' => [
        'summary'     => 120,
        'excerpt'     => 60,
        'translation' => 300,
    ],
];
