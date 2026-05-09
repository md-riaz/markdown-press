<?php
namespace App\Contracts;

interface StockPhotoServiceContract
{
    public function search(string $query, int $perPage = 20): array;
    public function downloadUrl(string $id): string;
}
