<?php
namespace App\Services;

use App\Models\Post;
use App\Models\User;

class WordPressImporter
{
    public function import(string $xmlPath, string $mode = 'append'): array
    {
        $result = ['posts' => 0, 'skipped' => 0, 'errors' => []];

        if (!file_exists($xmlPath)) {
            $result['errors'][] = "File not found: {$xmlPath}";
            return $result;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlPath, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            $result['errors'][] = 'Failed to parse XML file.';
            return $result;
        }

        if ($mode === 'fresh') {
            Post::query()->forceDelete();
        }

        $adminUser = User::where('role', 'admin')->first();
        $defaultUserId = $adminUser?->id ?? 1;

        $xml->registerXPathNamespace('wp',      'http://wordpress.org/export/1.2/');
        $xml->registerXPathNamespace('content', 'http://purl.org/rss/1.0/modules/content/');

        $items = $xml->channel->item ?? [];

        foreach ($items as $item) {
            $nsWp      = $item->children('http://wordpress.org/export/1.2/');
            $nsContent = $item->children('http://purl.org/rss/1.0/modules/content/');

            $postType = (string) $nsWp->post_type;
            $status   = (string) $nsWp->status;

            if ($postType !== 'post' || $status !== 'publish') continue;

            $title       = (string) $item->title;
            $slug        = (string) $nsWp->post_name;
            $content     = (string) $nsContent->encoded;
            $publishedAt = (string) $nsWp->post_date;

            if (!$slug) $slug = \Illuminate\Support\Str::slug($title);

            if ($mode === 'append' && Post::where('slug', $slug)->exists()) {
                $result['skipped']++;
                continue;
            }

            try {
                Post::create([
                    'user_id'          => $defaultUserId,
                    'title'            => $title,
                    'slug'             => $slug,
                    'content_markdown' => $content,
                    'published_at'     => $publishedAt ?: now(),
                    'status'           => 'published',
                ]);
                $result['posts']++;
            } catch (\Throwable $e) {
                $result['errors'][] = "Failed to import [{$slug}]: " . $e->getMessage();
            }
        }

        return $result;
    }
}
