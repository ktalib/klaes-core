<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ApplicationMother;
use App\Models\Scanning;
use App\Models\PageTyping;
use App\Models\FileTracking;
use App\Models\PrintLabelBatchItem;
use App\Models\User;
use App\Models\Grouping;
use App\Support\SltrDigitRank;
use App\Services\GenderNormalizer;

class FileIndexing extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_indexings';

    protected $fillable = [
        'main_application_id',
        'subapplication_id',
        'recertification_application_id',
        'tracking_id',
        'st_fillno',
        'serial_no',
        'batch_no',
        'st_batch_no',
        'shelf_location',
        'shelf_label_id',
        'sys_batch_no',
        'group_no',
        'file_number',
        'file_title',
        'gender',
        'gender_source',
        'land_use_type',
        'plot_number',
        'plot_size',
        'tp_no',
        'lpkn_no',
        'location',
        'latitude',
        'longitude',
        'street_name',
        'district',
        'registry',
        'physical_registry',
        'general_registry',
        'edms_file_type',
        'lga',
        'property_description',
        'file_type',
        'dob',
        'nin',
        'tin',
        'rc_no',
        'residence_address',
        'country_code',
        'phone',
        'has_cofo',
        'complainant',
        'dciv_reason',
        'dciv_status',
        'dciv_fileno',
        'is_merged',
        'has_transaction',
        'is_problematic',
        'is_co_owned_plot',
        'test_control',
        'is_updated',
        'workflow_status',
        'has_qc_issues',
        'created_by',
        'updated_by',
        'related_fileno',
        // Previous / duplicated number this same physical file used to carry.
        // Distinct from related_fileno (a JSON array of sibling files).
        // Full history lives in old_file_numbers; see OldFileNumberService.
        'old_fileno',
        'registry_batch_no',
        'batch_generated',
        'last_batch_id',
        'batch_generated_at',
        'batch_generated_by',
        'is_deleted',
        'deleted_at',
        // Root of Title — the pre-grant instrument/holder the title springs from.
        // Captured by hand on the File Indexing form (required there); read as the
        // last-resort fallback by TitleHolderResolver when no chain row supplies it.
        'root_of_title',
        'current_holder',
        'original_holder',
        'party_3',
        'party_4',
        'party_5',
        'indexing_type',
        'file_classification',
        'file_classification_remarks',
        'has_rofo',
        'has_temp_file',
        'temp_file_no',
        'kangis_fileno_placeholder',
        'kangis_fileno_resolved',
        'kangis_file_type',
        'mls_file_no',
        'kangis_file_no',
        'new_kangis_file_no',
        'prop_id',
        'parent_prop_id',
        // Root of the parent_prop_id chain — see App\Services\PropIdLineageService.
        'ancestral_prop_id',
        'is_corresponding_file',
        'corresponding_fileno',
        'sub_prefix',
        'suffix',
        'digit_rank',
        'tracking_status',
        'current_location',
        'file_tracker_id',
        'location_status_manual',
        // Bill Balance
        'bill_total_amount',
        'bill_from_year',
        'bill_to_year',
        'bill_receipt_no',
        'bill_receipt_date',
        // Grant Rent
        'ground_rent_amount',
        'ground_rent_from_year',
        'ground_rent_to_year',
        'ground_rent_receipt_no',
        'ground_rent_receipt_date',
        // Term of the R of O as shown/printed on the Legal Search report (e.g.
        // "99 Years"). Editable via the Edit File Information modal; when set it
        // overrides the term derived from land use, and the Residual Term is
        // computed from it.
        'term',
    ];

    public static function columnWhitelist(): array
    {
        return [
            'main_application_id',
            'subapplication_id',
            'recertification_application_id',
            'st_fillno',
            'file_number_id',
            'file_number',
            'file_title',
            'gender',
            'land_use_type',
            'plot_number',
            'plot_size',
            'latitude',
            'longitude',
            'district',
            'lga',
            'has_cofo',
            'is_merged',
            'has_transaction',
            'is_problematic',
            'is_co_owned_plot',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'serial_no',
            'batch_no',
            'st_batch_no',
            'shelf_location',
            'is_updated',
            'batch_id',
            'has_qc_issues',
            'workflow_status',
            'archived_at',
            'shelf_label_id',
            'assigned_by',
            'assigned_at',
            'reserved_by',
            'reserved_at',
            'status',
            'registry',
            'physical_registry',
            'general_registry',
            'edms_file_type',
            'location',
            'tp_no',
            'lpkn_no',
            'tracking_id',
            'batch_generated',
            'last_batch_id',
            'batch_generated_at',
            'batch_generated_by',
            'is_deleted',
            'deleted_at',
            'related_fileno',
            'old_fileno',
            'sys_batch_no',
            'group',
            'date_migrated',
            'source',
            'migrated_by',
            'date_created',
            'test_control',
            'registry_batch_no',
            'group_no',
            'street_name',
            'file_type',
            'dob',
            'nin',
            'tin',
            'rc_no',
            'residence_address',
            'country_code',
            'phone',
            'complainant',
            'dciv_reason',
            'dciv_status',
            'dciv_fileno',
            'root_of_title',
            'current_holder',
            'original_holder',
            'party_3',
            'party_4',
            'party_5',
            'indexing_type',
            'file_classification',
            'file_classification_remarks',
            'has_rofo',
            'has_temp_file',
            'temp_file_no',
            'kangis_fileno_placeholder',
            'kangis_fileno_resolved',
            'kangis_file_type',
            'mls_file_no',
            'kangis_file_no',
            'new_kangis_file_no',
            'prop_id',
            'parent_prop_id',
            'ancestral_prop_id',
            'kn_grouping_id',
            'is_corresponding_file',
            'corresponding_fileno',
            'sub_prefix',
            'suffix',
            'digit_rank',
            // Bill Balance
            'bill_total_amount',
            'bill_from_year',
            'bill_to_year',
            'bill_receipt_no',
            'bill_receipt_date',
            // Grant Rent
            'ground_rent_amount',
            'ground_rent_from_year',
            'ground_rent_to_year',
            'ground_rent_receipt_no',
            'ground_rent_receipt_date',
            'term',
        ];
    }

    protected $casts = [
        'has_cofo' => 'boolean',
        'has_rofo' => 'boolean',
        'has_temp_file' => 'boolean',
        'kangis_fileno_placeholder' => 'string',
        'kangis_fileno_resolved' => 'string',
        'is_merged' => 'boolean',
        'has_transaction' => 'boolean',
        'is_problematic' => 'boolean',
        'is_co_owned_plot' => 'boolean',
        'is_updated' => 'boolean',
        'is_deleted' => 'boolean',
        'has_qc_issues' => 'boolean',
        'batch_generated' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'batch_generated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'current_holder' => 'array',
        'original_holder' => 'array',
        'digit_rank' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (FileIndexing $model) {
            $model->digit_rank = SltrDigitRank::fromFileNumber($model->file_number);
        });

        // Gender is required on the indexing form, but the form is not the only way
        // a row gets here: commissioning auto-indexes through FileIndexingService,
        // and batch paths never see a request. Those paths used to write null, which
        // is how 133,773 of 133,779 rows ended up without a gender. Fall back to the
        // same inference the backfill uses, and always record where the value
        // came from. Creation only — never rewrite an existing row on update.
        static::creating(function (FileIndexing $model) {
            if ($model->gender !== null) {
                $model->gender_source = $model->gender_source ?: GenderNormalizer::SOURCE_CAPTURED;

                return;
            }

            $inferred = app(GenderNormalizer::class)->classify($model->file_title);

            if ($inferred !== null) {
                [$model->gender, $model->gender_source] = $inferred;
            }
        });
    }

    /** Every write path folds onto the client's four values. See GenderNormalizer. */
    public function setGenderAttribute($value): void
    {
        $this->attributes['gender'] = app(GenderNormalizer::class)->normalize($value);
    }

    public function mainApplication()
    {
        return $this->belongsTo(ApplicationMother::class, 'main_application_id');
    }

    public function scannings()
    {
        return $this->hasMany(Scanning::class, 'file_indexing_id')
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function pagetypings()
    {
        return $this->hasMany(PageTyping::class, 'file_indexing_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grouping()
    {
        return $this->hasOne(Grouping::class, 'awaiting_fileno', 'file_number');
    }

    public function getStatusAttribute()
    {
        $hasScanning = $this->scannings()->exists();
        $hasPageTyping = $this->pagetypings()->exists();

        if ($hasPageTyping) {
            return 'Typed';
        } elseif ($hasScanning) {
            return 'Scanned';
        } else {
            return 'Indexed';
        }
    }

    public function recertificationApplication()
    {
        return $this->belongsTo('App\Models\RecertificationApplication', 'recertification_application_id');
    }

    public function fileTracking()
    {
        return $this->hasOne(FileTracking::class, 'file_indexing_id');
    }

    public function printLabelBatchItems()
    {
        return $this->hasMany(PrintLabelBatchItem::class, 'file_indexing_id');
    }

    public function cadastralPrintLabelBatchItems()
    {
        return $this->hasMany(\App\Models\CadastralPrintLabelBatchItem::class, 'file_indexing_id');
    }

    public function getTrackingStatusAttribute($value)
    {
        // Prefer the resolved/persisted Quick Search location status when present
        // (IN_TRANSIT / IN_ARCHIVE / IN_POOL_OFFICE / FILE_NOT_FOUND / REFER_TO_ORIGINAL_REGISTRY).
        if (!empty($value)) {
            return $value;
        }

        $tracking = $this->fileTracking;
        if (!$tracking) {
            return 'Not Tracked';
        }

        return ucfirst(str_replace('_', ' ', $tracking->status));
    }

    public function getIsTrackedAttribute()
    {
        return $this->fileTracking !== null;
    }

    public function getHasLabelPrintedAttribute()
    {
        return $this->printLabelBatchItems()->exists();
    }

    /**
     * Get the first page typing record for cover type display
     */
    public function firstPageTyping()
    {
        return $this->hasOne(PageTyping::class, 'file_indexing_id')
            ->with(['coverType', 'pageType', 'pageSubType'])
            ->orderBy('page_number', 'asc');
    }

    /**
     * Get the cover type from the first page
     */
    public function getCoverTypeAttribute()
    {
        $firstPage = $this->firstPageTyping;
        return $firstPage ? $firstPage->coverType : null;
    }

    /**
     * Normalize a holder column ('root_of_title' | 'original_holder' |
     * 'current_holder') for display.
     *
     * original_holder/current_holder store EITHER a plain name string OR a
     * JSON-encoded array of names (block indexing). The model casts those two as
     * 'array', which decodes a plain-string value to null — so we read the raw
     * attribute and handle both. root_of_title is always a plain string (one root
     * per file) and is read through here so callers need only one accessor.
     */
    public function formattedHolder(string $which): ?string
    {
        $raw = $this->getRawOriginal($which);
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Stored as a JSON array of names (block indexing).
            if (is_array($decoded)) {
                $names = array_values(array_filter(array_map('trim', $decoded), fn ($n) => $n !== ''));
                return $names ? implode(', ', $names) : null;
            }
            // Stored as a JSON-encoded scalar string (e.g. "ZAKARI AHMED" or "").
            if (is_string($decoded)) {
                return trim($decoded) ?: null;
            }
            if ($decoded === null) {
                return null;
            }
            return trim((string) $decoded) ?: null;
        }

        // Plain, non-JSON string.
        return trim((string) $raw) ?: null;
    }

    /**
     * Check if this file has been used in any batch tracking sheet generation
     */
    public function hasBeenInBatch()
    {
        return \App\Models\TrackingSheet::where('selected_file_ids', 'LIKE', '%"' . $this->id . '"%')->exists();
    }

    /**
     * Get the latest batch that included this file
     */
    public function getLatestBatch()
    {
        return \App\Models\TrackingSheet::where('selected_file_ids', 'LIKE', '%"' . $this->id . '"%')
            ->orderBy('generated_at', 'desc')
            ->first();
    }

    /**
     * Get formatted batch generated at date
     */
    public function getFormattedBatchGeneratedAtAttribute()
    {
        if (!$this->batch_generated_at) {
            return null;
        }

        try {
            // If it's already a Carbon instance, format it
            if ($this->batch_generated_at instanceof \Carbon\Carbon) {
                return $this->batch_generated_at->format('M j, Y H:i');
            }

            // If it's a string, parse it with Carbon first
            if (is_string($this->batch_generated_at)) {
                return \Carbon\Carbon::parse($this->batch_generated_at)->format('M j, Y H:i');
            }

            return null;
        } catch (\Exception $e) {
            // If parsing fails, return null
            return null;
        }
    }

    /**
     * File-number prefixes a Sectional Titling "Existing / Extant Conversion" may
     * stand on. An ST primary converts a parcel that is already titled somewhere:
     * an MLS conversion file (CON-), a KANGIS file (KN*, MLKN*, MNKL*) or an SLTR
     * file. Ordered longest-first only for readability — matching is by prefix.
     */
    public const EXTANT_CONVERSION_PREFIXES = ['CON-', 'SLTR-', 'SLTR/', 'MLKN', 'MNKL', 'KN'];

    /**
     * registry values on those same files. KANGIS numbers are not all prefix-
     * detectable (plain "KN900" alongside "KNML 1093"), so the registry column is
     * the second way in.
     */
    public const EXTANT_CONVERSION_REGISTRIES = ['KANGIS', 'SLTR', 'KANGIS Registry', 'SLTR Registry'];

    /**
     * May this file number be picked as the mother of an ST conversion primary?
     */
    public static function isExtantConversionFileNumber(?string $fileNumber): bool
    {
        $f = strtoupper(trim((string) $fileNumber));
        if ($f === '') {
            return false;
        }

        foreach (self::EXTANT_CONVERSION_PREFIXES as $prefix) {
            if (str_starts_with($f, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect the general registry name from a file number prefix.
     * Returns null if file number is empty or unrecognizable.
     */
    public static function detectRegistryFromFileNumber(?string $fileNumber): ?string
    {
        if (empty($fileNumber)) {
            return null;
        }

        $f = strtoupper(trim($fileNumber));

        // ST Registry: ST-{LAND_USE}-{YEAR}-{SERIAL}
        if (str_starts_with($f, 'ST-')) {
            return 'ST Registry';
        }

        // DCIV Registry: DCIV-{YEAR}-{SERIAL}
        if (str_starts_with($f, 'DCIV-') || str_starts_with($f, 'DCIV/')) {
            return 'DCIV Registry';
        }

        // SLTR Registry: SLTR-{SERIAL}
        if (str_starts_with($f, 'SLTR-') || str_starts_with($f, 'SLTR/')) {
            return 'SLTR Registry';
        }

        // SIT Registry: SIT-{YEAR}-{SERIAL}
        if (str_starts_with($f, 'SIT-') || str_starts_with($f, 'SIT/')) {
            return 'SIT Registry';
        }

        // Survey Registry (GKN / LPKN)
        if (str_starts_with($f, 'GKN-') || str_starts_with($f, 'GKN/') || str_starts_with($f, 'GKN ')
            || str_starts_with($f, 'LPKN-') || str_starts_with($f, 'LPKN/') || str_starts_with($f, 'LPKN ')) {
            return 'Survey Registry';
        }

        // KANGIS Registry patterns
        if (str_starts_with($f, 'KNML') || str_starts_with($f, 'MLKN')
            || str_starts_with($f, 'MNKL') || str_starts_with($f, 'KNGP')) {
            return 'KANGIS Registry';
        }

        // Lands Registry: all standard MLS prefixes
        // RES, COM, IND, AG, MISC and their CON- and -RC variants
        $landsPatterns = [
            'RES-', 'RES/', 'COM-', 'COM/', 'IND-', 'IND/',
            'AG-', 'AG/', 'MISC-', 'MISC/',
            'CON-RES', 'CON-COM', 'CON-IND', 'CON-AG', 'CON-MISC',
        ];
        foreach ($landsPatterns as $pattern) {
            if (str_starts_with($f, $pattern)) {
                return 'Lands Registry';
            }
        }

        return null;
    }
}
