<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Modules\StaticGen\BuildOrchestrator;
use Illuminate\Console\Command;

class BuildBlogCommand extends Command
{
    protected $signature   = 'blog:build {--theme= : Theme slug to use for the build}';
    protected $description = 'Generate a static HTML build of the blog';

    public function handle(BuildOrchestrator $orchestrator): int
    {
        $slug  = $this->option('theme');
        $theme = $slug
            ? Theme::where('slug', $slug)->firstOrFail()
            : Theme::where('is_active', true)->firstOrFail();

        $this->info("Building with theme [{$theme->name}]...");

        $build = $orchestrator->build($theme);

        if ($build->status === 'completed') {
            $this->info("Build #{$build->id} completed — {$build->file_count} files, ZIP: {$build->zip_path}");
            return self::SUCCESS;
        }

        $this->error("Build failed: {$build->error_message}");
        return self::FAILURE;
    }
}
