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
    protected $casts = ['read_at' => 'datetime', 'company_read_at' => 'datetime'];

    /** The account the notice was sent to (null for one sent to a whole company). */
    public function account()
    {
        return $this->belongsTo(SurveyorPortalAccount::class, 'surveyor_portal_account_id');
    }

    /** The company's surveyor accounts, as a sub-query. */
    private static function companySurveyors(SurveyorPortalAccount $company)
    {
        return SurveyorPortalAccount::query()->surveyors()->where('company_id', $company->company_id)->select('id');
    }

    /**
     * What the account sees. A surveyor: its own notices and its company's. A company login (2026-11-10): its own,
     * the company's, and every notice sent to its surveyors, so what the SG sends them reaches the company too.
     */
    public function scopeVisibleTo(Builder $query, SurveyorPortalAccount $account): Builder
    {
        return $query->where(function ($q) use ($account) {
            $q->where('surveyor_portal_account_id', $account->id);
            if ($account->company_id) {
                $q->orWhere('company_id', $account->company_id);
                if ($account->isCompany()) {
                    $q->orWhereIn('surveyor_portal_account_id', self::companySurveyors($account));
                }
            }
        });
    }

    /** Unread for this account. A company reads its surveyors' notices with its own mark (company_read_at). */
    public function scopeUnreadFor(Builder $query, SurveyorPortalAccount $account): Builder
    {
        if (! $account->isCompany() || ! $account->company_id) {
            return $query->visibleTo($account)->whereNull('read_at');
        }

        return $query->where(function ($q) use ($account) {
            $q->where(fn ($own) => $own->where(fn ($w) => $w->where('surveyor_portal_account_id', $account->id)->orWhere('company_id', $account->company_id))
                    ->whereNull('read_at'))
              ->orWhere(fn ($theirs) => $theirs->whereIn('surveyor_portal_account_id', self::companySurveyors($account))->whereNull('company_read_at'));
        });
    }

    /** Mark everything the account sees as read, without touching a surveyor's own read mark from the company. */
    public static function markReadFor(SurveyorPortalAccount $account): void
    {
        if (! $account->isCompany() || ! $account->company_id) {
            static::query()->visibleTo($account)->whereNull('read_at')->update(['read_at' => now()]);

            return;
        }
        static::query()->where(fn ($w) => $w->where('surveyor_portal_account_id', $account->id)->orWhere('company_id', $account->company_id))
            ->whereNull('read_at')->update(['read_at' => now()]);
        static::query()->whereIn('surveyor_portal_account_id', self::companySurveyors($account))
            ->whereNull('company_read_at')->update(['company_read_at' => now()]);
    }

    /** Has this account read it? */
    public function isReadBy(SurveyorPortalAccount $account): bool
    {
        $theirs = $account->isCompany() && $this->surveyor_portal_account_id && (int) $this->surveyor_portal_account_id !== (int) $account->id;

        return $theirs ? $this->company_read_at !== null : $this->read_at !== null;
    }

    /** For a company: the surveyor it was sent to, when it is one of theirs. */
    public function surveyorFor(SurveyorPortalAccount $account): ?string
    {
        return $account->isCompany() && $this->surveyor_portal_account_id && (int) $this->surveyor_portal_account_id !== (int) $account->id
            ? ($this->account?->name ?: 'a surveyor') : null;
    }
}
