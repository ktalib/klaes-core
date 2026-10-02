<?php

namespace App\Services;

use App\Models\DuplexParcelUpdate;
use App\Models\DuplexParcelUpdateFile;
use App\Support\FileNumberLandUse;
use App\Support\PersonName;
use Illuminate\Support\Facades\DB;

/**
 * Everything that happened to a duplex, in one payload.
 *
 * A duplex spreads its effects across the registry — several stages, a handful of
 * new file numbers, a handful retired, PRA rows, lineage links — and none of that
 * is visible from the register row. This assembles the whole account so the
 * summary card can show it the way the File Indexing and MLS commissioning cards
 * show theirs.
 *
 * Read-only: it reports, it never writes.
 */
class DuplexSummaryService
{
    public function build(DuplexParcelUpdate $duplex): array
    {
        $duplex->loadMissing(['stageRows.files', 'files']);

        $derived = $this->derive($duplex);

        $stages = $duplex->stageRows
            ->map(fn ($stage) => $this->stage($stage, $derived[$stage->id] ?? []))
            ->values()
            ->all();

        // What is ACTIVE at the end is what leaves the LAST stage — not every number
        // the duplex ever issued. A subdivision child that a later Change of Purpose
        // renamed was issued, then retired; counting it as active would overstate the
        // parcel count every time.
        $commissioned = collect($stages)->last()['produced'] ?? [];
        $retired      = $this->retired($duplex);

        // Before commissioning there are no file numbers to report, so the sheet would
        // simply drop that section — the one an officer opens it to read. Report the
        // holding numbers that WILL become files instead.
        //
        // Every stage's NEW numbers, not the last stage's files. Reading only the last
        // stage undercounts any duplex that issues numbers earlier in the chain and
        // carries files through afterwards: a plan of CoP -> Merger -> Subdivision(39)
        // -> CoP(3) issues 44 numbers but ends with 39 files, and the sheet said 39
        // under a caption promising what would be issued.
        $planned = collect($stages)->flatMap(fn ($s) => $s['new_holdings'])->all();

        return [
            'duplex' => [
                'duplex_id'  => $duplex->duplex_id,
                // Title-cased for the sheet, which is read on screen. The printed memo
                // and conveyance take the raw value through Str::upper(), so paper stays
                // in the Ministry's capitals — see App\Support\PersonName.
                'applicant'  => PersonName::display($duplex->applicant_name),
                'file_title' => PersonName::display($duplex->file_title),
                'location'   => $duplex->address,
                'plot_no'    => $duplex->plot_no,
                // The commissioning modal seeds each file's Location Details from these.
                // It has always read duplex.lga, which this block did not carry - so the
                // LGA silently never seeded, and district was not offered at all.
                'lga'        => $duplex->lga,
                'district'   => $duplex->district,
                'land_use'   => $duplex->land_use,
                'status'     => $duplex->status,
                'captured'   => optional($duplex->created_at)->format('d M Y'),
                'committed'  => optional($duplex->committed_at)->format('d M Y H:i'),
                'knupda'     => $duplex->knupda_status,
            ],
            'sources'      => array_values((array) ($duplex->source_file_nos ?? [])),
            'stages'       => $stages,
            // The plot number each ACTIVE file actually carries, read back from the
            // registry. The duplex's own plot_no is what was captured up front, so a
            // duplex ending in an extension reported "C & B" while the file it issued
            // reads "C & B & EXTENSION" - the sheet named a plot no file has.
            'plot_numbers' => $this->plotNumbers($commissioned),
            'commissioned' => array_values(array_unique($commissioned)),
            // EVERY number this duplex issued, in the order the stages issued them —
            // not just the ones still active at the end. `commissioned` answers "what
            // does this parcel hold now"; the sheet's "File Numbers Generated" panel
            // asks a different question, and answering it with `commissioned` under-
            // reported a merger-then-extension duplex as one file when it minted two.
            'issued'       => collect($stages)
                ->flatMap(fn ($s) => $s['new_numbers'])
                ->unique()
                ->values()
                ->all(),
            'planned'      => array_values(array_unique($planned)),
            'retired'      => $retired,
            'totals'       => [
                'stages'       => count($stages),
                // Numbers this duplex issued: everything a stage produced that is not
                // simply a file carried through unchanged.
                'issued'       => collect($stages)->flatMap(fn ($s) => $s['new_numbers'])->unique()->count(),
                'commissioned' => count(array_unique($commissioned)),
                'retired'      => count($retired),
            ],
        ];
    }

    /** One stage: what it consumed, what it renumbered, what it left alone. */
    /**
     * What the stage card prints after the arrow: "Change of Purpose -> RES".
     *
     * Each file may change to its own purpose, so a stage no longer has one answer.
     * Where they all agree the card says so; where they differ it lists them rather
     * than picking one file's purpose and presenting it as the stage's.
     */
    protected function newLandUseLabel($stage): ?string
    {
        $rows = (array) data_get($stage->payload, 'cop_rows', []);

        if (empty($rows)) {
            return data_get($stage->payload, 'new_land_use');
        }

        $uses = array_values(array_unique(array_filter(array_map(
            fn ($r) => strtoupper(trim((string) ($r['new_land_use'] ?? ''))),
            $rows
        ))));

        if (empty($uses)) {
            return null;
        }

        return count($uses) === 1 ? $uses[0] : implode(' / ', $uses);
    }

    /**
     * What the commissioning engine will do with each stage, worked out the way
     * DuplexCommitService works it out: stage by stage, each one reading the land use
     * of the files the previous stage handed it.
     *
     * Before the Land step there are no real file numbers to read, so what travels
     * between the stages is the land-use TOKEN each file will carry ("CON-RES"). That
     * is all codeFor()/prefixFor() need, and it is the very string the engine is passed
     * as `land_use` — so the preview and the commit cannot disagree about which series
     * a stage draws from. Reading the DUPLEX's land use instead, as the commissioning
     * modal used to, answers for the first source file and no other: on a plan that
     * changes purpose at all, every number it previews is from the wrong series.
     *
     * Keyed by stage id. Every value is derived, never captured — the whole point is
     * that the officer is not asked for any of it at commissioning.
     */
    protected function derive(DuplexParcelUpdate $duplex): array
    {
        // Stage 1 reads the real source files; after that, tokens.
        $carry = array_values(array_filter((array) ($duplex->source_file_nos ?? [])));

        // Position in the flat per-file array the commissioning modal collects and
        // DuplexCommitService walks with its cursor. A carried file takes no slot in
        // it, on both sides, so this is the index of the card the officer types into.
        $entry = 0;
        $out   = [];

        foreach ($duplex->stageRows as $stage) {
            $rows     = $stage->files->sortBy('sequence')->values();
            $incoming = $this->incomingFor($duplex, $stage);

            if ($stage->type === 'change_of_purpose') {
                $next  = [];
                $files = [];

                foreach ($rows as $i => $row) {
                    $token    = (string) ($carry[$i] ?? ($carry[0] ?? ''));
                    $changing = $row->role !== DuplexParcelUpdateFile::ROLE_CARRIED;

                    if (!$changing) {
                        // It keeps its number, so it keeps its series and takes no serial.
                        $next[]  = $token;
                        $files[] = ['land_use' => $token, 'changing' => false, 'entry_index' => null];
                        continue;
                    }

                    // The container prefix belongs to the parcel, not to the purpose:
                    // CON-COM-2003-118 changed to RES becomes CON-RES-…, never RES-….
                    $prefix = FileNumberLandUse::prefixFor($token);
                    $use    = $this->newUseFor($stage, $incoming[$i] ?? null);
                    $target = $use === '' ? $token : ($prefix !== '' ? $prefix . '-' . $use : $use);

                    $next[]  = $target;
                    $files[] = ['land_use' => $target, 'changing' => true, 'entry_index' => $entry++];
                }

                $uses = array_values(array_unique(array_column(
                    array_filter($files, fn ($f) => $f['changing']),
                    'land_use'
                )));

                $out[$stage->id] = [
                    'land_use'         => implode(' / ', $uses),
                    'land_uses'        => $uses,
                    'application_type' => 'change_of_purpose',
                    // A Change of Purpose renames one file at a time whatever the count,
                    // so it runs the single-file path and the card must not read "batch".
                    'file_option'      => 'normal',
                    'parent_files'     => $incoming,
                    'incoming'         => $incoming,
                    'consumes_serial'  => true,
                    'files'            => $files,
                ];

                $carry = $next;
                continue;
            }

            // Every other type consumes its whole input and produces its own children,
            // so one land use answers for the stage — the first input's, which is what
            // DuplexCommitService::landUseFor() reads.
            $token  = (string) ($carry[0] ?? $duplex->land_use);
            $code   = FileNumberLandUse::codeFor($token);
            $prefix = FileNumberLandUse::prefixFor($token);

            $target = $code === ''
                ? strtoupper((string) $duplex->land_use)
                : ($prefix !== '' ? $prefix . '-' . $code : $code);

            $files = [];
            foreach ($rows as $row) {
                $files[] = ['land_use' => $target, 'changing' => true, 'entry_index' => $entry++];
            }

            $out[$stage->id] = [
                'land_use'         => $target,
                'land_uses'        => [$target],
                'application_type' => $stage->type,
                'file_option'      => $stage->type,
                'parent_files'     => $incoming,
                'incoming'         => $incoming,
                // An extension renumbers the file it receives — "<incoming> AND
                // EXTENSION" — and takes nothing out of the series.
                'consumes_serial'  => $stage->type !== 'extension',
                'files'            => $files,
            ];

            $carry = array_fill(0, max(1, $rows->count()), $target);
        }

        return $out;
    }

    /**
     * The files a stage receives: the duplex's own sources at rank 1, the holding
     * numbers the previous stage minted after that.
     */
    protected function incomingFor(DuplexParcelUpdate $duplex, $stage): array
    {
        foreach ([
            (array) data_get($stage->payload, 'input_holdings', []),
            (array) data_get($stage->payload, 'sources', []),
            (array) ($duplex->source_file_nos ?? []),
        ] as $candidate) {
            $list = array_values(array_filter($candidate));

            if (!empty($list)) {
                return $list;
            }
        }

        return [];
    }

    /**
     * The purpose one incoming file is changing to.
     *
     * Matched by the name the row was captured under first — a row may name a source
     * file or a holding number — then by the stage's single answer where every row
     * agrees, and finally by the legacy one-per-stage `new_land_use`.
     */
    protected function newUseFor($stage, ?string $incoming): string
    {
        $wanted = strtoupper(trim((string) $incoming));
        $uses   = [];

        foreach ($stage->copRows() as $row) {
            $use = strtoupper(trim((string) ($row['new_land_use'] ?? '')));

            if ($use === '') {
                continue;
            }

            $named = strtoupper(trim((string) ($row['file_no'] ?? '')));

            if ($named !== '' && $wanted !== '' && $named === $wanted) {
                return $use;
            }

            $uses[$use] = true;
        }

        if (count($uses) === 1) {
            return (string) array_key_first($uses);
        }

        return strtoupper(trim((string) data_get($stage->payload, 'new_land_use', '')));
    }


    protected function stage($stage, array $derived = []): array
    {
        $rows = $stage->files->sortBy('sequence')->values();

        $new = $rows->where('role', '!=', DuplexParcelUpdateFile::ROLE_CARRIED);
        $kept = $rows->where('role', DuplexParcelUpdateFile::ROLE_CARRIED);

        $incoming = array_values((array) ($derived['incoming'] ?? []));

        // Where this stage's slice of the modal's per-file entries begins. Read from
        // the first file that actually takes one, so a stage whose first parcels are
        // carried through still points at its own cards.
        // The one series this stage draws from, or null where its files go to more
        // than one — a Change of Purpose may send each file to its own purpose.
        $uses   = array_values((array) ($derived['land_uses'] ?? []));
        $single = count($uses) === 1 ? $uses[0] : null;

        $entryStart = null;
        foreach ((array) ($derived['files'] ?? []) as $f) {
            if (($f['entry_index'] ?? null) !== null) {
                $entryStart = $f['entry_index'];
                break;
            }
        }

        return [
            // Cast: sqlsrv hands integers back as strings, and a strict comparison on
            // rank (=== 1, "is this the first stage?") then silently never matches.
            // The commissioning card addresses a stage by id when Land corrects which
            // parcels a Change of Purpose renames.
            'id'          => $stage->id,
            'rank'        => (int) $stage->rank,
            'type'        => $stage->type,
            'label'       => $stage->label(),
            'status'      => $stage->status,
            'tracking_id' => $stage->tracking_id,
            'new_land_use' => $this->newLandUseLabel($stage),
            'input'       => array_values(array_filter((array) data_get($stage->payload, 'input_holdings', []))),
            'sources'     => array_values(array_filter((array) data_get($stage->payload, 'sources', []))),

            // Holding -> final, per file, which is the line officers actually read.
            // Plot numbers live on the stage payload rather than the file row, so they
            // are matched back by position here.
            //
            // Each row also carries what the commissioning card needs to render itself
            // without deciding anything: the series this file will be numbered in, its
            // position among the stage's incoming parcels, and which per-file entry on
            // the modal belongs to it.
            'files' => $rows->values()->map(fn ($r, $i) => [
                'holding'  => $r->holding_no,
                'final'    => $r->final_file_no,
                'carried'  => $r->role === DuplexParcelUpdateFile::ROLE_CARRIED,
                'plot_no'  => data_get($stage->payload, "plots.{$i}.plot_no"),
                'size'     => $r->plot_size ?? data_get($stage->payload, "plots.{$i}.size"),
                'holder'   => $r->holder_name ?? data_get($stage->payload, "plots.{$i}.holder"),
                'land_use' => $derived['files'][$i]['land_use'] ?? null,
                // 1-based, so the card can say "Plot 7 of 82" rather than naming a
                // holding number no officer can place.
                'plot_index'  => $i + 1,
                // Where this file's applicant details sit in the flat per-file array the
                // modal collects — null for a carried file, which is not commissioned.
                'entry_index' => $derived['files'][$i]['entry_index'] ?? null,
                'incoming'    => $incoming[$i] ?? null,
            ])->all(),

            // What the engine will be told, derived from the plan rather than chosen at
            // the counter. The card shows these locked: changing one here could only
            // break the lineage the stages already settled.
            'target_land_use'  => $derived['land_use'] ?? null,
            'target_land_uses' => $derived['land_uses'] ?? [],
            // Split the way the card's two selects hold it: Prefix carries the whole
            // token ("CON-RES"), Land Use only the code ("RES"). Null where the stage
            // sends its files to more than one purpose, because neither select can
            // then state one answer.
            'target_prefix'    => $single === null ? null : FileNumberLandUse::prefixFor($single),
            'target_code'      => $single === null ? null : FileNumberLandUse::codeFor($single),
            'application_type' => $derived['application_type'] ?? $stage->type,
            'file_option'      => $derived['file_option'] ?? $stage->type,
            'parent_files'     => $derived['parent_files'] ?? [],
            'consumes_serial'  => $derived['consumes_serial'] ?? true,
            // Set only when Land re-picked the parcels at the counter. The register and
            // the card both say so, because the plan then differs from the one Deeds
            // captured and the Commissioner approved.
            'selection_changed_at' => data_get($stage->payload, 'selection_changed_at'),
            // The slice of the modal's per-file entries this stage owns.
            'entry_start'      => $entryStart,
            'entry_count'      => $new->count(),

            // What this stage actually does, in counts: N new numbers minted, M files
            // retired to make way for them.
            'mints'       => $new->count(),
            'retires'     => ((int) $stage->rank) === 1 ? null : $new->count(),
            'new_numbers' => $new->pluck('final_file_no')->filter()->values()->all(),
            // The pre-commissioning counterpart: the holding numbers that WILL become
            // new file numbers. final_file_no is null until the Land step, so
            // new_numbers cannot answer "what is about to be issued".
            'new_holdings' => $new->pluck('holding_no')->filter()->values()->all(),
            'kept_numbers' => $kept->pluck('final_file_no')->filter()->values()->all(),
            'produced'    => $rows->pluck('final_file_no')->filter()->values()->all(),
            'holdings'    => $rows->pluck('holding_no')->filter()->values()->all(),
        ];
    }

    /**
     * file number => the plot number it carries, for the files a duplex left active.
     *
     * From mls_file_no first (the commissioning register's own answer) and fileNumber
     * behind it, because a file commissioned before mls_file_no was populated still has
     * a plot on the registry row. Read-only, and silent when there is nothing to read:
     * a duplex that has not commissioned yet simply has no entries.
     */
    protected function plotNumbers(array $fileNos): array
    {
        $fileNos = array_values(array_filter(array_unique($fileNos)));

        if (empty($fileNos)) {
            return [];
        }

        $conn = DB::connection('sqlsrv');
        $out  = [];

        foreach ([
            ['mls_file_no', 'full_file_number'],
            ['fileNumber',  'mlsfNo'],
        ] as [$table, $col]) {
            $rows = $conn->table($table)
                ->whereIn($col, $fileNos)
                ->get([$col . ' as file_no', 'plot_no']);

            foreach ($rows as $row) {
                $plot = trim((string) ($row->plot_no ?? ''));

                if ($plot !== '' && empty($out[$row->file_no])) {
                    $out[$row->file_no] = $plot;
                }
            }
        }

        return $out;
    }

    /**
     * What this duplex retired. Read back from decommissioned_files rather than
     * inferred, so the card reports what the registry actually holds.
     */
    protected function retired(DuplexParcelUpdate $duplex): array
    {
        $candidates = collect($duplex->source_file_nos ?? [])
            ->merge($duplex->files->pluck('final_file_no'))
            ->merge($duplex->files->pluck('source_file_no'))
            ->filter()
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return [];
        }

        return DB::connection('sqlsrv')->table('decommissioned_files')
            ->whereIn('file_no', $candidates->all())
            ->orderBy('id')
            ->get(['file_no', 'successor_file_no', 'decommissioning_reason', 'decommissioning_date'])
            ->map(fn ($r) => [
                'file_no'   => $r->file_no,
                'successor' => $r->successor_file_no ?? null,
                'reason'    => $r->decommissioning_reason,
                'date'      => $r->decommissioning_date
                    ? date('d M Y', strtotime($r->decommissioning_date))
                    : null,
            ])->values()->all();
    }

    /**
     * "Where the records went" for the files this duplex created — the same table
     * counts the File Indexing and MLS commissioning cards show.
     */
    public function storageSummary(DuplexParcelUpdate $duplex): ?array
    {
        // EVERY file the duplex produced, not just the first. The per-file summary was
        // written for File Indexing, where one save touches one file; reporting a
        // duplex's footprint from a single file understates it by the whole batch
        // (7 PRA rows read as 1).
        $files = $duplex->files
            ->pluck('final_file_no')
            ->filter()
            ->unique()
            ->values();

        if ($files->isEmpty()) {
            return null;
        }

        $service = app(IndexingStorageSummaryService::class);
        $merged  = null;

        foreach ($files as $fileNo) {
            try {
                $indexing = \App\Models\FileIndexing::on('sqlsrv')
                    ->where('file_number', $fileNo)
                    ->orderByDesc('id')
                    ->first();

                if (!$indexing) {
                    $indexing = new \App\Models\FileIndexing();
                    $indexing->setConnection('sqlsrv');
                    $indexing->file_number = $fileNo;
                    $indexing->general_registry = 'Lands Registry';
                }

                $one = $service->summarize($indexing, ['is_update' => false]);
            } catch (\Throwable $e) {
                continue;
            }

            if (!$one || empty($one['groups'])) {
                continue;
            }

            $merged = $merged === null ? $one : $this->mergeSummaries($merged, $one);
        }

        return $merged;
    }

    /** Add one file's row counts onto the running totals, keyed by group + table + label. */
    protected function mergeSummaries(array $into, array $add): array
    {
        foreach ($add['groups'] as $group) {
            $gi = null;
            foreach ($into['groups'] as $i => $existing) {
                if (($existing['title'] ?? null) === ($group['title'] ?? null)) {
                    $gi = $i;
                    break;
                }
            }

            if ($gi === null) {
                $into['groups'][] = $group;
                continue;
            }

            foreach ($group['rows'] ?? [] as $row) {
                $ri = null;
                foreach ($into['groups'][$gi]['rows'] as $j => $existingRow) {
                    if (($existingRow['table'] ?? null) === ($row['table'] ?? null)
                        && ($existingRow['label'] ?? null) === ($row['label'] ?? null)) {
                        $ri = $j;
                        break;
                    }
                }

                if ($ri === null) {
                    $into['groups'][$gi]['rows'][] = $row;
                } else {
                    $into['groups'][$gi]['rows'][$ri]['count'] =
                        ($into['groups'][$gi]['rows'][$ri]['count'] ?? 0) + ($row['count'] ?? 0);
                    // A per-file detail line is meaningless once several are summed.
                    unset($into['groups'][$gi]['rows'][$ri]['detail']);
                }
            }
        }

        return $into;
    }
}
