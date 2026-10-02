<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllocationListEntry extends Model
{
    use HasFactory;

    protected $table = 'allocation_list_stage';
    protected $connection = 'sqlsrv';

    protected $fillable = [
        'title',
        'first_name',
        'middle_name',
        'last_name',
        // Existing-allocation capture: the file number the allocation already
        // carries, plus the details backfilled from it.
        'file_no',
        'file_title',
        'allottee_name',
        'location',
        'allocation_year',
        // Allocation Source: which institution requested it, and who the
        // correspondence is addressed to.
        'institution_category',
        'institution_name',
        'addressed_to',
        'plot_number',
        'district',
        'lga',
        'state',
        'allottee_address',
        'allocated_by',
        'is_allocated',
        'created_by',
        'updated_by',
    ];
}
