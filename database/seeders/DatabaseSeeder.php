<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\ShortcodeRegistry;
use App\Models\Tag;
use App\Models\Theme;
use App\Models\User;
use App\Modules\Theme\ThemeRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ─────────────────────────────────────────────────────────────

        $admin = User::factory()->create([
            'name'     => 'Admin User',
            'email'    => 'admin@markdownpress.test',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'username' => 'admin',
            'bio'      => 'Platform administrator and chief editor.',
        ]);

        $editor = User::factory()->create([
            'name'     => 'Editor User',
            'email'    => 'editor@markdownpress.test',
            'password' => Hash::make('password'),
            'role'     => 'editor',
            'username' => 'editor',
            'bio'      => 'Senior content editor.',
        ]);

        $authors = User::factory(3)->create(['role' => 'author']);

        // ── Taxonomy ──────────────────────────────────────────────────────────

        $categories = Category::factory(8)->create();
        $tags        = Tag::factory(20)->create();

        // ── Posts ─────────────────────────────────────────────────────────────

        $allUsers = $authors->push($editor)->push($admin);

        Post::factory(50)->create(['status' => 'published', 'published_at' => now()])
            ->each(function (Post $post) use ($categories, $tags, $allUsers) {
                $post->user_id = $allUsers->random()->id;
                $post->save();
                $post->categories()->attach($categories->random(rand(1, 2)));
                $post->tags()->attach($tags->random(rand(2, 5)));

                // A couple approved comments per post
                Comment::factory(rand(0, 4))->create(['post_id' => $post->id]);
            });

        Post::factory(10)->draft()->create(['user_id' => $admin->id]);

        // ── Theme ─────────────────────────────────────────────────────────────

        /** @var ThemeRegistry $registry */
        $registry = app(ThemeRegistry::class);
        $themes   = $registry->discover();

        if ($themes->isEmpty()) {
            $themes = collect([
                Theme::create([
                    'name'        => 'Hello World',
                    'slug'        => 'hello-world',
                    'description' => 'Clean and minimal starter theme.',
                    'config'      => [
                        'key'    => 'hello-world',
                        'name'   => 'Hello World',
                        'author' => 'MarkdownPress Team',
                        'version' => '1.0.0',
                    ],
                ]),
            ]);
        }

        Theme::query()->update(['is_active' => false, 'is_default' => false]);
        $defaultTheme = $themes->firstWhere('slug', config('theme.default_theme', 'hello-world')) ?? $themes->first();
        if ($defaultTheme) {
            $defaultTheme->update(['is_active' => true, 'is_default' => true]);
        }

        // ── Settings ──────────────────────────────────────────────────────────

        Setting::set('general', 'site_name',        'MarkdownPress');
        Setting::set('general', 'site_description',  'A Markdown-first blogging CMS built on Laravel.');
        Setting::set('seo',     'default_meta_description', 'Read the latest articles on MarkdownPress.');

        // ── Shortcode Registry ────────────────────────────────────────────────

        $shortcodes = [
            ['name' => 'youtube',  'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\YoutubeHandler',  'description' => 'Embed YouTube video'],
            ['name' => 'gist',     'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\GistHandler',     'description' => 'Embed GitHub Gist'],
            ['name' => 'codepen',  'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\CodepenHandler',  'description' => 'Embed CodePen'],
            ['name' => 'mermaid',  'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\MermaidHandler',  'description' => 'Render Mermaid diagram'],
            ['name' => 'alert',    'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\AlertHandler',    'description' => 'Styled alert block'],
            ['name' => 'audio',    'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\AudioHandler',    'description' => 'HTML5 audio player'],
            ['name' => 'video',    'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\VideoHandler',    'description' => 'HTML5 video player'],
            ['name' => 'tweet',    'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\TweetHandler',    'description' => 'Embed Tweet'],
            ['name' => 'facebook', 'handler_class' => 'App\\Modules\\Shortcode\\Handlers\\FacebookHandler', 'description' => 'Embed Facebook post'],
        ];

        foreach ($shortcodes as $sc) {
            ShortcodeRegistry::create($sc);
        }
    }
}
