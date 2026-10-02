<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileIndexingLink extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'file_indexing_links';

    protected $fillable = [
        'file_indexing_id',
        'main_application_id',
        'subapplication_id',
        'recertification_application_id',
        'tracking_id',
        'st_fillno',
        'serial_no',
        'batch_no',
        'st_batch_no',
        'shelf_location',
        'shelf_label_id',
        'sys_batch_no',
        'group_no',
        'file_number',
        'file_title',
        'land_use_type',
        'plot_number',
        'tp_no',
        'lpkn_no',
        'location',
        'district',
        'registry',
        'physical_registry',
        'general_registry',
        'lga',
        'property_description',
        'file_type',
        'has_cofo',
        'is_merged',
        'has_transaction',
        'is_problematic',
        'is_co_owned_plot',
        'test_control',
        'is_updated',
        'workflow_status',
        'has_qc_issues',
        'created_by',
        'updated_by',
        'registry_batch_no',
        'batch_generated',
        'last_batch_id',
        'batch_generated_at',
        'batch_generated_by',
        'is_deleted',
        'deleted_at',
        'indexing_type',
        'mfile',
        'entity_type',
        'entity_name',
        'entity_physical_address',
        'customer_type',
        'customer_name',
        'customer_account_no',
        'customer_code',
        'property_address',
        'customer_status',
        'plot_size',
        'email',
        'dob',
        'nin',
        'tin',
        'rc_no',
        'phone',
        'residence_address',
        'country_code',
    ];

    protected $casts = [
        'has_cofo' => 'boolean',
        'is_merged' => 'boolean',
        'has_transaction' => 'boolean',
        'is_problematic' => 'boolean',
        'is_co_owned_plot' => 'boolean',
        'is_updated' => 'boolean',
        'is_deleted' => 'boolean',
        'has_qc_issues' => 'boolean',
        'batch_generated' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'batch_generated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'dob' => 'date',
    ];

    /**
     * Get the parent file indexing record by ID.
     */
    public function fileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }

    /**
     * Get the main file indexing record this is linked to by file number.
     */
    public function mainFileIndexing()
    {
        return $this->belongsTo(FileIndexing::class, 'file_number', 'file_number');
    }
}
