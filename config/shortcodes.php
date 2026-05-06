<?php

return [
    'handlers' => [
        'youtube'  => App\Modules\Shortcode\Handlers\YoutubeHandler::class,
        'gist'     => App\Modules\Shortcode\Handlers\GistHandler::class,
        'codepen'  => App\Modules\Shortcode\Handlers\CodepenHandler::class,
        'mermaid'  => App\Modules\Shortcode\Handlers\MermaidHandler::class,
        'alert'    => App\Modules\Shortcode\Handlers\AlertHandler::class,
        'audio'    => App\Modules\Shortcode\Handlers\AudioHandler::class,
        'video'    => App\Modules\Shortcode\Handlers\VideoHandler::class,
        'tweet'    => App\Modules\Shortcode\Handlers\TweetHandler::class,
        'facebook' => App\Modules\Shortcode\Handlers\FacebookHandler::class,
    ],
    'max_nesting_depth' => 3,
];
