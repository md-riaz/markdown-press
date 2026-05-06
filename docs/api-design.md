# API Design — MarkdownPress REST API

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Public Endpoints](#public-endpoints)
4. [Protected Endpoints](#protected-endpoints)
5. [Response Schemas](#response-schemas)
6. [Rate Limiting](#rate-limiting)
7. [CORS Configuration](#cors-configuration)

---

## 1. Overview

MarkdownPress exposes a fully API-first headless interface, enabling decoupled frontends, mobile clients, static site generators, and third-party integrations to consume content independently of the Laravel Blade rendering layer. Every feature available through the admin UI is also available via the REST API.

### Base URL

```
https://your-domain.com/api/v1
```

All endpoints are prefixed with `/api/v1`. The version segment is part of the URL path (not a header) to make versioning explicit and cacheable at the CDN layer.

### Versioning Strategy

| Version | Status     | Sunset Date  |
|---------|------------|--------------|
| v1      | Current    | —            |
| v2      | Planned    | —            |

- A new major version (`/api/v2`) is introduced only for breaking changes.
- Additive changes (new fields, new optional parameters) are made in-place without a version bump.
- Deprecated endpoints return a `Sunset` response header with the date they will be removed, providing at least 12 months of notice.
- The `X-API-Version` response header echoes the effective API version used to serve each request.

### Response Envelope

All successful responses wrap their payload in a consistent envelope:

```json
{
  "data": { },
  "meta": {
    "total": 0,
    "page": 1,
    "per_page": 15,
    "last_page": 1
  },
  "links": {
    "first": "https://your-domain.com/api/v1/posts?page=1",
    "last": "https://your-domain.com/api/v1/posts?page=4",
    "prev": null,
    "next": "https://your-domain.com/api/v1/posts?page=2"
  }
}
```

- `data` — the primary payload; an object for single-resource endpoints, an array for collection endpoints.
- `meta` — pagination bookkeeping; omitted on single-resource responses.
- `links` — cursor-style navigation URLs; omitted on single-resource responses.

### Error Envelope

All error responses (4xx, 5xx) use a consistent structure:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "tag_ids.0": ["The selected tag does not exist."]
  }
}
```

- `message` — a human-readable summary of the error.
- `errors` — a map of field names to arrays of validation messages; present only for `422 Unprocessable Entity` responses.

### HTTP Status Codes

| Code | Meaning                                         |
|------|-------------------------------------------------|
| 200  | OK — successful GET, PUT, PATCH                 |
| 201  | Created — successful POST that creates a resource |
| 204  | No Content — successful DELETE                  |
| 400  | Bad Request — malformed request syntax          |
| 401  | Unauthorized — missing or invalid token         |
| 403  | Forbidden — authenticated but not authorized    |
| 404  | Not Found — resource does not exist             |
| 409  | Conflict — duplicate resource (e.g., slug)      |
| 422  | Unprocessable Entity — validation failed        |
| 429  | Too Many Requests — rate limit exceeded         |
| 500  | Internal Server Error                           |

### Content Type

All requests and responses use `Content-Type: application/json` unless otherwise noted (e.g., multipart uploads).

---

## 2. Authentication

### Bearer Token

Protected endpoints require an `Authorization` header:

```
Authorization: Bearer <token>
```

Tokens are opaque strings issued by the `/api/v1/auth/token` endpoint and stored as hashed values using Laravel Sanctum. Each token can be scoped to a subset of capabilities (see token scopes below).

### Obtaining a Token

**`POST /api/v1/auth/token`**

Request:

```json
{
  "email": "author@example.com",
  "password": "secret",
  "token_name": "my-headless-app",
  "scopes": ["posts:read", "posts:write", "media:write"]
}
```

Response `201 Created`:

```json
{
  "data": {
    "token": "3|abc123...",
    "token_type": "Bearer",
    "scopes": ["posts:read", "posts:write", "media:write"],
    "expires_at": null
  }
}
```

- `token_name` is a human-readable label shown in the admin token management UI.
- `expires_at` is null for non-expiring tokens; pass an ISO 8601 datetime to create a short-lived token.

**`DELETE /api/v1/auth/token`** — revoke the currently authenticated token.

**`GET /api/v1/auth/tokens`** — list all tokens belonging to the authenticated user (requires `tokens:manage` scope).

### Token Scopes

| Scope             | Grants access to                                  |
|-------------------|---------------------------------------------------|
| `posts:read`      | Reading draft/private posts via API               |
| `posts:write`     | Creating, updating, deleting posts                |
| `media:read`      | Reading private media metadata                    |
| `media:write`     | Uploading and deleting media                      |
| `taxonomy:write`  | Creating/updating categories and tags             |
| `ai:use`          | Calling AI generation endpoints                   |
| `builds:trigger`  | Triggering static site builds                     |
| `tokens:manage`   | Listing and revoking tokens                       |
| `*`               | All scopes (superuser; admin accounts only)       |

### HMAC Request Signing (Optional)

For server-to-server integrations where long-lived tokens are undesirable, MarkdownPress supports HMAC-SHA256 request signing.

Each API application is issued a key pair: a `Client-ID` and a `Client-Secret`. To sign a request:

1. Construct the signing string: `METHOD\nPATH\nTIMESTAMP\nSHA256(request_body)`  
   Example: `POST\n/api/v1/posts\n1712000000\ne3b0c44298fc1c149afb...`
2. Compute `HMAC-SHA256(signing_string, Client-Secret)` and hex-encode the result.
3. Include the following headers in the request:

```
X-Client-ID: <your_client_id>
X-Timestamp: <unix_timestamp>
X-Signature: <hex_hmac>
```

Requests are rejected if the `X-Timestamp` is more than 300 seconds from the server's current time (replay protection). HMAC-authenticated requests bypass the `Authorization: Bearer` check but are still subject to scope enforcement based on the client's registered permissions.

### IP Allowlist

Tokens and HMAC client registrations may be locked to specific IP addresses or CIDR ranges in the admin UI (`Settings → API → Token Management`). Requests from non-allowlisted IPs receive a `403 Forbidden` response regardless of token validity.

---

## 3. Public Endpoints

No authentication is required for these endpoints. They return only published, public-visibility content. Drafts, private posts, and archived content are excluded unless a valid Bearer token is also supplied.

---

### Posts

#### `GET /api/v1/posts`

Returns a paginated list of published posts, ordered by `published_at` descending by default.

**Query Parameters:**

| Parameter  | Type    | Default | Description                                              |
|------------|---------|---------|----------------------------------------------------------|
| `page`     | integer | 1       | Page number                                              |
| `per_page` | integer | 15      | Items per page (max 100)                                 |
| `category` | string  | —       | Filter by category slug                                  |
| `tag`      | string  | —       | Filter by tag slug                                       |
| `locale`   | string  | —       | Filter by locale code (e.g., `en`, `fr`, `ja`)           |
| `q`        | string  | —       | Full-text search against title and content               |
| `sort`     | string  | `date`  | Sort field: `date`, `title`, `views`                     |
| `order`    | string  | `desc`  | Sort direction: `asc`, `desc`                            |
| `author`   | string  | —       | Filter by author username                                |

**Example Request:**

```
GET /api/v1/posts?category=tutorials&locale=en&page=2&per_page=10
```

**Example Response `200 OK`:**

```json
{
  "data": [
    {
      "id": 42,
      "slug": "getting-started-with-laravel",
      "title": "Getting Started with Laravel",
      "excerpt": "A beginner-friendly introduction to the Laravel framework.",
      "status": "published",
      "locale": "en",
      "reading_time_minutes": 6,
      "published_at": "2025-03-01T10:00:00Z",
      "updated_at": "2025-04-15T08:23:00Z",
      "author": {
        "id": 5,
        "username": "jane_dev",
        "display_name": "Jane Developer",
        "avatar_url": "https://your-domain.com/storage/avatars/jane.jpg"
      },
      "categories": [
        { "id": 3, "slug": "tutorials", "name": "Tutorials" }
      ],
      "tags": [
        { "id": 7, "slug": "laravel", "name": "Laravel" }
      ],
      "cover_image_url": "https://your-domain.com/storage/covers/laravel-intro.jpg"
    }
  ],
  "meta": {
    "total": 87,
    "page": 2,
    "per_page": 10,
    "last_page": 9
  },
  "links": {
    "first": "https://your-domain.com/api/v1/posts?page=1",
    "last": "https://your-domain.com/api/v1/posts?page=9",
    "prev": "https://your-domain.com/api/v1/posts?page=1",
    "next": "https://your-domain.com/api/v1/posts?page=3"
  }
}
```

---

#### `GET /api/v1/posts/{slug}`

Returns a single published post by its URL slug, including the rendered HTML body.

**Path Parameters:**

| Parameter | Type   | Description       |
|-----------|--------|-------------------|
| `slug`    | string | The post URL slug |

**Query Parameters:**

| Parameter | Type   | Description                                     |
|-----------|--------|-------------------------------------------------|
| `locale`  | string | Return the translation for this locale if it exists |

**Example Response `200 OK`:**

```json
{
  "data": {
    "id": 42,
    "slug": "getting-started-with-laravel",
    "title": "Getting Started with Laravel",
    "content_markdown": "# Getting Started\n\nLaravel is a PHP framework...",
    "body_html": "<h1>Getting Started</h1><p>Laravel is a PHP framework...</p>",
    "excerpt": "A beginner-friendly introduction to the Laravel framework.",
    "status": "published",
    "locale": "en",
    "reading_time_minutes": 6,
    "published_at": "2025-03-01T10:00:00Z",
    "updated_at": "2025-04-15T08:23:00Z",
    "meta_title": "Getting Started with Laravel — MarkdownPress",
    "meta_description": "Learn Laravel from scratch with this beginner-friendly guide.",
    "canonical_url": "https://your-domain.com/blog/getting-started-with-laravel",
    "author": {
      "id": 5,
      "username": "jane_dev",
      "display_name": "Jane Developer",
      "bio": "PHP developer and open-source contributor.",
      "avatar_url": "https://your-domain.com/storage/avatars/jane.jpg"
    },
    "categories": [
      { "id": 3, "slug": "tutorials", "name": "Tutorials", "parent_id": null }
    ],
    "tags": [
      { "id": 7, "slug": "laravel", "name": "Laravel" },
      { "id": 11, "slug": "php", "name": "PHP" }
    ],
    "cover_image_url": "https://your-domain.com/storage/covers/laravel-intro.jpg",
    "translations": [
      { "locale": "fr", "slug": "debuter-avec-laravel", "title": "Débuter avec Laravel", "url": "/api/v1/posts/debuter-avec-laravel" },
      { "locale": "ja", "slug": "laravel-nyumon", "title": "Laravel入門", "url": "/api/v1/posts/laravel-nyumon" }
    ]
  }
}
```

---

#### `GET /api/v1/posts/{slug}/translations`

Returns all language versions of a post as a collection.

**Example Response `200 OK`:**

```json
{
  "data": [
    {
      "locale": "en",
      "slug": "getting-started-with-laravel",
      "title": "Getting Started with Laravel",
      "status": "published",
      "published_at": "2025-03-01T10:00:00Z"
    },
    {
      "locale": "fr",
      "slug": "debuter-avec-laravel",
      "title": "Débuter avec Laravel",
      "status": "published",
      "published_at": "2025-03-05T12:00:00Z"
    }
  ]
}
```

---

### Taxonomy

#### `GET /api/v1/categories`

Returns all categories with a count of published posts in each.

**Query Parameters:**

| Parameter | Type   | Description                                |
|-----------|--------|--------------------------------------------|
| `locale`  | string | Return translated category names           |
| `parent`  | string | Filter to children of the given slug       |

**Example Response `200 OK`:**

```json
{
  "data": [
    {
      "id": 1,
      "slug": "engineering",
      "name": "Engineering",
      "description": "Technical deep-dives and architecture posts.",
      "parent_id": null,
      "post_count": 34,
      "children": [
        { "id": 3, "slug": "tutorials", "name": "Tutorials", "post_count": 12, "children": [] }
      ]
    }
  ]
}
```

---

#### `GET /api/v1/categories/{slug}`

Returns a single category along with its paginated posts.

**Query Parameters:** Same pagination and `locale` parameters as `GET /api/v1/posts`.

**Example Response `200 OK`:**

```json
{
  "data": {
    "id": 3,
    "slug": "tutorials",
    "name": "Tutorials",
    "description": "Step-by-step guides for developers.",
    "parent_id": 1,
    "post_count": 12,
    "posts": [
      {
        "id": 42,
        "slug": "getting-started-with-laravel",
        "title": "Getting Started with Laravel",
        "excerpt": "A beginner-friendly introduction...",
        "published_at": "2025-03-01T10:00:00Z"
      }
    ]
  },
  "meta": { "total": 12, "page": 1, "per_page": 15, "last_page": 1 },
  "links": { "first": null, "last": null, "prev": null, "next": null }
}
```

---

#### `GET /api/v1/tags`

Returns all tags with a count of published posts in each.

```json
{
  "data": [
    { "id": 7, "slug": "laravel", "name": "Laravel", "post_count": 22 },
    { "id": 11, "slug": "php", "name": "PHP", "post_count": 45 }
  ]
}
```

---

#### `GET /api/v1/tags/{slug}`

Returns a single tag along with its paginated posts. Response structure mirrors `GET /api/v1/categories/{slug}`.

---

### Authors

#### `GET /api/v1/authors`

Returns all public author profiles.

```json
{
  "data": [
    {
      "id": 5,
      "username": "jane_dev",
      "display_name": "Jane Developer",
      "bio": "PHP developer and open-source contributor.",
      "avatar_url": "https://your-domain.com/storage/avatars/jane.jpg",
      "website_url": "https://janedev.io",
      "twitter_handle": "jane_dev",
      "post_count": 18
    }
  ]
}
```

---

#### `GET /api/v1/authors/{username}`

Returns a single author profile along with their paginated published posts.

**Query Parameters:** Same pagination parameters as `GET /api/v1/posts`.

**Example Response `200 OK`:**

```json
{
  "data": {
    "id": 5,
    "username": "jane_dev",
    "display_name": "Jane Developer",
    "bio": "PHP developer and open-source contributor.",
    "avatar_url": "https://your-domain.com/storage/avatars/jane.jpg",
    "website_url": "https://janedev.io",
    "twitter_handle": "jane_dev",
    "post_count": 18,
    "posts": [
      {
        "id": 42,
        "slug": "getting-started-with-laravel",
        "title": "Getting Started with Laravel",
        "excerpt": "A beginner-friendly introduction...",
        "published_at": "2025-03-01T10:00:00Z"
      }
    ]
  },
  "meta": { "total": 18, "page": 1, "per_page": 15, "last_page": 2 },
  "links": { "first": null, "last": null, "prev": null, "next": "https://your-domain.com/api/v1/authors/jane_dev?page=2" }
}
```

---

### Media

#### `GET /api/v1/media/{id}`

Returns metadata for a publicly accessible media file.

**Example Response `200 OK`:**

```json
{
  "data": {
    "id": 101,
    "filename": "laravel-intro.jpg",
    "url": "https://your-domain.com/storage/media/laravel-intro.jpg",
    "mime_type": "image/jpeg",
    "size_bytes": 204800,
    "width": 1200,
    "height": 630,
    "alt_text": "Laravel framework logo on a dark background",
    "starred": false,
    "created_at": "2025-02-10T14:30:00Z"
  }
}
```

---

## 4. Protected Endpoints

All endpoints in this section require a valid `Authorization: Bearer <token>` header. Requests without a token receive `401 Unauthorized`. Requests with a token that lacks the required scope receive `403 Forbidden`.

---

### Post Management

#### `POST /api/v1/posts`

Creates a new post. Requires `posts:write` scope.

**Request Body:**

```json
{
  "title": "Deploying Laravel with Docker",
  "content_markdown": "# Deploying Laravel\n\nThis guide covers...",
  "status": "draft",
  "locale": "en",
  "slug": "deploying-laravel-with-docker",
  "excerpt": "A complete guide to containerising a Laravel application.",
  "category_ids": [3, 8],
  "tag_ids": [7, 14, 22],
  "cover_image_id": 101,
  "meta_title": "Deploying Laravel with Docker — MarkdownPress",
  "meta_description": "Learn to containerise Laravel using Docker Compose.",
  "canonical_url": null,
  "published_at": null
}
```

**Field Reference:**

| Field               | Type          | Required | Description                                                  |
|---------------------|---------------|----------|--------------------------------------------------------------|
| `title`             | string        | Yes      | Post title (max 255 chars)                                   |
| `content_markdown`  | string        | Yes      | Raw Markdown source                                          |
| `status`            | string        | Yes      | `draft`, `published`, `private`, `scheduled`                 |
| `locale`            | string        | No       | BCP 47 locale code; defaults to site default locale          |
| `slug`              | string        | No       | Auto-derived from title if omitted; must be unique           |
| `excerpt`           | string        | No       | Manual excerpt; auto-generated from content if omitted       |
| `category_ids`      | integer[]     | No       | Array of category IDs to attach                              |
| `tag_ids`           | integer[]     | No       | Array of tag IDs to attach                                   |
| `cover_image_id`    | integer       | No       | ID of a media record to use as cover image                   |
| `meta_title`        | string        | No       | SEO title override (max 70 chars)                            |
| `meta_description`  | string        | No       | SEO meta description (max 160 chars)                         |
| `canonical_url`     | string\|null  | No       | Canonical URL if content is republished from elsewhere       |
| `published_at`      | datetime\|null| No       | ISO 8601; required when `status` is `scheduled`              |

**Response `201 Created`:** Full Post resource (see [Response Schemas](#response-schemas)).

---

#### `PUT /api/v1/posts/{slug}`

Replaces a post's content and metadata. All fields from `POST /api/v1/posts` are accepted. A revision is automatically created before the update is applied. Requires `posts:write` scope.

**Response `200 OK`:** Updated Post resource.

---

#### `DELETE /api/v1/posts/{slug}`

Soft-deletes a post (moves it to trash). The post is no longer returned by public endpoints but remains retrievable via admin endpoints for 30 days before permanent deletion. Requires `posts:write` scope.

**Response `204 No Content`**

---

#### `POST /api/v1/posts/{slug}/clone`

Creates a duplicate of the post with status `draft`, appending ` (Copy)` to the title and generating a new unique slug. Requires `posts:write` scope.

**Response `201 Created`:** New Post resource.

---

#### `GET /api/v1/posts/{slug}/revisions`

Returns the revision history for a post. Requires `posts:read` scope.

**Example Response `200 OK`:**

```json
{
  "data": [
    {
      "id": 7,
      "post_id": 42,
      "title": "Deploying Laravel with Docker (v2)",
      "created_at": "2025-04-10T09:15:00Z",
      "created_by": { "id": 5, "username": "jane_dev", "display_name": "Jane Developer" }
    }
  ]
}
```

---

#### `POST /api/v1/posts/{slug}/revisions/{id}/restore`

Restores a post to the content of the specified revision. The current state is saved as a new revision before the restore. Requires `posts:write` scope.

**Response `200 OK`:** Updated Post resource.

---

### Media

#### `POST /api/v1/media`

Uploads a new media file. Requires `media:write` scope.

**Request:** `multipart/form-data`

| Field     | Type   | Required | Description                                              |
|-----------|--------|----------|----------------------------------------------------------|
| `file`    | file   | Yes      | Binary file data                                         |
| `alt_text`| string | No       | Accessibility alt text                                   |
| `folder`  | string | No       | Virtual folder path (e.g., `covers/2025`)                |

**Accepted MIME types:** `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`, `video/mp4`, `audio/mpeg`, `audio/wav`, `application/pdf`

**Maximum file size:** Configured per installation (default 20 MB).

**Response `201 Created`:** Media resource.

---

#### `DELETE /api/v1/media/{id}`

Deletes a media file and removes it from storage. Fails with `409 Conflict` if the file is referenced by a published post. Requires `media:write` scope.

**Response `204 No Content`**

---

#### `PATCH /api/v1/media/{id}/star`

Toggles the starred status of a media file (used to mark frequently used assets in the media library). Requires `media:write` scope.

**Response `200 OK`:**

```json
{
  "data": { "id": 101, "starred": true }
}
```

---

### AI

All AI endpoints require `ai:use` scope and are metered separately (see [Rate Limiting](#rate-limiting)). AI features are disabled if no AI provider is configured (`OPENAI_API_KEY` or equivalent).

#### `POST /api/v1/ai/summary`

Generates a summary of a post's content.

**Request Body:**

```json
{
  "post_slug": "deploying-laravel-with-docker",
  "max_sentences": 3
}
```

**Response `200 OK`:**

```json
{
  "data": {
    "summary": "This guide walks through containerising a Laravel application using Docker Compose. It covers creating a Dockerfile, configuring services for PHP-FPM and Nginx, and managing environment variables securely. The post also demonstrates zero-downtime deployments using rolling restarts."
  }
}
```

---

#### `POST /api/v1/ai/excerpt`

Generates a one-paragraph excerpt suitable for use in post listings and SEO meta descriptions.

**Request Body:**

```json
{
  "post_slug": "deploying-laravel-with-docker",
  "max_chars": 160
}
```

**Response `200 OK`:**

```json
{
  "data": {
    "excerpt": "Learn to containerise a Laravel application with Docker Compose, covering Dockerfiles, Nginx configuration, and zero-downtime deployments."
  }
}
```

---

#### `POST /api/v1/ai/translate`

Translates a post's title and content into the specified locale and creates a draft translation post linked to the original. Requires both `ai:use` and `posts:write` scopes.

**Request Body:**

```json
{
  "post_slug": "deploying-laravel-with-docker",
  "target_locale": "fr",
  "create_draft": true
}
```

**Response `201 Created`:** New draft Post resource in the target locale.

---

### Static Build

#### `POST /api/v1/builds`

Triggers a new static site build. Queues a background job that renders all published posts to static HTML/JSON files. Requires `builds:trigger` scope.

**Request Body:** (all fields optional)

```json
{
  "mode": "full",
  "notify_url": "https://hooks.example.com/build-complete"
}
```

| Field        | Type   | Default | Description                                             |
|--------------|--------|---------|---------------------------------------------------------|
| `mode`       | string | `full`  | `full` (rebuild all) or `incremental` (changed only)    |
| `notify_url` | string | —       | Webhook URL to POST a build result payload to on completion |

**Response `202 Accepted`:**

```json
{
  "data": {
    "id": "bld_8f3a9c",
    "status": "queued",
    "mode": "full",
    "triggered_at": "2025-05-01T11:00:00Z",
    "triggered_by": { "id": 5, "username": "jane_dev" }
  }
}
```

---

#### `GET /api/v1/builds`

Returns a paginated list of past and current builds. Requires `builds:trigger` scope.

---

#### `GET /api/v1/builds/{id}`

Returns the status and result of a specific build.

**Example Response `200 OK`:**

```json
{
  "data": {
    "id": "bld_8f3a9c",
    "status": "completed",
    "mode": "full",
    "pages_generated": 312,
    "duration_seconds": 47,
    "triggered_at": "2025-05-01T11:00:00Z",
    "completed_at": "2025-05-01T11:00:47Z",
    "download_url": "https://your-domain.com/storage/builds/bld_8f3a9c.zip",
    "download_expires_at": "2025-05-02T11:00:47Z",
    "log_url": "https://your-domain.com/api/v1/builds/bld_8f3a9c/log",
    "errors": []
  }
}
```

**Build statuses:** `queued`, `running`, `completed`, `failed`

---

## 5. Response Schemas

### Post Resource

```json
{
  "id": 42,
  "slug": "getting-started-with-laravel",
  "title": "Getting Started with Laravel",
  "content_markdown": "# Getting Started\n\n...",
  "body_html": "<h1>Getting Started</h1>...",
  "excerpt": "A beginner-friendly introduction to the Laravel framework.",
  "status": "published",
  "locale": "en",
  "reading_time_minutes": 6,
  "views": 1840,
  "published_at": "2025-03-01T10:00:00Z",
  "updated_at": "2025-04-15T08:23:00Z",
  "deleted_at": null,
  "meta_title": "Getting Started with Laravel — MarkdownPress",
  "meta_description": "Learn Laravel from scratch with this beginner-friendly guide.",
  "canonical_url": null,
  "cover_image_url": "https://your-domain.com/storage/covers/laravel-intro.jpg",
  "author": { "$ref": "AuthorResource" },
  "categories": [{ "$ref": "CategoryResource" }],
  "tags": [{ "$ref": "TagResource" }],
  "translations": [{ "$ref": "PostTranslationResource" }]
}
```

### PostTranslation Resource

```json
{
  "locale": "fr",
  "locale_label": "French",
  "slug": "debuter-avec-laravel",
  "title": "Débuter avec Laravel",
  "status": "published",
  "published_at": "2025-03-05T12:00:00Z",
  "url": "https://your-domain.com/api/v1/posts/debuter-avec-laravel"
}
```

### Category Resource

```json
{
  "id": 3,
  "slug": "tutorials",
  "name": "Tutorials",
  "description": "Step-by-step guides for developers.",
  "parent_id": 1,
  "parent": {
    "id": 1,
    "slug": "engineering",
    "name": "Engineering"
  },
  "post_count": 12,
  "created_at": "2024-01-10T00:00:00Z",
  "updated_at": "2025-01-20T00:00:00Z"
}
```

### Tag Resource

```json
{
  "id": 7,
  "slug": "laravel",
  "name": "Laravel",
  "post_count": 22,
  "created_at": "2024-01-10T00:00:00Z",
  "updated_at": "2025-02-14T00:00:00Z"
}
```

### Author Resource

```json
{
  "id": 5,
  "username": "jane_dev",
  "display_name": "Jane Developer",
  "email": "jane@example.com",
  "bio": "PHP developer and open-source contributor.",
  "avatar_url": "https://your-domain.com/storage/avatars/jane.jpg",
  "website_url": "https://janedev.io",
  "twitter_handle": "jane_dev",
  "github_handle": "janedev",
  "post_count": 18,
  "created_at": "2024-01-01T00:00:00Z"
}
```

> **Note:** The `email` field is only included when the request is authenticated and the token belongs to the author themselves or an admin.

### Media Resource

```json
{
  "id": 101,
  "filename": "laravel-intro.jpg",
  "original_filename": "laravel-hero.jpg",
  "url": "https://your-domain.com/storage/media/laravel-intro.jpg",
  "thumbnail_url": "https://your-domain.com/storage/media/thumb/laravel-intro.jpg",
  "mime_type": "image/jpeg",
  "size_bytes": 204800,
  "width": 1200,
  "height": 630,
  "alt_text": "Laravel framework logo on a dark background",
  "folder": "covers/2025",
  "starred": false,
  "uploaded_by": { "id": 5, "username": "jane_dev", "display_name": "Jane Developer" },
  "created_at": "2025-02-10T14:30:00Z",
  "updated_at": "2025-02-10T14:30:00Z"
}
```

### Build Resource

```json
{
  "id": "bld_8f3a9c",
  "status": "completed",
  "mode": "full",
  "pages_generated": 312,
  "duration_seconds": 47,
  "triggered_at": "2025-05-01T11:00:00Z",
  "completed_at": "2025-05-01T11:00:47Z",
  "triggered_by": { "id": 5, "username": "jane_dev", "display_name": "Jane Developer" },
  "download_url": "https://your-domain.com/storage/builds/bld_8f3a9c.zip",
  "download_expires_at": "2025-05-02T11:00:47Z",
  "log_url": "https://your-domain.com/api/v1/builds/bld_8f3a9c/log",
  "errors": []
}
```

---

## 6. Rate Limiting

Rate limits are enforced per IP address for unauthenticated requests and per token for authenticated requests. When a limit is exceeded, the API returns `429 Too Many Requests` with a `Retry-After` header indicating the number of seconds until the window resets.

### Limit Table

| Endpoint Group                    | Unauthenticated | Authenticated (standard) | Authenticated (admin) |
|-----------------------------------|-----------------|---------------------------|-----------------------|
| Public read (posts, categories, tags, authors) | 120 req/min     | 300 req/min               | 600 req/min           |
| Post write (create, update, delete) | —               | 30 req/min                | 120 req/min           |
| Media upload                      | —               | 10 req/min                | 60 req/min            |
| AI endpoints                      | —               | 10 req/min                | 30 req/min            |
| Build trigger                     | —               | 2 req/min                 | 10 req/min            |
| Auth token issuance               | 5 req/min       | 5 req/min                 | 20 req/min            |

Rate limit status is communicated via response headers on every request:

```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 87
X-RateLimit-Reset: 1714560000
```

Rate limit windows are **sliding windows** to prevent burst abuse at the boundary. Laravel's `throttle` middleware backed by Redis is used for accuracy across multiple application instances.

### Custom Limits

Custom rate limits can be configured per token in the admin UI (`Settings → API → Token Management`). This is useful for granting elevated limits to trusted automation clients or webhooks.

---

## 7. CORS Configuration

CORS is configured in `config/cors.php` using Laravel's built-in CORS middleware (`fruitcake/laravel-cors` is included by default in Laravel 13).

### Recommended Production Configuration

```php
// config/cors.php
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-Client-ID',
        'X-Timestamp',
        'X-Signature',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-API-Version',
        'Sunset',
    ],

    'max_age' => 86400,

    'supports_credentials' => false,
];
```

### Environment Variable

Set `CORS_ALLOWED_ORIGINS` in `.env` to a comma-separated list of allowed origins:

```env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://www.example.com
```

Use `*` to allow all origins only in local development. **Never use `*` in production** — it disables the same-origin protection that prevents credential theft via cross-site requests.

### Preflight Requests

The API handles `OPTIONS` preflight requests automatically. Preflight responses are cached by the browser for `max_age` seconds (86400 = 24 hours), reducing the overhead of repeat preflight checks.

### Headers Exposed to JavaScript

The `exposed_headers` list controls which response headers the browser's `fetch`/`XMLHttpRequest` APIs can read in JavaScript. Rate limit headers and the `X-API-Version` header are intentionally exposed so client-side code can implement adaptive throttling and version-aware behaviour.
