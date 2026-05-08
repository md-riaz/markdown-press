# Admin Panel

## 1. Overview

The MarkdownPress admin panel is built with [Filament 4](https://filamentphp.com/) and is accessible at the `/admin` route. Filament 4 provides a reactive, component-based UI built on Livewire 3 and Alpine.js, with full support for custom pages, resources, widgets, and actions.

### AdminPanelProvider Configuration

```php
// app/Providers/Filament/AdminPanelProvider.php

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors(['primary' => Color::Indigo])
            ->font('Inter')
            ->brandName('MarkdownPress')
            ->brandLogo(asset('images/logo.svg'))
            ->favicon(asset('images/favicon.ico'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class])
            ->navigationGroups([
                NavigationGroup::make('Content')->icon('heroicon-o-document-text'),
                NavigationGroup::make('Media')->icon('heroicon-o-photo'),
                NavigationGroup::make('Engagement')->icon('heroicon-o-chat-bubble-left-right'),
                NavigationGroup::make('AI Tools')->icon('heroicon-o-sparkles'),
                NavigationGroup::make('Themes')->icon('heroicon-o-paint-brush'),
                NavigationGroup::make('Settings')->icon('heroicon-o-cog-6-tooth'),
                NavigationGroup::make('Users')->icon('heroicon-o-users'),
                NavigationGroup::make('Imports / Exports')->icon('heroicon-o-arrow-up-tray'),
                NavigationGroup::make('Static Builds')->icon('heroicon-o-server-stack'),
            ]);
    }
}
```

---

## 2. Navigation Structure

```
Dashboard
└── Analytics Overview                     (page: AnalyticsDashboard)

Content
├── All Posts                              (resource: PostResource)
├── Categories                             (resource: CategoryResource)
└── Tags                                   (resource: TagResource)

Media
└── Media Library                          (resource: MediaResource)

Engagement
├── Comments                               (resource: CommentResource)
└── Newsletter Subscribers                 (resource: SubscriberResource)

AI Tools
└── AI Tools                               (page: AIToolsPage)

Themes
└── Themes                                 (resource: ThemeResource)

Settings
├── General Settings                       (page: GeneralSettingsPage)
├── SEO Settings                           (page: SeoSettingsPage)
├── AI Settings                            (page: AiSettingsPage)
└── API Tokens                             (resource: ApiTokenResource)

Users
└── Users                                  (resource: UserResource)

Imports / Exports
├── WordPress Import                       (page: WordPressImportPage)
└── JSON Export / Import                   (page: JsonExportImportPage)

Static Builds
└── Static Site Generator                  (page: StaticBuildsPage)
```

---

## 3. PostResource

**Class:** `App\Filament\Resources\PostResource`  
**Model:** `App\Models\Post`  
**Navigation group:** Content  
**Navigation icon:** `heroicon-o-document-text`

### List Columns

| Column         | Type          | Sortable | Searchable | Notes                                           |
|----------------|---------------|----------|------------|-------------------------------------------------|
| `title`        | TextColumn    | Yes      | Yes        | Linked to edit form                             |
| `status`       | BadgeColumn   | Yes      | No         | Colors: draft=gray, published=green, scheduled=yellow, private=red |
| `author`       | TextColumn    | Yes      | Yes        | Displays `user.name` via relationship           |
| `category`     | TextColumn    | No       | Yes        | Primary category name                           |
| `published_at` | DateTimeColumn| Yes      | No         | Formatted as `M j, Y g:i A`; null shown as `—` |
| `view_count`   | TextColumn    | Yes      | No         | Integer, comma-formatted                        |

### Filters

- **Status** — SelectFilter: draft / published / scheduled / private
- **Author** — SelectFilter: populated from `users` table (role ≥ author)
- **Category** — SelectFilter: hierarchical category tree
- **Tag** — SelectFilter: tag name search
- **Date Range** — DateRangeFilter on `published_at`

### Table Actions

- **Edit** — navigates to `PostResource::getEditUrl()`
- **Clone** — calls `PostService::clone($post)` which duplicates the record with status=draft, slug suffixed with `-copy`, resets `published_at` and `view_count`
- **View** — opens frontend post URL in new tab (only visible for published posts)
- **Delete** — soft-delete with confirmation modal; shows count of attached media and comments in confirmation text

### Form Fields

The post edit form is organized into a main column and a collapsible right sidebar.

**Main column:**

| Field               | Component                      | Notes                                                                    |
|---------------------|--------------------------------|--------------------------------------------------------------------------|
| `title`             | TextInput                      | Required; triggers slug auto-generation via Alpine.js on first keystroke |
| `slug`              | TextInput                      | Editable; validated unique per locale; prefixed with locale if multilingual |
| `content_markdown`  | MarkdownEditor (custom)        | Full-screen capable; shortcode syntax highlighting via CodeMirror extension |
| `excerpt`           | Textarea                       | Optional; used for meta description fallback                             |
| `featured_image`    | MediaPickerAction              | Opens MediaResource modal; stores `media_id` FK                          |

**Right sidebar (collapsible sections):**

| Section       | Fields                                                                                               |
|---------------|------------------------------------------------------------------------------------------------------|
| Publish       | `status` (Select), `published_at` (DateTimePicker, shown when status=scheduled)                     |
| Taxonomy      | `categories` (CheckboxList, multi-select), `tags` (TagsInput with autocomplete)                     |
| SEO           | `meta_title` (TextInput), `meta_description` (Textarea, 160-char counter), `og_image` (MediaPicker) |
| Access        | `password` (TextInput, shown when status=private)                                                    |
| Translations  | Links to per-language translation edit pages                                                         |
| Revisions     | Accordion list of recent revisions with restore action                                               |

---

## 4. MediaResource

**Class:** `App\Filament\Resources\MediaResource`  
**Model:** `App\Models\Media` (Spatie MediaLibrary model, extended)  
**Navigation group:** Media  
**Navigation icon:** `heroicon-o-photo`

### Layout

The list view uses a **custom grid layout** (overrides `getTableRecordView()`) that renders items as responsive cards (4 columns on desktop, 2 on tablet, 1 on mobile). Each card displays:

- Thumbnail image (WebP `thumb` variant, 300×300) or a filetype icon for audio/video
- Filename (truncated with tooltip)
- File size (human-readable)
- Star icon (filled/outline depending on `custom_properties.starred`)
- MIME type badge

### Filters

| Filter     | Type       | Behavior                              |
|------------|------------|---------------------------------------|
| Type       | Select     | All / Images / Audio / Video          |
| Date       | DateRange  | Filters on `media.created_at`         |
| Starred    | Toggle     | Shows only starred items              |

### Actions

| Action     | Behavior                                                                                              |
|------------|-------------------------------------------------------------------------------------------------------|
| View       | Opens a slide-over panel showing all variant URLs, dimensions, file size, uploader, and creation date |
| Star       | Toggles `custom_properties['starred']`; icon updates in grid without page reload                     |
| Copy URL   | Copies the variant URL (default: `webp`) to clipboard via Alpine.js                                  |
| Crop       | Opens Cropper.js modal; on confirm dispatches `MediaCropJob`                                          |
| Delete     | Confirmation modal → soft-delete record and schedule disk cleanup                                     |

---

## 5. ThemeResource

**Class:** `App\Filament\Resources\ThemeResource`  
**Model:** `App\Models\Theme`  
**Navigation group:** Themes  
**Navigation icon:** `heroicon-o-paint-brush`

### Layout

Themes are displayed in a **card grid** layout (3 columns). Each card includes:

- Screenshot image (stored in `public/themes/{theme_key}/screenshot.png`)
- Theme name and author
- "Active" badge (green) if this is the currently activated theme
- Short description

### Actions

| Action             | Behavior                                                                                           |
|--------------------|----------------------------------------------------------------------------------------------------|
| Activate           | Calls `ThemeRegistry::activate($theme->key)` → updates `settings.active_theme`; reloads grid      |
| Customize Colors   | Opens an inline Filament Action modal with a color picker form (primary, secondary, accent, background, text). On save, writes to `theme_settings` table for the active theme. Changes are reflected immediately in the frontend via CSS custom properties. |
| Preview            | Opens the blog frontend in a new tab with `?theme_preview={key}` query parameter for unsaved preview |

---

## 6. AIToolsPage

**Class:** `App\Filament\Pages\AIToolsPage`  
**Navigation group:** AI Tools  
**Navigation icon:** `heroicon-o-sparkles`

This is a custom full-page Filament page (Livewire component) with the following layout:

### Controls

| Control             | Type               | Notes                                                                |
|---------------------|--------------------|----------------------------------------------------------------------|
| Post selector       | Select (searchable)| Populated from all posts; search by title                            |
| AI Driver           | Select             | Options: Gemini (default), OpenAI, Anthropic (only drivers with configured API keys are shown) |
| Language (for translate) | Select        | Lists all active site locales                                        |

### Action Buttons

| Button               | Action                                                                                              |
|----------------------|-----------------------------------------------------------------------------------------------------|
| Generate Summary     | Dispatches `GenerateSummaryJob($postId, $driver)` to `ai` queue; polls for result via Livewire      |
| Generate Excerpt     | Dispatches `GenerateExcerptJob($postId, $driver)`; result auto-fills post `excerpt` field           |
| Translate To…        | Dispatches `TranslatePostJob($postId, $targetLocale, $driver)`; creates/updates `PostTranslation`   |
| Regenerate           | Re-runs the last action with fresh parameters                                                        |

### Status Indicators

- **API key status indicator** — a colored dot (green/red) next to each driver selector option indicating whether a valid API key is configured in Settings → AI Settings
- **Job progress bar** — Livewire `poll` (1-second interval) while a job is in `processing` state; hidden when idle
- **Output preview panel** — read-only Markdown preview of the AI-generated content; includes a "Copy" button and an "Apply to Post" button that saves the generated content to the appropriate post field

---

## 7. StaticBuildsPage

**Class:** `App\Filament\Pages\StaticBuildsPage`  
**Navigation group:** Static Builds  
**Navigation icon:** `heroicon-o-server-stack`

### Trigger Build

A prominent "Build Static Site" button opens a modal with:

- **Theme selector** — Select field populated from active themes; defaults to the currently active theme
- **Include drafts** — Toggle (default: off)
- **Base URL override** — TextInput (optional; defaults to `APP_URL`)

On confirm, dispatches `BuildOrchestratorJob` to the `builds` queue and creates a `StaticBuild` record with status `queued`.

### Real-Time Progress

Progress is tracked via Livewire polling (1-second interval) against the `static_builds` table. The progress bar displays:

- Overall percentage (`pages_built / pages_total * 100`)
- Current step label (e.g., "Rendering posts…", "Copying assets…", "Generating ZIP…")
- Elapsed time

When Laravel Echo is configured (`BROADCAST_DRIVER=pusher` or `reverb`), the poll is replaced by a WebSocket listener on `StaticBuildProgressEvent` for true real-time updates.

### Build History Table

| Column        | Type          | Notes                                   |
|---------------|---------------|-----------------------------------------|
| `theme`       | TextColumn    | Theme key/name                          |
| `status`      | BadgeColumn   | queued=gray, building=yellow, done=green, failed=red |
| `pages_built` | TextColumn    | e.g., `142 / 142`                       |
| `zip_size`    | TextColumn    | Human-readable file size                |
| `completed_at`| DateTimeColumn| Formatted timestamp                     |
| Download ZIP  | Action        | Generates a signed temporary URL for the ZIP file; button disabled if status ≠ done |
| View Logs     | Action        | Opens a slide-over with full build log text |
| Delete        | Action        | Removes build record and associated ZIP file from storage |

---

## 8. AnalyticsDashboard

**Class:** `App\Filament\Pages\AnalyticsDashboard`  
**Navigation:** Top-level (Dashboard section)  
**Navigation icon:** `heroicon-o-chart-bar`

### Stats Widgets

Four stat overview cards displayed at the top of the page:

| Metric            | Periods      | Data source                              |
|-------------------|--------------|------------------------------------------|
| Total Page Views  | 7d / 30d / 90d | `post_views` table, grouped by date    |
| Unique Visitors   | 7d / 30d / 90d | Distinct `ip_hash` in `post_views`     |
| New Posts         | 7d / 30d / 90d | `posts.published_at` count             |
| Subscriber Growth | 7d / 30d / 90d | `newsletter_subscribers.created_at`    |

Each stat card shows a trend indicator (percentage change vs. the previous equivalent period).

### Line Chart Widget

A Filament `ChartWidget` using Chart.js rendering daily page views as a line chart. Time range is controlled by a Select (7d / 30d / 90d) that triggers a Livewire update without page reload.

### Top Posts Table

A table widget listing the 10 most-viewed posts in the selected period, with columns: rank, title (linked), author, views, and average time on page (if available).

### Top Countries Table

A table widget listing the top 10 countries by unique visitors, with columns: flag emoji, country name, visitors, and percentage of total. Data is sourced from the `post_views.country_code` column (populated by a lightweight IP geolocation lookup during view recording).

---

## 9. CommentResource

**Class:** `App\Filament\Resources\CommentResource`  
**Model:** `App\Models\Comment`  
**Navigation group:** Engagement  
**Navigation icon:** `heroicon-o-chat-bubble-left-right`

### List Columns

| Column        | Type       | Notes                                        |
|---------------|------------|----------------------------------------------|
| `post`        | TextColumn | `post.title` via relationship, linked to post edit |
| `author_name` | TextColumn | Guest name or authenticated user display name |
| `content`     | TextColumn | Truncated to 100 characters                   |
| `status`      | BadgeColumn| pending=yellow, approved=green, rejected=red  |
| `created_at`  | DateTimeColumn | Sortable, default sort DESC               |

### Moderation Actions

| Action    | Behavior                                                                 |
|-----------|--------------------------------------------------------------------------|
| Approve   | Sets `status = approved`; fires `CommentApproved` event                  |
| Reject    | Sets `status = rejected`; optionally triggers an email notification to the comment author |
| Delete    | Hard-deletes the comment and all child replies (cascaded)                |
| Reply     | Opens a modal to compose an admin reply as the site author               |

### Nested Reply Display

When a comment has replies, the list row is expandable (Filament Detail panel / sub-table) showing child comments indented up to 3 levels. Each nested reply has the same moderation actions as top-level comments.

---

## 10. Settings Pages

All settings pages use `spatie/laravel-settings` or a dedicated `settings` key-value table, wrapped in a Filament `Page` with a `Form` component.

### General Settings (`GeneralSettingsPage`)

| Field            | Type                    | Notes                                |
|------------------|-------------------------|--------------------------------------|
| Site Name        | TextInput               | Stored in `settings.site_name`       |
| Tagline          | TextInput               | Stored in `settings.tagline`         |
| Site Description | Textarea                | Used as default meta description     |
| Logo             | MediaPicker             | Stores `media_id` FK                 |
| Favicon          | MediaPicker             | Stores `media_id` FK                 |
| Posts per page   | NumericInput            | Default: 10                          |
| Default language | Select                  | Populated from `config/app.php` supported locales |
| Timezone         | Select                  | PHP timezone list                    |
| Date format      | TextInput               | e.g., `M j, Y`                       |

### SEO Settings (`SeoSettingsPage`)

| Field                  | Type       | Notes                                              |
|------------------------|------------|----------------------------------------------------|
| Default Meta Title     | TextInput  | Template supports `{title}` and `{site_name}` tokens |
| Default Meta Description | Textarea | 160-char counter                                   |
| Default OG Image       | MediaPicker| Fallback for posts without a featured image        |
| Google Analytics ID    | TextInput  | `G-XXXXXXXXXX` or `UA-XXXXX-X`                    |
| Meta Pixel ID          | TextInput  | Facebook Pixel ID                                  |
| Sitemap enabled        | Toggle     | Enables `/sitemap.xml` route                       |
| Sitemap change frequency | Select   | daily / weekly / monthly                           |
| Robots.txt content     | CodeEditor | Editable raw `robots.txt`                          |

### AI Settings (`AiSettingsPage`)

| Field                  | Type       | Notes                                              |
|------------------------|------------|----------------------------------------------------|
| Default AI Driver      | Select     | gemini / openai / anthropic                        |
| Gemini API Key         | TextInput  | Masked input; validated with a "Test Connection" button |
| OpenAI API Key         | TextInput  | Masked input                                       |
| Anthropic API Key      | TextInput  | Masked input                                       |
| Max tokens per request | NumericInput | Default: 2048                                    |
| Temperature            | Slider     | 0.0–1.0, default 0.7                               |

---

## 11. Role-Based Access Control

Access to Filament resources is enforced via Laravel Policies registered with each Filament resource. Three built-in roles are defined:

| Role   | Capabilities                                                                                      |
|--------|---------------------------------------------------------------------------------------------------|
| Admin  | Full access to all resources and settings pages                                                   |
| Editor | Access to Posts (all authors), Comments, Media Library; cannot access Settings or User management |
| Author | Access to own Posts only (`posts.user_id = auth()->id()`); own media only; read-only Comments    |

### Policy Enforcement

Each Filament resource implements a `getEloquentQuery()` scope that applies ownership filters for non-admin users:

```php
// PostResource.php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    if (! auth()->user()->isAdmin() && ! auth()->user()->isEditor()) {
        $query->where('user_id', auth()->id());
    }

    return $query;
}
```

Filament action visibility is also gated:

```php
// In PostResource table actions
Action::make('delete')
    ->visible(fn (Post $record): bool =>
        auth()->user()->can('delete', $record)
    )
```

Settings pages are protected with a `canAccess()` override:

```php
public static function canAccess(): bool
{
    return auth()->user()->isAdmin();
}
```
