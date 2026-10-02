<?php

namespace App\Console\Commands;

use App\Services\PropertyIdAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Give Sectional Titling files the prop_id they are missing.
 *
 * ST files are the one group the register cannot resolve a PropID for, and they
 * are NOT all the same problem. st_file_numbers distinguishes two kinds, and
 * they need opposite treatment:
 *
 *  - A PRIMARY row's np_fileno is the ST number given to an EXISTING land file
 *    (ST-MIXED-2025-1 is CON-COM-2004-45). That parcel already has a prop_id.
 *    Minting a second one would split one parcel across two ids, which is the
 *    corruption this command exists to avoid - so the ST number is added to the
 *    old file's master row and ADOPTS its prop_id. Nothing new is minted.
 *
 *  - A PUA row is a unit produced by the fragmentation (ST-RES-2025-2-001).
 *    That is a genuinely new parcel and gets a new prop_id, the same way the
 *    ST-MIXED-2025-1-0xx units already did.
 *
 * Anything that fits neither is reported and left alone: a mother whose old file
 * has no prop_id either wants the OLD file fixed first, not the ST number, and a
 * file with no st_file_numbers row at all cannot be classified from here.
 *
 * Dry run unless --apply is passed.
 */
class BackfillStPropIds extends Command
{
    protected $signature = 'propid:backfill-st
        {--apply : Write the changes. Without this the command only reports what it would do}
        {--only= : Restrict to one kind of fix: adopt or allocate}
        {--allow-junk-band : Allocate even when the next prop_id lands in the poisoned 99,000,00x band}';

    protected $description = 'Resolve missing PropIDs on Sectional Titling files: mothers adopt the old land file\'s prop_id, units get a new one';

    /**
     * The band a poisoned MAX(prop_id) mints into. A stray 99,000,001 row makes
     * generateNextPropIdFromMaster() hand out 99,000,0xx instead of continuing
     * the real sequence, so allocation stops rather than adding to the mess.
     */
    private const JUNK_BAND_FLOOR = 99000000;

    private const FILE_NUMBER_COLUMNS = [
        'primary_file_number_norm',
        'mlsFNo_norm',
        'kangisFileNo_norm',
        'NewKANGISFileno_norm',
        'temp_fileno_norm',
    ];

    public function handle(PropertyIdAllocationService $allocator): int
    {
        $apply = (bool) $this->option('apply');
        $only = $this->option('only');

        if ($only !== null && !in_array($only, ['adopt', 'allocate'], true)) {
            $this->error('--only accepts "adopt" or "allocate".');

            return self::FAILURE;
        }

        $plan = $this->buildPlan();

        if (empty($plan['adopt']) && empty($plan['allocate']) && empty($plan['unclear'])) {
            $this->info('Every ST file already resolves to a PropID. Nothing to do.');

            return self::SUCCESS;
        }

        $this->report($plan);

        if (!$apply) {
            $this->newLine();
            $this->warn('Dry run - nothing was written. Re-run with --apply to make these changes.');

            return self::SUCCESS;
        }

        $adopted = $allocated = $failed = 0;

        if ($only !== 'allocate') {
            foreach ($plan['adopt'] as $file => $row) {
                try {
                    // Passing both numbers lets the service find the old file's
                    // master row and fill the ST number into it: the returned
                    // prop_id is the existing one, and no id is minted.
                    $propId = $allocator->allocateOrRetrievePropId($file, $row['old_file']);
                    $this->line("  adopted  {$file} -> prop_id {$propId}");
                    $adopted++;
                } catch (\Throwable $e) {
                    $this->error("  FAILED   {$file}: " . $e->getMessage());
                    $failed++;
                }
            }
        }

        if ($only !== 'adopt') {
            foreach ($plan['allocate'] as $file => $row) {
                $next = $this->peekNextPropId();
                if ($next >= self::JUNK_BAND_FLOOR && !$this->option('allow-junk-band')) {
                    $this->error("  REFUSED  {$file}: the next prop_id would be {$next}, inside the poisoned 99,000,00x band.");
                    $this->line('           Clean the stray high prop_id out of PropID_Master first, or pass --allow-junk-band.');
                    $failed++;
                    continue;
                }

                try {
                    $propId = $allocator->allocateOrRetrievePropId($file);
                    $this->line("  allocated {$file} -> prop_id {$propId}");
                    $allocated++;
                } catch (\Throwable $e) {
                    $this->error("  FAILED   {$file}: " . $e->getMessage());
                    $failed++;
                }
            }
        }

        $this->newLine();
        $this->info("Adopted {$adopted}, allocated {$allocated}, failed/refused {$failed}. Left alone: " . count($plan['unclear']) . '.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Every ST file number with no resolvable prop_id, sorted into what should
     * happen to it.
     */
    private function buildPlan(): array
    {
        $connection = DB::connection('sqlsrv');
        $norm = fn ($value) => strtoupper(trim((string) $value));

        $candidates = [];

        foreach ($connection->table('st_file_numbers')->get(['fileno', 'np_fileno', 'mls_fileno', 'file_no_type']) as $row) {
            $type = strtoupper(trim((string) $row->file_no_type));

            if ($type === 'PUA' && $norm($row->fileno) !== '') {
                $candidates[$norm($row->fileno)] = ['kind' => 'unit', 'mother' => $norm($row->np_fileno)];
            }

            if ($type === 'PRIMARY' && $norm($row->np_fileno) !== '') {
                $candidates[$norm($row->np_fileno)] = [
                    'kind' => 'mother',
                    'old_file' => $norm($row->mls_fileno ?: $row->fileno),
                ];
            }
        }

        $resolved = $this->resolveExisting(array_keys($candidates));

        $plan = ['adopt' => [], 'allocate' => [], 'unclear' => []];

        foreach ($candidates as $file => $info) {
            if (isset($resolved[$file])) {
                continue;   // already has a prop_id - not our business
            }

            if ($info['kind'] === 'unit') {
                $plan['allocate'][$file] = $info;
                continue;
            }

            $oldFile = $info['old_file'] ?? '';
            if ($oldFile === '' || $oldFile === $file) {
                $plan['unclear'][$file] = 'mother with no distinct old file number';
                continue;
            }

            $oldPropId = $this->propIdFor($oldFile);
            if ($oldPropId === null) {
                $plan['unclear'][$file] = "mother of [{$oldFile}], which has no prop_id either - fix that file first";
                continue;
            }

            $plan['adopt'][$file] = ['old_file' => $oldFile, 'prop_id' => $oldPropId];
        }

        return $plan;
    }

    /** Which of these file numbers already resolve to a prop_id. */
    private function resolveExisting(array $files): array
    {
        $found = [];
        if (empty($files)) {
            return $found;
        }

        foreach (self::FILE_NUMBER_COLUMNS as $column) {
            // Chunked well under the 2,100-parameter ceiling sqlsrv enforces.
            foreach (array_chunk($files, 1000) as $chunk) {
                $matches = DB::connection('sqlsrv')->table('PropID_Master')
                    ->whereNotNull('prop_id')
                    ->whereIn($column, $chunk)
                    ->get(['prop_id', $column]);

                foreach ($matches as $match) {
                    $key = strtoupper(trim((string) ($match->$column ?? '')));
                    if ($key !== '' && trim((string) $match->prop_id) !== '') {
                        $found[$key] = $match->prop_id;
                    }
                }
            }
        }

        return $found;
    }

    private function propIdFor(string $file): ?string
    {
        foreach (self::FILE_NUMBER_COLUMNS as $column) {
            $propId = DB::connection('sqlsrv')->table('PropID_Master')
                ->whereNotNull('prop_id')
                ->where($column, $file)
                ->value('prop_id');

            if ($propId !== null && trim((string) $propId) !== '') {
                return (string) $propId;
            }
        }

        return null;
    }

    /** What the allocator would mint next, without minting it. */
    private function peekNextPropId(): int
    {
        $max = DB::connection('sqlsrv')->table('PropID_Master')
            ->where('prop_id', '<', 2147483647)
            ->orderByDesc('prop_id')
            ->value('prop_id');

        return $max ? ((int) $max + 1) : 1;
    }

    private function report(array $plan): void
    {
        $this->info('ADOPT an existing parcel (ST mother is an alias of an old land file): ' . count($plan['adopt']));
        foreach ($plan['adopt'] as $file => $row) {
            $this->line(sprintf('   %-22s -> old %-20s prop_id %s', $file, $row['old_file'], $row['prop_id']));
        }

        $this->newLine();
        $this->info('ALLOCATE a new prop_id (genuinely new unit parcel): ' . count($plan['allocate']));
        foreach ($plan['allocate'] as $file => $row) {
            $this->line(sprintf('   %-22s unit of %s', $file, $row['mother'] ?: '?'));
        }

        if (!empty($plan['allocate'])) {
            $next = $this->peekNextPropId();
            $this->line(sprintf('   next prop_id would be %d%s', $next,
                $next >= self::JUNK_BAND_FLOOR ? '  <-- inside the poisoned 99,000,00x band' : ''));
        }

        $this->newLine();
        $this->warn('UNCLEAR - left alone: ' . count($plan['unclear']));
        foreach ($plan['unclear'] as $file => $why) {
            $this->line(sprintf('   %-22s %s', $file, $why));
        }
    }
}
