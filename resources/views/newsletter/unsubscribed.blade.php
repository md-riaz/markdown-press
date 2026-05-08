@extends('layouts.app', ['title' => 'Unsubscribed'])

@section('content')
<div class="text-center max-w-md mx-auto mt-16">
    <div class="text-5xl mb-4">👋</div>
    <h2 class="text-2xl font-bold mb-2">You've been unsubscribed</h2>
    <p class="text-gray-500">We're sorry to see you go. You won't receive any more newsletters.</p>
    <a href="{{ route('home') }}" class="mt-6 inline-block bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
        Back to Blog
    </a>
</div>
@endsection
