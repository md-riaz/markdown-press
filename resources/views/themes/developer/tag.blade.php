@include('themes.developer.partials.header', [
    'title' => '#'.$tag->name,
    'metaDescription' => $tag->description,
])

<div style="margin-bottom: 2rem;">
    <p class="eyebrow">Tag</p>
    <h1 style="font-size: 2.25rem; margin: .5rem 0;">#{{ $tag->name }}</h1>
</div>

@forelse($posts as $post)
    <article class="card">
        <div class="card-body">
            <h2 style="margin: 0 0 .75rem; font-size: 1.5rem;">
                <a href="/{{ $post->slug }}/">{{ $post->title }}</a>
            </h2>
            <div class="meta">{{ $post->author?->name }} · {{ $post->published_at?->format('M j, Y') }}</div>
        </div>
    </article>
@empty
    <p class="muted">No posts found for this tag.</p>
@endforelse

@include('themes.developer.partials.footer')
