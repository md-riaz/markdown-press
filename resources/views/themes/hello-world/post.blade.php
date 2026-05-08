@include('themes.hello-world.partials.header', [
    'title' => $post->meta_title ?? $post->title,
    'metaDescription' => $post->meta_description,
])

<article class="card">
    <div class="card-body">
        <div class="chips" style="margin-bottom: 1rem;">
            @foreach($post->categories as $category)
                <a href="/category/{{ $category->slug }}/" class="chip">{{ $category->name }}</a>
            @endforeach
        </div>
        <h1 style="font-size: 2.5rem; margin: 0 0 1rem;">{{ $post->title }}</h1>
        <div class="meta" style="margin-bottom: 2rem;">
            {{ $post->author?->name }} · {{ $post->published_at?->format('F j, Y') }}
        </div>

        <div class="prose">
            {!! $html !!}
        </div>

        @if($post->tags->isNotEmpty())
            <div class="chips" style="margin-top: 2rem;">
                @foreach($post->tags as $tag)
                    <a href="/tag/{{ $tag->slug }}/" class="chip">#{{ $tag->name }}</a>
                @endforeach
            </div>
        @endif
    </div>
</article>

@include('themes.hello-world.partials.footer')
