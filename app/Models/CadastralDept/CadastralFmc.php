<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** File Movement Card: one per case, commissioned at AD Cad Info. */
class CadastralFmc extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_fmcs';

    protected $guarded = ['id'];

    protected $casts = ['commissioned_at' => 'datetime'];

    public function case()
    {
        return $this->belongsTo(CadastralCase::class, 'cadastral_case_id');
    }

    public function logs()
    {
        return $this->hasMany(CadastralFmcLog::class, 'cadastral_fmc_id')->orderBy('logged_at')->orderBy('id');
    }

    public function commissioner()
    {
        return $this->belongsTo(User::class, 'commissioned_by');
    }
}
