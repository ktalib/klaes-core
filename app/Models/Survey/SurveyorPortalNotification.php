<?php

namespace App\Models\Survey;

use App\Models\Survey\SurveyorPortalAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A notice in a surveyor's portal. The staff bell's table points at staff users, so the portal has its own. */
class SurveyorPortalNotification extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'surveyor_portal_notifications';
    protected $guarded = ['id'];
    protected $casts = ['read_at' => 'datetime'];

    public function scopeVisibleTo(Builder $query, SurveyorPortalAccount $account): Builder
    {
        return $query->where(function ($q) use ($account) {
            $q->where('surveyor_portal_account_id', $account->id);
            if ($account->company_id) {
                $q->orWhere('company_id', $account->company_id);
            }
        });
    }
}
