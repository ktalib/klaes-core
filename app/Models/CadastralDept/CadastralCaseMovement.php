<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Append-only history of a case moving between desks. Never updated or deleted. */
class CadastralCaseMovement extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_case_movements';

    protected $guarded = ['id'];

    protected $casts = ['acted_at' => 'datetime'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
