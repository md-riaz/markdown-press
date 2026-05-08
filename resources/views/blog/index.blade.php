@extends('layouts.app')

@section('content')
<div class="space-y-10">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Latest Posts</h1>
        <p class="mt-2 text-gray-500">{{ \App\Models\Setting::get('general','site_description') }}</p>
    </div>

    @forelse($paginator as $post)
    <article class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
        @if($post->featuredImage)
        <a href="{{ route('post.show', $post->slug) }}">
            <img src="{{ $post->featuredImage->url }}" alt="{{ $post->featuredImage->alt_text }}"
                 class="w-full h-56 object-cover">
        </a>
        @endif
        <div class="p-6">
            <div class="flex flex-wrap gap-2 mb-3">
                @foreach($post->categories as $cat)
                <a href="{{ route('category.show', $cat->slug) }}"
                   class="text-xs font-semibold uppercase tracking-wide text-indigo-600 hover:underline">
                    {{ $cat->name }}
                </a>
                @endforeach
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">
                <a href="{{ route('post.show', $post->slug) }}" class="hover:text-indigo-600">
                    {{ $post->title }}
                </a>
            </h2>
            @if($post->meta_description)
            <p class="text-gray-600 text-sm line-clamp-3">{{ $post->meta_description }}</p>
            @endif
            <div class="mt-4 flex items-center justify-between text-xs text-gray-400">
                <div class="flex items-center gap-2">
                    @if($post->author?->avatar_url)
                    <img src="{{ $post->author->avatar_url }}" class="w-6 h-6 rounded-full">
                    @endif
                    <a href="{{ route('author.show', $post->author?->username ?? '#') }}"
                       class="hover:underline">{{ $post->author?->name }}</a>
                    <span>&bull;</span>
                    <span>{{ $post->published_at?->format('M j, Y') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span>{{ number_format($post->view_count) }} views</span>
                    <span>{{ $post->comment_count }} comments</span>
                </div>
            </div>
        </div>
    </article>
    @empty
    <p class="text-gray-500">No posts yet.</p>
    @endforelse

    {{ $paginator->links() }}
</div>
@endsection
