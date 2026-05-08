# MarkdownPress — Architecture

## 1. Project Overview

MarkdownPress is a production-grade CMS built on Laravel 13 that replicates and extends the feature set of TyroPress — a Markdown-first blogging platform designed for writers, developers, and content teams who demand clean content pipelines, zero vendor lock-in, and extensibility without complexity.

### Philosophy

| Principle | What It Means in Practice |
|-----------|--------------------------|
| **Markdown as single source of truth** | All content is stored as raw Markdown. Rendered HTML is always derived, never primary. |
| **Clean content pipeline** | Markdown → shortcode extraction → CommonMark rendering → HTML cache. Each stage has a single responsibility. |
| **Extensible via shortcodes** | Arbitrary functionality (embeds, galleries, callouts, code playgrounds) is injected at render time without polluting stored content. |
| **Extensible via themes** | Blade-based themes are fully decoupled from content and business logic. Switching themes requires zero data migration. |
| **AI as an optional accelerator** | AI features (summarisation, translation, excerpt generation) are queue-driven enhancements, never blocking operations. |
| **Offline-capable static output** | Every published post can be exported as a fully static HTML site, enabling CDN hosting or archive snapshots. |

MarkdownPress targets teams running self-hosted content platforms at scale while keeping infrastructure requirements modest: a single server with MySQL, Redis, and a queue worker is sufficient for most deployments.

---

## 2. High-Level Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                      CLIENTS                                         │
│  Browser (reader)   Admin SPA (Filament 4)   REST / JSON API         │
└───────────────┬─────────────────┬──────────────────┬────────────────┘
                │  HTTPS          │  HTTPS           │  HTTPS + token
                ▼                 ▼                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   LARAVEL HTTP LAYER                                  │
│                                                                       │
│  routes/web.php          routes/api.php       routes/admin.php       │
│  ThrottleMiddleware       AuthMiddleware       IpWhitelistMiddleware  │
│  LocaleMiddleware         CorsMiddleware       FilamentPanelProvider  │
│  CacheResponseMiddleware                                              │
│                                                                       │
│  PostController  FeedController  SitemapController  ApiPostController│
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   APPLICATION LAYER                                   │
│                                                                       │
│  PostService          MediaService         ShortcodeRegistry         │
│  ContentRenderer      ThemeManager         StaticSiteBuilder         │
│  AiService            TranslationService   SearchService             │
│                                                                       │
│  PostRepository       MediaRepository      TagRepository             │
│  CategoryRepository   UserRepository       BuildRepository           │
└───────────┬──────────────────┬──────────────────────┬───────────────┘
            │                  │                       │
            ▼                  ▼                       ▼
┌───────────────────┐ ┌──────────────────┐  ┌─────────────────────────┐
│   DOMAIN MODULES  │ │   QUEUE WORKERS  │  │   THEME ENGINE          │
│   app/Modules/    │ │                  │  │                         │
│                   │ │  queue:default   │  │  resources/themes/{name}│
│  Post             │ │  queue:ai        │  │  Blade layouts + partials│
│  Media            │ │  queue:media     │  │  Theme config JSON       │
│  Shortcode        │ │  queue:build     │  │  Asset pipeline (Vite)  │
│  Theme            │ │                  │  │                         │
│  AI               │ │  AiSummaryJob    │  └─────────────────────────┘
│  StaticGen        │ │  TranslateJob    │
│  Tag / Category   │ │  WebPConvertJob  │
│  User / Auth      │ │  StaticBuildJob  │
└───────────────────┘ └──────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   INFRASTRUCTURE LAYER                                │
│                                                                       │
│  MySQL 8           Redis             Storage (local / S3)            │
│  ─────────         ─────             ──────────────────              │
│  posts             HTML cache         original uploads               │
│  post_translations session store      WebP variants                  │
│  media             queue backend      thumbnails                     │
│  media_variants    pub/sub            static build ZIPs              │
│  tags, categories  rate-limit tokens                                  │
│  users, tokens     theme asset cache  CDN (optional CloudFront/CF)   │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. Key Architectural Decisions

### 3.1 Modular Monolith (`app/Modules/*`)

MarkdownPress uses a modular monolith rather than microservices. Each domain concern lives in its own module directory:

```
app/
└── Modules/
    ├── Post/
    │   ├── Models/
    │   ├── Services/
    │   ├── Repositories/
    │   ├── Jobs/
    │   ├── Events/
    │   ├── Listeners/
    │   └── Http/
    │       ├── Controllers/
    │       └── Requests/
    ├── Media/
    ├── Shortcode/
    ├── Theme/
    ├── AI/
    ├── StaticGen/
    └── Search/
```

**Rationale:** Microservices introduce distributed-systems complexity (network latency, partial failures, distributed transactions) that is not warranted at typical CMS scale. Modules give clear ownership boundaries, enable future extraction if needed, and allow each team member to reason about their domain without understanding the entire codebase.

### 3.2 Service-Layer Pattern (No Fat Controllers)

Controllers are intentionally thin. They validate the HTTP request, delegate to a service class, and return a response. Business logic never lives in a controller.

```php
// Thin controller — only HTTP concerns
class PostController extends Controller
{
    public function show(string $slug, PostService $posts): Response
    {
        $post = $posts->findPublishedBySlug($slug, app()->getLocale());
        return response()->view('theme::post.show', compact('post'));
    }
}
```

```php
// Service — owns business logic
class PostService
{
    public function findPublishedBySlug(string $slug, string $locale): PostViewModel
    {
        $post = $this->repository->findPublishedBySlug($slug, $locale);
        $html = $this->renderer->render($post, $locale);
        return PostViewModel::from($post, $html);
    }
}
```

### 3.3 Repository Pattern for Data Access

All Eloquent queries are encapsulated in repository classes. This keeps Eloquent out of service classes, makes unit-testing trivial (repositories are mocked), and provides a single place to add database-level caching.

```php
interface PostRepositoryInterface
{
    public function findPublishedBySlug(string $slug, string $locale): Post;
    public function listPublished(int $perPage, array $filters): LengthAwarePaginator;
    public function latestByTag(string $tag, int $limit): Collection;
}
```

### 3.4 Queue-Driven Heavy Tasks

Any operation that takes more than ~50 ms or calls an external API is dispatched to a background queue. This keeps HTTP response times under 200 ms for all user-facing pages.

| Operation | Queue | Typical Duration |
|-----------|-------|-----------------|
| AI summary generation | `ai` | 2–8 s |
| AI excerpt generation | `ai` | 1–4 s |
| AI translation of post body | `ai` | 5–30 s |
| WebP conversion (per image) | `media` | 100–500 ms |
| Thumbnail generation | `media` | 50–200 ms |
| Full static site build | `build` | 5–120 s |
| Search index update | `default` | 50–300 ms |

### 3.5 Redis Cache for Rendered HTML

Rendering Markdown → HTML (including shortcode processing) is CPU-intensive. The rendered output is stored in Redis keyed by `post:{id}:html:{locale}` with a configurable TTL (default 24 hours). The cache is proactively invalidated on any mutation that affects output:

| Invalidation Trigger | Cache Keys Cleared |
|---------------------|--------------------|
| Post saved / published | `post:{id}:html:*` |
| Theme changed (site-wide) | `post:*:html:*` (full flush) |
| Shortcode registered or removed | `post:*:html:*` (full flush) |
| Post translation updated | `post:{id}:html:{locale}` |

A `CacheResponseMiddleware` handles the warm-path: on a cache hit it returns the stored HTML directly without instantiating a service or touching the database.

### 3.6 Blade-Based Theme Engine

Themes are stored under `resources/themes/{theme-name}/` and consist of standard Blade templates plus a `theme.json` manifest. The `ThemeManager` service resolves the active theme and registers its view namespace (`theme::`). Themes have no access to business-logic classes — they receive only `PostViewModel`, `PaginatedPostsViewModel`, and similar read-only value objects.

```json
// resources/themes/aurora/theme.json
{
    "name": "Aurora",
    "version": "1.0.0",
    "author": "MarkdownPress Team",
    "supports": ["dark-mode", "syntax-highlighting", "reading-time", "toc"],
    "layouts": ["default", "full-width", "sidebar"],
    "color_schemes": ["light", "dark", "sepia"]
}
```

### 3.7 Markdown as Single Source of Truth

The `posts` table stores both `content_markdown` (the authoritative source) and `content_html_cached` (a denormalised copy for emergency fallback / search indexing). The `content_html_cached` column is never edited directly — it is only written by `ContentRenderer` after a full render pass.

```sql
-- posts table (relevant columns)
content_markdown     LONGTEXT NOT NULL,
content_html_cached  LONGTEXT NULL,
rendered_at          TIMESTAMP NULL,
```

If Redis is unavailable, `ContentRenderer` falls back to `content_html_cached`. If that is also null (fresh post), it renders on the fly and writes both caches synchronously.

---

## 4. Request Lifecycle

### 4.1 Cache Hit Path

```
Browser GET /blog/my-post
    │
    ▼
ThrottleMiddleware (rate limit check)
    │
    ▼
LocaleMiddleware (detect locale from URL prefix / Accept-Language)
    │
    ▼
CacheResponseMiddleware
    │  checks Redis: post:{id}:html:{locale}
    │  ──► HIT ──► return 200 with cached HTML  (< 5 ms total)
    │
   MISS ──► continues to controller
```

### 4.2 Cache Miss Path

```
CacheResponseMiddleware (miss)
    │
    ▼
PostController::show()
    │  calls PostService::findPublishedBySlug($slug, $locale)
    │
    ▼
PostRepository::findPublishedBySlug()
    │  SELECT with translatable scope; eager-loads tags, author, media
    │
    ▼
ContentRenderer::render(Post $post, string $locale)
    │
    ├── 1. Retrieve content_markdown from post model
    │
    ├── 2. ShortcodeExtractor::extract()
    │       scan for [shortcode attr="value"] patterns
    │       replace each with UUID placeholder %%SC_UUID%%
    │       store {uuid => ShortcodeNode} map
    │
    ├── 3. CommonMarkConverter::convert(string $markdown)
    │       full GFM + table + footnote + syntax-highlight extensions
    │       returns HTML string with %%SC_UUID%% placeholders intact
    │
    ├── 4. ShortcodeRenderer::inject(string $html, array $nodes)
    │       for each placeholder, render the shortcode handler
    │       replace placeholder with rendered HTML fragment
    │
    ├── 5. HtmlPurifier::clean() (optional, configurable)
    │
    └── 6. Redis::set("post:{id}:html:{locale}", $html, TTL)
            also write back to posts.content_html_cached
    │
    ▼
PostViewModel assembled (post + rendered HTML + meta)
    │
    ▼
Blade view rendered via ThemeManager (theme::post.show)
    │
    ▼
HTTP 200 response  (~40–120 ms on cache miss)
```

---

## 5. Technology Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| Framework | Laravel | 13.x | HTTP, routing, DI, queue, scheduler |
| Language | PHP | 8.3 | Runtime |
| Database | MySQL | 8.0 | Primary persistent store |
| Cache / Queue backend | Redis | 7.x | HTML cache, session, queue, rate limiting |
| Admin panel | Filament | 4.x | Full-featured admin UI, form builder, tables |
| Markdown parser | league/commonmark | 2.x | GFM, tables, footnotes, syntax highlighting |
| Media management | spatie/laravel-medialibrary | 11.x | File upload, conversions, collections |
| Multilingual content | spatie/laravel-translatable | 6.x | JSON-based translatable model attributes |
| Image processing | intervention/image | 3.x | WebP conversion, thumbnail generation |
| AI integration | openai-php/laravel | 0.10.x | GPT-4o summarisation, translation, excerpts |
| Search | Laravel Scout + Meilisearch | — | Full-text post search |
| Asset bundling | Vite + Tailwind CSS | — | Theme CSS/JS compilation |
| Static HTML export | Custom `StaticSiteBuilder` | — | Offline/CDN site snapshots |
| Testing | Pest PHP | 3.x | Unit, integration, feature tests |
| Code quality | Laravel Pint + PHPStan level 9 | — | Formatting and static analysis |

---

## 6. Deployment Overview

### Single-Server Deployment

```
┌────────────────────────────────────────────┐
│  Linux server (Ubuntu 24.04 LTS)            │
│                                             │
│  Nginx (TLS termination, static assets)    │
│  PHP-FPM 8.3 (app processes)               │
│  MySQL 8 (database)                        │
│  Redis 7 (cache + queue backend)           │
│  Supervisor (manages queue workers)        │
│  Laravel Scheduler (via cron)              │
└────────────────────────────────────────────┘
```

Supervisor configuration manages four persistent worker processes:

```ini
[program:markdownpress-default]
command=php artisan queue:work redis --queue=default --tries=3 --backoff=5

[program:markdownpress-ai]
command=php artisan queue:work redis --queue=ai --tries=2 --backoff=30 --timeout=120

[program:markdownpress-media]
command=php artisan queue:work redis --queue=media --tries=3 --backoff=10 --timeout=60

[program:markdownpress-build]
command=php artisan queue:work redis --queue=build --tries=1 --timeout=300
```

The Laravel scheduler runs every minute via a single cron entry:

```cron
* * * * * cd /var/www/markdownpress && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks include: sitemap regeneration (hourly), feed refresh (every 15 min), stale-cache sweep (daily), and AI usage report aggregation (daily).

### Docker Deployment

A `docker-compose.yml` ships with the project for local development and containerised production deployments:

```yaml
services:
  app:      # PHP-FPM 8.3 + Laravel
  nginx:    # Nginx reverse proxy
  mysql:    # MySQL 8
  redis:    # Redis 7
  worker:   # Queue workers (same image as app, different CMD)
  scheduler: # Scheduler (same image, runs artisan schedule:run in a loop)
```

### Storage

Media files are stored via the Laravel filesystem abstraction. The default disk is `local` (for development) and `s3` (for production), configured via `FILESYSTEM_DISK` env variable. All `MediaVariant` paths are relative, making disk switching transparent.

```env
FILESYSTEM_DISK=s3
AWS_BUCKET=markdownpress-media
AWS_URL=https://cdn.example.com
```

### Zero-Downtime Deploys

Deployments use an atomic symlink strategy (compatible with Deployer, Envoyer, or plain shell scripts):

1. Clone new release into a timestamped directory.
2. Run `composer install --no-dev --optimize-autoloader`.
3. Run `php artisan migrate --force`.
4. Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. Atomically switch the `current` symlink.
6. Send `SIGUSR2` to PHP-FPM for graceful reload.
7. Restart Supervisor workers (`php artisan queue:restart`).
