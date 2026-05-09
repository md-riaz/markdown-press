<?php
namespace App\Modules\AI;

use App\Contracts\AIDriverContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicDriver implements AIDriverContract
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('ai.drivers.anthropic.api_key', '');
        $this->model   = config('ai.drivers.anthropic.model', 'claude-3-haiku-20240307');
        $this->baseUrl = config('ai.drivers.anthropic.base_url', 'https://api.anthropic.com/v1');
    }

    public function summarize(string $markdown): string
    {
        return $this->message("Summarize the following Markdown article in 2-3 sentences:\n\n{$markdown}");
    }

    public function excerpt(string $markdown, int $words = 50): string
    {
        return $this->message("Write a compelling excerpt of about {$words} words for the following Markdown article:\n\n{$markdown}");
    }

    public function translate(string $markdown, string $targetLocale, string $sourceLocale = 'en'): string
    {
        return $this->message("Translate the following Markdown content from {$sourceLocale} to {$targetLocale}. Preserve all Markdown formatting:\n\n{$markdown}");
    }

    private function message(string $prompt): string
    {
        if (!$this->apiKey) {
            throw new RuntimeException('Anthropic API key not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->post("{$this->baseUrl}/messages", [
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: ' . $response->body());
        }

        return $response->json('content.0.text', '');
    }
}
