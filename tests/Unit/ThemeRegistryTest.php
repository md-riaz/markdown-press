<?php

namespace Tests\Unit;

use App\Models\Theme;
use App\Modules\Theme\ThemeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_discovers_and_upserts_theme_manifests(): void
    {
        $registry = app(ThemeRegistry::class);

        $themes = $registry->discover();

        $this->assertGreaterThanOrEqual(3, $themes->count());
        $this->assertDatabaseHas('themes', ['slug' => 'hello-world']);
        $this->assertDatabaseHas('themes', ['slug' => 'developer']);
        $this->assertDatabaseHas('themes', ['slug' => 'monolith']);

        $record = Theme::where('slug', 'hello-world')->firstOrFail();
        $this->assertSame('Hello World', $record->name);
        $this->assertSame('hello-world', data_get($record->config, 'key'));
        $this->assertSame('1.0.0', data_get($record->config, 'version'));
    }
}
