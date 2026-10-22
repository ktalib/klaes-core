<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\BillLegacy;
use App\Models\BillMaster;
use App\Models\BillMasterType;
use App\Models\BillPayment;
use App\Models\SmsSetting;
use App\Services\Billing\BillMasterService;
use App\Services\Billing\BillNotifier;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * KLAES REV-M over the Bill Master register (bill_master).
 *
 * Each bill type's page lists its bills and runs the bill flow
 * (BillMasterService): pick a file -> generate (BILL- ref, items from Bill
 * Settings) -> POS invoice -> pay (Paystack / POS) -> invoice with a PAID stamp.
 * Bill Legacy lists bill_legacy (bills captured under File Indexing).
 * Receipting is ReceiptController.
 *
 * Module actions: view opens a page, create generates, edit records a payment,
 * print prints the invoice.
 */
class BillMasterController extends Controller
{
    use RunsWorkflowActions;

    /** Bill types with a page of their own, and the module role that opens it. */
    private const TYPE_ROLES = [
        'land_use_charge' => ['Land Use Charge (LUC)', 'Billing'],
        'bill_balance' => ['Bill Balance', 'Billing'],
        'oss_bill' => ['Billing'],
        'betterment' => ['Billing'],
        'initial_bill' => ['Billing'],
        'initial_primary' => ['Billing'],
        'initial_pua' => ['Billing'],
        'initial_sua' => ['Billing'],
        'deed_of_purchase' => ['Billing'],
        'consent_bill' => ['Billing'],
        'fees_plan_description' => ['Billing'],
        'legal_search' => ['Billing'],
        'contravention_bill' => ['Billing'],
        'phs_onboarding' => ['Billing'],
        'phs_token_topup' => ['Billing'],
        'ground_rent' => ['Billing'],
        're_grant' => ['Billing'],
    ];

    private const DESCRIPTIONS = [
        'land_use_charge' => 'Land Use Charge bills',
        'bill_balance' => 'Bill balance bills',
        'oss_bill' => 'One-stop shop bills',
        'betterment' => 'Betterment charge bills',
        'initial_bill' => 'Initial bills: Primary, PuA and SuA',
        'initial_primary' => 'Initial bills for primary applications',
        'initial_pua' => 'Initial bills for PuA',
        'initial_sua' => 'Initial bills for SuA',
        'deed_of_purchase' => '5% Deed of Purchase bills',
        'consent_bill' => 'Consent bills',
        'fees_plan_description' => 'Fees, Plan & Description bills',
        'legal_search' => 'Legal search bills (Transaction Token Control)',
        'contravention_bill' => 'Contravention bills',
        'phs_onboarding' => 'PHS portal onboarding and subscription bills',
        'phs_token_topup' => 'PHS portal token top-up bills',
        'ground_rent' => 'Ground rent bills (recurring, rate x plot size)',
        're_grant' => 'Re-grant bills (recurring)',
    ];

    public function index(Request $request, string $type)
    {
        abort_unless(isset(self::TYPE_ROLES[$type]), 404);
        $this->authorizeAny(self::TYPE_ROLES[$type]);

        $billType = BillMasterType::query()->where('code', $type)->firstOrFail();
        $children = $billType->children()->get();
        $codes = $children->pluck('code')->prepend($type)->all();

        $sub = (string) $request->query('sub', '');
        $query = BillMaster::query()->with('type')
            ->whereIn('bill_type_code', $sub !== '' && in_array($sub, $codes, true) ? [$sub] : $codes);

        [$rows, $stats, $filters] = $this->listing($request, $query);

        $roles = self::TYPE_ROLES[$type];

        return view('revenue.bills.index', [
            'can' => [
                'create' => $this->allowed($roles, 'create') && $billType->is_active && $type !== 'land_use_charge',
                'pay' => $this->allowed($roles, 'edit') && $billType->accepts_payment,
                'print' => $this->allowed($roles, 'print'),
                'generate' => $this->allowed($roles, 'create'),
            ],
            'billType' => $billType,
            'children' => $children,
            'sub' => $sub,
            'rows' => $rows,
            'stats' => $stats,
            'filters' => $filters,
            'pageTitle' => $billType->name,
            'pageDescription' => self::DESCRIPTIONS[$type] ?? $billType->name,
            'moduleRaised' => self::MODULE_RAISED[$type] ?? null,
            'indexUrl' => route('revenue.bills.index', $type),
        ] + (in_array($type, self::CODE_BILLED, true) ? [
            // Billed from the officer's items under the type's Revenue Code: Generate opens the on-the-fly form, type fixed.
            'codeBilled' => true,
            'otfTypes' => collect([$billType]),
            'otfTypeCodes' => array_filter([$type => $billType->revenueCode()]),
            'otfCycles' => array_filter([$type => $billType->is_recurring ? config('billing.cycles.' . $billType->payment_cycle . '.label') : null]),
            'otfAllTypes' => collect([$billType]),
        ] : ['codeBilled' => false]));
    }

    /** REV-M bill types billed with the on-the-fly form (items entered by the officer), not Bill Formulas. */
    private const CODE_BILLED = ['ground_rent', 're_grant'];

    /**
     * Bill types raised in their own module or portal: the items are entered on the
     * module's form and REV-M takes the bill (and its total) as it is. These are
     * never generated from Bill Settings items; the Generate modal lists the file's
     * module bills for viewing and printing instead. [type => where it is raised]
     */
    private const MODULE_RAISED = [
        'bill_balance' => 'Deeds Bill Balance',
        'oss_bill' => 'One-Stop Shop',
        'contravention_bill' => 'SPAS',
        'consent_bill' => 'Consent Applications',
        'fees_plan_description' => 'Cadastral P&D Bills',
        'initial_bill' => 'ST Programmes',
        'legal_search' => 'the Legal Search portal',
        'phs_onboarding' => 'the PHS portal',
        'phs_token_topup' => 'the PHS portal',
    ];

    /** Bill Master → All Bills: the whole register, every type. */
    public function all(Request $request)
    {
        $this->authorizeAny(['Billing']);

        $types = BillMasterType::query()->orderBy('sort_order')->get();
        $type = (string) $request->query('type', '');
        $query = BillMaster::query()->with('type');
        if ($type !== '' && $types->contains('code', $type)) {
            $query->where('bill_type_code', $type);
        }
        // Raised: on-the-fly / next cycle of a recurring bill / everything else.
        $raised = (string) $request->query('raised', '');
        if (in_array($raised, [BillMaster::RAISED_ON_THE_FLY, BillMaster::RAISED_CYCLE], true)) {
            $query->where('raised_via', $raised);
        } elseif ($raised === 'recurring') {
            $query->whereNotNull('payment_cycle');
        }

        [$rows, $stats, $filters] = $this->listing($request, $query);

        return view('revenue.bills.all', [
            'rows' => $rows,
            'stats' => $stats,
            'filters' => $filters + ['type' => $type, 'raised' => $raised],
            'types' => $types,
            'can' => [
                'pay' => $this->allowed(['Billing'], 'edit'),
                'print' => $this->allowed(['Billing'], 'print'),
                'generate' => $this->allowed(['Billing'], 'create'),
                'onTheFly' => $this->allowed(['Billing'], 'create'),
            ],
            'onTheFlyTypes' => self::onTheFlyTypes(),
            // type code => its Revenue Code (one per bill type; Primary / PuA / SuA share Initial Bill's)
            'typeCodes' => self::onTheFlyTypes()->mapWithKeys(fn ($t) => [$t->code => $t->revenueCode()])->filter(),
            // type code => cycle label, for types that are recurring (children follow their parent)
            'cycleTypes' => self::onTheFlyTypes()->mapWithKeys(function ($t) {
                $setting = app(BillMasterService::class)->cycleType($t);

                return [$t->code => $setting && $setting->is_recurring ? config('billing.cycles.' . $setting->payment_cycle . '.label') : null];
            })->filter(),
            'indexUrl' => route('revenue.bills.all'),
        ]);
    }

    // ---- Bill flow -----------------------------------------------------------

    /** Generate modal: the picked file's details, its existing bills, and the price preview. */
    public function lookup(Request $request, string $type, BillMasterService $bills)
    {
        $billType = $this->typeOrFail($type, 'create');
        $kind = $this->kind($billType, (string) $request->query('kind', ''));
        $fileNumber = trim((string) $request->query('file_number', ''));

        $existing = $fileNumber === '' ? collect() : BillMaster::query()->with('type')
            ->where('file_number', $fileNumber)
            ->whereIn('bill_type_code', $billType->children()->pluck('code')->prepend($billType->code))
            ->orderByDesc('id')->limit(10)->get();

        $moduleRaised = self::MODULE_RAISED[$billType->code] ?? null;
        $spec = $priced = $error = null;
        try {
            if ($kind && !$moduleRaised) {
                $spec = $bills->itemsFor($kind);
                // With no amount for the officer to enter, the total is known now.
                if (!collect($spec['items'])->contains('input', true)) {
                    $priced = $bills->price($kind);
                }
            }
        } catch (WorkflowGuardException $e) {
            $error = $e->getMessage();
        }

        return view('revenue.bills.partials._generate_preview', [
            'billType' => $billType,
            'kind' => $kind,
            'kinds' => $billType->children()->get(),
            'file' => $fileNumber === '' ? null : $bills->fileDetails($fileNumber),
            'existing' => $existing,
            'spec' => $spec,
            'priced' => $priced,
            'error' => $error,
            'moduleRaised' => $moduleRaised,
            'canGenerate' => $this->allowed(self::TYPE_ROLES[$billType->code], 'create'),
            'canPrint' => $this->allowed(self::TYPE_ROLES[$billType->code], 'print'),
            'canPay' => $this->allowed(self::TYPE_ROLES[$billType->code], 'edit') && $billType->accepts_payment,
        ]);
    }

    public function store(Request $request, string $type, BillMasterService $bills)
    {
        $billType = $this->typeOrFail($type, 'create');
        if (isset(self::MODULE_RAISED[$billType->code])) {
            return $this->actionFailed("{$billType->name} bills are raised in " . self::MODULE_RAISED[$billType->code] . '; REV-M takes them with their total.');
        }

        $data = $request->validate([
            'file_number' => 'required|string|max:100',
            'kind' => 'nullable|string|max:60',
            'payer_name' => 'nullable|string|max:255',
            'payer_phone' => 'nullable|string|max:30',
            'payer_email' => 'nullable|email|max:150',
            'payer_address' => 'nullable|string|max:500',
            'inputs' => 'nullable|array',
            'inputs.*' => 'nullable|numeric|min:0',
        ]);

        $ref = null;
        $response = $this->attempt(function () use ($billType, $data, $bills, &$ref) {
            $kind = $this->kind($billType, (string) ($data['kind'] ?? ''));
            if (!$kind) {
                throw WorkflowGuardException::because("Choose which kind of {$billType->name} to generate.");
            }
            $bill = $bills->generate($kind, $data['file_number'], $data, $this->actor());
            $ref = $bill->bill_ref_id;

            return "Bill {$bill->bill_ref_id} generated for {$bill->file_number}: ₦" . number_format((float) $bill->amount, 2) . '.';
        }, 'Bill generated.');

        return $this->withRef($response, $ref);
    }

    /** The generated bill's ref on a JSON success, so the page opens its bill card. */
    private function withRef($response, ?string $ref)
    {
        if ($ref && $response instanceof \Illuminate\Http\JsonResponse && ($response->getData(true)['success'] ?? false)) {
            $response->setData($response->getData(true) + ['ref' => $ref]);
        }

        return $response;
    }

    /** View modal: the bill, its items and payments, the file record, and the payment form. */
    public function show(string $ref, BillMasterService $bills)
    {
        $bill = $this->billOrFail($ref, 'view');
        $roles = $this->rolesFor($bill);

        return view('revenue.bills.partials._bill_detail', [
            'bill' => $bill->load(['type', 'items', 'payments']),
            'file' => $bill->file_number ? $bills->fileDetails($bill->file_number) : null,
            'methods' => $bill->type ? $bills->methodsFor($bill->type) : [],
            'outstanding' => $bills->outstanding($bill),
            'canPay' => $this->allowed($roles, 'edit') && optional($bill->type)->accepts_payment,
            'canPrint' => $this->allowed($roles, 'print'),
            'canGenerate' => $this->allowed($roles, 'create'),
            'canSms' => $this->allowed($roles, 'edit'),
            'smsLog' => BillNotifier::log($bill->bill_ref_id),
            'nextCycle' => BillMaster::query()->where('previous_bill_ref', $bill->bill_ref_id)->value('bill_ref_id'),
        ]);
    }

    /**
     * Send a bill's SMS again by hand (the payer's number was missing or wrong, or the
     * gateway failed): the bill notice, or the latest payment's notice. A new event,
     * so it is not blocked by the first one; the result shows on the Notifications tab.
     */
    public function sendSms(Request $request, string $ref, BillNotifier $notifier)
    {
        $bill = $this->billOrFail($ref, 'edit');
        $data = $request->validate([
            'message' => 'required|in:generated,payment,due',
            'payer_phone' => 'nullable|string|max:30',
        ]);

        return $this->attempt(function () use ($bill, $data, $notifier) {
            if (!$bill->isGenerated()) {
                throw WorkflowGuardException::because("Generate {$bill->bill_ref_id} first.");
            }
            $phone = trim((string) ($data['payer_phone'] ?? ''));
            if ($phone !== '' && $phone !== $bill->payer_phone) {
                $bill->update(['payer_phone' => $phone, 'updated_by' => $this->actor()]);
            }
            if (!$bill->payer_phone) {
                throw WorkflowGuardException::because('Enter the payer\'s phone number.');
            }
            $key = ['generated' => SmsSetting::KEY_BILL_GENERATED, 'payment' => SmsSetting::KEY_BILL_PAYMENT, 'due' => SmsSetting::KEY_BILL_DUE][$data['message']];
            if (!SmsSetting::isEnabled($key)) {
                throw WorkflowGuardException::because('This SMS is switched off. Switch it on in the SMS Control Centre (Revenue (REV-M)).');
            }

            $tag = ':manual:' . now()->format('YmdHis');
            if ($data['message'] === 'payment') {
                $payment = $bill->payments()->where('status', BillPayment::STATUS_SUCCESSFUL)->get()->last();
                if (!$payment) {
                    throw WorkflowGuardException::because('This bill has no successful payment to report.');
                }
                $notifier->payment($bill, $payment, false, 'bill_payment:' . $payment->id . $tag);
            } elseif ($data['message'] === 'due') {
                if (!$bill->next_due_date) {
                    throw WorkflowGuardException::because('This bill has no next payment due.');
                }
                $notifier->due($bill, false, 'bill_due:' . $bill->bill_ref_id . $tag);
            } else {
                $notifier->generated($bill, true, false, 'bill_generated:' . $bill->bill_ref_id . $tag);
            }

            return "SMS queued to {$bill->payer_phone}. The gateway's response shows on the Notifications tab in a moment.";
        }, 'SMS queued.');
    }

    // ---- On-the-fly bills ----------------------------------------------------

    /** On-the-fly modal: the picked file's details (owner, phone, Plot No., District, LGA, Location). */
    public function fileInfo(Request $request, BillMasterService $bills)
    {
        abort_unless($this->allowed(['Billing'], 'create'), 403);
        $fileNumber = trim((string) $request->query('file_number', ''));
        abort_if($fileNumber === '', 422, 'Select the file number.');

        return response()->json($bills->fileDetails($fileNumber));
    }

    /** Raise an on-the-fly bill: a bill type, the file, and Revenue Code lines. */
    public function storeOnTheFly(Request $request, BillMasterService $bills)
    {
        abort_unless($this->allowed(['Billing'], 'create'), 403);
        $data = $request->validate([
            'bill_type' => 'required|string|max:60',
            'file_number' => 'required|string|max:100',
            'payer_name' => 'nullable|string|max:255',
            'payer_phone' => 'nullable|string|max:30',
            'payer_email' => 'nullable|email|max:150',
            'payer_address' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:500',
            'due_date' => 'nullable|date',
            'period_start' => 'nullable|date',
            'lines' => 'required|array|min:1|max:30',
            'lines.*.name' => 'nullable|string|max:255',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.rate' => 'nullable|numeric|min:0',
        ], [], ['lines' => 'items']);

        $ref = null;
        $response = $this->attempt(function () use ($data, $bills, &$ref) {
            $type = BillMasterType::query()->where('code', $data['bill_type'])->first();
            if (!$type) {
                throw WorkflowGuardException::because('Choose the bill type.');
            }
            $bill = $bills->onTheFly($type, $data['file_number'], $data['lines'], $data, $this->actor());
            $ref = $bill->bill_ref_id;

            return "On-the-fly bill {$bill->bill_ref_id} ({$type->name}) generated for {$bill->file_number}: ₦" . number_format((float) $bill->amount, 2) . '.';
        }, 'Bill generated.');

        return $this->withRef($response, $ref);
    }

    /** Bill types an on-the-fly bill can be raised under (concrete types; LuC on hold). */
    public static function onTheFlyTypes()
    {
        $types = BillMasterType::query()->where('is_active', true)->where('bill_group', BillMasterType::GROUP_AUTOMATED)->orderBy('sort_order')->get();
        $parents = $types->pluck('parent_code')->filter()->unique();

        return $types->reject(fn ($t) => $t->code === 'land_use_charge' || $parents->contains($t->code))->values();
    }

    /** The phone to put on a bill being generated: its own, else one found in the file's records. */
    public function contact(string $ref, BillMasterService $bills)
    {
        $bill = $this->billOrFail($ref, 'view');
        $found = trim((string) $bill->payer_phone) !== '' ? ['payer_phone' => $bill->payer_phone, 'phone_source' => 'this bill'] : $bills->phoneFor($bill->file_number);

        return response()->json(['phone' => $found['payer_phone'], 'source' => $found['phone_source']]);
    }

    /** Generate a bill a module sent over; its invoice opens from then on. */
    public function generateExisting(Request $request, string $ref, BillMasterService $bills)
    {
        $bill = $this->billOrFail($ref, 'create');
        $phone = $request->validate(['payer_phone' => 'nullable|string|max:30'])['payer_phone'] ?? null;

        return $this->withRef($this->attempt(function () use ($bill, $bills, $phone) {
            $bills->markGenerated($bill, $this->actor(), $phone);

            return "Bill {$bill->bill_ref_id} generated: ₦" . number_format((float) $bill->amount, 2) . '. You can now print the invoice or take payment.';
        }, 'Bill generated.'), $bill->bill_ref_id);
    }

    /** POS-format invoice (not the official receipt); only for a generated bill; never shows payment status. */
    public function invoice(string $ref, BillMasterService $bills)
    {
        $bill = $this->billOrFail($ref, 'print');
        abort_unless($bill->isGenerated(), 403, "Generate {$bill->bill_ref_id} first. The invoice opens once the bill has been generated.");

        return view('revenue.bills.invoice', [
            'bill' => $bill->load(['type', 'items', 'payments' => fn ($q) => $q->where('status', BillPayment::STATUS_SUCCESSFUL)]),
            'outstanding' => $bills->outstanding($bill),
        ]);
    }

    public function pay(Request $request, string $ref, BillMasterService $bills)
    {
        $bill = $this->billOrFail($ref, 'edit');
        if (!$bill->isGenerated()) {
            return $this->actionFailed("Generate {$bill->bill_ref_id} first. It can be paid once it has been generated.");
        }

        $data = $request->validate([
            'method' => 'required|string|in:' . implode(',', array_keys(config('billing.payment_methods', []))),
            'amount' => 'required|numeric|min:0.01',
            'transaction_id' => 'nullable|string|max:100',
            'payer_email' => 'nullable|email|max:150',
        ]);

        if ($data['method'] === BillPayment::METHOD_PAYSTACK) {
            try {
                $url = $bills->startPaystack($bill, (float) $data['amount'], (string) ($data['payer_email'] ?? $bill->payer_email),
                    route('revenue.bills.paystack.callback'), $this->actor());
            } catch (WorkflowGuardException $e) {
                return $this->actionFailed($e->getMessage());
            }

            return $this->actionSucceeded('Opening Paystack…', $url);
        }

        if ($data['method'] !== BillPayment::METHOD_POS) {
            return $this->actionFailed(config("billing.payment_methods.{$data['method']}.label", $data['method']) . ' is not available yet.');
        }

        return $this->attempt(function () use ($bill, $data, $bills) {
            $payment = $bills->recordPos($bill, (string) ($data['transaction_id'] ?? ''), (float) $data['amount'], $this->actor());

            return 'POS payment of ₦' . number_format((float) $payment->amount, 2) . " recorded on {$bill->bill_ref_id}.";
        }, 'Payment recorded.');
    }

    /** Paystack sends the payer back here; verify and settle. */
    public function paystackCallback(Request $request, BillMasterService $bills)
    {
        $reference = (string) ($request->query('reference') ?? $request->query('trxref') ?? '');
        $payment = BillPayment::query()->where('method', BillPayment::METHOD_PAYSTACK)->where('transaction_id', $reference)->first();
        $bill = $payment ? BillMaster::query()->with('type')->where('bill_ref_id', $payment->bill_ref_id)->first() : null;
        $back = $bill ? route('revenue.bills.index', $bill->type?->parent_code ?: $bill->bill_type_code) : route('revenue.receipts.index', 'e-receipt');

        try {
            $bills->confirmPaystack($reference, $this->actor());
        } catch (WorkflowGuardException $e) {
            return redirect($back)->with('error', $e->getMessage());
        }

        return redirect($back)->with('success', "Paystack payment confirmed for {$bill->bill_ref_id}.");
    }

    private function typeOrFail(string $type, string $action): BillMasterType
    {
        abort_unless(isset(self::TYPE_ROLES[$type]), 404);
        abort_unless($this->allowed(self::TYPE_ROLES[$type], $action), 403);

        return BillMasterType::query()->where('code', $type)->firstOrFail();
    }

    private function billOrFail(string $ref, string $action): BillMaster
    {
        $bill = BillMaster::query()->with('type')->where('bill_ref_id', $ref)->firstOrFail();
        abort_unless($this->allowed($this->rolesFor($bill), $action), 403);

        return $bill;
    }

    /** The concrete type to bill: the type itself, or the chosen child (Primary / PuA / SuA). */
    private function kind(BillMasterType $type, string $kind): ?BillMasterType
    {
        $children = $type->children()->get();
        if ($children->isEmpty()) {
            return $type;
        }

        return $children->firstWhere('code', $kind);
    }

    private function rolesFor(BillMaster $bill): array
    {
        return self::TYPE_ROLES[$bill->bill_type_code] ?? ['Billing'];
    }

    private function allowed(array $roles, string $action): bool
    {
        $user = auth()->user();
        foreach ($roles as $role) {
            if ($user && $user->canDo($role, $action)) {
                return true;
            }
        }

        return false;
    }

    private function actor(): string
    {
        return (string) (Auth::user()->name ?? Auth::id());
    }

    /** Legacy Billing → Bill Legacy: bills captured under File Indexing (bill_legacy). */
    public function legacy(Request $request)
    {
        $this->authorizeAny(['Billing']);

        $filters = ['type' => (string) $request->query('type', '')];
        $query = BillLegacy::query();
        if (array_key_exists($filters['type'], config('billing.legacy_types'))) {
            $query->where('bill_type_code', $filters['type']);
        }

        [$rows, $stats, $listFilters] = $this->listing($request, $query);

        return view('revenue.legacy.index', [
            'rows' => $rows,
            'stats' => $stats,
            'filters' => $listFilters + $filters,
            'types' => config('billing.legacy_types'),
            'pageTitle' => 'Bill Legacy',
            'pageDescription' => 'All bills captured under indexing',
            'indexUrl' => route('revenue.legacy.index'),
        ]);
    }

    /** Search, status / type filters, totals and the page of rows. */
    private function listing(Request $request, $query, bool $typeFilter = false): array
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'type' => $typeFilter ? (string) $request->query('type', '') : '',
        ];

        if ($filters['q'] !== '') {
            $like = '%' . mb_strtolower($filters['q']) . '%';
            $query->where(function ($w) use ($like) {
                foreach (['bill_ref_id', 'transaction_id', 'receipt_no', 'file_number', 'payer_name', 'plot_no', 'district', 'lga', 'location'] as $col) {
                    $w->orWhereRaw("LOWER({$col}) LIKE ?", [$like]);
                }
            });
        }
        // System bills: Pending = unpaid and not generated yet; Unpaid = generated, not paid.
        $system = $query->getModel() instanceof BillMaster;
        if ($system && $filters['status'] === 'pending') {
            $query->where('status', BillMaster::STATUS_UNPAID)->whereNull('generated_at');
        } elseif (array_key_exists($filters['status'], self::statuses())) {
            $query->where('status', $filters['status']);
            if ($system && $filters['status'] === BillMaster::STATUS_UNPAID) {
                $query->whereNotNull('generated_at');
            }
        }
        if ($filters['type'] !== '') {
            $query->where('bill_type_code', $filters['type']);
        }

        $totals = (clone $query)->selectRaw('COUNT(*) n, COALESCE(SUM(amount), 0) billed, COALESCE(SUM(amount_paid), 0) paid')->first();
        $stats = [
            'count' => (int) $totals->n,
            'billed' => (float) $totals->billed,
            'paid' => (float) $totals->paid,
            'outstanding' => max(0, (float) $totals->billed - (float) $totals->paid),
        ];

        $rows = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return [$rows, $stats, $filters];
    }

    public static function statuses(): array
    {
        return config('billing.statuses');
    }

    /** Statuses as system bills show them: Pending (not generated yet) first. */
    public static function filterStatuses(): array
    {
        return ['pending' => 'Pending'] + self::statuses();
    }

    private function authorizeAny(array $roles): void
    {
        $user = auth()->user();
        foreach ($roles as $role) {
            if ($user && $user->canDo($role, 'view')) {
                return;
            }
        }
        abort(403);
    }
}
