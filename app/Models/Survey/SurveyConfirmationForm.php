<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Confirmation Form, by its serial number (user, 2026-11-02), for a layout: a Survey project, or a
 * layout typed under Other (e.g. BUK/Zakara Layout). layout_name is the name printed on the form.
 * Generated -> Printed (blank form run off) -> Captured (the filled paper keyed in), or Voided.
 */
class SurveyConfirmationForm extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'survey_confirmation_forms';
    protected $guarded = ['id'];

    protected $casts = [
        'printed_at' => 'datetime', 'captured_at' => 'datetime', 'voided_at' => 'datetime',
        'vendor_date' => 'date', 'chairman_date' => 'date', 'village_head_date' => 'date', 'compensation_head_date' => 'date',
        'vendor_signed' => 'boolean', 'chairman_signed' => 'boolean', 'village_head_signed' => 'boolean', 'compensation_head_signed' => 'boolean',
    ];

    public const GENERATED = 'Generated';
    public const PRINTED   = 'Printed';
    public const CAPTURED  = 'Captured';
    public const VOIDED    = 'Voided';
    public const STATUSES  = [self::GENERATED, self::PRINTED, self::CAPTURED, self::VOIDED];

    /** Serials that can still be captured. */
    public const OPEN = [self::GENERATED, self::PRINTED];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SurveyProject::class, 'survey_project_id');
    }

    /** "00001": five digits, as on the printed form. */
    public function getSerialAttribute(): string
    {
        return self::formatSerial((int) $this->serial_no);
    }

    public static function formatSerial(int $n): string
    {
        return str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /** The layout line of the printed heading: "BUK/ZAKARA LAYOUT". */
    public function getLayoutHeadingAttribute(): string
    {
        $name = mb_strtoupper(trim((string) $this->layout_name));

        return $name === '' ? 'LAYOUT' : (str_contains($name, 'LAYOUT') ? $name : $name . ' LAYOUT');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
