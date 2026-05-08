@include('themes.monolith.partials.header', [
    'title' => \App\Models\Setting::get('general', 'site_name', 'MarkdownPress'),
    'metaDescription' => \App\Models\Setting::get('general', 'site_description'),
])

<div style="margin-bottom: 2rem;">
    <p class="eyebrow">{{ $theme->name }}</p>
    <h1 style="font-size: 2.25rem; margin: .5rem 0;">Latest Posts</h1>
    <p class="muted">{{ \App\Models\Setting::get('general', 'site_description') }}</p>
</div>

@forelse($posts as $post)
    <article class="card">
        <div class="card-body">
            <div class="chips" style="margin-bottom: .75rem;">
                @foreach($post->categories as $category)
                    <span class="chip">{{ $category->name }}</span>
                @endforeach
            </div>
            <h2 style="margin: 0 0 .75rem; font-size: 1.5rem;">
                <a href="/{{ $post->slug }}/">{{ $post->title }}</a>
            </h2>
            @if($post->meta_description)
                <p class="muted" style="margin: 0 0 1rem;">{{ $post->meta_description }}</p>
            @endif
            <div class="meta">
                {{ $post->author?->name }} · {{ $post->published_at?->format('M j, Y') }}
            </div>
        </div>
    </article>
@empty
    <p class="muted">No published posts were available for this build.</p>
@endforelse

@include('themes.monolith.partials.footer')
