<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * One ministry check recorded against an application. Append-only: a re-check
 * adds a row. Lands Registry records it on the application page; Survey records
 * it in Survey → Approvals and Physical Planning in PP Director → Instrument
 * Registration, and the application follows automatically.
 */
class InstrumentApplicationCheck extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_application_checks';

    public const TYPE_LANDS = 'lands';
    public const TYPE_SURVEY = 'survey';
    public const TYPE_PLANNING = 'planning';

    public const OUTCOME_PASSED = 'passed';
    public const OUTCOME_FAILED = 'failed';
    /** The check was turned off in Configurable Entries when the application went past it. */
    public const OUTCOME_SKIPPED = 'skipped';

    public const TYPE_LABELS = [
        self::TYPE_LANDS => 'Lands Registry check',
        self::TYPE_SURVEY => 'Survey — free from Government Acquisition',
        self::TYPE_PLANNING => 'Physical Planning check',
    ];

    /** The department that records each check, for "waiting for …" text. Defaults; see departmentFor(). */
    public const TYPE_DEPARTMENTS = [
        self::TYPE_LANDS => 'Lands Registry',
        self::TYPE_SURVEY => 'Survey',
        self::TYPE_PLANNING => 'Physical Planning',
    ];

    /** The check's name as set in Configurable Entries. */
    public static function labelFor(string $type): string
    {
        return app(\App\Services\InstrumentWorkflow\WorkflowPipeline::class)->label($type);
    }

    /** The check's department as set in Configurable Entries. */
    public static function departmentFor(string $type): string
    {
        return app(\App\Services\InstrumentWorkflow\WorkflowPipeline::class)->department($type);
    }

    /**
     * The Land Information Certificate particulars each check captures
     * : Section A from Lands, Section B from Survey.
     * Section C's plot is drawn from the Survey coordinates.
     */
    public const LIC_FIELDS = [
        self::TYPE_LANDS => [
            'root_of_title' => ['Root of Title', 'text'],
            'registration_particulars' => ['Registration Particulars/Date', 'text'],
            'encumbrances' => ['Encumbrance(s)', 'text'],
            'penalties_due' => ['Penalty(ies) Due', 'text'],
            'recommendation' => ['Recommendations', 'textarea'],
        ],
        self::TYPE_SURVEY => [
            'survey_plan_no' => ['Survey Plan No', 'text'],
            'survey_plan_date' => ['Survey Plan Date', 'date'],
            'area_of_land' => ['Area of Land', 'text'],
            'location_of_land' => ['Location of Land', 'text'],
            'pillar_nos' => ['Pillar Nos', 'text'],
            'coordinates' => ['Ref. Coordinates (UTM Zone 32N)', 'coordinates'],
            'surveyors_name' => ["Surveyor's Name", 'text'],
            'recommendation' => ['Recommendation', 'textarea'],
        ],
    ];

    protected $fillable = [
        'application_id',
        'check_type',
        'outcome',
        'notes',
        'details',
        'checked_by',
        'checked_by_name',
        'checked_at',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'details' => 'array',
    ];

    /**
     * UTM points from the free-text coordinates: one point per line, the last two
     * numbers on the line read as Easting then Northing ("PB 1234  326512.40  611847.10").
     *
     * @return list<array{label: string, e: float, n: float}>
     */
    public static function parseCoordinates(?string $text): array
    {
        $points = [];
        foreach (preg_split('/\r\n|\r|\n|;/', (string) $text) as $index => $line) {
            $line = trim(str_replace(',', ' ', $line));
            // The last two numbers are Easting and Northing; everything before them is the pillar label.
            if (!preg_match('/^(.*?)\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s*$/', $line, $match)) {
                continue;
            }
            $e = (float) $match[2];
            $n = (float) $match[3];
            if ($e < 1000 || $n < 1000) {
                continue; // not a UTM pair
            }
            $label = trim(preg_replace('/\s+/', ' ', $match[1]));
            $points[] = ['label' => $label !== '' ? $label : 'P' . (count($points) + 1), 'e' => $e, 'n' => $n];
        }

        return $points;
    }

    public function application()
    {
        return $this->belongsTo(InstrumentApplication::class, 'application_id');
    }
}

