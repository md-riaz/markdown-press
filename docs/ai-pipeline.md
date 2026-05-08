# AI Pipeline

## 1. Overview

MarkdownPress includes a first-class AI pipeline for automating content enrichment tasks: summarisation, excerpt generation, translation, and tone analysis. The pipeline is built on a **Bring Your Own Key (BYOK)** model — users supply their own API credentials and MarkdownPress never proxies requests through a shared key.

The pipeline supports multiple AI backends through a driver abstraction:

- **Gemini** (primary) — Google's Gemini API via direct HTTP calls.
- **Qwen** (fallback) — Alibaba's Qwen model, configured against a local or remote OpenAI-compatible endpoint.

A **Markdown Integrity System** protects all content sent to AI: code blocks and shortcodes are extracted before the prose is sent, and reinjected verbatim after the AI response is received. This guarantees that AI processing never corrupts Markdown structure, code samples, or MarkdownPress shortcodes.

All long-running AI operations run as queued jobs on the dedicated `ai` queue, decoupled from the HTTP request cycle.

---

## 2. Configuration

AI driver settings live in `config/ai.php`. All sensitive values are read from environment variables and never hardcoded.

### config/ai.php

```php
return [

    'default_driver' => env('AI_DEFAULT_DRIVER', 'gemini'),

    'drivers' => [

        'gemini' => [
            'api_key'     => env('GEMINI_API_KEY'),
            'model'       => env('GEMINI_MODEL', 'gemini-1.5-pro'),
            'temperature' => (float) env('GEMINI_TEMPERATURE', 0.3),
            'max_tokens'  => (int)   env('GEMINI_MAX_TOKENS', 4096),
        ],

        'qwen' => [
            'api_key'  => env('QWEN_API_KEY'),
            'base_url' => env('QWEN_BASE_URL', 'http://localhost:11434/v1'),
            'model'    => env('QWEN_MODEL', 'qwen2.5:14b'),
            'temperature' => (float) env('QWEN_TEMPERATURE', 0.3),
            'max_tokens'  => (int)   env('QWEN_MAX_TOKENS', 4096),
        ],

    ],

    'fallback_driver' => env('AI_FALLBACK_DRIVER', 'qwen'),

    'queue' => [
        'name'    => 'ai',
        'timeout' => 120,
        'retries' => 3,
        'backoff' => [10, 30, 60], // seconds between retry attempts
    ],

];
```

### Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `AI_DEFAULT_DRIVER` | No | `gemini` | Primary driver slug |
| `AI_FALLBACK_DRIVER` | No | `qwen` | Fallback driver when primary fails |
| `GEMINI_API_KEY` | Yes (if using Gemini) | — | Google AI Studio API key |
| `GEMINI_MODEL` | No | `gemini-1.5-pro` | Gemini model identifier |
| `QWEN_API_KEY` | Yes (if using Qwen remote) | — | Qwen API key (omit for local Ollama) |
| `QWEN_BASE_URL` | No | `http://localhost:11434/v1` | Qwen-compatible OpenAI endpoint |
| `QWEN_MODEL` | No | `qwen2.5:14b` | Model name at the Qwen endpoint |

---

## 3. AIDriverContract Interface

All drivers implement the following contract. No caller should depend on a concrete driver class.

```php
<?php

namespace App\AI\Contracts;

interface AIDriverContract
{
    /**
     * Generate a multi-paragraph summary of the given Markdown document.
     */
    public function summarize(string $markdown): string;

    /**
     * Generate a short excerpt of the given Markdown document.
     *
     * @param  int  $words  Target word count for the excerpt.
     */
    public function excerpt(string $markdown, int $words = 50): string;

    /**
     * Translate a Markdown document to the specified locale.
     *
     * @param  string  $targetLocale  ISO 639-1 locale code (e.g. 'fr', 'ar', 'zh').
     * @param  string  $sourceLocale  Source locale; defaults to 'en'.
     */
    public function translate(string $markdown, string $targetLocale, string $sourceLocale = 'en'): string;

    /**
     * Analyse the prose tone and return a weighted list of tone labels.
     *
     * @return array<string, float>  e.g. ['formal' => 0.8, 'technical' => 0.6]
     */
    public function suggestTone(string $markdown): array;
}
```

### Driver Resolution

`AIManager` extends Laravel's `Manager` and resolves the correct driver at call time:

```php
// App\AI\AIManager

public function driver(?string $name = null): AIDriverContract
{
    $name ??= config('ai.default_driver');

    try {
        return parent::driver($name);
    } catch (Throwable $e) {
        $fallback = config('ai.fallback_driver');
        Log::warning("AI driver [{$name}] failed, falling back to [{$fallback}]", [
            'error' => $e->getMessage(),
        ]);
        return parent::driver($fallback);
    }
}
```

---

## 4. GeminiDriver

`GeminiDriver` calls the Google Gemini REST API directly using Laravel's `Http` facade.

### Key Implementation Details

**HTTP Client** — Uses `Http::withHeaders(['x-goog-api-key' => $this->apiKey])` against `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`.

**System Prompt** — Every request prepends a system instruction that enforces Markdown output preservation:

```
You are a Markdown-aware content assistant. Your output must always be valid
Markdown. Preserve all heading levels, list structures, link syntax, and code
fence markers exactly as they appear in the input. Never convert Markdown to
HTML or plain text.
```

**Temperature** — Set to `0.3` by default (configurable via `GEMINI_TEMPERATURE`) to favour deterministic, factual output over creative variation.

**Summarise Implementation**

```php
public function summarize(string $markdown): string
{
    $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
        ->post($this->endpoint('generateContent'), [
            'system_instruction' => ['parts' => [['text' => $this->systemPrompt()]]],
            'contents' => [[
                'parts' => [['text' => "Summarise the following article in 3–5 paragraphs:\n\n{$markdown}"]],
            ]],
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => $this->maxTokens,
            ],
        ]);

    return $this->extractText($response->json());
}
```

**Response Extraction** — `extractText()` navigates `candidates[0].content.parts[0].text` and throws `AIResponseException` if the path is missing.

---

## 5. QwenDriver

`QwenDriver` communicates with any OpenAI-compatible endpoint, enabling both local Ollama-hosted Qwen models and the Alibaba Cloud Qwen API.

### Key Implementation Details

**HTTP Client** — Uses `Http::withToken($this->apiKey)->baseUrl($this->baseUrl)` and calls `/chat/completions`.

**Same System Prompt** — Identical Markdown preservation instruction as `GeminiDriver`.

**OpenAI-compatible Payload**

```php
protected function chat(string $userMessage): string
{
    $response = Http::withToken($this->apiKey)
        ->baseUrl($this->baseUrl)
        ->post('/chat/completions', [
            'model'       => $this->model,
            'temperature' => $this->temperature,
            'max_tokens'  => $this->maxTokens,
            'messages'    => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user',   'content' => $userMessage],
            ],
        ]);

    return $response->json('choices.0.message.content')
        ?? throw new AIResponseException('Unexpected Qwen response structure.');
}
```

**Local Endpoint** — When `QWEN_BASE_URL` points to a local Ollama instance, no `Authorization` header is sent (empty `api_key` is ignored by Ollama).

---

## 6. Markdown Integrity System

Sending raw Markdown to an LLM risks losing code blocks, shortcodes, or link references as the model paraphrases or reformats them. The Markdown Integrity System solves this with a placeholder extraction and reinsertion pipeline.

### Pipeline Steps

```
Input Markdown
      │
      ▼
┌─────────────────────────────────┐
│ 1. Extract protected blocks     │  Code fences (```...```), inline code,
│                                 │  MarkdownPress shortcodes ([sc]...[/sc])
│    Replace with placeholders    │  e.g. %%MP_BLOCK_0%%, %%MP_BLOCK_1%%
└──────────────┬──────────────────┘
               │  Clean prose only
               ▼
┌─────────────────────────────────┐
│ 2. Send prose to AI driver      │
│                                 │  Driver processes text; placeholders
│                                 │  pass through untouched
└──────────────┬──────────────────┘
               │  AI response (prose + placeholders)
               ▼
┌─────────────────────────────────┐
│ 3. Reinject original blocks     │  Replace %%MP_BLOCK_N%% with original
│                                 │  extracted content
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 4. Validate output integrity    │  Assert: all original link URLs present
│                                 │  Assert: all code fence openings present
│                                 │  Assert: placeholder count == 0
└──────────────┬──────────────────┘
               │
               ▼
         Final Markdown
```

### MarkdownIntegrityProcessor

```php
// App\AI\MarkdownIntegrityProcessor

class MarkdownIntegrityProcessor
{
    private array $extracted = [];

    public function extract(string $markdown): string
    {
        $this->extracted = [];
        $index = 0;

        // Extract fenced code blocks
        $markdown = preg_replace_callback(
            '/```[\s\S]*?```/m',
            function ($m) use (&$index) {
                $placeholder = "%%MP_BLOCK_{$index}%%";
                $this->extracted[$placeholder] = $m[0];
                $index++;
                return $placeholder;
            },
            $markdown
        );

        // Extract shortcodes
        $markdown = preg_replace_callback(
            '/\[[\w-]+(?:\s[^\]]+)?\](?:[\s\S]*?\[\/[\w-]+\])?/m',
            function ($m) use (&$index) {
                $placeholder = "%%MP_BLOCK_{$index}%%";
                $this->extracted[$placeholder] = $m[0];
                $index++;
                return $placeholder;
            },
            $markdown
        );

        return $markdown;
    }

    public function reinject(string $processed): string
    {
        return str_replace(
            array_keys($this->extracted),
            array_values($this->extracted),
            $processed
        );
    }

    public function validate(string $original, string $result): void
    {
        // All placeholders must be resolved
        if (preg_match('/%%MP_BLOCK_\d+%%/', $result)) {
            throw new MarkdownIntegrityException('Unreinjected placeholder found in AI output.');
        }

        // All original Markdown link URLs must still be present
        preg_match_all('/\]\((https?:\/\/[^\)]+)\)/', $original, $originalLinks);
        foreach ($originalLinks[1] as $url) {
            if (!str_contains($result, $url)) {
                throw new MarkdownIntegrityException("Link URL lost in AI processing: {$url}");
            }
        }
    }
}
```

---

## 7. Actions

Each AI capability is encapsulated as a dedicated Action class. Actions are the primary interface consumed by controllers and queue jobs — they are never called directly on drivers.

### SummaryAction

```php
// App\AI\Actions\SummaryAction

class SummaryAction
{
    public function __construct(
        private readonly AIManager $ai,
        private readonly MarkdownIntegrityProcessor $integrity,
    ) {}

    public function execute(Post $post): string
    {
        $clean    = $this->integrity->extract($post->body);
        $result   = $this->ai->driver()->summarize($clean);
        $restored = $this->integrity->reinject($result);

        $this->integrity->validate($post->body, $restored);

        return $restored;
    }
}
```

### ExcerptAction

```php
class ExcerptAction
{
    public function execute(Post $post, int $words = 50): string
    {
        $clean    = $this->integrity->extract($post->body);
        $result   = $this->ai->driver()->excerpt($clean, $words);
        $restored = $this->integrity->reinject($result);

        $this->integrity->validate($post->body, $restored);

        return $restored;
    }
}
```

### TranslateAction

```php
class TranslateAction
{
    public function execute(Post $post, string $targetLocale, string $sourceLocale = 'en'): PostTranslation
    {
        $clean    = $this->integrity->extract($post->body);
        $result   = $this->ai->driver()->translate($clean, $targetLocale, $sourceLocale);
        $restored = $this->integrity->reinject($result);

        $this->integrity->validate($post->body, $restored);

        return PostTranslation::updateOrCreate(
            ['post_id' => $post->id, 'locale' => $targetLocale],
            ['title' => $this->translateTitle($post->title, $targetLocale), 'body' => $restored]
        );
    }
}
```

---

## 8. Queue Jobs

All AI jobs are dispatched to the `ai` queue. They extend `AIJob`, which configures shared retry and timeout behaviour.

### AIJob (Base)

```php
// App\Jobs\AI\AIJob

abstract class AIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 3;
    public int   $timeout = 120;
    public array $backoff = [10, 30, 60];

    public function __construct()
    {
        $this->onQueue('ai');
    }
}
```

### Job Reference

| Job | Dispatched By | Description |
|-----|--------------|-------------|
| `GenerateSummaryJob` | `PostSaved` listener or admin action | Calls `SummaryAction`, saves result to `posts.ai_summary` |
| `GenerateExcerptJob` | `PostSaved` listener or admin action | Calls `ExcerptAction`, saves result to `posts.excerpt` if `excerpt` is null |
| `TranslatePostJob` | `TranslationRequested` event listener | Full translation flow; see Section 9 |

### GenerateSummaryJob

```php
class GenerateSummaryJob extends AIJob
{
    public function __construct(public readonly Post $post) {
        parent::__construct();
    }

    public function handle(SummaryAction $action): void
    {
        $summary = $action->execute($this->post);

        $this->post->update(['ai_summary' => $summary]);

        Cache::forget("post_{$this->post->slug}");
    }
}
```

---

## 9. Translation Flow

The translation pipeline is event-driven. The full flow from trigger to completion:

```
Admin clicks "Translate to [locale]"
              │
              ▼
  TranslationRequested event dispatched
  (payload: post_id, target_locale, source_locale)
              │
              ▼
  TranslationRequestedListener
  → dispatches TranslatePostJob to 'ai' queue
              │
              ▼ (async, on queue worker)
  TranslatePostJob::handle()
  → calls TranslateAction::execute($post, $targetLocale)
              │
              ▼
  TranslateAction
  1. Extract code blocks + shortcodes (MarkdownIntegrityProcessor)
  2. Send clean prose to AI driver (translate())
  3. Reinject original blocks
  4. Validate integrity
  5. Translate title (separate AI call with no extraction needed)
  6. PostTranslation::updateOrCreate(...)
              │
              ▼
  TranslationCompleted event dispatched
  (payload: post_id, locale, translation_id)
              │
        ┌─────┴─────┐
        ▼           ▼
  Cache::forget   Broadcast to admin
  (post cache)    via Pusher/Echo
                  (updates UI status badge)
```

### PostTranslation Record

On success, a `post_translations` row is created or updated:

```
post_translations
  id              bigint PK
  post_id         bigint FK → posts.id
  locale          varchar(10)
  title           text
  body            longtext
  ai_generated    boolean   (true = created by TranslateAction)
  created_at, updated_at
```

---

## 10. Error Handling

| Error Condition | Detection | Handling |
|----------------|-----------|----------|
| **API key missing** | `config('ai.drivers.{driver}.api_key')` is null at job boot | `AIConfigException` thrown; job marked as `failed` immediately (no retries); admin notification dispatched |
| **Rate limit hit** (HTTP 429) | HTTP response status check in driver | `AIRateLimitException` thrown; job released back to queue with exponential backoff (`backoff` config: 10s, 30s, 60s) |
| **Invalid response format** | `extractText()` returns null or unexpected structure | `AIResponseException` thrown; job retried up to `$tries` limit; on final failure, `JobFailed` event triggers admin alert |
| **Markdown integrity failure** | `MarkdownIntegrityProcessor::validate()` throws | `MarkdownIntegrityException` thrown; job marked failed; original content is **never** overwritten; error logged with full diff |
| **Network timeout** | HTTP client throws `ConnectionException` | Caught in driver; rethrown as `AIDriverException`; subject to standard retry policy |
| **All retries exhausted** | Laravel `failed()` callback | `PostAIJobFailed` event dispatched → stored in `ai_job_failures` table → shown as admin notification with driver name, error message, and post title |

### Admin Visibility

Failed AI jobs surface in the Filament admin panel under **Content → AI Jobs** with:
- Job type (Summary / Excerpt / Translation)
- Target post title (linked)
- Driver used
- Error message
- Timestamp
- Manual retry button (re-dispatches the original job)
