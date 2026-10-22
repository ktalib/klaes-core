<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A recertification application still being keyed.
 *
 * @see \App\Http\Controllers\RecertificationDraftController
 */
class RecertificationDraft extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'recertification_drafts';

    const STATUS_OPEN      = 'open';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'draft_key',
        'user_id',
        'land_file_no',
        'applicant_name',
        'current_step',
        'payload',
        'status',
        'application_id',
        'last_saved_at',
    ];

    protected $casts = [
        'payload'       => 'array',
        'current_step'  => 'integer',
        'last_saved_at' => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function toClientArray(bool $withPayload = false): array
    {
        $data = [
            'draft_key'        => $this->draft_key,
            'land_file_no'     => $this->land_file_no,
            'applicant_name'   => $this->applicant_name,
            'current_step'     => $this->current_step,
            'last_saved_at'    => optional($this->last_saved_at)->toIso8601String(),
            'last_saved_human' => optional($this->last_saved_at)->diffForHumans(),
        ];

        if ($withPayload) {
            $data['payload'] = $this->payload ?: [];
        }

        return $data;
    }
}
