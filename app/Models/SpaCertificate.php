<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpaCertificate extends Model
{
    use \App\Models\Concerns\GeneratesSpaReference;

    protected $connection = 'sqlsrv';
    protected $table      = 'spa_certificates';

    protected $fillable = [
        'spa_application_id', 'cert_number', 'file_number', 'new_file_number',
        'holder_name', 'from_use', 'to_use',
        'issue_date', 'expiry_date', 'issued_by', 'status', 'created_by',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'expiry_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'issued_by');
    }

    /**
     * SPAS-COP/YYYY/#### — sheets issued before 2026-08-18 carry the older
     * "SPA-COP" prefix and are counted too. See GeneratesSpaReference.
     */
    public static function generateCertNumber(): string
    {
        $year = now()->format('Y');

        $issued = DB::connection('sqlsrv')
            ->table('spa_certificates')
            ->where('cert_number', 'like', self::spaPrefixPattern("-COP/{$year}/%"))
            ->pluck('cert_number');

        return "SPAS-COP/{$year}/".str_pad((string) self::nextSpaSequence($issued), 4, '0', STR_PAD_LEFT);
    }
}
