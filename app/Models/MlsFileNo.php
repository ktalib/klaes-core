<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\GenderNormalizer;

class MlsFileNo extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'mls_file_no';

    protected $fillable = [
        'land_use',
        'year',
        'serial_number',
        'full_file_number',
        'file_name',
        'house_no',
        'street_name',
        'plot_no',
        'tp_no',
        'location',
        'lga',
        'district',
        'tracking_id',
        'customer_type',
        'gender',
        'gender_source',
        'file_option',
        'batch_no',
        'created_by',
        'commissioning_date',
        'commissioning_time',
        'is_deleted',
        'purpose_id',
        'source',
        'sub_source',
        // 'MLS' or 'OSS' — which system commissioned the file. The MLS file list
        // filters on this; see App\Support\OssOpCommissionFilter.
        'system_sub_type',
        'source_instrument_capture_id',
        'source_pra_id',
        // Applicant contact. The columns were added in Feb 2026 and the
        // commissioning form has posted them ever since, but they were never in
        // $fillable, so every mass-assignment silently dropped them and the
        // column stayed 100% empty. The commissioning SMS needs them.
        'phone_no',
        'rep_phone_no',
        // Optional applicant email from the commissioning form; becomes the
        // LAAS Portal account's email. See LaasCommissioningAccountService.
        'email',
        'sit_reason',
        // Old (duplicated) file number kept when a file number is re-issued.
        'old_fileno'
    ];

    protected static function booted()
    {
        // Commissioning requires gender on the form, but batch and service paths
        // do not go through it. Same fallback as FileIndexing: infer from the
        // holder name at creation, and always record the provenance.
        static::creating(function (MlsFileNo $model) {
            if ($model->gender !== null) {
                $model->gender_source = $model->gender_source ?: GenderNormalizer::SOURCE_CAPTURED;

                return;
            }

            $inferred = app(GenderNormalizer::class)->classify($model->file_name);

            if ($inferred !== null) {
                [$model->gender, $model->gender_source] = $inferred;
            }
        });
    }

    /** Every write path folds onto the client's four values. See GenderNormalizer. */
    public function setGenderAttribute($value): void
    {
        $this->attributes['gender'] = app(GenderNormalizer::class)->normalize($value);
    }

    public function purpose()
    {
        return $this->belongsTo(Purpose::class, 'purpose_id');
    }

    protected $casts = [
        'is_deleted' => 'boolean',
        'year' => 'integer',
        'serial_number' => 'integer'
    ];

    /**
     * Generate formatted file number
     */
    public static function generateFileNumber($landUse, $year, $serial)
    {
        return "{$landUse}-{$year}-{$serial}";
    }

    /**
     * Scope to filter by land use
     */
    public function scopeByLandUse($query, $landUse)
    {
        return $query->where('land_use', $landUse);
    }

    /**
     * Scope to filter by year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to get only active (non-deleted) records
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
        });
    }

    /**
     * Check if file number exists
     */
    public static function fileNumberExists($fileNumber)
    {
        return self::where('full_file_number', $fileNumber)->exists();
    }
}
