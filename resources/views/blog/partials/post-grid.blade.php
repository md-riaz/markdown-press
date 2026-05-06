<div class="space-y-6">
    @forelse($paginator as $post)
    <article class="bg-white rounded-xl border border-gray-100 p-6 shadow-sm hover:shadow-md transition">
        <h2 class="text-lg font-bold text-gray-900">
            <a href="{{ route('post.show', $post->slug) }}" class="hover:text-indigo-600">{{ $post->title }}</a>
        </h2>
        @if($post->meta_description)
        <p class="text-gray-500 text-sm mt-1 line-clamp-2">{{ $post->meta_description }}</p>
        @endif
        <p class="text-xs text-gray-400 mt-2">{{ $post->published_at?->format('M j, Y') }}</p>
    </article>
    @empty
    <p class="text-gray-400">No posts found.</p>
    @endforelse
    {{ $paginator->links() }}
</div>
