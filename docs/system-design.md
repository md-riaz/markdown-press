# MarkdownPress — System Design

## 1. Content Pipeline

The content pipeline is the central processing chain that transforms raw Markdown authored in the admin panel into the final HTML delivered to readers. It is designed to be deterministic, cache-friendly, and safe against Markdown parser interference with shortcode syntax.

### Pipeline Steps

```
1. Raw Markdown stored in posts.content_markdown
        │
        ▼
2. ShortcodeExtractor::extract(string $markdown)
   ├── Regex scan for [tag attr="val" /] and [tag]...[/tag] patterns
   ├── Each match replaced with a UUID placeholder: %%SC_550e8400%%
   └── Returns: (string $sanitisedMarkdown, array<string, ShortcodeNode> $nodes)
        │
        ▼
3. CommonMarkConverter::convert(string $sanitisedMarkdown)
   ├── Extensions: GFM, Tables, Footnotes, Strikethrough,
   │              TaskList, DescriptionList, SyntaxHighlight (highlight.php)
   └── Returns: string $htmlWithPlaceholders
        │
        ▼
4. ShortcodeRenderer::inject(string $html, array $nodes)
   ├── For each %%SC_{uuid}%% placeholder found in $html:
   │   ├── Resolve handler from ShortcodeRegistry by tag name
   │   ├── Call handler->render(ShortcodeNode $node): string
   │   └── Replace placeholder with rendered HTML fragment
   └── Returns: string $fullyRenderedHtml
        │
        ▼
5. (Optional) HtmlPurifier::clean(string $html)
   └── Applied only for user-generated content or API-submitted posts
        │
        ▼
6. Cache store
   ├── Redis::set("post:{id}:html:{locale}", $html, ttl: config('cache.post_html_ttl'))
   └── DB::update posts SET content_html_cached = $html, rendered_at = now()
        │
        ▼
7. HTML returned to PostService → PostViewModel → Blade theme view
```

### ASCII Pipeline Diagram

```
┌──────────────────────┐
│  posts.content_       │
│  markdown (DB)        │
└──────────┬───────────┘
           │ raw Markdown string
           ▼
┌──────────────────────┐      ┌──────────────────────────────┐
│  ShortcodeExtractor  │─────▶│  nodes: Map<uuid, Shortcode> │
│  (extract + replace) │      └──────────────────────────────┘
└──────────┬───────────┘
           │ Markdown with %%SC_uuid%% placeholders
           ▼
┌──────────────────────┐
│  CommonMarkConverter │  (league/commonmark 2.x, GFM extensions)
│  (Markdown → HTML)   │
└──────────┬───────────┘
           │ HTML with %%SC_uuid%% placeholders intact
           ▼
┌──────────────────────┐      ┌──────────────────────────────┐
│  ShortcodeRenderer   │◀─────│  nodes: Map<uuid, Shortcode> │
│  (inject fragments)  │      └──────────────────────────────┘
└──────────┬───────────┘
           │ fully resolved HTML
           ▼
┌──────────────────────┐
│  HtmlPurifier        │  (optional, API/UGC paths only)
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│  Redis cache store   │  key: post:{id}:html:{locale}
│  + DB fallback write │
└──────────────────────┘
```

---

## 2. Shortcode Pipeline

### Why Placeholder-Based Processing

Markdown parsers are aggressive about transforming their input. A naive approach of rendering Markdown first and then processing shortcodes would fail because:

- Square brackets `[...]` are valid Markdown link syntax — the parser will corrupt shortcode attributes.
- Multi-line block shortcodes would be wrapped in `<p>` tags by the paragraph renderer.
- Attribute values containing `*`, `_`, or backticks would be incorrectly processed as Markdown emphasis or code.

The placeholder approach surgically removes all shortcodes before the Markdown parser ever sees them, substituting opaque UUID strings that the parser ignores entirely.

### Extraction Phase

```php
// ShortcodeExtractor.php

public function extract(string $markdown): ExtractionResult
{
    $nodes = [];
    $pattern = '/\[(\w+)((?:\s+[\w-]+=(?:"[^"]*"|\'[^\']*\'|\S+))*)\s*(?:\/\]|\](.*?)\[\/\1\])/s';

    $sanitised = preg_replace_callback($pattern, function (array $matches) use (&$nodes) {
        $uuid = str_replace('-', '', Str::uuid()->toString());
        $placeholder = "%%SC_{$uuid}%%";
        $nodes[$uuid] = new ShortcodeNode(
            tag:        $matches[1],
            attributes: $this->parseAttributes($matches[2]),
            content:    $matches[3] ?? null,
        );
        return $placeholder;
    }, $markdown);

    return new ExtractionResult(markdown: $sanitised, nodes: $nodes);
}
```

### Render and Injection Phase

```php
// ShortcodeRenderer.php

public function inject(string $html, array $nodes): string
{
    foreach ($nodes as $uuid => $node) {
        $placeholder = "%%SC_{$uuid}%%";
        $handler = $this->registry->resolve($node->tag);

        $rendered = $handler
            ? $handler->render($node)
            : "<!-- unknown shortcode: {$node->tag} -->";

        $html = str_replace($placeholder, $rendered, $html);
    }

    return $html;
}
```

### Shortcode Handler Contract

```php
interface ShortcodeHandlerInterface
{
    /**
     * Return the tag name this handler responds to, e.g. "gallery", "callout".
     */
    public function tag(): string;

    /**
     * Render the shortcode node to an HTML string.
     * Must not throw; return an HTML comment on error.
     */
    public function render(ShortcodeNode $node): string;
}
```

Built-in shortcode handlers shipped with MarkdownPress:

| Tag | Description |
|-----|-------------|
| `[gallery ids="1,2,3"]` | Responsive image grid from media IDs |
| `[callout type="warning"]...[/callout]` | Styled alert box |
| `[code lang="php"]...[/code]` | Syntax-highlighted code block with copy button |
| `[embed url="..."]` | oEmbed iframe (YouTube, Vimeo, Twitter) |
| `[toc]` | Auto-generated table of contents from H2/H3 headings |
| `[reading-time]` | Calculated reading time badge |
| `[related tag="laravel" limit="3"]` | Inline related posts |
| `[form id="5"]` | Embedded contact/lead form |

---

## 3. Caching Strategy

### What Is Cached

| Cache Key Pattern | Content | Driver | Default TTL |
|-------------------|---------|--------|-------------|
| `post:{id}:html:{locale}` | Fully rendered post HTML | Redis | 86400 s (24 h) |
| `post:{id}:meta:{locale}` | SEO meta (title, description, og:image) | Redis | 86400 s |
| `feed:rss:{locale}` | RSS feed XML | Redis | 3600 s |
| `feed:atom:{locale}` | Atom feed XML | Redis | 3600 s |
| `sitemap:index` | XML sitemap | Redis | 3600 s |
| `theme:asset-manifest` | Compiled Vite asset paths | Redis | until deploy |
| `taxonomy:tags` | Flattened tag list for nav | Redis | 1800 s |
| `taxonomy:categories` | Category tree | Redis | 1800 s |

All cache keys live in a `markdownpress:` Redis key prefix to avoid collision with other applications on shared Redis instances.

### Invalidation Triggers

Invalidation is handled by `CacheInvalidationService`, which listens to model events via Laravel's observer system.

```php
// PostObserver.php
public function saved(Post $post): void
{
    $this->cache->invalidatePost($post->id);
}

// CacheInvalidationService.php
public function invalidatePost(int $postId): void
{
    // Invalidate all locale variants for this post
    $locales = config('app.supported_locales');
    foreach ($locales as $locale) {
        Redis::del("markdownpress:post:{$postId}:html:{$locale}");
        Redis::del("markdownpress:post:{$postId}:meta:{$locale}");
    }

    // Invalidate feeds and sitemap (post list changed)
    $this->invalidateFeeds();
    $this->invalidateSitemap();
}

public function invalidateAllPosts(): void
{
    // Used on theme change or shortcode registry update
    Redis::eval("
        local keys = redis.call('keys', ARGV[1])
        for _, k in ipairs(keys) do redis.call('del', k) end
        return #keys
    ", 0, 'markdownpress:post:*:html:*');
}
```

### Cache Warm-Up

After a deployment or full-cache flush, `php artisan markdownpress:cache:warm` queues `RenderPostJob` for every published post across all supported locales, ensuring readers do not experience cold-cache latency after a deploy.

---

## 4. Queue Topology

MarkdownPress uses four named queues to prevent head-of-line blocking between job categories of different priorities and durations.

### Queue Definitions

| Queue Name | Worker Timeout | Retry Attempts | Backoff | Purpose |
|------------|---------------|----------------|---------|---------|
| `default` | 60 s | 3 | 5 s, 10 s, 30 s | General-purpose low-latency jobs |
| `ai` | 120 s | 2 | 30 s, 120 s | External OpenAI API calls |
| `media` | 60 s | 3 | 10 s, 30 s, 60 s | CPU-bound image processing |
| `build` | 300 s | 1 | — | Full static site generation |

### Job Registry

| Job Class | Queue | Dispatched When |
|-----------|-------|-----------------|
| `UpdateSearchIndexJob` | `default` | Post published / updated |
| `RenderPostJob` | `default` | Cache miss recorded or manual warm |
| `SendPublishNotificationJob` | `default` | Post transitions to `published` |
| `RegenerateSitemapJob` | `default` | Post published / unpublished |
| `RegenerateFeedJob` | `default` | Post published / unpublished |
| `GenerateAiSummaryJob` | `ai` | Post saved with AI summary enabled |
| `GenerateAiExcerptJob` | `ai` | Post saved with empty excerpt |
| `TranslatePostJob` | `ai` | AI translation requested via admin |
| `ProcessMediaJob` | `media` | Media file uploaded |
| `ConvertToWebPJob` | `media` | Image stored after upload |
| `GenerateThumbnailsJob` | `media` | Image stored after upload |
| `StaticSiteBuildJob` | `build` | Build triggered via admin or API |

### Failed Job Handling

Failed jobs are stored in the `failed_jobs` table. The admin panel (Filament) exposes a Failed Jobs resource with retry / discard actions. Horizon (optional) can replace Supervisor for richer real-time queue monitoring.

```php
// config/queue.php (relevant excerpt)
'failed' => [
    'driver'   => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'mysql'),
    'table'    => 'failed_jobs',
],
```

---

## 5. Multilingual Data Flow

### Data Model

MarkdownPress stores translations using `spatie/laravel-translatable`. The `posts` table contains a `translations` JSON column that holds per-locale strings. A separate `post_translations` table stores long-form content (title, body, excerpt) for each locale to avoid bloating the main row.

```
posts
├── id
├── slug                   -- canonical, locale-independent
├── status
├── content_markdown       -- source-of-truth (default locale)
├── content_html_cached
├── author_id
└── ...

post_translations
├── id
├── post_id (FK → posts.id)
├── locale                 -- e.g. "fr", "de", "ja"
├── title
├── excerpt
├── content_markdown       -- translated Markdown body
├── content_html_cached    -- rendered HTML for this locale
├── seo_title
├── seo_description
├── ai_translated          -- bool: populated by AI job
└── translated_at
```

### Language Switcher Flow

```
1. Reader on /en/blog/my-post clicks "Français"
        │
        ▼
2. GET /fr/blog/my-post
        │
        ▼
3. LocaleMiddleware: sets app()->setLocale('fr')
        │
        ▼
4. PostRepository::findPublishedBySlug('my-post', 'fr')
   ├── Loads Post model
   └── Eager-loads PostTranslation WHERE locale = 'fr'
        │
        ▼
5. If PostTranslation exists:
   ├── Use translated title, excerpt, content_markdown
   └── ContentRenderer renders French Markdown
   If NOT exists:
   ├── Fall back to default locale content
   └── Show "translation unavailable" notice banner
        │
        ▼
6. Cache stored at: post:{id}:html:fr
```

### AI Translation Job Flow

```
Admin triggers "Auto-translate to French"
        │
        ▼
TranslatePostJob dispatched to [ai] queue
        │
        ▼
1. Load Post with default-locale content_markdown
2. Call OpenAI::chat() with system prompt:
   "Translate the following Markdown blog post to French.
    Preserve all Markdown syntax and shortcode placeholders exactly."
3. Receive translated Markdown string
4. Upsert PostTranslation:
   INSERT INTO post_translations (post_id, locale, content_markdown, ai_translated, translated_at)
   VALUES (?, 'fr', ?, true, NOW())
   ON DUPLICATE KEY UPDATE ...
5. Dispatch RenderPostJob for the new locale
6. Fire PostTranslationCreatedEvent (triggers admin notification)
```

### URL Structure

```
/en/blog/{slug}      → English (default)
/fr/blog/{slug}      → French
/de/blog/{slug}      → German
/ja/blog/{slug}      → Japanese

hreflang meta tags generated automatically for all available translations.
```

---

## 6. Static Site Generation Flow

The `StaticSiteBuilder` service produces a complete, self-contained static HTML snapshot of the blog suitable for CDN hosting, offline archival, or Netlify/GitHub Pages deployment.

### Build Steps

```
1. php artisan markdownpress:static:build --env=production
   (or triggered via admin UI → dispatches StaticSiteBuildJob)
        │
        ▼
2. BuildRecord created in DB: {status: 'running', started_at: now()}
        │
        ▼
3. Collect all published posts (all locales)
   SELECT * FROM posts WHERE status = 'published'
   JOIN post_translations ...
        │
        ▼
4. For each Post × Locale:
   ├── ContentRenderer::render($post, $locale)  [uses/warms Redis cache]
   ├── Blade::render('theme::post.show', $viewModel)
   └── Write HTML to build/{locale}/blog/{slug}/index.html
        │
        ▼
5. Generate index / listing pages:
   ├── Page 1..N of post listing → build/{locale}/blog/page/{n}/index.html
   ├── Tag pages → build/{locale}/tag/{slug}/index.html
   ├── Category pages → build/{locale}/category/{slug}/index.html
   └── Author pages → build/{locale}/author/{username}/index.html
        │
        ▼
6. Generate auxiliary files:
   ├── sitemap.xml
   ├── feed.rss
   ├── feed.atom
   └── robots.txt
        │
        ▼
7. Copy compiled theme assets (CSS, JS, fonts, images)
   from public/themes/{name}/ → build/assets/
        │
        ▼
8. Copy media variants (WebP thumbnails) from storage
   → build/media/
        │
        ▼
9. Create ZIP archive: storage/builds/{build_id}.zip
        │
        ▼
10. Update BuildRecord: {status: 'complete', finished_at: now(), zip_path: ...}
        │
        ▼
11. Optional: push ZIP to S3, trigger CloudFront invalidation,
    or deploy via rsync/git push (configured in config/staticgen.php)
```

### Build Configuration

```php
// config/staticgen.php
return [
    'output_path'    => storage_path('builds'),
    'base_url'       => env('STATIC_BASE_URL', 'https://example.com'),
    'locales'        => env('STATIC_LOCALES', 'en'),
    'include_drafts' => false,
    'deploy' => [
        'driver'  => env('STATIC_DEPLOY_DRIVER', 'zip'), // zip | s3 | rsync
        'bucket'  => env('STATIC_S3_BUCKET'),
        'rsync_target' => env('STATIC_RSYNC_TARGET'),
    ],
];
```

---

## 7. Security Design

### 7.1 Post Password Protection (HMAC Cookies)

Password-protected posts use HMAC-signed cookies rather than session state, enabling stateless verification across load-balanced nodes.

```php
// PostPasswordController.php
public function verify(Request $request, Post $post): Response
{
    $input = $request->validated()['password'];

    if (! hash_equals($post->password_hash, Hash::make($input))) {
        return back()->withErrors(['password' => 'Incorrect password.']);
    }

    // Sign a cookie: HMAC(post_id + expiry, APP_KEY)
    $payload = $post->id . '|' . now()->addDays(7)->timestamp;
    $signature = hash_hmac('sha256', $payload, config('app.key'));
    $token = base64_encode($payload . '|' . $signature);

    return redirect()->back()->withCookie(
        cookie('pp_' . $post->id, $token, 60 * 24 * 7, secure: true, httpOnly: true, sameSite: 'Strict')
    );
}
```

Verification on subsequent requests checks the HMAC before serving content — no database round-trip required.

### 7.2 API Token Authentication

REST API endpoints use Laravel Sanctum token authentication. Tokens are scoped to specific abilities:

| Ability | Description |
|---------|-------------|
| `posts:read` | Read published and draft posts |
| `posts:write` | Create and update posts |
| `posts:delete` | Delete posts |
| `media:upload` | Upload media files |
| `ai:generate` | Trigger AI generation jobs |
| `build:trigger` | Trigger static site builds |

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'ability:posts:write'])->group(function () {
    Route::post('/posts', [ApiPostController::class, 'store']);
    Route::put('/posts/{post}', [ApiPostController::class, 'update']);
});
```

### 7.3 IP Whitelist Middleware

Admin panel routes and the `/api/internal/*` namespace are protected by `IpWhitelistMiddleware`, which compares `$request->ip()` against a configurable CIDR list:

```php
// config/security.php
'admin_ip_whitelist' => explode(',', env('ADMIN_IP_WHITELIST', '')),
// e.g. ADMIN_IP_WHITELIST=10.0.0.0/8,192.168.1.50

// IpWhitelistMiddleware.php
public function handle(Request $request, Closure $next): Response
{
    $whitelist = config('security.admin_ip_whitelist');

    if (! empty($whitelist) && ! $this->ipInList($request->ip(), $whitelist)) {
        abort(403, 'Access denied from this IP address.');
    }

    return $next($request);
}
```

### 7.4 CORS Configuration

API CORS is configured via `config/cors.php` (Laravel's built-in CorsServiceProvider). Only explicitly listed origins are permitted:

```php
// config/cors.php
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
'max_age'         => 7200,
'supports_credentials' => false,
```

### 7.5 SSRF-Safe Image Proxy

Shortcode `[embed url="..."]` and any user-supplied external image URLs are routed through `ImageProxyController`, which enforces an allowlist of permitted domains and blocks requests to private IP ranges before proxying:

```php
// ImageProxyController.php
public function proxy(Request $request): Response
{
    $url = $request->validated()['url'];
    $host = parse_url($url, PHP_URL_HOST);

    // Block RFC-1918 / loopback / link-local addresses
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        abort(403, 'Private or reserved IP addresses are not permitted.');
    }

    // Domain allowlist check
    $allowed = config('security.image_proxy_allowlist');
    if (! empty($allowed) && ! in_array($host, $allowed, true)) {
        abort(403, 'Domain not in proxy allowlist.');
    }

    $response = Http::timeout(5)->get($url);
    // ... stream response with Content-Type validation
}
```

### 7.6 Additional Security Measures

| Measure | Implementation |
|---------|---------------|
| CSRF protection | Laravel's built-in `VerifyCsrfToken` on all web routes |
| XSS prevention | Blade's `{{ }}` auto-escaping; HtmlPurifier for UGC |
| SQL injection | Eloquent parameterised queries; no raw string concatenation |
| Mass assignment | `$fillable` defined on all models; never `$guarded = []` |
| Sensitive config | All secrets in `.env`; never committed to version control |
| Security headers | `Permissions-Policy`, `X-Content-Type-Options`, `X-Frame-Options` via middleware |
| Rate limiting | Sanctum + `ThrottleRequests` middleware on all API routes |

---

## 8. Media Processing Pipeline

### Upload and Processing Flow

```
1. User uploads file via admin panel (Filament) or API
        │
        ▼
2. MediaController::store()
   ├── Validate: mime type (image/jpeg, image/png, image/gif, image/webp, video/mp4, ...)
   ├── Validate: max size (configurable, default 20 MB)
   └── Validate: no embedded PHP / script payloads (finfo_file + regex)
        │
        ▼
3. spatie/laravel-medialibrary: addMedia($file)->toMediaCollection('uploads')
   ├── Generates UUID filename
   ├── Moves to storage disk (local/s3)
   └── Inserts record in media table
        │
        ▼
4. MediaObserver::created() dispatches ProcessMediaJob to [media] queue
        │
        ▼
5. ProcessMediaJob::handle()
   ├── Load Media model
   ├── Validate image integrity (Intervention Image::make() — catches corrupt files)
   │
   ├── Step A: ConvertToWebPJob (chained)
   │   ├── Intervention\Image\Image::encode('webp', quality: 85)
   │   ├── Store as {uuid}-original.webp alongside original
   │   └── Insert MediaVariant {type: 'webp', width: original, height: original}
   │
   └── Step B: GenerateThumbnailsJob (chained after A)
       ├── For each size in config('media.thumbnail_sizes'):
       │   ├── ['name'=>'thumb',    'w'=>150,  'h'=>150,  'fit'=>'crop']
       │   ├── ['name'=>'small',    'w'=>400,  'h'=>null, 'fit'=>'width']
       │   ├── ['name'=>'medium',   'w'=>800,  'h'=>null, 'fit'=>'width']
       │   ├── ['name'=>'large',    'w'=>1200, 'h'=>null, 'fit'=>'width']
       │   └── ['name'=>'og-image', 'w'=>1200, 'h'=>630,  'fit'=>'crop']
       ├── Each variant: encode to WebP, store to disk
       └── Insert MediaVariant records for each size
```

### MediaVariant Schema

```sql
CREATE TABLE media_variants (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id    BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(50)  NOT NULL,  -- 'thumb', 'medium', 'webp', etc.
    path        VARCHAR(500) NOT NULL,  -- relative to storage disk root
    disk        VARCHAR(50)  NOT NULL,  -- 'local' or 's3'
    mime_type   VARCHAR(100) NOT NULL,
    width       SMALLINT UNSIGNED NULL,
    height      SMALLINT UNSIGNED NULL,
    size_bytes  INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
    INDEX idx_media_name (media_id, name)
);
```

### Serving Media

The `MediaController::show()` action resolves a `MediaVariant` by media ID and variant name, returning a signed temporary URL (for S3) or a streamed response (for local disk). Variant URLs are never guessable public paths — all access goes through the signed URL mechanism.

```php
// MediaController.php
public function show(int $mediaId, string $variant, Request $request): RedirectResponse|Response
{
    $mediaVariant = MediaVariant::where('media_id', $mediaId)
        ->where('name', $variant)
        ->firstOrFail();

    if (config('filesystems.default') === 's3') {
        $url = Storage::disk('s3')->temporaryUrl($mediaVariant->path, now()->addHours(2));
        return redirect()->away($url);
    }

    return Storage::disk('local')->response($mediaVariant->path);
}
```

### Configuration

```php
// config/media.php
return [
    'max_upload_size_kb' => env('MEDIA_MAX_UPLOAD_KB', 20480), // 20 MB
    'allowed_mimes'      => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'webp_quality'       => env('MEDIA_WEBP_QUALITY', 85),
    'thumbnail_sizes' => [
        ['name' => 'thumb',    'width' => 150,  'height' => 150,  'fit' => 'crop'],
        ['name' => 'small',    'width' => 400,  'height' => null, 'fit' => 'width'],
        ['name' => 'medium',   'width' => 800,  'height' => null, 'fit' => 'width'],
        ['name' => 'large',    'width' => 1200, 'height' => null, 'fit' => 'width'],
        ['name' => 'og-image', 'width' => 1200, 'height' => 630,  'fit' => 'crop'],
    ],
];
```
