<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralFileReceipt;
use App\Models\CadastralShadowFile;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The correspondence (cadastral copy) file for a registered receipt.
 *
 * NOT A SECOND REGISTER. KLAES already commissions correspondence files on the
 * "Commission Correspondence File (Match MLSFileNo)" screen,
 * MlsFileNoMatchingController::store(), which writes three things:
 *
 *   1. a cadastral_shadow_files row (CadastralShadowFile: ref_number
 *      CSF-Ymd-His, full_number, file_name, plot_no, location, lga,
 *      tracking_id, created_by, date_matched, time_matched, is_deleted = 0)
 *   2. fileNumber.csf = '1' on the file's fileNumber row
 *   3. file_indexings.is_corresponding_file = 1, corresponding_fileno = the
 *      number, on the file's index row
 *
 * ensureFor() writes the same three, through the same model, so a file
 * commissioned from the registry looks exactly like one commissioned on that
 * screen and appears in its list. The controller itself is not called: it is
 * a JSON endpoint that requires an LGA id from its own form, refuses a number
 * with no mlsfNo row, and never checks whether a shadow file already exists.
 *
 * WHERE THIS DIFFERS FROM THAT SCREEN, ON PURPOSE
 *  - It looks first. A live shadow file for any of the file's numbers, or the
 *    index flag already set, means the correspondence exists: the receipt is
 *    MATCHED to it and nothing is created. (48k index rows carry the flag from
 *    earlier bulk work with no shadow row behind them; those match with no
 *    cadastral_shadow_file_id, and the page reads corresponding_fileno.)
 *  - The fileNumber row is matched across mlsfNo, kangisFileNo, NewKANGISFileNo
 *    and st_file_no, not on mlsfNo alone, and a file with no fileNumber row
 *    still gets its correspondence file (tracking_id then comes from the index).
 *  - The index flag is set on the receipt's own file_indexings row and the row
 *    holding the number in file_number (unique, indexed). The legacy screen also
 *    sweeps st_fillno, which is unindexed: inside a registration transaction
 *    that would take update locks across 172k rows.
 *
 * Must be called inside a sqlsrv transaction, with the receipt row and its
 * file_indexings row already locked (FileReceiptController::markRegistered).
 * Those two locks are what make a double click create one file, not two.
 */
class CorrespondenceFiles
{
    private const CONN = 'sqlsrv';

    public function __construct(private AuditService $audit = new AuditService()) {}

    private function conn()
    {
        return DB::connection(self::CONN);
    }

    /**
     * Create or match the receipt's correspondence file, and set
     * correspondence_status / cadastral_shadow_file_id on the receipt in memory
     * (the caller saves it).
     *
     * Conversion files get one too: the concept note only exempts them from
     * charting.
     *
     * @return array{action: string, shadow: ?CadastralShadowFile, fileno: ?string}
     *         action: kept | matched | created
     */
    public function ensureFor(CadastralFileReceipt $receipt): array
    {
        // Already linked (a re-registration after a reopen): nothing to do.
        if ($receipt->cadastral_shadow_file_id) {
            $shadow = CadastralShadowFile::find($receipt->cadastral_shadow_file_id);

            return ['action' => 'kept', 'shadow' => $shadow, 'fileno' => $shadow?->full_number];
        }

        $source  = $this->sourceRow($receipt);
        $numbers = $this->numbersOf($receipt, $source);

        // 1. A live shadow file under any of its numbers.
        if ($shadow = $this->existingShadow($numbers)) {
            $receipt->cadastral_shadow_file_id = $shadow->id;
            $receipt->correspondence_status    = 'matched';

            return ['action' => 'matched', 'shadow' => $shadow, 'fileno' => $shadow->full_number];
        }

        // 2. The index already says it has one (flag-only, no shadow row).
        if ($fileno = $this->flaggedCorrespondence($source, $numbers)) {
            $receipt->correspondence_status = 'matched';

            return ['action' => 'matched', 'shadow' => null, 'fileno' => $fileno];
        }

        // 3. None anywhere: commission it, as the MLS-match screen would.
        $number    = $receipt->file_number;
        $fileRows  = $this->fileNumberRows($numbers);
        $now       = now();

        $shadow = CadastralShadowFile::create([
            'ref_number'   => 'CSF-' . $now->format('Ymd') . '-' . $now->format('His'),
            'full_number'  => $number,
            'file_name'    => $receipt->file_title,
            'plot_no'      => $receipt->prop_plot ?: ($source->plot_number ?? null),
            // District, LGA, State — never the plot, which has its own column.
            'location'     => $receipt->property_location ?: null,
            'lga'          => $receipt->prop_lga ?: ($source->lga ?? null),
            'tracking_id'  => $fileRows->pluck('tracking_id')->filter()->first() ?: ($source->tracking_id ?? null),
            'created_by'   => auth()->id(),
            'date_matched' => $now->toDateString(),
            'time_matched' => $now->toTimeString(),
            'is_deleted'   => 0,
        ]);

        if ($fileRows->isNotEmpty()) {
            $this->conn()->table('fileNumber')
                ->whereIn('id', $fileRows->pluck('id')->all())
                ->update(['csf' => '1']);
        }

        $this->conn()->table('file_indexings')
            ->where(function ($q) use ($receipt, $number) {
                $q->where('file_number', $number);
                if ($receipt->file_indexing_id) {
                    $q->orWhere('id', $receipt->file_indexing_id);
                }
            })
            ->update([
                'is_corresponding_file' => 1,
                'corresponding_fileno'  => $number,
            ]);

        $receipt->cadastral_shadow_file_id = $shadow->id;
        $receipt->correspondence_status    = 'created';

        return ['action' => 'created', 'shadow' => $shadow, 'fileno' => $number];
    }

    /** Audit the outcome after commit; a failure here never undoes the registration. */
    public function audit(CadastralFileReceipt $receipt, array $result): void
    {
        if ($result['action'] === 'kept') {
            return;
        }

        try {
            $this->audit->logAction(
                $result['action'] === 'created' ? 'CADASTRAL_CORRESPONDENCE_CREATED' : 'CADASTRAL_CORRESPONDENCE_MATCHED',
                'cadastral_file_receipt',
                $receipt->id,
                null,
                [
                    'correspondence_status'    => $receipt->correspondence_status,
                    'cadastral_shadow_file_id' => $result['shadow']?->id,
                    'shadow_ref'               => $result['shadow']?->ref_number,
                    'corresponding_fileno'     => $result['fileno'],
                ],
                "{$receipt->file_number} ({$receipt->receipt_ref})"
            );
        } catch (\Throwable $e) {
            Log::warning('Cadastral correspondence audit failed: ' . $e->getMessage(), ['receipt_id' => $receipt->id]);
        }
    }

    /* ------------------------------- look-ups ------------------------------- */

    /** The receipt's file_indexings row, by id (or by number for a receipt without one). */
    private function sourceRow(CadastralFileReceipt $receipt): ?object
    {
        $q = $this->conn()->table('file_indexings')
            ->select(['id', 'file_number', 'st_fillno', 'kangis_file_no', 'plot_number', 'lga',
                      'tracking_id', 'is_corresponding_file', 'corresponding_fileno']);

        return $receipt->file_indexing_id
            ? $q->where('id', $receipt->file_indexing_id)->first()
            : $q->where('file_number', $receipt->file_number)->first();
    }

    /**
     * Every spelling the file goes by: the receipt's number, each number column
     * of its index row, and the normalised form of each. Matched as a set, never
     * column to like column.
     *
     * @return string[]
     */
    public function numbersOf(CadastralFileReceipt $receipt, ?object $source = null): array
    {
        $raw = [$receipt->file_number];

        if ($source) {
            array_push($raw, $source->file_number, $source->st_fillno, $source->kangis_file_no);
        }

        return $this->spellings($raw);
    }

    /**
     * The correspondence a file number already has, or null. Read-only: the
     * same two checks ensureFor() makes before it creates anything, for the
     * legacy MLS-match screen (MlsFileNoMatchingController::store), which must
     * refuse rather than commission a second one.
     *
     * $alsoKnownAs: the file's other numbers the caller already holds (the
     * fileNumber row's kangisFileNo / NewKANGISFileNo / st_file_no).
     *
     * @return array{shadow: ?CadastralShadowFile, fileno: string}|null
     */
    public function existingForNumber(string $number, array $alsoKnownAs = []): ?array
    {
        $cols = ['id', 'file_number', 'st_fillno', 'kangis_file_no', 'is_corresponding_file', 'corresponding_fileno'];

        // file_number is unique and indexed; st_fillno is not, so it is read
        // only when the number is not a file_number (an ST number).
        $source = $this->conn()->table('file_indexings')->select($cols)
            ->where('file_number', $number)->first()
            ?? $this->conn()->table('file_indexings')->select($cols)
                ->where('st_fillno', $number)
                ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->first();

        $raw = array_merge([$number], $alsoKnownAs);
        if ($source) {
            array_push($raw, $source->file_number, $source->st_fillno, $source->kangis_file_no);
        }
        $numbers = $this->spellings($raw);

        if ($shadow = $this->existingShadow($numbers)) {
            return ['shadow' => $shadow, 'fileno' => $shadow->full_number];
        }

        if ($fileno = $this->flaggedCorrespondence($source, $numbers)) {
            return ['shadow' => null, 'fileno' => $fileno];
        }

        return null;
    }

    /** Each number, trimmed, plus its normalised form; blanks and repeats dropped. */
    private function spellings(array $raw): array
    {
        $out = [];
        foreach ($raw as $n) {
            $n = trim((string) $n);
            if ($n === '') continue;
            $out[] = $n;
            if ($norm = FileNumberFormat::normalise($n)) $out[] = $norm;
        }

        return array_values(array_unique($out));
    }

    /** The oldest live shadow file under any of these numbers. */
    public function existingShadow(array $numbers): ?CadastralShadowFile
    {
        if ($numbers === []) {
            return null;
        }

        return CadastralShadowFile::query()
            ->where('is_deleted', 0)
            ->whereIn('full_number', $numbers)
            ->orderBy('id')
            ->first();
    }

    /**
     * The correspondence number the index already records for this file, or
     * null. The source row first; then any other row filed under one of its
     * numbers (file_number is unique and indexed, so this is a seek).
     */
    private function flaggedCorrespondence(?object $source, array $numbers): ?string
    {
        $rows = collect($source ? [$source] : []);

        if ($numbers !== []) {
            $rows = $rows->merge($this->conn()->table('file_indexings')
                ->select(['file_number', 'is_corresponding_file', 'corresponding_fileno'])
                ->whereIn('file_number', $numbers)
                ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->get());
        }

        foreach ($rows as $row) {
            if ((bool) $row->is_corresponding_file || trim((string) $row->corresponding_fileno) !== '') {
                return trim((string) $row->corresponding_fileno) ?: trim((string) $row->file_number);
            }
        }

        return null;
    }

    /**
     * fileNumber rows carrying any of these numbers in any of their four number
     * columns. One file routinely has several rows, each holding the number in
     * a different column.
     */
    private function fileNumberRows(array $numbers)
    {
        if ($numbers === []) {
            return collect();
        }

        return collect($this->conn()->table('fileNumber')
            ->select(['id', 'tracking_id'])
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(function ($w) use ($numbers) {
                $w->whereIn('mlsfNo', $numbers)
                  ->orWhereIn('kangisFileNo', $numbers)
                  ->orWhereIn('NewKANGISFileNo', $numbers)
                  ->orWhereIn('st_file_no', $numbers);
            })
            ->limit(50)
            ->get());
    }
}
