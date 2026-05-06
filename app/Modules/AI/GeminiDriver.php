<?php

namespace App\Modules\AI;

use App\Contracts\AIDriverContract;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiDriver implements AIDriverContract
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('ai.drivers.gemini.api_key', '');
        $this->model   = config('ai.drivers.gemini.model', 'gemini-1.5-flash');
        $this->baseUrl = config('ai.drivers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
    }

    public function summarize(string $markdown): string
    {
        $prompt = "Summarize the following Markdown article in 2-3 sentences:\n\n{$markdown}";
        return $this->generate($prompt);
    }

    public function excerpt(string $markdown, int $words = 50): string
    {
        $prompt = "Write a compelling excerpt of about {$words} words for the following Markdown article:\n\n{$markdown}";
        return $this->generate($prompt);
    }

    public function translate(string $markdown, string $targetLocale, string $sourceLocale = 'en'): string
    {
        $prompt = "Translate the following Markdown content from {$sourceLocale} to {$targetLocale}. Preserve all Markdown formatting:\n\n{$markdown}";
        return $this->generate($prompt);
    }

    private function generate(string $prompt): string
    {
        if (!$this->apiKey) {
            throw new RuntimeException('Gemini API key not configured.');
        }

        $response = Http::timeout(config('ai.timeouts.summary', 120))
            ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: ' . $response->body());
        }

        return $response->json('candidates.0.content.parts.0.text', '');
    }
}
