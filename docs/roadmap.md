# Implementation Roadmap

This document outlines the phased delivery plan for MarkdownPress. Each phase is scoped to produce independently testable, shippable increments. Dependencies between phases are explicit so that parallel workstreams can be identified where possible.

---

## Phase 1 — Foundation (Week 1–2)

Establish the database layer, authentication system, user roles, and the skeleton of the Filament admin panel. All subsequent phases build on this foundation.

### Key Deliverables

- [ ] All database migrations authored and passing (`php artisan migrate:fresh`)
- [ ] Eloquent models created for all core entities with full relationship definitions
- [ ] Model factories and seeders for all models (minimum 50 fake posts, 5 users across roles)
- [ ] Laravel Breeze installed and customized (login, register, password reset flows)
- [ ] Two-Factor Authentication (TOTP) via `laravel/fortify` — setup, confirm, recovery codes
- [ ] `Role` enum or model (`admin`, `editor`, `author`) with a `HasRole` trait on `User`
- [ ] `EnsureUserHasRole` middleware registered and applied to role-gated routes
- [ ] `AdminPanelProvider` registered with navigation groups, branding, and auth guard
- [ ] Filament panel accessible at `/admin` for admin users; redirects non-admins
- [ ] Basic health-check route (`GET /up`) returning 200 OK

### Dependencies

None — this is the project root. All other phases depend on Phase 1.

### Test Coverage Expectations

- Unit tests for `HasRole` trait (can/cannot role checks)
- Unit tests for all model relationships (asserting foreign key constraints and eager loading)
- Feature tests for authentication flows (login, logout, password reset, 2FA setup)
- Feature test confirming `/admin` redirects unauthenticated users to `/admin/login`
- Database tests asserting all migrations are idempotent (up/down/up)

---

## Phase 2 — Content Core (Week 3–4)

Build the complete post management engine including CRUD, revision history, Markdown rendering, shortcode processing, and HTML caching. This phase delivers the functional heart of the CMS.

### Key Deliverables

- [ ] `PostService` — `create()`, `update()`, `delete()`, `clone()`, `publish()`, `schedule()`, `findBySlug()`
- [ ] Slug generation logic — unique within locale, with numeric suffix on collision (e.g., `my-post-2`)
- [ ] `RevisionService` — auto-save on each post update (stores serialized content snapshot), `restore($revisionId)` method
- [ ] Shortcode engine: `ShortcodeRegistry`, `ShortcodeParser` (regex-based tokenizer), `ShortcodeRenderer`
- [ ] All built-in shortcode handlers registered: `[gallery]`, `[video]`, `[audio]`, `[cta]`, `[toc]`, `[code lang=""]`, `[notice type=""]`, `[columns]`, `[post_list limit="" category=""]`
- [ ] Markdown rendering pipeline: `MarkdownRenderer` service using `league/commonmark` with GFM extension
- [ ] Shortcode integration: shortcodes are extracted from Markdown source before CommonMark processing, rendered independently, then spliced back into the HTML output
- [ ] `RenderCache` service: wraps `MarkdownRenderer`; caches output in Redis under key `render:{post_id}:{locale}:{updated_at_hash}`; invalidated on post update or shortcode change
- [ ] Post list endpoints (public API, no auth): paginated, filtered by status=published

### Dependencies

- Phase 1 (models, migrations, auth)

### Test Coverage Expectations

- Unit tests for `ShortcodeParser` (all built-in shortcodes, malformed input, nested shortcodes)
- Unit tests for `MarkdownRenderer` (basic Markdown, GFM tables, fenced code blocks, XSS prevention — ensure `<script>` tags in Markdown source are stripped)
- Unit tests for `PostService` slug collision resolution
- Unit tests for `RevisionService` restore (asserts post content equals revision snapshot)
- Feature tests for post CRUD (create → read → update → delete cycle)
- Feature test for HTML cache hit/miss (assert Redis key existence before and after cache invalidation)

---

## Phase 3 — Media (Week 5)

Implement the full media upload and processing pipeline, admin media library, and stock photo integrations. Delivers functional image management for use in posts from Phase 2.

### Key Deliverables

- [ ] `POST /api/v1/media` endpoint with MIME + size validation and Spatie MediaLibrary storage
- [ ] `MediaProcessingJob` queued on `media` queue — WebP conversion, thumb (300×300), medium (800px) variants via Intervention Image
- [ ] `MediaVariant` model and migration
- [ ] Audio and video handling in `MediaProcessingJob` (copy + optional `ffprobe` duration extraction)
- [ ] `MediaResource` in Filament — grid layout, thumbnail display, filter by type/date/starred
- [ ] Star/unstar, copy URL, delete, and crop actions in `MediaResource`
- [ ] `MediaCropJob` — reads original file, applies crop params via Intervention Image, saves new `cropped_N` variant
- [ ] `StockPhotoServiceInterface` and four drivers: `UnsplashDriver`, `PixabayDriver`, `PexelsDriver`, `FreepikDriver`
- [ ] `SsrfGuard` utility with all protection checks (scheme, domain allowlist, IP resolution, redirect limits)
- [ ] Stock photo import action — downloads image through `SsrfGuard`, runs through upload pipeline
- [ ] `config/media.php` configuration file
- [ ] `/media/{id}/{variant}` named route for variant URL resolution
- [ ] `storage:link` documented in deployment instructions

### Dependencies

- Phase 1 (auth, models)
- Phase 2 (post model, for `featured_image` FK)

### Test Coverage Expectations

- Unit tests for `SsrfGuard` (valid URL, loopback IP, private IP, metadata IP, untrusted domain, redirect chain)
- Unit tests for each `StockPhotoDriver` with mocked HTTP responses
- Feature tests for `POST /api/v1/media` (valid upload, oversized file, invalid MIME, unauthenticated)
- Feature test for `MediaProcessingJob` — asserts WebP variant file is created and `MediaVariant` record exists
- Feature test for stock photo import — mocks HTTP client, asserts media record created

---

## Phase 4 — Multilingual + AI (Week 6–7)

Add multilingual post support and the full AI service layer. Delivers translation management and AI content tools that operate on posts from Phase 2.

### Key Deliverables

- [ ] `PostTranslation` model and migration (`post_id`, `locale`, `title`, `slug`, `content_markdown`, `meta_title`, `meta_description`)
- [ ] `PostTranslationService` — `create($postId, $locale, $data)`, `update()`, `delete()`
- [ ] Language switcher on frontend (generates locale-prefixed URLs: `/fr/post-slug`)
- [ ] Locale middleware that sets `App::setLocale()` from URL prefix
- [ ] Per-language font system: `LanguageFontMap` config (`config/fonts.php`) mapping locale codes to Google Fonts families
- [ ] `AiServiceInterface` with `summarize(string $content): string`, `generateExcerpt(string $content): string`, `translate(string $content, string $targetLocale): string`
- [ ] `GeminiDriver` implementing `AiServiceInterface` (Gemini 1.5 Flash via REST API)
- [ ] `OpenAiDriver` implementing `AiServiceInterface` (GPT-4o mini)
- [ ] `AnthropicDriver` implementing `AiServiceInterface` (Claude 3 Haiku)
- [ ] `AiDriverManager` — resolves driver from settings, validates API key presence before dispatch
- [ ] `GenerateSummaryJob`, `GenerateExcerptJob`, `TranslatePostJob` — queued on `ai` queue
- [ ] AI action results stored back on the respective post/translation record
- [ ] `AIToolsPage` in Filament (post selector, driver selector, action buttons, progress polling, output preview)

### Dependencies

- Phase 1 (auth, user model)
- Phase 2 (Post model, PostService)
- Phase 3 (media, for featured image on translations)

### Test Coverage Expectations

- Unit tests for `PostTranslationService` (create, update, delete; locale uniqueness constraint)
- Unit tests for `AiDriverManager` (correct driver resolved, exception when API key missing)
- Unit tests for each AI driver with mocked HTTP responses (success, rate-limit response, API error)
- Feature tests for `TranslatePostJob` — asserts `PostTranslation` record created with correct locale
- Feature tests for `GenerateSummaryJob` — asserts post `summary` field updated after job runs
- Feature test for locale middleware — asserts `App::getLocale()` set correctly from URL

---

## Phase 5 — Theme Engine (Week 8)

Build the theme registry, rendering pipeline, and initial set of bundled themes. Provides the visual layer for the static site generator in Phase 6.

### Key Deliverables

- [ ] `ThemeRegistry` — discovers themes from `resources/themes/` directory, validates `theme.json` manifests
- [ ] `theme.json` schema: `key`, `name`, `author`, `version`, `screenshot`, `supports` (features array), `colors` (default palette), `fonts` (per-locale overrides)
- [ ] `ThemeRenderer` service — resolves active theme, renders post/list/page templates using Blade
- [ ] Theme Blade layout inheritance: themes extend `themes::{key}::layout` which wraps `themes::{key}::partials.header` and `themes::{key}::partials.footer`
- [ ] Color customization system: theme-level CSS custom properties generated from `theme_settings` record and injected into `<style>` tag in `<head>`
- [ ] Per-language font overrides: `LanguageFontMap` feeds into theme's `<head>` Google Fonts `<link>` element
- [ ] Three bundled starter themes:
  - `hello-world` — single-column minimal blog, clean typography
  - `developer` — dark mode default, code-focused layout, syntax-highlighted code blocks
  - `monolith` — magazine-style with sidebar, category widgets, featured post hero
- [ ] `ThemeResource` in Filament — card grid, Activate action, Customize Colors modal, Preview link
- [ ] `Theme` model and `theme_settings` table for per-theme color customization persistence

### Dependencies

- Phase 1 (settings, models)
- Phase 2 (Markdown rendering pipeline, shortcode renderer)
- Phase 3 (media serving for featured images)
- Phase 4 (language font map)

### Test Coverage Expectations

- Unit tests for `ThemeRegistry` (discovers valid theme, rejects theme with malformed `theme.json`, lists all themes)
- Unit tests for `ThemeRenderer` (renders correct template for post, list, and static page views)
- Unit tests for color customization (CSS custom properties output from `theme_settings` record)
- Feature test for "Activate" action — asserts `settings.active_theme` updated

---

## Phase 6 — Static Site Generator (Week 9)

Implement the full static site generation pipeline, including all route crawling, asset bundling, and ZIP export. Consumes the theme engine from Phase 5.

### Key Deliverables

- [ ] `BlogBuildCommand` (`php artisan blog:build --theme= --output=`) as the CLI entry point
- [ ] `BuildOrchestrator` service — enumerates all pages to build (post list, all published posts, category pages, tag pages, static pages, sitemap, RSS feed, 404 page)
- [ ] `BuildJob` — renders a single page via `ThemeRenderer`, writes HTML to the build output directory
- [ ] `BuildProgressTracker` — updates `static_builds.pages_built` and `static_builds.current_step` during build
- [ ] `StaticBuildProgressEvent` — broadcast event (implements `ShouldBroadcast`) for real-time progress
- [ ] Asset pipeline: copies `public/themes/{key}/` CSS/JS/images into the build output directory; rewrites relative asset URLs
- [ ] ZIP export: compresses the output directory using PHP `ZipArchive`; stores in `storage/app/builds/`
- [ ] `StaticBuild` model and `static_builds` migration
- [ ] `StaticBuildsPage` in Filament — trigger modal, progress bar, build history table, download/delete actions
- [ ] `OrchestratorJob` dispatched to `builds` queue; child `BuildJob` instances dispatched as a job chain

### Dependencies

- Phase 1 (models, auth)
- Phase 2 (PostService, content rendering)
- Phase 3 (media serving, asset URLs)
- Phase 4 (multilingual routes)
- Phase 5 (ThemeRegistry, ThemeRenderer)

### Test Coverage Expectations

- Unit tests for `BuildOrchestrator` page enumeration (asserts correct set of URLs for a seeded blog)
- Unit tests for asset URL rewriting (absolute → relative path transformation)
- Feature test for `BlogBuildCommand` (runs against seeded database, asserts output directory contains expected HTML files)
- Feature test for ZIP export (asserts ZIP file created in `storage/app/builds/` and is non-empty)
- Feature test for `StaticBuild` status progression (queued → building → done)

---

## Phase 7 — Admin Panel Completion (Week 10–11)

Build out all remaining Filament resources and pages that depend on the core features delivered in earlier phases.

### Key Deliverables

- [ ] `PostResource` fully implemented (all list columns, filters, table actions, full form with all sidebar sections)
- [ ] `CategoryResource` and `TagResource` with hierarchical category support
- [ ] `CommentResource` with approve/reject/delete moderation actions and nested reply display
- [ ] `SubscriberResource` (newsletter subscribers list, export to CSV action, unsubscribe action)
- [ ] `AnalyticsDashboard` — stats widgets (views, visitors, new posts, subscriber growth), line chart, top posts table, top countries table
- [ ] `GeneralSettingsPage`, `SeoSettingsPage`, `AiSettingsPage` — all form fields persisted to `settings` table
- [ ] `ApiTokenResource` — list/revoke/create Sanctum tokens; show token once on creation
- [ ] `UserResource` — list, create, edit (name, email, role, avatar), impersonate action (admin only)
- [ ] `WordPressImportPage` — file upload (WXR XML), import progress bar, import summary (posts/pages/media imported vs. skipped)
- [ ] `JsonExportImportPage` — export all posts to JSON, import from JSON with conflict resolution (skip/overwrite)
- [ ] `WordPressImporter` service — parses WXR XML, maps WP post fields to MarkdownPress schema, converts HTML content to Markdown via `league/html-to-markdown`
- [ ] `JsonExporter` and `JsonImporter` services

### Dependencies

- Phase 1 (auth, user roles)
- Phase 2 (posts, categories, tags)
- Phase 3 (media)
- Phase 4 (AI tools page, translations)
- Phase 5 (theme resource)
- Phase 6 (static builds page)

### Test Coverage Expectations

- Feature tests for `CommentResource` moderation actions (approve changes status, reject fires notification)
- Feature tests for `WordPressImporter` (parses fixture WXR file, asserts correct post count imported)
- Feature tests for `JsonExporter` / `JsonImporter` round-trip (export → import → assert all posts match)
- Feature tests for `GeneralSettingsPage` save (asserts `settings` table updated)
- Feature tests for `ApiTokenResource` create/revoke

---

## Phase 8 — REST API (Week 12)

Expose all public and authenticated API endpoints, implement API token management, and document the API surface.

### Key Deliverables

- [ ] All public endpoints (no auth required):
  - `GET /api/v1/posts` — paginated, filterable by category, tag, locale
  - `GET /api/v1/posts/{slug}` — single post with all relationships
  - `GET /api/v1/categories` — full category tree
  - `GET /api/v1/tags`
  - `GET /api/v1/pages/{slug}`
  - `GET /api/v1/media/{id}/{variant}` — variant URL redirect
- [ ] All protected endpoints (`auth:sanctum`):
  - `POST /api/v1/posts`, `PUT /api/v1/posts/{id}`, `DELETE /api/v1/posts/{id}`
  - `POST /api/v1/media`, `DELETE /api/v1/media/{id}`
  - `POST /api/v1/comments`, `DELETE /api/v1/comments/{id}`
  - `POST /api/v1/newsletter/subscribe`, `DELETE /api/v1/newsletter/unsubscribe`
  - `GET/POST /api/v1/ai/generate` (summary, excerpt, translate)
  - `POST /api/v1/builds/trigger`
- [ ] `ApiTokenResource` in Filament for token issuance and revocation (Phase 7)
- [ ] `IpWhitelistMiddleware` — optional per-token IP allowlist stored in `personal_access_tokens.ip_whitelist` (JSON column)
- [ ] CORS configuration in `config/cors.php` — `paths: ['api/*']`, configurable origin allowlist
- [ ] API rate limiting: `ThrottleRequests` middleware — 60 req/min (unauthenticated), 300 req/min (authenticated)
- [ ] Consistent JSON API error responses (`{"error": {"code": "...", "message": "...", "details": {...}}}`)
- [ ] API documentation generated via [Scribe](https://scribe.knuckles.wtf/) (`php artisan scribe:generate`) — published at `/docs/api`

### Dependencies

- Phase 1 (auth, Sanctum)
- Phase 2 (PostService)
- Phase 3 (media)
- Phase 4 (translations, AI)
- Phase 6 (static builds trigger)

### Test Coverage Expectations

- Feature tests for all public endpoints (200 with correct shape, 404 for missing resources)
- Feature tests for all protected endpoints (401 without token, 403 with insufficient role, 200/201 with valid token)
- Feature test for `IpWhitelistMiddleware` (request from allowed IP passes, blocked IP returns 403)
- Feature tests for rate limiting (assert 429 after exceeding limit)
- Feature test confirming CORS headers present on `api/*` routes

---

## Phase 9 — Security Hardening (Week 13)

Harden all authentication flows, protect sensitive operations, and ensure secure handling of media fetching and access control.

### Key Deliverables

- [ ] HMAC-signed password cookies for protected posts — cookie value is `HMAC-SHA256(post_id + expires, APP_KEY)`; verified in `PostPasswordMiddleware`
- [ ] TOTP-based 2FA via `laravel/fortify` fully wired up: enable/disable flow, QR code setup, TOTP verification on login, recovery code generation and download
- [ ] OTP (one-time password) login: generates a 6-digit code, emails it to the user, valid for 10 minutes; implemented as an additional Fortify guard
- [ ] Magic link login: generates a signed URL (`URL::temporarySignedRoute()`), emails it to the user, valid for 30 minutes
- [ ] `SsrfGuard` fully implemented and applied to all external URL fetches (stock photos, any webhook or remote-fetch feature)
- [ ] Rate limiting per route group (see Phase 8); additional limits on auth routes: 5 login attempts per minute per IP
- [ ] `Content-Security-Policy` header configured via middleware (blocks inline scripts except Filament; CDN allowlist for fonts/images)
- [ ] `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` headers added globally
- [ ] Audit log: `ActivityLog` model records all admin actions (model, action, actor, IP, before/after JSON) — powered by `spatie/laravel-activitylog`
- [ ] Security review of all form inputs for XSS — Markdown output sanitized via HTML Purifier before storage and on render

### Dependencies

- Phase 1 (auth, User model)
- Phase 2 (Post model, post password feature)
- Phase 3 (media, `SsrfGuard`)
- Phase 8 (API, rate limiting infrastructure)

### Test Coverage Expectations

- Unit tests for `PostPasswordMiddleware` HMAC verification (valid cookie, expired cookie, tampered cookie)
- Feature tests for TOTP 2FA (setup flow, login with valid TOTP, login with invalid TOTP, recovery code consumption)
- Feature tests for magic link (link generated, link works within TTL, link rejected after expiry)
- Feature tests for OTP login (code sent, valid code accepted, expired code rejected)
- Unit tests for `SsrfGuard` (all protection scenarios — see Phase 3)
- Feature test confirming security headers present on all responses

---

## Phase 10 — Performance + Production (Week 14)

Optimize throughput, integrate CDN, configure production queue workers, and establish operational readiness.

### Key Deliverables

- [ ] Redis HTML render cache fully operational for all public post and list pages (`RenderCache` service from Phase 2); cache TTL configurable in `config/cache.php`
- [ ] Cache warming command: `php artisan cache:warm-posts` — pre-renders all published posts into Redis on deploy
- [ ] Queue optimization: dedicated queue workers per queue (`default`, `media`, `ai`, `builds`, `notifications`) configured in `config/horizon.php`
- [ ] Laravel Horizon installed and configured — supervisor processes defined, queue metrics dashboard at `/horizon` (admin-only)
- [ ] CDN integration: `AWS_CLOUDFRONT_URL` environment variable; all media URLs resolved through CloudFront when set; cache-busting via `media.updated_at` query param
- [ ] S3 storage backend fully tested with media upload, variant storage, and pre-signed URL generation
- [ ] `php artisan optimize` run in deployment pipeline (config/route/view cache)
- [ ] Health check endpoints:
  - `GET /up` — basic 200 OK (already in Phase 1)
  - `GET /health` — JSON report: database connectivity, Redis connectivity, queue worker status, storage disk free space
- [ ] Database query optimization: all frequently-filtered columns indexed (see `database-schema.md`); N+1 queries eliminated with eager loading across all Filament resources
- [ ] Telescope integration (local/staging only): request logging, query inspection, job monitoring
- [ ] Deployment checklist documented in `docs/deployment.md`: env variables, `storage:link`, `migrate --force`, `optimize`, Horizon start, queue worker supervision via Supervisor

### Dependencies

- All previous phases (this phase optimizes the complete system)

### Test Coverage Expectations

- Performance benchmark: `php artisan route:list` passes; average response time for `GET /api/v1/posts` under 200ms with cache warm (measured with `ab` or `wrk` in CI)
- Feature test for `GET /health` — asserts all checks return `"status": "ok"` in CI environment
- Feature test for cache warming command — asserts Redis keys exist after `cache:warm-posts` runs
- Integration test for S3 media storage — runs against MinIO in Docker if available in CI; otherwise mocked
- Regression test suite (all prior phase tests must continue to pass at 100%)

---

## Summary Timeline

| Phase | Weeks  | Focus                         | Blockers           |
|-------|--------|-------------------------------|--------------------|
| 1     | 1–2    | Foundation                    | None               |
| 2     | 3–4    | Content Core                  | Phase 1            |
| 3     | 5      | Media                         | Phases 1–2         |
| 4     | 6–7    | Multilingual + AI             | Phases 1–3         |
| 5     | 8      | Theme Engine                  | Phases 1–4         |
| 6     | 9      | Static Site Generator         | Phases 1–5         |
| 7     | 10–11  | Admin Panel Completion        | Phases 1–6         |
| 8     | 12     | REST API                      | Phases 1–7         |
| 9     | 13     | Security Hardening            | Phases 1–8         |
| 10    | 14     | Performance + Production      | All phases         |

**Total estimated duration:** 14 weeks (one engineer, full-time). With two engineers, Phases 4 and 5 can run in parallel (after Phase 3 completes), reducing total duration to approximately 11 weeks.
