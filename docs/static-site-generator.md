# Static Site Generator

## 1. Overview

The MarkdownPress Static Site Generator (SSG) transforms all published content into a self-contained, deployable static website. It renders every post, listing page, category, tag, and author page using the active theme, copies compiled assets, optimises images to WebP, generates a sitemap and robots.txt, and packages everything into a downloadable ZIP archive.

The output requires no PHP runtime — the resulting ZIP can be extracted and served from any static hosting provider (Netlify, GitHub Pages, Cloudflare Pages, S3) or a plain nginx/Apache server with no additional configuration.

Key properties of the build system:

- **Theme-faithful** — output is rendered through the same `ThemeRenderer` pipeline as the live site.
- **Memory-safe** — posts are collected in paginated batches to avoid loading the entire dataset into memory.
- **Auditable** — every build is recorded in the `static_builds` table with a full status history.
- **Async** — builds run as a queued job; real-time progress is broadcast to the admin panel via Laravel Echo.

---

## 2. CLI Command

```
php artisan blog:build {--theme=} {--output=}
```

### Signature

```php
// App\Console\Commands\BuildStaticSiteCommand

protected $signature = 'blog:build
                        {--theme= : Slug of the theme to use (defaults to active theme)}
                        {--output= : Absolute path for the build directory (defaults to storage/app/builds/{timestamp})}';

protected $description = 'Generate a complete static HTML website from all published content.';
```

### Options

| Option | Required | Default | Description |
|--------|----------|---------|-------------|
| `--theme` | No | Active theme | Theme slug to use for rendering; must exist in the `themes` table |
| `--output` | No | `storage/app/builds/{Y-m-d_His}` | Absolute path where the build directory is created before zipping |

### Example Usage

```bash
# Build with the active theme
php artisan blog:build

# Build with a specific theme
php artisan blog:build --theme=minimal-ink

# Build to a custom output directory
php artisan blog:build --output=/var/www/builds/my-build
```

### Output

On completion, the command prints:

```
Build completed.
  Posts rendered  : 142
  Images optimised: 318
  Total files     : 1,204
  ZIP size        : 48.3 MB
  ZIP path        : storage/app/builds/2025-01-15_143022.zip
```

On failure it prints the error and exits with code 1.

---

## 3. Build Orchestration Flow

The build is coordinated by `BuildOrchestrator`, which is invoked by both the CLI command and the queued `BuildJob`. The steps are strictly sequential.

### Step 1 — Validate Theme

```php
$theme = $themeRegistry->findBySlug($options['theme'] ?? null)
    ?? $themeRegistry->getActive();

if (!$theme) {
    throw new BuildException('No active theme found. Activate a theme before building.');
}
```

If `--theme` is supplied but the slug does not exist in the database, a `BuildException` is thrown and the process terminates before any files are written.

### Step 2 — Create BuildRecord

A `static_builds` row is inserted with `status = 'running'` and the current timestamp:

```php
$build = StaticBuild::create([
    'theme_slug' => $theme->slug,
    'status'     => 'running',
    'started_at' => now(),
    'output_path' => $outputPath,
]);
```

This record is used to broadcast real-time progress and to store final statistics.

### Step 3 — Collect Published Posts (Paginated)

Posts are loaded in chunks of 50 to avoid memory exhaustion on large sites:

```php
Post::published()
    ->with(['author', 'category', 'tags'])
    ->chunkById(50, function (Collection $posts) use ($builder, $build) {
        foreach ($posts as $post) {
            $builder->renderPost($post);
            $this->broadcastProgress($build, ++$this->renderedCount);
        }
    });
```

### Step 4 — Render Individual Posts

For each post, `ThemeRenderer::renderPost()` is called in static mode (which disables session and auth middleware). The HTML is written to `{outputPath}/{post-slug}/index.html`:

```php
private function renderPost(Post $post): void
{
    $html = $this->renderer->renderPostStatic($post);

    $path = $this->outputPath . "/{$post->slug}/index.html";

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $html);
}
```

### Step 5 — Render Listing Pages

The home page and all pagination pages are rendered:

```php
$totalPages = ceil(Post::published()->count() / $this->postsPerPage);

for ($page = 1; $page <= $totalPages; $page++) {
    $posts = Post::published()->forPage($page, $this->postsPerPage)->get();
    $html  = $this->renderer->renderIndexStatic($posts, $page, $totalPages);

    $path = $page === 1
        ? $this->outputPath . '/index.html'
        : $this->outputPath . "/page/{$page}/index.html";

    File::put($path, $html);
}
```

### Step 6 — Render Category Pages

```php
Category::withCount('publishedPosts')->get()->each(function (Category $category) {
    Post::published()->inCategory($category)
        ->chunkById(50, function (Collection $posts) use ($category) {
            // Renders /category/{slug}/index.html and /category/{slug}/page/N/index.html
            $this->renderPaginatedListing($posts, "category/{$category->slug}", $category);
        });
});
```

### Step 7 — Render Tag Pages

Identical pattern to categories, output at `tag/{slug}/index.html`.

### Step 8 — Render Author Pages

```php
User::hasPublishedPosts()->get()->each(function (User $author) {
    // Output at author/{username}/index.html
    $this->renderAuthorPage($author);
});
```

### Step 9 — Copy Theme Assets

The active theme's `assets/` directory is copied recursively to `{outputPath}/assets/`:

```php
File::copyDirectory(
    $theme->path . '/assets',
    $this->outputPath . '/assets'
);
```

Compiled application CSS and JS from `public/build/` (Vite manifest) are also copied to `assets/app/`.

### Step 10 — Optimise Images

All images referenced in the rendered HTML are detected, converted to WebP, and their paths rewritten. See [Section 7](#7-image-optimization-during-build) for full details.

### Step 11 — Generate sitemap.xml and robots.txt

```php
// sitemap.xml
$sitemap = SitemapBuilder::build(
    posts: Post::published()->get(),
    categories: Category::all(),
    tags: Tag::all(),
    baseUrl: config('app.url'),
);
File::put($this->outputPath . '/sitemap.xml', $sitemap);

// robots.txt
File::put($this->outputPath . '/robots.txt', "User-agent: *\nAllow: /\nSitemap: " . config('app.url') . "/sitemap.xml\n");
```

### Step 12 — ZIP the Build Directory

The entire output directory is zipped using PHP's `ZipArchive`:

```php
$zipPath = $this->outputPath . '.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->outputPath));
foreach ($files as $file) {
    if (!$file->isDir()) {
        $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($this->outputPath) + 1));
    }
}

$zip->close();
```

The original unzipped directory is then removed:

```php
File::deleteDirectory($this->outputPath);
```

### Step 13 — Update BuildRecord

```php
$build->update([
    'status'      => 'completed',
    'finished_at' => now(),
    'file_count'  => $this->fileCount,
    'image_count' => $this->imageCount,
    'zip_size'    => filesize($zipPath),
    'zip_path'    => $zipPath,
]);
```

If any step throws an unrecoverable exception, the catch block updates `status = 'failed'` with an `error_message` and re-throws for the queue to handle.

---

## 4. Queue Integration

For builds triggered from the admin panel, a `BuildJob` is dispatched to the `build` queue:

```php
// App\Jobs\BuildJob

class BuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries   = 1;   // No retries; builds are not idempotent mid-flight

    public function __construct(
        public readonly int    $buildId,
        public readonly string $themeSlug,
        public readonly string $outputPath,
    ) {
        $this->onQueue('build');
    }

    public function handle(BuildOrchestrator $orchestrator): void
    {
        $orchestrator->run(
            buildId:    $this->buildId,
            themeSlug:  $this->themeSlug,
            outputPath: $this->outputPath,
        );
    }
}
```

### Real-Time Progress Broadcasting

`BuildOrchestrator` fires `BuildProgressUpdated` events during the render loop. These are broadcast over the `build.{buildId}` channel using Laravel Echo + Pusher (or Reverb for self-hosted installs):

```php
// App\Events\BuildProgressUpdated

class BuildProgressUpdated implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [new PrivateChannel("build.{$this->buildId}")];
    }

    public function broadcastWith(): array
    {
        return [
            'rendered'  => $this->renderedCount,
            'total'     => $this->totalCount,
            'step'      => $this->currentStep,
            'percent'   => round(($this->renderedCount / max($this->totalCount, 1)) * 100),
        ];
    }
}
```

The Filament admin panel subscribes to this channel and updates a progress bar in real time.

---

## 5. BuildRecord Schema

The `static_builds` table mirrors the full lifecycle of each build:

```
static_builds
  id              bigint PK, auto-increment
  theme_slug      varchar(100)                  Theme used for this build
  status          enum('pending','running','completed','failed')
  started_at      timestamp nullable            Set when BuildJob begins
  finished_at     timestamp nullable            Set on completion or failure
  file_count      int unsigned nullable         Total files written to ZIP
  image_count     int unsigned nullable         Images converted to WebP
  zip_size        bigint unsigned nullable       ZIP file size in bytes
  zip_path        varchar(500) nullable          Absolute path to ZIP file
  error_message   text nullable                  Set on failure
  created_at      timestamp
  updated_at      timestamp
```

Indexes: `status`, `created_at` (for admin listing queries).

---

## 6. Output Directory Structure

The build directory has the following structure before it is zipped:

```
build/
├── index.html                          Home page (page 1)
├── page/
│   ├── 2/index.html                    Listing page 2
│   └── 3/index.html                    Listing page 3
├── {post-slug}/
│   └── index.html                      Single post
├── category/
│   └── {slug}/
│       ├── index.html                  Category listing page 1
│       └── page/2/index.html           Category listing page 2
├── tag/
│   └── {slug}/
│       └── index.html                  Tag listing page
├── author/
│   └── {username}/
│       └── index.html                  Author listing page
├── assets/
│   ├── css/
│   │   └── theme.css                   Theme stylesheet
│   ├── js/
│   │   └── theme.js                    Theme JavaScript
│   ├── fonts/
│   │   └── *.woff2                     Self-hosted fonts
│   └── images/
│       └── *.webp                      Optimised post images
├── sitemap.xml
└── robots.txt
```

All internal links in the rendered HTML use relative paths, ensuring the ZIP can be served from any subdirectory without configuration.

---

## 7. Image Optimization During Build

After all HTML files are written and before the ZIP is created, the image optimiser scans every rendered HTML file and processes referenced images.

### Detection

```php
// App\Services\Build\ImageOptimizer

public function processHtmlFile(string $htmlPath): void
{
    $html = File::get($htmlPath);

    preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $matches);

    foreach ($matches[1] as $src) {
        $optimisedSrc = $this->optimise($src, $htmlPath);
        $html = str_replace($src, $optimisedSrc, $html);
    }

    File::put($htmlPath, $html);
}
```

### Optimisation Steps

1. **Resolve source** — absolute URLs are downloaded to a local cache; relative paths are resolved against the build directory.
2. **Convert to WebP** — using the `intervention/image` library with quality 85:
   ```php
   Image::read($sourcePath)
       ->toWebp(quality: 85)
       ->save($webpPath);
   ```
3. **Resize** — images wider than 1600px are resized to max 1600px width, preserving aspect ratio.
4. **Rewrite path** — the `src` attribute in the HTML is replaced with the relative WebP path under `assets/images/`.
5. **Deduplicate** — a SHA-256 hash of the source URL is used as the filename, so the same image referenced in multiple posts is only downloaded and converted once.

### Unsupported Formats

SVG files are copied as-is (no conversion). GIF files are copied as-is to preserve animation. Only JPEG, PNG, and AVIF sources are converted to WebP.

---

## 8. Incremental Builds

> **Future consideration** — not implemented in v1.0.

Full rebuilds on large sites (1000+ posts) take several minutes. An incremental build strategy would:

1. Compare `Post.updated_at` against `StaticBuild.finished_at` from the last successful build.
2. Re-render only posts modified since the last build.
3. Re-render listing pages that reference those posts (index, affected category/tag pages).
4. Skip unchanged posts entirely, re-copying their existing HTML from the previous build ZIP.

This would require storing a content hash per rendered page and a manifest of which pages reference which posts. The `static_builds` table would need an additional `manifest` JSON column.

Tracking issue: `gh issue create --title "Incremental SSG builds"`.

---

## 9. Admin Panel Integration

Build management is handled by a dedicated Filament resource: **Appearance → Static Builds**.

### Triggering a Build

The **Build Site** button in the Filament toolbar opens a modal with:
- **Theme** — dropdown pre-populated with all registered themes; defaults to active theme.
- **Build** button — dispatches `BuildJob` and immediately navigates to the build detail page.

```php
// App\Filament\Actions\TriggerBuildAction

Action::make('build_site')
    ->label('Build Site')
    ->form([
        Select::make('theme_slug')
            ->label('Theme')
            ->options(Theme::pluck('name', 'slug'))
            ->default(fn() => Theme::where('is_active', true)->value('slug'))
            ->required(),
    ])
    ->action(function (array $data) {
        $outputPath = storage_path('app/builds/' . now()->format('Y-m-d_His'));

        $build = StaticBuild::create([
            'theme_slug'  => $data['theme_slug'],
            'status'      => 'pending',
            'output_path' => $outputPath,
        ]);

        BuildJob::dispatch($build->id, $data['theme_slug'], $outputPath);

        Notification::make()->title('Build started')->success()->send();
    });
```

### Build History Table

The build history is displayed as a Filament table with the following columns:

| Column | Description |
|--------|-------------|
| ID | Auto-increment build ID |
| Theme | Theme slug used |
| Status | Badge: `pending` (grey), `running` (blue spinner), `completed` (green), `failed` (red) |
| Files | `file_count` |
| Images | `image_count` |
| ZIP Size | Human-readable (e.g. `48.3 MB`) |
| Duration | `finished_at - started_at` |
| Date | `created_at` |
| Actions | Download ZIP, View Log, Delete |

### Real-Time Progress Bar

When a build is `running`, the detail page displays a progress bar that updates in real time via:

```js
// resources/js/filament/build-progress.js

Echo.private(`build.${buildId}`)
    .listen('BuildProgressUpdated', (event) => {
        document.getElementById('build-progress').style.width = `${event.percent}%`;
        document.getElementById('build-step').textContent = event.step;
        document.getElementById('build-count').textContent =
            `${event.rendered} / ${event.total} pages`;
    });
```

### ZIP Download

Completed builds show a **Download ZIP** button that streams the ZIP file through:

```php
// App\Filament\Pages\StaticBuildDetailPage

public function downloadZip(StaticBuild $build): StreamedResponse
{
    abort_unless($build->status === 'completed', 404);

    return response()->streamDownload(function () use ($build) {
        readfile($build->zip_path);
    }, basename($build->zip_path));
}
```

The ZIP is served directly from `storage/app/builds/` and is never publicly accessible via a URL — only authenticated admin users with the `manage_builds` permission can download it.
