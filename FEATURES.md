# TyroPress — Feature Reference

> **Source:** https://tyropress.com
> **Tagline:** *"Your words, amplified."*
> **Description:** An AI-powered blogging platform built for writers who care about their craft.
> Offers Markdown-first editing, multilingual publishing, 27 themes, static site generation,
> and full data portability from day one.

---

## Table of Contents

1. [Content Management](#1-content-management)
2. [Editor — Markdown, WYSIWYG & Shortcodes](#2-editor--markdown-wysiwyg--shortcodes)
3. [AI Tools](#3-ai-tools)
4. [Multilingual & i18n Support](#4-multilingual--i18n-support)
5. [Media Handling](#5-media-handling)
6. [Theme System](#6-theme-system)
7. [Static Site Generation (SSG)](#7-static-site-generation-ssg)
8. [Security](#8-security)
9. [SEO & Taxonomy](#9-seo--taxonomy)
10. [Admin Panel](#10-admin-panel)
11. [REST API](#11-rest-api)
12. [Analytics & Insights](#12-analytics--insights)
13. [Comments & Community](#13-comments--community)
14. [Newsletter](#14-newsletter)
15. [Publishing Workflow](#15-publishing-workflow)
16. [Multi-Author & Permissions](#16-multi-author--permissions)
17. [Data Portability](#17-data-portability)
18. [Infrastructure & CDN](#18-infrastructure--cdn)
19. [Upcoming / Roadmap](#19-upcoming--roadmap)

---

## 1. Content Management

| Feature | Details |
|---|---|
| Post statuses | Published, Scheduled, Draft |
| Scheduled publishing | Set a publication date and time; platform auto-publishes |
| Password-protected posts | HMAC-signed cookies with 24-hour expiry for secure access |
| Revision history | Every edit is auto-saved; restore any previous version with one click |
| Post cloning | Duplicate any post with all tags, categories, and metadata intact |
| Tags | Flat taxonomy; auto-generated listing pages for each tag |
| Categories | Hierarchical taxonomy; auto-generated listing pages per category |
| Author pages | Every author gets a public profile with bio, avatar, and complete post archive |
| Audio attachment | Attach MP3/WAV/OGG audio to any post; built-in embedded player for "listen while you read" |
| Clean URL slugs | SEO-friendly URLs generated automatically for posts, tags, categories, and authors |

---

## 2. Editor — Markdown, WYSIWYG & Shortcodes

### Core Editor

- **Markdown-first** — Markdown is a first-class citizen, not an afterthought
- **Live preview** — real-time rendered preview while writing in Markdown
- **WYSIWYG mode** — full rich visual editor available as an alternative
- **Mid-draft mode switching** — toggle between Markdown and WYSIWYG modes at any point during editing
- **Supported content elements:** headings, lists (ordered/unordered), code blocks, tables, inline images, all shortcodes

### Rich Shortcode System

All shortcodes are rendered through a **Markdown-safe pipeline** — they don't break Markdown formatting.

| Shortcode | Embeds |
|---|---|
| YouTube | Embedded video player |
| GitHub Gists | Live code snippet viewer |
| CodePen | Embedded pen/demo |
| Mermaid diagrams | Flowcharts, sequence diagrams, etc. |
| Audio player | Inline audio with playback speed control |
| MP4 video | Self-hosted video player |
| Tweets | Embedded Twitter/X posts |
| Facebook posts | Embedded Facebook content |
| Alert blocks | Styled callout/alert blocks (info, warning, error, etc.) |

---

## 3. AI Tools

> Uses **Google Gemini** (cloud) and **Qwen** (local fallback). Bring Your Own Key (BYOK) — no usage caps from TyroPress itself.

| AI Feature | Description |
|---|---|
| **AI Summary** | Automatic long-form summary generated from post content |
| **AI Excerpt** | 30–50 word hook-style excerpt, AI-generated |
| **AI Translation** | Full post translation to target language; preserves all Markdown, links, code formatting |
| **BYOK (Bring Your Own Key)** | Users supply their own Gemini or Qwen API key; TyroPress imposes no usage limits |
| **Multi-model support** | Google Gemini (primary cloud model); Qwen as local fallback |
| **Language coverage** | English, Bengali, Japanese, Spanish, Arabic (and any language Gemini supports) |
| **Markdown integrity** | AI translation preserves code blocks, hyperlinks, and formatting exactly |
| **Auto slug sync** | When a parent post's slug changes, translated post slugs are updated automatically |

---

## 4. Multilingual & i18n Support

| Feature | Details |
|---|---|
| Multiple language versions | Create translated variants of any post linked to the parent |
| Language switcher | Automatically enabled on every post; readers can switch language inline |
| Per-language fonts | Different Google Fonts assignable per language (e.g., Bengali posts use Noto Sans Bengali) |
| Font lazy-loading | Language-specific fonts load only when needed — no performance penalty for unused languages |
| Auto slug generation | Translated slugs auto-generated from translated content |
| Slug synchronization | Translated slugs stay in sync when the parent slug changes |
| Supported font examples | Noto Sans Bengali, Hind Siliguri, Noto Sans JP, Noto Naskh Arabic, and all other Google Fonts |
| Per-language heading fonts | Separate font override for headings per language |
| Per-language body fonts | Separate font override for body copy per language |

---

## 5. Media Handling

### Upload & Processing

| Feature | Details |
|---|---|
| File upload | Upload images, audio, and video directly |
| Max file size | Up to **20 MB** per file |
| Auto WebP conversion | All uploaded images are automatically converted to WebP format |
| Thumbnail generation | Automatic thumbnail creation on upload |
| Crop & resize | In-platform image editing (crop and resize) |
| Audio formats supported | MP3, WAV, OGG |
| Video support | MP4 shortcode embedding |

### Media Library

| Feature | Details |
|---|---|
| Library organization | Search and browse all uploaded media |
| Favorites / starring | Star media items to bookmark favorites for quick access |
| SSRF protection | Server-side proxying uses SSRF protection to prevent server-side request forgery |
| Stock photo search | Search and import images from **Unsplash**, **Pixabay**, **Freepik**, and **Pexels** |

### Global CDN Delivery

| Metric | Value |
|---|---|
| Edge locations | 300+ worldwide |
| Average latency | < 50 ms |
| Uptime SLA | 99.9% |
| File size savings | ~62% smaller with WebP optimization |
| Coverage | NA East/West, EU West/Central, Asia Pacific, Southeast Asia, South America East, Middle East South, Australia East, Africa |

---

## 6. Theme System

### General

- **27 themes** available, switchable with one click
- **Full color customization** — override any theme's color palette
- **Live preview** — see color changes before publishing/saving
- **Revert to defaults** — restore original theme colors at any time
- **Granular color controls:** primary color, accent color, background color, text color, border color

### Named Themes (from landing page)

| Theme Name | Style Description |
|---|---|
| Hello World | Clean and minimal; classic blog look with a modern monospace accent |
| The Art of Writing | Warm editorial aesthetic; elegant typography for long-form reading |
| Developer | Developer-focused design; code aesthetic with a sky-blue accent |
| Medium-inspired | Clean, Medium-inspired premium reading experience; crisp typography, deep green accents |
| Stories from the Night Sky | Deep-space dark theme with warm gold accents and italic Newsreader headlines |
| Whispers of the Forest | Lush, immersive forest-vibe theme; deep greens, earthy tones, organic textures |
| Monolith | Stark, typography-driven brutalist design; high contrast and highly functional |
| KAPOW! | Bold comic-book theme with halftone textures, sticker-like panels, loud typography |
| *(+ 19 more)* | Additional themes available in platform |

---

## 7. Static Site Generation (SSG)

| Feature | Details |
|---|---|
| Static HTML export | Generate a full static HTML site from any installed theme |
| ZIP download | Download the complete built site as a ZIP archive |
| Full build history | All previous builds are stored and accessible |
| Per-theme builds | Target a specific theme at build time |
| Real-time build progress | Live progress tracking during the build process |
| Image optimization | Images are optimized (WebP, resized) automatically during the build |
| CLI interface | `php artisan blog:build --theme=<theme-name>` |
| Build output info | Reports file count, image count, and ZIP size upon completion |

---

## 8. Security

### Authentication

| Feature | Details |
|---|---|
| Two-Factor Authentication (2FA) | TOTP-based; works with any authenticator app (Google Authenticator, Authy, etc.) |
| OTP Login | One-time password delivered via email |
| Magic Login Links | Passwordless login via email link |
| Password protection (posts) | HMAC-signed cookies; 24-hour expiry per session |

### API Security

| Feature | Details |
|---|---|
| Token-based auth | Secure API endpoints with token-based authentication |
| HMAC-signed requests | Requests signed with HMAC for bulletproof integrity verification |
| IP whitelist | Restrict API access to specific IP addresses only |
| CORS configuration | Whitelist specific domains; block unauthorized cross-origin requests |

### Infrastructure Security

| Feature | Details |
|---|---|
| SSRF protection | Server-side image proxying uses SSRF-protected implementation |

---

## 9. SEO & Taxonomy

### Per-Post SEO Controls

| Feature | Details |
|---|---|
| Custom meta title | Override the post title for `<title>` and search engines |
| Custom meta description | Custom description for search result snippets |
| Open Graph image | Custom OG image per post for social sharing previews |
| Canonical URL | Per-post canonical URL to avoid duplicate content issues |
| noindex flag | Mark individual posts as noindex to exclude from search engines |
| Twitter Card | Automatic Twitter Card metadata for rich link previews on X/Twitter |
| Facebook sharing | Facebook sharing metadata (Open Graph) supported out of the box |

### Site-Level SEO

| Feature | Details |
|---|---|
| XML Sitemap | Auto-generated with priority and frequency settings |
| robots.txt | Automatically generated |
| Structured data | JSON-LD structured data for rich snippets in search results |

### Taxonomy

| Feature | Details |
|---|---|
| Tags | Flat taxonomy with auto-generated tag listing pages |
| Categories | Structured taxonomy with auto-generated category listing pages |
| Author pages | Public author profiles with complete post archive |
| SEO-friendly URLs | Clean slug-based URLs for all taxonomy and author pages |

---

## 10. Admin Panel

| Feature | Details |
|---|---|
| Post management views | Filter posts by All, Published, Scheduled, Draft |
| Dashboard theming | Full sidebar color customization to match brand identity |
| Google Analytics integration | Built-in GA support; no code changes required |
| Meta Pixel integration | Built-in Facebook/Meta Pixel support; no code changes required |
| User/author management | Invite authors with role assignments |
| Newsletter management | Search subscribers, filter by status, export to CSV |
| Comment moderation | Approve/reject comments from the admin panel |
| Media library | Browse, search, star, upload, and delete media assets |
| Backup & restore | Granular backup and restore |
| WordPress import | Import wizard with live progress |
| SSG build trigger | Launch static site builds from within the admin panel |
| Analytics dashboard | View page views, unique visitors, top posts, top countries, most-commented posts |

---

## 11. REST API

> TyroPress is **API-first / headless from day one**.

### Public Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/v1/posts` | List all published posts |
| `GET` | `/v1/posts/:slug` | Single post with full body, tags, categories, author, and translations |
| `GET` | `/v1/categories` | All categories with post counts |
| `GET` | `/v1/tags` | All tags with usage counts |
| `GET` | `/v1/authors` | Author profiles and bios |

### API Security Controls

| Control | Details |
|---|---|
| Token authentication | Bearer token-based auth for protected endpoints |
| HMAC signing | Request signing for integrity verification |
| IP whitelist | Allowlist specific IPs at the API gateway level |
| CORS | Whitelist allowed origins |

### Compatible Headless Consumers

Next.js · Nuxt · React · Vue · Svelte · Astro · Remix · Flutter

---

## 12. Analytics & Insights

| Feature | Details |
|---|---|
| Page view tracking | Privacy-first; no third-party tracking by default |
| Unique visitor counting | Session-based deduplication to prevent inflation |
| Country detection | Identifies visitor country per page view |
| Daily views chart | Selectable timeframes: 7 days, 30 days, 90 days |
| Top 10 posts | Most-viewed posts ranking |
| Top 10 countries | Geographic breakdown of visitors |
| Most-commented posts | Posts ranked by comment count |
| Session deduplication | Prevents repeated visits in one session from inflating counts |
| Third-party integrations | Google Analytics and Meta Pixel (configurable in admin) |

---

## 13. Comments & Community

| Feature | Details |
|---|---|
| Nested comments | Up to **3 levels** of replies (threaded conversations) |
| Gravatar support | Commenter avatars pulled from Gravatar |
| Country tracking | Each comment logs the commenter's detected country |
| Moderation queue | Comments can require admin/editor approval before publishing |
| Auto-approval | Option to auto-approve comments without moderation |
| Comment management | Admin panel interface for reviewing and moderating comments |

---

## 14. Newsletter

| Feature | Details |
|---|---|
| Built-in subscriber system | Collect email subscribers without a third-party service |
| Subscriber search | Search subscribers by name or email from the admin panel |
| Status filters | Filter subscribers by status (active, unsubscribed, etc.) |
| CSV export | Export subscriber list as RFC 4180-compliant CSV |

---

## 15. Publishing Workflow

| Feature | Details |
|---|---|
| Draft mode | Save work-in-progress without publishing |
| Scheduled publishing | Set a future date and time; post auto-publishes |
| Immediate publish | Publish live with a single click |
| Password protection | Gate any post behind a password |
| Revision history | Auto-saved drafts with full version history and one-click restore |
| Post cloning | Duplicate any post (including all metadata, tags, categories) |
| Post statuses | Published · Scheduled · Draft |

---

## 16. Multi-Author & Permissions

### Roles

| Role | Permissions |
|---|---|
| **Admin** | Full access to all platform settings, users, posts, media, and configuration |
| **Editor** | Can review, edit, and publish *any* post; full editorial control |
| **Author** | Can create and edit their own posts only |

### Author Profiles

- Every author gets a **public profile page**
- Profile includes: bio, avatar, and complete post archive
- Profiles accessible via clean, SEO-friendly author URLs

---

## 17. Data Portability

### WordPress Import

| Feature | Details |
|---|---|
| Source support | WordPress.org and WordPress.com blogs |
| Auto-detection | Automatically detects REST API endpoints from the blog URL |
| Import modes | **Append** (add to existing content) or **Fresh** (replace all content) |
| Live progress | Real-time progress tracking during import |
| What's imported | Posts, pages, media, categories, and tags |

### Full JSON Export

| Feature | Details |
|---|---|
| Export scope | Posts, categories, tags, authors, media references, settings, branding |
| Format | Structured JSON |
| Re-import | One-click re-import into any TyroPress instance |
| Import modes | Append or replace |
| Slug preservation | Slug structure preserved on re-import |
| Media mapping | Media references are mapped during import |

### Backup & Restore

| Feature | Details |
|---|---|
| Granular backup | Choose what to back up: settings, branding, users, content, media (individually or all) |
| Restore | Full or selective restore from any backup |

---

## 18. Infrastructure & CDN

| Feature | Details |
|---|---|
| Global CDN | Every image auto-distributed across global edge network |
| Edge locations | 300+ worldwide |
| Average CDN latency | < 50 ms |
| Uptime SLA | 99.9% |
| Image optimization | Auto-WebP with ~62% size reduction |
| Regions covered | NA East, NA West, EU West, EU Central, Asia Pacific, Southeast Asia, South America East, Middle East South, Australia East, Africa |
| Free to start | No credit card required; setup in under 2 minutes |

---

## 19. Upcoming / Roadmap

| Feature | Status | Notes |
|---|---|---|
| **Form Builder** | 🔜 Upcoming | Drag-and-drop form creation with customizable fields and submission handling |

---

## Platform Summary

| Capability | Status |
|---|---|
| 27 Themes | ✅ Available |
| AI-Powered Writing | ✅ Available (Gemini + Qwen) |
| SSG (Static Site Generation) | ✅ Available |
| i18n / Multilingual | ✅ Available |
| Markdown-First Editor | ✅ Available |
| WYSIWYG Editor | ✅ Available |
| REST API / Headless | ✅ Available |
| WordPress Import | ✅ Available |
| Global CDN | ✅ Available |
| 2FA / OTP / Magic Links | ✅ Available |
| Comments & Newsletter | ✅ Available |
| Privacy-First Analytics | ✅ Available |
| Google Analytics & Meta Pixel | ✅ Available |
| Form Builder | 🔜 Upcoming |
| Open Source | ❌ Not available |
| Self-Hosted Option | ❌ Not advertised |

---

*Last updated: 2026-05-05 — sourced from https://tyropress.com landing page copy and UI demonstrations.*
