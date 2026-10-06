<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One duplex = one instruction carrying 1..N parcel updates in a declared order.
 *
 * `stages` is the canonical ordered plan captured at Step 1 (tick order = execution
 * order). The stage rows in duplex_parcel_update_stages are built from it and keep
 * the same ranks; nothing downstream may re-order by type.
 */
class DuplexParcelUpdate extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'duplex_parcel_updates';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CAPTURED  = 'captured';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_IN_LAND   = 'in_land';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_REJECTED  = 'rejected';

    /** The five parcel updates a duplex may carry, with their display labels. */
    public const TYPES = [
        'merger'            => 'Merger',
        'subdivision'       => 'Subdivision',
        'change_of_purpose' => 'Change of Purpose',
        'extension'         => 'Extension',
        'separation'        => 'Separation',
    ];

    /**
     * Plots, files or source parcels any one stage may carry, whatever its type, and
     * source files per duplex. Matches the single-workflow subdivision ceiling,
     * PlotSubdivisionController::MAX_PLOTS, so a layout that fits there also fits here.
     */
    public const MAX_PLOTS = 2000;

    /**
     * Files the commissioning engine mints per batch run (generateBatch max:200). A
     * Subdivision or Separation stage bigger than this is commissioned in chunks of
     * this size by DuplexCommitService::commissionStandard().
     */
    public const BATCH_CAP = 200;

    protected $fillable = [
        'source_module',
        'duplex_id',
        'applicant_name',
        'file_title',
        'source_file_nos',
        'stages',
        'status',
        'land_use',
        'plot_no',
        'house_no',
        'street_name',
        'district',
        'lga',
        'state',
        'phone',
        'address',
        // Relative path on the `public` disk to the recommended site plan — one
        // drawing for the whole instruction, uploaded on the step before Done.
        'site_plan',
        'land_value',
        'knupda_fee',
        'knupda_status',
        'knupda_remarks',
        'remarks',
        'application_generated_at',
        'recommendation_generated_at',
        'recommendation_printed_at',
        'conveyance_generated_at',
        'sent_to_land_at',
        'committed_at',
        'captured_by',
        'updated_by',
        'approved_by',
        'committed_by',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'source_file_nos'             => 'array',
        'stages'                      => 'array',
        'application_generated_at'    => 'datetime',
        'recommendation_generated_at' => 'datetime',
        'recommendation_printed_at' => 'datetime',
        'conveyance_generated_at'     => 'datetime',
        'sent_to_land_at'             => 'datetime',
        'committed_at'                => 'datetime',
        'deleted_at'                  => 'datetime',
    ];

    /**
     * The stage ROWS.
     *
     * Deliberately not called stages(): `stages` is also a JSON column on this table
     * (the ordered plan captured at step 1), and an attribute always wins over a
     * relation of the same name in Eloquent — so `$duplex->stages` would hand back
     * the plan array while `$duplex->stages()` handed back rows. Two different
     * shapes behind one name is a trap; the rows get their own name instead.
     */
    public function stageRows(): HasMany
    {
        return $this->hasMany(DuplexParcelUpdateStage::class, 'duplex_parcel_update_id')
            ->orderBy('rank');
    }

    /**
     * The officer who started the instruction. captured_by holds the user id
     * (Auth::id() at store), not a name.
     */
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DuplexParcelUpdateFile::class, 'duplex_parcel_update_id')
            ->orderBy('sequence');
    }

    /** Not-soft-deleted rows — the same idiom the five single parcel tables use. */
    public function scopeVisible($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        });
    }

    /** "M(1) S(2) CoP(3)" — the stage plan in execution order, for the listing. */
    public function stageSummary(): string
    {
        $plan = collect($this->stages ?? [])->sortBy('rank');

        return $plan->map(function ($s) {
            $label = self::TYPES[$s['type'] ?? ''] ?? ($s['type'] ?? '?');
            return $label . '(' . ($s['rank'] ?? '?') . ')';
        })->implode('  ');
    }

    /**
     * True for a duplex holding one stage.
     *
     * Capture now requires two or more, so this answers for records captured before
     * that rule — the memo and the application sheet drop their plural wording for
     * them rather than printing "these updates" over a single point.
     */
    public function isSingleStage(): bool
    {
        return count($this->stages ?? []) === 1;
    }

    /**
     * Per-file values are stored as a JSON ARRAY, not as comma-separated text.
     *
     * A duplex can act on several files, and they need not share an applicant, a title
     * or a location — so each of those columns holds one value per file. An array keeps
     * the values separate, which comma-joined text cannot: "MUSA, ADAM & SONS" is two
     * names or one depending on nothing the string itself can tell you.
     *
     * A single-file duplex still stores a plain string, so everything captured before
     * this reads back unchanged.
     */
    public static function encodeList($value): ?string
    {
        $list = array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), (array) $value),
            fn ($v) => $v !== ''
        ));

        if (empty($list)) {
            return null;
        }

        return count($list) === 1 ? $list[0] : json_encode($list, JSON_UNESCAPED_UNICODE);
    }

    /** One stored column back as the list of values it holds. */
    public static function decodeList($value): array
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return [];
        }

        if (str_starts_with($raw, '[')) {
            $parsed = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return array_values(array_filter(array_map(
                    fn ($v) => trim((string) $v),
                    $parsed
                ), fn ($v) => $v !== ''));
            }
        }

        return [$raw];
    }

    /**
     * "Musa, Adam, Ibrahim, Sani and Usman" — the reading form.
     *
     * Ands the last pair rather than listing commas throughout, because these strings
     * are read aloud in an office and printed on instruments.
     */
    public static function readableList($value): string
    {
        $list = static::decodeList($value);

        if (count($list) < 2) {
            return $list[0] ?? '';
        }

        $last = array_pop($list);

        return implode(', ', $list) . ' and ' . $last;
    }

    /** The file titles, one per source file. */
    public function fileTitles(): array
    {
        return static::decodeList($this->getRawOriginal('file_title'));
    }

    /*
     * Everything that reads these columns — the register, the memo, the conveyance, the
     * summary card — gets the readable list without knowing an array was stored. Use
     * getRawOriginal() or the *Names()/Titles() helpers when the values are needed apart.
     */
    public function getFileTitleAttribute($value): string
    {
        return static::readableList($value);
    }

    public function getPlotNoAttribute($value): string
    {
        return static::readableList($value);
    }

    public function getDistrictAttribute($value): string
    {
        return static::readableList($value);
    }

    public function getLgaAttribute($value): string
    {
        return static::readableList($value);
    }
}
