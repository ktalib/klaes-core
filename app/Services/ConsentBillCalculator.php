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
     * The configured profile for a transaction type, or null when the type is
     * blank or unknown — in which case the bill falls back to the flat rates.
     *
     * @return array{label: string, consent_type: string, registration_rate: float,
     *               processing_fee: float, stamp_duty_rate: float, payee: string,
     *               template: string}|null
     */
    public static function transactionType(?string $key): ?array
    {
        $key = trim((string) $key);

        if ($key === '') {
            return null;
        }

        $profile = config('consent_bill.transaction_types.' . $key);

        return is_array($profile) ? $profile : null;
    }

    /**
     * The full name of a stamp duty payee, as the letter prints it.
     */
    public static function payeeName(?string $payee): string
    {
        $payee = trim((string) $payee);

        return (string) config('consent_bill.payees.' . $payee, $payee);
    }

    /**
     * Build the bill for a valuation amount.
     *
     * Each line is rounded to two decimals before the total is summed, so the
     * printed lines always add up to the printed total. Summing first and
     * rounding afterwards can leave the letter a kobo out.
     *
     * With a transaction type, its rates apply and stamp duty is computed but
     * kept OUT of bill_total: the letter acknowledges registration + processing
     * as the Ministry's bill and directs stamp duty to KIRS or FIRS separately.
     *
     * Without one, the bill is exactly what it was before transaction types
     * existed — flat configured rates, stamp duty inside the total — so a
     * consent captured that way recalculates to the same figures.
     *
     * $rates overrides a percentage the officer edited on the form:
     * ['registration' => 4.0, 'stamp_duty' => 2.5]. Either key may be absent,
     * in which case the type's (or the flat) rate applies.
     *
     * @return array{valuation_amount: float, stamp_duty_rate: float,
     *               stamp_duty_amount: float, registration_rate: float,
     *               registration_fee: float, processing_fee: float,
     *               bill_total: float, stamp_duty_payee: ?string}
     */
    public function compute(float $valuationAmount, ?string $transactionType = null, array $rates = []): array
    {
        // A negative or malformed valuation must not produce a negative demand.
        $valuationAmount = max(0.0, round($valuationAmount, 2));

        $profile = self::transactionType($transactionType);

        $stampDutyRate = (float) ($rates['stamp_duty']
            ?? $profile['stamp_duty_rate'] ?? config('consent_bill.rates.stamp_duty', 0));
        $registrationRate = (float) ($rates['registration']
            ?? $profile['registration_rate'] ?? config('consent_bill.rates.registration', 0));
        $processingFee = (float) ($profile['processing_fee'] ?? config('consent_bill.fixed_fees.processing', 0));

        $lines = [
            'stamp_duty_amount' => round($valuationAmount * $stampDutyRate / 100, 2),
            'registration_fee' => round($valuationAmount * $registrationRate / 100, 2),
            'processing_fee' => round($processingFee, 2),
        ];

        $billed = $profile
            ? $lines['registration_fee'] + $lines['processing_fee']
            : array_sum($lines);

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
            'bill_total' => round($billed, 2),
            'stamp_duty_payee' => $profile['payee'] ?? null,
        ]);
    }

    /**
     * Compute straight from a file number, resolving its valuation first.
     *
     * Returns the bill plus what it was based on, so a caller can tell the
     * difference between "no valuation on file" and "valued at zero" instead of
     * quietly billing zero for both.
     */
    public function computeForFile(string $fileNumber, ?string $transactionType = null, array $rates = []): array
    {
        $report = $this->valuationFor($fileNumber);
        $amount = $this->amountOf($report);

        return array_merge($this->compute($amount, $transactionType, $rates), [
            'valuation_report_id' => $report?->id,
            'valuation_reference' => $report?->file_number,
            'valuation_date' => $report?->inspection_date,
            'valuation_status' => $report?->status,
            'has_valuation' => (bool) $report,
            'bill_computed_at' => Carbon::now(),
        ]);
    }

    /**
     * A rate as it is printed: "5%", "2.5%", "0.375%" — never "5.00%".
     *
     * Four places, matching the decimal(8,4) rate columns: the mortgage stamp
     * duty is 0.375%, which two places would print as "0.38%".
     */
    public static function formatRate(float|int|string $rate): string
    {
        return rtrim(rtrim(number_format((float) $rate, 4, '.', ''), '0'), '.') . '%';
    }

    /**
     * A naira amount in words, as the consent letter writes the consideration:
     * "Six Hundred Thousand Naira Only", "Eighty-Two Thousand Five Hundred
     * Naira and Fifty Kobo Only".
     *
     * Written out by hand because the intl extension (NumberFormatter) is not
     * installed on this server.
     */
    public static function amountInWords(float|int|string $amount): string
    {
        $amount = round(max(0.0, (float) $amount), 2);
        $naira = (int) floor($amount);
        $kobo = (int) round(($amount - $naira) * 100);

        $words = ($naira === 0 ? 'Zero' : self::integerInWords($naira)) . ' Naira';

        if ($kobo > 0) {
            $words .= ' and ' . self::integerInWords($kobo) . ' Kobo';
        }

        return $words . ' Only';
    }

    private static function integerInWords(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $belowThousand = function (int $n) use ($ones, $tens): string {
            $parts = [];
            if ($n >= 100) {
                $parts[] = $ones[intdiv($n, 100)] . ' Hundred';
                $n %= 100;
            }
            if ($n >= 20) {
                $parts[] = $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
            } elseif ($n > 0) {
                $parts[] = $ones[$n];
            }
            return implode(' ', $parts);
        };

        $groups = [1000000000000 => 'Trillion', 1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'];
        $parts = [];
        foreach ($groups as $size => $name) {
            if ($n >= $size) {
                $parts[] = $belowThousand(intdiv($n, $size)) . ' ' . $name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = $belowThousand($n);
        }

        return implode(' ', $parts);
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
