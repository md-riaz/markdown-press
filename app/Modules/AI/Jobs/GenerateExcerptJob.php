<?php

namespace App\Modules\AI\Jobs;

use App\Contracts\AIDriverContract;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateExcerptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(private Post $post, private int $words = 50) {}

    public function handle(AIDriverContract $ai): void
    {
        $excerpt = $ai->excerpt($this->post->content_markdown, $this->words);
        $this->post->update(['meta_description' => $excerpt]);
    }
}
