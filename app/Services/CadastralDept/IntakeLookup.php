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
            // Decommissioned: never taken in; the message names the successor.
            return ['status' => 'refused', 'message' => $r['message'], 'flags' => $r['flags']] + $out;
        } else {
            $file = $this->commissioned((string) ($q['file_number'] ?? ''));
            if (!$file) {
                $n = trim((string) ($q['file_number'] ?? ''));

                return ['message' => $n === ''
                    ? 'Select a file number first.'
                    : "{$n} is neither indexed nor commissioned. Index or commission the file first, then register it here."] + $out;
            }
        }

        $out['file'] = $file;
        $out['correspondence'] = $this->correspondence($file['file_number']);
        $out['duplicate'] = $this->duplicate($file['file_number']);
        $out['flags'] = array_values(array_filter([
            $file['record'] === 'commissioned' ? ['info', 'Commissioned, not yet indexed'] : null,
            $out['duplicate']['flag'] ? ['warn', 'Possible duplicate: ' . $out['duplicate']['note']] : null,
            $out['correspondence']['exists']
                ? ['ok', 'Correspondence file: ' . ($out['correspondence']['shadow'] ?: ($out['correspondence']['fileno'] ?: 'yes'))]
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

    /** A live fileNumber row for the number, when the file is commissioned but not indexed. */
    private function commissioned(string $number): ?array
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
            ->where(fn ($w) => $w->whereNull('is_decommissioned')->orWhere('is_decommissioned', 0))
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
    private function duplicate(string $number): array
    {
        $notes = [];

        if (CaseWorkflow::installed()) {
            $refs = CadastralCase::query()->where('file_number', $number)
                ->where('status', '!=', CadastralCase::STATUS_IN_PROGRESS)
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
