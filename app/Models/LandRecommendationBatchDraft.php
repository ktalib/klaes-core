<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An in-progress Plot Subdivision batch capture.
 *
 * @see \App\Http\Controllers\LandRecommendationBatchDraftController
 */
class LandRecommendationBatchDraft extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'land_recommendation_batch_drafts';

    const STATUS_OPEN      = 'open';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'draft_key',
        'user_id',
        'application_type',
        'mother_file_no',
        'children_total',
        'children_selected',
        'payload',
        'payload_previous',
        'previous_children_total',
        'previous_saved_at',
        'status',
        'rofo_batch_id',
        'last_saved_at',
    ];

    protected $casts = [
        'payload'                 => 'array',
        'payload_previous'        => 'array',
        'children_total'          => 'integer',
        'children_selected'       => 'integer',
        'previous_children_total' => 'integer',
        'last_saved_at'           => 'datetime',
        'previous_saved_at'       => 'datetime',
    ];

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * The shape the resume list and the restore endpoint both hand back.
     */
    public function toClientArray(bool $withPayload = false): array
    {
        $data = [
            'draft_key'         => $this->draft_key,
            'mother_file_no'    => $this->mother_file_no,
            'application_type'  => $this->application_type,
            'children_total'    => $this->children_total,
            'children_selected' => $this->children_selected,
            'status'            => $this->status,
            'last_saved_at'     => optional($this->last_saved_at)->toIso8601String(),
            'last_saved_human'  => optional($this->last_saved_at)->diffForHumans(),

            // One step of undo, offered only when the copy it holds is worth going
            // back to — see the note on the payload_previous migration.
            'has_previous'            => !empty($this->payload_previous),
            'previous_children_total' => $this->previous_children_total,
            'previous_saved_human'    => optional($this->previous_saved_at)->diffForHumans(),
        ];

        if ($withPayload) {
            $data['payload'] = $this->payload ?: [];
        }

        return $data;
    }
}
