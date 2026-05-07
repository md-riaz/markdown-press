</main>
<footer class="site-footer">
    <div class="container muted">
        &copy; {{ date('Y') }} {{ \App\Models\Setting::get('general', 'site_name', 'MarkdownPress') }}.
    </div>
</footer>
</body>
</html>
