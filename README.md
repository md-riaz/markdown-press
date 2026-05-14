# MarkdownPress

A Laravel-based, Markdown-first CMS with multilingual publishing, theme support, AI-assisted workflows, and static site generation.

## Preview

Screens below were captured from the seeded app running locally in the container.

### Public Pages

| Home | Post |
| --- | --- |
| ![Home page preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/public-home.png) | ![Post page preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/public-post.png) |

| Category | Tag |
| --- | --- |
| ![Category page preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/public-category.png) | ![Tag page preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/public-tag.png) |

| Author | Newsletter unsubscribe |
| --- | --- |
| ![Author page preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/public-author.png) | ![Newsletter unsubscribe preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/public-unsubscribe.png) |

### Admin Pages

| Login | Dashboard |
| --- | --- |
| ![Admin login preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-login.png) | ![Admin dashboard preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-dashboard.png) |

| Posts | Categories |
| --- | --- |
| ![Admin posts preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-posts.png) | ![Admin categories preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-categories.png) |

| Tags | Comments |
| --- | --- |
| ![Admin tags preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-tags.png) | ![Admin comments preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-comments.png) |

| Media | Subscribers |
| --- | --- |
| ![Admin media preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-media.png) | ![Admin subscribers preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-subscribers.png) |

| Users | API tokens |
| --- | --- |
| ![Admin users preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-users.png) | ![Admin API tokens preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-api-tokens.png) |

| Themes | Settings |
| --- | --- |
| ![Admin themes preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-themes.png) | ![Admin settings preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-settings.png) |

| AI tools | Static builds |
| --- | --- |
| ![Admin AI tools preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-ai-tools.png) | ![Admin static builds preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-static-builds.png) |

| WordPress import | JSON export / import |
| --- | --- |
| ![Admin WordPress import preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-wordpress-import.png) | ![Admin JSON export/import preview](https://raw.githubusercontent.com/md-riaz/markdown-press/main/docs/screenshots/admin-json-export-import.png) |

## Documentation

Project documentation lives in `/docs`:

- [docs/README.md](docs/README.md) — documentation index and developer quick start
- [docs/architecture.md](docs/architecture.md) — high-level architecture overview
- [docs/system-design.md](docs/system-design.md) — rendering pipeline, caching, queue, and security design
- [docs/database-schema.md](docs/database-schema.md) — schema and relationships
- [docs/modules.md](docs/modules.md) — module and service map
- [docs/api-design.md](docs/api-design.md) — REST API design
- [docs/shortcode-engine.md](docs/shortcode-engine.md) — shortcode parsing/rendering internals
- [docs/theme-engine.md](docs/theme-engine.md) — theme manifest, rendering, customization
- [docs/ai-pipeline.md](docs/ai-pipeline.md) — AI pipeline and markdown integrity flow
- [docs/static-site-generator.md](docs/static-site-generator.md) — static build architecture
- [docs/media-system.md](docs/media-system.md) — upload, processing, and media security
- [docs/admin-panel.md](docs/admin-panel.md) — Filament admin module structure
- [docs/roadmap.md](docs/roadmap.md) — implementation roadmap and phases
- [FEATURES.md](FEATURES.md) — feature reference baseline

## Quick Start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
composer dev
```

## Core Commands

```bash
# run test suite
php artisan test

# build static site
php artisan blog:build
```
