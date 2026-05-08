<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'title'            => $this->title,
            'excerpt'          => $this->meta_description,
            'content_html'     => $this->content_html_cached,
            'status'           => $this->status,
            'published_at'     => $this->published_at?->toIso8601String(),
            'is_featured'      => $this->is_featured,
            'view_count'       => $this->view_count,
            'comment_count'    => $this->comment_count,
            'meta_title'       => $this->meta_title,
            'meta_description' => $this->meta_description,
            'og_image_url'     => $this->og_image_url,
            'author'           => [
                'id'         => $this->author?->id,
                'name'       => $this->author?->name,
                'username'   => $this->author?->username,
                'avatar_url' => $this->author?->avatar_url,
            ],
            'featured_image'   => $this->featuredImage ? [
                'url'    => $this->featuredImage->url,
                'alt'    => $this->featuredImage->alt_text,
                'width'  => $this->featuredImage->width,
                'height' => $this->featuredImage->height,
            ] : null,
            'categories' => $this->categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]),
            'tags'        => $this->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug]),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
