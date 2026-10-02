<?php

namespace App\Models;

use App\Models\Concerns\HasBoundarySegments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JointSiteInspectionReport extends Model
{
    use HasFactory;

    // The four-sides parser/compiler is shared with MasterJsiReport: both sheets
    // print "On the North: ..." and they have to read identically.
    use HasBoundarySegments;

    protected $connection = 'sqlsrv';

    protected $table = 'joint_site_inspection_reports';

    protected $fillable = [
        'application_id',
        'sub_application_id',
        'file_number',
        'inspection_date',
        'lkn_number',
        'applicant_name',
        'applicant_address',
        'location',
        'location_components',
        'applicant_address_components',
        'plot_number',
        'scheme_number',
        'available_on_ground',
        'boundary_description',
        'sections_count',
        'unit_dimension',
        'unit_number',
        'inspection_officer_id',
        'source',
        'purpose',
        'private_layout_number',
        'conformity',
        'accessibility',
        'existing_road_reservation',
        'recommended_road_reservation',
        'observed_road_category',
        'recommended_comment_status',
        'how_many_meters',
        'adequate_size_requirement',
        'land_traversing_utility',
        'existing_site_length',
        'existing_site_width',
        'existing_site_area',
        'recommended_site_length',
        'recommended_site_width',
        'recommended_site_area',
        'road_reservation',
        'prevailing_land_use',
        'applied_land_use',
        'shared_utilities',
        'compliance_status',
        'has_additional_observations',
        'additional_observations',
        'inspection_officer',
        'existing_site_measurement_summary',
        'existing_site_measurement_entries',
        'is_generated',
        'is_submitted',
        'generated_at',
        'submitted_at',
        'generated_by',
        'submitted_by',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'unit_number',
        'boundary_segments',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'available_on_ground' => 'boolean',
        'inspection_officer_id' => 'integer',
        'conformity' => 'boolean',
        'accessibility' => 'boolean',
        'adequate_size_requirement' => 'boolean',
        'land_traversing_utility' => 'boolean',
        'existing_site_length' => 'string',
        'existing_site_width' => 'string',
        'existing_site_area' => 'decimal:2',
        'recommended_site_length' => 'string',
        'recommended_site_width' => 'string',
        'recommended_site_area' => 'decimal:2',
        'has_additional_observations' => 'boolean',
        'is_generated' => 'boolean',
        'is_submitted' => 'boolean',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'shared_utilities' => 'array',
        'existing_site_measurement_entries' => 'array',
        'location_components' => 'array',
        'applicant_address_components' => 'array',
    ];

    protected function unitNumber(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                $rawValue = $value;

                if ($rawValue === null || $rawValue === '') {
                    return null;
                }

                $stringValue = trim((string) $rawValue);
                return $stringValue === '' ? null : $stringValue;
            },
            set: function ($value) {
                if ($value === null || $value === '') {
                    return ['unit_number' => null];
                }

                $stringValue = trim((string) $value);

                return ['unit_number' => $stringValue === '' ? null : $stringValue];
            }
        );
    }

}
