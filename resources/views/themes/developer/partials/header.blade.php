<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription ?? \App\Models\Setting::get('general', 'site_description') }}">
    <title>{{ $title ?? \App\Models\Setting::get('general', 'site_name', 'MarkdownPress') }}</title>
    <style>
        :root { color-scheme: dark; }
        body { margin: 0; font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace; background: #0b1020; color: #e2e8f0; }
        a { color: #22d3ee; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .container { max-width: 1040px; margin: 0 auto; padding: 0 1.25rem; }
        .site-header, .site-footer { background: #111827; border-color: #1f2937; border-style: solid; }
        .site-header { border-width: 0 0 1px; }
        .site-footer { border-width: 1px 0 0; margin-top: 4rem; }
        .site-header .container, .site-footer .container { padding-top: 1.25rem; padding-bottom: 1.25rem; }
        .brand { font-size: 1.1rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .content { padding: 2rem 0 4rem; }
        .card { background: #111827; border: 1px solid #1f2937; border-radius: .75rem; }
        .card + .card { margin-top: 1rem; }
        .card-body { padding: 1.25rem; }
        .eyebrow { font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: #a78bfa; font-weight: 700; }
        .muted { color: #94a3b8; }
        .meta { font-size: .875rem; color: #94a3b8; }
        .prose { line-height: 1.8; }
        .prose pre { background: #020617; color: #e2e8f0; padding: .875rem; border-radius: .5rem; overflow-x: auto; }
        .chips { display: flex; gap: .5rem; flex-wrap: wrap; }
        .chip { background: #0f172a; color: #67e8f9; border-radius: 9999px; padding: .35rem .75rem; font-size: .8rem; font-weight: 600; border: 1px solid #1f2937; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container">
        <a href="/" class="brand">{{ \App\Models\Setting::get('general', 'site_name', 'MarkdownPress') }}</a>
    </div>
</header>
<main class="container content">
