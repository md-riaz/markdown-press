<?php

namespace App\Modules\StaticGen;

use App\Models\Post;
use App\Models\StaticBuild;
use App\Models\Theme;
use App\Modules\Post\RenderCache;
use App\Modules\Theme\ThemeRenderer;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BuildOrchestrator
{
    public function __construct(
        private ThemeRenderer $renderer,
        private RenderCache   $cache,
    ) {}

    public function build(Theme $theme): StaticBuild
    {
        $build = StaticBuild::create([
            'theme_id'   => $theme->id,
            'status'     => 'running',
            'started_at' => now(),
        ]);

        try {
            $tmpDir = storage_path("app/builds/build_{$build->id}");
            @mkdir($tmpDir, 0755, true);

            $fileCount  = 0;
            $imageCount = 0;

            // Build index page
            $posts = Post::published()->with(['author','categories','tags','featuredImage'])->latest('published_at')->get()->all();
            $indexHtml = $this->renderer->renderIndex($posts, $theme);
            file_put_contents("{$tmpDir}/index.html", $indexHtml);
            $fileCount++;

            // Build each post
            foreach ($posts as $post) {
                $html = $this->cache->get($post);
                $slug = $post->slug;
                @mkdir("{$tmpDir}/{$slug}", 0755, true);
                file_put_contents("{$tmpDir}/{$slug}/index.html", $html);
                $fileCount++;
            }

            // Copy theme assets
            $assetsDir = public_path("themes/{$theme->slug}");
            if (is_dir($assetsDir)) {
                \Illuminate\Support\Facades\File::copyDirectory($assetsDir, "{$tmpDir}/assets");
            }

            // Create ZIP
            $zipPath = "builds/build_{$build->id}.zip";
            $zipAbsolute = storage_path("app/{$zipPath}");
            @mkdir(dirname($zipAbsolute), 0755, true);

            $zip = new ZipArchive();
            $zip->open($zipAbsolute, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $this->addDirToZip($zip, $tmpDir, '');
            $zip->close();

            $zipSize = filesize($zipAbsolute);

            // Cleanup temp
            $this->rrmdir($tmpDir);

            $build->update([
                'status'       => 'completed',
                'file_count'   => $fileCount,
                'image_count'  => $imageCount,
                'zip_size'     => $zipSize,
                'zip_path'     => $zipPath,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $build->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }

        return $build->fresh();
    }

    private function addDirToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        foreach (glob("{$dir}/*") as $file) {
            if (is_dir($file)) {
                $this->addDirToZip($zip, $file, $prefix . basename($file) . '/');
            } else {
                $zip->addFile($file, $prefix . basename($file));
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        foreach (glob("{$dir}/*") as $file) {
            is_dir($file) ? $this->rrmdir($file) : unlink($file);
        }
        @rmdir($dir);
    }
}
