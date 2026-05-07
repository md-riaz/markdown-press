<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? \App\Models\Setting::get('general', 'site_description') }}">
    <title>{{ $title ?? \App\Models\Setting::get('general', 'site_name', 'MarkdownPress') }}</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: "Source Sans 3", Inter, system-ui, sans-serif; background: #ffffff; color: #111827; }
        a { color: #0f766e; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .container { max-width: 1180px; margin: 0 auto; padding: 0 1.5rem; }
        .site-header, .site-footer { background: #f8fafc; border-color: #e5e7eb; border-style: solid; }
        .site-header { border-width: 0 0 1px; }
        .site-footer { border-width: 1px 0 0; margin-top: 4rem; }
        .site-header .container, .site-footer .container { padding-top: 1.25rem; padding-bottom: 1.25rem; }
        .brand { font-size: 1.35rem; font-weight: 800; }
        .content { padding: 2rem 0 4rem; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: .75rem; }
        .card + .card { margin-top: 1rem; }
        .card-body { padding: 1.5rem; }
        .eyebrow { font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: #0f766e; font-weight: 700; }
        .muted { color: #6b7280; }
        .meta { font-size: .9rem; color: #6b7280; }
        .prose { line-height: 1.75; font-size: 1.05rem; }
        .prose img { max-width: 100%; height: auto; }
        .chips { display: flex; gap: .5rem; flex-wrap: wrap; }
        .chip { background: #ecfeff; color: #0f766e; border-radius: 9999px; padding: .35rem .75rem; font-size: .8rem; font-weight: 600; border: 1px solid #ccfbf1; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a href="/" class="brand">{{ \App\Models\Setting::get('general', 'site_name', 'MarkdownPress') }}</a>
    </div>
</header>
<main class="container content">
