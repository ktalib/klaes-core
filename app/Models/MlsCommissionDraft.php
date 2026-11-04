<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Commission New File Number (MLS FC) card still being keyed.
 *
 * @see \App\Http\Controllers\MlsCommissionDraftController
 */
class MlsCommissionDraft extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'mls_commission_drafts';

    const STATUS_OPEN      = 'open';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'draft_key',
        'user_id',
        'file_title',
        'file_type',
        'land_use',
        'plot_no',
        'district',
        'lga',
        'payload',
        'status',
        'committed_file_numbers',
        'last_saved_at',
    ];

    protected $casts = [
        'payload'       => 'array',
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
            'file_title'       => $this->file_title,
            'file_type'        => $this->file_type,
            'land_use'         => $this->land_use,
            'plot_no'          => $this->plot_no,
            'district'         => $this->district,
            'lga'              => $this->lga,
            'last_saved_at'    => optional($this->last_saved_at)->toIso8601String(),
            'last_saved_human' => optional($this->last_saved_at)->diffForHumans(),
        ];

        if ($withPayload) {
            $data['payload'] = $this->payload ?: [];
        }

        return $data;
    }
}
