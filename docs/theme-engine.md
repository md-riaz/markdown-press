# Theme Engine

## 1. Overview

MarkdownPress uses a Blade-based theme engine designed around the principle of complete separation between content and presentation. Each theme is a self-contained directory under `resources/themes/{theme-slug}/`, containing its own views, assets, configuration, and metadata.

The engine resolves the active theme at request time, configures the Blade view resolver to load templates from the theme directory, injects a standardised set of variables into every view, and applies any stored colour or font customisations as CSS custom properties. Themes have no awareness of the database layer — they consume only the variables injected by `ThemeRenderer`.

This architecture means:
- Themes can be swapped without touching any application logic.
- A theme can be developed in isolation and dropped into `resources/themes/` without modifying any service provider or config file.
- Per-theme customisations (colours, fonts, layout toggles) are stored in the database and applied at render time, so the theme source files remain pristine.

---

## 2. Theme Directory Structure

```
resources/themes/hello-world/
├── theme.json                  # Metadata and default configuration
├── screenshot.png              # 1280×960 preview image shown in admin
├── views/
│   ├── layout.blade.php        # Master layout (wrapped by all other views)
│   ├── post.blade.php          # Single post page
│   ├── index.blade.php         # Post listing / home page
│   ├── category.blade.php      # Posts filtered by category
│   ├── tag.blade.php           # Posts filtered by tag
│   ├── author.blade.php        # Posts filtered by author
│   └── 404.blade.php           # Not-found page
└── assets/
    ├── css/
    │   └── theme.css           # Compiled stylesheet (references CSS custom properties)
    ├── js/
    │   └── theme.js            # Optional theme-specific JavaScript
    └── fonts/
        └── *.woff2             # Self-hosted font files (optional)
```

### File Responsibilities

| File | Responsibility |
|------|---------------|
| `theme.json` | Declares metadata, default colours, fonts, and layout options |
| `layout.blade.php` | Renders `<html>`, `<head>` (injected CSS vars + Google Fonts), `<body>`, nav, footer; yields a `@section('content')` slot |
| `post.blade.php` | Extends layout; renders a single `$post` including title, body, metadata, author block |
| `index.blade.php` | Extends layout; iterates `$posts` with pagination controls |
| `category.blade.php` | Extends layout; renders filtered post listing with category header |
| `tag.blade.php` | Extends layout; renders filtered post listing with tag header |
| `author.blade.php` | Extends layout; renders author profile and their published posts |
| `404.blade.php` | Extends layout; standalone not-found page |
| `screenshot.png` | Static preview image; must be exactly 1280×960 px |

---

## 3. theme.json Schema

`theme.json` is the single source of truth for a theme's identity and default configuration. All fields are required unless marked optional.

```json
{
  "name": "Hello World",
  "slug": "hello-world",
  "description": "A clean, typography-first theme for long-form writing.",
  "version": "1.0.0",
  "author": {
    "name": "MarkdownPress Team",
    "url": "https://markdownpress.dev"
  },
  "colors": {
    "primary":    "#1a56db",
    "accent":     "#e3a008",
    "background": "#ffffff",
    "text":       "#111827",
    "border":     "#e5e7eb"
  },
  "fonts": {
    "heading_family": "Inter",
    "body_family":    "Merriweather",
    "code_family":    "JetBrains Mono"
  },
  "layout": {
    "sidebar":    false,
    "full_width": false
  }
}
```

### Field Reference

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Human-readable display name |
| `slug` | string | Directory name; must match the directory slug exactly; lowercase, hyphenated |
| `description` | string | Short description shown in the admin theme gallery |
| `version` | string | SemVer string |
| `author.name` | string | Author display name |
| `author.url` | string (optional) | Author website URL |
| `colors.primary` | hex string | Primary brand colour (links, buttons, active states) |
| `colors.accent` | hex string | Secondary highlight colour |
| `colors.background` | hex string | Page background colour |
| `colors.text` | hex string | Body text colour |
| `colors.border` | hex string | Border and divider colour |
| `fonts.heading_family` | string | Google Fonts family name for headings |
| `fonts.body_family` | string | Google Fonts family name for body text |
| `fonts.code_family` | string | Google Fonts / self-hosted family name for code blocks |
| `layout.sidebar` | bool | Whether the theme renders a sidebar by default |
| `layout.full_width` | bool | Whether posts render in full-width (no max-width container) by default |

---

## 4. ThemeRegistry

`ThemeRegistry` is responsible for discovering themes on disk, persisting their metadata to the database, and tracking the currently active theme.

### Discovery

On invocation of `php artisan themes:sync` (or automatically during `php artisan blog:install`), `ThemeRegistry::discover()` scans `resource_path('themes')` for subdirectories that contain a valid `theme.json`. Each discovered theme is upserted into the `themes` table keyed by `slug`.

```php
// App\Services\Theme\ThemeRegistry

public function discover(): Collection
{
    $themesPath = resource_path('themes');

    return collect(File::directories($themesPath))
        ->filter(fn($dir) => File::exists($dir . '/theme.json'))
        ->map(fn($dir) => $this->upsertFromDirectory($dir));
}

private function upsertFromDirectory(string $dir): Theme
{
    $config = json_decode(File::get($dir . '/theme.json'), associative: true);

    return Theme::updateOrCreate(
        ['slug' => $config['slug']],
        [
            'name'        => $config['name'],
            'description' => $config['description'],
            'version'     => $config['version'],
            'author'      => $config['author']['name'],
            'config'      => $config,
            'path'        => $dir,
        ]
    );
}
```

### Activation

Only one theme can be active at a time. `ThemeRegistry::activate(string $slug)` sets `is_active = true` on the target record and `is_active = false` on all others within a single transaction. It then clears the theme config cache.

```php
public function activate(string $slug): void
{
    DB::transaction(function () use ($slug) {
        Theme::query()->update(['is_active' => false]);
        Theme::where('slug', $slug)->update(['is_active' => true]);
    });

    Cache::forget('active_theme');
}
```

### Active Theme Resolution

```php
public function getActive(): Theme
{
    return Cache::rememberForever('active_theme', fn() =>
        Theme::where('is_active', true)->firstOrFail()
    );
}
```

---

## 5. ThemeRenderer

`ThemeRenderer` is the central rendering service. It is bound as a singleton in `ThemeServiceProvider` and is consumed by all web controllers.

### View Path Resolution

When `ThemeRenderer::boot()` is called (in `ThemeServiceProvider::boot()`), it prepends the active theme's `views/` directory to the Blade finder's paths. This means `view('post')` resolves to `resources/themes/{active-slug}/views/post.blade.php` before checking the application's default `resources/views/` directory.

```php
// App\Services\Theme\ThemeRenderer

public function boot(): void
{
    $theme = $this->registry->getActive();

    $this->finder->prependLocation(
        $theme->path . '/views'
    );

    $this->activeTheme = $theme;
}
```

### Rendering a Post

Controllers do not call `view()` directly. Instead they delegate to `ThemeRenderer::renderPost(Post $post)`:

```php
public function renderPost(Post $post): Response
{
    $config = $this->configLoader->load($this->activeTheme);

    return response()->view('post', [
        'post'     => $post,
        'settings' => $this->settingsRepository->all(),
        'theme'    => $config,
        'locale'   => App::getLocale(),
    ]);
}
```

Similar methods exist for `renderIndex`, `renderCategory`, `renderTag`, `renderAuthor`, and `render404`.

---

## 6. Theme Variables Available in Blade

Every theme view receives the following variables. Controllers must not inject additional ad-hoc variables; all data must flow through `ThemeRenderer`.

| Variable | Type | Description |
|----------|------|-------------|
| `$post` | `Post\|null` | The current post object (set on single-post views) |
| `$posts` | `LengthAwarePaginator\|null` | Paginated post collection (set on listing views) |
| `$category` | `Category\|null` | Current category (set on category views) |
| `$tag` | `Tag\|null` | Current tag (set on tag views) |
| `$author` | `User\|null` | Current author (set on author views) |
| `$settings` | `array` | Flat associative array of all `site_settings` rows (e.g. `$settings['site_name']`) |
| `$theme` | `ThemeConfig` | Value object holding merged colours, fonts, and layout config |
| `$locale` | `string` | Active ISO 639-1 locale code (e.g. `'en'`, `'ar'`, `'zh'`) |

### ThemeConfig Value Object

```php
readonly class ThemeConfig
{
    public function __construct(
        public string $slug,
        public string $name,
        public ThemeColors $colors,
        public ThemeFonts  $fonts,
        public ThemeLayout $layout,
    ) {}
}
```

Access in Blade:

```blade
{{ $theme->colors->primary }}
{{ $theme->fonts->headingFamily }}
@if ($theme->layout->sidebar) ... @endif
```

---

## 7. Color Customization

Per-theme colour overrides are stored in the `theme_customizations` table:

```
theme_customizations
  id              bigint PK
  theme_slug      varchar(100) FK → themes.slug
  key             varchar(100)   (e.g. "colors.primary")
  value           varchar(255)
  created_at, updated_at
```

At render time, `ThemeConfigLoader` merges these database values over the defaults from `theme.json`. `ThemeRenderer` then serialises the resolved colours as CSS custom properties and injects them into the `<head>` section via the `@stack('theme-vars')` Blade stack (pushed from `layout.blade.php`).

```php
// ThemeRenderer::buildCssVars(ThemeConfig $config): string

private function buildCssVars(ThemeConfig $config): string
{
    $c = $config->colors;

    return <<<CSS
    <style>
    :root {
      --color-primary:    {$c->primary};
      --color-accent:     {$c->accent};
      --color-background: {$c->background};
      --color-text:       {$c->text};
      --color-border:     {$c->border};
    }
    </style>
    CSS;
}
```

Theme stylesheets reference only `var(--color-primary)` etc., so customisations apply universally without modifying any CSS file.

---

## 8. Per-Language Fonts

MarkdownPress supports multilingual content. Certain locales require scripts not covered by the default Latin-subset Google Fonts URL (e.g. Arabic, Chinese, Japanese). Font configuration is resolved in a two-step process:

1. **Base fonts** — loaded from `theme.json` `fonts` block (or DB override).
2. **Locale override** — if a record exists in `theme_customizations` for keys `fonts.heading_family__{locale}`, `fonts.body_family__{locale}`, or `fonts.code_family__{locale}`, those values replace the base fonts for the current request.

The Google Fonts URL is generated lazily by `GoogleFontsUrlBuilder`:

```php
// App\Services\Theme\GoogleFontsUrlBuilder

public function build(ThemeFonts $fonts, string $locale): string
{
    $families = array_unique([
        $fonts->headingFamily,
        $fonts->bodyFamily,
        $fonts->codeFamily,
    ]);

    $subset = $this->subsetForLocale($locale); // e.g. 'latin', 'arabic', 'chinese-simplified'

    $query = collect($families)
        ->map(fn($f) => 'family=' . urlencode($f) . ':wght@400;600;700')
        ->implode('&');

    return "https://fonts.googleapis.com/css2?{$query}&subset={$subset}&display=swap";
}
```

The URL is injected into `<head>` as a `<link rel="preconnect">` + `<link rel="stylesheet">` pair. For RTL locales (`ar`, `he`, `fa`, `ur`), `ThemeRenderer` also adds `dir="rtl"` to the `<html>` tag via the `$locale` variable.

---

## 9. ThemeConfigLoader

`ThemeConfigLoader` is responsible for reading `theme.json`, fetching any stored customisations, merging them, and returning a fully resolved `ThemeConfig` value object. The result is cached per theme slug until the customisation cache is explicitly invalidated.

```php
// App\Services\Theme\ThemeConfigLoader

public function load(Theme $theme): ThemeConfig
{
    return Cache::remember("theme_config_{$theme->slug}", now()->addHour(), function () use ($theme) {
        $defaults     = $theme->config; // decoded theme.json stored in DB
        $overrides    = $this->fetchOverrides($theme->slug);
        $merged       = array_replace_recursive($defaults, $overrides);

        return new ThemeConfig(
            slug:   $merged['slug'],
            name:   $merged['name'],
            colors: new ThemeColors(...$merged['colors']),
            fonts:  new ThemeFonts(
                headingFamily: $merged['fonts']['heading_family'],
                bodyFamily:    $merged['fonts']['body_family'],
                codeFamily:    $merged['fonts']['code_family'],
            ),
            layout: new ThemeLayout(
                sidebar:   $merged['layout']['sidebar'],
                fullWidth: $merged['layout']['full_width'],
            ),
        );
    });
}

private function fetchOverrides(string $slug): array
{
    return ThemeCustomization::where('theme_slug', $slug)
        ->get()
        ->reduce(function (array $carry, ThemeCustomization $row) {
            data_set($carry, $row->key, $row->value); // e.g. key="colors.primary"
            return $carry;
        }, []);
}
```

When an admin saves a customisation, `ThemeCustomizationSaved` event is dispatched, which triggers a listener that calls `Cache::forget("theme_config_{$slug}")` and `Cache::forget('active_theme')`.

---

## 10. Adding a New Theme

Follow these steps to create and register a new theme.

### Step 1 — Scaffold the directory

```bash
mkdir -p resources/themes/my-theme/{views,assets/css,assets/js,assets/fonts}
```

### Step 2 — Create theme.json

Copy the schema from [Section 3](#3-themejson-schema) and populate all fields. The `slug` value must exactly match the directory name.

### Step 3 — Create the required views

At minimum, the following files must exist:

```
views/layout.blade.php
views/post.blade.php
views/index.blade.php
views/404.blade.php
```

All other views (`category`, `tag`, `author`) fall back to `index.blade.php` if absent.

### Step 4 — Reference CSS custom properties

In your stylesheet, use the custom properties defined in [Section 7](#7-color-customization) rather than hardcoded hex values:

```css
a { color: var(--color-primary); }
body { background: var(--color-background); color: var(--color-text); }
```

### Step 5 — Add a screenshot

Place a 1280×960 px PNG at `resources/themes/my-theme/screenshot.png`.

### Step 6 — Sync to the database

```bash
php artisan themes:sync
```

This discovers the new theme and inserts it into the `themes` table.

### Step 7 — Activate the theme

Either via the Filament admin panel (**Appearance → Themes → Activate**) or via CLI:

```bash
php artisan themes:activate my-theme
```

### Step 8 — Verify

Navigate to the frontend. The blog should render using the new theme. Check the browser DevTools to confirm the CSS custom properties are injected in `<head>`.
