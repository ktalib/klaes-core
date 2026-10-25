<?php

namespace App\Models\Survey;

use App\Models\CadastralDept\CadastralCase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file received by Survey's Examination/Verification unit: from Cadastral (one
 * line per hand-over) or registered in Survey. Status moves received ->
 * in_progress -> cleared (sent back to the requesting unit) or returned (with a
 * query). A closed line is frozen.
 */
class SurveyRegisterFile extends SurveyModel
{
    protected $table = 'survey_register_files';

    protected $casts = [
        'received_at'           => 'datetime',
        'closed_at'             => 'datetime',
        'cadastral_case_id'     => 'integer',
        'cadastral_movement_id' => 'integer',
        'file_indexing_id'      => 'integer',
        'closed_by'             => 'integer',
    ];

    public const PURPOSE_VERIFICATION = 'verification';
    public const PURPOSE_EXAMINATION  = 'examination';

    public const SOURCE_CADASTRAL = 'cadastral';
    public const SOURCE_SURVEY    = 'survey';

    public const RECEIVED    = 'received';
    public const IN_PROGRESS = 'in_progress';
    public const CLEARED     = 'cleared';
    public const RETURNED    = 'returned';

    public const STATUSES = [
        self::RECEIVED    => 'Received',
        self::IN_PROGRESS => 'In progress',
        self::CLEARED     => 'Cleared',
        self::RETURNED    => 'Returned',
    ];

    /** Status chip colours, as on the Cadastral register. */
    public const STATUS_COLOURS = [
        self::RECEIVED    => 'yellow',
        self::IN_PROGRESS => 'blue',
        self::CLEARED     => 'green',
        self::RETURNED    => 'red',
    ];

    public const PURPOSES = [
        self::PURPOSE_VERIFICATION => 'Verification',
        self::PURPOSE_EXAMINATION  => 'Examination',
    ];

    /** What the unit records on clearing, per purpose. */
    public const FINDINGS = [
        self::PURPOSE_VERIFICATION => [
            'not_on_acquisition' => 'Does not fall on Government acquisition',
            'on_acquisition'     => 'Falls on Government acquisition',
        ],
        self::PURPOSE_EXAMINATION => [
            'plan_in_order'    => 'Survey plan in order',
            'changes_required' => 'Changes required (Surveyors Report)',
        ],
    ];

    public function cadastralCase(): BelongsTo
    {
        return $this->belongsTo(CadastralCase::class, 'cadastral_case_id');
    }

    public function isFrozen(): bool
    {
        return in_array($this->status, [self::CLEARED, self::RETURNED], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function findingLabel(): ?string
    {
        return $this->finding ? (self::FINDINGS[$this->purpose][$this->finding] ?? $this->finding) : null;
    }

    /**
     * For a Cadastral line: has the case moved on from Survey's desk? The Cadastral
     * desk records the outcome when the file comes back, so this reads it live.
     */
    public function backInCadastral(): ?bool
    {
        if ($this->source !== self::SOURCE_CADASTRAL || ! $this->cadastralCase) return null;

        return $this->cadastralCase->current_desk !== \App\Services\SurveyRegisterSync::DESKS_BY_PURPOSE[$this->purpose];
    }
}
