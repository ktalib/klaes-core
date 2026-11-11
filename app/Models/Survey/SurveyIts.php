<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Survey survey job and its Instruction to Survey (GKN section). Registered as a
 * Draft with a job number; issuing gives it an I to S number and locks the
 * instruction. The Surveyors Report, List of Coordinates and Field Observations
 * attach to it.
 */
class SurveyIts extends SurveyModel
{
    protected $table = 'survey_its';

    protected $casts = [
        'its_issued_at'         => 'date',
        'issued_at'             => 'datetime',
        'completed_at'          => 'datetime',
        'surveyor_id'           => 'integer',
        'its_issued_by_user_id' => 'integer',
        'return_date'           => 'date',
        'rec_report'            => 'boolean',
        'rec_survey_diagram'    => 'boolean',
        'rec_field_books'       => 'boolean',
        'rec_computation_sheets' => 'boolean',
        'rec_surveyors_plan'    => 'boolean',
    ];

    /** Page 2's Field Records in the field jacket, in the paper's order (CR 10112026 call): column => label, a tick box each. */
    public const FIELD_RECORDS = [
        'rec_report'             => 'Report',
        'rec_survey_diagram'     => 'Survey Diagram',
        'rec_field_books'        => 'Field Books',
        'rec_computation_sheets' => 'Computation Sheets',
        'rec_surveyors_plan'     => "Surveyor's Plan",
    ];

    /*
     | Item 1's bold words, the data of the two sub-items (user, 2026-11-10), each with Other (specify). The first
     | entry is the paper form's own word and the default; add choices here.
     */
    public const CONTROL_CONDITIONS = ['prove to be in situ'];
    public const DETAIL_CONDITIONS = ['Identifiable'];

    public const DRAFT     = 'Draft';
    public const ISSUED    = 'Issued';
    public const REPORTED  = 'Report Submitted';
    public const COMPLETED = 'Completed';
    public const CANCELLED = 'Cancelled';

    public const STATUSES = [self::DRAFT, self::ISSUED, self::REPORTED, self::COMPLETED, self::CANCELLED];

    public const STATUS_COLOURS = [
        self::DRAFT     => 'gray',
        self::ISSUED    => 'blue',
        self::REPORTED  => 'purple',
        self::COMPLETED => 'green',
        self::CANCELLED => 'red',
    ];

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(SurveyorListEntry::class, 'surveyor_id');
    }

    public function computationFile(): BelongsTo
    {
        return $this->belongsTo(SurveyComputationFile::class, 'survey_computation_file_id');
    }

    /**
     * The Job No. (SUV-JOB-YYYY-NNN) is the Computation File's; the I to S never takes its own (user, 2026-11-11:
     * "Computation File No. must have job no").
     */
    public function jobNo(): ?string
    {
        return $this->survey_computation_file_id ? $this->computationFile?->job_no : null;
    }

    public function isIssued(): bool
    {
        return $this->its_number !== null;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::CANCELLED], true);
    }

    /** Next SRV/ITS/YYYY/0001, from the highest number issued this year. */
    public static function nextItsNumber(): string
    {
        $prefix = 'SRV/ITS/' . date('Y') . '/';
        $last = static::withTrashed()->where('its_number', 'like', $prefix . '%')->orderByDesc('its_number')->value('its_number');
        $n = $last && preg_match('/(\d+)$/', $last, $m) ? (int) $m[1] : 0;

        return $prefix . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
    }

    /** The instruction text offered when the I to S is issued; editable before issue. */
    public function defaultInstructions(): string
    {
        $where = implode(', ', array_filter([
            $this->plot_no ? 'Plot ' . $this->plot_no : null,
            $this->district,
            $this->lga,
        ]));

        return trim('You are hereby instructed to carry out the survey of the property'
            . ($where ? " at {$where}" : '')
            . ", file {$this->file_number}" . ($this->file_title ? " ({$this->file_title})" : '')
            . ", under survey job {$this->job_ref}."
            . ($this->job_scope ? ' Scope: ' . $this->job_scope : ''));
    }
}
