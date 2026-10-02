<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlotSeparationApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'plot_separation_applications';

    public const STATUS_PENDING      = 'pending';
    public const STATUS_APPROVED     = 'approved';
    public const STATUS_REJECTED     = 'rejected';
    public const STATUS_COMMISSIONED = 'commissioned';

    protected $fillable = [
        'file_no',
        'file_title',
        'applicant_name',
        'plot_no',
        'house_no',
        'street_name',
        'district',
        'lga',
        'state',
        'land_use',
        'residential_address',
        'correspondence_address',
        'nationality',
        'occupation',
        'num_plots',
        'status',
        'remarks',
        'captured_by',
        'updated_by',
        'knupda_status',
        'knupda_fee',
        'land_value',
        'knupda_remarks',
        'application_generated_at',
        'recommendation_generated_at',
        'site_plan',
        'ownership_document',
        'application_letter',
        'means_of_id',
        'tax_clearance',
        'is_deleted',
        'deleted_by',
        'deleted_at',
    ];

    public function plotSizes()
    {
        return $this->hasMany(PlotApplicationSize::class, 'application_id')->where('application_type', 'separation');
    }
}
