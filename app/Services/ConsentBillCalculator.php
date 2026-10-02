<?php

namespace App\Services;

use App\Models\ValuationReport;
use Illuminate\Support\Carbon;

/**
 * The one place a consent bill is calculated.
 *
 * The wizard mirrors these numbers on screen for the operator, but nothing the
 * browser posts is trusted: the controller calls compute() again on save and
 * stores what comes back. A client that posts its own total gets it discarded.
 */
class ConsentBillCalculator
{
    /**
     * The valuation a consent should be billed against.
     *
     * Where a file has been valued more than once, the most recent eligible
     * report wins. A re-evaluated report supersedes its own original figure,
     * which is why re_value_figures is preferred over value_figures.
     */
    public function valuationFor(string $fileNumber): ?ValuationReport
    {
        $fileNumber = trim($fileNumber);

        if ($fileNumber === '') {
            return null;
        }

        return ValuationReport::where('file_number', $fileNumber)
            ->whereIn('status', (array) config('consent_bill.eligible_valuation_statuses', []))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The effective Section E amount for a report, as a float.
     *
     * value_figures is stored as display text ("50,000,000.00"), so it is
     * stripped of everything but digits and the decimal point before use —
     * a raw (float) cast would silently read "50,000,000.00" as 50.
     */
    public function amountOf(?ValuationReport $report): float
    {
        if (!$report) {
            return 0.0;
        }

        foreach ([$report->re_value_figures, $report->value_figures] as $candidate) {
            $clean = preg_replace('/[^0-9.]/', '', (string) $candidate);

            if ($clean !== '' && is_numeric($clean) && (float) $clean > 0) {
                return round((float) $clean, 2);
            }
        }

        return 0.0;
    }

    /**
     * Build the bill for a valuation amount.
     *
     * Each line is rounded to two decimals before the total is summed, so the
     * printed lines always add up to the printed total. Summing first and
     * rounding afterwards can leave the letter a kobo out.
     *
     * @return array{valuation_amount: float, stamp_duty_rate: float,
     *               stamp_duty_amount: float, registration_rate: float,
     *               registration_fee: float, processing_fee: float,
     *               bill_total: float}
     */
    public function compute(float $valuationAmount): array
    {
        // A negative or malformed valuation must not produce a negative demand.
        $valuationAmount = max(0.0, round($valuationAmount, 2));

        $stampDutyRate = (float) config('consent_bill.rates.stamp_duty', 0);
        $registrationRate = (float) config('consent_bill.rates.registration', 0);

        $lines = [
            'stamp_duty_amount' => round($valuationAmount * $stampDutyRate / 100, 2),
            'registration_fee' => round($valuationAmount * $registrationRate / 100, 2),
            'processing_fee' => round((float) config('consent_bill.fixed_fees.processing', 0), 2),
        ];

        return array_merge([
            'valuation_amount' => $valuationAmount,
            'stamp_duty_rate' => $stampDutyRate,
            'registration_rate' => $registrationRate,
            // No longer billed. Written as null rather than left out, so a save
            // clears whatever an earlier bill had stored in them.
            'cgt_rate' => null,
            'cgt_amount' => null,
            'assignment_fee' => null,
        ], $lines, [
            'bill_total' => round(array_sum($lines), 2),
        ]);
    }

    /**
     * Compute straight from a file number, resolving its valuation first.
     *
     * Returns the bill plus what it was based on, so a caller can tell the
     * difference between "no valuation on file" and "valued at zero" instead of
     * quietly billing zero for both.
     */
    public function computeForFile(string $fileNumber): array
    {
        $report = $this->valuationFor($fileNumber);
        $amount = $this->amountOf($report);

        return array_merge($this->compute($amount), [
            'valuation_report_id' => $report?->id,
            'valuation_reference' => $report?->file_number,
            'valuation_date' => $report?->inspection_date,
            'valuation_status' => $report?->status,
            'has_valuation' => (bool) $report,
            'bill_computed_at' => Carbon::now(),
        ]);
    }

    /**
     * A rate as it is printed: "5%", "2.5%" — never "5.00%".
     */
    public static function formatRate(float|int|string $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.') . '%';
    }

    /**
     * The fee lines in the order the consent letter prints them.
     *
     * A line keeps the rate its bill was assessed under, so a reprint matches
     * the figures beside it. Where no rate was stored — a consent captured
     * before billing existed, or one with no valuation yet — the configured
     * rate is shown instead: the schedule is a statement of the rates that
     * apply, and "0%" would be wrong rather than merely unknown.
     */
    public function linesForLetter(array $bill): array
    {
        return [
            [
                'label' => 'Stamp Duty (' . self::formatRate(
                    $bill['stamp_duty_rate'] ?? null ?: config('consent_bill.rates.stamp_duty')
                ) . ')',
                'amount' => $bill['stamp_duty_amount'] ?? 0,
            ],
            [
                'label' => 'Registration Fee (' . self::formatRate(
                    $bill['registration_rate'] ?? null ?: config('consent_bill.rates.registration')
                ) . ')',
                'amount' => $bill['registration_fee'] ?? 0,
            ],
            ['label' => 'Processing Fee', 'amount' => $bill['processing_fee'] ?? 0],
        ];
    }
}
