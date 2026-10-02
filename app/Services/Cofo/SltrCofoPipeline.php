<?php

namespace App\Services\Cofo;

use App\Services\Tdp\TdpLibrary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The SLTR Certificate of Occupancy pipeline — the ST workflow, for SLTR.
 *
 *     RofO -> CofO Registration (Deeds) -> Front Page / White Copy
 *          -> Title Deed Plan -> Merge -> Original
 *
 * Same six stages as StCofoPipeline, with the same rules: the first two are pre-conditions
 * reported as state, ordering is not enforced, and Merge needs both sides.
 *
 * There is NO certificate table of its own. An SLTR CofO is registered through Deeds
 * instrument capture, so the certificate already exists as an `instrument_capture` row
 * (instrument_type 'SLTR Certificate of Occupancy') with its `deed_registrations` row —
 * holder, plot, district, LGA, land use and registration particulars are all there. The front
 * page reads that record; generating it only fills the four columns capture leaves empty
 * (party_2_address, duration, start_date, cofo_date). `cofo_date` set is what "front page
 * generated" means.
 *
 * The queue is keyed on the FILE NUMBER, because its two sources share no file numbers today:
 * sltr_recommendations with a generated RofO, and the registered captures.
 */
class SltrCofoPipeline
{
    public const INSTRUMENT_TYPE = 'SLTR Certificate of Occupancy';

    public const DEFAULT_SIGNED_TITLE = 'Honorable Commissioner of Land and Physical Planning';

    public const STAGES = [
        'rofo' => [
            'label' => 'RofO',
            'owner' => 'KLAES / SLTR',
            'required' => false,
            'description' => 'Right of Occupancy issued from the SLTR recommendation — a pre-condition, not a step taken here',
        ],
        'registration' => [
            'label' => 'CofO Registration',
            'owner' => 'KLAES / Deeds',
            'required' => false,
            'description' => 'Captured and registered in Deeds as an SLTR Certificate of Occupancy — a pre-condition, not a step taken here',
        ],
        'front_page' => [
            'label' => 'Front Page',
            'owner' => 'KLAES / SLTR',
            'required' => true,
            'description' => 'The CofO front page (White Copy), generated on the SLTR Front Page screen',
        ],
        'tdp' => [
            'label' => 'Title Deed Plan',
            'owner' => 'KANGIS / GIS',
            'required' => true,
            'description' => 'The back page, produced by GIS and filed under its LGA in the TDP store',
        ],
        'merge' => [
            'label' => 'Merge',
            'owner' => 'KLAES / SLTR',
            'required' => true,
            'description' => 'Front page and TDP paired into one two-sided certificate',
        ],
        'original' => [
            'label' => 'Original',
            'owner' => 'KLAES / SLTR',
            'required' => true,
            'description' => 'The signed Original, issued once the merged certificate is approved',
        ],
    ];

    /**
     * The step BEFORE the certificate pipeline, shown only where the whole SLTR journey is
     * displayed (the Recommendations screen and its form). Kept out of STAGES so the CofO screens' rail and
     * stage counts are unchanged.
     */
    public const RECOMMENDATION_STAGE = [
        'recommendation' => [
            'label' => 'Recommendation',
            'owner' => 'KLAES / SLTR',
            'required' => false,
            'description' => 'SLTR recommendation captured and approved — leads to the RofO',
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

    /** The comparison key for a file number: trimmed and upper-cased. */
    public static function key(?string $fileNumber): string
    {
        return strtoupper(trim((string) $fileNumber));
    }

    /** @return array<string, int> */
    public function counts(?Collection $rows = null): array
    {
        $rows ??= $this->rows();

        $counts = [];
        foreach (array_keys(self::STAGES) as $key) {
            $counts[$key] = $rows->filter(fn ($r) => $r->stages_done[$key] ?? false)->count();
        }

        return $counts;
    }

    /**
     * Every file in the SLTR CofO workflow, with its sources, TDP and stage state.
     *
     * In the workflow means: an SLTR RofO was generated, OR the file is registered as an SLTR
     * CofO through instrument capture.
     */
    public function rows(): Collection
    {
        $connection = DB::connection('sqlsrv');

        $recommendations = $connection->table('sltr_recommendations')
            ->whereNull('deleted_at')
            ->where('rofo_status', 'generated')
            ->orderBy('id')
            ->get()
            ->keyBy(fn ($r) => self::key($r->sltr_number));

        $deeds = $connection->table('deed_registrations')
            ->where('instrument_type', self::INSTRUMENT_TYPE)
            ->where('status', 'registered')
            ->orderBy('id')
            ->get()
            ->keyBy(fn ($r) => self::key($r->fileno));

        $captures = $this->captures($deeds->pluck('instrument_capture_id'));

        $keys = $recommendations->keys()
            ->merge($deeds->keys())
            ->filter()
            ->unique()
            ->values();

        $indexed = $this->indexedFiles($keys);
        $uploads = $this->uploadedTdps($keys);
        $storeReady = $this->library->isReachable();

        return $keys->map(function (string $key) use ($recommendations, $deeds, $captures, $indexed, $uploads, $storeReady) {
            $rec = $recommendations->get($key);
            $deed = $deeds->get($key);
            $capture = $deed ? ($captures[(int) $deed->instrument_capture_id] ?? null) : null;
            $index = $indexed[$key] ?? null;

            // Registered values first: once Deeds has captured the certificate, that record is
            // what the front page prints.
            $row = (object) [
                'file_no' => $deed->fileno ?? $rec->sltr_number ?? $key,
                'recommendation' => $rec,
                'deed' => $deed,
                'capture' => $capture,
                'capture_id' => $capture->id ?? null,
                'registration_number' => $this->first($deed->registration_number ?? null, $capture->registration_number ?? null),
                'holder_name' => $this->first($capture->party_2_name ?? null, $deed->grantee ?? null, $rec->applicant_name ?? null, $index->file_title ?? null),
                'plot_no' => $this->first($capture->plot_number ?? null, $rec->plot_number ?? null, $index->plot_number ?? null),
                'district' => $this->first($capture->district ?? null, $deed->district ?? null, $index->district ?? null),
                'lga' => $this->first($capture->lga ?? null, $deed->lga ?? null, $rec->lga ?? null, $index->lga ?? null),
                'index' => $index,
            ];

            $this->attachTdp($row, $uploads[$key] ?? null, $storeReady);

            return $this->decorateStages($row);
        })->sortByDesc(fn ($r) => ($r->stages_done['front_page'] ? 2 : 0) + ($r->deed ? 1 : 0))->values();
    }

    /** One row by file number, or null when the file is not in the workflow. */
    public function row(string $fileNumber): ?object
    {
        $key = self::key($fileNumber);

        return $this->rows()->first(fn ($r) => self::key($r->file_no) === $key);
    }

    /** One row by its instrument_capture id, or null. */
    public function rowForCapture(int $captureId): ?object
    {
        return $captureId > 0
            ? $this->rows()->first(fn ($r) => (int) $r->capture_id === $captureId)
            : null;
    }

    /**
     * The front page form's values.
     *
     * The registered facts (holder, plot, location, land use, particulars) come from the
     * capture and are not editable here — Deeds owns them. The four fields capture leaves
     * empty fall back to the recommendation and the file index until they are saved.
     */
    public function prefill(object $row): array
    {
        $capture = $row->capture;
        $rec = $row->recommendation;
        $index = $row->index;
        $deed = $row->deed;

        $term = $this->first($capture->duration ?? null, $rec->term ?? null, $index->term ?? null);

        return [
            'file_no' => $row->file_no,
            'holder_name' => $row->holder_name,
            'land_use' => $this->first($capture->land_use ?? null, $rec->land_use ?? null, $index->land_use_type ?? null),
            'plot_no' => $row->plot_no,
            'property_district' => $row->district,
            'property_lga' => $row->lga,
            'property_description' => $capture->property_description ?? null,
            'registration_number' => $row->registration_number,
            'volume_no' => $this->first($deed->volume_no ?? null, $capture->volume_no ?? null),
            'page_no' => $this->first($deed->page_no ?? null, $capture->page_no ?? null),
            'reg_date' => $this->first($deed->deeds_date ?? null, $capture->reg_date ?? null),
            // Editable — written back to instrument_capture.
            'holder_address' => $this->first($capture->party_2_address ?? null, $rec->applicant_address ?? null, $index->residence_address ?? null),
            'total_term' => is_numeric($term) ? (int) $term : 40,
            'start_date' => !empty($capture->start_date) ? substr((string) $capture->start_date, 0, 10) : now()->format('Y-m-d'),
            'cofo_date' => !empty($capture->cofo_date) ? substr((string) $capture->cofo_date, 0, 10) : null,
        ];
    }

    /**
     * The object the SLTR front page partial prints, assembled from the capture.
     */
    public function certificate(object $row): object
    {
        $values = $this->prefill($row);

        return (object) [
            'file_no' => $row->file_no,
            'certificate_number' => $row->registration_number,
            'holder_name' => $values['holder_name'],
            'holder_address' => $values['holder_address'],
            'land_use' => $values['land_use'],
            'plot_no' => $values['plot_no'],
            'total_term' => $values['total_term'],
            'start_date' => $values['start_date'],
            'issued_date' => $values['cofo_date'],
            'property_lga' => $values['property_lga'],
            'signed_by' => null,
            'signed_title' => self::DEFAULT_SIGNED_TITLE,
            // SLTR has no holder photograph; the partial falls back to its placeholder.
            'passport' => null,
            'multiple_owners_passport' => null,
        ];
    }

    /*
    |---------------------------------------------------------------------------
    | Internals
    |---------------------------------------------------------------------------
    */

    private function decorateStages(object $row): object
    {
        $registered = $row->deed !== null && $row->capture !== null;

        $done = [
            'rofo' => $row->recommendation !== null,
            'registration' => $registered,
            'front_page' => $registered && !empty($row->capture->cofo_date),
            'tdp' => $row->has_tdp,
        ];
        $done['merge'] = $done['front_page'] && $done['tdp'];
        $done['original'] = false;

        $row->stages_done = $done;
        $row->stages_complete = count(array_filter($done));

        $current = 'original';
        foreach (['registration', 'front_page', 'tdp', 'merge', 'original'] as $stage) {
            if (!$done[$stage]) {
                $current = $stage;
                break;
            }
        }
        $row->current_stage = $current;

        $row->fsm_status = match (true) {
            !$done['registration'] => 'PENDING_REGISTRATION',
            // Registered, front page not generated yet — the next move is SLTR's own.
            !$done['front_page'] => 'PENDING_FRONT_PAGE',
            !$done['tdp'] => 'PENDING_TDP',
            !$done['original'] => 'GENERATING_DOCUMENTS',
            default => 'COMPLETED',
        };

        return $row;
    }

    /**
     * The live SLTR CofO captures behind the given ids.
     *
     * @return array<int, object>
     */
    private function captures(Collection $ids): array
    {
        $ids = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $out = [];

        foreach ($ids->chunk(500) as $chunk) {
            foreach (DB::connection('sqlsrv')->table('instrument_capture')
                ->whereIn('id', $chunk->all())
                ->where('instrument_type', self::INSTRUMENT_TYPE)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', '0');
                })
                ->get() as $capture) {
                $out[(int) $capture->id] = $capture;
            }
        }

        return $out;
    }

    /**
     * Back page: the GIS store first, an upload second — as ST.
     * The LGA is a hint, never a filter: captured LGA text is not always a folder name.
     */
    private function attachTdp(object $row, ?object $upload, bool $storeReady): void
    {
        $row->tdp_source = null;
        $row->tdp_plan = null;
        $row->tdp = $upload;

        $number = trim((string) $row->file_no);

        if ($storeReady && $number !== '') {
            $plan = $this->library->findForFileNumber($number, $row->lga ?: null)
                ?? $this->library->findForFileNumber($number);

            if ($plan !== null) {
                $row->tdp_plan = $plan;
                $row->tdp_source = 'gis';
            }
        }

        if ($row->tdp_source === null && $upload !== null) {
            $row->tdp_source = 'upload';
        }

        $row->has_tdp = $row->tdp_source !== null;
    }

    /** @return array<string, object> key => active uploaded TDP */
    private function uploadedTdps(Collection $keys): array
    {
        if ($keys->isEmpty() || !Schema::connection('sqlsrv')->hasTable('sltr_cofo_tdp')) {
            return [];
        }

        $out = [];

        foreach (DB::connection('sqlsrv')->table('sltr_cofo_tdp')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get() as $tdp) {
            $out[self::key($tdp->file_no)] = $tdp;
        }

        return $out;
    }

    /**
     * The file-index row for each file number — the fallback for address, plot and location.
     * Several rows can share a number, so the first non-deleted one wins.
     *
     * @return array<string, object>
     */
    private function indexedFiles(Collection $keys): array
    {
        if ($keys->isEmpty()) {
            return [];
        }

        $out = [];

        foreach ($keys->chunk(500) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $chunk->all())
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderBy('id')
                ->get(['file_number', 'file_title', 'land_use_type', 'plot_number', 'district', 'lga', 'term', 'residence_address']);

            foreach ($rows as $row) {
                $out[self::key($row->file_number)] ??= $row;
            }
        }

        return $out;
    }

    /** The first non-blank value. */
    private function first(...$values)
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return is_string($value) ? trim($value) : $value;
            }
        }

        return null;
    }
}
