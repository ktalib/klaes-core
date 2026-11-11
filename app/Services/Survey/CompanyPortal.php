<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyBeaconNumber;
use App\Models\Survey\SurveyBeaconRegister;
use App\Models\Survey\SurveyChangeReport as CR;
use App\Models\Survey\SurveyItsCoordinate as Coord;
use App\Models\Survey\SurveyorPortalAccount as Account;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What a company sees on the MLPP-SG portal (user, 2026-11-10): its surveyors, its beacons (issued and used)
 * and its surveyors' Surveyor's Reports. Read only.
 *
 * - Surveyors: the portal surveyor accounts registered under the company.
 * - Beacons: numbers on Beacon Register lines issued to the company (survey_beacon_register.company_id), from a
 *   portal request or from Survey's Issue Beacon No. Older lines entered without a company are not counted.
 * - Reports: the portal Surveyor's Reports written by the company's surveyors.
 */
class CompanyPortal
{
    public function surveyors(Account $company): Builder
    {
        return Account::query()->surveyors()->where('company_id', $company->company_id);
    }

    /** Every beacon number issued to the company, newest line first. */
    public function beacons(Account $company): Builder
    {
        return SurveyBeaconNumber::query()
            ->whereHas('register', fn ($q) => $q->where('company_id', $company->company_id))
            ->with('register.request');
    }

    public function reports(Account $company): Builder
    {
        return CR::query()->whereIn('surveyor_portal_account_id', $this->surveyors($company)->select('id'));
    }

    public function report(Account $company, int $id): CR
    {
        $r = $this->reports($company)->find($id);
        abort_unless($r, 404);

        return $r;
    }

    /** The company's Surveyor's Report each used beacon is on, keyed by beacon id. */
    public function reportsFor(Account $company, Collection $beacons): Collection
    {
        if ($beacons->isEmpty()) return collect();

        $reports = $this->reports($company)->get(['id', 'report_ref', 'surveyor_name', 'plot_no', 'district', 'lga', 'location'])->keyBy('id');
        $keys = $beacons->mapWithKeys(fn ($b) => [$b->id => $this->key($b->beacon_no)]);
        $onReport = $reports->isEmpty() ? collect() : Coord::whereIn('survey_change_report_id', $reports->keys())
            ->get(['beacon_id', 'survey_change_report_id'])
            ->keyBy(fn ($c) => $this->key($c->beacon_id));

        return $keys->map(fn ($k) => ($c = $onReport->get($k)) ? $reports->get($c->survey_change_report_id) : null);
    }

    /** The register's spelling-free key for a beacon number, so "KDJ 0001" and "kdj0001" match. */
    private function key(?string $beacon): string
    {
        return (string) SurveyBeaconRegister::normalise($beacon);
    }

    /** @return array{surveyors:int, active:int, issued:int, used:int, left:int, reports:int, submitted:int} */
    public function stats(Account $company): array
    {
        $issued = $this->beacons($company)->count();
        $used = $this->beacons($company)->where('used', 1)->count();

        return [
            'surveyors' => $this->surveyors($company)->count(),
            'active' => $this->surveyors($company)->where('status', Account::ACTIVE)->count(),
            'issued' => $issued,
            'used' => $used,
            'left' => $issued - $used,
            'reports' => $this->reports($company)->count(),
            'submitted' => $this->reports($company)->whereNotNull('submitted_at')->count(),
        ];
    }
}

