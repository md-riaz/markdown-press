<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? \App\Models\Setting::get('general','site_description') }}">
    <title>{{ $title ?? \App\Models\Setting::get('general','site_name','MarkdownPress') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3/dist/tailwind.min.css">
    @stack('head')
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

<header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="{{ route('home') }}" class="font-bold text-xl text-indigo-600 hover:text-indigo-800">
            {{ \App\Models\Setting::get('general','site_name','MarkdownPress') }}
        </a>
        <nav class="flex gap-6 text-sm text-gray-600">
            <a href="{{ route('home') }}" class="hover:text-indigo-600">Blog</a>
            <a href="/admin" class="hover:text-indigo-600">Admin</a>
        </nav>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-10">
    @if(session('success'))
        <div class="mb-6 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    @yield('content')
</main>

<footer class="bg-white border-t border-gray-200 mt-16 py-8 text-center text-sm text-gray-500">
    <div class="max-w-4xl mx-auto px-4">
        <p>&copy; {{ date('Y') }} {{ \App\Models\Setting::get('general','site_name','MarkdownPress') }}. Built with MarkdownPress.</p>
        <form action="{{ route('newsletter.subscribe') }}" method="POST" class="mt-4 flex justify-center gap-2">
            @csrf
            <input type="email" name="email" placeholder="Your email" required
                   class="border border-gray-300 rounded px-3 py-1.5 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <button class="bg-indigo-600 text-white px-4 py-1.5 rounded text-sm hover:bg-indigo-700">Subscribe</button>
        </form>
    </div>
</footer>

@stack('scripts')
</body>
</html>
