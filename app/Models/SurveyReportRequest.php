<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyReportRequest extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'survey_report_requests';

    protected static function booted(): void
    {
        static::created(function (self $request): void {
            if (!empty($request->serial_no) || empty($request->getKey())) {
                return;
            }

            $request->forceFill([
                'serial_no' => str_pad((string) $request->getKey(), 6, '0', STR_PAD_LEFT),
            ])->saveQuietly();
        });
    }

    protected $fillable = [
        'file_number',
        'file_title',
        'serial_no',
        'lands_section_date',
        'lands_section_name',
        'land_officer_id',
        'lands_section_rank',
        'lands_section_signature',
        'signature_verification_method',
        'signature_verified_at',
        'signature_approved_by',
        'director_cadastral_date',
        'director_name',
        'director_rank',
        'director_signature',
        'rofo_no',
        'item_g_no',
        'plot_description',
        'survey_necessary',
        'beacon_numbers',
        'director_cadastral_approval_date',
        'director_cadastral_signature_method',
        'director_cadastral_signature',
        'director_cadastral_signature_verified_at',
        'director_cadastral_signature_approved_by',
        'commissioner_date',
        'status',
        'print_count',
        'user_id',
    ];

    protected $casts = [
        'lands_section_date' => 'date',
        'signature_verified_at' => 'datetime',
        'director_cadastral_date' => 'date',
        'director_cadastral_approval_date' => 'date',
        'director_cadastral_signature_verified_at' => 'datetime',
        'commissioner_date' => 'date',
        'print_count' => 'integer',
    ];
}
