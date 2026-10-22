<?php

namespace App\Console\Commands;

use App\Models\BillBalance;
use App\Models\BillMaster;
use App\Models\Billing;
use App\Models\Cadastral\CadastralBill;
use App\Models\ConsentApplication;
use App\Models\LegalSearchOnlinePayment;
use App\Models\Phs\PhsOnboardingRequest;
use App\Models\Phs\PhsTokenTransaction;
use App\Models\SpaBill;
use App\Services\Billing\BillRef;
use App\Services\Billing\Modules\CadastralPndMirror;
use App\Services\Billing\Modules\ConsentBillMirror;
use App\Services\Billing\Modules\DeedsBillBalanceMirror;
use App\Services\Billing\Modules\OssBillMirror;
use App\Services\Billing\Modules\PortalBillMirror;
use App\Services\Billing\Modules\SpasBillMirror;
use App\Services\Billing\Modules\StFinalBillMirror;
use App\Services\Billing\Modules\StInitialBillMirror;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * KLAES REV-M phase F (docs/plans/KLAES_REVM_Bill_Master_Plan.md): bring the
 * bills raised BEFORE Bill Master into it.
 *
 * --preview writes a CSV of every bill: source row, old ref, the BILL- ref it
 * would get, amount, lines, how it would load (unpaid / paid) and whether its
 * module ref is renumbered. Nothing is kept: each record runs through its real
 * mirror inside a transaction of 25 that is always rolled back.
 *
 * --load (approved 2026-10-19) does it for real, one short transaction per bill,
 * after backing up every module row it renumbers (storage/app/revm/backup_*).
 * Decisions: module bills load unpaid, except a Deeds bill with a receipt
 * recorded in the module and SPAS bills with recorded payments, which load
 * paid; negative Deeds bills load as they are; Deeds / SPAS / Cadastral / OSS
 * refs are renumbered to the BILL- ref and the old ref kept as legacy_ref.
 * Re-running skips bills already in Bill Master.
 */
class BillsConsolidate extends Command
{
    protected $signature = 'bills:consolidate
        {--preview : Write the preview CSV only; nothing is saved}
        {--load : Load the bills into Bill Master for real (backs up first)}
        {--force : With --load, do not ask for confirmation}';

    protected $description = 'REV-M phase F: bring pre-Bill-Master module bills into Bill Master (preview or load)';

    private const CHUNK = 25;
    private const ACTOR = 'consolidation';

    public function handle(): int
    {
        if ($this->option('load')) {
            return $this->load();
        }
        if (!$this->option('preview')) {
            $this->error('Choose --preview (nothing saved) or --load.');

            return self::FAILURE;
        }

        $items = $this->items();
        $this->info(count($items) . ' source records found. Running each through its mirror (rolled back)…');

        $rows = [];
        $bar = $this->output->createProgressBar(count($items));
        foreach (array_chunk($items, self::CHUNK) as $chunk) {
            DB::connection('sqlsrv')->beginTransaction();
            try {
                foreach ($chunk as $item) {
                    $rows[] = $this->runItem($item);
                    $bar->advance();
                }
            } finally {
                DB::connection('sqlsrv')->rollBack();
            }
        }
        $bar->finish();
        $this->newLine(2);

        // The BILL- refs the load would issue, in this order.
        $next = (int) substr(BillRef::next(BillRef::SYSTEM), -6);
        $year = now()->year;
        foreach ($rows as &$row) {
            $row['new_ref'] = $row['action'] === 'LOAD' ? sprintf('BILL-%d-%06d', $year, $next++) : '';
        }
        unset($row);

        $path = $this->write($rows);
        $this->summary($rows);
        $this->info("Preview written: {$path}");

        return self::SUCCESS;
    }

    /** @return list<array{source: string, table: string, id: int, old_ref: ?string, renumber: ?string, run: callable, skip: ?string}> */
    private function items(): array
    {
        $items = [];
        $done = fn (string $table, $id) => BillMaster::query()->where('source_table', $table)->where('source_id', $id)->exists();

        foreach (BillBalance::query()->orderBy('id')->get() as $b) {
            if ($done(DeedsBillBalanceMirror::SOURCE_TABLE, $b->id)) { continue; }
            $items[] = ['source' => 'Deeds Bill Balance', 'table' => DeedsBillBalanceMirror::SOURCE_TABLE, 'id' => (int) $b->id, 'old_ref' => $b->reference,
                'renumber' => 'deeds_bill_balances_metadata.reference', 'skip' => null,
                'run' => function () use ($b) {
                    $fees = (array) (DB::connection('sqlsrv')->table('billing')->where('ID', $b->billing_id)->first() ?? []);
                    app(DeedsBillBalanceMirror::class)->sync($b, $fees, self::ACTOR, true);
                },
                'post' => fn (string $ref) => DB::connection('sqlsrv')->table('deeds_bill_balances_metadata')->where('id', $b->id)->update(['reference' => $ref, 'updated_at' => now()]),
                'payments' => function (BillMaster $bill) use ($b) {
                    $billing = DB::connection('sqlsrv')->table('billing')->where('ID', $b->billing_id)->first();
                    $receipt = trim((string) ($billing->bill_balance_reciept ?? ''));
                    // A receipt that is only the bill's own reference was never a payment.
                    if ($receipt !== '' && $receipt !== $b->reference && (float) $bill->amount > 0) {
                        $this->recordPayment($bill, $receipt, (float) $bill->amount, $billing->bill_balance_date ?? null, 'Receipt recorded on Deeds Bill Balance.');
                    }
                }];
        }

        foreach (SpaBill::query()->orderBy('id')->get() as $b) {
            if ($done(SpasBillMirror::SOURCE_TABLE, $b->id)) { continue; }
            $items[] = ['source' => 'SPAS Contravention', 'table' => SpasBillMirror::SOURCE_TABLE, 'id' => (int) $b->id, 'old_ref' => $b->reference_id,
                'renumber' => 'spa_bills.reference_id', 'skip' => null,
                'run' => fn () => app(SpasBillMirror::class)->sync($b, self::ACTOR, true),
                'pre' => function (string $ref) use ($b) {
                    DB::connection('sqlsrv')->table('spa_bills')->where('id', $b->id)->update(['reference_id' => $ref, 'updated_at' => now()]);
                    $b->reference_id = $ref;
                },
                'payments' => function (BillMaster $bill) use ($b) {
                    foreach (DB::connection('sqlsrv')->table('spa_payments')->where('spa_bill_id', $b->id)->orderBy('id')->get() as $p) {
                        $this->recordPayment($bill, $p->receipt_number ?: 'SPAS-PAY-' . $p->id, (float) $p->amount_paid, $p->payment_date,
                            'Payment recorded on SPAS (' . ($p->payment_method ?: 'method not recorded') . ').');
                    }
                }];
        }

        foreach (CadastralBill::query()->orderBy('id')->get() as $b) {
            if ($done(CadastralPndMirror::SOURCE_TABLE, $b->id)) { continue; }
            $items[] = ['source' => 'Cadastral P&D', 'table' => CadastralPndMirror::SOURCE_TABLE, 'id' => (int) $b->id, 'old_ref' => $b->bill_ref,
                'renumber' => 'cadastral_bills.bill_ref', 'skip' => $b->status === 'Issued' ? null : "Bill is {$b->status}",
                'run' => fn () => app(CadastralPndMirror::class)->sync($b, self::ACTOR, true),
                'pre' => function (string $ref) use ($b) {
                    DB::connection('sqlsrv')->table('cadastral_bills')->where('id', $b->id)->update(['bill_ref' => $ref]);
                    $b->bill_ref = $ref;
                }];
        }

        foreach (ConsentApplication::query()->whereNotNull('bill_total')->where('bill_total', '>', 0)->orderBy('id')->get() as $a) {
            if ($done(ConsentBillMirror::SOURCE_TABLE, $a->id)) { continue; }
            $items[] = ['source' => 'Consent Bill', 'table' => ConsentBillMirror::SOURCE_TABLE, 'id' => (int) $a->id, 'old_ref' => $a->application_tracking_no,
                'renumber' => null, 'skip' => null, 'run' => fn () => app(ConsentBillMirror::class)->sync($a)];
        }

        foreach (DB::connection('sqlsrv')->table('mother_applications')->orderBy('id')->pluck('id') as $id) {
            if ($done('mother_applications', $id)) { continue; }
            $items[] = ['source' => 'ST Initial (Primary)', 'table' => 'mother_applications', 'id' => (int) $id, 'old_ref' => null,
                'renumber' => null, 'skip' => null, 'run' => fn () => app(StInitialBillMirror::class)->forPrimary((int) $id)];
        }
        foreach (DB::connection('sqlsrv')->table('subapplications')->orderBy('id')->pluck('id') as $id) {
            if ($done('subapplications', $id)) { continue; }
            $items[] = ['source' => 'ST Initial (Unit)', 'table' => 'subapplications', 'id' => (int) $id, 'old_ref' => null,
                'renumber' => null, 'skip' => null, 'run' => fn () => app(StInitialBillMirror::class)->forUnit((int) $id)];
        }

        foreach (DB::connection('sqlsrv')->table('final_bills')->orderBy('id')->get() as $row) {
            if ($done(StFinalBillMirror::SOURCE_TABLE, $row->id)) { continue; }
            $items[] = ['source' => 'ST Final (Bill Balance)', 'table' => StFinalBillMirror::SOURCE_TABLE, 'id' => (int) $row->id, 'old_ref' => null,
                'renumber' => null, 'skip' => null, 'run' => fn () => app(StFinalBillMirror::class)->sync($row)];
        }

        foreach (Billing::query()->where('source', 'OSS_OP_APPLICATION_FEE')->orderBy('ID')->get() as $row) {
            if ($done(OssBillMirror::SOURCE_TABLE, $row->ID)) { continue; }
            $items[] = ['source' => 'OSS Bill', 'table' => OssBillMirror::SOURCE_TABLE, 'id' => (int) $row->ID, 'old_ref' => $row->ref_id,
                'renumber' => 'billing.ref_id', 'skip' => null, 'run' => fn () => app(OssBillMirror::class)->sync($row),
                'pre' => function (string $ref) use ($row) {
                    DB::connection('sqlsrv')->table('billing')->where('ID', $row->ID)->update(['ref_id' => $ref, 'updated_at' => now()]);
                    $row->ref_id = $ref;
                }];
        }

        foreach (LegalSearchOnlinePayment::query()->where('status', 'paid')->orderBy('id')->get() as $p) {
            if ($done('legal_search_online_payments', $p->id)) { continue; }
            $items[] = ['source' => 'Legal Search (portal)', 'table' => 'legal_search_online_payments', 'id' => (int) $p->id, 'old_ref' => $p->reference,
                'renumber' => null, 'skip' => null, 'run' => fn () => app(PortalBillMirror::class)->legalSearch($p, true)];
        }
        foreach (PhsTokenTransaction::query()->whereIn('type', ['purchase', 'topup'])->where('status', 'completed')->orderBy('id')->get() as $t) {
            if ($done('phs_token_transactions', $t->id)) { continue; }
            $items[] = ['source' => 'PHS Token Top-up (portal)', 'table' => 'phs_token_transactions', 'id' => (int) $t->id, 'old_ref' => $t->reference_no,
                'renumber' => null, 'skip' => null, 'run' => fn () => app(PortalBillMirror::class)->phsTokens($t, true)];
        }
        foreach (PhsOnboardingRequest::query()->where('payment_status', 'completed')->orderBy('id')->get() as $r) {
            if ($done('phs_onboarding_requests', $r->id)) { continue; }
            $items[] = ['source' => 'PHS Onboarding (portal)', 'table' => 'phs_onboarding_requests', 'id' => (int) $r->id, 'old_ref' => $r->invoice_number,
                'renumber' => null, 'skip' => null, 'run' => fn () => app(PortalBillMirror::class)->phsOnboarding($r, true)];
        }

        return $items;
    }

    private function runItem(array $item): array
    {
        $row = [
            'source' => $item['source'], 'source_table' => $item['table'], 'source_id' => $item['id'],
            'old_ref' => (string) $item['old_ref'], 'new_ref' => '', 'action' => 'LOAD', 'note' => '',
            'bill_type' => '', 'loads_as' => '', 'amount' => '', 'lines' => '', 'payments' => '',
            'file_number' => '', 'payer' => '', 'plot_no' => '', 'district' => '', 'lga' => '', 'location' => '',
            'renumbers' => $item['renumber'] ? "{$item['renumber']}: {$item['old_ref']} → new ref" : '',
        ];

        if ($item['skip']) {
            return ['action' => 'SKIP', 'note' => $item['skip'], 'renumbers' => ''] + $row;
        }

        try {
            ($item['run'])();
            $bill = BillMaster::query()->where('source_table', $item['table'])->where('source_id', $item['id'])->first();
            if ($bill && isset($item['payments'])) {
                ($item['payments'])($bill);
            }
        } catch (\Throwable $e) {
            return ['action' => 'ERROR', 'note' => $e->getMessage(), 'renumbers' => ''] + $row;
        }

        if (!$bill) {
            return ['action' => 'SKIP', 'note' => $this->whySkipped($item), 'renumbers' => ''] + $row;
        }

        return $this->describe($item, $bill->fresh(['items', 'payments']), $row);
    }

    /** The CSV row for a bill now in Bill Master. */
    private function describe(array $item, BillMaster $bill, array $row): array
    {
        return [
            'bill_type' => $bill->bill_type_code,
            'loads_as' => $bill->status,
            'amount' => number_format((float) $bill->amount, 2, '.', ''),
            'lines' => $bill->items->map(fn ($i) => $i->name . ' ' . number_format((float) $i->amount, 2, '.', ''))->implode(' | '),
            'payments' => $bill->payments->map(fn ($p) => $p->methodLabel() . ' ' . $p->transaction_id . ' ' . number_format((float) $p->amount, 2, '.', ''))->implode(' | '),
            'file_number' => (string) $bill->file_number,
            'payer' => (string) $bill->payer_name,
            'plot_no' => (string) $bill->plot_no,
            'district' => (string) $bill->district,
            'lga' => (string) $bill->lga,
            'location' => (string) $bill->location,
            'note' => $bill->legacy_ref && $bill->legacy_ref !== $item['old_ref'] ? "Shared ST ref {$bill->legacy_ref}" : $this->noteFor($item, $bill),
        ] + $row;
    }

    private function whySkipped(array $item): string
    {
        return match ($item['table']) {
            'mother_applications', 'subapplications' => 'No fee on the application',
            StFinalBillMirror::SOURCE_TABLE => 'Application no longer exists, or already billed from Deeds',
            ConsentBillMirror::SOURCE_TABLE => 'Not mirrored (see log)',
            default => 'Nothing to bill (zero amount or not mirrored; see log)',
        };
    }

    private function noteFor(array $item, BillMaster $bill): string
    {
        if ($item['table'] === DeedsBillBalanceMirror::SOURCE_TABLE) {
            $receipt = DB::connection('sqlsrv')->table('deeds_bill_balances_metadata as m')
                ->leftJoin('billing as b', 'b.ID', '=', 'm.billing_id')->where('m.id', $item['id'])->value('b.bill_balance_reciept');
            if ($receipt && $receipt !== $item['old_ref']) {
                return (float) $bill->amount > 0 ? "Module receipt {$receipt}: loads paid" : "Module receipt {$receipt}; amount not positive, loads as is";
            }
            if ((float) $bill->amount < 0) {
                return 'Negative (deposit larger than charges): loads as is';
            }
        }
        if ($item['table'] === SpasBillMirror::SOURCE_TABLE) {
            $status = SpaBill::query()->whereKey($item['id'])->value('status');
            if ($status && $status !== 'unpaid') {
                return "SPAS status {$status}: its SPAS payments are recorded";
            }
        }

        return '';
    }

    private function write(array $rows, string $prefix = 'consolidation_preview_'): string
    {
        $columns = ['source', 'source_table', 'source_id', 'action', 'old_ref', 'new_ref', 'bill_type', 'loads_as', 'amount',
            'file_number', 'payer', 'plot_no', 'district', 'lga', 'location', 'lines', 'payments', 'renumbers', 'note'];

        $dir = storage_path('app/revm');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . DIRECTORY_SEPARATOR . $prefix . now()->format('Ymd_His') . '.csv';

        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF"); // so Excel opens ₦ and → correctly
        fputcsv($fh, array_merge(['s_n'], $columns));
        foreach ($rows as $i => $row) {
            fputcsv($fh, array_merge([$i + 1], array_map(fn ($c) => $row[$c] ?? '', $columns)));
        }
        fclose($fh);

        return $path;
    }

    // ---- Load ---------------------------------------------------------------

    private function load(): int
    {
        $items = $this->items();
        $renumbered = collect($items)->whereNotNull('renumber')->where('skip', null)->count();
        $this->warn(count($items) . " bills will be loaded into Bill Master; {$renumbered} module refs renumbered.");
        if (!$this->option('force') && !$this->confirm('Load them now?')) {
            return self::FAILURE;
        }

        $backup = $this->backup();
        $this->info("Module rows backed up: {$backup}");

        $rows = [];
        $bar = $this->output->createProgressBar(count($items));
        foreach ($items as $item) {
            $rows[] = $this->loadItem($item);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $path = $this->write($rows, 'consolidation_load_');
        $this->summary($rows);
        $this->info("Results written: {$path}");

        return collect($rows)->where('action', 'ERROR')->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /** One bill, in its own short transaction: renumber, mirror, payments. */
    private function loadItem(array $item): array
    {
        $row = [
            'source' => $item['source'], 'source_table' => $item['table'], 'source_id' => $item['id'],
            'old_ref' => (string) $item['old_ref'], 'new_ref' => '', 'action' => 'LOAD', 'note' => '',
            'bill_type' => '', 'loads_as' => '', 'amount' => '', 'lines' => '', 'payments' => '',
            'file_number' => '', 'payer' => '', 'plot_no' => '', 'district' => '', 'lga' => '', 'location' => '',
            'renumbers' => '',
        ];
        if ($item['skip']) {
            return ['action' => 'SKIP', 'note' => $item['skip']] + $row;
        }

        try {
            $bill = DB::connection('sqlsrv')->transaction(function () use ($item) {
                if (isset($item['pre'])) {
                    ($item['pre'])(BillRef::next(BillRef::SYSTEM));
                }
                ($item['run'])();

                $bill = BillMaster::query()->where('source_table', $item['table'])->where('source_id', $item['id'])->first();
                if (!$bill) {
                    throw new \DomainException('SKIP');   // undo any renumbering
                }
                if (isset($item['post'])) {
                    ($item['post'])($bill->bill_ref_id);
                }
                if ($item['renumber'] && $item['old_ref'] && !$bill->legacy_ref) {
                    $bill->update(['legacy_ref' => $item['old_ref']]);
                }
                if (isset($item['payments'])) {
                    ($item['payments'])($bill);
                }

                return $bill;
            });
        } catch (\DomainException $e) {
            return ['action' => 'SKIP', 'note' => $this->whySkipped($item)] + $row;
        } catch (\Throwable $e) {
            return ['action' => 'ERROR', 'note' => $e->getMessage()] + $row;
        }

        $bill = $bill->fresh(['items', 'payments']);
        $out = $this->describe($item, $bill, $row);
        $out['new_ref'] = $bill->bill_ref_id;
        $out['renumbers'] = $item['renumber'] ? "{$item['renumber']}: {$item['old_ref']} → {$bill->bill_ref_id}" : '';

        return $out;
    }

    private function recordPayment(BillMaster $bill, string $transactionId, float $amount, $paidAt, string $note): void
    {
        if ($amount <= 0) {
            return;
        }
        try {
            $paidAt = $paidAt ? \Illuminate\Support\Carbon::parse($paidAt) : now();
        } catch (\Throwable) {
            $paidAt = now();
        }
        \App\Models\BillPayment::create([
            'bill_ref_id' => $bill->bill_ref_id,
            'method' => 'module_receipt',
            'transaction_id' => $transactionId,
            'amount' => round($amount, 2),
            'paid_at' => $paidAt,
            'status' => \App\Models\BillPayment::STATUS_SUCCESSFUL,
            'recorded_by' => self::ACTOR,
            'notes' => $note,
        ]);
        app(\App\Services\Billing\BillMasterService::class)->refreshTotals($bill, self::ACTOR);
    }

    /** Every module row whose ref the load overwrites, saved before anything changes. */
    private function backup(): string
    {
        $dir = storage_path('app/revm/backup_' . now()->format('Ymd_His'));
        mkdir($dir, 0775, true);
        $db = DB::connection('sqlsrv');

        $sets = [
            'deeds_bill_balances_metadata' => $db->table('deeds_bill_balances_metadata')->orderBy('id')->get(),
            'spa_bills' => $db->table('spa_bills')->orderBy('id')->get(),
            'cadastral_bills' => $db->table('cadastral_bills')->orderBy('id')->get(),
            'billing_oss' => $db->table('billing')->where('source', 'OSS_OP_APPLICATION_FEE')->orderBy('ID')->get(),
        ];
        foreach ($sets as $name => $rows) {
            file_put_contents("{$dir}/{$name}.json", json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $fh = fopen("{$dir}/{$name}.csv", 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            $first = $rows->first();
            if ($first) {
                fputcsv($fh, array_keys((array) $first));
                foreach ($rows as $r) {
                    fputcsv($fh, array_values((array) $r));
                }
            }
            fclose($fh);
            if (!is_file("{$dir}/{$name}.json")) {
                throw new \RuntimeException("Backup of {$name} failed; nothing was loaded.");
            }
        }

        return $dir;
    }

    private function summary(array $rows): void
    {
        $table = [];
        foreach (collect($rows)->groupBy('source') as $source => $group) {
            $load = $group->where('action', 'LOAD');
            $table[] = [
                $source,
                $group->count(),
                $load->count(),
                $load->where('loads_as', 'unpaid')->count(),
                $load->where('loads_as', 'paid')->count() + $load->where('loads_as', 'part_paid')->count(),
                $group->where('action', 'SKIP')->count(),
                $group->where('action', 'ERROR')->count(),
                number_format($load->sum(fn ($r) => (float) $r['amount']), 2),
            ];
        }
        $this->table(['Source', 'Records', 'Load', 'Unpaid', 'Paid', 'Skip', 'Error', 'Amount (₦)'], $table);
    }
}
