<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\RevisionResource;
use App\Modules\Post\PostService;
use App\Modules\Post\RevisionService;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController extends Controller
{
    public function __construct(
        private PostService    $posts,
        private RevisionService $revisions,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['category', 'tag', 'author']);
        $per     = min((int) ($request->per_page ?? 15), 100);

        return PostResource::collection($this->posts->paginate($per, $filters));
    }

    public function show(string $slug): PostResource|JsonResponse
    {
        $post = $this->posts->findBySlug($slug);
        if (!$post || $post->status !== 'published') {
            return response()->json(['message' => 'Not found.'], 404);
        }
        return new PostResource($post);
    }

    public function translations(string $slug): JsonResponse
    {
        $post = $this->posts->findBySlug($slug);
        if (!$post) return response()->json(['message' => 'Not found.'], 404);

        return response()->json($post->translations);
    }

    public function store(Request $request): PostResource
    {
        $data = $request->validate([
            'title'            => 'required|string|max:512',
            'content_markdown' => 'required|string',
            'slug'             => 'nullable|string|max:512',
            'status'           => 'in:draft,published,scheduled',
            'category_ids'     => 'array',
            'tag_ids'          => 'array',
            'meta_description' => 'nullable|string',
            'is_featured'      => 'boolean',
        ]);

        $post = $this->posts->create($request->user(), $data);
        return new PostResource($post);
    }

    public function update(Request $request, string $slug): PostResource|JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'title'            => 'sometimes|string|max:512',
            'content_markdown' => 'sometimes|string',
            'status'           => 'sometimes|in:draft,published,scheduled',
            'category_ids'     => 'sometimes|array',
            'tag_ids'          => 'sometimes|array',
            'meta_description' => 'nullable|string',
            'is_featured'      => 'sometimes|boolean',
        ]);

        return new PostResource($this->posts->update($post, $data));
    }

    public function destroy(string $slug): JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        $this->posts->delete($post);
        return response()->json(['message' => 'Deleted.']);
    }

    public function clone(Request $request, string $slug): PostResource
    {
        $post   = Post::where('slug', $slug)->firstOrFail();
        $cloned = $this->posts->clone($post, $request->user());
        return new PostResource($cloned);
    }

    public function revisions(string $slug): JsonResponse
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        return response()->json(RevisionResource::collection($this->revisions->list($post)));
    }

    public function restoreRevision(Request $request, string $slug, int $id): PostResource
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        return new PostResource($this->revisions->restore($post, $id));
    }

    public function publish(string $slug): PostResource
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        return new PostResource($this->posts->publish($post));
    }

    public function schedule(Request $request, string $slug): PostResource
    {
        $data = $request->validate(['scheduled_at' => 'required|date']);
        $post = Post::where('slug', $slug)->firstOrFail();
        return new PostResource($this->posts->schedule($post, new \DateTime($data['scheduled_at'])));
    }
}
