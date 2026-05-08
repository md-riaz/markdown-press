@extends('layouts.app', ['title' => $author->name])

@section('content')
<div class="mb-8 flex items-center gap-6">
    @if($author->avatar_url)
    <img src="{{ $author->avatar_url }}" class="w-20 h-20 rounded-full border-4 border-indigo-200">
    @endif
    <div>
        <h1 class="text-3xl font-bold">{{ $author->name }}</h1>
        @if($author->bio)<p class="text-gray-500 mt-1">{{ $author->bio }}</p>@endif
    </div>
</div>
@include('blog.partials.post-grid', ['paginator' => $paginator])
@endsection
