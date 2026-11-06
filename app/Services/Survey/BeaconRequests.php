<?php

namespace App\Services\Survey;

use App\Http\Controllers\Survey\BeaconRegisterController;
use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Survey\SurveyBeaconRegister;
use App\Models\Survey\SurveyBeaconRequest;
use App\Models\Survey\SurveyorPortalAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A beacon request's life (CR 03112026 items 19-21 + the Cadastral I to S): submit, the SG reviews and
 * issues the next numbers or rejects, the requester accepts. Issuing goes through the Beacon Register's
 * own save, so a request gets the same pool, checks and duplicate guard as the Issue window.
 */
class BeaconRequests
{
    public const MAX_QUANTITY = 1000;

    public function __construct(private SurveyorPortalNotifier $notify, private ItsBeacons $itsBeacons)
    {
    }

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    private function create(array $values): SurveyBeaconRequest
    {
        // Two requests at the same moment can take one number; the unique index refuses the second.
        for ($try = 1; ; $try++) {
            try {
                return SurveyBeaconRequest::create($values + ['request_no' => SurveyBeaconRequest::nextNumber(), 'status' => SurveyBeaconRequest::SUBMITTED]);
            } catch (QueryException $e) {
                if ($try >= 3 || ! str_contains($e->getMessage(), 'ux_survey_beacon_requests_no')) throw $e;
            }
        }
    }

    /** From the portal. @param array{layout_type:string, quantity:int, job_name:?string, plot_no:?string, district:?string, lga:?string, latitude:?float, longitude:?float, remarks:?string} $data */
    public function fromPortal(SurveyorPortalAccount $account, array $data): SurveyBeaconRequest
    {
        $request = $this->db()->transaction(function () use ($account, $data) {
            return $this->create($data + [
                'source' => SurveyBeaconRequest::SOURCE_PORTAL,
                'surveyor_portal_account_id' => $account->id,
                'company_id' => $account->company_id,
                'company_name' => $account->company_name,
                'surveyor_name' => $account->name,
                'surveyor_phone' => $account->phone,
                'location' => $this->location($data['district'] ?? null, $data['lga'] ?? null),
            ]);
        });
        $this->notify->requestSubmitted($request);

        return $request;
    }

    /** From a Cadastral I to S: the job's surveyor, firm and location. Cadastral's I to S are private. */
    public function fromCadastralJob(CadastralSurveyJob $job, int $quantity, ?string $remarks): SurveyBeaconRequest
    {
        if (! $job->surveyor_name) {
            throw ValidationException::withMessages(['quantity' => 'Assign a surveyor to the job first.']);
        }
        $company = $job->firm_name
            ? $this->db()->table('survey_companies')->whereRaw('UPPER(name) = ?', [mb_strtoupper(trim($job->firm_name))])->first(['id', 'name'])
            : null;

        $request = $this->db()->transaction(fn () => $this->create([
            'source' => SurveyBeaconRequest::SOURCE_CADASTRAL,
            'cadastral_survey_job_id' => $job->id,
            'layout_type' => 'Private',
            'company_id' => $company->id ?? null,
            'company_name' => $company->name ?? ($job->firm_name ?: null),
            'surveyor_name' => $job->surveyor_name,
            'surveyor_phone' => $job->surveyor?->phone,
            'job_name' => $job->its_number ?: $job->job_number,
            'quantity' => $quantity,
            'plot_no' => $job->prop_plot,
            'district' => $job->prop_district,
            'lga' => $job->prop_lga,
            'location' => $this->location($job->prop_district, $job->prop_lga),
            'remarks' => $remarks,
        ]));
        $this->notify->requestSubmitted($request);

        return $request;
    }

    /**
     * The SG issues the numbers. Public takes the next numbers from the pool; Private is A + serial +
     * letters, with the letters and start serial from the SG's window.
     *
     * @param array{letters?:?string, start?:?int, company_choice?:?string, company_other?:?string, issue_date?:?string} $input
     */
    public function issue(SurveyBeaconRequest $request, array $input): SurveyBeaconRequest
    {
        $request = $this->db()->transaction(function () use ($request, $input) {
            $q = SurveyBeaconRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($q->status !== SurveyBeaconRequest::SUBMITTED) {
                throw ValidationException::withMessages(['issue' => "{$q->request_no} is already {$q->status}."]);
            }

            $entries = app(BeaconRegisterController::class)->issueGroups([$this->group($q, $input)], $input['issue_date'] ?? now()->toDateString());
            SurveyBeaconRegister::whereIn('id', array_map(fn ($e) => $e->id, $entries))->update(['survey_beacon_request_id' => $q->id]);

            $numbers = [];
            foreach ($entries as $e) {
                foreach ($e->numbers()->get(['beacon_no', 'beacon_series', 'beacon_seq']) as $n) {
                    $numbers[] = ['no' => $n->beacon_no, 'series' => $n->beacon_series, 'seq' => (int) $n->beacon_seq];
                }
            }
            if ($q->cadastral_survey_job_id) {
                $this->itsBeacons->attach($numbers, 'cadastral_survey_job_id', (int) $q->cadastral_survey_job_id);
            }

            $q->update([
                'status' => SurveyBeaconRequest::ISSUED,
                'issued_numbers' => mb_substr(implode(', ', array_map(fn ($e) => $e->issued_range, $entries)), 0, 500),
                'company_id' => $entries[0]->company_id ?? $q->company_id,
                'company_name' => $entries[0]->company_name ?? $q->company_name,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return $q;
        });
        Log::info('Survey beacon request issued', ['request' => $request->request_no, 'numbers' => $request->issued_numbers, 'by' => Auth::id()]);
        $this->notify->requestIssued($request);

        return $request;
    }

    public function reject(SurveyBeaconRequest $request, string $note): SurveyBeaconRequest
    {
        $request = $this->db()->transaction(function () use ($request, $note) {
            $q = SurveyBeaconRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($q->status !== SurveyBeaconRequest::SUBMITTED) {
                throw ValidationException::withMessages(['issue' => "{$q->request_no} is already {$q->status}."]);
            }
            $q->update(['status' => SurveyBeaconRequest::REJECTED, 'review_note' => $note, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

            return $q;
        });
        $this->notify->requestRejected($request);

        return $request;
    }

    public function accept(SurveyBeaconRequest $request): SurveyBeaconRequest
    {
        if ($request->status !== SurveyBeaconRequest::ISSUED) {
            throw ValidationException::withMessages(['accept' => "{$request->request_no} is {$request->status}; only issued beacons can be accepted."]);
        }
        $request->update(['status' => SurveyBeaconRequest::ACCEPTED, 'accepted_at' => now()]);
        $this->notify->requestAccepted($request);

        return $request;
    }

    /** The request as one Issue group. The surveyor is the linked directory entry when there is one. */
    private function group(SurveyBeaconRequest $q, array $input): array
    {
        $choice = 'other';
        if ($q->account?->cadastral_surveyor_id) {
            $choice = 'surcon:' . $q->account->cadastral_surveyor_id;
        } elseif ($q->account?->surveyors_list_id) {
            $choice = 'staff:' . $q->account->surveyors_list_id;
        } elseif ($q->cadastral_survey_job_id && ($id = CadastralSurveyJob::whereKey($q->cadastral_survey_job_id)->value('cadastral_surveyor_id'))) {
            $choice = 'surcon:' . $id;
        }

        $group = [
            'layout_type'         => $q->layout_type,
            'surveyor_choice'     => $choice,
            'surveyor_name_other' => $choice === 'other' ? $q->surveyor_name : null,
            'prop_plot'           => $q->plot_no,
            'prop_district'       => $q->district ? ['Other'] : [],
            'prop_district_other' => $q->district,
            'prop_lga'            => $q->lga ? [$q->lga] : [],
            'prop_state'          => $q->district || $q->lga ? 'Kano' : null,
        ];

        if ($q->layout_type === 'Government') {
            return $group + ['count' => $q->quantity];
        }

        $letters = strtoupper(trim((string) ($input['letters'] ?? '')));
        $start = (int) ($input['start'] ?? 0);
        if (! preg_match('/^[A-Z]{1,3}$/', $letters)) {
            throw ValidationException::withMessages(['letters' => 'Enter the letters, one to three.']);
        }
        if ($start < 1 || $start + $q->quantity - 1 > BeaconPool::MAX_SEQ) {
            throw ValidationException::withMessages(['start' => 'Enter a start serial that fits ' . $q->quantity . ' beacons up to 9999.']);
        }

        $number = fn (int $seq) => BeaconRegisterController::PRIVATE_PREFIX . str_pad((string) $seq, 4, '0', STR_PAD_LEFT) . $letters;
        $company = trim((string) ($input['company_choice'] ?? ''));
        if ($company === '' && $q->company_id) $company = (string) $q->company_id;
        $other = trim((string) ($input['company_other'] ?? ''));
        if ($company === '' && $q->company_name) { $company = 'other'; $other = $q->company_name; }

        return $group + [
            'beacon_from'    => $number($start),
            'beacon_to'      => $q->quantity > 1 ? $number($start + $q->quantity - 1) : null,
            'company_choice' => $company,
            'company_other'  => $other,
        ];
    }

    private function location(?string $district, ?string $lga): ?string
    {
        // Location is district + LGA (+ state); the plot number never goes in it.
        return implode(', ', array_filter([$district, $lga])) ?: null;
    }
}
