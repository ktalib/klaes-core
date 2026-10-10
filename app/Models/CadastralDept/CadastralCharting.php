<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** A charting card: one per charting stage of a case; corrections are new versions. */
class CadastralCharting extends Model
{
    public const STAGES = [
        'single' => 'Charting',
        'first' => '1st charting',
        'second' => '2nd charting',
        'check' => 'Charting check',
    ];

    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_chartings';

    protected $guarded = ['id'];

    protected $casts = [
        'charted' => 'boolean',
        'falls_on_existing' => 'boolean',
        'agrees_with_first' => 'boolean',
        'matches_record' => 'boolean',
        'is_current' => 'boolean',
        'charted_at' => 'datetime',
        'area_sqm' => 'float',
    ];

    public function case()
    {
        return $this->belongsTo(CadastralCase::class, 'cadastral_case_id');
    }

    public function beacons()
    {
        return $this->hasMany(CadastralChartingBeacon::class, 'cadastral_charting_id')->orderBy('sort_order');
    }

    public function officer()
    {
        return $this->belongsTo(User::class, 'charted_by');
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? $this->stage;
    }

    /** Something on the card that the next desk should see. */
    public function flags(): array
    {
        return array_values(array_filter([
            $this->charted === false ? 'Not charted: ' . $this->not_charted_reason : null,
            $this->falls_on_existing ? 'Falls on existing plot (' . ($this->overlap_extent ?: '?') . '): ' . $this->existing_files : null,
            $this->agrees_with_first === false ? 'Differs from 1st charting: ' . $this->agree_note : null,
            $this->matches_record === false ? 'Does not match the record: ' . $this->match_note : null,
        ]));
    }
}
