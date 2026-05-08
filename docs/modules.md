# MarkdownPress — Application Modules Reference

This document describes every module under `app/Modules/`, every service and repository in the service layer, all contracts, and the complete domain event/listener map for MarkdownPress.

---

## Table of Contents

1. [Module Overview](#1-module-overview)
2. [Module: Post](#2-module-post)
3. [Module: Shortcode](#3-module-shortcode)
4. [Module: Media](#4-module-media)
5. [Module: Theme](#5-module-theme)
6. [Module: AI](#6-module-ai)
7. [Module: StaticGen](#7-module-staticgen)
8. [Service Layer](#8-service-layer)
9. [Repository Layer](#9-repository-layer)
10. [Contracts](#10-contracts)
11. [Event / Listener Map](#11-event--listener-map)

---

## 1. Module Overview

MarkdownPress is organised as a **modular monolith**. All domain logic lives under `app/Modules/`, each module being a self-contained namespace that owns its models, services, repositories, jobs, events, and Filament resources. The application bootstraps all modules through individual `ModuleServiceProvider` classes, which are registered automatically via a pattern in `bootstrap/providers.php`.

### Directory Structure

```
app/
├── Contracts/                  # Application-wide interfaces
├── Models/                     # Eloquent models (shared, module-agnostic)
├── Modules/
│   ├── Post/
│   │   ├── Actions/
│   │   ├── Contracts/
│   │   ├── Events/
│   │   ├── Jobs/
│   │   ├── Listeners/
│   │   ├── Repositories/
│   │   ├── Services/
│   │   └── PostServiceProvider.php
│   ├── Shortcode/
│   │   ├── Contracts/
│   │   ├── Handlers/
│   │   ├── Services/
│   │   └── ShortcodeServiceProvider.php
│   ├── Media/
│   │   ├── Jobs/
│   │   ├── Repositories/
│   │   ├── Services/
│   │   └── MediaServiceProvider.php
│   ├── Theme/
│   │   ├── Contracts/
│   │   ├── Services/
│   │   └── ThemeServiceProvider.php
│   ├── AI/
│   │   ├── Actions/
│   │   ├── Contracts/
│   │   ├── Drivers/
│   │   ├── Jobs/
│   │   └── AIServiceProvider.php
│   └── StaticGen/
│       ├── Commands/
│       ├── Jobs/
│       ├── Services/
│       └── StaticGenServiceProvider.php
├── Repositories/               # Shared repository contracts + implementations
└── Services/                   # Cross-cutting application services
```

### PSR-4 Autoloading

Each module is registered in `composer.json` under the application's root namespace:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

Because all modules live under `app/Modules/`, they resolve automatically. The fully-qualified class name for `PostService`, for example, is `App\Modules\Post\Services\PostService`.

### Module Service Providers

Each module exports exactly one service provider that:

1. Binds the module's contract interfaces to their concrete implementations in the IoC container.
2. Registers event listeners scoped to that module.
3. Schedules any recurring jobs owned by the module.
4. Loads module-specific configuration and view namespaces if required.

---

## 2. Module: Post

**Namespace:** `App\Modules\Post`

### Responsibilities

- CRUD lifecycle management for posts (create, update, soft-delete, restore, force-delete).
- Markdown-to-HTML rendering pipeline including shortcode resolution and syntax highlighting.
- Revision history: capturing snapshots on every save and exposing a diff-based restore flow.
- Scheduled publishing: transitioning posts from `scheduled` → `published` at the correct UTC time.
- Post search and filtering via the repository layer.
- Cache invalidation of rendered HTML when post content or shortcodes change.

### Classes

#### `PostService`

`App\Modules\Post\Services\PostService`

The primary application service for post management. All Filament resources and API controllers interact with `PostService` rather than directly with Eloquent. The service coordinates the repository, rendering pipeline, revision capture, and event dispatch.

| Method | Signature | Description |
|---|---|---|
| `create` | `create(array $data, User $author): Post` | Validates, persists, renders HTML, saves initial revision, dispatches `PostCreated`. |
| `update` | `update(Post $post, array $data, User $editor): Post` | Updates fields, re-renders HTML if Markdown changed, saves revision, dispatches `PostUpdated`. |
| `publish` | `publish(Post $post): Post` | Transitions status to `published`, sets `published_at`, dispatches `PostPublished`. |
| `schedule` | `schedule(Post $post, Carbon $at): Post` | Sets status to `scheduled` and `scheduled_at`. |
| `unpublish` | `unpublish(Post $post): Post` | Reverts status to `draft`, clears `published_at`. |
| `delete` | `delete(Post $post): void` | Soft-deletes the post. |
| `restore` | `restore(Post $post): void` | Restores a soft-deleted post. |
| `forceDelete` | `forceDelete(Post $post): void` | Permanently removes post, revisions, translations, and related media joins. |
| `restoreRevision` | `restoreRevision(Revision $revision, User $editor): Post` | Copies revision content back to the post, saves a new revision to record the restore action. |
| `generateSlug` | `generateSlug(string $title, ?int $exceptId = null): string` | Produces a unique URL slug from a title. |

#### `PostRepository`

`App\Modules\Post\Repositories\PostRepository`

Implements `PostRepositoryContract`. Encapsulates all Eloquent queries for the `posts` table. Controllers and services never write raw queries; they call repository methods.

| Method | Signature | Description |
|---|---|---|
| `findBySlug` | `findBySlug(string $slug): ?Post` | Fetches a published post by its slug; returns `null` if not found or not published. |
| `findById` | `findById(int $id): ?Post` | Fetches any post by ID, including soft-deleted. |
| `latestPublished` | `latestPublished(int $perPage = 15): LengthAwarePaginator` | Paginates published posts ordered by `published_at` descending. |
| `featured` | `featured(int $limit = 5): Collection` | Returns `is_featured = 1` posts. |
| `byCategory` | `byCategory(Category $cat, int $perPage = 15): LengthAwarePaginator` | Paginates posts in a category. |
| `byTag` | `byTag(Tag $tag, int $perPage = 15): LengthAwarePaginator` | Paginates posts with a tag. |
| `search` | `search(string $query, int $perPage = 15): LengthAwarePaginator` | Full-text search over title and Markdown content. |
| `scheduled` | `scheduled(): Collection` | Returns posts where `status = scheduled` and `scheduled_at <= now()`. |
| `forAuthor` | `forAuthor(User $user, int $perPage = 15): LengthAwarePaginator` | Paginates all posts by a given author. |

#### `MarkdownRenderingPipeline`

`App\Modules\Post\Services\MarkdownRenderingPipeline`

Wraps the `league/commonmark` `MarkdownConverter` and applies a series of pre-processors and post-processors to the raw Markdown before and after conversion. Returns final HTML.

**Pipeline stages (in order):**

| Stage | Type | Description |
|---|---|---|
| `ShortcodePreProcessor` | Pre-processor | Extracts shortcode blocks and replaces them with stable placeholder tokens before Markdown parsing. |
| CommonMark conversion | Core | Runs the full GFM-extended `MarkdownConverter` (tables, task lists, strikethrough, autolinks). |
| `SyntaxHighlightExtension` | CommonMark extension | Wraps fenced code blocks with Prism-compatible class attributes. |
| `ShortcodePostProcessor` | Post-processor | Substitutes shortcode placeholder tokens with rendered shortcode HTML from `ShortcodeRenderer`. |
| `LazyImageProcessor` | Post-processor | Adds `loading="lazy"` and `decoding="async"` attributes to all `<img>` tags. |
| `ExternalLinkProcessor` | Post-processor | Adds `rel="noopener noreferrer" target="_blank"` to external anchor tags. |
| `HeadingAnchorProcessor` | Post-processor | Injects `id` attributes on `<h2>`–`<h4>` elements for in-page navigation. |

```php
// Usage within PostService
$html = app(MarkdownRenderingPipeline::class)->render($post->content_markdown);
$post->content_html_cached = $html;
```

#### `RevisionService`

`App\Modules\Post\Services\RevisionService`

Manages the creation and retrieval of post revisions. Revisions are append-only and are never mutated after creation.

| Method | Signature | Description |
|---|---|---|
| `capture` | `capture(Post $post, User $author): Revision` | Creates a new `Revision` record from the current post state. Stores title, full Markdown, and a JSON diff of all other changed fields. |
| `forPost` | `forPost(Post $post): Collection` | Returns all revisions for a post, ordered by `created_at` descending. |
| `diff` | `diff(Revision $from, Revision $to): array` | Returns a structured diff array comparing two revision Markdown snapshots. |
| `pruneOld` | `pruneOld(Post $post, int $keep = 50): int` | Deletes revisions beyond the `$keep` limit, returning the count removed. |

#### `ScheduledPublishingJob`

`App\Modules\Post\Jobs\ScheduledPublishingJob`

A queued job dispatched by the Laravel scheduler every minute. Queries all posts with `status = scheduled` and `scheduled_at <= now()`, then calls `PostService::publish()` for each. Implements idempotency by wrapping each publish in a database transaction with a `status` re-check.

```php
// Registered in PostServiceProvider
$schedule->job(ScheduledPublishingJob::class)->everyMinute()->withoutOverlapping();
```

### Contracts / Interfaces

| Interface | Path | Description |
|---|---|---|
| `PostRepositoryContract` | `App\Modules\Post\Contracts\PostRepositoryContract` | Defines the query interface for the post repository. |
| `MarkdownRendererContract` | `App\Modules\Post\Contracts\MarkdownRendererContract` | Defines the `render(string $markdown): string` interface. |
| `RevisionServiceContract` | `App\Modules\Post\Contracts\RevisionServiceContract` | Defines the revision capture and retrieval interface. |

---

## 3. Module: Shortcode

**Namespace:** `App\Modules\Shortcode`

### Responsibilities

- Parsing shortcode syntax from Markdown and HTML content.
- Dispatching parsed shortcodes to the correct handler.
- Managing the runtime registry of available shortcodes.
- Rendering shortcode output as safe, sanitized HTML.
- Providing an extension point so new shortcodes can be added by inserting a row in `shortcode_registry` and implementing `ShortcodeHandlerContract`.

### Shortcode Syntax

MarkdownPress uses a bracket-style shortcode syntax compatible with TyroPress:

```
[youtube id="dQw4w9WgXcQ" autoplay="false"]
[alert type="warning"]Content here[/alert]
[mermaid]graph TD; A-->B[/mermaid]
```

Shortcodes may be self-closing or may wrap inner content between opening and closing tags.

### Classes

#### `ShortcodeRegistry`

`App\Modules\Shortcode\Services\ShortcodeRegistry`

Bootstrapped from the `shortcode_registry` database table at application boot (results are cached for the duration of the request lifecycle). Provides handler resolution by shortcode name.

| Method | Signature | Description |
|---|---|---|
| `register` | `register(string $name, string $handlerClass): void` | Inserts or updates a registry entry and refreshes the in-memory map. |
| `resolve` | `resolve(string $name): ?ShortcodeHandlerContract` | Returns a handler instance for the given shortcode name, or `null` if unrecognised. |
| `all` | `all(): Collection` | Returns all active shortcode registry entries. |
| `disable` | `disable(string $name): void` | Sets `is_active = false` for the named shortcode. |

#### `ShortcodeParser`

`App\Modules\Shortcode\Services\ShortcodeParser`

A pure parser that processes a string (HTML or Markdown) and extracts all shortcode occurrences. Returns a `ParsedShortcodeCollection` — an ordered list of `ParsedShortcode` value objects, each containing the tag name, attributes array, inner content, and original source token.

The parser uses a single-pass regex with a named capture group approach and does not recurse into nested shortcodes of the same type (to prevent ambiguity).

```php
// Extract all shortcodes from a string
$parsed = app(ShortcodeParser::class)->parse($markdown);

foreach ($parsed->all() as $shortcode) {
    // $shortcode->name, $shortcode->attributes, $shortcode->content, $shortcode->token
}
```

#### `ShortcodeRenderer`

`App\Modules\Shortcode\Services\ShortcodeRenderer`

Accepts a `ParsedShortcode` and delegates rendering to the appropriate handler via the `ShortcodeRegistry`. Wraps handler output in an error boundary — if a handler throws, the renderer returns a styled error notice block rather than propagating the exception.

| Method | Signature | Description |
|---|---|---|
| `render` | `render(ParsedShortcode $shortcode): string` | Resolves the handler, calls `handle()`, and returns rendered HTML. |
| `renderAll` | `renderAll(string $content): string` | Convenience method: parses a full string and replaces all shortcode tokens with rendered output. |

#### Individual Handlers

All handlers implement `ShortcodeHandlerContract` and live under `App\Modules\Shortcode\Handlers\`.

| Class | Shortcode Tag | Description |
|---|---|---|
| `YouTubeHandler` | `[youtube]` | Renders a privacy-enhanced `youtube-nocookie.com` iframe embed with responsive wrapper. Accepts `id`, `start`, `autoplay` attributes. |
| `GistHandler` | `[gist]` | Renders a GitHub Gist embed via the Gist JSON API (`gist.github.com/{id}.json`). Accepts `id`, `file` attributes. |
| `CodePenHandler` | `[codepen]` | Renders a CodePen embed iframe. Accepts `id`, `user`, `height`, `theme`, `tab` attributes. |
| `MermaidHandler` | `[mermaid]` | Renders a `<div class="mermaid">` block with the diagram source. The front-end Mermaid.js library renders it client-side. |
| `AlertHandler` | `[alert]` | Renders a styled alert callout box. Accepts `type` attribute (`info`, `warning`, `danger`, `success`). Inner content is parsed as Markdown. |
| `AudioHandler` | `[audio]` | Renders an HTML5 `<audio>` player. Accepts `src` or `media_id` attributes. Resolves `media_id` to a signed URL via `MediaService`. |
| `VideoHandler` | `[video]` | Renders an HTML5 `<video>` player. Accepts `src` or `media_id`, `poster`, `autoplay`, `loop` attributes. |
| `TweetHandler` | `[tweet]` | Renders a Twitter/X embedded tweet via the oEmbed endpoint. Accepts `id` or `url` attribute. Falls back to a plain link if the API is unavailable. |
| `FacebookHandler` | `[facebook]` | Renders a Facebook post embed via the Graph API oEmbed endpoint. Accepts `url` attribute. Falls back gracefully. |

### Registering a New Shortcode

To add a custom shortcode handler:

**Step 1** — Implement the handler class:

```php
namespace App\Modules\Shortcode\Handlers;

use App\Contracts\ShortcodeHandlerContract;
use App\Modules\Shortcode\ValueObjects\ParsedShortcode;

class MapHandler implements ShortcodeHandlerContract
{
    public function handle(ParsedShortcode $shortcode): string
    {
        $lat  = $shortcode->attributes['lat'] ?? '0';
        $lng  = $shortcode->attributes['lng'] ?? '0';
        $zoom = $shortcode->attributes['zoom'] ?? '12';

        return '<div class="shortcode-map" data-lat="'
            . e($lat) . '" data-lng="' . e($lng)
            . '" data-zoom="' . e($zoom) . '"></div>';
    }
}
```

**Step 2** — Insert a registry entry (or seed it via a migration):

```php
use App\Models\ShortcodeRegistry;

ShortcodeRegistry::create([
    'name'          => 'map',
    'handler_class' => \App\Modules\Shortcode\Handlers\MapHandler::class,
    'description'   => 'Embeds an interactive map at the given coordinates.',
    'is_active'     => true,
]);
```

**Step 3** — Clear the shortcode registry cache:

```bash
php artisan cache:clear
```

The new shortcode is immediately available in all post content.

---

## 4. Module: Media

**Namespace:** `App\Modules\Media`

### Responsibilities

- Accepting file uploads from the Filament media library panel and the post editor.
- Storing originals on the configured disk (local or S3).
- Dispatching asynchronous jobs to generate WebP conversions and responsive thumbnails.
- Serving media with signed URLs on private disks.
- Providing a browser API for the front-end media picker used by the post editor.
- Integrating stock photo services (Unsplash, Pexels) to import external images into the library.
- Managing media soft-deletion and storage garbage collection.

### Classes

#### `MediaService`

`App\Modules\Media\Services\MediaService`

Primary service for all media operations. Coordinates uploads, processing dispatch, signed URL generation, and deletion.

| Method | Signature | Description |
|---|---|---|
| `store` | `store(UploadedFile $file, User $owner, string $collection = 'images'): Media` | Validates MIME type and file size, saves the file to disk, creates the `Media` record, dispatches `MediaProcessingJob`. |
| `importFromUrl` | `importFromUrl(string $url, User $owner, string $collection = 'images'): Media` | Downloads a remote URL (e.g., from a stock photo provider), stores it locally, and creates the `Media` record. |
| `getUrl` | `getUrl(Media $media, string $variant = 'original'): string` | Returns a signed URL (private disk) or plain URL (public disk) for a media file or named variant. |
| `delete` | `delete(Media $media): void` | Deletes the `Media` record and all its `MediaVariant` records, then removes all physical files from disk. |
| `updateAltText` | `updateAltText(Media $media, string $altText): Media` | Updates the `alt_text` column. |
| `star` | `star(Media $media): Media` | Toggles `is_starred = true`. |
| `unstar` | `unstar(Media $media): Media` | Toggles `is_starred = false`. |

#### `MediaRepository`

`App\Modules\Media\Repositories\MediaRepository`

Implements `MediaRepositoryContract`. Provides paginated, filtered browsing queries for the media library.

| Method | Signature | Description |
|---|---|---|
| `browse` | `browse(array $filters, int $perPage = 30): LengthAwarePaginator` | Filters by `collection`, `mime_type`, `is_starred`, full-text search on `filename` and `alt_text`. |
| `findById` | `findById(int $id): ?Media` | Fetches a single media record with its variants eager-loaded. |
| `recentByUser` | `recentByUser(User $user, int $limit = 20): Collection` | Returns the most recently uploaded files for a given user. |

#### `MediaProcessingJob`

`App\Modules\Media\Jobs\MediaProcessingJob`

A queued job dispatched after every upload. Runs on the `media` queue. Sequentially invokes `WebPConverter` and `ThumbnailGenerator` for image files. For video files, it extracts a poster still using FFmpeg if available. Non-image/video files (audio, documents) have no variants generated.

```php
// Dispatched by MediaService::store()
MediaProcessingJob::dispatch($media)->onQueue('media');
```

#### `WebPConverter`

`App\Modules\Media\Services\WebPConverter`

Converts an original image to WebP format using `intervention/image`. Saves the output as a new `MediaVariant` with `variant_name = 'webp'`. Skips files that are already WebP or are animated GIFs.

```php
$converter->convert($media);
// Creates media_variants row: { variant_name: 'webp', mime_type: 'image/webp', ... }
```

#### `ThumbnailGenerator`

`App\Modules\Media\Services\ThumbnailGenerator`

Generates three responsive size variants from the original image using `intervention/image`:

| Variant Name | Max Dimension | JPEG Quality |
|---|---|---|
| `thumb` | 150 × 150 (cropped) | 80 |
| `medium` | 640px wide, aspect-preserved | 82 |
| `large` | 1280px wide, aspect-preserved | 85 |

Each variant is saved to disk and recorded in `media_variants`.

#### `StockPhotoService`

`App\Modules\Media\Services\StockPhotoService`

Provides a unified interface over multiple stock photo APIs. Currently supports Unsplash and Pexels. Configuration is read from `settings` (group: `ai`, keys: `unsplash_access_key`, `pexels_api_key`).

| Method | Signature | Description |
|---|---|---|
| `search` | `search(string $query, int $perPage = 20, string $provider = 'unsplash'): array` | Searches the given provider and returns a normalized array of photo results. |
| `import` | `import(string $photoId, string $provider, User $owner): Media` | Downloads the full-resolution photo and imports it via `MediaService::importFromUrl()`. |

---

## 5. Module: Theme

**Namespace:** `App\Modules\Theme`

### Responsibilities

- Managing the registry of installed themes.
- Activating and deactivating themes.
- Loading and merging theme configuration with user customizations.
- Rendering front-end pages using the active theme's Blade views.
- Providing a live-preview mechanism in the Filament theme panel.

### Blade View Structure

Each theme is a self-contained directory under `resources/themes/{slug}/`:

```
resources/themes/aurora/
├── views/
│   ├── layouts/
│   │   └── app.blade.php       # Root layout with <head>, <body>
│   ├── partials/
│   │   ├── header.blade.php
│   │   ├── footer.blade.php
│   │   ├── sidebar.blade.php
│   │   └── post-card.blade.php
│   ├── pages/
│   │   ├── home.blade.php
│   │   ├── post.blade.php
│   │   ├── category.blade.php
│   │   ├── tag.blade.php
│   │   ├── author.blade.php
│   │   └── search.blade.php
│   └── components/             # Inline Blade components scoped to the theme
├── assets/
│   ├── css/
│   └── js/
└── theme.json                  # Schema for config options
```

Theme views are loaded via a namespaced view finder: `theme::pages.post` resolves to the active theme's `pages/post.blade.php`. The `ThemeServiceProvider` registers the active theme's view path at boot.

### Classes

#### `ThemeRegistry`

`App\Modules\Theme\Services\ThemeRegistry`

Manages the set of installed themes and controls which theme is active.

| Method | Signature | Description |
|---|---|---|
| `active` | `active(): Theme` | Returns the currently active theme. Results are cached. Throws if no active theme is found. |
| `all` | `all(): Collection` | Returns all installed themes. |
| `activate` | `activate(Theme $theme): void` | Sets `is_active = true` on the given theme and `false` on all others within a transaction. Dispatches a cache clear. |
| `isInstalled` | `isInstalled(string $slug): bool` | Checks whether a theme directory and `theme.json` exist. |

#### `ThemeRenderer`

`App\Modules\Theme\Services\ThemeRenderer`

Resolves the correct Blade view for a given page type under the active theme and renders it with the supplied data. Falls back to a bundled default theme view if the active theme does not define the requested view.

```php
// Rendering a post page
return $themeRenderer->render('pages.post', [
    'post'       => $post,
    'related'    => $relatedPosts,
    'comments'   => $comments,
]);
```

#### `ThemeConfigLoader`

`App\Modules\Theme\Services\ThemeConfigLoader`

Reads the `theme.json` schema for a given theme, loads the `config` column from the `themes` table, and validates and merges the values. Provides typed accessors for theme config values with fallback to schema defaults.

```php
$loader = app(ThemeConfigLoader::class)->for($theme);

$primaryColor = $loader->get('colors.primary', '#3b82f6');
$fontFamily   = $loader->get('typography.body_font', 'Inter');
```

#### `ThemeCustomizationService`

`App\Modules\Theme\Services\ThemeCustomizationService`

Manages the `theme_customizations` table. Provides batch-set and batch-get operations used by the Filament theme customization panel.

| Method | Signature | Description |
|---|---|---|
| `set` | `set(Theme $theme, string $key, mixed $value): void` | Upserts a key-value customization row. |
| `setMany` | `setMany(Theme $theme, array $data): void` | Upserts multiple keys within a single transaction. |
| `get` | `get(Theme $theme, string $key, mixed $default = null): mixed` | Retrieves a single customization value. |
| `all` | `all(Theme $theme): Collection` | Returns all customizations for a theme. |
| `reset` | `reset(Theme $theme): void` | Deletes all customization rows for a theme, reverting to schema defaults. |

---

## 6. Module: AI

**Namespace:** `App\Modules\AI`

### Responsibilities

- Providing a unified interface over multiple LLM providers (Gemini, Qwen, and the default OpenAI driver via `openai-php/laravel`).
- Executing content generation actions: summarisation, excerpt generation, translation.
- Supporting **Bring Your Own Key (BYOK)** — per-user or per-site API keys stored in `settings`.
- Dispatching AI workloads as queued jobs to prevent blocking the request cycle.
- Tracking token usage and estimated cost per request (stored in `meta` on the relevant model).

### BYOK Configuration

AI provider keys are stored in the `settings` table under the `ai` group:

| Key | Description |
|---|---|
| `ai.default_driver` | Active driver: `openai`, `gemini`, or `qwen` |
| `ai.openai_api_key` | OpenAI API key (overrides `OPENAI_API_KEY` env) |
| `ai.gemini_api_key` | Google Gemini API key |
| `ai.qwen_api_key` | Alibaba Qwen API key |
| `ai.openai_model` | OpenAI model identifier, e.g. `gpt-4o` |
| `ai.gemini_model` | Gemini model identifier, e.g. `gemini-1.5-pro` |
| `ai.qwen_model` | Qwen model identifier, e.g. `qwen-max` |

### Classes

#### `AIService` (interface)

`App\Modules\AI\Contracts\AIDriverContract`

The central interface resolved from the IoC container. The active driver is determined by the `ai.default_driver` setting. See [Contracts](#10-contracts) for the full interface definition.

#### `GeminiDriver`

`App\Modules\AI\Drivers\GeminiDriver`

Implements `AIDriverContract` using the Google Gemini REST API. Handles authentication, request serialisation, streaming support, and error normalisation into the application's `AIException` type.

#### `QwenDriver`

`App\Modules\AI\Drivers\QwenDriver`

Implements `AIDriverContract` using the Alibaba Cloud Qwen API. Follows the same contract as `GeminiDriver`, providing a transparent swap. Uses `openai-php/laravel`'s custom base URL support to target the Qwen-compatible OpenAI endpoint.

#### `SummaryAction`

`App\Modules\AI\Actions\SummaryAction`

Generates a summary of a post's Markdown content. Uses a structured prompt that instructs the LLM to return plain text within a specified word count. The summary is persisted as an excerpt on the `Post` model.

```php
$summary = app(SummaryAction::class)->execute($post, maxWords: 80);
```

#### `ExcerptAction`

`App\Modules\AI\Actions\ExcerptAction`

Generates a short, punchy excerpt (typically 1–2 sentences) optimised for use in post cards and Open Graph descriptions. Returns a plain-text string.

```php
$excerpt = app(ExcerptAction::class)->execute($post);
```

#### `TranslateAction`

`App\Modules\AI\Actions\TranslateAction`

Translates a post's title, Markdown body, meta title, and meta description into a target locale. Returns a `TranslationResult` value object. On success, creates or updates the corresponding `post_translations` row with `is_ai_translated = true`. Dispatches `TranslationRequested` event before executing.

```php
$result = app(TranslateAction::class)->execute($post, targetLocale: 'fr');
```

#### `AIJob`

`App\Modules\AI\Jobs\AIJob`

A generic queued job that wraps any invokable AI action. Used to push expensive generation tasks off the request lifecycle onto the `ai` queue.

```php
AIJob::dispatch(TranslateAction::class, [
    'post_id'       => $post->id,
    'target_locale' => 'de',
])->onQueue('ai');
```

---

## 7. Module: StaticGen

**Namespace:** `App\Modules\StaticGen`

### Responsibilities

- Crawling all public routes (posts, categories, tags, author pages, home) and rendering each to a static HTML file.
- Copying all public media assets into the build output directory.
- Packaging the output as a downloadable ZIP archive.
- Recording build progress and final statistics in the `static_builds` table.
- Providing an Artisan command for local/CI-triggered builds.

### Classes

#### `StaticSiteBuilder`

`App\Modules\StaticGen\Services\StaticSiteBuilder`

Orchestrates the full build. Iterates over all published posts and taxonomy pages, renders each with `ThemeRenderer`, and writes the output HTML to a structured directory.

| Method | Signature | Description |
|---|---|---|
| `build` | `build(Theme $theme, string $outputPath): BuildResult` | Executes the full site build and returns a `BuildResult` value object with file/image counts. |
| `renderPage` | `renderPage(string $route, array $viewData): string` | Renders a single page to an HTML string using `ThemeRenderer`. |
| `copyAssets` | `copyAssets(Theme $theme, string $outputPath): int` | Copies the theme's compiled CSS/JS and all public media files to the output directory. |

#### `BuildOrchestrator`

`App\Modules\StaticGen\Services\BuildOrchestrator`

Higher-level coordinator called by `StaticGenJob`. Creates the `static_builds` record, monitors build progress, handles exceptions, and updates the record's `status` field at each lifecycle stage.

```
pending → running → completed
                 ↘ failed
```

#### `BlogBuildCommand`

`App\Modules\StaticGen\Commands\BlogBuildCommand`

Artisan command: `php artisan markdownpress:build`. Accepts `--theme` and `--output` options. Suitable for CI/CD pipelines or scheduled local builds.

```bash
php artisan markdownpress:build --theme=aurora --output=./dist
```

#### `ZipExporter`

`App\Modules\StaticGen\Services\ZipExporter`

Compresses a completed build output directory into a single ZIP archive using PHP's `ZipArchive` extension. Saves the archive to the `builds` disk and updates `static_builds.zip_path` and `static_builds.zip_size`.

```php
$zipPath = app(ZipExporter::class)->export($buildOutputPath, $staticBuild);
```

#### `BuildRecorder`

`App\Modules\StaticGen\Services\BuildRecorder`

Thin wrapper around `StaticBuild` Eloquent model. Provides typed helper methods used by `BuildOrchestrator` to update build status and counters atomically.

| Method | Signature | Description |
|---|---|---|
| `markRunning` | `markRunning(StaticBuild $build): void` | Sets `status = running`, `started_at = now()`. |
| `markCompleted` | `markCompleted(StaticBuild $build, BuildResult $result): void` | Sets `status = completed`, `completed_at`, `file_count`, `image_count`. |
| `markFailed` | `markFailed(StaticBuild $build, Throwable $e): void` | Sets `status = failed`, `error_message`. |

---

## 8. Service Layer

Cross-cutting services under `app/Services/` are not scoped to a single module and may be used by multiple modules or by HTTP controllers directly.

### `AnalyticsService`

`App\Services\AnalyticsService`

Provides read-only aggregate views over the `page_views` table. All methods return pre-aggregated result objects; they never return raw Eloquent collections to avoid N+1 issues in the Filament analytics dashboard.

| Method | Description |
|---|---|
| `totalViewsForPost(Post $post, Carbon $from, Carbon $to): int` | Total views for a post in a date range. |
| `topPosts(int $limit, Carbon $from, Carbon $to): Collection` | Top posts by view count in a date range. |
| `viewsByCountry(Post $post, Carbon $from, Carbon $to): Collection` | View counts grouped by country code. |
| `viewsOverTime(Carbon $from, Carbon $to, string $interval = 'day'): Collection` | Daily/weekly/monthly time-series of site-wide views. |
| `incrementViewCount(Post $post, Request $request): void` | Writes a `page_views` row and increments `posts.view_count` via an atomic query. Deduplicates by `session_id` within a 30-minute window. |

### `NewsletterService`

`App\Services\NewsletterService`

Manages subscriber lifecycle and email dispatch. Email sending uses Laravel's Mail system with the configured mail driver.

| Method | Description |
|---|---|
| `subscribe(string $email, ?string $name = null): Subscriber` | Upserts a subscriber record, generates a unique token, sends a confirmation email. |
| `unsubscribe(string $token): void` | Finds subscriber by token, sets `status = unsubscribed` and `unsubscribed_at = now()`. |
| `sendPostNotification(Post $post): void` | Queues a notification email to all `status = active` subscribers. |
| `import(array $emails): array` | Bulk-imports an array of `[email, name]` pairs, returning counts of imported/skipped. |

### `CommentService`

`App\Services\CommentService`

Handles the comment submission, moderation, and count update lifecycle.

| Method | Description |
|---|---|
| `submit(Post $post, array $data, ?User $user = null): Comment` | Validates and creates a comment in `pending` state. Hashes guest email for Gravatar. Fires `CommentSubmitted` event. |
| `approve(Comment $comment): Comment` | Sets `status = approved`. Increments `posts.comment_count` atomically. |
| `reject(Comment $comment): Comment` | Sets `status = rejected`. |
| `delete(Comment $comment): void` | Hard-deletes a comment and decrements `posts.comment_count` if it was approved. |
| `approvedForPost(Post $post): Collection` | Returns approved top-level comments with replies eager-loaded, ordered by `created_at` ascending. |

### `SitemapService`

`App\Services\SitemapService`

Generates the XML sitemap consumed by `routes/web.php` at `/sitemap.xml`. Iterates all published posts, category pages, and tag pages and returns a `Sitemap` value object that renders to valid XML.

| Method | Description |
|---|---|
| `generate(): Sitemap` | Builds a `Sitemap` with `<url>` entries for all public pages. Includes `<lastmod>` from `updated_at` and `<changefreq>` and `<priority>` heuristics per content type. |
| `generateIndex(): SitemapIndex` | Generates a sitemap index referencing monthly post sitemaps for very large sites. |

### `ImportService`

`App\Services\ImportService`

Imports content from a WordPress WXR (XML) export file. Maps WordPress post types, categories, tags, and media attachments to the MarkdownPress schema. Converts HTML post content to Markdown using a reverse-conversion heuristic.

| Method | Description |
|---|---|
| `fromWxr(string $filePath, User $importingUser): ImportResult` | Parses and imports a WXR file. Returns an `ImportResult` with counts of created/skipped/failed items. |
| `previewWxr(string $filePath): ImportPreview` | Parses the file and returns post/category/tag counts without writing to the database. |

### `ExportService`

`App\Services\ExportService`

Exports MarkdownPress content to standard interchange formats.

| Method | Description |
|---|---|
| `toMarkdownZip(): string` | Exports all published posts as individual `.md` files with YAML front-matter in a ZIP archive. Returns the temporary file path. |
| `toJson(): string` | Serialises all posts, categories, and tags to a single JSON blob following the MarkdownPress export schema. |
| `toRss(int $limit = 20): string` | Generates an RSS 2.0 feed of the most recent published posts. |

---

## 9. Repository Layer

All repositories follow the same structural pattern: an interface (contract) under `app/Repositories/Contracts/` and a concrete Eloquent implementation under `app/Repositories/`. Contracts are bound in `AppServiceProvider`.

### Contracts

| Contract | Concrete Implementation | Description |
|---|---|---|
| `PostRepositoryContract` | `PostRepository` | Post queries |
| `MediaRepositoryContract` | `MediaRepository` | Media library queries |
| `CategoryRepositoryContract` | `CategoryRepository` | Category hierarchy queries |
| `TagRepositoryContract` | `TagRepository` | Tag queries |
| `CommentRepositoryContract` | `CommentRepository` | Comment queries with moderation state |
| `SubscriberRepositoryContract` | `SubscriberRepository` | Subscriber queries |
| `RevisionRepositoryContract` | `RevisionRepository` | Revision history queries |
| `SettingsRepositoryContract` | `SettingsRepository` | Settings KV access; caches values with a tagged cache key |

### Base Repository

All concrete repositories extend `App\Repositories\AbstractRepository`, which provides:

```php
abstract class AbstractRepository
{
    protected Model $model;

    public function find(int $id): ?Model { ... }
    public function all(): Collection { ... }
    public function create(array $data): Model { ... }
    public function update(Model $model, array $data): Model { ... }
    public function delete(Model $model): bool { ... }
}
```

Domain-specific repositories extend this base and add query methods appropriate to their model.

---

## 10. Contracts

All application-wide interfaces are under `app/Contracts/`. These define the boundaries between modules and the infrastructure layer, making core business logic testable without framework or database dependencies.

### `AIDriverContract`

`App\Contracts\AIDriverContract`

```php
interface AIDriverContract
{
    /**
     * Send a prompt and receive a plain-text response.
     */
    public function complete(string $prompt, array $options = []): string;

    /**
     * Return the name of this driver (e.g., 'openai', 'gemini', 'qwen').
     */
    public function driverName(): string;

    /**
     * Return the estimated token count for a string.
     */
    public function estimateTokens(string $text): int;
}
```

### `ThemeDriverContract`

`App\Contracts\ThemeDriverContract`

```php
interface ThemeDriverContract
{
    /**
     * Render a named view within the theme context with supplied data.
     */
    public function render(string $view, array $data = []): string;

    /**
     * Return the active theme's configuration value by dot-notation key.
     */
    public function config(string $key, mixed $default = null): mixed;

    /**
     * Return true if the active theme provides the given view.
     */
    public function hasView(string $view): bool;
}
```

### `ShortcodeHandlerContract`

`App\Contracts\ShortcodeHandlerContract`

```php
interface ShortcodeHandlerContract
{
    /**
     * Render the shortcode and return safe HTML output.
     */
    public function handle(ParsedShortcode $shortcode): string;

    /**
     * Return a list of accepted attribute names for documentation/validation.
     *
     * @return string[]
     */
    public function supportedAttributes(): array;
}
```

### `MediaStorageContract`

`App\Contracts\MediaStorageContract`

```php
interface MediaStorageContract
{
    /**
     * Persist an uploaded file and return its storage path.
     */
    public function store(UploadedFile $file, string $directory): string;

    /**
     * Delete a file at the given path.
     */
    public function delete(string $path): bool;

    /**
     * Return a URL (signed if required) for the given path.
     */
    public function url(string $path): string;
}
```

---

## 11. Event / Listener Map

MarkdownPress uses Laravel's built-in event/listener system. Events are plain PHP objects under each module's `Events/` directory. Listeners are registered in each module's service provider via `$this->listen` or by implementing `ShouldHandleEventsAfterCommit` where appropriate.

### Domain Events

| Event Class | Namespace | Trigger |
|---|---|---|
| `PostCreated` | `App\Modules\Post\Events` | Dispatched by `PostService::create()` after the post and initial revision are persisted. |
| `PostUpdated` | `App\Modules\Post\Events` | Dispatched by `PostService::update()` after a post is modified. |
| `PostPublished` | `App\Modules\Post\Events` | Dispatched by `PostService::publish()` when status transitions to `published`. |
| `PostDeleted` | `App\Modules\Post\Events` | Dispatched by `PostService::delete()` after soft-deletion. |
| `TranslationRequested` | `App\Modules\AI\Events` | Dispatched by `TranslateAction` before beginning AI translation. |
| `TranslationCompleted` | `App\Modules\AI\Events` | Dispatched by `TranslateAction` after a `post_translations` row is created or updated. |
| `BuildTriggered` | `App\Modules\StaticGen\Events` | Dispatched by `BuildOrchestrator` when a new static build is initiated. |
| `BuildCompleted` | `App\Modules\StaticGen\Events` | Dispatched by `BuildOrchestrator` when a build finishes successfully. |
| `BuildFailed` | `App\Modules\StaticGen\Events` | Dispatched by `BuildOrchestrator` when a build encounters a fatal error. |
| `MediaUploaded` | `App\Modules\Media\Events` | Dispatched by `MediaService::store()` after the file is persisted and the processing job is queued. |
| `MediaDeleted` | `App\Modules\Media\Events` | Dispatched by `MediaService::delete()` after the record and files are removed. |
| `CommentSubmitted` | `App\Events` | Dispatched by `CommentService::submit()` after a new comment is saved. |
| `CommentApproved` | `App\Events` | Dispatched by `CommentService::approve()`. |

### Listener Map

| Event | Listener | Action |
|---|---|---|
| `PostPublished` | `SendNewsletterNotification` | Calls `NewsletterService::sendPostNotification()` for active subscribers. |
| `PostPublished` | `UpdateSitemap` | Queues a sitemap regeneration job. |
| `PostPublished` | `IncrementPublishedCount` | (Optional) Increments a stats counter in `settings`. |
| `PostUpdated` | `InvalidateHtmlCache` | Clears the `content_html_cached` column and schedules a re-render. |
| `PostUpdated` | `UpdateSitemap` | Queues a sitemap regeneration job. |
| `PostCreated` | `CaptureInitialRevision` | Writes the first `revisions` record for the new post. |
| `PostDeleted` | `PruneRelatedMedia` | Removes media-join associations; does not delete the media files themselves. |
| `TranslationRequested` | `LogTranslationRequest` | Writes an informational log entry with the post ID and target locale. |
| `TranslationCompleted` | `InvalidateTranslationCache` | Clears any cached rendered HTML for the translated locale. |
| `BuildTriggered` | `NotifyAdminBuildStarted` | Sends an in-app Filament notification to admin users. |
| `BuildCompleted` | `NotifyAdminBuildReady` | Sends an in-app Filament notification with download link. |
| `BuildFailed` | `NotifyAdminBuildFailed` | Sends an in-app Filament notification with the error message. |
| `MediaUploaded` | `DispatchMediaProcessing` | Dispatches `MediaProcessingJob` onto the `media` queue. |
| `MediaDeleted` | `PurgeMediaFromCdn` | If a CDN integration is configured, issues a cache purge request for all variant URLs. |
| `CommentSubmitted` | `NotifyPostAuthor` | Emails the post author if their notification preference is enabled. |
| `CommentApproved` | `NotifyCommenter` | Emails the commenter (guest or user) to inform them their comment is live. |

### Registering Listeners

All listeners are registered in the relevant module's service provider:

```php
// Example: App\Modules\Post\PostServiceProvider

protected $listen = [
    PostPublished::class => [
        SendNewsletterNotification::class,
        UpdateSitemap::class,
    ],
    PostUpdated::class => [
        InvalidateHtmlCache::class,
        UpdateSitemap::class,
    ],
    PostCreated::class => [
        CaptureInitialRevision::class,
    ],
    PostDeleted::class => [
        PruneRelatedMedia::class,
    ],
];
```
