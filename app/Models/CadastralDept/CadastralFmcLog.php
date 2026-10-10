<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** A QR log-in or log-out of a unit, made with the FMC. Append-only. */
class CadastralFmcLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_fmc_logs';

    protected $guarded = ['id'];

    protected $casts = ['logged_at' => 'datetime'];

    public function logger()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
