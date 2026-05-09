<?php

return [
    'handlers' => [
        'youtube'   => App\Modules\Shortcode\Handlers\YoutubeHandler::class,
        'gist'      => App\Modules\Shortcode\Handlers\GistHandler::class,
        'codepen'   => App\Modules\Shortcode\Handlers\CodepenHandler::class,
        'mermaid'   => App\Modules\Shortcode\Handlers\MermaidHandler::class,
        'alert'     => App\Modules\Shortcode\Handlers\AlertHandler::class,
        'audio'     => App\Modules\Shortcode\Handlers\AudioHandler::class,
        'video'     => App\Modules\Shortcode\Handlers\VideoHandler::class,
        'tweet'     => App\Modules\Shortcode\Handlers\TweetHandler::class,
        'facebook'  => App\Modules\Shortcode\Handlers\FacebookHandler::class,
        'gallery'   => App\Modules\Shortcode\Handlers\GalleryHandler::class,
        'cta'       => App\Modules\Shortcode\Handlers\CtaHandler::class,
        'toc'       => App\Modules\Shortcode\Handlers\TocHandler::class,
        'code'      => App\Modules\Shortcode\Handlers\CodeHandler::class,
        'notice'    => App\Modules\Shortcode\Handlers\NoticeHandler::class,
        'columns'   => App\Modules\Shortcode\Handlers\ColumnsHandler::class,
        'post_list' => App\Modules\Shortcode\Handlers\PostListHandler::class,
    ],
    'max_nesting_depth' => 3,
];
