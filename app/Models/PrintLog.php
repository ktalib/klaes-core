<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrintLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'print_logs';

    protected $fillable = [
        'reference_number',
        'document_type',
        'print_type',
        'status',
        'user_id'
    ];

    /**
     * Get the user who printed the document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A reset marker, not a print: written when a Super Admin puts a document's
     * print state back so it can be printed again (LandRofoController::resetPrint).
     *
     * `status` holds the scope — 'all', 'original' or 'office' — which says which
     * copies the reset covered. Nothing else in the app writes this type, and no
     * print_logs row is ever deleted, so the history of what went on paper stays
     * whole while "is this printed?" means "printed since the last reset".
     */
    public const TYPE_RESET = 'PrintReset';

    /**
     * A white copy run — the proof read before a document is issued.
     *
     * Recorded under a DOCUMENT TYPE OF ITS OWN, not merely a print_type of its
     * own, and that is the whole safety of it. Every "has this been printed?"
     * question in the app filters on document_type: the Printed tabs, the batch
     * queue, printedSinceReset(), $printDates. A proof logged under the document's
     * real type would answer yes to all of them and mark an unissued letter as
     * printed. Under its own type it is invisible to every one of them, and only
     * the code that asks for it by name can see it.
     *
     * whiteCopyType() derives it, so the two ends cannot drift apart.
     */
    public const TYPE_WHITE_COPY = 'WhiteCopy';

    /** The document_type a white copy of $documentType is logged under. */
    public static function whiteCopyType(string $documentType): string
    {
        return $documentType . ' White Copy';
    }

    /**
     * Record that a proof was run off. Never touches print counts, serials or any
     * official print state — see TYPE_WHITE_COPY.
     */
    public static function logWhiteCopy(string $documentType, ?string $referenceNumber, ?int $userId = null): void
    {
        $ref = trim((string) $referenceNumber);
        if ($ref === '') {
            return;
        }

        // A proof that fails to log must not take the document down with it: the
        // sheet is already rendering, and a lost log line is a lesser fault than a
        // 500 in place of the letter.
        try {
            static::create([
                'reference_number' => $ref,
                'document_type'    => self::whiteCopyType($documentType),
                'print_type'       => self::TYPE_WHITE_COPY,
                'status'           => 'White Copy',
                'user_id'          => $userId,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Which of $referenceNumbers have had a white copy run off SINCE their last
     * reset — the proofing stage done, so the official print is open and the proof
     * itself is spent.
     *
     * @param  string[]  $referenceNumbers
     * @return string[]  upper-cased, trimmed file numbers
     */
    public static function whiteCopyPrinted(string $documentType, array $referenceNumbers): array
    {
        $keys = array_values(array_unique(array_filter(array_map(
            fn ($r) => strtoupper(trim((string) $r)),
            $referenceNumbers
        ))));

        if (empty($keys)) {
            return [];
        }

        // Both the proofs and the resets, in one pass. A reset is written against
        // the OFFICIAL document type (see LandRofoController::resetPrint), so the
        // two live under different type names and have to be asked for by name.
        $rows = static::whereIn('document_type', [self::whiteCopyType($documentType), $documentType])
            ->where(function ($q) {
                $q->where('print_type', self::TYPE_WHITE_COPY)
                  ->orWhere('print_type', self::TYPE_RESET);
            })
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(reference_number)))'), $keys)
            ->get(['reference_number', 'document_type', 'print_type', 'created_at']);

        return $rows->groupBy(fn ($r) => strtoupper(trim((string) $r->reference_number)))
            ->filter(function ($forOneFile) use ($documentType) {
                // A reset restarts the whole workflow, so the proof has to be run
                // again: the letter may have been corrected in between, and the
                // fresh run is exactly what a proof exists to check. Only proofs
                // taken AFTER the last reset still count.
                $lastReset = $forOneFile
                    ->where('print_type', self::TYPE_RESET)
                    ->max('created_at');

                $proofs = $forOneFile->where('print_type', self::TYPE_WHITE_COPY);

                if ($proofs->isEmpty()) {
                    return false;
                }

                return $lastReset === null || $proofs->max('created_at') > $lastReset;
            })
            ->keys()
            ->all();
    }

    /** Which copies each reset scope reopens. */
    public const RESET_SCOPES = [
        'all'      => ['Original', 'Duplicate', 'Triplicate'],
        'original' => ['Original'],
        'office'   => ['Duplicate', 'Triplicate'],
    ];

    /**
     * The moment after which a print of $copy counts, for one document. Null when
     * that copy has never been reset.
     */
    public static function resetCutoff($logs, string $copy)
    {
        $cutoff = null;

        foreach ($logs as $log) {
            if ($log->print_type !== self::TYPE_RESET) {
                continue;
            }

            $covers = self::RESET_SCOPES[strtolower(trim((string) $log->status))] ?? [];
            if (!in_array($copy, $covers, true)) {
                continue;
            }

            if ($cutoff === null || $log->created_at > $cutoff) {
                $cutoff = $log->created_at;
            }
        }

        return $cutoff;
    }

    /** How many times $copy has been printed since it was last reset. */
    public static function countSinceReset($logs, string $copy): int
    {
        $cutoff = self::resetCutoff($logs, $copy);

        return $logs->filter(function ($log) use ($copy, $cutoff) {
            if ($log->status !== $copy || $log->print_type === self::TYPE_RESET) {
                return false;
            }
            return $cutoff === null || $log->created_at > $cutoff;
        })->count();
    }

    /**
     * Which of $referenceNumbers have had a sheet actually put through the printer
     * and not reset since — regardless of which pipeline printed it.
     *
     * printedSinceReset() answers a narrower question: printed under ONE named
     * print_type. That is right for the batch queue, which cares only about its own
     * runs, but wrong for "is there anything left to proofread": one letter can be
     * printed as 'Individual' from a row menu, 'Batch' from the Print Manager or
     * 'LandRofoBatch' from a batch run, and all three mean the same thing to an
     * officer holding the paper.
     *
     * It is also the only honest source for that question. rofo_print_count is
     * incremented by the single-print path alone, so a letter run off through a
     * batch has a full print history and a count of zero — which is exactly how a
     * printed letter can sit on the Not Printed tab.
     *
     * Scoped to the reference numbers asked about, so a paginated list costs one
     * query over the rows on screen rather than the whole table.
     *
     * $sinceReset decides what a reset means here:
     *
     *   true  — the ordinary reading, matching every other "is this printed?"
     *           question in the app: a Super Admin reset puts the document back to
     *           unprinted and it reads as never printed.
     *   false — has a sheet EVER gone through the printer, reset or not. Nothing
     *           uses this today: the White Copy asked it for a while, and the answer
     *           was wrong — a reset is a declaration that the letter will be printed
     *           again, and that fresh run wants proofreading like any other. Kept
     *           because "was this ever on paper" is a real question and the reset
     *           logic here is the only place that can answer it honestly.
     *
     * @param  string[]  $referenceNumbers
     * @return string[]  upper-cased, trimmed file numbers
     */
    public static function printedAnyhowSinceReset(string $documentType, array $referenceNumbers, bool $sinceReset = true): array
    {
        $keys = array_values(array_unique(array_filter(array_map(
            fn ($r) => strtoupper(trim((string) $r)),
            $referenceNumbers
        ))));

        if (empty($keys)) {
            return [];
        }

        $logs = static::where('document_type', $documentType)
            ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(reference_number)))'), $keys)
            ->get(['reference_number', 'print_type', 'status', 'created_at']);

        return $logs->groupBy(fn ($log) => strtoupper(trim((string) $log->reference_number)))
            ->filter(function ($forOneFile) use ($sinceReset) {
                // Any real print at all, resets disregarded.
                if (!$sinceReset) {
                    return $forOneFile->contains(fn ($l) => $l->print_type !== self::TYPE_RESET);
                }

                foreach (['Original', 'Duplicate', 'Triplicate'] as $copy) {
                    if (self::countSinceReset($forOneFile, $copy) > 0) {
                        return true;
                    }
                }

                // A run logged with no copy named (older rows) counts unless
                // something has been reset since.
                $unnamed = $forOneFile->filter(fn ($l) => $l->print_type !== self::TYPE_RESET
                    && !in_array($l->status, ['Original', 'Duplicate', 'Triplicate'], true));
                if ($unnamed->isEmpty()) {
                    return false;
                }

                $lastReset = $forOneFile->where('print_type', self::TYPE_RESET)->max('created_at');

                return $lastReset === null || $unnamed->max('created_at') > $lastReset;
            })
            ->keys()
            ->all();
    }

    /**
     * Reference numbers of $documentType printed under $printType and NOT reset
     * since — upper-cased and trimmed, ready to match against a file number.
     */
    public static function printedSinceReset(string $documentType, string $printType): array
    {
        $logs = static::where('document_type', $documentType)
            ->whereIn('print_type', [$printType, self::TYPE_RESET])
            ->get(['reference_number', 'print_type', 'status', 'created_at']);

        $key = fn ($r) => strtoupper(trim((string) $r));

        return $logs->groupBy(fn ($log) => $key($log->reference_number))
            ->filter(function ($forOneFile) {
                // Printed if any copy still counts as printed after the resets.
                foreach (['Original', 'Duplicate', 'Triplicate'] as $copy) {
                    if (self::countSinceReset($forOneFile, $copy) > 0) {
                        return true;
                    }
                }

                // A run logged with no copy named at all (older rows) counts as a
                // print unless something has been reset since.
                $unnamed = $forOneFile->filter(fn ($l) => $l->print_type !== self::TYPE_RESET
                    && !in_array($l->status, ['Original', 'Duplicate', 'Triplicate'], true));
                if ($unnamed->isEmpty()) {
                    return false;
                }

                $lastReset = $forOneFile->where('print_type', self::TYPE_RESET)->max('created_at');

                return $lastReset === null || $unnamed->max('created_at') > $lastReset;
            })
            ->keys()
            ->all();
    }
}
