<?php

namespace App\Modules\Media;

use App\Contracts\MediaStorageContract;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService implements MediaStorageContract
{
    public function store(UploadedFile $file, string $collection = 'images'): Media
    {
        /** @var User $user */
        $user = Auth::user();
        $disk = config('media.disk', 'public');

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs("media/{$collection}", $filename, $disk);

        [$width, $height] = $this->imageDimensions($file);

        $media = Media::create([
            'user_id'    => $user->id,
            'filename'   => $file->getClientOriginalName(),
            'disk'       => $disk,
            'path'       => $path,
            'mime_type'  => $file->getMimeType(),
            'size'       => $file->getSize(),
            'width'      => $width,
            'height'     => $height,
            'collection' => $collection,
        ]);

        // Dispatch variant processing job (if image)
        if (str_starts_with($file->getMimeType(), 'image/') && $file->getMimeType() !== 'image/svg+xml') {
            ProcessMediaVariantsJob::dispatch($media);
        }

        return $media;
    }

    public function delete(Media $media): void
    {
        foreach ($media->variants as $variant) {
            Storage::disk($variant->disk)->delete($variant->path);
            $variant->delete();
        }

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }

    public function url(Media $media, string $variant = 'original'): string
    {
        if ($variant !== 'original') {
            $v = $media->variant($variant);
            if ($v) return Storage::disk($v->disk)->url($v->path);
        }

        return Storage::disk($media->disk)->url($media->path);
    }

    private function imageDimensions(UploadedFile $file): array
    {
        if (str_starts_with($file->getMimeType(), 'image/') && function_exists('getimagesize')) {
            $size = @getimagesize($file->getPathname());
            return $size ? [$size[0], $size[1]] : [null, null];
        }
        return [null, null];
    }
}
