<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One stage of a duplex. `rank` is the execution order and comes from the order the
 * officer ticked the types — a type may legitimately appear twice at different ranks.
 *
 * A stage never writes to the registry. It consumes `input_holding_no` (or the
 * duplex's real source files, when it is rank 1) and emits holding numbers into
 * duplex_parcel_update_files.
 */
class DuplexParcelUpdateStage extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'duplex_parcel_update_stages';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_DONE     = 'done';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'duplex_parcel_update_id',
        'duplex_id',
        'type',
        'rank',
        'status',
        'input_holding_no',
        'plot_count',
        'payload',
        'tracking_id',
        'reject_reason',
        'completed_at',
        'captured_by',
        'updated_by',
    ];

    protected $casts = [
        'payload'      => 'array',
        'completed_at' => 'datetime',
    ];

    public function duplex(): BelongsTo
    {
        return $this->belongsTo(DuplexParcelUpdate::class, 'duplex_parcel_update_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DuplexParcelUpdateFile::class, 'duplex_parcel_update_stage_id')
            ->orderBy('sequence');
    }

    public function label(): string
    {
        return DuplexParcelUpdate::TYPES[$this->type] ?? $this->type;
    }

    /** Merger collapses many into one; the rest fan out to plot_count children. */
    /**
     * The per-file Change of Purpose rows: which files change, and what each becomes.
     *
     * Empty for every other stage type, and for a Change of Purpose captured before
     * per-file purposes existed — those carry a single `new_land_use` instead.
     */
    public function copRows(): array
    {
        return array_values((array) data_get($this->payload, 'cop_rows', []));
    }

    /**
     * The new land use to print for this stage.
     *
     * A stage no longer has one answer — a duplex may bring several files to a common
     * purpose, or send them to different ones — so where the files agree this returns
     * the shared code and where they differ it lists them. Returning one file's
     * purpose as if it were the stage's would misstate a conveyance.
     */
    public function newLandUseLabel(): ?string
    {
        $rows = $this->copRows();

        if (empty($rows)) {
            $legacy = trim((string) data_get($this->payload, 'new_land_use', ''));
            return $legacy !== '' ? strtoupper($legacy) : null;
        }

        $uses = array_values(array_unique(array_filter(array_map(
            fn ($r) => strtoupper(trim((string) ($r['new_land_use'] ?? ''))),
            $rows
        ))));

        if (empty($uses)) {
            return null;
        }

        return count($uses) === 1 ? $uses[0] : implode(' / ', $uses);
    }

    /**
     * The land use this stage's parcels are changing FROM.
     *
     * Three sources, in order of authority:
     *
     *   1. the per-file rows, when they agree — the file's own recorded purpose;
     *   2. `current_land_use` on the payload, set from the plan;
     *   3. derived — the last Change of Purpose before this one decided what these
     *      parcels are, because they are the parcels it produced.
     *
     * Falling back to the DUPLEX's land use is the last resort and is usually wrong
     * for a later stage: the duplex takes its land use from its first source file,
     * which a mid-plan parcel has long since stopped being. That fallback is what
     * printed "change of purpose from commercial to commercial use".
     */
    public function currentLandUseLabel(): ?string
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($r) => strtoupper(trim((string) ($r['current_land_use'] ?? ''))),
            $this->copRows()
        ))));

        if (count($codes) === 1) {
            return $codes[0];
        }

        $stored = strtoupper(trim((string) data_get($this->payload, 'current_land_use', '')));
        if ($stored !== '') {
            return $stored;
        }

        $prior = static::query()
            ->where('duplex_parcel_update_id', $this->duplex_parcel_update_id)
            ->where('type', 'change_of_purpose')
            ->where('rank', '<', $this->rank)
            ->reorder('rank', 'desc')
            ->first();

        // Only a single, unambiguous purpose can stand in. Where the earlier stage
        // sent files to different uses there is no one answer to print.
        $inherited = $prior?->newLandUseLabel();
        if ($inherited && !str_contains($inherited, ' / ')) {
            return $inherited;
        }

        return $this->duplex?->land_use ? strtoupper((string) $this->duplex->land_use) : null;
    }

    /** True when the files in this stage are not all changing to the same purpose. */
    public function hasMixedNewLandUses(): bool
    {
        return str_contains((string) $this->newLandUseLabel(), ' / ');
    }

    public function outputCount(): int
    {
        if (in_array($this->type, ['merger', 'extension'], true)) {
            return 1;
        }

        return max(1, (int) ($this->plot_count ?? 1));
    }
}
