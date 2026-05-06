<?php

namespace App\Modules\AI\Jobs;

use App\Contracts\AIDriverContract;
use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class TranslatePostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(
        private Post   $post,
        private string $targetLocale,
        private string $sourceLocale = 'en',
    ) {}

    public function handle(AIDriverContract $ai): void
    {
        $translatedContent = $ai->translate($this->post->content_markdown, $this->targetLocale, $this->sourceLocale);
        $translatedTitle   = $ai->translate($this->post->title, $this->targetLocale, $this->sourceLocale);

        PostTranslation::updateOrCreate(
            ['post_id' => $this->post->id, 'locale' => $this->targetLocale],
            [
                'title'            => $translatedTitle,
                'slug'             => Str::slug($translatedTitle),
                'content_markdown' => $translatedContent,
                'is_ai_translated' => true,
            ]
        );
    }
}
