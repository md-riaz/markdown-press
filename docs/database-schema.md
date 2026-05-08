# MarkdownPress — Database Schema Reference

This document defines every database table used by MarkdownPress, including column definitions, indexes, foreign keys, and Laravel relationship summaries. All tables use the `utf8mb4` character set with `utf8mb4_unicode_ci` collation unless otherwise noted.

---

## Table of Contents

1. [users](#1-users)
2. [posts](#2-posts)
3. [post_translations](#3-post_translations)
4. [revisions](#4-revisions)
5. [categories](#5-categories)
6. [tags](#6-tags)
7. [post_category (pivot)](#7-post_category-pivot)
8. [post_tag (pivot)](#8-post_tag-pivot)
9. [media](#9-media)
10. [media_variants](#10-media_variants)
11. [shortcode_registry](#11-shortcode_registry)
12. [themes](#12-themes)
13. [theme_customizations](#13-theme_customizations)
14. [static_builds](#14-static_builds)
15. [comments](#15-comments)
16. [subscribers](#16-subscribers)
17. [page_views](#17-page_views)
18. [settings](#18-settings)
19. [api_tokens](#19-api_tokens)
20. [Entity Relationship Overview](#entity-relationship-overview)

---

## 1. `users`

Stores all authenticated users. The `role` column controls access within Filament and throughout the application's authorization layer.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `name` | `VARCHAR(255)` | No | — | Display name |
| `email` | `VARCHAR(255)` | No | — | Unique login email address |
| `email_verified_at` | `TIMESTAMP` | Yes | `NULL` | Timestamp when email was verified |
| `password` | `VARCHAR(255)` | No | — | Bcrypt-hashed password |
| `role` | `ENUM('admin','editor','author')` | No | `'author'` | Authorization role |
| `bio` | `TEXT` | Yes | `NULL` | Short author biography shown on post pages |
| `avatar_url` | `VARCHAR(512)` | Yes | `NULL` | Absolute or relative URL to the user's avatar image |
| `remember_token` | `VARCHAR(100)` | Yes | `NULL` | Laravel remember-me token |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `users_pkey` | PRIMARY | `id` |
| `users_email_unique` | UNIQUE | `email` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `posts` | `hasMany` | `posts.user_id` | All posts authored by this user |
| `revisions` | `hasMany` | `revisions.user_id` | All revisions saved by this user |
| `media` | `hasMany` | `media.user_id` | All media uploaded by this user |
| `apiTokens` | `hasMany` | `api_tokens.user_id` | Personal API tokens |
| `comments` | `hasMany` | `comments.user_id` | Approved comments left by this user |

---

## 2. `posts`

The central content table. Stores both the raw Markdown source and a cached HTML render. Supports soft-deletion, scheduling, SEO metadata, and password protection.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `user_id` | `BIGINT UNSIGNED` | No | — | FK → `users.id`; the post author |
| `title` | `VARCHAR(512)` | No | — | Default-locale post title |
| `slug` | `VARCHAR(512)` | No | — | URL-safe unique identifier |
| `content_markdown` | `LONGTEXT` | No | — | Raw Markdown source content |
| `content_html_cached` | `LONGTEXT` | Yes | `NULL` | Pre-rendered HTML (invalidated on save) |
| `status` | `ENUM('draft','scheduled','published')` | No | `'draft'` | Publication lifecycle state |
| `password` | `VARCHAR(255)` | Yes | `NULL` | Bcrypt hash if post is password-protected |
| `published_at` | `TIMESTAMP` | Yes | `NULL` | Actual datetime the post went live |
| `scheduled_at` | `TIMESTAMP` | Yes | `NULL` | Datetime to auto-publish (if status is `scheduled`) |
| `featured_image_id` | `BIGINT UNSIGNED` | Yes | `NULL` | FK → `media.id`; hero image |
| `audio_attachment_id` | `BIGINT UNSIGNED` | Yes | `NULL` | FK → `media.id`; linked audio file |
| `meta_title` | `VARCHAR(255)` | Yes | `NULL` | SEO `<title>` override |
| `meta_description` | `VARCHAR(512)` | Yes | `NULL` | SEO meta-description |
| `og_image_url` | `VARCHAR(512)` | Yes | `NULL` | Open Graph image URL |
| `canonical_url` | `VARCHAR(512)` | Yes | `NULL` | Canonical URL hint for search engines |
| `noindex` | `TINYINT(1)` | No | `0` | Whether to emit `<meta name="robots" content="noindex">` |
| `is_featured` | `TINYINT(1)` | No | `0` | Whether the post is pinned/featured on the homepage |
| `view_count` | `INT UNSIGNED` | No | `0` | Denormalized total view counter (updated asynchronously) |
| `comment_count` | `INT UNSIGNED` | No | `0` | Denormalized approved comment count |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |
| `deleted_at` | `TIMESTAMP` | Yes | `NULL` | Soft-delete timestamp; `NULL` = active |

### Indexes

| Index Name | Type | Columns | Notes |
|---|---|---|---|
| `posts_pkey` | PRIMARY | `id` | |
| `posts_slug_unique` | UNIQUE | `slug` | Only enforced on non-deleted rows |
| `posts_user_id_index` | INDEX | `user_id` | FK lookup |
| `posts_status_published_at_index` | INDEX | `status`, `published_at` | Composite; used by public listing queries |
| `posts_scheduled_at_index` | INDEX | `scheduled_at` | Used by `ScheduledPublishingJob` |
| `posts_featured_image_id_index` | INDEX | `featured_image_id` | FK lookup |
| `posts_audio_attachment_id_index` | INDEX | `audio_attachment_id` | FK lookup |
| `posts_deleted_at_index` | INDEX | `deleted_at` | Soft-delete filter |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `author` | `belongsTo` | `users` | Via `user_id` |
| `featuredImage` | `belongsTo` | `media` | Via `featured_image_id` |
| `audioAttachment` | `belongsTo` | `media` | Via `audio_attachment_id` |
| `translations` | `hasMany` | `post_translations` | All locale-specific versions |
| `revisions` | `hasMany` | `revisions` | Full revision history |
| `categories` | `belongsToMany` | `categories` | Via `post_category` pivot |
| `tags` | `belongsToMany` | `tags` | Via `post_tag` pivot |
| `comments` | `hasMany` | `comments` | All comments on this post |
| `pageViews` | `hasMany` | `page_views` | Raw analytics rows |

---

## 3. `post_translations`

Stores locale-specific overrides for a post's title, slug, and content. One row per post per locale. Used by `spatie/laravel-translatable` and the AI translation pipeline.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `post_id` | `BIGINT UNSIGNED` | No | — | FK → `posts.id` |
| `locale` | `VARCHAR(10)` | No | — | BCP-47 locale code, e.g. `en`, `fr`, `zh-CN` |
| `title` | `VARCHAR(512)` | No | — | Translated post title |
| `slug` | `VARCHAR(512)` | No | — | URL slug for this locale |
| `content_markdown` | `LONGTEXT` | No | — | Translated Markdown source |
| `content_html_cached` | `LONGTEXT` | Yes | `NULL` | Pre-rendered HTML cache for this translation |
| `meta_title` | `VARCHAR(255)` | Yes | `NULL` | Translated SEO title |
| `meta_description` | `VARCHAR(512)` | Yes | `NULL` | Translated SEO description |
| `is_ai_translated` | `TINYINT(1)` | No | `0` | `1` if content was generated by the AI translation pipeline |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns | Notes |
|---|---|---|---|
| `post_translations_pkey` | PRIMARY | `id` | |
| `post_translations_post_locale_unique` | UNIQUE | `post_id`, `locale` | One translation per locale per post |
| `post_translations_locale_slug_unique` | UNIQUE | `locale`, `slug` | Slug uniqueness scoped to locale |
| `post_translations_post_id_index` | INDEX | `post_id` | FK lookup |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `post` | `belongsTo` | `posts` | Via `post_id` |

---

## 4. `revisions`

Immutable audit log of all content changes. A new row is written every time a post is saved. The `meta` JSON column stores any changed fields beyond title and Markdown (e.g., status, slug, SEO fields).

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `post_id` | `BIGINT UNSIGNED` | No | — | FK → `posts.id` |
| `user_id` | `BIGINT UNSIGNED` | No | — | FK → `users.id`; who made the change |
| `title` | `VARCHAR(512)` | No | — | Snapshot of post title at save time |
| `content_markdown` | `LONGTEXT` | No | — | Full Markdown snapshot |
| `meta` | `JSON` | Yes | `NULL` | JSON object with all other changed fields |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | When this revision was saved |

> **Note:** There is no `updated_at` column — revisions are write-once.

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `revisions_pkey` | PRIMARY | `id` |
| `revisions_post_id_index` | INDEX | `post_id` |
| `revisions_user_id_index` | INDEX | `user_id` |
| `revisions_post_id_created_at_index` | INDEX | `post_id`, `created_at` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `post` | `belongsTo` | `posts` | Via `post_id` |
| `author` | `belongsTo` | `users` | Via `user_id` |

---

## 5. `categories`

Hierarchical content taxonomy. Supports one level of nesting via `parent_id` self-reference. The `sort_order` column controls display sequence within a level.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `parent_id` | `BIGINT UNSIGNED` | Yes | `NULL` | FK → `categories.id`; `NULL` = top-level |
| `name` | `VARCHAR(255)` | No | — | Display name |
| `slug` | `VARCHAR(255)` | No | — | Unique URL identifier |
| `description` | `TEXT` | Yes | `NULL` | Optional category description |
| `meta_title` | `VARCHAR(255)` | Yes | `NULL` | SEO title for category archive page |
| `meta_description` | `VARCHAR(512)` | Yes | `NULL` | SEO description for category archive page |
| `sort_order` | `INT` | No | `0` | Integer sort position (ascending) |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `categories_pkey` | PRIMARY | `id` |
| `categories_slug_unique` | UNIQUE | `slug` |
| `categories_parent_id_index` | INDEX | `parent_id` |
| `categories_sort_order_index` | INDEX | `sort_order` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `parent` | `belongsTo` | `categories` | Self-referential via `parent_id` |
| `children` | `hasMany` | `categories` | Self-referential via `parent_id` |
| `posts` | `belongsToMany` | `posts` | Via `post_category` pivot |

---

## 6. `tags`

Flat content taxonomy for freeform tagging. Tags have no hierarchy.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `name` | `VARCHAR(255)` | No | — | Display name |
| `slug` | `VARCHAR(255)` | No | — | Unique URL identifier |
| `description` | `TEXT` | Yes | `NULL` | Optional tag description |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `tags_pkey` | PRIMARY | `id` |
| `tags_slug_unique` | UNIQUE | `slug` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `posts` | `belongsToMany` | `posts` | Via `post_tag` pivot |

---

## 7. `post_category` (pivot)

Many-to-many join table between posts and categories. No surrogate key; composite primary key.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `post_id` | `BIGINT UNSIGNED` | No | — | FK → `posts.id` |
| `category_id` | `BIGINT UNSIGNED` | No | — | FK → `categories.id` |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `post_category_pkey` | PRIMARY | `post_id`, `category_id` |
| `post_category_category_id_index` | INDEX | `category_id` |

---

## 8. `post_tag` (pivot)

Many-to-many join table between posts and tags. No surrogate key; composite primary key.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `post_id` | `BIGINT UNSIGNED` | No | — | FK → `posts.id` |
| `tag_id` | `BIGINT UNSIGNED` | No | — | FK → `tags.id` |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `post_tag_pkey` | PRIMARY | `post_id`, `tag_id` |
| `post_tag_tag_id_index` | INDEX | `tag_id` |

---

## 9. `media`

Central media library. Each row represents one uploaded file. Processed variants (thumbnails, WebP conversions) are stored in `media_variants`. Managed by `spatie/laravel-medialibrary` with a custom collection model.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `user_id` | `BIGINT UNSIGNED` | No | — | FK → `users.id`; uploader |
| `filename` | `VARCHAR(255)` | No | — | Original filename as uploaded by the user |
| `disk` | `VARCHAR(50)` | No | — | Laravel filesystem disk name (`local`, `s3`, etc.) |
| `path` | `VARCHAR(1024)` | No | — | Full path on the disk |
| `mime_type` | `VARCHAR(127)` | No | — | MIME type, e.g. `image/jpeg`, `audio/mpeg` |
| `size` | `BIGINT UNSIGNED` | No | — | File size in bytes |
| `width` | `INT UNSIGNED` | Yes | `NULL` | Pixel width (images and video only) |
| `height` | `INT UNSIGNED` | Yes | `NULL` | Pixel height (images and video only) |
| `alt_text` | `VARCHAR(512)` | Yes | `NULL` | Accessible alt text for images |
| `is_starred` | `TINYINT(1)` | No | `0` | Whether the file is bookmarked in the media library UI |
| `collection` | `VARCHAR(50)` | No | `'images'` | Logical collection bucket: `images`, `audio`, `video`, `documents` |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `media_pkey` | PRIMARY | `id` |
| `media_user_id_index` | INDEX | `user_id` |
| `media_collection_index` | INDEX | `collection` |
| `media_mime_type_index` | INDEX | `mime_type` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `uploader` | `belongsTo` | `users` | Via `user_id` |
| `variants` | `hasMany` | `media_variants` | All processed variants |
| `postFeaturedIn` | `hasMany` | `posts` | Posts where this is the featured image |
| `postAudioIn` | `hasMany` | `posts` | Posts where this is the audio attachment |

---

## 10. `media_variants`

Stores every processed derivative of an original media file. For images, this includes WebP conversions, thumbnails, and responsive sizes. For video, it may include preview stills.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `media_id` | `BIGINT UNSIGNED` | No | — | FK → `media.id` |
| `variant_name` | `VARCHAR(50)` | No | — | Variant identifier: `webp`, `thumb`, `medium`, `large`, `poster` |
| `disk` | `VARCHAR(50)` | No | — | Laravel filesystem disk name |
| `path` | `VARCHAR(1024)` | No | — | Full path on the disk |
| `mime_type` | `VARCHAR(127)` | No | — | MIME type of the variant |
| `size` | `BIGINT UNSIGNED` | No | — | File size in bytes |
| `width` | `INT UNSIGNED` | Yes | `NULL` | Pixel width |
| `height` | `INT UNSIGNED` | Yes | `NULL` | Pixel height |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `media_variants_pkey` | PRIMARY | `id` |
| `media_variants_media_id_variant_unique` | UNIQUE | `media_id`, `variant_name` |
| `media_variants_media_id_index` | INDEX | `media_id` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `original` | `belongsTo` | `media` | Via `media_id` |

---

## 11. `shortcode_registry`

Registry of all available shortcode definitions. Each row binds a shortcode name to its PHP handler class. The `ShortcodeParser` queries this table at boot to build its dispatch table.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `name` | `VARCHAR(100)` | No | — | Shortcode tag name, e.g. `youtube`, `alert`, `mermaid` |
| `handler_class` | `VARCHAR(512)` | No | — | Fully-qualified PHP class implementing `ShortcodeHandlerContract` |
| `description` | `TEXT` | Yes | `NULL` | Human-readable description of what the shortcode does |
| `is_active` | `TINYINT(1)` | No | `1` | Whether the shortcode is available for rendering |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |

> **Note:** There is no `updated_at` column — registry entries are treated as immutable configuration.

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `shortcode_registry_pkey` | PRIMARY | `id` |
| `shortcode_registry_name_unique` | UNIQUE | `name` |

---

## 12. `themes`

Records every installed theme. Only one theme may have `is_default = 1` at a time; a database trigger or application-layer constraint ensures this. The `config` JSON column stores theme-specific schema (color palettes, font choices, layout toggles) that `ThemeConfigLoader` validates against a per-theme schema file.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `name` | `VARCHAR(255)` | No | — | Human-readable theme name |
| `slug` | `VARCHAR(100)` | No | — | Machine identifier; matches directory name under `resources/themes/` |
| `description` | `TEXT` | Yes | `NULL` | Theme description for the admin panel |
| `thumbnail_path` | `VARCHAR(512)` | Yes | `NULL` | Relative path to the preview screenshot |
| `config` | `JSON` | Yes | `NULL` | Theme options: colors, fonts, layout settings |
| `is_active` | `TINYINT(1)` | No | `0` | Whether this theme is currently activated |
| `is_default` | `TINYINT(1)` | No | `0` | Whether this theme is the installation default |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `themes_pkey` | PRIMARY | `id` |
| `themes_slug_unique` | UNIQUE | `slug` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `customizations` | `hasMany` | `theme_customizations` | Key-value overrides for this theme |
| `staticBuilds` | `hasMany` | `static_builds` | All build runs using this theme |

---

## 13. `theme_customizations`

Per-theme key-value store for user-applied overrides (e.g., custom CSS variables, logo URL, footer text). These are layered on top of the base `themes.config` JSON at render time.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `theme_id` | `BIGINT UNSIGNED` | No | — | FK → `themes.id` |
| `key` | `VARCHAR(255)` | No | — | Setting key, e.g. `primary_color`, `logo_url` |
| `value` | `TEXT` | Yes | `NULL` | Setting value (serialized if complex) |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `theme_customizations_pkey` | PRIMARY | `id` |
| `theme_customizations_theme_key_unique` | UNIQUE | `theme_id`, `key` |
| `theme_customizations_theme_id_index` | INDEX | `theme_id` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `theme` | `belongsTo` | `themes` | Via `theme_id` |

---

## 14. `static_builds`

Audit log for every static site generation run. Tracks lifecycle state, output statistics, and the path to the downloadable ZIP archive.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `theme_id` | `BIGINT UNSIGNED` | No | — | FK → `themes.id`; theme used for this build |
| `status` | `ENUM('pending','running','completed','failed')` | No | `'pending'` | Current build lifecycle state |
| `file_count` | `INT UNSIGNED` | Yes | `NULL` | Total number of HTML/asset files generated |
| `image_count` | `INT UNSIGNED` | Yes | `NULL` | Total number of images copied or processed |
| `zip_size` | `BIGINT UNSIGNED` | Yes | `NULL` | Size of the output ZIP archive in bytes |
| `zip_path` | `VARCHAR(1024)` | Yes | `NULL` | Relative path to the ZIP file on the storage disk |
| `error_message` | `TEXT` | Yes | `NULL` | Captured exception message if `status = failed` |
| `started_at` | `TIMESTAMP` | Yes | `NULL` | Timestamp when the build job began processing |
| `completed_at` | `TIMESTAMP` | Yes | `NULL` | Timestamp when the build job finished |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `static_builds_pkey` | PRIMARY | `id` |
| `static_builds_theme_id_index` | INDEX | `theme_id` |
| `static_builds_status_index` | INDEX | `status` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `theme` | `belongsTo` | `themes` | Via `theme_id` |

---

## 15. `comments`

Threaded comment tree. Supports both authenticated users and anonymous (guest) commenters. Guest identity is stored inline; authenticated user details are resolved via `user_id`. Gravatar is derived from `guest_email` at write time.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `post_id` | `BIGINT UNSIGNED` | No | — | FK → `posts.id` |
| `parent_id` | `BIGINT UNSIGNED` | Yes | `NULL` | FK → `comments.id`; `NULL` = top-level comment |
| `user_id` | `BIGINT UNSIGNED` | Yes | `NULL` | FK → `users.id`; `NULL` = guest commenter |
| `guest_name` | `VARCHAR(255)` | No | — | Display name (mirrored from user for authenticated commenters) |
| `guest_email` | `VARCHAR(255)` | No | — | Email address (mirrored from user for authenticated commenters) |
| `guest_country` | `CHAR(2)` | Yes | `NULL` | ISO 3166-1 alpha-2 country code, geo-detected at submission |
| `body` | `TEXT` | No | — | Comment body (plain text, HTML-escaped on output) |
| `status` | `ENUM('pending','approved','rejected')` | No | `'pending'` | Moderation state |
| `gravatar_hash` | `VARCHAR(64)` | Yes | `NULL` | MD5 hash of the email address for Gravatar lookup |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `comments_pkey` | PRIMARY | `id` |
| `comments_post_id_status_index` | INDEX | `post_id`, `status` |
| `comments_parent_id_index` | INDEX | `parent_id` |
| `comments_user_id_index` | INDEX | `user_id` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `post` | `belongsTo` | `posts` | Via `post_id` |
| `parent` | `belongsTo` | `comments` | Self-referential via `parent_id` |
| `replies` | `hasMany` | `comments` | Self-referential via `parent_id` |
| `user` | `belongsTo` | `users` | Via `user_id`; `NULL` for guests |

---

## 16. `subscribers`

Email newsletter subscribers. The `token` column is a cryptographically random string used in one-click unsubscribe links, keeping the unsubscribe flow unauthenticated and secure.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `name` | `VARCHAR(255)` | Yes | `NULL` | Optional subscriber display name |
| `email` | `VARCHAR(255)` | No | — | Unique subscriber email address |
| `status` | `ENUM('active','unsubscribed')` | No | `'active'` | Subscription state |
| `token` | `VARCHAR(100)` | No | — | Secure random token for unsubscribe URL |
| `subscribed_at` | `TIMESTAMP` | No | — | When the subscriber opted in |
| `unsubscribed_at` | `TIMESTAMP` | Yes | `NULL` | When the subscriber opted out |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `subscribers_pkey` | PRIMARY | `id` |
| `subscribers_email_unique` | UNIQUE | `email` |
| `subscribers_token_unique` | UNIQUE | `token` |
| `subscribers_status_index` | INDEX | `status` |

---

## 17. `page_views`

Raw analytics event log. One row per unique page view impression. IP addresses are stored as a one-way hash for GDPR compliance. This table has no `updated_at` column and does not use soft deletes.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `post_id` | `BIGINT UNSIGNED` | No | — | FK → `posts.id` |
| `ip_hash` | `VARCHAR(64)` | No | — | SHA-256 hash of the visitor IP address |
| `country` | `CHAR(2)` | Yes | `NULL` | ISO 3166-1 alpha-2 country code from geo-IP lookup |
| `session_id` | `VARCHAR(100)` | No | — | Hashed browser session identifier |
| `viewed_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | Exact timestamp of the view event |

### Indexes

| Index Name | Type | Columns | Notes |
|---|---|---|---|
| `page_views_pkey` | PRIMARY | `id` | |
| `page_views_post_id_viewed_at_index` | INDEX | `post_id`, `viewed_at` | Used by analytics aggregation queries |
| `page_views_country_index` | INDEX | `country` | Used by country breakdown report |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `post` | `belongsTo` | `posts` | Via `post_id` |

---

## 18. `settings`

Global key-value configuration store. Settings are grouped by functional domain and managed via the Filament Settings panel. Values are stored as text and cast by the application layer. The composite unique index on `(group, key)` is the effective natural key.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Surrogate primary key |
| `group` | `VARCHAR(50)` | No | — | Logical group: `general`, `seo`, `ai`, `analytics`, `mail`, `social` |
| `key` | `VARCHAR(100)` | No | — | Setting key within the group |
| `value` | `TEXT` | Yes | `NULL` | Setting value; cast to the appropriate type in application code |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `settings_pkey` | PRIMARY | `id` |
| `settings_group_key_unique` | UNIQUE | `group`, `key` |

---

## 19. `api_tokens`

Personal API tokens for external integrations and the REST API. Tokens are stored as HMAC-SHA256 hashes and never retrievable after creation. The `abilities` JSON array controls which API operations the token may perform.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | No | auto-increment | Primary key |
| `user_id` | `BIGINT UNSIGNED` | No | — | FK → `users.id`; token owner |
| `name` | `VARCHAR(255)` | No | — | Human-readable token label |
| `token` | `VARCHAR(128)` | No | — | HMAC-SHA256 hash of the plain-text token |
| `abilities` | `JSON` | No | `'["*"]'` | JSON array of ability strings, e.g. `["posts:read","posts:write"]` |
| `ip_whitelist` | `JSON` | Yes | `NULL` | JSON array of allowed CIDR ranges; `NULL` = unrestricted |
| `last_used_at` | `TIMESTAMP` | Yes | `NULL` | When this token was last authenticated |
| `expires_at` | `TIMESTAMP` | Yes | `NULL` | Expiry timestamp; `NULL` = no expiry |
| `created_at` | `TIMESTAMP` | Yes | `NULL` | Record creation timestamp |
| `updated_at` | `TIMESTAMP` | Yes | `NULL` | Record update timestamp |

### Indexes

| Index Name | Type | Columns |
|---|---|---|
| `api_tokens_pkey` | PRIMARY | `id` |
| `api_tokens_token_unique` | UNIQUE | `token` |
| `api_tokens_user_id_index` | INDEX | `user_id` |

### Relationships

| Relationship | Type | Target | Notes |
|---|---|---|---|
| `user` | `belongsTo` | `users` | Via `user_id` |

---

## Entity Relationship Overview

The following text-based ERD illustrates the primary relationships between tables. Cardinality is shown with crow's foot notation in text form: `||` = one, `}|` = one-or-more, `|{` = zero-or-more.

```
users
  ||-----|{ posts                (users.id → posts.user_id)
  ||-----|{ revisions            (users.id → revisions.user_id)
  ||-----|{ media                (users.id → media.user_id)
  ||-----|{ api_tokens           (users.id → api_tokens.user_id)
  ||--|{  comments               (users.id → comments.user_id, nullable)

posts
  ||-----|{ post_translations    (posts.id → post_translations.post_id)
  ||-----|{ revisions            (posts.id → revisions.post_id)
  ||-----|{ comments             (posts.id → comments.post_id)
  ||-----|{ page_views           (posts.id → page_views.post_id)
  ||--|{  post_category [pivot]  (posts.id → post_category.post_id)
  ||--|{  post_tag [pivot]       (posts.id → post_tag.post_id)
  ||--|{  media (featured_image) (posts.featured_image_id → media.id, nullable)
  ||--|{  media (audio)          (posts.audio_attachment_id → media.id, nullable)

categories
  ||--|{  categories (self-ref)  (categories.id → categories.parent_id, nullable)
  ||--|{  post_category [pivot]  (categories.id → post_category.category_id)

tags
  ||--|{  post_tag [pivot]       (tags.id → post_tag.tag_id)

media
  ||-----|{ media_variants       (media.id → media_variants.media_id)

themes
  ||-----|{ theme_customizations (themes.id → theme_customizations.theme_id)
  ||-----|{ static_builds        (themes.id → static_builds.theme_id)

comments
  ||--|{  comments (self-ref)    (comments.id → comments.parent_id, nullable)
```

### Pivot / Junction Tables

| Pivot Table | Left FK | Right FK |
|---|---|---|
| `post_category` | `posts.id` | `categories.id` |
| `post_tag` | `posts.id` | `tags.id` |

### Standalone Tables (no FK dependencies)

| Table | Notes |
|---|---|
| `shortcode_registry` | No FK; references PHP classes by string |
| `subscribers` | Self-contained; not linked to `users` by design |
| `settings` | Global KV store; no FK relationships |
