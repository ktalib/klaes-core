<?php

namespace App\Services;

use App\Support\ConsolidatedReportFormatting;
use App\Support\PersonName;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One register per parcel-update type (Subdivision, Merger, Extension, Change of
 * Purpose), read across the three places that work is recorded:
 *
 *   - Parcel Update: the type's own *_applications table (Simple Parcel Update).
 *   - Advance (Duplex): duplex_parcel_update_stages of that type. A committed stage
 *     also writes an application row tagged "[Duplex DPX-… · stage N]" in remarks;
 *     those rows are skipped so the work is listed once, from the stage.
 *   - Legacy: manual_file_linkages (Parcel Update - Legacy), one row per linkage
 *     group, since a legacy subdivision saves one linkage row per child plot.
 *
 * Read-only. Rows with status 'hidden' are File Indexing classification ticks
 * routed by TitleStatusParcelRouter, not processed parcel updates, and stay out,
 * as they do on the Parcel Update pages.
 */
class ParcelRegisterService
{
    public const TYPES = [
        'subdivision' => [
            'label'  => 'Subdivision',
            'table'  => 'plot_subdivision_applications',
            'stage'  => 'subdivision',
            'legacy' => ['Subdivision'],
            'route'  => 'plot-subdivision.index',
            'icon'   => 'split-square-horizontal',
        ],
        'merger' => [
            'label'  => 'Merger',
            'table'  => 'plot_merger_applications',
            'stage'  => 'merger',
            'legacy' => ['Merger'],
            'route'  => 'plot-merger.index',
            'icon'   => 'combine',
        ],
        'extension' => [
            'label'  => 'Extension',
            'table'  => 'plot_extension_applications',
            'stage'  => 'extension',
            'legacy' => ['Plot Extension', 'Extension'],
            'route'  => 'plot-extension.index',
            'icon'   => 'expand',
        ],
        'change-of-purpose' => [
            'label'  => 'Change of Purpose',
            'table'  => 'change_of_purpose_applications',
            'stage'  => 'change_of_purpose',
            'legacy' => ['Change of Purpose'],
            'route'  => 'change-of-purpose.index',
            'icon'   => 'repeat',
        ],
    ];

    public const SOURCE_SPU    = 'Parcel Update';
    public const SOURCE_DUPLEX = 'Advance (Duplex)';
    public const SOURCE_LEGACY = 'Legacy';

    /** Short code and full name shown for each source: SPU, APU, LBF. */
    public const SOURCE_CODES = [
        self::SOURCE_SPU    => ['SPU', 'Simple Parcel Update'],
        self::SOURCE_DUPLEX => ['APU', 'Advance Parcel Update (Duplex)'],
        self::SOURCE_LEGACY => ['LBF', 'Legacy Backfill'],
    ];

    /**
     * file_indexings id per file number (upper-cased, trimmed), for the VFS folio link.
     * Live rows only; when a number is indexed twice the first id wins.
     */
    public function folioIds(array $fileNumbers): array
    {
        $numbers = array_values(array_unique(array_filter(array_map(
            fn ($f) => strtoupper(trim((string) $f)), $fileNumbers
        ))));
        if (! $numbers) {
            return [];
        }

        $ids = [];
        foreach (array_chunk($numbers, 500) as $chunk) {
            DB::connection('sqlsrv')->table('file_indexings')
                // Plain column match so the file_number index is used: the column is
                // case-insensitive (CI_AS) and = ignores trailing spaces, so this finds
                // what UPPER(LTRIM(RTRIM())) did without scanning every indexed file.
                ->whereIn('file_number', $chunk)
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderBy('id')
                ->get(['id', 'file_number'])
                ->each(function ($r) use (&$ids) {
                    $ids[strtoupper(trim($r->file_number))] ??= (int) $r->id;
                });
        }

        return $ids;
    }

    /** Every row of the register for $type, newest first. */
    public function rows(string $type): Collection
    {
        $cfg = self::TYPES[$type];

        return $this->applicationRows($type, $cfg)
            ->concat($this->duplexRows($cfg))
            ->concat($this->legacyRows($cfg))
            ->sortByDesc(fn ($r) => $r->sort_date)
            ->values();
    }

    /** Case-insensitive search plus source/status filters. */
    public function filter(Collection $rows, string $search, string $source, string $status): Collection
    {
        $needle = mb_strtolower(trim($search));

        return $rows->filter(function ($r) use ($needle, $source, $status) {
            if ($source !== '' && $r->source !== $source) {
                return false;
            }
            if ($status !== '' && strcasecmp($r->status, $status) !== 0) {
                return false;
            }
            if ($needle === '') {
                return true;
            }

            $hay = mb_strtolower(implode(' ', array_merge(
                [$r->reference, $r->applicant, $r->plot_no, $r->district, $r->lga, $r->location, $r->detail],
                $r->from_files,
                $r->to_files
            )));

            return str_contains($hay, $needle);
        })->values();
    }

    private function applicationRows(string $type, array $cfg): Collection
    {
        $db    = DB::connection('sqlsrv');
        $table = $cfg['table'];

        $records = $db->table($table)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'hidden'))
            ->orderByDesc('id')
            ->get()
            // Materialised from a duplex stage: listed from the stage instead. Merger
            // commissioning overwrites remarks, so its tag is lost; the duplex always
            // sets temp_file_no to the holding / duplex number (DPX-…) instead.
            ->reject(fn ($a) => str_starts_with((string) ($a->remarks ?? ''), '[Duplex ')
                || str_starts_with((string) ($a->temp_file_no ?? ''), 'DPX-'))
            ->values();

        // Merger source plots are held in plot_application_sizes, not on the row.
        $mergerSources = [];
        if ($type === 'merger' && $records->isNotEmpty()) {
            foreach ($records->pluck('id')->chunk(1000) as $ids) {
                $sizes = $db->table('plot_application_sizes')
                    ->where('application_type', 'merger')
                    ->whereIn('application_id', $ids->all())
                    ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                    ->get(['application_id', 'source_file_no']);
                foreach ($sizes as $s) {
                    if (trim((string) $s->source_file_no) !== '') {
                        $mergerSources[$s->application_id][] = trim($s->source_file_no);
                    }
                }
            }
        }

        $userNames = $this->userNames($records->pluck('captured_by'));

        return $records->map(function ($a) use ($type, $cfg, $mergerSources, $userNames) {
            $from = [];
            $to   = [];
            $detail = '';

            switch ($type) {
                case 'subdivision':
                    $from = [$a->file_no];
                    $to   = $this->commissionedFiles($a->commissioned_batches ?? null);
                    break;
                case 'merger':
                    $from = $mergerSources[$a->id] ?? [];
                    $to   = [$a->file_no];
                    break;
                case 'extension':
                    $from = [$a->file_no];
                    break;
                case 'change-of-purpose':
                    $from   = [$a->file_no];
                    $detail = trim(implode(' → ', array_filter([
                        $a->land_use ?? '',
                        $a->new_purpose ?: ($a->purpose ?? ''),
                    ])));
                    break;
            }

            return $this->row([
                'key'        => 'spu-' . $a->id,
                'source'     => self::SOURCE_SPU,
                'origin'     => $a->source_module ?? '',
                'reference'  => $type === 'merger' ? ($a->temp_file_no ?: $a->file_no) : $a->file_no,
                'from_files' => $from,
                'to_files'   => $to,
                'applicant'  => $a->applicant_name ?? '',
                'plot_no'    => $a->plot_no ?? '',
                'district'   => $a->district ?? '',
                'lga'        => $a->lga ?? '',
                'state'      => $a->state ?? '',
                'stored_location' => $a->location ?? '',
                'plots'      => $a->num_plots ?? ($a->plot_count ?? null),
                'status'     => $a->status ?? '',
                'detail'     => $detail,
                'by'         => $userNames[$a->captured_by ?? 0] ?? '',
                'date'       => $a->created_at,
                'open_url'   => route($cfg['route'], ['search' => $a->file_no]),
            ]);
        });
    }

    private function duplexRows(array $cfg): Collection
    {
        $db = DB::connection('sqlsrv');

        $stages = $db->table('duplex_parcel_update_stages as s')
            ->join('duplex_parcel_updates as d', 'd.id', '=', 's.duplex_parcel_update_id')
            ->where('s.type', $cfg['stage'])
            ->where(fn ($q) => $q->whereNull('d.is_deleted')->orWhere('d.is_deleted', 0))
            ->orderByDesc('s.id')
            ->get([
                's.id', 's.duplex_id', 's.rank', 's.status as stage_status', 's.plot_count', 's.payload',
                's.input_holding_no', 's.created_at', 's.captured_by',
                'd.status as duplex_status', 'd.applicant_name', 'd.plot_no', 'd.district', 'd.lga', 'd.state',
                'd.source_file_nos', 'd.committed_at',
            ]);

        if ($stages->isEmpty()) {
            return collect();
        }

        $files = collect();
        foreach ($stages->pluck('id')->chunk(1000) as $ids) {
            $files = $files->concat($db->table('duplex_parcel_update_files')
                ->whereIn('duplex_parcel_update_stage_id', $ids->all())
                ->whereIn('role', ['source', 'result'])
                ->orderBy('sequence')
                ->get(['duplex_parcel_update_stage_id', 'role', 'source_file_no', 'final_file_no']));
        }
        $files = $files->groupBy('duplex_parcel_update_stage_id');

        $userNames = $this->userNames($stages->pluck('captured_by'));

        return $stages->map(function ($s) use ($cfg, $files, $userNames) {
            $payload = json_decode((string) $s->payload, true) ?: [];
            $mine    = $files->get($s->id, collect());

            $from = array_values(array_filter(array_merge(
                (array) ($payload['sources'] ?? []),
                $mine->where('role', 'source')->pluck('source_file_no')->all()
            )));
            if ($from === [] && $s->input_holding_no) {
                $from = [$s->input_holding_no];
            }
            $to = $mine->where('role', 'result')->pluck('final_file_no')->filter()->values()->all();

            $detail = '';
            if ($cfg['stage'] === 'change_of_purpose') {
                $detail = trim(implode(' → ', array_filter([
                    $payload['current_land_use'] ?? '',
                    $payload['new_purpose'] ?? ($payload['new_land_use'] ?? ''),
                ])));
            }

            return $this->row([
                'key'        => 'dpx-' . $s->id,
                'source'     => self::SOURCE_DUPLEX,
                'origin'     => 'Stage ' . $s->rank,
                'reference'  => $s->duplex_id,
                'from_files' => array_values(array_unique($from)),
                'to_files'   => array_values(array_unique($to)),
                'applicant'  => $s->applicant_name ?? '',
                'plot_no'    => $s->plot_no ?? '',
                'district'   => $s->district ?? '',
                'lga'        => $s->lga ?? '',
                'state'      => $s->state ?? '',
                'plots'      => $s->plot_count,
                'status'     => str_replace('_', ' ', (string) $s->duplex_status),
                'detail'     => $detail,
                'by'         => $userNames[$s->captured_by ?? 0] ?? '',
                'date'       => $s->created_at,
                'open_url'   => route('duplex-parcel-update.index', ['search' => $s->duplex_id]),
            ]);
        });
    }

    private function legacyRows(array $cfg): Collection
    {
        $links = DB::connection('sqlsrv')->table('manual_file_linkages')
            ->whereIn('workflow_type', $cfg['legacy'])
            ->orderBy('id')
            ->get();

        if ($links->isEmpty()) {
            return collect();
        }

        $groups = $links->groupBy(fn ($l) => $l->linkage_group_id ?: ('id-' . $l->id));

        // Property details come from the indexing of the processed (new) file.
        $firstNew = $groups->map(fn ($g) => trim((string) $g->first()->new_file_number))->filter()->unique()->values();
        $indexing = collect();
        foreach ($firstNew->chunk(1000) as $chunk) {
            $indexing = $indexing->concat(DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $chunk->all())
                ->orderBy('id')
                ->get(['file_number', 'plot_number', 'district', 'lga', 'location']));
        }
        $indexing = $indexing->keyBy('file_number');

        return $groups->map(function (Collection $g, $groupId) use ($indexing) {
            $first = $g->first();
            $from  = [];
            foreach ($g as $l) {
                foreach ((array) json_decode((string) $l->old_file_numbers, true) as $f) {
                    if (trim((string) $f) !== '') {
                        $from[] = trim($f);
                    }
                }
            }
            $to  = $g->pluck('new_file_number')->map(fn ($f) => trim((string) $f))->filter()->unique()->values()->all();
            $idx = $indexing->get(trim((string) $first->new_file_number));

            return $this->row([
                'key'        => 'lgc-' . $first->id,
                'source'     => self::SOURCE_LEGACY,
                'origin'     => $first->source ?? '',
                'reference'  => $first->holding_file_no ?: ($first->approval_reference ?: ('Linkage #' . $first->id)),
                'from_files' => array_values(array_unique($from)),
                'to_files'   => $to,
                'applicant'  => $first->applicant_name ?? '',
                'plot_no'    => $g->count() === 1 ? ($first->child_plot_number ?: ($idx->plot_number ?? '')) : ($idx->plot_number ?? ''),
                'district'   => $idx->district ?? '',
                'lga'        => $idx->lga ?? '',
                'stored_location' => $idx->location ?? '',
                'plots'      => $g->count(),
                'status'     => 'linked',
                'detail'     => trim((string) $first->remarks),
                'by'         => $first->processed_by ?? '',
                'date'       => $first->created_at,
                'open_url'   => route('admin.manual-linkage.index'),
            ]);
        })->values();
    }

    private function row(array $r): object
    {
        $date = $r['date'] ? Carbon::parse($r['date']) : null;

        // Some duplex rows hold district / LGA as a JSON list (one per plot).
        foreach (['district', 'lga', 'state'] as $field) {
            $value = trim((string) ($r[$field] ?? ''));
            if (str_starts_with($value, '[') && is_array($list = json_decode($value, true))) {
                $value = implode(', ', array_unique(array_filter(array_map('trim', array_map('strval', $list)))));
            }
            $r[$field] = $value;
        }

        $r['from_files'] = array_values(array_filter(array_map('strval', $r['from_files'])));
        $r['to_files']   = array_values(array_filter(array_map('strval', $r['to_files'])));
        $r['applicant']  = PersonName::display($r['applicant']);
        $r['location']   = ConsolidatedReportFormatting::location(
            $r['district'], $r['lga'], $r['state'] ?? '', $r['stored_location'] ?? '', $r['plot_no']
        );
        $r['status']     = ucwords(strtolower(trim((string) $r['status'])));
        $r['date']       = $date;
        $r['sort_date']  = $date ? $date->timestamp : 0;
        unset($r['stored_location'], $r['state']);

        return (object) $r;
    }

    private function commissionedFiles($batches): array
    {
        $files = [];
        foreach ((array) json_decode((string) $batches, true) as $batch) {
            foreach ((array) ($batch['files'] ?? []) as $f) {
                if ($f !== null && $f !== '') {
                    $files[] = $f;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /** id => "First Last" for the given user ids. */
    private function userNames(Collection $ids): array
    {
        $ids = $ids->filter(fn ($id) => is_numeric($id))->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::connection('sqlsrv')->table('users')
            ->whereIn('id', $ids->all())
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($u) => [$u->id => trim(ucfirst((string) $u->first_name) . ' ' . ucfirst((string) $u->last_name))])
            ->all();
    }
}
