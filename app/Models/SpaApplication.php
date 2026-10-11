<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpaApplication extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_applications';

    protected $fillable = [
        'file_number', 'tracking_id', 'file_indexing_id', 'is_indexed', 'land_title_type',
        'owner_name', 'phone', 'location', 'district', 'lga',
        'land_use_type', 'existing_use', 'proposed_use',
        'scenario', 'status', 'created_by', 'photos',
        // Offline sync (database/sql/2026_08_15_spas_offline_sync_schema.sql):
        // device-generated id that makes a create idempotent on retry.
        'client_uuid',
        // SPAS-YYYY-####, on every record (migration 2026_10_09_120000).
        'spa_id',
        // The property's QR sticker, typed or scanned (migration 2026_10_09_150000).
        'sticker_number',
    ];

    protected $casts = [
        'is_indexed' => 'boolean',
        'photos'     => 'array',
    ];

    /**
     * Text stored in CAPITALS, the way the register is written, whichever form or
     * device saves it — they used to arrive mixed ("BADAWA PHAS II, Nasarawa").
     * File number, phone and the status/type codes are left exactly as given.
     */
    private const UPPERCASE_FIELDS = [
        'owner_name', 'location', 'district', 'lga',
        'land_use_type', 'existing_use', 'proposed_use',
    ];

    public function setAttribute($key, $value)
    {
        if (in_array($key, self::UPPERCASE_FIELDS, true) && is_string($value)) {
            $value = trim($value) === '' ? $value : mb_strtoupper(trim($value), 'UTF-8');
        }

        return parent::setAttribute($key, $value);
    }

    public function fieldData()
    {
        return $this->hasMany(SpaFieldData::class, 'spa_application_id');
    }

    public function notices()
    {
        return $this->hasMany(SpaNotice::class, 'spa_application_id');
    }

    public function firstNotice()
    {
        return $this->hasOne(SpaNotice::class, 'spa_application_id')->where('notice_type', 'first')->latest();
    }

    public function secondNotice()
    {
        return $this->hasOne(SpaNotice::class, 'spa_application_id')->where('notice_type', 'second')->latest();
    }

    public function bills()
    {
        return $this->hasMany(SpaBill::class, 'spa_application_id');
    }

    public function payments()
    {
        return $this->hasMany(SpaPayment::class, 'spa_application_id');
    }

    public function departmentReferrals()
    {
        return $this->hasMany(SpaDepartmentReferral::class, 'spa_application_id');
    }

    public function memos()
    {
        return $this->hasMany(SpaMemo::class, 'spa_application_id');
    }

    public function certificate()
    {
        return $this->hasOne(SpaCertificate::class, 'spa_application_id');
    }

    private static ?bool $hasSticker = null;

    /** Whether the sticker_number column exists yet. */
    public static function supportsSticker(): bool
    {
        return self::$hasSticker ??= \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('spa_applications', 'sticker_number');
    }

    private static ?bool $hasSpaId = null;

    /** Whether the spa_id column exists yet (code works before the migration). */
    public static function supportsSpaId(): bool
    {
        return self::$hasSpaId ??= \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('spa_applications', 'spa_id');
    }

    /**
     * The next SPAS ID, SPAS-YYYY-####. One sequence for every record: a
     * customary title uses it as its file number too, so both columns are
     * read to find the highest number taken this year.
     */
    public static function generateSpasId(): string
    {
        $year = now()->format('Y');
        $max  = 0;

        foreach (self::supportsSpaId() ? ['spa_id', 'file_number'] : ['file_number'] as $col) {
            $values = DB::connection('sqlsrv')->table('spa_applications')
                ->where($col, 'like', "SPAS-{$year}-%")->pluck($col);
            foreach ($values as $v) {
                $max = max($max, (int) substr($v, strrpos($v, '-') + 1));
            }
        }

        return "SPAS-{$year}-" . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Kept for existing callers: a customary file number is the SPAS ID. */
    public static function generateCustomaryFileNumber(): string
    {
        return self::generateSpasId();
    }

    /** What to show or match on when the owner gave no file number. */
    public function getReferenceAttribute(): ?string
    {
        return $this->file_number ?: ($this->spa_id ?? null);
    }
}
