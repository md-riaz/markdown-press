<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_show_returns_public_metadata(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane Developer',
            'username' => 'jane_dev',
        ]);

        $media = Media::create([
            'user_id' => $user->id,
            'filename' => 'laravel-intro.jpg',
            'disk' => 'public',
            'path' => 'media/laravel-intro.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 204800,
            'width' => 1200,
            'height' => 630,
            'alt_text' => 'Laravel framework logo on a dark background',
            'is_starred' => false,
            'collection' => 'images',
        ]);

        $response = $this->getJson("/api/v1/media/{$media->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $media->id);
        $response->assertJsonPath('data.filename', 'laravel-intro.jpg');
        $response->assertJsonPath('data.mime_type', 'image/jpeg');
        $response->assertJsonPath('data.size_bytes', 204800);
        $response->assertJsonPath('data.width', 1200);
        $response->assertJsonPath('data.height', 630);
        $response->assertJsonPath('data.alt_text', 'Laravel framework logo on a dark background');
        $response->assertJsonPath('data.starred', false);
        $response->assertJsonPath('data.uploaded_by.username', 'jane_dev');
    }

    public function test_media_show_returns_not_found_for_missing_media(): void
    {
        $this->getJson('/api/v1/media/999999')
            ->assertNotFound();
    }

    public function test_media_show_response_structure_is_stable(): void
    {
        $user = User::factory()->create();

        $media = Media::create([
            'user_id' => $user->id,
            'filename' => 'cover.png',
            'disk' => 'public',
            'path' => 'media/cover.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'width' => 100,
            'height' => 100,
            'alt_text' => 'Cover image',
            'is_starred' => true,
            'collection' => 'images',
        ]);

        $this->getJson("/api/v1/media/{$media->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'filename',
                    'url',
                    'mime_type',
                    'size_bytes',
                    'width',
                    'height',
                    'alt_text',
                    'starred',
                    'created_at',
                    'updated_at',
                    'uploaded_by' => [
                        'id',
                        'username',
                        'name',
                    ],
                ],
            ]);
    }
}
