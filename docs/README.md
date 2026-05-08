# MarkdownPress — Project Documentation

> A production-grade Laravel 13 CMS inspired by TyroPress: Markdown-first, AI-powered, multilingual, with a theme engine and static site generation.

---

## Document Index

| Document | Description |
|---|---|
| [architecture.md](architecture.md) | High-level system architecture, tech stack, request lifecycle, deployment overview |
| [system-design.md](system-design.md) | Content pipeline, caching strategy, queue topology, security design, media processing |
| [database-schema.md](database-schema.md) | All 19 database tables — columns, indexes, relationships, ERD overview |
| [modules.md](modules.md) | All application modules, service layer, repositories, contracts, event/listener map |
| [api-design.md](api-design.md) | Full REST API specification — endpoints, request/response schemas, auth, rate limiting |
| [shortcode-engine.md](shortcode-engine.md) | Shortcode parser, registry, all built-in handlers, extensibility guide |
| [theme-engine.md](theme-engine.md) | Blade-based theme system, theme.json schema, color customization, per-language fonts |
| [ai-pipeline.md](ai-pipeline.md) | AI service layer, BYOK config, Gemini/Qwen drivers, Markdown integrity, queue jobs |
| [static-site-generator.md](static-site-generator.md) | SSG build flow, CLI command, output structure, ZIP export, admin integration |
| [media-system.md](media-system.md) | Upload pipeline, WebP conversion, thumbnails, stock photo search, security |
| [admin-panel.md](admin-panel.md) | Filament 4 panel structure, all resources, role-based access, settings pages |
| [roadmap.md](roadmap.md) | 10-phase implementation plan (14 weeks), deliverables, dependencies, test coverage |

---

## Quick Start for Developers

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start development servers
composer dev
```

---

## Technology Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.3) |
| Admin Panel | Filament 4 |
| Markdown Parser | league/commonmark 2.x |
| Media Library | spatie/laravel-medialibrary 11.x |
| Image Processing | intervention/image 3.x |
| Multilingual | spatie/laravel-translatable 6.x |
| AI Integration | openai-php/laravel (Gemini + Qwen) |
| Database | MySQL 8 |
| Cache / Queue | Redis |
| Frontend Assets | Vite |

---

## Repository Structure

```
markdown-press/
├── app/
│   ├── Contracts/          # Core interfaces (AI, Theme, Shortcode, Media)
│   ├── Http/               # Controllers, Middleware, Requests
│   ├── Models/             # Eloquent models
│   ├── Modules/
│   │   ├── AI/             # AI service layer (drivers, actions, jobs)
│   │   ├── Media/          # Media upload, processing, stock photos
│   │   ├── Post/           # Post CRUD, revisions, scheduling, pipeline
│   │   ├── Shortcode/      # Shortcode parser, registry, handlers
│   │   ├── StaticGen/      # Static site builder, CLI command
│   │   └── Theme/          # Theme registry, renderer, config loader
│   ├── Providers/          # Service providers
│   ├── Repositories/       # Data access layer
│   └── Services/           # Cross-cutting services (Analytics, Newsletter…)
├── config/
│   ├── ai.php
│   ├── media.php
│   └── shortcodes.php
├── database/
│   ├── migrations/
│   └── seeders/
├── docs/                   # ← You are here
├── resources/
│   ├── themes/             # Theme directories (hello-world, developer, …)
│   └── views/
├── routes/
│   ├── api.php
│   └── web.php
├── FEATURES.md             # TyroPress feature reference
└── README.md
```

---

## Architecture at a Glance

```
┌─────────────────────────────────────────────────────────────┐
│                    Browser / API Client                      │
└────────────────────────────┬────────────────────────────────┘
                             │ HTTP
┌────────────────────────────▼────────────────────────────────┐
│              Laravel HTTP Layer (Routes + Middleware)        │
│         Web routes · API v1 routes · Admin (Filament)       │
└────────────────────────────┬────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────┐
│            Application Layer (Services + Repositories)       │
│   PostService · ShortcodeRegistry · ThemeRenderer · etc.    │
└──────┬──────────┬──────────┬──────────┬──────────┬──────────┘
       │          │          │          │          │
   ┌───▼──┐  ┌───▼──┐  ┌───▼──┐  ┌───▼──┐  ┌───▼──┐
   │ Post │  │Short │  │Theme │  │  AI  │  │Media │
   │Module│  │ code │  │Engine│  │ Pipe │  │System│
   └───┬──┘  └──────┘  └──────┘  └──────┘  └──────┘
       │
┌──────▼──────────────────────────────────────────────────────┐
│              Infrastructure Layer                            │
│    MySQL 8 · Redis · Queue Workers · Storage (S3/Local)     │
└─────────────────────────────────────────────────────────────┘
```

See [architecture.md](architecture.md) for the full breakdown and [roadmap.md](roadmap.md) to understand implementation phases.
