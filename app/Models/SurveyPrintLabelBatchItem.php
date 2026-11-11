<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Survey Print File Labels (user, 2026-11-09): cloned from KangisPrintLabelBatchItem. */
class SurveyPrintLabelBatchItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'survey_print_label_batch_items';

    protected $fillable = [
        'batch_id',
        'file_indexing_id',
        'grouping_id',
        'file_number',
        'prefix',
        'file_title',
        'plot_number',
        'district',
        'lga',
        'land_use_type',
        'shelf_location',
        'qr_code_data',
        'barcode_data',
        'label_position',
        'is_printed',
        'printed_at',
    ];

    protected $casts = [
        'batch_id'          => 'integer',
        'file_indexing_id'  => 'integer',
        'grouping_id'=> 'integer',
        'label_position'    => 'integer',
        'is_printed'      => 'boolean',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'printed_at'      => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SurveyPrintLabelBatch::class, 'batch_id');
    }

    public function fileIndexing(): BelongsTo
    {
        return $this->belongsTo(FileIndexing::class, 'file_indexing_id');
    }
}
