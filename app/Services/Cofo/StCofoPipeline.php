<?php

namespace App\Services\Cofo;

use App\Services\Tdp\TdpLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ST Certificate of Occupancy pipeline.
 *
 * Six stages, in the order the brief lists them:
 *
 *     RofO -> CofO Registration (Deeds) -> Front Page / White Copy
 *          -> Title Deed Plan -> Merge -> Original
 *
 * The first two are PRE-CONDITIONS: they normally happened elsewhere before a record
 * reached the certificate screens, so they are reported as state and never blocked on. The
 * workflow proper begins at the front page.
 *
 * Ordering is deliberately NOT enforced. A front page may exist while its TDP is still
 * pending and that is a normal state, not a fault — the TDP comes from another department.
 * The one real constraint is that Merge needs both sides, because the certificate has no
 * back without the TDP.
 *
 * This class exists so the Front Page screen (CofoController) and the CofO screen
 * (StCofoTdpController) show the SAME pipeline with the SAME counts. Two copies of the
 * stage list would drift the first time anyone added a stage to one of them.
 */
class StCofoPipeline
{
    public const STAGES = [
        'rofo' => [
            'label' => 'RofO',
            'owner' => 'KLAES / ST',
            'required' => false,
            'description' => 'Right of Occupancy issued for the unit — a pre-condition, not a step taken here',
        ],
        'registration' => [
            'label' => 'CofO Registration',
            'owner' => 'KLAES / Deeds',
            'required' => false,
            'description' => 'Registered in the deeds register — a pre-condition, not a step taken here',
        ],
        'front_page' => [
            'label' => 'Front Page',
            'owner' => 'KLAES / ST',
            'required' => true,
            'description' => 'The CofO front page (White Copy), generated on the Front Page screen',
        ],
        'tdp' => [
            'label' => 'Title Deed Plan',
            'owner' => 'KANGIS / GIS',
            'required' => true,
            'description' => 'The back page, produced by GIS and filed under its LGA in the TDP store',
        ],
        'merge' => [
            'label' => 'Merge',
            'owner' => 'KLAES / ST',
            'required' => true,
            'description' => 'Front page and TDP paired into one two-sided certificate',
        ],
        'original' => [
            'label' => 'Original',
            'owner' => 'KLAES / ST',
            'required' => true,
            'description' => 'The signed Original, issued once the merged certificate is approved',
        ],
    ];

    public function __construct(private TdpLibrary $library)
    {
    }

    /** @return array<string, array> */
    public function stages(): array
    {
        return self::STAGES;
    }

    /**
     * How many certificates have reached each stage, across the whole registry.
     *
     * Counts are deliberately registry-wide and never scoped to a screen's filters: a
     * number under a stage dot that moved when you searched would not be a pipeline, it
     * would be a second copy of the result count.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $empty = array_fill_keys(array_keys(self::STAGES), 0);

        if (!Schema::connection('sqlsrv')->hasTable('st_cofo')) {
            return $empty;
        }

        /*
         | The same universe the CofO queue lists: every unit with an issued RofO, plus any
         | unit that already carries a CofO record.
         |
         | Counting st_cofo alone gave 1 while the queue showed 69, so the Front Page rail
         | and the CofO rail disagreed about the same pipeline. The base has to match.
        */
        $rows = DB::connection('sqlsrv')->table('subapplications as s')
            ->leftJoin('rofo as r', function ($join) {
                $join->on('r.sub_application_id', '=', 's.id')
                    ->where('r.active', 1)
                    ->whereNotNull('r.rofo_no');
            })
            ->leftJoin('st_cofo as c', function ($join) {
                $join->on('c.file_no', '=', 's.fileno')->where('c.is_active', 1);
            })
            ->where(function ($query) {
                $query->whereNull('s.is_deleted')->orWhere('s.is_deleted', 0);
            })
            ->where(function ($query) {
                $query->whereNotNull('r.rofo_no')->orWhereNotNull('c.id');
            })
            ->select(
                's.id as unit_id',
                's.fileno as file_no',
                'r.rofo_no',
                'c.id as cofo_id',
                'c.sub_application_id',
                'c.property_lga',
                'c.RegNo',
                'c.page_no'
            )
            ->get();

        if ($rows->isEmpty()) {
            return $empty;
        }

        $counts = $empty;

        /*
         | The RofO arrives on the join, keyed on the unit's own id — the same way the RofO
         | Applications screen finds it.
         |
         | A RofO filed under the id the CERTIFICATE carries counts too. 46 of 114 rofo rows
         | are stranded that way after subapplications was renumbered, and a signed RofO is
         | a signed RofO wherever it is filed. Leaving them out put this count one behind the
         | queue's, which is the kind of quiet disagreement this class exists to prevent.
        */
        $strandedIds = $this->rofoUnitIds(
            $rows->filter(fn ($r) => trim((string) $r->rofo_no) === '')->pluck('sub_application_id')->filter()
        );

        $counts['rofo'] = $rows->filter(function ($r) use ($strandedIds) {
            return trim((string) $r->rofo_no) !== ''
                || isset($strandedIds[(int) $r->sub_application_id]);
        })->count();
        $counts['registration'] = $this->registeredCount(
            $rows->pluck('sub_application_id')->filter(),
            $rows->pluck('file_no')
        );

        /*
         | A row in st_cofo is CAPTURED data, not an issued front page.
         |
         | The front page prints "registered as No. X at Page Y in Volume Z", so without
         | registration particulars there is nothing to print — the stage cannot be
         | complete. The brief puts registration (Deeds) before the front page (ST) for
         | exactly this reason.
         |
         | Counting every st_cofo row as a front page showed stage 3 complete while stage 2
         | was still empty, which is the pipeline running backwards.
        */
        $counts['front_page'] = $rows->filter(fn ($r) => $this->hasParticulars($r))->count();

        $storeReady = $this->library->isReachable();

        foreach ($rows as $row) {
            $number = trim((string) $row->file_no);

            if ($number === '') {
                continue;
            }

            $hasTdp = false;

            if ($storeReady) {
                // The LGA is a HINT, not a filter: st_cofo.property_lga holds captured text,
                // not folder names, so scoping to it alone silently misses real plans.
                $hasTdp = ($this->library->findForFileNumber($number, $row->property_lga ?: null)
                    ?? $this->library->findForFileNumber($number)) !== null;
            }

            if (!$hasTdp && Schema::connection('sqlsrv')->hasTable('st_cofo_tdp')) {
                $hasTdp = DB::connection('sqlsrv')->table('st_cofo_tdp')
                    ->where('sub_application_id', (int) $row->sub_application_id)
                    ->where('is_active', 1)
                    ->exists();
            }

            if ($hasTdp) {
                $counts['tdp']++;

                // Merge needs BOTH sides, and the front side only exists once the
                // certificate is registered. A back page beside an unregistered front page
                // is not a certificate. Having both IS being merged: the print pairs them
                // on demand, there is no separate merged artefact yet.
                if ($this->hasParticulars($row)) {
                    $counts['merge']++;
                }
            }
        }

        // Nothing issues an Original yet; the stage is shown so the pipeline is honest
        // about what remains rather than ending at Merge.
        $counts['original'] = 0;

        return $counts;
    }

    /**
     * Does this certificate carry its registration particulars?
     *
     * `RegNo` and `page_no` are the serial/page a registered certificate is issued under.
     * Both are NULL on captured-but-unregistered rows — the live row is one — and nothing
     * in the current code ever writes them, which is itself outstanding work.
     *
     * Public so the CofO screen can ask the same question per row without a second rule.
     */
    public function hasParticulars(object $row): bool
    {
        return trim((string) ($row->RegNo ?? '')) !== ''
            || trim((string) ($row->page_no ?? '')) !== '';
    }

    /**
     * Which of these ids have an issued RofO filed against them.
     *
     * @return array<int, true>
     */
    private function rofoUnitIds($subApplicationIds): array
    {
        $ids = collect($subApplicationIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty() || !Schema::connection('sqlsrv')->hasTable('rofo')) {
            return [];
        }

        $found = [];

        foreach ($ids->chunk(500) as $chunk) {
            foreach (DB::connection('sqlsrv')->table('rofo')
                ->whereIn('sub_application_id', $chunk->all())
                ->where('active', 1)
                ->whereNotNull('rofo_no')
                ->pluck('sub_application_id') as $id) {
                $found[(int) $id] = true;
            }
        }

        return $found;
    }

    private function rofoCount($subApplicationIds): int
    {
        $ids = collect($subApplicationIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty() || !Schema::connection('sqlsrv')->hasTable('rofo')) {
            return 0;
        }

        $total = 0;

        foreach ($ids->chunk(500) as $chunk) {
            $total += DB::connection('sqlsrv')->table('rofo')
                ->whereIn('sub_application_id', $chunk->all())
                ->where('active', 1)
                ->whereNotNull('rofo_no')
                ->distinct()
                ->count('sub_application_id');
        }

        return $total;
    }

    /**
     * Registered in EITHER register.
     *
     * KLAES has two that do not know about each other: SectionalCofOReg (written by the ST
     * registration screen) and deed_registrations typed 'Sectional Titling CofO'. A
     * certificate registered through either one is registered, so both are read rather than
     * picking a winner — which register should own this is a decision for later, and
     * guessing it would under-report today.
     */
    private function registeredCount($subApplicationIds, $fileNumbers): int
    {
        $ids = collect($subApplicationIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        $schema = Schema::connection('sqlsrv');
        $registered = [];

        if ($schema->hasTable('SectionalCofOReg')) {
            foreach ($ids->chunk(500) as $chunk) {
                foreach (DB::connection('sqlsrv')->table('SectionalCofOReg')
                    ->whereIn('sub_application_id', $chunk->all())
                    ->where('status', 'registered')
                    ->pluck('sub_application_id') as $id) {
                    $registered[(int) $id] = true;
                }
            }
        }

        // The other register keys on the file number, not the unit id, so the two are
        // bridged here rather than in SQL.
        $numbers = collect($fileNumbers)->filter()
            ->map(fn ($n) => trim((string) $n))->filter()->unique()->values();

        if ($numbers->isNotEmpty() && $schema->hasTable('deed_registrations')) {
            $byNumber = [];

            foreach ($numbers->chunk(500) as $chunk) {
                foreach (DB::connection('sqlsrv')->table('deed_registrations')
                    ->whereIn('fileno', $chunk->all())
                    ->where('instrument_type', 'Sectional Titling CofO')
                    ->where('status', 'registered')
                    ->pluck('fileno') as $number) {
                    $byNumber[strtoupper(trim((string) $number))] = true;
                }
            }

            foreach (collect($subApplicationIds)->values() as $i => $id) {
                $number = strtoupper(trim((string) (collect($fileNumbers)->values()[$i] ?? '')));

                if ($number !== '' && isset($byNumber[$number])) {
                    $registered[(int) $id] = true;
                }
            }
        }

        return count($registered);
    }
}
