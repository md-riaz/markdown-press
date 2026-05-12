<?php
namespace App\Services\StockPhoto;

use App\Contracts\StockPhotoServiceContract;
use Illuminate\Support\Facades\Http;

class UnsplashDriver implements StockPhotoServiceContract
{
    private string $accessKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->accessKey = config('media.stock_photos.unsplash.access_key', '');
        $this->baseUrl   = config('media.stock_photos.unsplash.base_url', 'https://api.unsplash.com');
    }

    public function search(string $query, int $perPage = 20): array
    {
        $response = Http::withHeaders(['Authorization' => "Client-ID {$this->accessKey}"])
            ->get("{$this->baseUrl}/search/photos", ['query' => $query, 'per_page' => $perPage]);

        $results = $response->json('results', []);

        return array_map(fn ($item) => [
            'id'          => $item['id'],
            'url'         => $item['urls']['full'],
            'thumb'       => $item['urls']['thumb'],
            'description' => $item['description'] ?? $item['alt_description'] ?? '',
            'credit'      => $item['user']['name'] ?? '',
        ], $results);
    }

    public function downloadUrl(string $id): string
    {
        $response = Http::withHeaders(['Authorization' => "Client-ID {$this->accessKey}"])
            ->get("{$this->baseUrl}/photos/{$id}");

        return $response->json('links.download', '');
    }
}
