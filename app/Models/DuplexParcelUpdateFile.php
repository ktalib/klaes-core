<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every file a duplex touches, in one place.
 *
 *  - role=source   a real registry file the duplex consumes (retired at commit)
 *  - role=holding  an internal DPX-2026-0007-H03 number; exists only here, never in
 *                  fileNumber / file_indexings / mls_file_no, and is retired at commit
 *  - role=result   the real file number minted at commit
 *
 * A holding row becomes a result row when the commit fills in final_file_no — the
 * pair is what the Land screen shows as "holding -> new file".
 */
class DuplexParcelUpdateFile extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'duplex_parcel_update_files';

    public const ROLE_SOURCE  = 'source';
    public const ROLE_HOLDING = 'holding';
    public const ROLE_RESULT  = 'result';

    /**
     * A file this stage received and did NOT change — it keeps the number the
     * previous stage gave it and simply travels on. No new holding number is minted
     * for it, because no new file number will be either.
     */
    public const ROLE_CARRIED = 'carried';

    protected $fillable = [
        'duplex_parcel_update_id',
        'duplex_parcel_update_stage_id',
        'duplex_id',
        'role',
        'holding_no',
        'source_file_no',
        'final_file_no',
        'file_title',
        'plot_size',
        'holder_name',
        'prop_id',
        'parent_prop_id',
        'will_decommission',
        'decommissioned',
        'sequence',
    ];

    public function duplex(): BelongsTo
    {
        return $this->belongsTo(DuplexParcelUpdate::class, 'duplex_parcel_update_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DuplexParcelUpdateStage::class, 'duplex_parcel_update_stage_id');
    }
}
