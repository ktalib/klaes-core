<?php

namespace App\Services\CsvImporter\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FileNumberCatalog
{
    private const CACHE_KEY = 'csv_importer.correct_fileno.catalog';

    public function __construct(private readonly ?string $datasetPath = null)
    {
    }

    public function getCatalog(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds((int) config('csv_importer.catalog_cache_ttl')),
            fn () => $this->loadCatalog()
        );
    }

    public function listCategoryCodes(): array
    {
        $catalog = $this->getCatalog();
        $categories = Arr::get($catalog, 'categories', []);

        return collect($categories)
            ->pluck('code')
            ->filter()
            ->map(fn ($code) => Str::upper((string) $code))
            ->unique()
            ->values()
            ->all();
    }

    public function findCategoryByCode(string $code): ?array
    {
        $target = Str::upper($code);
        $catalog = $this->getCatalog();
        $categories = Arr::get($catalog, 'categories', []);

        foreach ($categories as $category) {
            if (Str::upper((string) Arr::get($category, 'code')) === $target) {
                return $category;
            }
        }

        return null;
    }

    public function matchFileNumber(?string $fileNumber): ?array
    {
        if (!$fileNumber) {
            return null;
        }

        $prefix = Str::upper(Str::before($fileNumber, '-'));
        if ($prefix === '') {
            return null;
        }

        $category = $this->findCategoryByCode($prefix);
        if ($category === null) {
            return null;
        }

        return [
            'code' => $category['code'],
            'name' => $category['name'] ?? null,
            'samples' => $category['samples'] ?? [],
        ];
    }

    private function loadCatalog(): array
    {
        $path = $this->datasetPath ?? config('csv_importer.correct_fileno_path');

        if (!$path || !File::exists($path)) {
            throw new \RuntimeException(
                sprintf('CSV Importer catalog not found at path: %s', $path)
            );
        }

        $contents = File::get($path);
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(sprintf(
                'Unable to decode %s: %s',
                $path,
                json_last_error_msg()
            ));
        }

        return Arr::get($decoded, 'file_number_system', []);
    }
}
