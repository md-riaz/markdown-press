<?php

namespace App\Modules\Media;

use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ProcessMediaVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(private Media $media) {}

    public function handle(): void
    {
        $disk     = $this->media->disk;
        $original = Storage::disk($disk)->path($this->media->path);

        foreach (config('media.variants', []) as $variantName => $opts) {
            $this->processVariant($original, $variantName, $opts, $disk);
        }
    }

    private function processVariant(string $srcPath, string $variantName, array $opts, string $disk): void
    {
        $image = Image::read($srcPath);

        if (isset($opts['width']) && isset($opts['height'])) {
            $image = $image->cover($opts['width'], $opts['height']);
        } elseif (isset($opts['width'])) {
            $image = $image->scaleDown(width: $opts['width']);
        }

        $format  = $opts['format'] ?? 'webp';
        $quality = $opts['quality'] ?? 82;

        $ext      = $format === 'webp' ? 'webp' : 'jpg';
        $filename = Str::uuid() . '.' . $ext;
        $dir      = dirname($this->media->path);
        $path     = "{$dir}/variants/{$filename}";

        $encoded = $image->toWebp($quality);
        Storage::disk($disk)->put($path, (string) $encoded);

        $size = strlen((string) $encoded);
        [$w, $h] = [$image->width(), $image->height()];

        MediaVariant::updateOrCreate(
            ['media_id' => $this->media->id, 'variant_name' => $variantName],
            [
                'disk'      => $disk,
                'path'      => $path,
                'mime_type' => "image/{$format}",
                'size'      => $size,
                'width'     => $w,
                'height'    => $h,
            ]
        );
    }
}
