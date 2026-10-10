<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** A checklist item ticked at a step (config: steps.*.tasks). Append-only. */
class CadastralCaseTask extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_case_tasks';

    protected $guarded = ['id'];

    protected $casts = ['done_at' => 'datetime'];

    public function doer()
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
