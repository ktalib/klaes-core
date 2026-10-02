<?php

namespace App\Console\Commands;

use App\Models\FileIndexing;
use App\Services\RegistryDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Audits file_indexings.registry / general_registry / physical_registry
 * against the Registry 1/2/3 rules in config/file_ranges.php and, with
 * --apply, corrects rows that disagree with what the file number's
 * prefix+year actually maps to. Same command for local dev and production —
 * defaults to a dry-run report; nothing is written until --apply is passed.
 *
 * Scope: only rows RegistryDetector recognizes as belonging to the Lands/SIT
 * registry family (i.e. covered by config/file_ranges.php). Files from other
 * registries (ST, SLTR, DCIV, KANGIS, Survey) are left untouched — this tool
 * doesn't have rules for them.
 *
 * general_registry is fully deterministic from the file number (same rule
 * FileIndexing::detectRegistryFromFileNumber() already uses elsewhere), so
 * — like `registry` — it's always corrected to the canonical label, even
 * when a (wrong/inconsistently-spelled) value is already present.
 *
 * physical_registry is only ever filled in when EMPTY — an existing value is
 * never overwritten, since it can legitimately carry sub-classification
 * (e.g. "Registry 1 - Deed" vs "Registry 1 - Land") this tool has no way to
 * infer from the file number alone.
 */
class FixFileIndexingRegistry extends Command
{
    protected $signature = 'registry:fix-file-indexings
        {--apply : Write the corrections. Without this flag, only a report is produced.}
        {--chunk=500 : Rows per chunk}
        {--limit=0 : Stop after auditing this many rows (0 = no limit)}';

    protected $description = 'Audit/correct file_indexings.registry, general_registry and physical_registry using the Registry 1/2/3 rules in config/file_ranges.php (Lands + SIT registry family only).';

    /** @var array<string,int> */
    protected array $counts = [
        'scanned' => 0,
        'registry_corrected' => 0,
        'registry_unchanged' => 0,
        'general_registry_filled' => 0,
        'physical_registry_filled' => 0,
        'empty_file_number' => 0,
        'unparseable_no_year' => 0,
        'prefix_not_in_registry_config' => 0,
        'registry_label_has_no_number' => 0,
    ];

    /** @var array<int,array<string,mixed>> */
    protected array $logRows = [];

    public function handle(RegistryDetector $detector): int
    {
        $apply = (bool) $this->option('apply');
        $chunkSize = max(50, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));

        $conn = DB::connection('sqlsrv');

        $this->info($apply
            ? 'Running in APPLY mode — file_indexings will be updated.'
            : 'Running in DRY-RUN mode — no changes will be written (pass --apply to write).');

        $query = $conn->table('file_indexings')
            ->select(['id', 'file_number', 'temp_file_no', 'mls_file_no', 'registry', 'general_registry', 'physical_registry'])
            ->orderBy('id');

        $processed = 0;

        $query->chunkById($chunkSize, function ($rows) use ($detector, $apply, $conn, &$processed, $limit) {
            foreach ($rows as $row) {
                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }
                $processed++;
                $this->auditRow($row, $detector, $apply, $conn);
            }
            return true;
        });

        $this->counts['scanned'] = $processed;

        $this->writeCsvLog($apply);
        $this->printSummary($apply);

        return self::SUCCESS;
    }

    protected function auditRow(object $row, RegistryDetector $detector, bool $apply, $conn): void
    {
        $candidate = $this->firstNonEmpty([$row->file_number, $row->temp_file_no, $row->mls_file_no]);
        $detected = $detector->detect($candidate);

        if ($detected['registry'] === null) {
            $this->counts[$detected['reason']] = ($this->counts[$detected['reason']] ?? 0) + 1;
            return;
        }

        $registryNumber = $detected['registry'];
        $generalLabel = FileIndexing::detectRegistryFromFileNumber($candidate);

        $updates = [];

        // registry: always corrected to match the file number, whatever it currently holds.
        $expectedRegistry = (string) $registryNumber;
        $currentRegistry = trim((string) $row->registry);
        if ($currentRegistry !== $expectedRegistry) {
            $updates['registry'] = $expectedRegistry;
            $this->logRows[] = [
                'id' => $row->id, 'file_number' => $candidate, 'field' => 'registry',
                'old_value' => $row->registry, 'new_value' => $expectedRegistry,
            ];
        }

        // general_registry: like `registry`, this is fully deterministic from the file
        // number (FileIndexing::detectRegistryFromFileNumber), so — unlike physical_registry
        // below — always enforce the canonical label. Historical data had every spelling
        // variant under the sun ("Land Registry", "Land  Registry" w/ double space, and
        // outright wrong categories like "Cadastral Registry" on RES/COM files) that a
        // fill-only-when-empty rule would never have touched.
        $currentGeneral = trim((string) $row->general_registry);
        if ($generalLabel !== null && $currentGeneral !== $generalLabel) {
            $updates['general_registry'] = $generalLabel;
            $this->logRows[] = [
                'id' => $row->id, 'file_number' => $candidate, 'field' => 'general_registry',
                'old_value' => $row->general_registry, 'new_value' => $generalLabel,
            ];
        }

        // physical_registry: only filled in when currently empty. SIT files map to the
        // catalog's "SIT Registry" entry; the Lands family (RES/COM/IND/AG and their
        // RC/CON- variants) maps to "Registry {N} - Land", matching physical_registries.name.
        if (trim((string) $row->physical_registry) === '') {
            $physicalLabel = $generalLabel === 'SIT Registry' ? 'SIT Registry' : "Registry {$registryNumber} - Land";
            $updates['physical_registry'] = $physicalLabel;
            $this->logRows[] = [
                'id' => $row->id, 'file_number' => $candidate, 'field' => 'physical_registry',
                'old_value' => $row->physical_registry, 'new_value' => $physicalLabel,
            ];
        }

        if (empty($updates)) {
            $this->counts['registry_unchanged']++;
            return;
        }

        if (isset($updates['registry'])) {
            $this->counts['registry_corrected']++;
        }
        if (isset($updates['general_registry'])) {
            $this->counts['general_registry_filled']++;
        }
        if (isset($updates['physical_registry'])) {
            $this->counts['physical_registry_filled']++;
        }

        if ($apply) {
            $conn->table('file_indexings')->where('id', $row->id)->update($updates);
        }
    }

    protected function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }

    protected function writeCsvLog(bool $apply): void
    {
        if (empty($this->logRows)) {
            return;
        }

        $dir = storage_path('app/registry-audit');
        File::ensureDirectoryExists($dir);

        $prefix = $apply ? 'applied' : 'dry-run';
        $path = $dir . DIRECTORY_SEPARATOR . $prefix . '-' . now()->format('Y-m-d_His') . '.csv';

        $handle = fopen($path, 'w');
        fputcsv($handle, ['id', 'file_number', 'field', 'old_value', 'new_value']);
        foreach ($this->logRows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->info("Detail log written to: {$path}");
    }

    protected function printSummary(bool $apply): void
    {
        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Rows scanned', $this->counts['scanned']],
            [$apply ? 'registry corrected' : 'registry would correct', $this->counts['registry_corrected']],
            [$apply ? 'general_registry corrected' : 'general_registry would correct', $this->counts['general_registry_filled']],
            [$apply ? 'physical_registry filled' : 'physical_registry would fill', $this->counts['physical_registry_filled']],
            ['Already fully correct', $this->counts['registry_unchanged']],
            ['Empty file number', $this->counts['empty_file_number']],
            ['Unparseable (no 4-digit year)', $this->counts['unparseable_no_year']],
            ['Prefix outside Registry 1/2/3 config (other registry family)', $this->counts['prefix_not_in_registry_config']],
            ['Config range has no numeric registry label', $this->counts['registry_label_has_no_number']],
        ]);

        $totalWouldChange = $this->counts['registry_corrected'] + $this->counts['general_registry_filled'] + $this->counts['physical_registry_filled'];
        if (!$apply && $totalWouldChange > 0) {
            $this->warn("Re-run with --apply to write these correction(s).");
        }
    }
}
