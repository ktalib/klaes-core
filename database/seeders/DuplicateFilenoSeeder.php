<?php

namespace Database\Seeders;

use App\Models\DuplicateFileno;
use App\Services\CsvImporter\Support\CsvValueNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DuplicateFilenoSeeder extends Seeder
{
    // Keep batches small to stay under SQL Server's 2,100-parameter limit during upserts
    private const CHUNK_SIZE = 150;

    private const TYPE_DUPLICATE = 'duplicate';
    private const TYPE_TEMPORARY = 'temporary';
    private const TYPE_WRC = 'wrc';

    /**
     * @var array<int, array<string, string>>
     */
    private array $baseSources = [
        ['path' => 'resources/views/file_search_db/csv/DUPLICATE FILES LIST.csv', 'type' => self::TYPE_DUPLICATE],
        ['path' => 'resources/views/file_search_db/csv/temp.csv', 'type' => self::TYPE_TEMPORARY],
        ['path' => 'resources/views/file_search_db/csv/wcr.csv', 'type' => self::TYPE_WRC],
    ];

    public function run(): void
    {
        $normalizer = app(CsvValueNormalizer::class);
        $files = $this->resolveSourceFiles();

        $this->command?->info('DuplicateFilenoSeeder: Resolved ' . count($files) . ' source files.');
        
        foreach ($files as $file) {
            $this->command?->info('Found file: ' . $file['path'] . ' (type: ' . $file['type'] . ')');
        }

        if (empty($files)) {
            $this->command?->warn('DuplicateFilenoSeeder: No CSV files found for import.');
            return;
        }

        DuplicateFileno::on('sqlsrv')->delete();
        $this->command?->info('DuplicateFilenoSeeder: Cleared existing records.');

        $totalRecords = 0;
        foreach ($files as $file) {
            $count = $this->seedFromFile($file['path'], $normalizer, $file['type']);
            $totalRecords += $count;
        }
        
        $this->command?->info('DuplicateFilenoSeeder: Imported ' . $totalRecords . ' records total.');
    }

    /**
     * @return array<int, array{path: string, type: string}>
     */
    private function resolveSourceFiles(): array
    {
        $resolved = [];

        foreach ($this->baseSources as $source) {
            $absolute = $this->toAbsolutePath($source['path']);
            if ($absolute !== null) {
                $resolved[] = ['path' => $absolute, 'type' => $source['type']];
            }
        }

        $wildcardMatches = glob(database_path('csv/duplicate_fileno*.csv')) ?: [];
        foreach ($wildcardMatches as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $resolved[] = ['path' => $path, 'type' => self::TYPE_DUPLICATE];
        }

        $unique = [];
        foreach ($resolved as $entry) {
            $unique[$entry['path']] = $entry;
        }

        return array_values($unique);
    }

    private function toAbsolutePath(string $relativePath): ?string
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');

        $candidates = [
            base_path($normalized),
            resource_path('views/file_search_db/csv/' . basename($normalized)),
            database_path('csv/' . basename($normalized)),
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }

    private function seedFromFile(string $path, CsvValueNormalizer $normalizer, string $type): int
    {
        if (! is_readable($path)) {
            $this->command?->warn(sprintf('DuplicateFilenoSeeder: Skipping unreadable file %s', $path));
            return 0;
        }

        $this->command?->info(sprintf('DuplicateFilenoSeeder: Processing file %s (type: %s)', basename($path), $type));
        
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->command?->warn(sprintf('DuplicateFilenoSeeder: Unable to open %s', $path));
            return 0;
        }

        $headers = null;
        $chunk = [];
        $processedRows = 0;
        $skippedRows = 0;
        $totalRecords = 0;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = $this->normalizeHeaders($row);
                    $this->command?->info('Headers: ' . implode(', ', array_filter($headers)));
                    continue;
                }

                if ($this->isRowEmpty($row)) {
                    $skippedRows++;
                    continue;
                }

                $record = $this->mapRowToRecord($headers, $row);

                $registry = $this->cleanString($record['registry'] ?? null);
                $fileNumber = $normalizer->standardizeFileNumber($record['file_number'] ?? null);

                if ($registry === null || $fileNumber === null) {
                    $skippedRows++;
                    continue;
                }

                $chunk[] = [
                    'registry' => $registry,
                    'file_number' => $fileNumber,
                    'file_title' => $this->cleanString($record['file_title'] ?? null),
                    'plot_number' => $this->cleanString($record['plot_number'] ?? null),
                    'location' => $this->cleanString($record['location'] ?? null),
                    'comment' => $this->decorateComment($this->cleanString($record['comment'] ?? $record['comments'] ?? null), $type),
                ];
                
                $processedRows++;

                if (count($chunk) >= self::CHUNK_SIZE) {
                    $inserted = $this->persistChunk($chunk, $type);
                    $totalRecords += $inserted;
                    $chunk = [];
                }
            }
        } finally {
            fclose($handle);
        }

        if (! empty($chunk)) {
            $inserted = $this->persistChunk($chunk, $type);
            $totalRecords += $inserted;
        }
        
        $this->command?->info(sprintf('File %s: %d processed, %d skipped, %d inserted', basename($path), $processedRows, $skippedRows, $totalRecords));
        
        return $totalRecords;
    }

    /**
     * @param array<int, string|null> $headers
     * @param array<int, string|null> $row
     */
    private function mapRowToRecord(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            if ($header === null || $header === '') {
                continue;
            }

            $mapped[$header] = Arr::get($row, $index);
        }

        return $mapped;
    }

    /**
     * @param array<int, string|null> $row
     * @return array<int, string|null>
     */
    private function normalizeHeaders(array $row): array
    {
        return array_map(function ($value) {
            $trimmed = $this->cleanString($value);
            
            if ($trimmed === null) {
                return null;
            }

            // Convert to lowercase and replace spaces with underscores for field mapping
            $normalized = Str::lower($trimmed);
            $normalized = str_replace(' ', '_', $normalized);
            
            return $normalized;
        }, $row);
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->cleanString($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function cleanString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    /**
     * @param array<int, array<string, mixed>> $chunk
     */
    private function persistChunk(array $chunk, string $type): int
    {
        if (empty($chunk)) {
            return 0;
        }

        $timestamp = Carbon::now();

        $payload = array_map(function (array $row) use ($timestamp, $type) {
            $row['created_at'] = $row['created_at'] ?? $timestamp;
            $row['updated_at'] = $timestamp;
            $row['source'] = sprintf('csv:%s', $type);

            return $row;
        }, $chunk);

        try {
            DuplicateFileno::on('sqlsrv')->insert($payload);
            return count($payload);
        } catch (\Exception $e) {
            $this->command?->error('Failed to insert chunk: ' . $e->getMessage());
            return 0;
        }
    }

    private function decorateComment(?string $comment, string $type): string
    {
        $prefix = '[' . strtoupper($type) . ']';

        if ($comment === null || $comment === '') {
            return $prefix;
        }

        $trimmed = trim($comment);

        if (str_starts_with($trimmed, $prefix)) {
            return $trimmed;
        }

        return $prefix . ' ' . $trimmed;
    }
}
