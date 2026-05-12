<?php
namespace App\Modules\AI;

use App\Contracts\AIDriverContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiDriver implements AIDriverContract
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('ai.drivers.openai.api_key', '');
        $this->model  = config('ai.drivers.openai.model', 'gpt-4o-mini');
    }

    public function summarize(string $markdown): string
    {
        return $this->chat("Summarize the following Markdown article in 2-3 sentences:\n\n{$markdown}");
    }

    public function excerpt(string $markdown, int $words = 50): string
    {
        return $this->chat("Write a compelling excerpt of about {$words} words for the following Markdown article:\n\n{$markdown}");
    }

    public function translate(string $markdown, string $targetLocale, string $sourceLocale = 'en'): string
    {
        return $this->chat("Translate the following Markdown content from {$sourceLocale} to {$targetLocale}. Preserve all Markdown formatting:\n\n{$markdown}");
    }

    private function chat(string $prompt): string
    {
        if (!$this->apiKey) {
            throw new RuntimeException('OpenAI API key not configured.');
        }

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => $this->model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '');
    }
}
