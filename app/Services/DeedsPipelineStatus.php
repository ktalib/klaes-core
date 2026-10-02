<?php

namespace App\Services;

use App\Models\ConsentApplication;
use App\Support\DeedsPipelineGates;
use Illuminate\Support\Facades\DB;

/**
 * Where a file stands in the Deeds pipeline: Valuation -> Consent -> Print -> Registration.
 *
 * One resolver for the whole pipeline so the gates and the workflow strip can
 * never disagree about what has been done. Anything that needs to know whether
 * a stage is complete — the consent store rule, the capture gate, the three
 * index screens — reads it from here.
 *
 * Read-only: it resolves and reports, it never writes and never decides policy.
 * Whether a missing stage blocks an action is config, applied by the caller.
 */
class DeedsPipelineStatus
{
    public const STAGE_VALUATION = 'valuation';
    public const STAGE_CONSENT = 'consent';
    public const STAGE_PRINT = 'print';
    public const STAGE_REGISTRATION = 'registration';

    public function __construct(private ConsentBillCalculator $bills)
    {
    }

    /**
     * What the consent letter for a dealing is actually called, so the strip
     * names the document an officer has to go and produce rather than a generic
     * "consent". Falls back to a plain wording for anything unmapped.
     */
    private function consentDocumentName(?string $consentType): string
    {
        $names = [
            'Assignment' => '"Consent to Assign"',
            'ST Assignment' => '"Consent to Assign"',
            'Gift' => '"Consent to Gift"',
            'Mortgage' => '"Consent to Mortgage"',
        ];

        return $names[$consentType] ?? 'Consent';
    }

    /**
     * Whether a consent of this type is valued before it is captured.
     *
     * Only a sale is. A Gift passes no consideration and a Mortgage is secured
     * on the property rather than sold, so neither carries a valuation and
     * neither is held up waiting for one.
     *
     * A null or blank type means "any consent on this file" — the Valuation
     * screen and the list views, neither of which is about one dealing. Those
     * are treated as requiring a valuation, so a sale is never quietly reported
     * as exempt just because no type was supplied.
     */
    public function valuationRequiredFor(?string $consentType): bool
    {
        if ($consentType === null || trim($consentType) === '') {
            return true;
        }

        $required = (array) config('deeds_pipeline.valuation_required_for', ['Assignment']);

        foreach ($required as $type) {
            if (strcasecmp(trim((string) $type), trim($consentType)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * How strictly a named gate is enforced: 'block', 'warn' or 'off'.
     *
     * Resolved through DeedsPipelineGates so what System Admin -> Configurable
     * Entries -> Valuation - Consent - Registration shows is what the gates
     * actually do. Unset gates still come from config/deeds_pipeline.php.
     */
    public function gateMode(string $gate): string
    {
        return DeedsPipelineGates::mode($gate);
    }

    /**
     * The consent type that authorises an instrument, or null when the
     * instrument is not consent-gated at all.
     */
    public function consentTypeFor(?string $instrumentType): ?string
    {
        $needle = strtolower(trim((string) $instrumentType));

        if ($needle === '') {
            return null;
        }

        foreach ((array) config('deeds_pipeline.consent_instruments', []) as $instrument => $consentType) {
            // Compared loosely because live rows carry both the full name and
            // shortened spellings of the same instrument.
            if (str_contains($needle, strtolower($instrument)) || str_contains(strtolower($instrument), $needle)) {
                return $consentType;
            }
        }

        return null;
    }

    /**
     * The whole pipeline for one file.
     *
     * $consentType narrows the consent and registration stages to one kind of
     * dealing. Left null, any consent on the file satisfies the consent stage —
     * which is what the Valuation and Consent screens want, since neither is
     * about a particular instrument.
     *
     * @return array<string, array<string, mixed>>
     */
    public function forFile(?string $fileNumber, ?string $consentType = null): array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return [
                self::STAGE_VALUATION => $this->stage(false, 'No file number'),
                self::STAGE_CONSENT => $this->stage(false, 'No file number'),
                self::STAGE_PRINT => $this->stage(false, 'No file number'),
                self::STAGE_REGISTRATION => $this->stage(false, 'No file number'),
            ];
        }

        $consent = $this->consentStage($fileNumber, $consentType);

        return [
            self::STAGE_VALUATION => $this->valuationStage($fileNumber, $consentType),
            self::STAGE_CONSENT => $consent,
            self::STAGE_PRINT => $this->printStage($consent, $consentType),
            self::STAGE_REGISTRATION => $this->registrationStage($fileNumber, $consentType),
        ];
    }

    /**
     * The pipeline for many files at once.
     *
     * Three queries for the whole set rather than four per file: the index
     * screens list hundreds of rows, and forFile() in a loop would put a
     * thousand round trips behind a single page load.
     *
     * Returns a map keyed by the UPPERCASED, trimmed file number.
     *
     * @param  iterable<string>  $fileNumbers
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function forFiles(iterable $fileNumbers): array
    {
        $keys = [];
        foreach ($fileNumbers as $file) {
            $key = strtoupper(trim((string) $file));
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
        $keys = array_keys($keys);

        if ($keys === []) {
            return [];
        }

        $eligible = (array) config('consent_bill.eligible_valuation_statuses', []);

        // Newest row per file wins, matching the single-file resolvers above.
        $latest = function ($rows, string $column) {
            $out = [];
            foreach ($rows as $row) {
                $key = strtoupper(trim((string) $row->{$column}));
                if ($key !== '' && !isset($out[$key])) {
                    $out[$key] = $row;
                }
            }
            return $out;
        };

        $valuations = $latest(
            DB::connection('sqlsrv')->table('valuation_reports')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $keys)
                ->when($eligible !== [], fn($q) => $q->whereIn('status', $eligible))
                ->orderByDesc('id')
                ->get(['id', 'file_number', 'value_figures', 're_value_figures', 'inspection_date', 'status']),
            'file_number'
        );

        $consents = $latest(
            DB::connection('sqlsrv')->table('consent_applications')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(file_number)))'), $keys)
                ->orderByDesc('created_at')
                ->get(['id', 'file_number', 'consent_type', 'application_tracking_no', 'print_count', 'created_at']),
            'file_number'
        );

        $captures = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->where(function ($q) use ($keys) {
                $q->whereIn(DB::raw('UPPER(LTRIM(RTRIM(mlsFNo)))'), $keys)
                    ->orWhereIn(DB::raw('UPPER(LTRIM(RTRIM(temp_fileno)))'), $keys);
            })
            ->orderByDesc('id')
            ->get(['id', 'mlsFNo', 'temp_fileno', 'instrument_type', 'registration_number', 'reg_no', 'reg_date', 'is_deed_registered']);

        $byFile = [];
        foreach ($captures as $row) {
            foreach ([$row->mlsFNo, $row->temp_fileno] as $candidate) {
                $key = strtoupper(trim((string) $candidate));
                if ($key !== '' && in_array($key, $keys, true)) {
                    $byFile[$key][] = $row;
                }
            }
        }

        $out = [];
        foreach ($keys as $key) {
            $report = $valuations[$key] ?? null;
            $amount = 0.0;
            if ($report) {
                foreach ([$report->re_value_figures, $report->value_figures] as $candidate) {
                    $clean = preg_replace('/[^0-9.]/', '', (string) $candidate);
                    if ($clean !== '' && is_numeric($clean) && (float) $clean > 0) {
                        $amount = round((float) $clean, 2);
                        break;
                    }
                }
            }

            $consent = $consents[$key] ?? null;
            $rows = $byFile[$key] ?? [];
            $registered = null;
            foreach ($rows as $row) {
                if (!empty($row->registration_number) || !empty($row->reg_no) || (int) ($row->is_deed_registered ?? 0) === 1) {
                    $registered = $row;
                    break;
                }
            }

            $out[$key] = [
                // The file's own latest consent decides whether a valuation was
                // ever required here. Without it a Gift or a Mortgage row would
                // show Valuation outstanding on the list screens for work that
                // is not part of its workflow at all.
                self::STAGE_VALUATION => !$this->valuationRequiredFor($consent->consent_type ?? null)
                    ? $this->stage(true, 'Not required for a ' . trim((string) $consent->consent_type) . ' consent', [
                        'not_required' => true,
                    ])
                    : ($report && $amount > 0
                        ? $this->stage(true, 'Valued', ['report_id' => $report->id, 'amount' => $amount])
                        : $this->stage(false, $report
                            ? 'The Valuation Report for this File carries no amount in Section E'
                            : 'No Valuation Report has been captured for this Consent (Application)')),
                self::STAGE_CONSENT => $consent
                    ? $this->stage(true, 'Consent captured', [
                        'consent_id' => $consent->id,
                        'consent_type' => $consent->consent_type,
                        'print_count' => (int) $consent->print_count,
                        'printed' => ((int) $consent->print_count) > 0,
                    ])
                    : $this->stage(false, 'No Consent for this File has been captured'),
                self::STAGE_PRINT => $consent && (int) $consent->print_count > 0
                    ? $this->stage(true, 'Consent printed', [
                        'consent_id' => $consent->id,
                        'print_count' => (int) $consent->print_count,
                    ])
                    : $this->stage(false, $consent
                        ? 'Consent captured but not yet printed'
                        : 'No Consent has been captured to print'),
                self::STAGE_REGISTRATION => $registered
                    ? $this->stage(true, 'Registered', [
                        'registration_number' => $registered->registration_number ?: $registered->reg_no,
                        'instrument_type' => $registered->instrument_type,
                    ])
                    : $this->stage(false, $rows === []
                        ? 'No Instrument has been Registered against this FileNo (Application)'
                        : 'Instrument captured for this FileNo, awaiting Registration', [
                        'captured' => $rows !== [],
                    ]),
            ];
        }

        return $out;
    }

    /**
     * The valuation stage. Resolved through ConsentBillCalculator so the stage
     * is complete on exactly the reports a bill may be raised against — an
     * ineligible status must not show as done and then fail to bill.
     */
    private function valuationStage(string $fileNumber, ?string $consentType = null): array
    {
        // A Gift or a Mortgage is never valued, so the stage is satisfied
        // rather than left outstanding. Flagged not_required so the strip can
        // say so plainly instead of showing a green tick for work nobody did.
        if (!$this->valuationRequiredFor($consentType)) {
            return $this->stage(true, 'Not required for a ' . trim((string) $consentType) . ' consent', [
                'not_required' => true,
            ]);
        }

        $report = $this->bills->valuationFor($fileNumber);

        if (!$report) {
            // No report on file, but an authorised officer may have accepted a
            // valuation done before KLAES existed. Reported as done so the strip
            // tells the truth about the file, and flagged manual so it is never
            // read as a report the Ministry holds.
            //
            // NOTE for callers enforcing the gate: a manual acceptance belongs to
            // the consent it was recorded on. Check empty($stage['manual'])
            // before treating this as satisfying Valuation-before-Consent for a
            // NEW consent, or one officer's override would silently clear the
            // gate for everyone else on that file.
            $manual = ConsentApplication::whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($fileNumber)])
                ->where('manual_valuation', 1)
                ->orderByDesc('manual_valuation_at')
                ->first();

            if ($manual) {
                return $this->stage(true, 'Manual valuation accepted by ' . $manual->manual_valuation_by
                    . ($manual->manual_valuation_at
                        ? ' on ' . $manual->manual_valuation_at->format('d/m/Y')
                        : ''), [
                    'manual' => true,
                    'amount' => (float) $manual->valuation_amount,
                    'reason' => $manual->manual_valuation_reason,
                    'reference' => $manual->manual_valuation_ref,
                ]);
            }

            return $this->stage(false, 'No Valuation Report has been captured for this Consent (Application)');
        }

        $amount = $this->bills->amountOf($report);

        // A report with nothing in Section E cannot be billed against, so the
        // stage is reported as incomplete rather than quietly passing.
        if ($amount <= 0) {
            return $this->stage(false, 'The Valuation Report for this File carries no amount in Section E', [
                'report_id' => $report->id,
                'inspection_date' => $report->inspection_date,
            ]);
        }

        return $this->stage(true, 'Valued', [
            'report_id' => $report->id,
            'amount' => $amount,
            'inspection_date' => $report->inspection_date,
            'status' => $report->status,
        ]);
    }

    /**
     * The consent stage. Any consent on the file counts unless a type is given,
     * in which case the configured group for that type is accepted — an
     * Assignment consent backs a Deed of Gift, a Mortgage consent does not.
     */
    private function consentStage(string $fileNumber, ?string $consentType): array
    {
        $query = ConsentApplication::whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($fileNumber)]);

        if ($consentType !== null) {
            $accepted = (array) config("deeds_pipeline.consent_groups.{$consentType}", [$consentType]);
            $query->whereIn('consent_type', $accepted);
        }

        $consent = $query->orderByDesc('created_at')->first();

        if (!$consent) {
            return $this->stage(false, 'No ' . $this->consentDocumentName($consentType)
                . ' for this File has been captured');
        }

        return $this->stage(true, 'Consent captured', [
            'consent_id' => $consent->id,
            'consent_type' => $consent->consent_type,
            'tracking_no' => $consent->application_tracking_no,
            'print_count' => (int) $consent->print_count,
            'printed' => ((int) $consent->print_count) > 0,
            'captured_at' => $consent->created_at,
        ]);
    }

    /**
     * A captured consent must be printed before the authorised instrument is
     * registered. print_count is increased only by ConsentApplicationController
     * after the document has been sent to print, so it is the workflow's record
     * of that physical hand-off.
     *
     * @param array<string, mixed> $consent
     */
    private function printStage(array $consent, ?string $consentType): array
    {
        if (!($consent['done'] ?? false)) {
            return $this->stage(false, 'No ' . $this->consentDocumentName($consentType)
                . ' has been captured to print');
        }

        $count = (int) ($consent['print_count'] ?? 0);

        if ($count < 1) {
            return $this->stage(false, 'Consent captured but not yet printed', [
                'consent_id' => $consent['consent_id'] ?? null,
                'consent_type' => $consent['consent_type'] ?? null,
                'print_count' => 0,
            ]);
        }

        return $this->stage(true, 'Consent printed', [
            'consent_id' => $consent['consent_id'] ?? null,
            'consent_type' => $consent['consent_type'] ?? null,
            'print_count' => $count,
        ]);
    }

    /**
     * The registration stage — a captured instrument on this file that has
     * actually been registered, not merely captured.
     */
    private function registrationStage(string $fileNumber, ?string $consentType): array
    {
        $upper = strtoupper($fileNumber);

        $query = DB::connection('sqlsrv')->table('instrument_capture')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = ?', [$upper])
                    ->orWhereRaw('UPPER(LTRIM(RTRIM(temp_fileno))) = ?', [$upper]);
            });

        // Narrow to the instruments this consent type authorises, so a
        // registered mortgage does not report an assignment as registered.
        if ($consentType !== null) {
            $instruments = array_keys(array_filter(
                (array) config('deeds_pipeline.consent_instruments', []),
                fn($type) => $type === $consentType
            ));

            if ($instruments !== []) {
                $query->where(function ($q) use ($instruments) {
                    foreach ($instruments as $instrument) {
                        $q->orWhere('instrument_type', 'LIKE', '%' . $instrument . '%');
                    }
                });
            }
        }

        $captures = $query->orderByDesc('id')
            ->get(['id', 'instrument_type', 'registration_number', 'reg_no', 'reg_date', 'is_deed_registered']);

        if ($captures->isEmpty()) {
            return $this->stage(false, 'No Instrument has been Registered against this FileNo (Application)');
        }

        $registered = $captures->first(fn($c) => !empty($c->registration_number)
            || !empty($c->reg_no)
            || (int) ($c->is_deed_registered ?? 0) === 1);

        if (!$registered) {
            return $this->stage(false, 'Instrument captured for this FileNo, awaiting Registration', [
                'captured' => true,
                'capture_count' => $captures->count(),
                'instrument_type' => $captures->first()->instrument_type,
            ]);
        }

        return $this->stage(true, 'Registered', [
            'captured' => true,
            'capture_id' => $registered->id,
            'instrument_type' => $registered->instrument_type,
            'registration_number' => $registered->registration_number ?: $registered->reg_no,
            'reg_date' => $registered->reg_date,
        ]);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function stage(bool $done, string $message, array $detail = []): array
    {
        return array_merge([
            'done' => $done,
            'message' => $message,
        ], $detail);
    }
}
