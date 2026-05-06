# Shortcode Engine — MarkdownPress

## Table of Contents

1. [Overview & Philosophy](#overview--philosophy)
2. [Parsing Algorithm](#parsing-algorithm)
3. [Regex Pattern](#regex-pattern)
4. [Attribute Parsing](#attribute-parsing)
5. [ShortcodeRegistry](#shortcoderegistry)
6. [Built-in Handlers](#built-in-handlers)
7. [Nested Shortcodes](#nested-shortcodes)
8. [Adding a New Shortcode](#adding-a-new-shortcode)
9. [Security](#security)

---

## 1. Overview & Philosophy

### Why Shortcodes?

CommonMark Markdown is intentionally constrained. It handles prose, headings, lists, links, and fenced code blocks well, but it has no native mechanism for embedding rich media, interactive widgets, or dynamic content. The common workarounds — raw HTML blocks inside Markdown — are fragile, verbose, and cannot be safely rendered in a CMS where author trust levels vary.

Shortcodes fill this gap by providing a **declarative, author-friendly syntax** for embedding structured content:

```
[youtube id="dQw4w9WgXcQ"]
[alert type="warning"]Your API key expires in 7 days.[/alert]
[mermaid]
graph TD
  A[Request] --> B[Router]
  B --> C[Controller]
[/mermaid]
```

Authors write clean, readable source. The engine resolves shortcodes at render time, producing safe, tested HTML output for every handler.

### The Markdown-Safe Placeholder Approach

The core architectural challenge is that a Markdown parser will corrupt shortcode syntax if it encounters it as raw text. For example:

- Brackets `[...]` are parsed as link syntax.
- Quotes inside attributes are escaped or stripped.
- Block shortcodes that span multiple lines may be wrapped in `<p>` tags or have their whitespace collapsed.

To prevent this, MarkdownPress uses a **placeholder swap strategy**:

1. Shortcodes are extracted from the raw Markdown source **before** it is passed to the parser.
2. Each extracted shortcode is replaced in the raw source with a unique opaque token (e.g., `%%SC_0%%`) that the Markdown parser treats as an inert text run.
3. The placeholder-safe Markdown is parsed to HTML.
4. Shortcode handlers are invoked and their HTML output replaces the placeholder tokens in the final HTML.

This ensures the Markdown parser never sees shortcode syntax, shortcode output is never re-parsed by Markdown, and each concern remains cleanly separated.

---

## 2. Parsing Algorithm

The following describes the complete pipeline from raw Markdown input to final rendered HTML.

### Step 1 — Scan for Shortcodes

The `ShortcodeProcessor` receives the raw Markdown string. It applies the shortcode regex (see [Regex Pattern](#regex-pattern)) to find all shortcode occurrences — both self-closing and block forms — in document order.

```
Input:
  "# Intro\n\nWatch this:\n\n[youtube id=\"dQw4w9WgXcQ\"]\n\n[alert type=\"warning\"]Check this.[/alert]"
```

### Step 2 — Extract and Store

Each regex match is stored in a `$shortcodes` array. The array key is a unique placeholder token of the form `%%SC_{n}%%` where `n` is an incrementing integer. The value is an associative array holding the shortcode `name`, parsed `attributes`, and optional `content`.

```php
$shortcodes = [
    '%%SC_0%%' => [
        'name'       => 'youtube',
        'attributes' => ['id' => 'dQw4w9WgXcQ'],
        'content'    => null,
    ],
    '%%SC_1%%' => [
        'name'       => 'alert',
        'attributes' => ['type' => 'warning'],
        'content'    => 'Check this.',
    ],
];
```

### Step 3 — Replace Matches with Placeholders

Each shortcode match in the raw Markdown string is replaced with its corresponding placeholder token. Block-level shortcodes are surrounded by blank lines in the replacement to ensure the placeholder is treated as its own paragraph — preventing it from being merged into surrounding prose.

```
After replacement:
  "# Intro\n\nWatch this:\n\n%%SC_0%%\n\n%%SC_1%%"
```

### Step 4 — Parse Markdown to HTML

The placeholder-safe Markdown string is passed through the configured CommonMark parser (League CommonMark with GFM extensions). The parser sees only inert placeholder tokens at the shortcode positions and generates valid HTML without interference.

```html
<!-- After CommonMark parsing -->
<h1>Intro</h1>
<p>Watch this:</p>
<p>%%SC_0%%</p>
<p>%%SC_1%%</p>
```

### Step 5 — Invoke Shortcode Handlers

For each entry in `$shortcodes`, the `ShortcodeRegistry` resolves the handler registered under the shortcode name and calls its `render()` method, passing the parsed attributes and content string.

```php
$renderedFragments = [];
foreach ($shortcodes as $token => $sc) {
    $handler = ShortcodeRegistry::resolve($sc['name']);
    $renderedFragments[$token] = $handler->render($sc['attributes'], $sc['content']);
}
```

If no handler is registered for a shortcode name, the token is replaced with an empty string (the shortcode is silently removed from output). In debug mode, a visible warning comment is emitted instead.

### Step 6 — Replace Placeholder Tokens in HTML

Each placeholder token in the HTML output is replaced with the corresponding rendered HTML fragment from Step 5. Because the placeholders were wrapped in `<p>` tags by the Markdown parser, the replacement also strips the enclosing `<p>%%SC_n%%</p>` wrapper to avoid invalid nesting (e.g., `<p><div>...</div></p>`).

```php
foreach ($renderedFragments as $token => $html) {
    $wrappedToken = '<p>' . $token . '</p>';
    // Prefer the unwrapped replacement; fall back to inline replacement.
    if (str_contains($htmlOutput, $wrappedToken)) {
        $htmlOutput = str_replace($wrappedToken, $html, $htmlOutput);
    } else {
        $htmlOutput = str_replace($token, $html, $htmlOutput);
    }
}
```

### Step 7 — Return Final HTML

The fully resolved HTML string is returned from `ShortcodeProcessor::process(string $markdown): string` and used as the post's `body_html`.

---

## 3. Regex Pattern

The following regex matches both self-closing (`[name attr="val" /]`) and block (`[name attr="val"]content[/name]`) shortcodes. It uses named capture groups for clarity.

```php
const SHORTCODE_PATTERN = '/
    \[                          # Opening bracket
    (?P<name>[a-zA-Z_][a-zA-Z0-9_-]*)  # Shortcode name (letters, digits, hyphens, underscores)
    (?P<attributes>[^\]\/]*)    # Attribute string (everything up to ] or /)
    (?:                         # Non-capturing group for self-closing vs block
        \/\]                    # Self-closing: />
        |
        \]                      # Block open: >
        (?P<content>.*?)        # Inner content (non-greedy)
        \[\/(?P=name)\]         # Matching closing tag using back-reference
    )
/xs';
```

### Flags

| Flag | Meaning                                                              |
|------|----------------------------------------------------------------------|
| `x`  | Extended mode — whitespace and comments in the pattern are ignored   |
| `s`  | DOTALL mode — `.` matches newlines, enabling multi-line content blocks |

### Named Capture Groups

| Group        | Captures                                                    | Example               |
|--------------|-------------------------------------------------------------|-----------------------|
| `name`       | The shortcode identifier                                    | `youtube`             |
| `attributes` | Raw attribute string (unparsed)                             | ` id="dQw4w9WgXcQ"`  |
| `content`    | Inner content of block shortcodes; absent for self-closing  | `Check this.`         |

### Example Matches

| Input                                         | `name`    | `attributes`        | `content`       |
|-----------------------------------------------|-----------|---------------------|-----------------|
| `[youtube id="dQw4w9WgXcQ"]`                  | youtube   | ` id="dQw4w9WgXcQ"` | *(null)*        |
| `[alert type="warning"]message[/alert]`        | alert     | ` type="warning"`   | `message`       |
| `[mermaid]\ngraph TD\n  A-->B\n[/mermaid]`    | mermaid   | *(empty)*           | `graph TD\n...` |

---

## 4. Attribute Parsing

The raw `attributes` capture group is a string like:

```
 id="dQw4w9WgXcQ" autoplay loop title="My Video"
```

The `AttributeParser` converts this into a PHP associative array using a tokenizer regex:

```php
class AttributeParser
{
    private const ATTR_PATTERN = '/
        (?P<key>[a-zA-Z_][a-zA-Z0-9_-]*)  # Attribute name
        (?:                                 # Optional value
            \s*=\s*
            (?:
                "(?P<dq>[^"]*)"             # Double-quoted value
                |
                \'(?P<sq>[^\']*)\'          # Single-quoted value
                |
                (?P<uq>[^\s\]]+)            # Unquoted value
            )
        )?
    /x';

    public static function parse(string $raw): array
    {
        $attributes = [];
        preg_match_all(self::ATTR_PATTERN, trim($raw), $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $match['key'];
            if (!empty($match['dq']) || array_key_exists('dq', $match)) {
                $value = $match['dq'];
            } elseif (!empty($match['sq']) || array_key_exists('sq', $match)) {
                $value = $match['sq'];
            } elseif (!empty($match['uq'])) {
                $value = $match['uq'];
            } else {
                // Boolean flag attribute with no value
                $value = true;
            }
            $attributes[$key] = $value;
        }

        return $attributes;
    }
}
```

### Parsing Examples

| Raw Attribute String                        | Parsed Array                                              |
|---------------------------------------------|-----------------------------------------------------------|
| `id="dQw4w9WgXcQ"`                          | `['id' => 'dQw4w9WgXcQ']`                                 |
| `type="warning" dismissible`                | `['type' => 'warning', 'dismissible' => true]`            |
| `src='/audio/file.mp3' loop controls`       | `['src' => '/audio/file.mp3', 'loop' => true, 'controls' => true]` |
| `user="octocat" slug="abc" height=400`      | `['user' => 'octocat', 'slug' => 'abc', 'height' => '400']` |

All values are returned as strings (or `true` for boolean flags). Type coercion — e.g., casting `height` to an integer — is the responsibility of each handler.

---

## 5. ShortcodeRegistry

### Registration

Handlers are registered in a dedicated service provider and can also be declared in `config/shortcodes.php` for application-level configuration.

#### `config/shortcodes.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Built-in Shortcode Handlers
    |--------------------------------------------------------------------------
    | Each entry is a fully-qualified class name implementing
    | ShortcodeHandlerContract. The service provider will instantiate and
    | register each handler automatically on application boot.
    */
    'handlers' => [
        \App\Shortcodes\YoutubeHandler::class,
        \App\Shortcodes\GistHandler::class,
        \App\Shortcodes\CodepenHandler::class,
        \App\Shortcodes\MermaidHandler::class,
        \App\Shortcodes\AlertHandler::class,
        \App\Shortcodes\AudioHandler::class,
        \App\Shortcodes\VideoHandler::class,
        \App\Shortcodes\TweetHandler::class,
        \App\Shortcodes\FacebookHandler::class,
    ],
];
```

#### `ShortcodeServiceProvider`

```php
<?php

namespace App\Providers;

use App\Services\ShortcodeRegistry;
use Illuminate\Support\ServiceProvider;

class ShortcodeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShortcodeRegistry::class, function () {
            return new ShortcodeRegistry();
        });
    }

    public function boot(): void
    {
        $registry = $this->app->make(ShortcodeRegistry::class);

        foreach (config('shortcodes.handlers', []) as $handlerClass) {
            $registry->register($this->app->make($handlerClass));
        }
    }
}
```

### Interface Contract

Every shortcode handler must implement the following contract:

```php
<?php

namespace App\Contracts;

interface ShortcodeHandlerContract
{
    /**
     * The shortcode name this handler responds to.
     * Must match [a-zA-Z_][a-zA-Z0-9_-]* and be unique across all registered handlers.
     */
    public function name(): string;

    /**
     * Render the shortcode to an HTML string.
     *
     * @param  array<string, string|bool>  $attributes  Parsed attribute key-value pairs.
     * @param  string|null                 $content     Inner content for block shortcodes; null for self-closing.
     * @return string                                   Safe HTML fragment to inject into the post body.
     */
    public function render(array $attributes, ?string $content): string;
}
```

### `ShortcodeRegistry` Class

```php
<?php

namespace App\Services;

use App\Contracts\ShortcodeHandlerContract;
use InvalidArgumentException;

class ShortcodeRegistry
{
    /** @var array<string, ShortcodeHandlerContract> */
    private array $handlers = [];

    public function register(ShortcodeHandlerContract $handler): void
    {
        $name = $handler->name();

        if (isset($this->handlers[$name])) {
            throw new InvalidArgumentException(
                "A shortcode handler for '{$name}' is already registered."
            );
        }

        $this->handlers[$name] = $handler;
    }

    public function resolve(string $name): ?ShortcodeHandlerContract
    {
        return $this->handlers[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->handlers[$name]);
    }

    /** @return string[] */
    public function registeredNames(): array
    {
        return array_keys($this->handlers);
    }
}
```

---

## 6. Built-in Handlers

### `[youtube]`

Embeds a YouTube video in a responsive iframe.

**Syntax:**

```
[youtube id="dQw4w9WgXcQ"]
[youtube id="dQw4w9WgXcQ" width="800" height="450" autoplay="1"]
```

**Attributes:**

| Attribute   | Type    | Default | Description                           |
|-------------|---------|---------|---------------------------------------|
| `id`        | string  | —       | YouTube video ID (required)           |
| `width`     | integer | 560     | Iframe width in pixels                |
| `height`    | integer | 315     | Iframe height in pixels               |
| `autoplay`  | string  | `0`     | Pass `1` to autoplay (muted required) |
| `start`     | integer | 0       | Start playback at this second offset  |

**Rendered HTML:**

```html
<div class="shortcode-youtube video-responsive">
  <iframe
    width="560"
    height="315"
    src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"
    title="YouTube video player"
    frameborder="0"
    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
    allowfullscreen
    loading="lazy">
  </iframe>
</div>
```

> The `youtube-nocookie.com` domain is used to avoid setting third-party cookies until the user interacts with the player.

---

### `[gist]`

Embeds a GitHub Gist using its script embed mechanism.

**Syntax:**

```
[gist id="abc123def456"]
[gist id="abc123def456" file="example.php"]
```

**Attributes:**

| Attribute | Type   | Default | Description                                               |
|-----------|--------|---------|-----------------------------------------------------------|
| `id`      | string | —       | GitHub Gist ID (required)                                 |
| `file`    | string | —       | Specific file within the gist to embed; embeds all if omitted |

**Rendered HTML:**

```html
<div class="shortcode-gist">
  <script src="https://gist.github.com/abc123def456.js?file=example.php"></script>
  <noscript>
    <a href="https://gist.github.com/abc123def456">View Gist on GitHub</a>
  </noscript>
</div>
```

---

### `[codepen]`

Embeds a CodePen pen in an iframe.

**Syntax:**

```
[codepen user="octocat" slug="abcde"]
[codepen user="octocat" slug="abcde" height="400" theme="dark" tab="result"]
```

**Attributes:**

| Attribute    | Type    | Default  | Description                                       |
|--------------|---------|----------|---------------------------------------------------|
| `user`       | string  | —        | CodePen username (required)                       |
| `slug`       | string  | —        | Pen slug/hash (required)                          |
| `height`     | integer | 300      | Iframe height in pixels                           |
| `theme`      | string  | `light`  | `light` or `dark`                                 |
| `tab`        | string  | `result` | Default tab: `html`, `css`, `js`, `result`        |
| `editable`   | bool    | false    | Show the editable embed version                   |

**Rendered HTML:**

```html
<div class="shortcode-codepen">
  <iframe
    height="300"
    style="width: 100%;"
    scrolling="no"
    title="CodePen Embed"
    src="https://codepen.io/octocat/embed/abcde?default-tab=result&theme-id=light"
    frameborder="no"
    loading="lazy"
    allowtransparency="true"
    allowfullscreen="true">
    <a href="https://codepen.io/octocat/pen/abcde">View Pen on CodePen</a>
  </iframe>
</div>
```

---

### `[mermaid]`

Renders a Mermaid.js diagram. The diagram source is placed inside a `<div>` with the `mermaid` class, which the Mermaid.js library (loaded in the theme's asset pipeline) processes at runtime.

**Syntax:**

```
[mermaid]
graph TD
  A[HTTP Request] --> B{Router}
  B -->|GET /posts| C[PostController]
  B -->|POST /posts| D[PostController]
[/mermaid]
```

**Attributes:** None.

**Rendered HTML:**

```html
<div class="shortcode-mermaid">
  <div class="mermaid">
graph TD
  A[HTTP Request] --&gt; B{Router}
  B --&gt;|GET /posts| C[PostController]
  B --&gt;|POST /posts| D[PostController]
  </div>
</div>
```

> The inner content is HTML-entity-encoded to prevent XSS. The Mermaid.js library decodes entities before parsing the diagram source.

---

### `[alert]`

Renders a styled callout box for drawing reader attention. Supports four severity levels.

**Syntax:**

```
[alert type="info"]This feature is available from version 2.0.[/alert]
[alert type="warning" dismissible]Your session expires in 10 minutes.[/alert]
[alert type="error"]Invalid API key. Please rotate your credentials.[/alert]
[alert type="success"]Your post was published successfully.[/alert]
```

**Attributes:**

| Attribute     | Type   | Default  | Description                                           |
|---------------|--------|----------|-------------------------------------------------------|
| `type`        | string | `info`   | Alert severity: `info`, `warning`, `error`, `success` |
| `dismissible` | bool   | false    | Adds a close button (requires theme JS support)       |
| `title`       | string | —        | Optional bold heading inside the alert                |

**Rendered HTML (type="warning", dismissible):**

```html
<div class="shortcode-alert alert alert--warning alert--dismissible" role="alert">
  <button class="alert__close" aria-label="Dismiss">&times;</button>
  <p class="alert__body">Your session expires in 10 minutes.</p>
</div>
```

---

### `[audio]`

Embeds an HTML5 audio player.

**Syntax:**

```
[audio src="/storage/media/podcast-ep01.mp3"]
[audio src="/storage/media/podcast-ep01.mp3" controls loop preload="metadata"]
```

**Attributes:**

| Attribute  | Type   | Default    | Description                                                  |
|------------|--------|------------|--------------------------------------------------------------|
| `src`      | string | —          | URL of the audio file (required)                             |
| `controls` | bool   | true       | Show native browser controls                                 |
| `autoplay` | bool   | false      | Autoplay (muted automatically per browser policy)            |
| `loop`     | bool   | false      | Loop the audio                                               |
| `preload`  | string | `metadata` | `none`, `metadata`, or `auto`                                |
| `label`    | string | —          | Accessible label for the player                              |

**Rendered HTML:**

```html
<div class="shortcode-audio">
  <audio
    src="/storage/media/podcast-ep01.mp3"
    controls
    preload="metadata"
    aria-label="Audio player">
    <p>Your browser does not support the audio element.
       <a href="/storage/media/podcast-ep01.mp3">Download the audio file</a>.
    </p>
  </audio>
</div>
```

---

### `[video]`

Embeds an HTML5 video player.

**Syntax:**

```
[video src="/storage/media/demo.mp4"]
[video src="/storage/media/demo.mp4" poster="/storage/media/demo-thumb.jpg" width="800"]
```

**Attributes:**

| Attribute  | Type    | Default    | Description                                             |
|------------|---------|------------|---------------------------------------------------------|
| `src`      | string  | —          | URL of the video file (required)                        |
| `poster`   | string  | —          | URL of the poster/thumbnail image                       |
| `width`    | integer | —          | Video width in pixels (responsive if omitted)           |
| `height`   | integer | —          | Video height in pixels                                  |
| `controls` | bool    | true       | Show native browser controls                            |
| `autoplay` | bool    | false      | Autoplay (always muted when true)                       |
| `loop`     | bool    | false      | Loop the video                                          |
| `muted`    | bool    | false      | Mute audio by default                                   |
| `preload`  | string  | `metadata` | `none`, `metadata`, or `auto`                           |

**Rendered HTML:**

```html
<div class="shortcode-video video-responsive">
  <video
    src="/storage/media/demo.mp4"
    poster="/storage/media/demo-thumb.jpg"
    controls
    preload="metadata">
    <p>Your browser does not support the video element.
       <a href="/storage/media/demo.mp4">Download the video file</a>.
    </p>
  </video>
</div>
```

---

### `[tweet]`

Embeds a tweet using Twitter's oEmbed blockquote format. Renders a static blockquote that Twitter's `widgets.js` enhances progressively.

**Syntax:**

```
[tweet id="1234567890123456789"]
[tweet id="1234567890123456789" theme="dark" lang="fr"]
```

**Attributes:**

| Attribute | Type   | Default | Description                                         |
|-----------|--------|---------|-----------------------------------------------------|
| `id`      | string | —       | Tweet ID (required; numeric string)                 |
| `theme`   | string | `light` | `light` or `dark`                                   |
| `lang`    | string | `en`    | BCP 47 language code for the embed UI               |
| `cards`   | string | —       | Pass `hidden` to suppress media cards               |

**Rendered HTML:**

```html
<div class="shortcode-tweet">
  <blockquote class="twitter-tweet" data-theme="light" data-lang="en">
    <a href="https://twitter.com/i/web/status/1234567890123456789">
      View tweet on Twitter/X
    </a>
  </blockquote>
</div>
```

> Twitter's `widgets.js` must be loaded in the theme footer for the embed to be hydrated into a fully-rendered tweet card.

---

### `[facebook]`

Embeds a Facebook post or video using the Facebook JavaScript SDK `<div>` embed format.

**Syntax:**

```
[facebook url="https://www.facebook.com/username/posts/1234567890"]
[facebook url="https://www.facebook.com/video.php?v=1234567890" type="video" width="560"]
```

**Attributes:**

| Attribute | Type    | Default | Description                                                 |
|-----------|---------|---------|-------------------------------------------------------------|
| `url`     | string  | —       | Full URL of the Facebook post or video (required)           |
| `type`    | string  | `post`  | `post` or `video`                                           |
| `width`   | integer | 500     | Embed width in pixels (between 220 and 750)                 |
| `showtext`| bool    | true    | Include post text in the embed (posts only)                 |

**Rendered HTML:**

```html
<div class="shortcode-facebook">
  <div
    class="fb-post"
    data-href="https://www.facebook.com/username/posts/1234567890"
    data-width="500"
    data-show-text="true">
  </div>
</div>
```

> The Facebook JS SDK (`connect.facebook.net/en_US/sdk.js`) must be loaded in the theme for the embed to render.

---

## 7. Nested Shortcodes

MarkdownPress processes nested shortcodes using an **inside-out (innermost-first) strategy**. This ensures that inner shortcode output does not interfere with outer shortcode attribute parsing or content extraction.

### How It Works

The shortcode regex uses non-greedy content matching (`.*?`). On a document containing nested shortcodes, the first pass of `preg_match_all` will find only the innermost shortcodes (because the non-greedy `.*?` stops at the first closing tag it encounters). Outer shortcodes still contain their un-processed inner shortcode syntax in their `content` string at this stage.

Processing is performed in a loop: the full shortcode extraction and replacement cycle is repeated until no further shortcode tokens are found in the input. Effectively:

1. Pass 1 — all innermost shortcodes are extracted, stored, and replaced with placeholders.
2. The partially-processed string (with inner placeholders) is scanned again.
3. Pass 2 — shortcodes whose `content` previously contained inner shortcodes are now matched (their content now contains placeholder tokens from Pass 1).
4. This continues until the regex finds no more matches.

### Example

```markdown
[alert type="info"]
  Watch this: [youtube id="abc123"]
[/alert]
```

**Pass 1:** The `youtube` shortcode is innermost. It is extracted and replaced:

```
[alert type="info"]
  Watch this: %%SC_0%%
[/alert]
```

**Pass 2:** The `alert` shortcode is now matched. Its content is `Watch this: %%SC_0%%`. The alert handler renders its content string — which will contain the placeholder — and the full swap is performed in Step 6 of the main pipeline.

### Depth Limit

To prevent runaway processing from malformed or deliberately recursive shortcodes, the processing loop is capped at **10 passes**. If shortcodes remain unresolved after 10 passes, they are silently removed from output. This limit is configurable via `config/shortcodes.php`:

```php
'max_nesting_depth' => 10,
```

---

## 8. Adding a New Shortcode

Follow these steps to add a custom shortcode handler.

### Step 1 — Create the Handler Class

Create a new PHP class in `app/Shortcodes/`. The filename and class name must follow the `{Name}Handler` convention.

```php
<?php

namespace App\Shortcodes;

use App\Contracts\ShortcodeHandlerContract;

class SpotifyHandler implements ShortcodeHandlerContract
{
    public function name(): string
    {
        return 'spotify';
    }

    public function render(array $attributes, ?string $content): string
    {
        $type = $attributes['type'] ?? 'track';
        $id   = $attributes['id'] ?? '';

        if (empty($id)) {
            return '';
        }

        // Validate $type against an allowlist before using it in a URL.
        $allowedTypes = ['track', 'album', 'playlist', 'episode', 'show'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'track';
        }

        $embedUrl = htmlspecialchars(
            "https://open.spotify.com/embed/{$type}/{$id}",
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $safeId = htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
        <div class="shortcode-spotify">
          <iframe
            src="{$embedUrl}"
            width="100%"
            height="152"
            frameborder="0"
            allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
            loading="lazy"
            title="Spotify embed: {$safeId}">
          </iframe>
        </div>
        HTML;
    }
}
```

### Step 2 — Implement the Contract

Ensure your class:

- Declares `implements ShortcodeHandlerContract`.
- Returns a lowercase, hyphen-safe name from `name()` — this is the string authors will type between `[` and `]`.
- Returns a valid, safe HTML string from `render()` — never return raw user input without sanitisation.

### Step 3 — Register the Handler

Add the fully-qualified class name to the `handlers` array in `config/shortcodes.php`:

```php
'handlers' => [
    // ... existing handlers ...
    \App\Shortcodes\SpotifyHandler::class,
],
```

Alternatively, register the handler programmatically in a custom service provider:

```php
public function boot(): void
{
    $registry = $this->app->make(ShortcodeRegistry::class);
    $registry->register(new SpotifyHandler());
}
```

### Step 4 — Write Tests

Create a feature test in `tests/Feature/Shortcodes/SpotifyHandlerTest.php`:

```php
<?php

namespace Tests\Feature\Shortcodes;

use App\Services\ShortcodeProcessor;
use Tests\TestCase;

class SpotifyHandlerTest extends TestCase
{
    public function test_renders_spotify_embed(): void
    {
        $processor = app(ShortcodeProcessor::class);
        $markdown  = '[spotify type="track" id="4uLU6hMCjMI75M1A2tKUQC"]';
        $html      = $processor->process($markdown);

        $this->assertStringContainsString('open.spotify.com/embed/track/4uLU6hMCjMI75M1A2tKUQC', $html);
        $this->assertStringContainsString('shortcode-spotify', $html);
    }

    public function test_returns_empty_string_when_id_is_missing(): void
    {
        $processor = app(ShortcodeProcessor::class);
        $html      = $processor->process('[spotify type="track"]');

        $this->assertStringNotContainsString('spotify', $html);
    }

    public function test_rejects_invalid_embed_type(): void
    {
        $processor = app(ShortcodeProcessor::class);
        $html      = $processor->process('[spotify type="<script>" id="abc"]');

        $this->assertStringNotContainsString('<script>', $html);
    }
}
```

### Step 5 — Document the Shortcode

Add a row to the shortcode reference table in the site's author documentation (`resources/docs/shortcodes.md`) and update `FEATURES.md` if the shortcode adds a new capability.

---

## 9. Security

The shortcode engine handles author-supplied input and produces HTML output consumed by end users. Multiple layers of sanitisation are applied.

### XSS Prevention in Attribute Values

Every attribute value used inside HTML attribute positions must be escaped with `htmlspecialchars` before interpolation:

```php
$safeValue = htmlspecialchars($attributes['title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
// Use $safeValue in HTML, never the raw $attributes['title']
```

Use `ENT_QUOTES` to encode both single and double quotes, preventing attribute injection across both quoting styles. Prefer `ENT_SUBSTITUTE` to replace malformed multibyte sequences with the Unicode replacement character rather than returning an empty string.

### URL Validation for `src` and `url` Attributes

Handlers that accept URL attributes (`src`, `url`, `poster`, `href`) must validate the value before embedding it in the HTML:

```php
private function validateUrl(string $url): string
{
    $parsed = parse_url($url);

    // Only allow http and https schemes.
    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
        return '';
    }

    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
```

This prevents `javascript:`, `data:`, `vbscript:`, and protocol-relative URLs from being injected into `src` or `href` attributes.

For handlers that accept URLs from a restricted set of domains (e.g., YouTube, Spotify), perform an additional domain allowlist check:

```php
private function validateYouTubeId(string $id): string
{
    // YouTube video IDs are 11 characters of [a-zA-Z0-9_-].
    if (!preg_match('/^[a-zA-Z0-9_\-]{11}$/', $id)) {
        return '';
    }
    return $id;
}
```

### Content Sanitisation for Block Shortcodes

Block shortcode content (the text between opening and closing tags) is author-supplied Markdown or plain text. Handlers that embed this content directly in HTML must sanitise it:

- For diagram handlers like `[mermaid]`, HTML-encode the content:
  ```php
  $safeContent = htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  ```

- For alert and callout handlers where content may include Markdown, pass the content through the Markdown parser (not the full `ShortcodeProcessor`, to avoid infinite recursion) and then through an HTML purifier to strip disallowed tags:
  ```php
  $innerHtml = $this->markdownParser->convertToHtml($content);
  $safeHtml  = $this->htmlPurifier->purify($innerHtml);
  ```

### Allowlisted Attribute Values

For attributes with a fixed set of valid values (e.g., `type` in `[alert]`), always validate against an explicit allowlist and fall back to the default rather than passing the raw value through:

```php
$allowedTypes = ['info', 'warning', 'error', 'success'];
$type = in_array($attributes['type'] ?? '', $allowedTypes, true)
    ? $attributes['type']
    : 'info';
```

### Integer Attribute Casting

Numeric attributes like `width`, `height`, and `start` should be cast to integers and clamped to a sensible range before use:

```php
$width  = min(max((int) ($attributes['width'] ?? 560), 100), 1920);
$height = min(max((int) ($attributes['height'] ?? 315), 50), 1080);
```

This prevents negative values, zero dimensions, and excessively large iframes from reaching the HTML output.

### Security Checklist for New Handlers

Before merging a new shortcode handler, verify each of the following:

| Check                                              | Required |
|----------------------------------------------------|----------|
| All attribute values escaped with `htmlspecialchars` | ✅ Yes  |
| URL attributes validated for scheme (http/https only) | ✅ Yes  |
| Embed IDs validated against an expected character pattern | ✅ Yes |
| Enum attributes validated against an explicit allowlist | ✅ Yes |
| Integer attributes cast and clamped                | ✅ Yes  |
| Block content sanitised before HTML interpolation  | ✅ Yes  |
| No raw `$_GET`, `$_POST`, or `request()` access inside the handler | ✅ Yes |
| Unit test asserting that a `<script>` injection payload is neutralised | ✅ Yes |
