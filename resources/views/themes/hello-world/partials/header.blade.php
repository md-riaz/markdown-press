<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? \App\Models\Setting::get('general', 'site_description') }}">
    <title>{{ $title ?? \App\Models\Setting::get('general', 'site_name', 'MarkdownPress') }}</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: Inter, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        a { color: #4f46e5; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .container { max-width: 960px; margin: 0 auto; padding: 0 1.25rem; }
        .site-header, .site-footer { background: #fff; border-color: #e2e8f0; border-style: solid; }
        .site-header { border-width: 0 0 1px; }
        .site-footer { border-width: 1px 0 0; margin-top: 4rem; }
        .site-header .container, .site-footer .container { padding-top: 1.25rem; padding-bottom: 1.25rem; }
        .brand { font-size: 1.25rem; font-weight: 700; }
        .content { padding: 2rem 0 4rem; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06); }
        .card + .card { margin-top: 1rem; }
        .card-body { padding: 1.5rem; }
        .eyebrow { font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: #6366f1; font-weight: 700; }
        .muted { color: #64748b; }
        .meta { font-size: .875rem; color: #64748b; }
        .prose { line-height: 1.75; }
        .prose img { max-width: 100%; height: auto; }
        .chips { display: flex; gap: .5rem; flex-wrap: wrap; }
        .chip { background: #eef2ff; color: #4338ca; border-radius: 9999px; padding: .35rem .75rem; font-size: .8rem; font-weight: 600; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a href="/" class="brand">{{ \App\Models\Setting::get('general', 'site_name', 'MarkdownPress') }}</a>
    </div>
</header>
<main class="container content">
