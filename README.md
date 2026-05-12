# MarkdownPress

A Laravel-based, Markdown-first CMS with multilingual publishing, theme support, AI-assisted workflows, and static site generation.

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
