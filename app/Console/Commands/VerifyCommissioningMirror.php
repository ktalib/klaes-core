<?php

namespace App\Console\Commands;

use App\Models\DcivFileNo;
use App\Models\FileIndexing;
use App\Models\GknFileNo;
use App\Services\CommissioningMirrorService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class VerifyCommissioningMirror extends Command
{
    protected $signature = 'fileindexing:verify-mirror
        {--registry= : Filter by registry label (e.g. DCIV, Survey)}
        {--file-number= : Target a specific file number}
        {--id= : Target a specific file_indexings.id}
        {--limit=1 : Number of rows to test when filtering by registry}';

    protected $description = 'Re-run the File Indexing commissioning mirror for selected records and report downstream status.';

    public function handle(CommissioningMirrorService $mirrorService): int
    {
        $targets = $this->determineTargets();

        if ($targets->isEmpty()) {
            $this->warn('No File Indexing records matched the provided filters.');
            $this->line('Try providing --registry=DCIV or --registry=Survey to grab recent entries.');
            return self::FAILURE;
        }

        $rows = [];
        foreach ($targets as $fileIndexing) {
            $related = $this->decodeRelatedNumbers($fileIndexing->related_fileno);
            $mirrorService->mirror($fileIndexing, [], $related);

            $rows[] = [
                'id' => $fileIndexing->id,
                'file_number' => $fileIndexing->file_number,
                'registry' => $fileIndexing->general_registry ?? $fileIndexing->registry ?? 'N/A',
                'dciv' => DcivFileNo::where('full_file_number', $fileIndexing->file_number)->exists() ? '✔' : '—',
                'gkn' => GknFileNo::where('full_file_number', $fileIndexing->file_number)->exists() ? '✔' : '—',
            ];
        }

        $this->table(['ID', 'File Number', 'Registry', 'DCIV Mirror', 'GKN Mirror'], $rows);

        $this->info(sprintf('Validated %d record(s).', count($rows)));

        return self::SUCCESS;
    }

    protected function determineTargets(): Collection
    {
        if ($id = $this->option('id')) {
            return FileIndexing::query()
                ->whereKey((int) $id)
                ->get();
        }

        if ($fileNumber = $this->option('file-number')) {
            return FileIndexing::query()
                ->where('file_number', trim($fileNumber))
                ->orderByDesc('id')
                ->get();
        }

        if ($registry = $this->option('registry')) {
            $limit = (int) $this->option('limit');
            if ($limit < 1) {
                $limit = 1;
            }

            return $this->queryByRegistry($registry, $limit);
        }

        return $this->defaultTargets();
    }

    protected function queryByRegistry(string $needle, int $limit): Collection
    {
        $trimmed = trim($needle);

        return FileIndexing::query()
            ->where(function ($query) use ($trimmed) {
                $query->where('general_registry', 'LIKE', '%' . $trimmed . '%')
                    ->orWhere('registry', 'LIKE', '%' . $trimmed . '%');
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    protected function defaultTargets(): Collection
    {
        $needles = ['DCIV', 'SURVEY'];
        $collection = collect();

        foreach ($needles as $needle) {
            $record = $this->queryByRegistry($needle, 1)->first();
            if ($record) {
                $collection->push($record);
            }
        }

        return $collection->unique('id');
    }

    protected function decodeRelatedNumbers($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
