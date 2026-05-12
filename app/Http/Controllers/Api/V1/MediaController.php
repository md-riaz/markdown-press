<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Modules\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function show(int $id): MediaResource
    {
        $media = Media::with('user')->findOrFail($id);

        return new MediaResource($media);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
            'collection' => 'in:images,audio,video',
        ]);

        $media = $this->media->store($request->file('file'), $request->input('collection', 'images'));

        return response()->json($media, 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $this->media->delete($media);

        return response()->json(['message' => 'Deleted.']);
    }

    public function star(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $media->update(['is_starred' => ! $media->is_starred]);

        return response()->json($media);
    }
}
