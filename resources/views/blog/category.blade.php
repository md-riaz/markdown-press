@extends('layouts.app', ['title' => $category->name])

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold">{{ $category->name }}</h1>
    @if($category->description)<p class="text-gray-500 mt-2">{{ $category->description }}</p>@endif
</div>
@include('blog.partials.post-grid', ['paginator' => $paginator])
@endsection
