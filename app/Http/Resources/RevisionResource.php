<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'post_id'    => $this->post_id,
            'title'      => $this->title,
            'created_at' => $this->created_at?->toIso8601String(),
            'user'       => [
                'id'   => $this->user?->id,
                'name' => $this->user?->name,
            ],
        ];
    }
}
