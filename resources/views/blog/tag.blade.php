@extends('layouts.app', ['title' => '#'.$tag->name])

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold">#{{ $tag->name }}</h1>
</div>
@include('blog.partials.post-grid', ['paginator' => $paginator])
@endsection
