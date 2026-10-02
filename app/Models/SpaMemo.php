<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SpaMemo extends Model
{
    use \App\Models\Concerns\GeneratesSpaReference;

    protected $connection = 'sqlsrv';
    protected $table      = 'spa_memos';

    protected $fillable = [
        'spa_application_id', 'memo_no', 'prepared_by',
        'forwarded_to', 'forwarded_at',
        'commissioner_decision', 'commissioner_notes', 'decided_at',
        'created_by',
    ];

    protected $casts = [
        'forwarded_at' => 'datetime',
        'decided_at'   => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'prepared_by');
    }

    /**
     * SPAS/YYYY/#### — numbers issued before 2026-08-18 carry the older "SPA"
     * prefix and are counted too, so the sequence continues rather than
     * restarting. See GeneratesSpaReference.
     */
    public static function generateMemoNumber(): string
    {
        $year = now()->format('Y');

        $issued = DB::connection('sqlsrv')
            ->table('spa_memos')
            ->where('memo_no', 'like', self::spaPrefixPattern("/{$year}/%"))
            ->pluck('memo_no');

        return "SPAS/{$year}/".str_pad((string) self::nextSpaSequence($issued), 4, '0', STR_PAD_LEFT);
    }
}
