# Media System

## 1. Overview

The MarkdownPress media system handles the full lifecycle of uploaded assets — from initial validation and persistent storage through async processing, variant generation, and delivery. It is built on top of [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary) and [intervention/image](https://image.intervention.io/), with queue-backed processing to keep upload responses fast.

### Supported Asset Types

| Type  | Accepted MIME Groups                                        | Notes                                      |
|-------|-------------------------------------------------------------|--------------------------------------------|
| Image | `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml` | Auto-converted to WebP; SVG stored as-is  |
| Audio | `audio/mpeg`, `audio/ogg`, `audio/wav`, `audio/aac`        | Stored without transcoding                 |
| Video | `video/mp4`, `video/webm`, `video/ogg`, `video/quicktime`  | Stored as-is; duration extracted if possible |

### Limits & Defaults

- **Maximum file size:** 20 MB per file
- **Disk options:** `local` (default) or `s3` — configurable per environment
- **WebP conversion:** automatic for all raster images on upload
- **Thumbnail dimensions:** 300 × 300 px (contain, no crop)
- **Medium variant width:** 800 px (aspect-ratio preserved)

### Stock Photo Search

MarkdownPress integrates with four stock photo providers via a unified `StockPhotoService` abstraction. API keys are Bring-Your-Own-Key (BYOK):

| Provider  | Driver class            | Requires API Key |
|-----------|-------------------------|------------------|
| Unsplash  | `UnsplashDriver`        | Yes              |
| Pixabay   | `PixabayDriver`         | Yes              |
| Pexels    | `PexelsDriver`          | Yes              |
| Freepik   | `FreepikDriver`         | Yes              |

---

## 2. Upload Pipeline

```
Client
  │
  │  POST /api/v1/media  (multipart/form-data)
  ▼
MediaController::store()
  │  ├─ Validate MIME type (allowlist from config/media.php)
  │  ├─ Validate max file size (20 MB)
  │  └─ Authorize (auth:sanctum — uploader must be authenticated)
  │
  ▼
spatie/laravel-medialibrary
  │  ├─ addMediaFromRequest('file')
  │  ├─ toMediaCollection('uploads', disk: config('media.disk'))
  │  └─ Persists `media` DB record (uuid, file_name, mime_type, size, path)
  │
  ▼
MediaProcessingJob::dispatch($media->id)  →  pushed to `media` queue
  │
  ▼
HTTP 201 Created  →  returns MediaResource JSON (id, url, mime_type, size, status: "processing")
```

### Request Format

```http
POST /api/v1/media
Authorization: Bearer {token}
Content-Type: multipart/form-data

file=<binary>
collection=uploads          # optional, defaults to "uploads"
alt_text=<string>           # optional, stored in media.custom_properties
```

### Response Format

```json
{
  "data": {
    "id": "01HX...",
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "file_name": "hero.jpg",
    "mime_type": "image/jpeg",
    "size": 2048000,
    "status": "processing",
    "urls": {
      "original": "https://example.com/storage/media/hero.jpg"
    },
    "created_at": "2025-01-01T00:00:00Z"
  }
}
```

Once `MediaProcessingJob` completes, the `status` field transitions to `"ready"` and the `urls` map is populated with all generated variant URLs.

---

## 3. MediaProcessingJob

**Queue:** `media`  
**Class:** `App\Jobs\MediaProcessingJob`  
**Retries:** 3 (exponential back-off: 30s, 120s, 600s)  
**Timeout:** 120 seconds

### Image Processing Flow

```
MediaProcessingJob::handle()
  │
  ├─ Retrieve original file path from Spatie media record
  │
  ├─ Open with Intervention Image (ImageManager::gd() or ::imagick())
  │
  ├─ [Variant: webp]
  │    ├─ Encode to WebP (quality: 85)
  │    ├─ Write to storage: media/{uuid}/conversions/original.webp
  │    └─ Create MediaVariant record (variant_key: "webp")
  │
  ├─ [Variant: thumb]
  │    ├─ Resize to 300×300, fit: contain (letterbox with transparent bg for PNG, white for JPEG)
  │    ├─ Encode to WebP (quality: 80)
  │    ├─ Write to storage: media/{uuid}/conversions/thumb.webp
  │    └─ Create MediaVariant record (variant_key: "thumb")
  │
  ├─ [Variant: medium]
  │    ├─ Resize width to 800 px, height proportional
  │    ├─ Encode to WebP (quality: 82)
  │    ├─ Write to storage: media/{uuid}/conversions/medium.webp
  │    └─ Create MediaVariant record (variant_key: "medium")
  │
  └─ Update media.status = "ready"
       Dispatch MediaProcessed event (broadcast on private channel media.{userId})
```

### Audio Processing Flow

```
MediaProcessingJob::handle()
  │
  ├─ Copy original to processed path: media/{uuid}/processed/{filename}
  ├─ Create MediaVariant record (variant_key: "processed", path: ...)
  └─ Update media.status = "ready"
```

No transcoding is performed. The original file is retained alongside the processed copy.

### Video Processing Flow

```
MediaProcessingJob::handle()
  │
  ├─ Store reference path (no re-encoding)
  ├─ Attempt duration extraction:
  │    └─ If `ffprobe` is available on the host:
  │         shell_exec("ffprobe -v quiet -print_format json -show_format {path}")
  │         Parse JSON → extract format.duration
  │         Store in media.custom_properties['duration']
  └─ Update media.status = "ready"
```

Duration extraction is best-effort; failure does not fail the job.

### `MediaVariant` DB Record

```
media_variants
  id            (ulid)
  media_id      (FK → media.id)
  variant_key   (string: "webp" | "thumb" | "medium" | "processed" | "cropped_{n}")
  disk          (string)
  path          (string)
  mime_type     (string)
  size          (integer, bytes)
  width         (integer|null)
  height        (integer|null)
  created_at
  updated_at
```

---

## 4. Media Library (Admin)

The admin media library is a Filament resource (`App\Filament\Resources\MediaResource`) rendered in a responsive grid of image thumbnails.

### Filters

| Filter             | Type            | Behavior                                               |
|--------------------|-----------------|--------------------------------------------------------|
| MIME type group    | Select          | Options: All, Images, Audio, Video                     |
| Upload date range  | DateRangePicker | Filters by `media.created_at`                          |
| Filename search    | TextInput       | `LIKE %query%` on `media.file_name`                    |
| Starred only       | Toggle          | Filters on `media.custom_properties->starred = true`   |

### Actions

- **View** — opens a detail slide-over showing all variant URLs, metadata, and dimensions
- **Star / Unstar** — toggles `custom_properties['starred']` boolean; updates star icon in grid
- **Copy URL** — copies the CDN/public URL of the selected variant to clipboard
- **Delete** — soft-deletes the `media` record; fires `MediaDeleted` event; removes files from disk via Spatie cleanup
- **Crop & Resize** — opens an inline Filament action modal:
  - Loads the WebP variant into a JavaScript crop UI (using [Cropper.js](https://fengyuanchen.github.io/cropperjs/))
  - Admin sets crop rectangle and optional output dimensions
  - On confirm: dispatches `MediaCropJob($media->id, $cropParams)` to `media` queue
  - `MediaCropJob` reads the original file via Intervention Image, applies crop, encodes to WebP, saves as `MediaVariant` with key `cropped_{n}` (incrementing integer)
  - Admin panel refreshes to show new variant in the detail panel

---

## 5. Stock Photo Integration

### Service Interface

```php
interface StockPhotoServiceInterface
{
    /**
     * @return array{
     *   results: array<int, array{
     *     id: string,
     *     thumbnail_url: string,
     *     full_url: string,
     *     author: string,
     *     source: string,
     *     attribution_url: string,
     *   }>,
     *   total: int,
     *   page: int,
     *   per_page: int,
     * }
     */
    public function search(string $query, int $page = 1): array;
}
```

All four drivers (`UnsplashDriver`, `PixabayDriver`, `PexelsDriver`, `FreepikDriver`) implement this interface and normalize their API responses into the same shape. The `StockPhotoService` acts as a multiplexer — it resolves the active driver based on `config('media.stock_photos.default_driver')`.

### Import Flow

```
Admin selects stock photo → clicks "Import"
  │
  ├─ POST /admin/media/stock-import  {provider, photo_id, full_url}
  │
  ├─ StockPhotoImportAction::handle()
  │    ├─ SSRF validation (see Security section)
  │    ├─ Http::get($full_url) → stream response to temp MemoryStream
  │    ├─ Build UploadedFile from stream
  │    └─ Delegate to MediaController::storeFromStream($uploadedFile, $metadata)
  │
  └─ Runs through the same upload pipeline (validation → spatie → MediaProcessingJob)
       Result: stock photo stored as a local media record with full variant set
```

### SSRF Protection

Before fetching any external image URL (stock photos or any user-supplied URL), the `SsrfGuard` utility class performs the following checks:

1. **Scheme check** — only `https://` URLs are accepted
2. **Domain allowlist** — the URL hostname must match one of the configured trusted domains in `config/media.php` under `stock_photos.trusted_domains`
3. **IP resolution check** — resolves the hostname via `dns_get_record()`; rejects if the resolved IP is:
   - A loopback address (`127.0.0.0/8`, `::1`)
   - A private range (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`)
   - A link-local address (`169.254.0.0/16`, `fe80::/10`)
   - A cloud metadata IP (`169.254.169.254` — AWS/GCP/Azure IMDS)
4. **Redirect policy** — HTTP redirects are followed up to a maximum of 2 hops; each redirected URL is re-validated against the same rules

Any failure in `SsrfGuard` throws `SsrfValidationException`, which returns HTTP 422 to the caller.

Default trusted domains per driver:

```php
'trusted_domains' => [
    'images.unsplash.com',
    'plus.unsplash.com',
    'cdn.pixabay.com',
    'images.pexels.com',
    'img.freepik.com',
],
```

---

## 6. Serving Media

### Local Storage

Files are served from Laravel's `public` disk via the `/storage/` symlink created by `php artisan storage:link`. The symlink maps `public/storage` → `storage/app/public`.

```
URL pattern:  /storage/media/{uuid}/original/{filename}
              /storage/media/{uuid}/conversions/{variant}.webp
```

### S3 Storage

When `MEDIA_DISK=s3` is set, Spatie MediaLibrary stores files on the configured S3 bucket. CDN URLs are generated directly from the S3 bucket (or CloudFront distribution if `AWS_CLOUDFRONT_URL` is set).

```
URL pattern:  https://{bucket}.s3.{region}.amazonaws.com/media/{uuid}/...
         or:  https://{cloudfront_domain}/media/{uuid}/...
```

### Named Variant Routes

For consistent URL generation regardless of storage backend:

```
GET /media/{mediaId}/{variant}
```

This route resolves to `MediaServeController::show($mediaId, $variant)`, which:
1. Looks up `MediaVariant` by `media_id` + `variant_key`
2. Generates a temporary signed URL (S3) or returns the public symlink URL (local)
3. Redirects the client to the resolved URL (HTTP 302)

This ensures that variant URLs in templates remain consistent even when switching storage backends.

---

## 7. Configuration — `config/media.php`

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    | Supported: "local", "s3"
    */
    'disk' => env('MEDIA_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload Constraints
    |--------------------------------------------------------------------------
    */
    'max_size' => env('MEDIA_MAX_SIZE_MB', 20) * 1024,  // in kilobytes

    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/aac',
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Variants
    |--------------------------------------------------------------------------
    */
    'variants' => [
        'webp'   => ['format' => 'webp', 'quality' => 85],
        'thumb'  => ['format' => 'webp', 'quality' => 80, 'width' => 300, 'height' => 300, 'fit' => 'contain'],
        'medium' => ['format' => 'webp', 'quality' => 82, 'width' => 800],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock Photo Providers
    |--------------------------------------------------------------------------
    */
    'stock_photos' => [
        'default_driver' => env('STOCK_PHOTO_DRIVER', 'unsplash'),

        'trusted_domains' => [
            'images.unsplash.com',
            'plus.unsplash.com',
            'cdn.pixabay.com',
            'images.pexels.com',
            'img.freepik.com',
        ],

        'unsplash' => [
            'access_key' => env('UNSPLASH_ACCESS_KEY'),
            'per_page'   => 20,
        ],

        'pixabay' => [
            'api_key'  => env('PIXABAY_API_KEY'),
            'per_page' => 20,
        ],

        'pexels' => [
            'api_key'  => env('PEXELS_API_KEY'),
            'per_page' => 20,
        ],

        'freepik' => [
            'api_key'  => env('FREEPIK_API_KEY'),
            'per_page' => 20,
        ],
    ],

];
```

---

## 8. Security

### File Type Validation

Validation is performed at two independent layers:

1. **Extension check** — the uploaded filename's extension is compared against a derived allowlist built from `config('media.allowed_mimes')`
2. **MIME sniffing** — `finfo_file()` is used to detect the actual MIME type from the binary content of the uploaded file, independent of the browser-supplied `Content-Type` header

Both checks must pass. A mismatch (e.g., a PHP script renamed to `.jpg`) is rejected with HTTP 422 before the file is persisted to any disk.

### File Size Enforcement

The `max:20480` (kilobytes) validation rule is applied both at the Laravel validation layer and at the web server level (`client_max_body_size 22M` in Nginx config). The server-level limit provides an early rejection that avoids loading the full payload into PHP.

### SSRF Protection for Stock Photo Fetch

See [Section 5 — SSRF Protection](#ssrf-protection) for full details. All external URL fetches pass through `SsrfGuard` before any HTTP request is made.

### Storage Path Isolation

Each media file is stored under a UUID-based directory (`media/{uuid}/`) generated by Spatie MediaLibrary. This prevents path traversal attacks and avoids filename collisions. Users cannot influence the storage path.

The `media` table includes an `uploaded_by` foreign key referencing `users.id`. Filament policies enforce that non-admin users can only view and delete their own uploads.

### Signed URLs for Private Media

When `MEDIA_DISK=s3` and a media record is marked as private (e.g., `custom_properties['public'] = false`), the `/media/{id}/{variant}` route generates a time-limited S3 pre-signed URL (valid 60 minutes) rather than a permanent CDN URL.
