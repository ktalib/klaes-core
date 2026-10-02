<?php

namespace App\Models\Laas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A land allocation application submitted through the LAAS Portal.
 *
 * This model owns the stage machine. Stages are strictly ordered (see ORDER):
 * the workflow service only ever moves an application forward, so a hook firing
 * twice — or firing late, after a later stage has already been reached — cannot
 * drag an application backwards.
 */
class LaasApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'laas_applications';

    // ---- Stages -----------------------------------------------------------
    // Spec letters refer to the LAAS Portal specification (steps a-k).
    public const STAGE_DRAFT                   = 'draft';                   // a
    public const STAGE_SUBMITTED               = 'submitted';               // b
    public const STAGE_DIRECTOR_APPROVED       = 'director_approved';       // c
    public const STAGE_FILENO_ASSIGNED         = 'fileno_assigned';         // d
    public const STAGE_LAND12_RAISED           = 'land12_raised';           // e
    public const STAGE_AT_CADASTRAL            = 'at_cadastral';            // f
    public const STAGE_LAND12_COMPLETED        = 'land12_completed';        // g
    public const STAGE_RECOMMENDATION_PENDING  = 'recommendation_pending';  // h
    public const STAGE_RECOMMENDATION_APPROVED = 'recommendation_approved'; // i
    public const STAGE_ROFO_GENERATED          = 'rofo_generated';          // j
    public const STAGE_ROFO_SIGNED             = 'rofo_signed';             // k

    /** Terminal, and off the main line — a rejected application has no rank. */
    public const STAGE_REJECTED = 'rejected';

    /**
     * The main line, in order. Position in this array IS the stage's rank;
     * `rejected` is deliberately absent because it is not a point on the line.
     */
    public const ORDER = [
        self::STAGE_DRAFT,
        self::STAGE_SUBMITTED,
        self::STAGE_DIRECTOR_APPROVED,
        self::STAGE_FILENO_ASSIGNED,
        self::STAGE_LAND12_RAISED,
        self::STAGE_AT_CADASTRAL,
        self::STAGE_LAND12_COMPLETED,
        self::STAGE_RECOMMENDATION_PENDING,
        self::STAGE_RECOMMENDATION_APPROVED,
        self::STAGE_ROFO_GENERATED,
        self::STAGE_ROFO_SIGNED,
    ];

    /** Short labels for the applicant's stage tracker. */
    public const LABELS = [
        self::STAGE_DRAFT                   => 'Draft',
        self::STAGE_SUBMITTED               => 'Application received',
        self::STAGE_DIRECTOR_APPROVED       => 'Approved by Director',
        self::STAGE_FILENO_ASSIGNED         => 'File Number assigned',
        self::STAGE_LAND12_RAISED           => 'Survey request raised',
        self::STAGE_AT_CADASTRAL            => 'With Cadastral',
        self::STAGE_LAND12_COMPLETED        => 'Survey report completed',
        self::STAGE_RECOMMENDATION_PENDING  => 'Awaiting recommendation',
        self::STAGE_RECOMMENDATION_APPROVED => 'Recommendation approved',
        self::STAGE_ROFO_GENERATED          => 'RoFO generated',
        self::STAGE_ROFO_SIGNED             => 'RoFO signed',
        self::STAGE_REJECTED                => 'Not approved',
    ];

    protected $fillable = [
        'reference_no',
        'laas_applicant_id',
        'applicant_name',
        'applicant_phone',
        'applicant_email',
        'applicant_address',
        'applicant_nin',
        'applicant_type',
        'land_use',
        'purpose_id',
        'lga_id',
        'district_id',
        'location',
        'plot_no',
        'approx_size',
        'existing_allocation_ref',
        'applicant_remarks',
        'stage',
        'land_type',
        'form_data',
        'file_number',
        'survey_report_request_id',
        'land_recommendation_id',
        'rofo_id',
        'director_approved_by',
        'director_approved_at',
        'rejection_reason',
        'fileno_assigned_by',
        'fileno_assigned_at',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        // The full Statutory Right of Occupancy answers, keyed by
        // oss_applications column name. See App\Support\Laas\SroFormSchema.
        'form_data'            => 'array',
        'director_approved_at' => 'datetime',
        'fileno_assigned_at'   => 'datetime',
        'submitted_at'         => 'datetime',
        'completed_at'         => 'datetime',
    ];

    public function applicant()
    {
        return $this->belongsTo(LaasApplicant::class, 'laas_applicant_id');
    }

    public function events()
    {
        return $this->hasMany(LaasApplicationEvent::class, 'laas_application_id');
    }

    public function documents()
    {
        return $this->hasMany(LaasDocument::class, 'laas_application_id');
    }

    // ---- Stage helpers ----------------------------------------------------

    /**
     * Stages from which an application has entered the land registry.
     *
     * At `fileno_assigned` a real file number is minted into `mls_file_no`, and
     * a Land 12, recommendation and RoFO may follow — all keyed to it. From
     * that point the application is a statutory record, and deleting the
     * applicant behind it would orphan every one of those rows.
     */
    public static function registryStages(): array
    {
        return array_slice(self::ORDER, self::rank(self::STAGE_FILENO_ASSIGNED));
    }

    /** Position on the main line, or -1 for `rejected` / anything unrecognised. */
    public static function rank(?string $stage): int
    {
        $index = array_search($stage, self::ORDER, true);

        return $index === false ? -1 : $index;
    }

    public static function label(?string $stage): string
    {
        return self::LABELS[$stage] ?? (string) $stage;
    }

    /** True when $stage lies strictly ahead of where this application already is. */
    public function canAdvanceTo(string $stage): bool
    {
        if ($this->stage === self::STAGE_REJECTED) {
            return false;
        }

        return self::rank($stage) > self::rank($this->stage);
    }

    public function hasReached(string $stage): bool
    {
        return self::rank($this->stage) >= self::rank($stage) && self::rank($stage) >= 0;
    }

    /**
     * Next free reference in the LAAS-<year>-<6 digits> series.
     *
     * Derived from the highest reference already issued this year rather than
     * from a row count, so deleting a row can never hand the same reference out
     * twice. The unique index on reference_no is the real backstop.
     */
    public static function nextReference(?int $year = null): string
    {
        $year = $year ?: (int) date('Y');
        $prefix = "LAAS-{$year}-";

        $last = DB::connection('sqlsrv')->table('laas_applications')
            ->where('reference_no', 'like', $prefix . '%')
            ->max('reference_no');

        $next = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
