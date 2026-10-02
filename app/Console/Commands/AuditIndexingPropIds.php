<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only audit of file_indexings.prop_id as a JOIN KEY.
 *
 * Why this exists: prop_id is used across the app to find a file's relatives, but it
 * is not unique on file_indexings and nothing enforces that it should be. A bulk
 * import stamped thousands of rows with a row ordinal instead of the real property id
 * (diagnosed in database/sql/2026_08_10_fix_file_indexings_prop_id_conflicts.sql), so
 * unrelated parcels now share a prop_id and any join on it drags a stranger's file
 * into the result. That is exactly the bug the Virtual Folder System hit.
 *
 * What already exists and is NOT duplicated here:
 *   - vw_prop_id_conflicts          detects the INVERSE (one file -> several prop_ids)
 *   - propid:reconcile-indexing     repairs file_indexings.prop_id against PropID_Master
 *   - propid:backfill-ancestral     recomputes ancestral_prop_id
 *   - kangis:link-parent-propids    fills missing KANGIS parent links
 *
 * What was missing, and is what this reports:
 *   1. prop_id shared by file_indexings rows that are NOT related to each other
 *   2. parent_prop_id pointing at a prop_id no file holds
 *   3. KANGIS files with no prop_id, which is why their children cannot be linked
 *
 * THIS COMMAND NEVER WRITES. There is no --apply and nothing to roll back. It is safe
 * to run on production at any time; the only side effect is CSV files on disk.
 */
class AuditIndexingPropIds extends Command
{
    protected $signature = 'propid:audit-indexing
        {--csv= : Directory for the CSV reports. Default: storage/app/propid-remediation}
        {--limit=0 : Cap rows examined per check (0 = all).}';

    protected $description = 'Read-only audit of file_indexings.prop_id as a join key: shared ids, dangling parents, missing KANGIS ids.';

    private const CONNECTION = 'sqlsrv';

    public function handle(): int
    {
        $conn = DB::connection(self::CONNECTION);
        $dir = rtrim($this->option('csv') ?: storage_path('app/propid-remediation'), '/\\');

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->error("Cannot create report directory: {$dir}");

            return self::FAILURE;
        }

        $stamp = date('Ymd-His');
        $this->info('Read-only audit of file_indexings.prop_id. Nothing will be written to the database.');
        $this->newLine();

        try {
            $shared = $this->auditSharedPropIds($conn, $dir, $stamp);
            $dangling = $this->auditDanglingParents($conn, $dir, $stamp);
            $missing = $this->auditMissingKangisPropIds($conn, $dir, $stamp);
        } catch (Throwable $e) {
            $this->error('Audit failed: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['check', 'count', 'meaning'],
            [
                ['prop_id shared — VIOLATIONS', $shared['violations'], 'every file number must have its own prop_id'],
                ['  of which, different parcels', $shared['collisions'], 'worst kind: a join returns a stranger\'s file'],
                ['  of which, needs review', $shared['review'], 'district disagrees, or plot is a non-identifying descriptor'],
                ['  of which, undecidable', $shared['undecidable'], 'no plot recorded — cannot judge either way'],
                ['prop_id shared, ALLOWED', $shared['legitimate'], 'OP/ToT pairs and ST mothers only'],
                ['parent_prop_id -> no such prop_id', $dangling, 'lineage pointer into nothing'],
                ['KANGIS files with no prop_id', $missing, 'cannot act as the parent their own model needs'],
            ]
        );

        $this->newLine();
        $this->comment("CSV reports written to {$dir}");
        $this->comment('Next step is a DRY RUN only: php artisan propid:reconcile-indexing');
        $this->comment('Do not pass --apply until those numbers have been reviewed.');

        return self::SUCCESS;
    }

    /**
     * prop_id values held by more than one file_indexings row.
     *
     * THE RULE: every distinct file number gets its OWN prop_id. Sharing is a violation.
     * Exactly two cases are exempt, both documented in the code that creates them:
     * OP/ToT pairs, and ST mothers adopting the old land file's id.
     *
     * Being linked in the file register does NOT exempt a cluster. It explains why the
     * files share, but the relationship belongs in parent_prop_id / related_file_number,
     * not in a duplicated identity — which is exactly what KangisParentLinkService says:
     * "Each file carries its OWN prop_id."
     *
     * Violations are graded by how provable they are, because that decides how they get
     * fixed: `collision` (different plots) is the worst and the most certain;
     * `violation-same-parcel` is the easiest, since the files genuinely relate and only
     * need splitting; `review-*` and `undecidable` need a human or better data.
     */
    private function auditSharedPropIds($conn, string $dir, string $stamp): array
    {
        $this->line('1/3  prop_id shared across files...');

        $limit = (int) $this->option('limit');

        $query = $conn->table('file_indexings')
            ->select('prop_id', DB::raw('count(*) as total'))
            ->whereNotNull('prop_id')
            ->where('prop_id', '<>', '')
            ->groupBy('prop_id')
            ->havingRaw('count(*) > 1')
            ->orderByDesc(DB::raw('count(*)'));

        if ($limit > 0) {
            $query->limit($limit);
        }

        $clusters = $query->get();
        $opTot = $this->loadOpTotFileNumbers($conn);

        $rows = [];
        $verdicts = [];

        foreach ($clusters as $cluster) {
            $members = $conn->table('file_indexings')
                ->select('id', 'file_number', 'file_title', 'registry', 'related_fileno',
                         'plot_number', 'district')
                ->where('prop_id', $cluster->prop_id)
                ->get();

            $verdict = $this->classifyCluster($conn, $members, $opTot);
            $verdicts[$verdict] = ($verdicts[$verdict] ?? 0) + 1;

            foreach ($members as $member) {
                $rows[] = [
                    $cluster->prop_id,
                    (int) $cluster->total,
                    $verdict,
                    $member->id,
                    $member->file_number,
                    $member->registry,
                    $member->file_title,
                ];
            }
        }

        $this->writeCsv(
            "{$dir}/propid-shared-{$stamp}.csv",
            ['prop_id', 'files_sharing', 'verdict', 'file_indexing_id', 'file_number', 'registry', 'file_title'],
            $rows
        );

        $collisions = $verdicts['collision'] ?? 0;
        $undecidable = $verdicts['undecidable'] ?? 0;
        $review = ($verdicts['review-district-mismatch'] ?? 0) + ($verdicts['review-weak-plot'] ?? 0);
        // Only the two documented exceptions are legitimate now.
        $legitimate = ($verdicts['legitimate-optot'] ?? 0) + ($verdicts['legitimate-st'] ?? 0);
        $violations = array_sum($verdicts) - $legitimate;

        $this->line("     {$clusters->count()} shared prop_id values");
        foreach ($verdicts as $v => $n) {
            $this->line(sprintf('       %-24s %5d', $v, $n));
        }

        return [
            'collisions' => $collisions,
            'legitimate' => $legitimate,
            'undecidable' => $undecidable,
            'review' => $review,
            'violations' => $violations,
        ];
    }

    /**
     * Values in plot_number that identify nothing at all.
     *
     * "PIECE OF LAND" (40,901 files) and "A PIECE OF LAND" (2,038) are the same thing —
     * normalised together below. Treating either as a plot would make every unsurveyed
     * parcel look like the same parcel, which is exactly the wrong answer.
     */
    private const PLOT_PLACEHOLDERS = ['PIECE OF LAND', 'PLOT', 'N/A', 'NIL', '-', ''];

    /**
     * Real land descriptors that still do not identify WHICH parcel.
     *
     * FARMLAND (1,171 files, 97% of them land_use AGRICULTURAL) is NOT the same as
     * "piece of land" — it is a genuine descriptor, so it counts as a difference when
     * set against a numbered plot: `FARMLAND | 24 | 24` is three files on two different
     * parcels. But two files both saying FARMLAND are not thereby the same farm, so a
     * cluster whose only plot value is one of these cannot be passed as same-parcel.
     */
    private const WEAK_PLOT_VALUES = ['FARMLAND'];

    /**
     * A cluster is legitimate when its members are the SAME PARCEL held across several
     * files, when they are linked to each other, or when it is one of the documented
     * sharing cases. Otherwise it conflates strangers.
     *
     * The same-parcel test is what makes this number usable. One property is routinely
     * indexed three times — Old KANGIS, Land file, New KANGIS — and those three SHOULD
     * share a prop_id. Without the test, 2,005 correct clusters were reported as
     * collisions purely because the file register has no link row for them, drowning
     * the ~49 that are genuinely wrong.
     */
    private function classifyCluster($conn, $members, array $opTot): string
    {
        $numbers = [];

        foreach ($members as $member) {
            $number = strtoupper(trim((string) $member->file_number));

            if ($number === '') {
                continue;
            }

            // OP/ToT pairs share one prop_id by design.
            if (isset($opTot[$number])) {
                return 'legitimate-optot';
            }

            // ST mothers adopt the old land file's prop_id by design.
            if (str_starts_with($number, 'ST-')) {
                return 'legitimate-st';
            }

            $numbers[] = $number;
        }

        // THE RULE (confirmed with the product owner, 2026-09-27):
        // every distinct file number gets its OWN prop_id. Sharing is a violation, not
        // a design. This matches KangisParentLinkService, which states that each of the
        // three KANGIS files "carries its OWN prop_id" and expresses the relationship
        // through parent_prop_id instead.
        //
        // The ONLY exceptions are the two the code documents, both handled above:
        // OP/ToT pairs, and ST mothers adopting the old land file's id.
        //
        // An earlier version of this classifier treated "same parcel, several files" as
        // legitimate and passed 905 clusters that are in fact violations. Do not
        // reintroduce that: one parcel indexed three times is three file numbers, and
        // therefore three prop_ids.
        $plots = [];
        $districts = [];

        foreach ($members as $member) {
            $plot = strtoupper(trim((string) ($member->plot_number ?? '')));

            // "A PIECE OF LAND" and "PIECE OF LAND" are one value.
            $plot = preg_replace('/^A\s+PIECE\s+OF\s+LAND$/', 'PIECE OF LAND', $plot);

            if (!in_array($plot, self::PLOT_PLACEHOLDERS, true)) {
                $plots[$plot] = true;
            }

            // Collapse spacing/punctuation so "YAN MATA" and "YANMATA" agree. Real
            // typos ("YAMMATA") survive, which is why this only feeds the review queue.
            $district = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($member->district ?? '')));

            if ($district !== '') {
                $districts[$district] = true;
            }
        }

        if (count($plots) > 1) {
            // Different plots is decisive: one prop_id cannot mean two parcels, no
            // matter what the register says about them being linked.
            return 'collision';
        }

        // District is a WEAK signal, not a verdict. The column carries spelling
        // variants for one place ("YAN MATA" / "YANMATA" / "YAMMATA"), so treating a
        // mismatch as proof of a collision reclassified thousands of correct clusters.
        // It still matters, because a member whose plot_number is a placeholder
        // contributes no plot and can hide a real collision — that is what concealed
        // prop_id 16072, where CON-RES-1994-611 (Kurnar Qrts, plot "PIECE OF LAND")
        // shares with the Airport Road files. So a district mismatch goes to a review
        // queue rather than being counted as a confirmed collision.
        if (count($districts) > 1) {
            return 'review-district-mismatch';
        }

        if (count($plots) === 1) {
            // A lone weak descriptor cannot prove sameness — two farms are still two
            // farms — so it goes to review rather than passing as one parcel.
            if (in_array(array_key_first($plots), self::WEAK_PLOT_VALUES, true)) {
                return 'review-weak-plot';
            }

            // One parcel, several file numbers. Still a violation under the rule, but
            // kept as its own verdict because it is the EASY kind to resolve: the files
            // genuinely relate, so the fix is to split the id and link them through
            // parent_prop_id rather than to untangle a mix-up.
            return 'violation-same-parcel';
        }

        // No usable plot on any member — cannot decide either way. Reported separately
        // rather than being counted as a collision it may well not be.
        if (empty($plots)) {
            $undecidable = true;
        }

        // Linked in the register, in either direction? Then sharing is expected.
        if (count($numbers) > 1 && Schema::connection(self::CONNECTION)->hasTable('related_file_number')) {
            $linked = $conn->table('related_file_number')
                ->where(function ($q) use ($numbers) {
                    foreach ($numbers as $number) {
                        $q->orWhere(function ($inner) use ($number) {
                            $inner->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$number]);
                        })->orWhere(function ($inner) use ($number) {
                            $inner->whereRaw('UPPER(LTRIM(RTRIM(related_fileno))) = ?', [$number]);
                        });
                    }
                })
                ->exists();

            if ($linked) {
                return 'violation-linked';
            }
        }

        // Or linked through one member's own related_fileno list.
        foreach ($members as $member) {
            $json = strtoupper((string) $member->related_fileno);

            foreach ($numbers as $number) {
                if ($number !== strtoupper(trim((string) $member->file_number))
                    && str_contains($json, $number)) {
                    return 'violation-linked';
                }
            }
        }

        return !empty($undecidable) ? 'undecidable' : 'collision';
    }

    /**
     * parent_prop_id values that match no file's prop_id.
     *
     * KangisParentLinkService writes a parent id without checking the parent exists,
     * so a child indexed before its KANGIS parent keeps a pointer into nothing.
     */
    private function auditDanglingParents($conn, string $dir, string $stamp): int
    {
        $this->line('2/3  parent_prop_id pointing at nothing...');

        $limit = (int) $this->option('limit');

        $query = $conn->table('file_indexings')
            ->select('id', 'file_number', 'registry', 'prop_id', 'parent_prop_id')
            ->whereNotNull('parent_prop_id')
            ->where('parent_prop_id', '<>', '');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $known = [];
        $rows = [];
        $count = 0;

        foreach ($query->get() as $row) {
            // parent_prop_id is a comma-separated ancestor LIST, so every member is
            // checked individually — a list is dangling only where a member is.
            foreach (array_filter(array_map('trim', explode(',', (string) $row->parent_prop_id))) as $parent) {
                if (!array_key_exists($parent, $known)) {
                    $known[$parent] = $conn->table('file_indexings')
                        ->where('prop_id', $parent)
                        ->exists();
                }

                if (!$known[$parent]) {
                    $count++;
                    $rows[] = [$row->id, $row->file_number, $row->registry, $row->prop_id, $row->parent_prop_id, $parent];
                }
            }
        }

        $this->writeCsv(
            "{$dir}/propid-dangling-parents-{$stamp}.csv",
            ['file_indexing_id', 'file_number', 'registry', 'prop_id', 'parent_prop_id', 'missing_parent'],
            $rows
        );

        $this->line("     {$count} dangling parent reference(s)");

        return $count;
    }

    /**
     * KANGIS files with no prop_id.
     *
     * The KANGIS three-file model makes the Old KANGIS file the parent, and children
     * point at its prop_id. A KANGIS file with no prop_id therefore cannot be the
     * parent its own children need, and the link is silently never written.
     */
    private function auditMissingKangisPropIds($conn, string $dir, string $stamp): int
    {
        $this->line('3/3  KANGIS files with no prop_id...');

        $query = $conn->table('file_indexings')
            ->select('id', 'file_number', 'file_title', 'registry')
            ->where('registry', 'like', '%KANGIS%')
            ->where(function ($q) {
                $q->whereNull('prop_id')->orWhere('prop_id', '');
            });

        if (($limit = (int) $this->option('limit')) > 0) {
            $query->limit($limit);
        }

        $rows = [];

        foreach ($query->get() as $row) {
            $rows[] = [$row->id, $row->file_number, $row->registry, $row->file_title];
        }

        $this->writeCsv(
            "{$dir}/propid-kangis-missing-{$stamp}.csv",
            ['file_indexing_id', 'file_number', 'registry', 'file_title'],
            $rows
        );

        $this->line('     ' . count($rows) . ' KANGIS file(s) with no prop_id');

        return count($rows);
    }

    /** Mirrors ReconcileIndexingPropIds::loadOpTotFileNumbers — same rule, same source. */
    private function loadOpTotFileNumbers($conn): array
    {
        $set = [];

        try {
            $conn->table('pra')
                ->where(function ($q) {
                    $q->where('instrument_type', 'like', '%Occupancy Permit%')
                        ->orWhere('instrument_type', 'like', '%Transfer of Title%')
                        ->orWhere('instrument_type', 'like', '%(OP)%');
                })
                ->select('fileno', 'mlsFNo')
                ->orderBy('id')
                ->chunk(20000, function ($rows) use (&$set) {
                    foreach ($rows as $r) {
                        foreach ([$r->fileno, $r->mlsFNo] as $v) {
                            $v = strtoupper(trim((string) $v));

                            if ($v !== '') {
                                $set[$v] = true;
                            }
                        }
                    }
                });
        } catch (Throwable $e) {
            // Table absent — the OP/ToT whitelist simply does not apply.
        }

        return $set;
    }

    private function writeCsv(string $path, array $header, array $rows): void
    {
        $handle = @fopen($path, 'w');

        if ($handle === false) {
            $this->warn("     could not write {$path}");

            return;
        }

        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }
}
