<?php
namespace App\Modules\Media;

use App\Models\Media;
use App\Models\MediaVariant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class MediaCropJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Media $media,
        private array $cropParams,
    ) {}

    public function handle(): void
    {
        $disk     = $this->media->disk;
        $path     = $this->media->path;
        $contents = Storage::disk($disk)->get($path);

        $image = Image::read($contents);
        $image->crop(
            (int) $this->cropParams['width'],
            (int) $this->cropParams['height'],
            (int) $this->cropParams['x'],
            (int) $this->cropParams['y'],
        );

        $variantName = 'cropped_' . now()->timestamp;
        $ext         = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $variantPath = 'media/variants/' . $variantName . '.' . $ext;

        Storage::disk($disk)->put($variantPath, $image->toJpeg()->toString());

        MediaVariant::create([
            'media_id'     => $this->media->id,
            'variant_name' => $variantName,
            'disk'         => $disk,
            'path'         => $variantPath,
            'mime_type'    => 'image/jpeg',
            'size'         => Storage::disk($disk)->size($variantPath),
            'width'        => $image->width(),
            'height'       => $image->height(),
        ]);
    }
}
