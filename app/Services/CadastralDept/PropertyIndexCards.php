<?php

namespace App\Services\CadastralDept;

use App\Services\DocumentQr\QrRenderer;
use App\Services\EdmsScanUploadFolderService;
use App\Services\Edms\EdmsDocumentPathResolver;
use App\Services\LegalSearchService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Property Index Cards (user, 2026-10-16). One card per file: the file's details and every
 * transaction the Legal Search engine finds for it in the four tables (PRA, File History,
 * CofO and Deeds Registration), drawn with the cadastral_dept.pic._card template and filed as
 * a PDF to the file's EDMS folder under the Cadastral Registry:
 *
 *   EDMS/SCAN_UPLOAD/Cadastral_Registry/PIC/{FILE NUMBER}/PIC-{FILE NUMBER}.pdf
 *
 * Regenerating overwrites the PDF, so a file has one card. It is not a scannings row: the card
 * is generated, not scanned, and would otherwise land in Page Typing on every regeneration.
 *
 * Generated automatically when Registry registers a file into the Cadastral workflow (the file
 * is "under process"), and on demand from the Generate Property Index Card page.
 */
class PropertyIndexCards
{
    public const TABLE = 'cadastral_property_index_cards';
    public const FOLDER = 'PIC';

    /** The Legal Search engine's sources, as the card's Remarks name them; any other is shown as it comes. */
    private const SOURCES = [
        'pra' => 'PRA',
        'file_history_staging' => 'File History',
        'CofO_staging' => 'CofO',
        'deed_registrations' => 'Deeds Registration',
        'Related Fileno' => 'Related file',
    ];

    public function __construct(
        private LegalSearchService $search,
        private EdmsDocumentPathResolver $paths,
        private EdmsScanUploadFolderService $folders,
        private QrRenderer $qr,
    ) {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection('sqlsrv')->hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The card's row for a file number, or null. */
    public function find(string $fileNumber): ?object
    {
        if (!self::installed()) {
            return null;
        }

        return DB::connection('sqlsrv')->table(self::TABLE)->where('file_number', trim($fileNumber))->first();
    }

    /** Public-disk path of the file's card PDF. */
    public function pathFor(string $fileNumber): string
    {
        $folder = $this->folders->folderName($fileNumber);

        return EdmsDocumentPathResolver::SCAN_UPLOAD_ROOT . '/' . $this->paths->registrySlug('Cadastral Registry')
            . '/' . self::FOLDER . '/' . $folder . '/PIC-' . $folder . '.pdf';
    }

    /**
     * The card's data for a file number, from the file index and the Legal Search engine.
     *
     * @return array the $pic array cadastral_dept.pic._card renders
     */
    public function build(string $fileNumber): array
    {
        $fileNumber = trim($fileNumber);
        $db = DB::connection('sqlsrv');

        $ix = $db->table('file_indexings')->whereNull('deleted_at')->where('file_number', $fileNumber)->orderByDesc('id')->first();

        // The Legal Search engine, read the way its Timeline is (user, 2026-10-20): not narrowed to
        // the parcel's prop_id, which dropped the related files' transactions (COM-2026-103 showed
        // none of COM-1985-118's or KN5318's). The prop_id still finds the related files.
        $query = ['file_number' => $fileNumber];
        $propId = $this->propId($fileNumber);
        $report = [];
        try {
            $result = $this->search->buildPrintReport($query);
            if (($result['status'] ?? null) === 200) {
                $report = $result['payload']['data'] ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning('PropertyIndexCards: legal search failed', ['file_no' => $fileNumber, 'error' => $e->getMessage()]);
            throw new \RuntimeException('The Legal Search engine could not read ' . $fileNumber . ': ' . $e->getMessage(), 0, $e);
        }

        if (!$ix && empty($report)) {
            throw new \RuntimeException("No file index or Legal Search record was found for {$fileNumber}.");
        }

        $dash = static fn ($x) => (($x = trim((string) $x)) === '-' ? '' : $x);
        $place = static fn ($x) => ($x = trim((string) $x)) === '' ? '' : mb_convert_case(mb_strtolower($x), MB_CASE_TITLE);
        $extra = $this->sourceDetails($fileNumber);

        [$repDistrict, $repLga] = array_pad(array_map('trim', explode(',', $dash($report['district_lga'] ?? ''), 2)), 2, '');

        $size = $dash($ix->plot_size ?? '') ?: $dash($report['size'] ?? '');
        $lat = $dash($ix->latitude ?? '');
        $long = $dash($ix->longitude ?? '');
        if ($lat === '' && $long === '' && ($ll = $dash($report['lon_lat'] ?? '')) !== '') {
            [$long, $lat] = array_pad(array_map('trim', explode(',', $ll, 2)), 2, '');
        }

        return [
            'file_number' => $fileNumber,
            'name' => $dash($report['file_title'] ?? '') ?: $place($ix->file_title ?? ''),
            'plot_no' => $dash($ix->plot_number ?? '') ?: $dash($report['plot_no'] ?? ''),
            'district' => $place($ix->district ?? '') ?: $repDistrict,
            'lga' => $place($ix->lga ?? '') ?: $repLga,
            'locality' => $place($extra['layout'] ?? ''),
            'approx_area_ha' => $this->hectares($size),
            'final_area_ha' => '',
            'tp_no' => $dash($ix->tp_no ?? '') ?: $dash($report['tpno'] ?? ''),
            'plan_no' => $extra['approved_plan_no'] ?? '',
            'tracing_no' => $dash($ix->lpkn_no ?? '') ?: ($extra['lpkn_no'] ?? ''),
            'map_sheet' => $extra['metric_sheet'] ?? '',
            'lat' => $lat,
            'long' => $long,
            'related_files' => $this->relatedFiles($fileNumber, $ix, $propId),
            'transactions' => $this->transactions($report['rows'] ?? [], $fileNumber),
            'form_no' => 'SY-039',
            'file_indexing_id' => $ix->id ?? null,
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => optional(auth()->user())->name,
            'sources' => ['Legal Search'],
        ];
    }

    /**
     * Build the card, file its PDF and record it. Overwrites the file's previous card.
     *
     * @param  string  $trigger  intake | manual
     */
    public function generate(string $fileNumber, string $trigger, ?int $caseId = null, ?int $userId = null): object
    {
        if (!self::installed()) {
            throw new \RuntimeException('The Property Index Cards table has not been created yet.');
        }
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            throw new \InvalidArgumentException('Choose a file number.');
        }

        $db = DB::connection('sqlsrv');
        try {
            $pic = $this->build($fileNumber);
            $pic['qr'] = $this->qr->dataUri(route('cadastral-dept.pic.show', ['file' => $fileNumber]), 160);

            $pdf = Pdf::loadView('cadastral_dept.pic.pdf', ['pic' => $pic])->setPaper('a5', 'landscape')->output();
            $path = $this->pathFor($fileNumber);
            if (!Storage::disk('public')->put($path, $pdf)) {
                throw new \RuntimeException('The card could not be written to the EDMS folder.');
            }
        } catch (\Throwable $e) {
            $this->record($fileNumber, ['trigger' => $trigger, 'cadastral_case_id' => $caseId, 'last_error' => mb_substr($e->getMessage(), 0, 1000)], false);
            throw $e;
        }

        return $this->record($fileNumber, [
            'file_indexing_id' => $pic['file_indexing_id'],
            'file_title' => mb_substr((string) $pic['name'], 0, 300) ?: null,
            'path' => $path,
            'transactions_count' => count($pic['transactions']),
            'trigger' => $trigger,
            'cadastral_case_id' => $caseId,
            'generated_by' => $userId,
            'generated_at' => now(),
            'last_error' => null,
        ], true);
    }

    /** Insert or update the file's row; $generated counts a successful generation. */
    private function record(string $fileNumber, array $values, bool $generated): object
    {
        $db = DB::connection('sqlsrv');
        $existing = $db->table(self::TABLE)->where('file_number', $fileNumber)->first();
        if (!$existing && !$generated) {
            // A failure on a file with no card yet still leaves a row, so the page can show why.
            $values += ['path' => null, 'generated_at' => null];
        }
        $values['updated_at'] = now();

        if ($existing) {
            if ($generated) {
                $values['generation_count'] = (int) $existing->generation_count + 1;
            }
            $db->table(self::TABLE)->where('id', $existing->id)->update($values);
        } else {
            $db->table(self::TABLE)->insert($values + [
                'file_number' => mb_substr($fileNumber, 0, 100),
                'generation_count' => $generated ? 1 : 0,
                'created_at' => now(),
            ]);
        }

        return $db->table(self::TABLE)->where('file_number', $fileNumber)->first();
    }

    /**
     * The engine's rows from the four tables, in the engine's order (the order a Legal Search shows).
     * Its derived lifecycle rows (File Commissioning, Recertification, ...) are not transactions.
     */
    /**
     * Every row of the Legal Search Timeline, the related files' too (user, 2026-10-20); a row from
     * another file names it in Remarks.
     */
    private function transactions(array $rows, string $fileNumber = ''): array
    {
        $dash = static fn ($x) => (($x = trim((string) $x)) === '-' ? '' : $x);
        $out = [];
        foreach ($rows as $row) {
            $source = (string) ($row['source_table'] ?? '');
            // File Commissioning / File Decommissioning are not shown on the card (user, 2026-10-20).
            if (preg_match('/^\s*file\s+(de)?commissioning\s*$/i', (string) ($row['instrument_type'] ?? ''))) {
                continue;
            }
            $date = $dash($row['reg_date'] ?? '') ?: $dash($row['transaction_date'] ?? '');
            $time = $dash($row['reg_time'] ?? '');
            $reg = $dash($row['reg_no'] ?? '');
            $party2 = $dash($row['grantee'] ?? '');
            if (($p3 = $dash($row['party_3'] ?? '')) !== '') {
                $party2 = trim($party2 . ' & ' . $p3, ' &');
            }
            $remarks = self::SOURCES[$source] ?? $source;
            $from = $dash($row['file_no'] ?? '');
            if ($from !== '' && strcasecmp($from, $fileNumber) !== 0) {
                $remarks .= ' · ' . $from;
            }
            // The card's PDF font has no arrows: "Re-Grant → X" prints as "Re-Grant to X".
            if (($c = str_replace(['→', '->'], 'to', $dash($row['comments'] ?? ''))) !== '') {
                $remarks .= ' · ' . $c;
            }
            $out[] = [
                'type' => $dash($row['instrument_type'] ?? ''),
                'party1' => $dash($row['grantor'] ?? ''),
                'party2' => $party2,
                'reg_datetime' => trim($date . ' ' . $time),
                // "0/0/0" is the engine's "no particulars on record".
                'reg_particulars' => preg_match('#^0*/0*/0*$#', $reg) ? '' : $reg,
                'remarks' => $remarks,
            ];
        }

        return $out;
    }

    /** First recorded Plan No., Map Sheet, LPKN No. and layout in the source tables. */
    private function sourceDetails(string $fileNumber): array
    {
        $db = DB::connection('sqlsrv');
        $out = [];
        foreach (['pra', 'CofO_staging', 'file_history_staging'] as $table) {
            try {
                $rows = $db->table($table)
                    ->where(fn ($q) => $q->where('mlsFNo', $fileNumber)->orWhere('kangisFileNo', $fileNumber)
                        ->orWhere('NewKANGISFileno', $fileNumber)->orWhere('fileno', $fileNumber))
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->get(['approved_plan_no', 'metric_sheet', 'lpkn_no', 'layout']);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($rows as $r) {
                foreach (['approved_plan_no', 'metric_sheet', 'lpkn_no', 'layout'] as $col) {
                    $val = trim((string) ($r->{$col} ?? ''));
                    if (!isset($out[$col]) && $val !== '' && $val !== '-') {
                        $out[$col] = $val;
                    }
                }
            }
        }

        return $out;
    }

    /** The file's other numbers: its MLS / KANGIS / New KANGIS aliases and its linked files. */
    private function relatedFiles(string $fileNumber, ?object $ix, ?string $propId): array
    {
        $nums = [];
        foreach (['mls_file_no', 'kangis_file_no', 'new_kangis_file_no', 'corresponding_fileno', 'old_fileno'] as $col) {
            $nums[] = $ix->{$col} ?? null;
        }
        $linked = json_decode((string) ($ix->related_fileno ?? ''), true);
        if (is_array($linked)) {
            foreach ($linked as $l) {
                $nums[] = is_array($l) ? ($l['file_number'] ?? $l['fileno'] ?? null) : $l;
            }
        }
        if ($propId) {
            $m = DB::connection('sqlsrv')->table('PropID_Master')->where('prop_id', $propId)
                ->get(['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno']);
            foreach ($m as $row) {
                foreach ((array) $row as $v) {
                    $nums[] = $v;
                }
            }
        }

        // Only the other files (user, 2026-10-20): no -REF- numbers (COM-2026-103-REF-124680), and
        // not the card's own file in any form (with a -REF- or "(T)" suffix).
        $self = strtoupper($fileNumber);
        $base = fn (string $n) => strtoupper(trim(preg_replace('/(-REF-\S*|\s*\([^)]*\))+$/i', '', $n)));
        $seen = [];
        foreach ($nums as $n) {
            $n = trim((string) $n);
            if ($n === '' || $n === '-' || stripos($n, '-REF-') !== false || $base($n) === $self || isset($seen[strtoupper($n)])) {
                continue;
            }
            $seen[strtoupper($n)] = $n;
        }

        return array_values($seen);
    }

    /** The parcel's prop_id from PropID_Master, matched on any of its file-number columns. */
    private function propId(string $fileNumber): ?string
    {
        try {
            $propId = DB::connection('sqlsrv')->table('PropID_Master')
                ->where(function ($q) use ($fileNumber) {
                    foreach (['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'] as $col) {
                        $q->orWhereRaw("UPPER(LTRIM(RTRIM({$col}))) = ?", [strtoupper($fileNumber)]);
                    }
                })
                ->whereNotNull('prop_id')->orderBy('id')->value('prop_id');
        } catch (\Throwable $e) {
            return null;
        }
        $propId = trim((string) $propId);

        return $propId !== '' ? $propId : null;
    }

    /** Plot size as hectares: a bare number is taken as hectares, m² / sq m is converted. */
    private function hectares(string $size): string
    {
        $size = trim($size);
        if ($size === '' || !preg_match('/([0-9]+(?:\.[0-9]+)?)/', str_replace(',', '', $size), $m)) {
            return '';
        }
        $n = (float) $m[1];
        if (preg_match('/m2|m²|sq\.?\s*m|sqm|square/i', $size)) {
            $n /= 10000;
        }

        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}
