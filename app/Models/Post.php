<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'featured_image_id', 'audio_attachment_id',
        'title', 'slug', 'content_markdown', 'content_html_cached',
        'status', 'password', 'published_at', 'scheduled_at',
        'meta_title', 'meta_description', 'og_image_url', 'canonical_url',
        'noindex', 'is_featured', 'view_count', 'comment_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'noindex'      => 'boolean',
        'is_featured'  => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function audioAttachment(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'audio_attachment_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'post_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class)->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('status', 'approved');
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }

    public function translationForLocale(string $locale): ?PostTranslation
    {
        return $this->translations->firstWhere('locale', $locale);
    }

    public function isPasswordProtected(): bool
    {
        return !empty($this->password);
    }
}
