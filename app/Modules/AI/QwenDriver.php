<?php

namespace App\Modules\AI;

use App\Contracts\AIDriverContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QwenDriver implements AIDriverContract
{
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->model   = config('ai.drivers.qwen.model', 'qwen2.5:latest');
        $this->baseUrl = config('ai.drivers.qwen.base_url', 'http://localhost:11434/v1');
    }

    public function summarize(string $markdown): string
    {
        return $this->chat("Summarize in 2-3 sentences:\n\n{$markdown}");
    }

    public function excerpt(string $markdown, int $words = 50): string
    {
        return $this->chat("Write a {$words}-word excerpt:\n\n{$markdown}");
    }

    public function translate(string $markdown, string $targetLocale, string $sourceLocale = 'en'): string
    {
        return $this->chat("Translate from {$sourceLocale} to {$targetLocale}, keep Markdown:\n\n{$markdown}");
    }

    private function chat(string $content): string
    {
        $response = Http::timeout(300)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'    => $this->model,
                'messages' => [['role' => 'user', 'content' => $content]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Qwen API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '');
    }
}
