<?php
namespace App\Services\StockPhoto;

use App\Contracts\StockPhotoServiceContract;
use Illuminate\Support\Facades\Http;

class PixabayDriver implements StockPhotoServiceContract
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('media.stock_photos.pixabay.api_key', '');
        $this->baseUrl = config('media.stock_photos.pixabay.base_url', 'https://pixabay.com/api');
    }

    public function search(string $query, int $perPage = 20): array
    {
        $response = Http::get($this->baseUrl, [
            'key'      => $this->apiKey,
            'q'        => $query,
            'per_page' => $perPage,
        ]);

        $hits = $response->json('hits', []);

        return array_map(fn ($item) => [
            'id'          => (string) $item['id'],
            'url'         => $item['largeImageURL'],
            'thumb'       => $item['previewURL'],
            'description' => $item['tags'] ?? '',
            'credit'      => $item['user'] ?? '',
        ], $hits);
    }

    public function downloadUrl(string $id): string
    {
        $response = Http::get($this->baseUrl, ['key' => $this->apiKey, 'id' => $id]);
        $hits = $response->json('hits', []);
        return $hits[0]['largeImageURL'] ?? '';
    }
}
