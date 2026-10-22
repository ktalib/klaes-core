<?php

namespace App\Services\Billing;

use App\Models\BillingType;
use App\Models\BillItem;
use App\Models\BillMaster;
use App\Models\BillMasterType;
use App\Models\BillPayment;
use App\Models\RevenueItem;
use App\Models\SpaBillItem;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The KLAES REV-M bill flow, the same for every Bill Master type
 * (docs/plans/KLAES_REVM_Bill_Master_Plan.md, section 3):
 *
 *   generate  file picked → items from the type's Bill Settings → BILL- ref → unpaid
 *   pay       Paystack (existing keys) or POS (Transaction ID recorded against the
 *             Bill Ref ID); Bank Transfer is shown but disabled (config/billing.php)
 *   totals    amount_paid / status / latest Transaction ID worked out from payments
 *
 * The official receipt is a separate step, under Receipting.
 */
class BillMasterService
{
    public function __construct(
        private CadastralRegistryLookup $lookup,
        private BillPhoneFinder $phones,
        private BillNotifier $notifier
    ) {
    }

    // ---- File details ------------------------------------------------------

    /**
     * What the records hold about a file, for backfilling a bill. Location is
     * district + LGA + state, never the plot number.
     */
    public function fileDetails(string $fileNumber): array
    {
        $row = $this->lookup->indexedFile($fileNumber);

        $district = trim((string) ($row->district ?? ''));
        $lga = trim((string) ($row->lga ?? ''));

        return [
            'file_number' => $row->file_number ?? trim($fileNumber),
            'file_indexing_id' => $row->id ?? null,
            'indexed' => (bool) $row,
            'payer_name' => trim((string) ($row->file_title ?? '')) ?: null,
            'plot_no' => trim((string) ($row->plot_number ?? '')) ?: null,
            'district' => $district ?: null,
            'lga' => $lga ?: null,
            'location' => implode(', ', array_filter([$district, $lga, ($district || $lga) ? 'Kano State' : null])) ?: null,
            'plot_size' => $row->plot_size ?? null,
        ] + $this->phoneFor($fileNumber);
    }

    /** The payer's phone found in the file's records (File Indexing, earlier bills, applications). */
    public function phoneFor(?string $fileNumber): array
    {
        $found = $this->phones->find($fileNumber);

        return ['payer_phone' => $found['phone'], 'phone_source' => $found['source']];
    }

    // ---- Items -------------------------------------------------------------

    /**
     * The charges a bill of $type carries today, from its Bill Settings sub-tab.
     * Each line: code, name, amount (null when the officer enters it), input.
     *
     * @return array{formula: string, items: list<array{code: string, name: string, amount: ?float, input: bool}>, zero: list<string>}
     * @throws WorkflowGuardException
     */
    public function itemsFor(BillMasterType $type): array
    {
        $tab = $type->settings_tab ?: $type->code;

        if ($tab === 'land_use_charge') {
            throw WorkflowGuardException::because('Land Use Charge billing is on hold.');
        }

        if ($tab === 'contravention_bill') {
            $items = SpaBillItem::billable() // already ordered, as SPAS bills it
                ->map(fn ($i) => ['code' => 'SPA_' . $i->id, 'name' => $i->name, 'amount' => (float) $i->amount, 'input' => false])
                ->values()->all();

            return ['formula' => '', 'items' => $items, 'zero' => []];
        }

        $billingType = BillingType::query()->with(['items.revenueItem'])->where('code', $tab)->first();
        if (!$billingType || $billingType->items->isEmpty()) {
            throw WorkflowGuardException::because("No bill items are set up for {$type->name}. Set them up in Configurable Entries → Bill Settings.");
        }
        if (!$billingType->isEffective()) {
            throw WorkflowGuardException::because("{$type->name} bill items are switched off in Bill Settings.");
        }

        $items = [];
        $zero = [];
        foreach ($billingType->items as $item) {
            if (!$item->isEffective()) {
                $zero[] = strtoupper($item->variable); // "an inactive item counts as ₦0 in the formula"
                continue;
            }
            $items[] = [
                'code' => strtoupper($item->variable),
                'name' => $item->label,
                'amount' => match ($item->source) {
                    \App\Models\BillingTypeItem::SOURCE_REVENUE_ITEM => (float) optional($item->revenueItem)->base_rate,
                    \App\Models\BillingTypeItem::SOURCE_AMOUNT => (float) $item->amount,
                    default => null,
                },
                'input' => $item->source === \App\Models\BillingTypeItem::SOURCE_WORKFLOW,
            ];
        }

        return ['formula' => (string) $billingType->formula, 'items' => $items, 'zero' => $zero];
    }

    /**
     * Lines and total for $type with the officer's amounts for entered items.
     *
     * @param array<string, mixed> $inputs code => amount
     * @return array{lines: list<array{code: string, name: string, amount: float}>, total: float}
     */
    public function price(BillMasterType $type, array $inputs = []): array
    {
        $spec = $this->itemsFor($type);
        $inputs = array_change_key_case($inputs, CASE_UPPER);

        $values = array_fill_keys($spec['zero'], 0.0);
        $lines = [];
        foreach ($spec['items'] as $item) {
            if ($item['input']) {
                $raw = $inputs[$item['code']] ?? null;
                if ($raw === null || $raw === '' || !is_numeric($raw) || (float) $raw < 0) {
                    throw WorkflowGuardException::because("Enter the amount for {$item['name']}.");
                }
                $amount = round((float) $raw, 2);
            } else {
                $amount = round((float) $item['amount'], 2);
            }
            $values[$item['code']] = $amount;
            $lines[] = ['code' => $item['code'], 'name' => $item['name'], 'amount' => $amount];
        }

        try {
            $total = BillFormula::evaluate($spec['formula'], $values);
        } catch (BillFormulaException $e) {
            throw WorkflowGuardException::because("{$type->name}'s formula could not be worked out: " . $e->getMessage());
        }

        return ['lines' => $lines, 'total' => $total];
    }

    // ---- Generate ----------------------------------------------------------

    /**
     * Generate a bill: Bill Ref ID from Bill Master, items from Bill Settings, unpaid.
     *
     * @param array{payer_name?: ?string, payer_phone?: ?string, payer_email?: ?string, payer_address?: ?string, inputs?: array, description?: ?string} $data
     */
    public function generate(BillMasterType $type, string $fileNumber, array $data, string $actor): BillMaster
    {
        if (!$type->is_active) {
            throw WorkflowGuardException::because("{$type->name} bills are switched off in Bill Settings.");
        }
        if (BillMasterType::query()->where('parent_code', $type->code)->exists()) {
            throw WorkflowGuardException::because("Choose which kind of {$type->name} to generate.");
        }

        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            throw WorkflowGuardException::because('Select the file number first.');
        }

        $priced = $this->price($type, $data['inputs'] ?? []);
        if ($priced['total'] <= 0) {
            throw WorkflowGuardException::because('The bill comes to ₦0.00. Check the items in Bill Settings.');
        }

        $file = $this->fileDetails($fileNumber);

        $bill = DB::connection('sqlsrv')->transaction(fn () => BillRef::retrying(function () use ($type, $file, $data, $priced, $actor) {
            $bill = BillMaster::create([
                'bill_type_code' => $type->code,
                'file_number' => $file['file_number'],
                'file_indexing_id' => $file['file_indexing_id'],
                'payer_name' => $this->clean($data['payer_name'] ?? null) ?? $file['payer_name'],
                'payer_phone' => $this->clean($data['payer_phone'] ?? null) ?? $file['payer_phone'],
                'payer_email' => $this->clean($data['payer_email'] ?? null),
                'payer_address' => $this->clean($data['payer_address'] ?? null),
                'plot_no' => $file['plot_no'],
                'district' => $file['district'],
                'lga' => $file['lga'],
                'location' => $file['location'],
                'description' => $this->clean($data['description'] ?? null) ?? $type->name,
                'amount' => $priced['total'],
                'amount_paid' => 0,
                'bill_date' => now()->toDateString(),
                'status' => BillMaster::STATUS_UNPAID,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            foreach ($priced['lines'] as $i => $line) {
                BillItem::create([
                    'bill_ref_id' => $bill->bill_ref_id,
                    'item_code' => $line['code'],
                    'name' => $line['name'],
                    'quantity' => 1,
                    'rate' => $line['amount'],
                    'amount' => $line['amount'],
                    'sort_order' => $i + 1,
                ]);
            }

            return $this->applyCycle($bill, $type);
        }));

        $this->notifier->generated($bill, true);

        return $bill;
    }

    // ---- On-the-fly bills ---------------------------------------------------

    /**
     * An on-the-fly bill (user, 2026-10-21): raised on the Bill Master page for a
     * bill type, found by its Revenue Code (one code per bill type, not per item).
     * The lines are the officer's items: item, quantity, rate; quantity x rate = the
     * line, and every line carries the bill type's Revenue Code. Generated at once.
     *
     * @param list<array{name?: ?string, quantity?: mixed, rate?: mixed}> $lines
     */
    public function onTheFly(BillMasterType $type, string $fileNumber, array $lines, array $data, string $actor): BillMaster
    {
        if (!$type->is_active) {
            throw WorkflowGuardException::because("{$type->name} bills are switched off in Bill Settings.");
        }
        if ($type->code === 'land_use_charge') {
            throw WorkflowGuardException::because('Land Use Charge billing is on hold.');
        }
        if (BillMasterType::query()->where('parent_code', $type->code)->exists()) {
            throw WorkflowGuardException::because("Choose which kind of {$type->name} this bill is.");
        }
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            throw WorkflowGuardException::because('Select the file number first.');
        }

        // One Revenue Code per bill type (Primary / PuA / SuA use Initial Bill's); every line carries it.
        $code = $type->revenueCode();
        $unitRate = in_array($type->code, config('billing.unit_rate_types', []), true);

        $priced = [];
        foreach (array_values($lines) as $n => $line) {
            $row = $n + 1;
            // Free text, tidied: single spaces, first letter up; the officer's wording otherwise kept.
            $name = $this->clean(preg_replace('/\s+/', ' ', (string) ($line['name'] ?? '')));
            $hasNumbers = is_numeric($line['rate'] ?? null) || (is_numeric($line['quantity'] ?? null) && (float) $line['quantity'] != 1.0);
            if ($name === null && !$hasNumbers) {
                continue; // an empty row
            }
            if ($name === null) {
                throw WorkflowGuardException::because("Line {$row}: enter the item.");
            }
            $name = mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
            if ($unitRate && !(is_numeric($line['quantity'] ?? null) && (float) $line['quantity'] > 0)) {
                // Ground rent = rate x plot size: never billed as one unit by default.
                throw WorkflowGuardException::because("Line {$row}: enter the plot size (m²) for {$name}.");
            }
            $qty = is_numeric($line['quantity'] ?? null) ? round((float) $line['quantity'], 4) : 1.0;
            $rate = is_numeric($line['rate'] ?? null) ? round((float) $line['rate'], 2) : 0.0;
            if ($qty <= 0) {
                throw WorkflowGuardException::because("Line {$row}: the quantity must be more than 0.");
            }
            if ($rate <= 0) {
                throw WorkflowGuardException::because("Line {$row}: enter the rate for {$name}.");
            }
            $priced[] = ['code' => $code, 'name' => $name, 'quantity' => $qty, 'rate' => $rate, 'amount' => round($qty * $rate, 2)];
        }
        if (!$priced) {
            throw WorkflowGuardException::because('Add at least one item.');
        }
        $total = round(array_sum(array_column($priced, 'amount')), 2);

        $file = $this->fileDetails($fileNumber);

        $bill = DB::connection('sqlsrv')->transaction(fn () => BillRef::retrying(function () use ($type, $file, $data, $priced, $total, $actor) {
            $bill = BillMaster::create([
                'bill_type_code' => $type->code,
                'file_number' => $file['file_number'],
                'file_indexing_id' => $file['file_indexing_id'],
                'payer_name' => $this->clean($data['payer_name'] ?? null) ?? $file['payer_name'],
                'payer_phone' => $this->clean($data['payer_phone'] ?? null) ?? $file['payer_phone'],
                'payer_email' => $this->clean($data['payer_email'] ?? null),
                'payer_address' => $this->clean($data['payer_address'] ?? null),
                'plot_no' => $file['plot_no'],
                'district' => $file['district'],
                'lga' => $file['lga'],
                'location' => $file['location'],
                'description' => $this->clean($data['description'] ?? null) ?? $type->name,
                'amount' => $total,
                'amount_paid' => 0,
                'bill_date' => now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'status' => BillMaster::STATUS_UNPAID,
                'raised_via' => BillMaster::RAISED_ON_THE_FLY,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);

            foreach ($priced as $i => $line) {
                BillItem::create([
                    'bill_ref_id' => $bill->bill_ref_id,
                    'item_code' => $line['code'],
                    'name' => $line['name'],
                    'quantity' => $line['quantity'],
                    'rate' => $line['rate'],
                    'amount' => $line['amount'],
                    'sort_order' => $i + 1,
                ]);
            }

            return $this->applyCycle($bill, $type, !empty($data['period_start']) ? Carbon::parse($data['period_start']) : null);
        }));

        $this->notifier->generated($bill, true);

        return $bill;
    }

    // ---- Payment cycles (recurring bills) ------------------------------------

    /** The type whose Bill Settings hold the cycle: the parent for Primary / PuA / SuA. */
    public function cycleType(?BillMasterType $type): ?BillMasterType
    {
        if ($type && $type->parent_code) {
            return BillMasterType::query()->where('code', $type->parent_code)->first() ?? $type;
        }

        return $type;
    }

    /** Months in one period of $cycle, or 0 when it is not a known cycle. */
    public static function cycleMonths(?string $cycle): int
    {
        return (int) config('billing.cycles.' . $cycle . '.months', 0);
    }

    /**
     * A bill of a recurring type covers one period of its payment cycle, starting on
     * $start (default: the bill date). The next payment falls due the day after the
     * period ends. Bills of other types are left as they are.
     */
    public function applyCycle(BillMaster $bill, ?BillMasterType $type = null, ?Carbon $start = null): BillMaster
    {
        $setting = $this->cycleType($type ?? $bill->type);
        $months = $setting && $setting->is_recurring ? self::cycleMonths($setting->payment_cycle) : 0;
        if ($months <= 0 || $bill->period_start) {
            return $bill;
        }

        $start = ($start ?? Carbon::parse($bill->bill_date ?? now()))->copy()->startOfDay();
        $end = $start->copy()->addMonthsNoOverflow($months)->subDay();
        $bill->forceFill([
            'payment_cycle' => $setting->payment_cycle,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'next_due_date' => $end->copy()->addDay()->toDateString(),
        ])->save();

        return $bill;
    }

    /**
     * Raise the next cycle's bill for a recurring bill whose next payment is due:
     * same file, payer and lines (same rates), the following period, generated, and
     * linked back through previous_bill_ref. The old bill stops carrying the due date.
     */
    public function raiseNextCycle(BillMaster $bill, string $actor): BillMaster
    {
        $months = self::cycleMonths($bill->payment_cycle);
        if ($months > 0 && BillMaster::query()->where('previous_bill_ref', $bill->bill_ref_id)->exists()) {
            throw WorkflowGuardException::because("The next cycle of {$bill->bill_ref_id} has already been raised.");
        }
        if ($months <= 0 || !$bill->next_due_date) {
            throw WorkflowGuardException::because("{$bill->bill_ref_id} is not on a payment cycle.");
        }
        if ($bill->status === BillMaster::STATUS_CANCELLED) {
            throw WorkflowGuardException::because("{$bill->bill_ref_id} is cancelled.");
        }
        $setting = $this->cycleType($bill->type);
        if (!$setting || !$setting->is_recurring || !$setting->is_active) {
            throw WorkflowGuardException::because(($setting->name ?? $bill->bill_type_code) . ' is not a recurring bill type in Bill Settings any more.');
        }
        if (BillMaster::query()->where('previous_bill_ref', $bill->bill_ref_id)->exists()) {
            throw WorkflowGuardException::because("The next cycle of {$bill->bill_ref_id} has already been raised.");
        }

        $start = Carbon::parse($bill->next_due_date)->startOfDay();
        $end = $start->copy()->addMonthsNoOverflow($months)->subDay();
        $lines = $bill->items()->get();

        $next = DB::connection('sqlsrv')->transaction(fn () => BillRef::retrying(function () use ($bill, $start, $end, $lines, $actor) {
            $next = BillMaster::create($bill->only([
                'bill_type_code', 'file_number', 'file_indexing_id', 'payer_name', 'payer_phone', 'payer_email', 'payer_address',
                'plot_no', 'district', 'lga', 'location', 'description', 'amount', 'payment_cycle',
            ]) + [
                'amount_paid' => 0,
                'bill_date' => now()->toDateString(),
                'due_date' => $start->toDateString(),
                'status' => BillMaster::STATUS_UNPAID,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'next_due_date' => $end->copy()->addDay()->toDateString(),
                'previous_bill_ref' => $bill->bill_ref_id,
                'raised_via' => BillMaster::RAISED_CYCLE,
                'created_by' => $actor,
                'updated_by' => $actor,
            ]);
            foreach ($lines as $i => $line) {
                BillItem::create([
                    'bill_ref_id' => $next->bill_ref_id,
                    'item_code' => $line->item_code,
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'rate' => $line->rate,
                    'amount' => $line->amount,
                    'sort_order' => $i + 1,
                ]);
            }
            $bill->forceFill(['next_due_date' => null, 'updated_by' => $actor])->save();

            return $next;
        }));

        $this->notifier->generated($next, false, app()->runningInConsole());

        return $next;
    }

    // ---- Module bills -------------------------------------------------------

    /**
     * Mirror a bill a module raised into Bill Master (phase E): create or update
     * the bill_master row for (source_table, source_id) with its line items. The
     * total is the sum of the lines (a deduction is a negative line). A new row
     * draws its BILL- ref here; the module stores that ref as its own.
     *
     * $sourceId may be null while the module row is still being created; attach
     * it afterwards with $bill->update(['source_id' => ...]).
     *
     * @param array<string, mixed> $attrs bill_master columns (payer, property, dates, …)
     * @param list<array{name: string, amount: float|int|string, code?: ?string}> $lines
     */
    public function mirror(string $typeCode, string $sourceTable, ?int $sourceId, array $attrs, array $lines, string $actor): BillMaster
    {
        $lines = array_values(array_filter($lines, fn ($l) => round((float) $l['amount'], 2) != 0.0));
        $total = round(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 2);

        $existing = $sourceId === null ? null : BillMaster::query()
            ->where('source_table', $sourceTable)->where('source_id', $sourceId)->first();

        return DB::connection('sqlsrv')->transaction(function () use ($existing, $typeCode, $sourceTable, $sourceId, $attrs, $lines, $total, $actor) {
            $values = array_merge($attrs, [
                'bill_type_code' => $typeCode,
                'source_table' => $sourceTable,
                'source_id' => $sourceId,
                'amount' => $total,
                'updated_by' => $actor,
            ]);

            if ($existing) {
                $existing->update($values);
                BillItem::query()->where('bill_ref_id', $existing->bill_ref_id)->delete();
                $bill = $existing;
            } else {
                $bill = BillRef::retrying(fn () => BillMaster::create($values + [
                    'amount_paid' => 0,
                    'status' => BillMaster::STATUS_UNPAID,
                    'bill_date' => now()->toDateString(),
                    'created_by' => $actor,
                ]));
            }

            foreach ($lines as $i => $line) {
                BillItem::create([
                    'bill_ref_id' => $bill->bill_ref_id,
                    'item_code' => $line['code'] ?? null,
                    'name' => $line['name'],
                    'quantity' => 1,
                    'rate' => round((float) $line['amount'], 2),
                    'amount' => round((float) $line['amount'], 2),
                    'sort_order' => $i + 1,
                ]);
            }

            return $this->refreshTotals($bill, $actor);
        });
    }

    /**
     * Generate a bill a module sent over (its items and total come from the module's
     * form). Only a generated bill has an invoice.
     */
    public function markGenerated(BillMaster $bill, string $actor, ?string $phone = null): BillMaster
    {
        if ($bill->status === BillMaster::STATUS_CANCELLED) {
            throw WorkflowGuardException::because("{$bill->bill_ref_id} is cancelled.");
        }
        if ($bill->isGenerated()) {
            throw WorkflowGuardException::because("{$bill->bill_ref_id} has already been generated.");
        }
        if ((float) $bill->amount <= 0) {
            throw WorkflowGuardException::because("{$bill->bill_ref_id} comes to " . '₦' . number_format((float) $bill->amount, 2) . '. Correct it in its module first.');
        }

        // The phone the officer confirmed, else the bill's own, else one found in the file's records.
        $phone = $this->clean($phone);
        $phoneConfirmed = $phone !== null;
        $phone ??= $this->clean($bill->payer_phone) ?? $this->phoneFor($bill->file_number)['payer_phone'];

        $bill->update(['generated_at' => now(), 'generated_by' => $actor, 'updated_by' => $actor, 'payer_phone' => $phone]);
        $this->applyCycle($bill);
        $this->notifier->generated($bill, $phoneConfirmed);

        return $bill;
    }

    /** The module removed its bill: Bill Master keeps the row, flagged cancelled. */
    public function cancelMirror(string $sourceTable, int $sourceId, string $actor): void
    {
        BillMaster::query()->where('source_table', $sourceTable)->where('source_id', $sourceId)
            ->where('status', '<>', BillMaster::STATUS_CANCELLED)
            ->update(['status' => BillMaster::STATUS_CANCELLED, 'updated_by' => $actor, 'updated_at' => now()]);
    }

    /** Location for Bill Master: district + LGA (+ state), never the plot number. */
    public static function location(?string $district, ?string $lga, ?string $state = 'Kano State'): ?string
    {
        $parts = array_filter(array_map(fn ($v) => trim((string) $v), [$district, $lga]));

        return $parts ? implode(', ', array_merge($parts, array_filter([trim((string) $state)]))) : null;
    }

    // ---- Payment -----------------------------------------------------------

    /** Methods this bill's type offers, with whether each can be chosen now. */
    public function methodsFor(BillMasterType $type): array
    {
        $out = [];
        foreach (config('billing.payment_methods', []) as $key => $meta) {
            if (!in_array($key, $type->paymentMethods(), true)) {
                continue;
            }
            $out[$key] = $meta + ['selectable' => (bool) ($meta['enabled'] ?? false)];
        }

        return $out;
    }

    public function outstanding(BillMaster $bill): float
    {
        return max(0, round((float) $bill->amount - (float) $bill->amount_paid, 2));
    }

    /** A POS payment: paid at the terminal with the Bill Ref ID; the officer records it. */
    public function recordPos(BillMaster $bill, string $transactionId, float $amount, string $actor): BillPayment
    {
        $this->assertPayable($bill, BillPayment::METHOD_POS, $amount);

        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            throw WorkflowGuardException::because('Enter the POS Transaction ID.');
        }
        if (BillPayment::query()->where('bill_ref_id', $bill->bill_ref_id)->where('transaction_id', $transactionId)
            ->where('status', BillPayment::STATUS_SUCCESSFUL)->exists()) {
            throw WorkflowGuardException::because("Transaction ID {$transactionId} is already recorded on this bill.");
        }

        $payment = DB::connection('sqlsrv')->transaction(function () use ($bill, $transactionId, $amount, $actor) {
            $payment = BillPayment::create([
                'bill_ref_id' => $bill->bill_ref_id,
                'method' => BillPayment::METHOD_POS,
                'transaction_id' => $transactionId,
                'amount' => round($amount, 2),
                'paid_at' => now(),
                'status' => BillPayment::STATUS_SUCCESSFUL,
                'recorded_by' => $actor,
                // Checked later against the POS settlement report (Reconciliation).
                'reconciliation_status' => BillPayment::RECON_UNRECONCILED,
            ]);
            $this->refreshTotals($bill, $actor);

            return $payment;
        });
        $this->notifier->payment($bill, $payment);

        return $payment;
    }

    /**
     * Start a Paystack payment (the existing Paystack keys, as PHS and instruments use).
     * Returns Paystack's checkout URL; Paystack sends the payer back to $callbackUrl.
     */
    public function startPaystack(BillMaster $bill, float $amount, string $email, string $callbackUrl, string $actor): string
    {
        $this->assertPayable($bill, BillPayment::METHOD_PAYSTACK, $amount);

        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw WorkflowGuardException::because('Paystack needs the payer\'s email address.');
        }
        if (!config('services.paystack.secret')) {
            throw WorkflowGuardException::because('Paystack is not configured on this server.');
        }

        $reference = $bill->bill_ref_id . '-' . strtoupper(Str::random(6));

        $payment = BillPayment::create([
            'bill_ref_id' => $bill->bill_ref_id,
            'method' => BillPayment::METHOD_PAYSTACK,
            'transaction_id' => $reference,
            'amount' => round($amount, 2),
            'status' => BillPayment::STATUS_PENDING,
            'recorded_by' => $actor,
        ]);

        if (!$bill->payer_email) {
            $bill->update(['payer_email' => $email]);
        }

        $response = Http::withToken(config('services.paystack.secret'))
            ->post(rtrim(config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/initialize', [
                'email' => $email,
                'amount' => (int) round($amount * 100),
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => ['bill_ref_id' => $bill->bill_ref_id, 'bill_type' => $bill->bill_type_code, 'file_number' => $bill->file_number],
            ]);

        if (!$response->successful() || !$response->json('data.authorization_url')) {
            $payment->update(['status' => BillPayment::STATUS_FAILED, 'gateway_response' => Str::limit($response->body(), 2000)]);
            throw WorkflowGuardException::because('Could not reach Paystack. Please try again.');
        }

        return (string) $response->json('data.authorization_url');
    }

    /** Paystack sent the payer back: verify the reference and settle the payment. */
    public function confirmPaystack(string $reference, string $actor): BillPayment
    {
        $payment = BillPayment::query()->where('method', BillPayment::METHOD_PAYSTACK)->where('transaction_id', $reference)->first();
        if (!$payment) {
            throw WorkflowGuardException::because('Payment reference not found.');
        }
        if ($payment->status === BillPayment::STATUS_SUCCESSFUL) {
            return $payment;
        }

        $verify = Http::withToken(config('services.paystack.secret'))
            ->get(rtrim(config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/verify/' . urlencode($reference));

        $paidKobo = (int) $verify->json('data.amount');
        $ok = $verify->successful() && $verify->json('data.status') === 'success'
            && $paidKobo === (int) round((float) $payment->amount * 100);

        $bill = BillMaster::query()->where('bill_ref_id', $payment->bill_ref_id)->firstOrFail();

        DB::connection('sqlsrv')->transaction(function () use ($payment, $verify, $ok, $bill, $actor) {
            $payment->update([
                'status' => $ok ? BillPayment::STATUS_SUCCESSFUL : BillPayment::STATUS_FAILED,
                'paid_at' => $ok ? now() : null,
                'gateway_response' => Str::limit($verify->body(), 2000),
                // Verified with Paystack for the exact amount: reconciled against the gateway.
                'reconciliation_status' => $ok ? BillPayment::RECON_RECONCILED : null,
                'reconciled_at' => $ok ? now() : null,
                'reconciled_by' => $ok ? BillPayment::RECON_BY_PAYSTACK : null,
                'settlement_ref' => $ok && $verify->json('data.id') ? (string) $verify->json('data.id') : null,
            ]);
            if ($ok) {
                $this->refreshTotals($bill, $actor);
            }
        });

        if (!$ok) {
            throw WorkflowGuardException::because("Paystack did not confirm payment {$reference}.");
        }
        $this->notifier->payment($bill->refresh(), $payment->refresh());

        return $payment;
    }

    /** amount_paid, status and the latest-payment shortcuts, from the successful payments. */
    public function refreshTotals(BillMaster $bill, ?string $actor = null): BillMaster
    {
        $paid = BillPayment::query()->where('bill_ref_id', $bill->bill_ref_id)->where('status', BillPayment::STATUS_SUCCESSFUL);
        $sum = round((float) (clone $paid)->sum('amount'), 2);
        $latest = (clone $paid)->orderByDesc('paid_at')->orderByDesc('id')->first();

        $status = $bill->status === BillMaster::STATUS_CANCELLED ? BillMaster::STATUS_CANCELLED
            : ($sum <= 0 ? BillMaster::STATUS_UNPAID
            : ($sum + 0.004 >= (float) $bill->amount ? BillMaster::STATUS_PAID : BillMaster::STATUS_PART_PAID));

        $bill->update([
            'amount_paid' => $sum,
            'status' => $status,
            'transaction_id' => $latest?->transaction_id,
            'receipt_no' => $latest?->receipt_no,
            'receipt_date' => $latest?->receipt_date,
            'updated_by' => $actor ?? $bill->updated_by,
        ]);

        return $bill;
    }

    private function assertPayable(BillMaster $bill, string $method, float $amount): void
    {
        $type = $bill->type;
        if (!$type || !$type->accepts_payment) {
            throw WorkflowGuardException::because('This bill type does not take payment at the bill stage.');
        }
        $methods = $this->methodsFor($type);
        if (!($methods[$method]['selectable'] ?? false)) {
            throw WorkflowGuardException::because(config("billing.payment_methods.{$method}.label", $method) . ' is not available for this bill.');
        }
        if ($bill->status === BillMaster::STATUS_CANCELLED) {
            throw WorkflowGuardException::because('This bill is cancelled.');
        }
        $outstanding = $this->outstanding($bill);
        if ($outstanding <= 0) {
            throw WorkflowGuardException::because('This bill is already paid.');
        }
        if ($amount <= 0) {
            throw WorkflowGuardException::because('Enter the amount paid.');
        }
        if ($amount - $outstanding > 0.004) {
            throw WorkflowGuardException::because('The amount is more than the ₦' . number_format($outstanding, 2) . ' outstanding.');
        }
    }

    private function clean(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
