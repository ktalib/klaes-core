<?php

namespace App\Models\InstrumentWorkflow;

use App\Models\CustomerRecord;
use App\Models\FileIndexingRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * An Instrument Registration application submitted to the Lands Department.
 *
 * Owns the stage machine. The line is not strictly forward - Accounts can
 * reject a captured payment and BIR can decline an instrument - so allowed
 * moves are an explicit TRANSITIONS map. Only InstrumentWorkflowService
 * changes `stage`.
 *
 * The instrument itself is captured in Deeds → Instrument Capture and sent to
 * BIR from there; the application follows it (pending_bir → registered).
 */
class InstrumentApplication extends Model
{
    use SoftDeletes;

    protected $connection = 'sqlsrv';
    protected $table = 'instrument_applications';

    // ---- Stages (numbers follow the granular workflow) --------------------
    public const STAGE_FEE_PENDING = 'fee_pending';             // 1   application + fee bill raised
    public const STAGE_FEE_PAID = 'fee_paid';                   // 1   deposit slip captured, awaiting Accounts
    public const STAGE_FEE_VALIDATED = 'fee_validated';         // 1   Accounts validated, receipt generated
    public const STAGE_LANDS_CHECK = 'lands_check';             // 2-3 receipt issued; LIC form raised
    public const STAGE_SURVEY_CHECK = 'survey_check';           // 4-5
    public const STAGE_PLANNING_CHECK = 'planning_check';       // 5
    public const STAGE_LIC_ISSUED = 'lic_issued';               // 6
    public const STAGE_CODES_VERIFIED = 'codes_verified';       // 7 TIN (ALAES also issues an ABSSIN here; Kano has none)
    public const STAGE_REGISTRATION_INVOICED = 'registration_invoiced';         // 9
    public const STAGE_REGISTRATION_PAID = 'registration_paid';                 // 9   ready for Instrument Capture
    public const STAGE_PENDING_BIR = 'pending_bir';             // 10
    public const STAGE_BIR_DECLINED = 'bir_declined';           // 10
    public const STAGE_BIR_APPROVED = 'bir_approved';           // 11-12
    public const STAGE_REGISTERED = 'registered';               // 13-14
    public const STAGE_COR_PRINTED = 'cor_printed';             // 14
    public const STAGE_COMPLETED = 'completed';                 // 15

    public const STAGE_CHECK_FAILED = 'check_failed';
    public const STAGE_CANCELLED = 'cancelled';

    public const ORDER = [
        self::STAGE_FEE_PENDING,
        self::STAGE_FEE_PAID,
        self::STAGE_FEE_VALIDATED,
        self::STAGE_LANDS_CHECK,
        self::STAGE_SURVEY_CHECK,
        self::STAGE_PLANNING_CHECK,
        self::STAGE_LIC_ISSUED,
        self::STAGE_CODES_VERIFIED,
        self::STAGE_REGISTRATION_INVOICED,
        self::STAGE_REGISTRATION_PAID,
        self::STAGE_PENDING_BIR,
        self::STAGE_BIR_APPROVED,
        self::STAGE_REGISTERED,
        self::STAGE_COR_PRINTED,
        self::STAGE_COMPLETED,
    ];

    public const LABELS = [
        self::STAGE_FEE_PENDING => 'Application fee due',
        self::STAGE_FEE_PAID => 'Fee paid — awaiting Accounts',
        self::STAGE_FEE_VALIDATED => 'Fee validated — receipt to issue',
        self::STAGE_LANDS_CHECK => 'Lands Registry check',
        self::STAGE_SURVEY_CHECK => 'Survey check',
        self::STAGE_PLANNING_CHECK => 'Physical Planning check',
        self::STAGE_LIC_ISSUED => 'LIC issued',
        self::STAGE_CODES_VERIFIED => 'TIN verified',
        self::STAGE_REGISTRATION_INVOICED => 'Registration fee bill generated',
        self::STAGE_REGISTRATION_PAID => 'Registration fee paid — ready for capture',
        self::STAGE_PENDING_BIR => 'Awaiting BIR review',
        self::STAGE_BIR_DECLINED => 'Declined by BIR',
        self::STAGE_BIR_APPROVED => 'Approved by BIR',
        self::STAGE_REGISTERED => 'Registered',
        self::STAGE_COR_PRINTED => 'CoR sticker printed',
        self::STAGE_COMPLETED => 'Completed (signed)',
        self::STAGE_CHECK_FAILED => 'Failed ministry check',
        self::STAGE_CANCELLED => 'Cancelled',
    ];

    /** Owner lane: mslup (blue), klaes/kangis (green), bir (orange). */
    public const OWNERS = [
        self::STAGE_FEE_PENDING => 'klaes',
        self::STAGE_FEE_PAID => 'klaes',
        self::STAGE_FEE_VALIDATED => 'klaes',
        self::STAGE_LANDS_CHECK => 'mslup',
        self::STAGE_SURVEY_CHECK => 'mslup',
        self::STAGE_PLANNING_CHECK => 'mslup',
        self::STAGE_LIC_ISSUED => 'mslup',
        self::STAGE_CODES_VERIFIED => 'bir',
        self::STAGE_REGISTRATION_INVOICED => 'bir',
        self::STAGE_REGISTRATION_PAID => 'klaes',
        self::STAGE_PENDING_BIR => 'bir',
        self::STAGE_BIR_DECLINED => 'bir',
        self::STAGE_BIR_APPROVED => 'bir',
        self::STAGE_REGISTERED => 'klaes',
        self::STAGE_COR_PRINTED => 'klaes',
        self::STAGE_COMPLETED => 'mslup',
    ];

    public const TRANSITIONS = [
        self::STAGE_FEE_PENDING => [self::STAGE_FEE_PAID, self::STAGE_CANCELLED],
        // Accounts either validates the deposit or rejects it back for re-capture.
        self::STAGE_FEE_PAID => [self::STAGE_FEE_VALIDATED, self::STAGE_FEE_PENDING, self::STAGE_CANCELLED],
        // The checks run in the order set in Configurable Entries, and any can be off,
        // so the receipt and each check may lead to any check or straight to the LIC.
        // WorkflowPipeline::nextStage() decides which; this map only bounds it.
        self::STAGE_FEE_VALIDATED => [self::STAGE_LANDS_CHECK, self::STAGE_SURVEY_CHECK, self::STAGE_PLANNING_CHECK, self::STAGE_LIC_ISSUED],
        self::STAGE_LANDS_CHECK => [self::STAGE_SURVEY_CHECK, self::STAGE_PLANNING_CHECK, self::STAGE_LIC_ISSUED, self::STAGE_CHECK_FAILED, self::STAGE_CANCELLED],
        self::STAGE_SURVEY_CHECK => [self::STAGE_LANDS_CHECK, self::STAGE_PLANNING_CHECK, self::STAGE_LIC_ISSUED, self::STAGE_CHECK_FAILED, self::STAGE_CANCELLED],
        self::STAGE_PLANNING_CHECK => [self::STAGE_LANDS_CHECK, self::STAGE_SURVEY_CHECK, self::STAGE_LIC_ISSUED, self::STAGE_CHECK_FAILED, self::STAGE_CANCELLED],
        self::STAGE_LIC_ISSUED => [self::STAGE_CODES_VERIFIED, self::STAGE_CANCELLED],
        self::STAGE_CODES_VERIFIED => [self::STAGE_REGISTRATION_INVOICED, self::STAGE_CANCELLED],
        self::STAGE_REGISTRATION_INVOICED => [self::STAGE_REGISTRATION_PAID, self::STAGE_CODES_VERIFIED, self::STAGE_CANCELLED],
        self::STAGE_REGISTRATION_PAID => [self::STAGE_PENDING_BIR],
        self::STAGE_PENDING_BIR => [self::STAGE_BIR_APPROVED, self::STAGE_BIR_DECLINED],
        self::STAGE_BIR_DECLINED => [self::STAGE_PENDING_BIR],
        self::STAGE_BIR_APPROVED => [self::STAGE_REGISTERED],
        self::STAGE_REGISTERED => [self::STAGE_COR_PRINTED],
        self::STAGE_COR_PRINTED => [self::STAGE_COMPLETED],
        self::STAGE_COMPLETED => [],
        self::STAGE_CHECK_FAILED => [],
        self::STAGE_CANCELLED => [],
    ];

    /** Which check each check stage is. The order between them is WorkflowPipeline's. */
    public const CHECK_STAGES = [
        self::STAGE_LANDS_CHECK => ['type' => 'lands'],
        self::STAGE_SURVEY_CHECK => ['type' => 'survey'],
        self::STAGE_PLANNING_CHECK => ['type' => 'planning'],
    ];

    /** Identity, codes and billing are frozen once the instrument is with BIR. */
    public const LOCKED_STAGES = [
        self::STAGE_PENDING_BIR,
        self::STAGE_BIR_APPROVED,
        self::STAGE_REGISTERED,
        self::STAGE_COR_PRINTED,
        self::STAGE_COMPLETED,
    ];

    protected $fillable = [
        'reference',
        'customer_record_id',
        'file_indexing_id',
        'file_number',
        'instrument_type',
        'consideration_amount',
        'instrument_capture_id',
        'deed_registration_id',
        'applicant_type',
        'applicant_name',
        'phone',
        'email',
        'address',
        'identity_type',
        'rc_number',
        'failure_reason',
        'lic_number',
        'lic_issued_at',
        'lic_issued_by',

        'tin',
        'payment_reference',
        'signed_at',
        'signed_by',
        'signing_officer_name',
        'is_demo',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['nin_encrypted', 'nin_hash'];

    protected $casts = [
        'is_demo' => 'boolean',
        'consideration_amount' => 'decimal:2',
        'stage_changed_at' => 'datetime',
        'lic_issued_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    // ---- Stage helpers ----------------------------------------------------

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->stage] ?? [], true);
    }

    public function stageLabel(): string
    {
        return self::labelFor($this->stage);
    }

    /** A stage's name; check stages take the name set in Configurable Entries. */
    public static function labelFor(string $stage): string
    {
        if (isset(self::CHECK_STAGES[$stage])) {
            return app(\App\Services\InstrumentWorkflow\WorkflowPipeline::class)->label(self::CHECK_STAGES[$stage]['type']);
        }

        return self::LABELS[$stage] ?? $stage;
    }

    /** The main line with the checks in their configured order. */
    public static function order(): array
    {
        return app(\App\Services\InstrumentWorkflow\WorkflowPipeline::class)->order();
    }

    /** Position on the main line; a BIR decline sits at the BIR step, failures at -1. */
    public function rank(): int
    {
        $stage = $this->stage === self::STAGE_BIR_DECLINED ? self::STAGE_PENDING_BIR : $this->stage;
        $rank = array_search($stage, self::order(), true);

        return $rank === false ? -1 : $rank;
    }

    public function hasReached(string $stage): bool
    {
        $target = array_search($stage, self::order(), true);

        return $target !== false && $this->rank() >= $target;
    }

    public function isLocked(): bool
    {
        return in_array($this->stage, self::LOCKED_STAGES, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->stage, [self::STAGE_COMPLETED, self::STAGE_CHECK_FAILED, self::STAGE_CANCELLED], true);
    }

    /** An application whose registration fee bill is paid and which has no instrument sent yet. */
    public function isReadyForCapture(): bool
    {
        return $this->stage === self::STAGE_REGISTRATION_PAID && !$this->instrument_capture_id;
    }

    // ---- Identity ---------------------------------------------------------

    public function setNin(?string $nin): void
    {
        $nin = self::normalizeNin($nin);

        $this->nin_encrypted = $nin ? Crypt::encryptString($nin) : null;
        $this->nin_hash = $nin ? self::hashNin($nin) : null;
        $this->nin_last4 = $nin ? substr($nin, -4) : null;
    }

    public function nin(): ?string
    {
        return $this->nin_encrypted ? Crypt::decryptString($this->nin_encrypted) : null;
    }

    public function maskedNin(): string
    {
        return $this->nin_last4 ? '*******' . $this->nin_last4 : '—';
    }

    public function maskedPhone(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone);

        return strlen($digits) >= 4 ? str_repeat('*', max(strlen($digits) - 4, 0)) . substr($digits, -4) : '—';
    }

    public static function normalizeNin(?string $nin): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $nin);

        return $digits === '' ? null : $digits;
    }

    /**
     * Keyed with the app key, so a leaked table cannot be brute-forced against
     * the 11-digit NIN space. The only form of the NIN ever searched.
     */
    public static function hashNin(string $nin): string
    {
        return hash_hmac('sha256', self::normalizeNin($nin), (string) config('app.key'));
    }

    // ---- References -------------------------------------------------------

    /** IRA-YYYY-NNNNNN, allocated inside the create transaction; the unique index is the backstop. */
    public static function nextReference(): string
    {
        $prefix = 'IRA-' . now()->format('Y') . '-';

        $last = DB::connection('sqlsrv')->table('instrument_applications')
            ->where('reference', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * LIC-YYYY-NNNNNN. Generated, never typed: the LIC is issued automatically
     * when Physical Planning passes the application. Call inside the transaction
     * that writes it.
     */
    public static function nextLicNumber(): string
    {
        $prefix = 'LIC-' . now()->format('Y') . '-';

        $last = DB::connection('sqlsrv')->table('instrument_applications')
            ->where('lic_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('lic_number')
            ->value('lic_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    // ---- Relations --------------------------------------------------------

    public function customer()
    {
        return $this->belongsTo(CustomerRecord::class, 'customer_record_id');
    }

    public function fileRecord()
    {
        return $this->belongsTo(FileIndexingRecord::class, 'file_indexing_id');
    }

    public function checks()
    {
        return $this->hasMany(InstrumentApplicationCheck::class, 'application_id')->orderBy('id');
    }

    public function events()
    {
        return $this->hasMany(InstrumentApplicationEvent::class, 'application_id')->orderBy('id');
    }

    public function taxChecks()
    {
        return $this->hasMany(TaxIdentityCheck::class, 'application_id')->orderByDesc('id');
    }

    public function invoices()
    {
        return $this->hasMany(InstrumentInvoice::class, 'application_id')->orderByDesc('id');
    }

    public function documents()
    {
        return $this->hasMany(InstrumentApplicationDocument::class, 'application_id')
            ->whereNull('deleted_at')
            ->orderBy('doc_type');
    }

    public function submissions()
    {
        return $this->hasMany(BirSubmission::class, 'application_id')->orderByDesc('id');
    }

    public function activeInvoice(string $purpose): ?InstrumentInvoice
    {
        return $this->invoices()
            ->where('purpose', $purpose)
            ->whereIn('status', [InstrumentInvoice::STATUS_UNPAID, InstrumentInvoice::STATUS_PAID])
            ->first();
    }

    public function successfulTaxCheck(): ?TaxIdentityCheck
    {
        return $this->taxChecks()->where('tin_status', '<>', TaxIdentityCheck::STATUS_FAILED)->whereNotNull('tin')->first();
    }
}

