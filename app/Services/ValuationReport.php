<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValuationReport extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'valuation_reports';

    protected $fillable = [
        'file_number',
        'inspection_date',
        'land_use_purpose',
        'valuation_purpose',
        'valuation_type',
        'full_name',
        'address',
        'property_no',
        'street_name',
        'estate_quarters',
        'town_city',
        'lga',
        'property_type',
        'num_blocks',
        'storey_height',
        'bedroom_units',
        'ancillary_bldgs',
        'current_use',
        'use',
        'property_desc',
        'foundation_type',
        'foundation_stage',
        'floor_type',
        'floor_finish',
        'walls_type',
        'walls_finish',
        'roof_type',
        'roof_material',
        'ceiling_detail',
        'ext_doors',
        'window_type',
        'burglary_proof',
        'toilet_bath',
        'water_supply',
        'electricity',
        'drainage',
        'fittings_summary',
        'remarks',
        'valuation_basis',
        'value_words',
        'value_figures',
        'surveyor_name',
        'surveyor_rank',
        'chief_valuer',
        'status',
        'print_count',
        're_evaluate',
        'parent_id',
        'user_id',
        'neighborhood_characteristics',
        'units_per_block',
        'walls_stage',
        'boundary_wall_type',
        'boundary_wall_height',
        'boundary_wall_finish',
        'gate_type',
        'int_doors',
        'door_security_proof',
        'fittings_sitting',
        'fittings_dining',
        'fittings_bedroom',
        'fittings_toilet',
        'fittings_bath',
        'fittings_kitchen',
        'fittings_store',
        'fittings_bq',
        'other_info',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'print_count' => 'integer',
        're_evaluate' => 'integer'
    ];

    /**
     * Get the user who created the report.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
