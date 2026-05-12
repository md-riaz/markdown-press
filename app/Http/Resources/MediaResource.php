<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'filename'   => $this->filename,
            'url'        => $this->url,
            'mime_type'  => $this->mime_type,
            'size_bytes' => $this->size,
            'width'      => $this->width,
            'height'     => $this->height,
            'alt_text'   => $this->alt_text,
            'starred'    => $this->is_starred,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'uploaded_by' => [
                'id'       => $this->user?->id,
                'username' => $this->user?->username,
                'name'     => $this->user?->name,
            ],
        ];
    }
}
