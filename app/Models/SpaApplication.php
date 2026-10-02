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
    ];

    protected $casts = [
        'is_indexed' => 'boolean',
        'photos'     => 'array',
    ];

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

    public static function generateCustomaryFileNumber(): string
    {
        $year = now()->format('Y');
        $last = DB::connection('sqlsrv')
            ->table('spa_applications')
            ->where('file_number', 'like', "SPAS-{$year}-%")
            ->orderByDesc('id')
            ->value('file_number');

        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

        return "SPAS-{$year}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
