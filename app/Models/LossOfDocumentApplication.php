<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Loss of Document application, raised under Parcel/Title Management (Post CofO).
 * module says which list it belongs to (Land FC or OSS). See the table's migration.
 */
class LossOfDocumentApplication extends Model
{
    public const MODULE_LAND = 'land';
    public const MODULE_OSS = 'oss';

    public const KIND_REGULAR = 'regular';
    public const KIND_OP = 'op';

    public const STATUS_PENDING = 'pending';

    protected $connection = 'sqlsrv';

    protected $table = 'loss_of_document_applications';

    protected $fillable = [
        'module',
        'document_kind',
        'applicant_name',
        'file_no',
        'op_serial_number',
        'op_source',
        'op_id',
        'op_type',
        'nationality',
        'occupation',
        'phone',
        'email',
        'residential_address',
        'correspondence_address',
        'land_use',
        'plot_no',
        'plan_no',
        'district',
        'lga',
        'location',
        'remarks',
        'passport_photo',
        'status',
        'captured_by',
        'updated_by',
    ];
}
