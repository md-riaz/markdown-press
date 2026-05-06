@extends('layouts.app', ['title' => $post->meta_title ?? $post->title, 'metaDescription' => $post->meta_description])

@section('content')
<article class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    @if($post->featuredImage)
    <img src="{{ $post->featuredImage->url }}" alt="{{ $post->featuredImage->alt_text }}"
         class="w-full max-h-96 object-cover">
    @endif

    <div class="p-8 md:p-12">
        {{-- Categories --}}
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach($post->categories as $cat)
            <a href="{{ route('category.show', $cat->slug) }}"
               class="text-xs font-semibold uppercase tracking-wide text-indigo-600 bg-indigo-50 px-2 py-1 rounded hover:bg-indigo-100">
                {{ $cat->name }}
            </a>
            @endforeach
        </div>

        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">{{ $post->title }}</h1>

        <div class="flex items-center gap-3 text-sm text-gray-500 mb-8 pb-6 border-b border-gray-100">
            @if($post->author?->avatar_url)
            <img src="{{ $post->author->avatar_url }}" class="w-8 h-8 rounded-full">
            @endif
            <div>
                By <a href="{{ route('author.show', $post->author?->username ?? '#') }}"
                      class="font-medium text-gray-800 hover:underline">{{ $post->author?->name }}</a>
                &bull; {{ $post->published_at?->format('F j, Y') }}
                &bull; {{ $post->view_count }} views
            </div>
        </div>

        {{-- Post content --}}
        <div class="prose prose-lg max-w-none">
            {!! $html !!}
        </div>

        {{-- Tags --}}
        @if($post->tags->count())
        <div class="mt-8 pt-6 border-t border-gray-100 flex flex-wrap gap-2">
            @foreach($post->tags as $tag)
            <a href="{{ route('tag.show', $tag->slug) }}"
               class="text-xs text-gray-500 border border-gray-200 px-2 py-1 rounded hover:bg-gray-50">
                #{{ $tag->name }}
            </a>
            @endforeach
        </div>
        @endif

        {{-- Author bio --}}
        @if($post->author?->bio)
        <div class="mt-10 p-6 bg-gray-50 rounded-xl flex gap-4">
            @if($post->author->avatar_url)
            <img src="{{ $post->author->avatar_url }}" class="w-16 h-16 rounded-full flex-shrink-0">
            @endif
            <div>
                <p class="font-semibold text-gray-900">{{ $post->author->name }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $post->author->bio }}</p>
            </div>
        </div>
        @endif

        {{-- Comments --}}
        <div class="mt-12">
            <h3 class="text-xl font-bold mb-6">Comments</h3>

            @forelse($post->comments as $comment)
            <div class="flex gap-4 mb-6">
                @if($comment->gravatar_hash)
                <img src="https://www.gravatar.com/avatar/{{ $comment->gravatar_hash }}?s=40&d=mp"
                     class="w-10 h-10 rounded-full flex-shrink-0">
                @endif
                <div class="flex-1 bg-gray-50 rounded-lg p-4">
                    <p class="font-medium text-sm text-gray-800">{{ $comment->display_name }}</p>
                    <p class="text-gray-600 mt-1">{{ $comment->body }}</p>
                </div>
            </div>
            @empty
            <p class="text-gray-400 text-sm">No comments yet. Be the first!</p>
            @endforelse

            <form action="{{ route('comments.store', $post->slug) }}" method="POST" class="mt-8 space-y-4">
                @csrf
                @error('body') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="guest_name" placeholder="Your name" required
                           class="border border-gray-300 rounded px-3 py-2 text-sm w-full">
                    <input type="email" name="guest_email" placeholder="Your email" required
                           class="border border-gray-300 rounded px-3 py-2 text-sm w-full">
                </div>
                <textarea name="body" rows="4" placeholder="Write a comment..." required
                          class="border border-gray-300 rounded px-3 py-2 text-sm w-full"></textarea>
                <button class="bg-indigo-600 text-white px-5 py-2 rounded text-sm hover:bg-indigo-700">
                    Post Comment
                </button>
            </form>
        </div>
    </div>
</article>
@endsection
