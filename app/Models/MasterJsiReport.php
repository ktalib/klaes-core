<?php

namespace App\Models;

use App\Models\Concerns\HasBoundarySegments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Physical Planning site observation behind a parcel update.
 *
 * One form, two categories and five layouts. The category says which register the
 * inspection came from — SPU for the five single workflows, APU for a duplex — and
 * parcel_update_type says which of the Ministry sheets it prints as. The narrative
 * fields are shared; the S/N table rows differ per type and are described once in
 * App\Support\MasterJsiPortionTemplates.
 *
 * An approved report is what Generate Recommendation and Approve rest on, in the
 * place the KAMMA/Physical Planning handshake used to hold. The question is asked
 * through App\Support\MasterJsiGate, never by reading `status` at a call site — the
 * gate also carries the legacy knupda_status arm that keeps pre-cutover records
 * moving.
 */
class MasterJsiReport extends Model
{
    use HasBoundarySegments;

    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_reports';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';

    public const CATEGORY_SPU = 'SPU';
    public const CATEGORY_APU = 'APU';

    /** The two categories, with the words the officer picks between. */
    public const CATEGORIES = [
        self::CATEGORY_SPU => 'SPU — Single Parcel Update',
        self::CATEGORY_APU => 'APU — Advance Parcel Update (Duplex)',
    ];

    /**
     * The workflow a report belongs to: slug => [table, label].
     *
     * The slugs are the ones ParcelUpdateNotificationService::typeLabel() already
     * speaks, so a report, a notification and a menu item all name the same thing.
     * subject_type stores the slug rather than a model class: six workflows sit in
     * six tables, and a class name would break the moment one is moved.
     */
    public const SUBJECTS = [
        'subdivision'       => ['plot_subdivision_applications', 'Plot Subdivision'],
        'separation'        => ['plot_separation_applications',  'Plot Separation'],
        'merger'            => ['plot_merger_applications',      'Plot Merger'],
        'extension'         => ['plot_extension_applications',   'Plot Extension'],
        'change_of_purpose' => ['change_of_purpose_applications', 'Change of Purpose'],
        'duplex'            => ['duplex_parcel_updates',         'APU - Advance Parcel Update (Duplex)'],
    ];

    protected $fillable = [
        'jsi_ref',
        'category',
        'parcel_update_type',
        'subject_type',
        'subject_id',
        'duplex_stage_id',
        'file_number',
        'file_title',
        'applicant_name',
        'inspection_date',
        'location',
        'plot_number',
        'district',
        'lga',
        'inspection_officer',
        'inspection_officer_id',
        'available_on_ground',
        'boundary_description',
        'boundary_segments',
        'road_reservation',
        'conformity',
        'prevailing_land_use',
        'existing_land_use',
        'recommended_land_use',
        'existing_purpose',
        'recommended_purpose',
        'number_of_units',
        'average_size',
        'narrative_summary',
        'additional_observations',

        // Site Inspection Template Update (additive).
        'site_unit',
        'site_existing_area_sqm',
        'site_existing_dimensions',
        'site_recommended_area_sqm',
        'recommended_total_override_sqm',
        'override_reason',
        'proposed_merged_plot_number',
        'recommended_merged_area_sqm',
        'merger_remarks',
        'extension_remarks',
        'location_coordinates',
        'finding_site_suitable',
        'finding_site_accessible',
        'development_status',
        'officer_recommendation',
        'officer_designation',
        'further_directions',
        'supervisor_remarks',
        'supervisor_decision',
        'returned_at',
        'returned_by',

        'status',
        'generated_at',
        'generated_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_reason',
        'sent_to_deeds_at',
        'sent_to_deeds_by',
        'created_by',
        'updated_by',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'inspection_date'  => 'date',
        'conformity'       => 'boolean',
        'is_deleted'       => 'boolean',
        'number_of_units'  => 'integer',
        'generated_at'     => 'datetime',
        'submitted_at'     => 'datetime',
        'approved_at'      => 'datetime',
        'sent_to_deeds_at' => 'datetime',
        'deleted_at'       => 'datetime',
        'site_existing_area_sqm'         => 'decimal:2',
        'site_recommended_area_sqm'      => 'decimal:2',
        'recommended_total_override_sqm' => 'decimal:2',
        'recommended_merged_area_sqm'    => 'decimal:2',
        'finding_site_suitable'   => 'boolean',
        'finding_site_accessible' => 'boolean',
        'returned_at'             => 'datetime',
    ];

    /** The four-direction view of boundary_description the form binds to. */
    protected $appends = ['boundary_segments'];

    public function portions(): HasMany
    {
        return $this->hasMany(MasterJsiPortion::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    public function purposes(): HasMany
    {
        return $this->hasMany(MasterJsiPurpose::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MasterJsiParticipant::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    public function mergerProperties(): HasMany
    {
        return $this->hasMany(MasterJsiMergerProperty::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    public function extensionPortions(): HasMany
    {
        return $this->hasMany(MasterJsiExtensionPortion::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    public function subdivisionPlots(): HasMany
    {
        return $this->hasMany(MasterJsiSubdivisionPlot::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    public function purposeChanges(): HasMany
    {
        return $this->hasMany(MasterJsiPurposeChange::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(MasterJsiEvidence::class, 'master_jsi_report_id')
            ->orderBy('sequence')
            ->orderBy('id');
    }

    // ---------------------------------------------------------------------
    // Site Inspection Template Update — purpose / derived-measurement reads.
    // ---------------------------------------------------------------------

    /**
     * The chosen purposes as an ordered list of slugs.
     *
     * A legacy sheet has no purpose rows; its single purpose is its
     * parcel_update_type (Separation alias resolved to Subdivision).
     */
    public function purposeSlugs(): array
    {
        $slugs = $this->purposes->pluck('purpose')->all();

        if (!empty($slugs)) {
            return $slugs;
        }

        $legacy = MasterJsiPurpose::SECTION_MAP[$this->parcel_update_type] ?? $this->parcel_update_type;

        return $legacy ? [$legacy] : [];
    }

    public function hasPurpose(string $slug): bool
    {
        if (in_array($slug, $this->purposeSlugs(), true)) {
            return true;
        }

        // A separation inspection counts as wanting the subdivision section.
        foreach (array_keys(MasterJsiPurpose::SECTION_MAP, $slug, true) as $alias) {
            if (in_array($alias, $this->purposeSlugs(), true)) {
                return true;
            }
        }

        return false;
    }

    /** Whether the subdivision/separation section applies. */
    public function wantsSubdivisionSection(): bool
    {
        return $this->hasPurpose('subdivision') || $this->hasPurpose('separation');
    }

    /**
     * A legacy record rendered the sheet through MasterJsiPortionTemplates only.
     *
     * New-template sections can come with no child rows on a half-captured sheet;
     * the legacy path is only taken when the record was captured BEFORE the update.
     */
    public function isLegacyLayout(): bool
    {
        return $this->purposes->isEmpty()
            && $this->participants->isEmpty()
            && $this->mergerProperties->isEmpty()
            && $this->extensionPortions->isEmpty()
            && $this->subdivisionPlots->isEmpty()
            && $this->purposeChanges->isEmpty()
            && !$this->site_existing_area_sqm
            && !$this->site_recommended_area_sqm
            && !$this->finding_site_suitable && $this->finding_site_suitable !== false
            && !$this->finding_site_accessible && $this->finding_site_accessible !== false;
    }

    public function isReturned(): bool
    {
        return $this->supervisor_decision === 'returned' && $this->returned_at !== null;
    }

    // ---- Merger ----------------------------------------------------------

    public function totalMergerAreaSqm(): float
    {
        return (float) $this->mergerProperties->sum('area_sqm');
    }

    // ---- Extension -------------------------------------------------------

    public function totalExtensionAreaSqm(): float
    {
        return (float) $this->extensionPortions->sum('area_sqm');
    }

    /**
     * Recommended total site area.
     *
     * The rule is Existing Site Area + Total Extension Area. An authorised officer
     * may override it; the override and its reason are kept for audit.
     */
    public function recommendedTotalSiteAreaSqm(): float
    {
        if ($this->recommended_total_override_sqm !== null
            && (float) $this->recommended_total_override_sqm > 0) {
            return (float) $this->recommended_total_override_sqm;
        }

        $existing = (float) $this->site_existing_area_sqm;
        $total    = $this->totalExtensionAreaSqm();

        if ($total > 0) {
            return $existing + $total;
        }

        return (float) $this->site_recommended_area_sqm ?: $existing;
    }

    // ---- Subdivision -----------------------------------------------------

    public function totalSubdivisionAreaSqm(): float
    {
        return (float) $this->subdivisionPlots->sum('area_sqm');
    }

    /** Remaining/unallocated = recommended site area minus the subdivision total. */
    public function subdivisionRemainingAreaSqm(): float
    {
        $base = (float) $this->site_recommended_area_sqm;

        // Same rule the capture form shows: a typed recommended total wins, and
        // otherwise the recommended site area is Existing + Additions.
        if ($base <= 0) {
            $base = (float) $this->site_existing_area_sqm + $this->totalExtensionAreaSqm();
        }

        return max(0, $base - $this->totalSubdivisionAreaSqm());
    }

    /** Reports that have not been soft-deleted. */
    public function scopeVisible($query)
    {
        return $query->where(function ($q) {
            $q->where('is_deleted', 0)->orWhereNull('is_deleted');
        });
    }

    public function scopeForSubject($query, string $subjectType, int $subjectId)
    {
        return $query->where('subject_type', $subjectType)->where('subject_id', $subjectId);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function sentToDeeds(): bool
    {
        return $this->sent_to_deeds_at !== null;
    }

    /** "Plot Subdivision", "Change of Purpose" — the label the sheet and the menu use. */
    public function typeLabel(): string
    {
        return self::SUBJECTS[$this->parcel_update_type][1]
            ?? ucfirst(str_replace('_', ' ', (string) $this->parcel_update_type));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? (string) $this->category;
    }

    /**
     * Next free MJSI-<year>-<serial>.
     *
     * Same shape as DuplexHoldingNumberService::allocateDuplexId(): the serial is
     * the highest issued this year plus one, and a deleted report does not free its
     * number — the counter only moves forward, so a reference on a printed sheet is
     * never re-issued to a different inspection.
     */
    public static function allocateRef(?int $year = null): string
    {
        $year = $year ?: (int) date('Y');
        $max  = 0;

        $existing = static::where('jsi_ref', 'LIKE', 'MJSI-' . $year . '-%')->pluck('jsi_ref');

        foreach ($existing as $ref) {
            if (preg_match('/-(\d+)$/', (string) $ref, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('MJSI-%d-%04d', $year, $max + 1);
    }
}
