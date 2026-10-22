<?php

namespace App\Services\Billing\Modules;

use App\Models\BillMaster;
use App\Models\BillPayment;
use App\Services\Billing\BillMasterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ST initial bills (Primary / PuA / SuA) → Bill Master (phase E,
 * docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 * ST fees are paid BEFORE the application and the form records the receipts,
 * so the bill is mirrored as already paid (user decision, 2026-10-19): one
 * line per fee, and each captured receipt becomes a successful payment
 * (method "captured", receipt no. as the Transaction ID). The official RCT-
 * receipt can then be generated under REV-M. The ST forms are unchanged.
 */
class StInitialBillMirror
{
    public const METHOD_CAPTURED = 'captured';

    public function __construct(private BillMasterService $bills)
    {
    }

    /** A primary (mother) application was saved. Never throws into the save. */
    public function forPrimary(int $applicationId): ?BillMaster
    {
        return $this->guarded(function () use ($applicationId) {
            $app = DB::connection('sqlsrv')->table('mother_applications')->where('id', $applicationId)->first();
            if (!$app) {
                return null;
            }

            $fees = [
                ['APPLICATION', 'Application fee', $app->application_fee ?? null, $app->application_fee_receipt_number ?? null, $app->application_fee_payment_date ?? null],
                ['PROCESSING', 'Processing fee', $app->processing_fee ?? null, $app->processing_fee_receipt_number ?? null, $app->processing_fee_payment_date ?? null],
                ['SITE_PLAN', 'Site plan fee', $app->site_plan_fee ?? null, $app->site_plan_fee_receipt_number ?? null, $app->site_plan_fee_payment_date ?? null],
            ];

            return $this->mirror('initial_primary', 'mother_applications', $app, $fees, [
                'file_number' => $app->fileno ?: ($app->np_fileno ?? null),
                'plot_no' => $app->property_plot_no ?? null,
                'district' => $app->property_district ?? null,
                'lga' => $app->property_lga ?? null,
            ]);
        });
    }

    /** A unit application (PuA or SuA) was saved. Never throws into the save. */
    public function forUnit(int $subApplicationId): ?BillMaster
    {
        return $this->guarded(function () use ($subApplicationId) {
            $app = DB::connection('sqlsrv')->table('subapplications')->where('id', $subApplicationId)->first();
            if (!$app) {
                return null;
            }

            $isSua = (int) ($app->is_sua_unit ?? 0) === 1 || strtoupper((string) ($app->unit_type ?? '')) === 'SUA';
            $fees = [
                ['APPLICATION', 'Application fee', $app->application_fee ?? null, $app->application_fee_receipt_no ?? null, $app->application_fee_payment_date ?? null],
                ['PROCESSING', 'Processing fee', $app->processing_fee ?? null, $app->processing_fee_receipt_no ?? null, $app->processing_fee_payment_date ?? null],
                ['SURVEY', 'Site plan / survey fee', $app->site_plan_fee ?? null, $app->survey_fee_receipt_no ?? null, $app->survey_fee_payment_date ?? null],
            ];

            return $this->mirror($isSua ? 'initial_sua' : 'initial_pua', 'subapplications', $app, $fees, [
                'file_number' => $app->fileno ?: null,
                'plot_no' => null,
                'district' => $app->unit_district ?? ($app->address_district ?? null),
                'lga' => $app->unit_lga ?? ($app->address_lga ?? null),
            ]);
        });
    }

    /**
     * @param list<array{0: string, 1: string, 2: mixed, 3: ?string, 4: mixed}> $fees code, label, amount, receipt no., paid date
     */
    private function mirror(string $type, string $table, object $app, array $fees, array $property): ?BillMaster
    {
        // The ST fee columns are text ("10,000.00"), so read them as amounts first.
        $fees = array_map(fn ($f) => [$f[0], $f[1], $this->amount($f[2]), $f[3], $f[4]], $fees);

        $lines = [];
        foreach ($fees as [$code, $label, $amount]) {
            if ($amount > 0) {
                $lines[] = ['code' => $code, 'name' => $label, 'amount' => $amount];
            }
        }
        if (!$lines) {
            return null; // no fee on the application, nothing to bill
        }

        $existing = BillMaster::query()->where('source_table', $table)->where('source_id', $app->id)->exists();
        $file = $property['file_number'] ? $this->bills->fileDetails($property['file_number']) : [];
        $name = trim(($app->applicant_type ?? '') === 'corporate' && !empty($app->corporate_name)
            ? $app->corporate_name
            : trim(($app->first_name ?? '') . ' ' . ($app->surname ?? ''))) ?: ($app->corporate_name ?? null);
        $district = $property['district'] ?: ($file['district'] ?? null);
        $lga = $property['lga'] ?: ($file['lga'] ?? null);
        $actor = (string) (auth()->user()->name ?? auth()->id() ?? 'system');

        $bill = $this->bills->mirror($type, $table, (int) $app->id, [
            'file_number' => $property['file_number'],
            'file_indexing_id' => $file['file_indexing_id'] ?? null,
            'payer_name' => $name ?: ($file['payer_name'] ?? null),
            'plot_no' => $property['plot_no'] ?: ($file['plot_no'] ?? null),
            'district' => $district,
            'lga' => $lga,
            'location' => BillMasterService::location($district, $lga),
            'description' => 'ST initial bill',
        ], $lines, $actor);

        // The fees were paid before the application: record the captured receipts once.
        if (!$existing) {
            $general = trim((string) ($app->receipt_number ?? ''));
            $generalDate = $app->payment_date ?? null;
            $byReceipt = [];
            foreach ($fees as [$code, $label, $amount, $receipt, $date]) {
                if ($amount <= 0) {
                    continue;
                }
                $receipt = trim((string) $receipt) ?: $general;
                if ($receipt === '') {
                    continue; // no receipt captured for this fee: it stays unpaid in REV-M
                }
                $byReceipt[$receipt]['amount'] = ($byReceipt[$receipt]['amount'] ?? 0) + (float) $amount;
                $byReceipt[$receipt]['date'] = $byReceipt[$receipt]['date'] ?? ($date ?: $generalDate);
                $byReceipt[$receipt]['fees'][] = $label;
            }

            foreach ($byReceipt as $receipt => $p) {
                BillPayment::create([
                    'bill_ref_id' => $bill->bill_ref_id,
                    'method' => self::METHOD_CAPTURED,
                    'transaction_id' => $receipt,
                    'amount' => round($p['amount'], 2),
                    'paid_at' => $this->date($p['date']) ?? now(),
                    'status' => BillPayment::STATUS_SUCCESSFUL,
                    'recorded_by' => $actor,
                    'notes' => 'Paid before the ST application; receipt captured on the form (' . implode(', ', $p['fees']) . ').',
                ]);
            }
            $this->bills->refreshTotals($bill, $actor);
        }

        return $bill->refresh();
    }

    /** "₦10,000.00", "10000", null → 10000.0 / 0.0 */
    private function amount($value): float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function date($value): ?\Illuminate\Support\Carbon
    {
        try {
            return $value ? \Illuminate\Support\Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function guarded(callable $fn): ?BillMaster
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::warning('ST initial bill not mirrored to Bill Master', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
