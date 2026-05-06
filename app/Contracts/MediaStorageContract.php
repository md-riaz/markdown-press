<?php

namespace App\Contracts;

use App\Models\Media;
use Illuminate\Http\UploadedFile;

interface MediaStorageContract
{
    public function store(UploadedFile $file, string $collection = 'images'): Media;

    public function delete(Media $media): void;

    public function url(Media $media, string $variant = 'original'): string;
}
