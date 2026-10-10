<?php

namespace App\Models\CadastralDept;

use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Cadastral\CadastralSurveyor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A field jacket: the small survey file AD P&D commissions for a case. It is
 * linked to one survey job (whose I to S is the instruction issued) and to the
 * surveyor currently assigned; earlier assignments stay in the history.
 */
class CadastralFieldJacket extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_field_jackets';

    protected $guarded = ['id'];

    protected $casts = [
        'commissioned_at' => 'datetime',
        'print_count' => 'integer',
    ];

    public function case()
    {
        return $this->belongsTo(CadastralCase::class, 'cadastral_case_id');
    }

    public function job()
    {
        return $this->belongsTo(CadastralSurveyJob::class, 'cadastral_survey_job_id');
    }

    public function surveyor()
    {
        return $this->belongsTo(CadastralSurveyor::class, 'cadastral_surveyor_id');
    }

    public function assignments()
    {
        return $this->hasMany(CadastralFieldJacketAssignment::class, 'cadastral_field_jacket_id')->orderBy('id');
    }

    public function commissioner()
    {
        return $this->belongsTo(User::class, 'commissioned_by');
    }
}
