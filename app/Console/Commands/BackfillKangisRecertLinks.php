<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill the KANGIS recertification edges that Legal Search reads from.
 *
 * The linkage between a KANGIS file and the land file it was recertified from is captured at
 * indexing time in file_indexings.related_fileno (a JSON array). Legal Search, however, builds
 * its recertification timeline rows ONLY from the related_file_number register
 * (LegalSearchService::fetchRelatedRecertificationRows). Files indexed without a corresponding
 * register row therefore show no recertification event at all — measured at ~1,390 of 4,503
 * indexed KANGIS files (31%).
 *
 * This command reads the indexing back-links and writes the missing register edges.
 * It is idempotent: an edge already present in either direction is skipped.
 *
 * Edge typing follows the same rule the write sites use:
 *   - land file <-> MLKN/KNML/KNGP or KN…  ->  "KANGIS Recertification"
 *   - land file <-> old-MLS "KN 1234"      ->  "Ministry of Land & Physical Planning Recertification"
 *     (the spelling the register rebuild uses; note MlsFileNoController writes a shorter variant)
 */
class BackfillKangisRecertLinks extends Command
{
    protected $signature = 'kangis:backfill-recert-links
                            {--apply : Write the rows. Without this flag the command only reports.}
                            {--file= : Restrict to a single KANGIS file number.}
                            {--limit=0 : Stop after N KANGIS files (0 = no limit).}';

    protected $description = 'Create missing related_file_number recertification edges from file_indexings.related_fileno';

    /** Provenance tag written to related_file_number.source_table. See the insert below. */
    private const SOURCE_TAG = 'kangis_recert_backfill';

    /** Legacy KANGIS: MLKN/KNML/KNGP, 1-6 digits, optional unit suffix (MLKN 2280-1). */
    private const RE_LEGACY_KANGIS = '/^(MLKN|KNML|KNGP)\s?\d{1,6}([-_]\d{1,3})?$/i';

    /** New KANGIS: KN + 2-6 digits, no separator (KN2690). */
    private const RE_NEW_KANGIS = '/^KN\d{2,6}$/i';

    /** Old-MLS KN file: KN + separator + digits (KN 3232, KN-3232). These are Ministry recerts. */
    private const RE_OLD_MLS_KN = '/^KN[\s-]\d+$/i';

    public function handle(): int
    {
        $conn = DB::connection('sqlsrv');

        if (!Schema::connection('sqlsrv')->hasTable('related_file_number')) {
            $this->error('related_file_number table not found.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $limit = (int) $this->option('limit');

        // Two derivation sources, because the registry records the land<->KANGIS relationship in
        // two different shapes:
        //   (a) related_fileno JSON on the KANGIS row  — the common case;
        //   (b) parent_prop_id pointing at the land parcel, with related_fileno EMPTY — the
        //       canonical "Option A" shape used by new-KANGIS files (e.g. KN2690 -> 147163).
        // Source (b) has no file number to read, so the parent prop_id is resolved to the land
        // file number in resolveParentLandFile().
        $query = $conn->table('file_indexings')
            ->where('registry', 'KANGIS')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereRaw("LTRIM(RTRIM(ISNULL(related_fileno,''))) NOT IN ('', '[]')")
                  ->orWhereRaw("LTRIM(RTRIM(ISNULL(parent_prop_id,''))) <> ''");
            });

        if ($single = trim((string) $this->option('file'))) {
            $query->where('file_number', $single);
        }

        $rows = $query->orderBy('id')->get(['id', 'file_number', 'related_fileno', 'prop_id', 'parent_prop_id', 'file_title', 'current_holder', 'location']);

        $this->info(sprintf('%s %d KANGIS indexing row(s).', $apply ? 'Processing' : 'DRY RUN over', $rows->count()));

        $created = 0;
        $skipped = 0;
        $filesTouched = 0;
        $preview = [];

        foreach ($rows as $row) {
            $kangisNo = trim((string) $row->file_number);
            if ($kangisNo === '') {
                continue;
            }

            $edgesForFile = 0;

            $counterparts = $this->parseRelated($row->related_fileno);

            // Source (b): no land file named in related_fileno, but parent_prop_id points at one.
            if (empty(array_filter($counterparts, fn ($v) => !$this->isAnyKangis($v)))) {
                $parentLand = $this->resolveParentLandFile($conn, $row);
                if ($parentLand !== null) {
                    $counterparts[] = $parentLand;
                }
            }

            foreach ($counterparts as $counterpart) {
                // Only land <-> KANGIS pairs are recertification edges. A KANGIS-to-KANGIS
                // pair (e.g. MLKN 3673 -> KN2690, two aliases of one parcel) is an alias
                // relationship, not a recertification, and is left alone.
                if ($this->isAnyKangis($counterpart)) {
                    continue;
                }

                $type = $this->edgeType($kangisNo, $counterpart);
                if ($type === null) {
                    continue;
                }

                if ($this->edgeExists($conn, $kangisNo, $counterpart)) {
                    $skipped++;
                    continue;
                }

                $preview[] = sprintf('%-16s <-> %-18s  %s', $kangisNo, $counterpart, $type);

                if ($apply) {
                    $conn->table('related_file_number')->insert([
                        'file_number'      => $counterpart,   // land file = parent endpoint
                        'related_fileno'   => $kangisNo,      // KANGIS file = counterpart
                        'transaction_type' => $type,
                        'prop_id'          => $row->prop_id ?: null,
                        'file_title'       => $row->file_title ?: null,
                        'location'         => $row->location ?: null,
                        // No comment. The KANGIS file number is already the row's File No. column,
                        // so repeating it in Comments is redundant on screen and in the slip.
                        // (Ministry recertification rows are different — they show the old-MLS
                        // "KN …" number there by design, see makePrintMinistryRecertRow().)
                        'comment'          => null,
                        // Deliberately NOT 'file_indexings': 7,791 pre-existing rows already carry
                        // that tag, so it cannot identify what this command wrote. A dedicated tag
                        // keeps the backfill fully reversible:
                        //   DELETE FROM related_file_number WHERE source_table = 'kangis_recert_backfill'
                        'source_table'     => self::SOURCE_TAG,
                        'source_id'        => $row->id,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }

                $created++;
                $edgesForFile++;
            }

            if ($edgesForFile > 0) {
                $filesTouched++;
                if ($limit > 0 && $filesTouched >= $limit) {
                    break;
                }
            }
        }

        foreach (array_slice($preview, 0, 40) as $line) {
            $this->line('  ' . $line);
        }
        if (count($preview) > 40) {
            $this->line(sprintf('  … and %d more', count($preview) - 40));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d edge(s) across %d file(s). %d already present.',
            $apply ? 'Created' : 'Would create',
            $created,
            $filesTouched,
            $skipped
        ));

        if (!$apply && $created > 0) {
            $this->comment('Re-run with --apply to write these rows.');
        }

        return self::SUCCESS;
    }

    /** related_fileno is a JSON array, but CSV is also present in older rows. */
    private function parseRelated($raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $decoded), fn ($v) => $v !== ''));
            }
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }

    private function isAnyKangis(string $v): bool
    {
        return (bool) (preg_match(self::RE_LEGACY_KANGIS, $v) || preg_match(self::RE_NEW_KANGIS, $v));
    }

    /**
     * The register type for a (KANGIS file, counterpart) pair, or null when the pair is not a
     * recertification at all (e.g. the counterpart is not a recognisable land file number).
     */
    private function edgeType(string $kangisNo, string $counterpart): ?string
    {
        if (preg_match(self::RE_OLD_MLS_KN, $counterpart)) {
            return 'Ministry of Land & Physical Planning Recertification';
        }
        // Counterpart must look like a land file (RES-2022-1, CON-COM-2014-130, COM-RC-1982-19…).
        if (!preg_match('/^(CON-)?(RES|COM|IND|AG)(-RC)?-\d{4}-\d+$/i', $counterpart)) {
            return null;
        }
        return $this->isAnyKangis($kangisNo) ? 'KANGIS Recertification' : null;
    }

    /**
     * Resolve a KANGIS row's parent_prop_id to the land file number of the parent parcel.
     * Tried in order: PropID_Master (mlsFNo / primary_file_number), then the parent parcel's own
     * non-KANGIS file_indexings row. Returns null when the parent resolves to nothing, to itself,
     * or to another KANGIS alias — an alias must never be linked to an alias.
     */
    private function resolveParentLandFile($conn, $row): ?string
    {
        $ownPid = trim((string) ($row->prop_id ?? ''));

        foreach (array_filter(array_map('trim', explode(',', (string) ($row->parent_prop_id ?? '')))) as $parentPid) {
            if ($parentPid === '' || $parentPid === $ownPid) {
                continue;   // self-reference, not a parent
            }

            $pm = $conn->table('PropID_Master')->where('prop_id', $parentPid)
                ->first(['mlsFNo', 'primary_file_number']);

            $candidates = [$pm->mlsFNo ?? null, $pm->primary_file_number ?? null];

            $candidates[] = $conn->table('file_indexings')
                ->where('prop_id', $parentPid)
                ->whereNull('deleted_at')
                ->where('registry', '<>', 'KANGIS')
                ->value('file_number');

            foreach ($candidates as $cand) {
                $cand = trim((string) $cand);
                if ($cand !== '' && !$this->isAnyKangis($cand) && !preg_match(self::RE_OLD_MLS_KN, $cand)) {
                    return $cand;
                }
            }
        }

        return null;
    }

    /** True when an edge between these two numbers already exists in either direction. */
    private function edgeExists($conn, string $a, string $b): bool
    {
        return $conn->table('related_file_number')
            ->where(function ($q) use ($a, $b) {
                $q->where(function ($w) use ($a, $b) {
                    $w->where('file_number', $a)->where('related_fileno', $b);
                })->orWhere(function ($w) use ($a, $b) {
                    $w->where('file_number', $b)->where('related_fileno', $a);
                });
            })
            ->exists();
    }
}
