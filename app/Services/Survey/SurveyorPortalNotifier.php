<?php

namespace App\Services\Survey;

use App\Models\SmsSetting;
use App\Models\Survey\SurveyBeaconRequest;
use App\Models\Survey\SurveyorPortalAccount;
use App\Models\Survey\SurveyorPortalNotification;
use App\Models\User;
use App\Services\Sms\KlaesSmsDispatcher;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Who hears what on the surveyor portal (CR 03112026 items 18-21). The SG is every active holder of
 * the Survey - SG role (user, 2026-11-05: all three), told on the staff bell and by SMS; a surveyor is
 * told in the portal and by SMS; a Cadastral officer who asked is told on the staff bell.
 *
 * Telling people must never undo the work, so everything here runs after commit and swallows failures.
 * The SMS messages ship switched off, like every message in config/klaes_sms.php.
 */
class SurveyorPortalNotifier
{
    public const SG_ROLE = 'Survey - SG';

    public function __construct(private UserNotificationService $bell, private KlaesSmsDispatcher $sms)
    {
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function surveyorGenerals()
    {
        return User::query()
            ->whereRaw("',' + REPLACE(ISNULL(assign_role, ''), ', ', ',') + ',' LIKE ?", ['%,' . self::SG_ROLE . ',%'])
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->get(['id', 'first_name', 'last_name', 'phone_number']);
    }

    private function afterCommit(callable $work): void
    {
        DB::connection('sqlsrv')->afterCommit(function () use ($work) {
            try {
                $work();
            } catch (\Throwable $e) {
                Log::warning('Surveyor portal: a notice could not be sent', ['error' => $e->getMessage()]);
            }
        });
    }

    private function toSg(string $title, string $body, string $path, ?string $smsKey = null, array $tokens = [], ?string $dedupe = null): void
    {
        foreach ($this->surveyorGenerals() as $sg) {
            $this->bell->create((int) $sg->id, 'survey_beacon_request', $title, $body, ['open_path' => $path], ['module' => 'survey']);
            if ($smsKey) {
                $this->sms->queue($smsKey, $sg->phone_number ?? null, $tokens, ['dedupe_key' => $dedupe ? "$dedupe:{$sg->id}" : null, 'phone_confirmed' => true]);
            }
        }
    }

    private function toAccount(SurveyorPortalAccount $a, string $title, string $body, ?string $link, ?string $smsKey, array $tokens, string $dedupe): void
    {
        SurveyorPortalNotification::create(['surveyor_portal_account_id' => $a->id, 'title' => $title, 'body' => $body, 'link' => $link]);
        if ($smsKey) {
            $this->sms->queue($smsKey, $a->phone, $tokens, ['dedupe_key' => $dedupe, 'phone_confirmed' => true]);
        }
    }

    public function registered(SurveyorPortalAccount $a): void
    {
        $this->afterCommit(function () use ($a) {
            $this->sms->queue(SmsSetting::KEY_SURVEY_PORTAL_REGISTERED, $a->phone, ['Name' => $a->holder_name, 'Ref' => $a->registration_id],
                ['dedupe_key' => 'kssgof-reg:' . $a->id, 'phone_confirmed' => true]);
            $path = parse_url(route('survey-module.beacon-requests.index', ['tab' => 'registrations']), PHP_URL_PATH) . '?tab=registrations';
            $this->toSg('New surveyor registration', "{$a->holder_name} ({$a->type_label}) registered on the portal and is waiting for approval. {$a->registration_id}.", $path);
        });
    }

    public function accountApproved(SurveyorPortalAccount $a): void
    {
        $this->afterCommit(fn () => $this->toAccount($a, 'Account approved', "Your account is active. Your Surveyor ID is {$a->surveyor_id_no}.", null,
            SmsSetting::KEY_SURVEY_PORTAL_ACTIVE, ['Name' => $a->holder_name, 'Ref' => $a->surveyor_id_no], 'kssgof-active:' . $a->id));
    }

    public function accountRejected(SurveyorPortalAccount $a): void
    {
        $this->afterCommit(fn () => $this->sms->queue(SmsSetting::KEY_SURVEY_PORTAL_DECLINED, $a->phone, ['Name' => $a->holder_name, 'Ref' => $a->registration_id],
            ['dedupe_key' => 'kssgof-declined:' . $a->id, 'phone_confirmed' => true]));
    }

    public function requestSubmitted(SurveyBeaconRequest $q): void
    {
        $this->afterCommit(function () use ($q) {
            $path = parse_url(route('survey-module.beacon-requests.index'), PHP_URL_PATH);
            $from = $q->company_name ?: $q->surveyor_name;
            $this->toSg("Beacon request {$q->request_no}", "{$from} asked for {$q->quantity} beacon(s) ({$q->type_label}, {$q->source_label}).", $path,
                SmsSetting::KEY_SURVEY_BEACON_REQUEST, ['Ref' => $q->request_no, 'Name' => $from, 'Qty' => (string) $q->quantity], 'brq:' . $q->id);

            if ($q->account) {
                $this->toAccount($q->account, "Request {$q->request_no} submitted", "Your request for {$q->quantity} beacon(s) has gone to the Surveyor General.",
                    route('surveyor-portal.requests.show', $q), SmsSetting::KEY_SURVEY_BEACON_RECEIVED, ['Ref' => $q->request_no], 'brq-got:' . $q->id);
            }
        });
    }

    public function requestIssued(SurveyBeaconRequest $q): void
    {
        $this->afterCommit(function () use ($q) {
            if ($q->account) {
                $this->toAccount($q->account, "Beacons ready: {$q->request_no}", "{$q->issued_numbers}. Open the request to accept them and print your slip.",
                    route('surveyor-portal.requests.show', $q), SmsSetting::KEY_SURVEY_BEACON_READY, ['Ref' => $q->request_no], 'brq-ready:' . $q->id);
            } elseif ($q->created_by) {
                $this->bell->create((int) $q->created_by, 'survey_beacon_request', "Beacons issued: {$q->request_no}", (string) $q->issued_numbers,
                    ['open_path' => $this->jobPath($q)], ['module' => 'cadastral']);
            }
        });
    }

    public function requestRejected(SurveyBeaconRequest $q): void
    {
        $this->afterCommit(function () use ($q) {
            if ($q->account) {
                $this->toAccount($q->account, "Request {$q->request_no} not approved", (string) $q->review_note,
                    route('surveyor-portal.requests.show', $q), SmsSetting::KEY_SURVEY_BEACON_DECLINED, ['Ref' => $q->request_no], 'brq-no:' . $q->id);
            } elseif ($q->created_by) {
                $this->bell->create((int) $q->created_by, 'survey_beacon_request', "Request {$q->request_no} not approved", (string) $q->review_note,
                    ['open_path' => $this->jobPath($q)], ['module' => 'cadastral']);
            }
        });
    }

    public function requestAccepted(SurveyBeaconRequest $q): void
    {
        $this->afterCommit(fn () => $this->toSg("Beacons accepted: {$q->request_no}", ($q->company_name ?: $q->surveyor_name) . ' accepted ' . $q->issued_numbers . '.',
            parse_url(route('survey-module.beacon-requests.index'), PHP_URL_PATH)));
    }

    private function jobPath(SurveyBeaconRequest $q): string
    {
        return $q->cadastral_survey_job_id
            ? (string) parse_url(route('cadastral-module.survey-jobs.edit', $q->cadastral_survey_job_id), PHP_URL_PATH)
            : '/';
    }

    /** New notices stay quiet until the tables exist. */
    public static function installed(): bool
    {
        return Schema::connection('sqlsrv')->hasTable('survey_beacon_requests');
    }
}
