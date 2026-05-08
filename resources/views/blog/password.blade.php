@extends('layouts.app', ['title' => 'Protected Post'])

@section('content')
<div class="max-w-md mx-auto bg-white p-8 rounded-xl shadow-sm border border-gray-100 text-center">
    <div class="text-4xl mb-4">🔒</div>
    <h2 class="text-2xl font-bold mb-2">{{ $post->title }}</h2>
    <p class="text-gray-500 mb-6">This post is password-protected.</p>
    <form action="{{ route('post.unlock', $post->slug) }}" method="POST" class="space-y-4">
        @csrf
        @error('password')<p class="text-red-500 text-sm">{{ $message }}</p>@enderror
        <input type="password" name="password" placeholder="Enter password" required
               class="border border-gray-300 rounded px-3 py-2 text-sm w-full">
        <button class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">Unlock</button>
    </form>
</div>
@endsection
