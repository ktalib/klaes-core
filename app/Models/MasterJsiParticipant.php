<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person who was present during the joint site inspection.
 *
 * applicant | applicant_representative. Representatives repeat: name, phone and
 * relationship/capacity, so a representative who is neither the applicant nor one
 * of the parties named on the title is still captured.
 */
class MasterJsiParticipant extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_participants';

    public const PARTICIPANT_APPLICANT           = 'applicant';
    public const PARTICIPANT_APPLICANT_REP       = 'applicant_representative';

    protected $fillable = [
        'master_jsi_report_id',
        'participant',
        'name',
        'phone',
        'relationship',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MasterJsiReport::class, 'master_jsi_report_id');
    }

    public function isRepresentative(): bool
    {
        return $this->participant === self::PARTICIPANT_APPLICANT_REP;
    }
}