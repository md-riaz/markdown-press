<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Media;

class MediaVariantController extends Controller
{
    public function show(int $id, string $variant)
    {
        $media = Media::find($id);
        if (!$media) abort(404);

        $mediaVariant = $media->variants()->where('variant_name', $variant)->first();
        if (!$mediaVariant) abort(404);

        return redirect($mediaVariant->url);
    }
}
