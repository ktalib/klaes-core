<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\Cadastral\CorrespondenceFiles;
use App\Support\LgaNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the Registry intake form needs about a picked file, and the server's
 * own re-read of it on save. READ ONLY.
 *
 * The file must already be indexed (file_indexings) or commissioned (a live
 * fileNumber row), as the client asked. Indexed files are resolved through the
 * old Cadastral Module's CadastralRegistryLookup, so both modules agree on
 * which file a number means.
 *
 * Duplicates (client brief): a file that has come in before is flagged, not
 * refused. Only a second OPEN case for the same number is refused.
 */
class IntakeLookup
{
    /** fileNumber columns that can carry the number (cross-column, never positional). */
    private const COMMISSIONED_COLUMNS = ['mlsfNo', 'kangisFileNo', 'NewKANGISFileNo', 'st_file_no', 'gkn_fileno', 'dciv_fileno'];

    /** @var string[]|null canonical LGA names, read once per request */
    private ?array $lgaNames = null;

    public function __construct(
        private CadastralRegistryLookup $lookup,
        private WorkflowDefinition $def
    ) {
    }

    /**
     * @param array{file_number?:string, tab?:string, file_indexing_id?:int} $q
     * @return array{status:string, message:?string, candidates:array, file:?array, flags:array, open_case:?array, correspondence:?array}
     */
    public function resolve(array $q): array
    {
        $out = [
            'status' => 'not_found', 'message' => null, 'candidates' => [],
            'file' => null, 'flags' => [], 'open_case' => null, 'correspondence' => null,
            'duplicate' => ['flag' => false, 'note' => null],
        ];

        $r = $this->lookup->resolveFile([
            'file_number' => (string) ($q['file_number'] ?? ''),
            'tab' => (string) ($q['tab'] ?? ''),
            'file_indexing_id' => (int) ($q['file_indexing_id'] ?? 0),
            'scope' => 'indexed',
        ]);

        if ($r['status'] === 'ambiguous') {
            return ['status' => 'ambiguous', 'message' => $r['message'], 'candidates' => $r['candidates']] + $out;
        }

        // Verification still processes a decommissioned file (user, 2026-10-15): take its
        // index row instead of refusing it; the File Status checks show it is decommissioned.
        $verification = ($q['workflow'] ?? '') === 'verification';
        if ($verification && $r['status'] === 'refused' && (int) ($r['file']['id'] ?? 0) > 0) {
            $r = $this->lookup->resolveFile([
                'file_number' => (string) ($q['file_number'] ?? ''),
                'file_indexing_id' => (int) $r['file']['id'],
                'scope' => 'indexed',
            ]);
        }

        if ($r['status'] === 'ok' && $r['file']) {
            $f = $r['file'];
            $v = $r['values'];
            $file = [
                'file_number' => $f['file_number'],
                'file_indexing_id' => $f['file_indexing_id'],
                'title' => ($f['owner'] ?? '') ?: ($v['file_title'] ?? null),
                'plot' => ($v['prop_plot'] ?? null) ?: ($v['plot_no'] ?? null),
                'district' => $this->district($v),
                'lga' => $this->lga($v['prop_lga'] ?? null),
                'location' => ($v['location'] ?? null) ?: $this->location($this->district($v), $this->lga($v['prop_lga'] ?? null)),
                'registry_label' => $f['registry_label'],
                'type' => $f['type'],
                'other_numbers' => $f['other_numbers'],
                'file_source' => $this->def->fileSourceForLookup($f['source']),
                'record' => 'indexed',
            ];
        } elseif ($r['status'] === 'refused' && $r['file']) {
            // Decommissioned: not taken in (Verification excepted, above); the message names the successor.
            return ['status' => 'refused', 'message' => $r['message'], 'flags' => $r['flags']] + $out;
        } else {
            $file = $this->commissioned((string) ($q['file_number'] ?? ''), $verification);
            if (!$file) {
                $n = trim((string) ($q['file_number'] ?? ''));

                return ['message' => $n === ''
                    ? 'Select a file number first.'
                    : "{$n} is neither indexed nor commissioned. Index or commission the file first, then register it here."] + $out;
            }
        }

        $out['file'] = $file;
        $out['correspondence'] = $this->correspondence($file['file_number']);
        $out['duplicate'] = $this->duplicate($file['file_number'], (string) ($q['workflow'] ?? ''));
        $out['flags'] = array_values(array_filter([
            $file['record'] === 'commissioned' ? ['info', 'Commissioned, not yet indexed'] : null,
            $out['duplicate']['flag'] ? ['warn', 'Possible duplicate: ' . $out['duplicate']['note']] : null,
            $out['correspondence']['exists']
                // Matched through file indexing (a flag, no CSF row): labelled KLAES Matched, in red (user, 2026-10-27).
                ? ($out['correspondence']['shadow']
                    ? ['ok', 'Correspondence file: ' . $out['correspondence']['shadow']]
                    : ['danger', 'KLAES Matched'])
                : ['info', 'No correspondence file yet'],
        ]));

        if ($open = $this->openCase($file['file_number'])) {
            $out['open_case'] = $open;
            $out['status'] = 'refused';
            $out['message'] = "{$file['file_number']} is already in the department as {$open['ref']}, now with {$open['desk']}. "
                . 'A file can only be registered again after its current case closes.';

            return $out;
        }

        $out['status'] = 'ok';

        return $out;
    }

    /**
     * Number prefixes each application type lists at intake (user, 2026-10-12):
     * Conversion lists the indexed CON- files, Direct Allocation the indexed
     * RES- / COM- / IND- / AG- files. Verification lists any indexed file,
     * decommissioned ones included (user, 2026-10-15): its File Status checks
     * are what Verification is for, and a correspondence file is shown, not required.
     */
    private const LIST_PREFIXES = [
        'conversion' => ['CON-'],
        'direct' => ['RES-', 'COM-', 'IND-', 'AG-'],
    ];

    /**
     * The file-number prefixes of Conversion or Direct Allocation. Verification picks one of the two
     * first and lists that group's files only (user, 2026-10-26).
     *
     * @return string[]
     */
    public static function prefixesFor(?string $group): array
    {
        return self::LIST_PREFIXES[(string) $group] ?? [];
    }

    /**
     * The intake dropdown: up to $limit live indexed files for the application
     * type, matching what was typed. READ ONLY.
     *
     * @return array<int, array{id:int, file_number:string, title:?string, plot:?string, district:?string, lga:?string, tracking_id:?string}>
     */
    public function options(string $workflow, string $term, int $limit = 30, ?string $group = null, array $typePrefixes = []): array
    {
        if (!isset(self::LIST_PREFIXES[$workflow]) && $workflow !== 'verification') {
            return [];
        }

        $q = DB::connection('sqlsrv')->table('file_indexings')
            ->select(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga', 'tracking_id'])
            ->whereNotNull('file_number')
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0));

        // A file type with its own numbers (ST File: ST-, SLTR File: SLTR-, Conversion File: CON-) lists
        // just those, under any application type (user, 2026-10-27).
        if ($workflow !== 'verification') {
            $listPrefixes = $typePrefixes ?: self::LIST_PREFIXES[$workflow];
            $q->where(fn ($w) => $w->whereNull('is_decommissioned')->orWhere('is_decommissioned', 0));
            $q->where(function ($w) use ($listPrefixes) {
                foreach ($listPrefixes as $prefix) {
                    $w->orWhere('file_number', 'like', $prefix . '%');
                }
            });
        } elseif ($typePrefixes) {
            $q->where(function ($w) use ($typePrefixes) {
                foreach ($typePrefixes as $prefix) {
                    $w->orWhere('file_number', 'like', $prefix . '%');
                }
            });
        } elseif (self::prefixesFor($group)) {
            // Verification > Conversion or Direct Allocation: that group's files, decommissioned ones included.
            $q->where(function ($w) use ($group) {
                foreach (self::prefixesFor($group) as $prefix) {
                    $w->orWhere('file_number', 'like', $prefix . '%');
                }
            });
        }

        $term = trim($term);
        if ($term !== '') {
            $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';
            $q->where(fn ($w) => $w->where('file_number', 'like', $like)->orWhere('file_title', 'like', $like));
        }

        return $q->orderBy('file_number')->limit($limit)->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'file_number' => trim((string) $r->file_number),
                'title' => $r->file_title ?: null,
                'plot' => $r->plot_number ?: null,
                'district' => $r->district ?: null,
                'lga' => $this->lga($r->lga),
                'tracking_id' => $r->tracking_id ?: null,
            ])->all();
    }

    /** A live fileNumber row for the number, when the file is commissioned but not indexed. */
    private function commissioned(string $number, bool $withDecommissioned = false): ?array
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        $row = DB::connection('sqlsrv')->table('fileNumber')
            ->where(function ($w) use ($number) {
                foreach (self::COMMISSIONED_COLUMNS as $col) {
                    $w->orWhere($col, $number);
                }
            })
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->when(!$withDecommissioned, fn ($q) => $q->where(fn ($w) => $w->whereNull('is_decommissioned')->orWhere('is_decommissioned', 0)))
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'file_number' => $number,
            'file_indexing_id' => null,
            'title' => $row->FileName ?: null,
            'plot' => $row->plot_no ?? null,
            'district' => $row->district ?: null,
            'lga' => $this->lga($row->lga ?? null),
            'location' => $this->location($row->district ?? null, $this->lga($row->lga ?? null)),
            'registry_label' => 'Commissioned (' . ($row->type ?: 'fileNumber') . ')',
            'type' => $row->type ?? null,
            'other_numbers' => array_values(array_unique(array_filter(array_map(
                fn ($c) => trim((string) ($row->{$c} ?? '')),
                self::COMMISSIONED_COLUMNS
            ), fn ($v) => $v !== '' && $v !== $number))),
            'file_source' => null,
            'record' => 'commissioned',
        ];
    }

    /**
     * The file's correspondence file: the index flag, plus the shadow-file row
     * the old module's matching reads (its ref_number is the CSF number), so a
     * Verification case carries the CSF number from intake.
     *
     * @return array{exists:bool, fileno:?string, shadow:?string}
     */
    private function correspondence(string $number): array
    {
        $c = $this->lookup->correspondence($number);
        try {
            $known = app(CorrespondenceFiles::class)->existingForNumber($number);
        } catch (\Throwable $e) {
            $known = null;   // the shadow lookup is extra; the index flag still answers
        }

        return [
            'exists' => (bool) $c['exists'] || $known !== null,
            'fileno' => ($c['corresponding_fileno'] ?: ($known['fileno'] ?? null)) ?: null,
            'shadow' => $known['shadow']->ref_number ?? null,
        ];
    }

    /**
     * Flag, never refuse: earlier closed cases, earlier intake receipts in the
     * old Cadastral Module, and the duplicate register. The same-plot check was
     * removed from this form (user, 2026-10-12).
     *
     * @return array{flag:bool, note:?string}
     */
    private function duplicate(string $number, string $workflow = ''): array
    {
        $notes = [];

        if (CaseWorkflow::installed()) {
            $refs = CadastralCase::query()->where('file_number', $number)
                ->where('status', '!=', CadastralCase::STATUS_IN_PROGRESS)
                // Verification follows a completed Conversion or Direct Allocation case on the
                // same file, so those are not a duplicate (user, 2026-10-15).
                ->when($workflow === 'verification', fn ($q) => $q->whereNot(fn ($w) => $w
                    ->whereIn('workflow', ['conversion', 'direct'])
                    ->where('status', CadastralCase::STATUS_COMPLETED)))
                ->orderByDesc('id')->limit(5)->pluck('case_ref');
            if ($refs->isNotEmpty()) {
                $notes[] = 'came in before as ' . $refs->implode(', ') . '.';
            }
        }

        if (Schema::connection('sqlsrv')->hasTable('cadastral_file_receipts')) {
            $receipts = DB::connection('sqlsrv')->table('cadastral_file_receipts')
                ->where('file_number', $number)->whereNull('deleted_at')
                ->orderByDesc('id')->limit(5)->pluck('receipt_ref');
            if ($receipts->isNotEmpty()) {
                $notes[] = 'old intake receipt(s) ' . $receipts->implode(', ') . '.';
            }
        }

        $duplicates = $this->lookup->duplicates($number);
        if ($duplicates->isNotEmpty()) {
            $notes[] = $duplicates->count() . ' entry(ies) in the duplicate register.';
        }

        return ['flag' => $notes !== [], 'note' => $notes ? implode(' ', $notes) : null];
    }

    /**
     * Scan to register (backlog, 2026-10-27): turn any KLAES QR (tracking sheet, label, RofO, a
     * typed tracking ID or file number) into the file(s) it names, each with its open case (if it
     * is already in the department) and the application types its number allows. The Register
     * page then asks Conversion / Direct Allocation / Verification with the detected one marked,
     * so nobody has to open the right tab first. READ ONLY.
     *
     * @return array{resolved:bool, message:?string, matches:array<int, array>}
     */
    public function scan(string $payload): array
    {
        $r = app(\App\Services\FileTracking\FileQrResolver::class)->resolve($payload);
        $matches = [];
        foreach ($r['matches'] as $m) {
            $number = (string) ($m['file_number'] ?? '');
            if ($number === '') {
                continue;
            }
            $open = $this->openCase($number);
            $matches[] = [
                'file_number' => $number,
                'title' => $m['file_title'],
                'tracking_id' => $m['tracking_id'],
                'indexed' => (bool) $m['indexed'],
                'open_case' => $open ? $open + ['url' => route('cadastral-dept.cases.show', $open['id'])] : null,
                // The file's details for the right half of the scan panel (only the first few matches).
                'details' => count($matches) < 3 ? $this->scanDetails($number) : null,
            ] + self::detect($number);
            // Already has a correspondence file: suggest Verification (user, 2026-10-27).
            $last = &$matches[count($matches) - 1];
            if (!empty($last['details']['csf']) && in_array('verification', $last['workflows'], true)) {
                $last['suggested'] = 'verification';
                $last['proceed_verification'] = true;
            }
            unset($last);
        }

        return [
            'resolved' => $matches !== [],
            'message' => $matches ? (count($matches) > 1 ? 'This code matches more than one file. Pick the right one.' : null)
                // The resolver's wording is the Secretariat's ("log it manually"); here the fallback is the list.
                : preg_replace('/\s*Log (the file|it) manually\.?$/', '', (string) ($r['message'] ?: 'No KLAES file matches this code.'))
                    . ' Choose the application type below and pick the file from the list.',
            'matches' => $matches,
        ];
    }

    /** Plot, district, LGA, location and correspondence file of a scanned file, from resolve(). */
    private function scanDetails(string $number): ?array
    {
        try {
            $r = $this->resolve(['file_number' => $number, 'workflow' => 'verification']);
        } catch (\Throwable $e) {
            return null;
        }
        $f = $r['file'] ?? null;
        if (!$f) {
            return null;
        }

        return [
            'plot' => $f['plot'] ?? null,
            'district' => $f['district'] ?? null,
            'lga' => $f['lga'] ?? null,
            'location' => $f['location'] ?? null,
            'correspondence' => !empty($r['correspondence']['exists'])
                ? (($r['correspondence']['shadow'] ?? null) ?: ($r['correspondence']['fileno'] ?? 'yes')) : null,
            'csf' => !empty($r['correspondence']['exists']) ? $this->csfDetails($r['correspondence']) : null,
        ];
    }

    /**
     * The correspondence file's own details, for the scan panel (user, 2026-10-27): a file that
     * already has one has been through Cadastral, so the officer is told to proceed with Verification.
     */
    private function csfDetails(array $c): array
    {
        $row = !empty($c['shadow'])
            ? DB::connection('sqlsrv')->table('cadastral_shadow_files')->where('ref_number', $c['shadow'])->first()
            : null;

        return [
            'ref' => $row->ref_number ?? ($c['shadow'] ?? null),
            'file_number' => $row->full_number ?? ($c['fileno'] ?? null),
            'name' => $row->file_name ?? null,
            'plot' => $row->plot_no ?? null,
            'lga' => $row->lga ?? null,
            'location' => $row->location ?? null,
            // 'csf': a correspondence (shadow) file row; 'indexing': matched through file indexing (KLAES Matched).
            'source' => $row ? 'csf' : 'indexing',
            'matched' => $row && $row->date_matched ? trim(\Illuminate\Support\Carbon::parse($row->date_matched)->format('d M Y') . ' ' . substr((string) $row->time_matched, 0, 5)) : null,
        ];
    }

    /**
     * What a file number allows, from its prefix: the group (Conversion: CON-; Direct Allocation:
     * RES- / COM- / IND- / AG-), a file type when the prefix fixes one (CON-, ST-, SLTR-), and the
     * application types it can be registered under. The suggestion is only a default: the
     * officer still chooses, Verification included, since the number cannot tell (user, 2026-10-27).
     *
     * @return array{group:?string, file_type:?string, workflows:string[], suggested:?string}
     */
    public static function detect(string $number): array
    {
        $n = strtoupper(trim($number));
        $fileType = null;
        foreach ((array) config('cadastral_dept.file_types', []) as $key => $type) {
            foreach ((array) ($type['prefixes'] ?? []) as $prefix) {
                if (str_starts_with($n, strtoupper($prefix))) {
                    $fileType = $key;
                    break 2;
                }
            }
        }
        $group = null;
        foreach (self::LIST_PREFIXES as $g => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($n, $prefix)) {
                    $group = $g;
                    break 2;
                }
            }
        }
        $workflows = $fileType
            ? array_values((array) config("cadastral_dept.file_types.{$fileType}.workflows", []))
            : ($group ? [$group, 'verification'] : ['conversion', 'direct', 'verification']);

        return ['group' => $group, 'file_type' => $fileType, 'workflows' => $workflows, 'suggested' => $group];
    }

    /** @return array{ref:string, desk:string, step:string}|null */
    private function openCase(string $number): ?array
    {
        if (!CaseWorkflow::installed()) {
            return null;
        }
        $case = CadastralCase::query()->open()->where('file_number', $number)->orderByDesc('id')->first();

        return $case ? ['ref' => $case->case_ref, 'desk' => $case->deskShort(), 'step' => $case->stepLabel(), 'id' => $case->id] : null;
    }

    private function district(array $v): ?string
    {
        $d = $v['prop_district'] ?? null;

        return ($d === 'Other' || $d === 'other') ? ($v['prop_district_other'] ?? null) : $d;
    }

    /**
     * The stored LGA in the lgas table's spelling ("KUMBOTSO" -> "Kumbotso"), so a
     * case and everything copied from it reads the same. A value the shared
     * LgaNormalizer can't place is kept as stored rather than guessed.
     */
    private function lga(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $this->lgaNames ??= DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->pluck('name')->all();

        return LgaNormalizer::normalize($raw, $this->lgaNames) ?? $raw;
    }

    /** District + LGA only; the plot never goes into a location. */
    private function location(?string $district, ?string $lga): ?string
    {
        $parts = array_values(array_filter([trim((string) $district), trim((string) $lga)], fn ($p) => $p !== ''));

        return $parts ? implode(', ', $parts) : null;
    }
}
