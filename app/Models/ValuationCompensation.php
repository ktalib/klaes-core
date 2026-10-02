<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValuationCompensation extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'valuation_compensations';

    protected $fillable = [
        'our_ref',
        'your_ref',
        'valuation_date',
        'location',
        'owner_name',
        'plot_no',
        'street_name',
        'building_type',
        'building_count',
        'area_covered',
        'rate_of_cost',
        'compensation_amount',
        'account_name',
        'account_number',
        'bank_name',
        'phone_number',
        'nin',
        'remarks',
        'compensated_items',
        'compensated_items_other',
        'completion_stage',
        'number_of_floors',

        'project_id',
        'sub_project_id',
        'project_fileno',
        'worker_id',
        'is_deleted',
        'user_id',
        'latitude',
        'longitude',
        'apply_percentage',
        'length',
        'breadth'
    ];

    protected $casts = [
        'valuation_date' => 'date',
        'building_count' => 'integer',
        'area_covered' => 'decimal:2',
        'rate_of_cost' => 'decimal:2',
        'compensation_amount' => 'decimal:2',
        'is_deleted' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'length' => 'decimal:2',
        'breadth' => 'decimal:2'
    ];

    /**
     * Scope to filter only non-deleted records.
     */
    public function scopeActive($query)
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * The true compensation total for this record = building amounts + compensated
     * item amounts. For the JSON compensated_items format, compensation_amount holds
     * only the buildings portion, so the items (e.g. a fence/wall on an "Allow"
     * building with no area/rate) must be added. Legacy string records keep the
     * stored compensation_amount, which already represents the full total.
     */
    public function getTotalCompensationAttribute(): float
    {
        $raw = $this->compensated_items;
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['buildings'])) {
                $buildings = collect($decoded['buildings'] ?? [])->sum(fn ($b) => (float) ($b['amount'] ?? 0));
                $items     = collect($decoded['items'] ?? [])->sum(fn ($i) => (float) ($i['amount'] ?? 0));
                return $buildings + $items;
            }
        }

        return (float) $this->compensation_amount;
    }

    /**
     * Relationship to the user who created the record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function subProject()
    {
        return $this->belongsTo(SubProject::class, 'sub_project_id');
    }

    public function worker()
    {
        return $this->belongsTo(ProjectWorker::class, 'worker_id', 'worker_code');
    }
}
