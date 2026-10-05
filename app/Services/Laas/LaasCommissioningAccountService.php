<?php

namespace App\Services\Laas;

use App\Models\LandRecommendation;
use App\Models\Laas\LaasApplicant;
use App\Models\Laas\LaasApplication;
use App\Models\Laas\LaasApplicationEvent;
use App\Services\BulkSmsNgService;
use App\Services\MlsCommissioningOssApplicationService;
use App\Services\Sms\ApplicantPhoneResolver;
use App\Support\HolderName;
use App\Support\Laas\SroFormSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Opens (or reuses) a LAAS Portal account for the applicant of every file
 * commissioned in Land or OSS, and puts one portal application per file on it.
 *
 * The portal application form IS the OSS / Land application form — the same
 * Statutory Right of Occupancy answers, keyed by oss_applications column name
 * (see SroFormSchema) — so a commissioned file is simply an application that
 * skipped the portal's own intake: it starts at "File Number assigned", and the
 * Land 12 / Recommendation / RoFO hooks in LaasWorkflowService move it on from
 * there exactly as they do for a portal-filed one.
 *
 * WHO IS "THE SAME APPLICANT"
 * An account is reused when the commissioning email matches one, or when the
 * phone AND the holder name both match. Phone alone is not enough: one number
 * in file_indexings sits on 1,211 different files (clerks, agents), and an
 * account keyed on it would show strangers each other's folios — passports
 * included. A batch of five files for one person still lands on one account,
 * because all five carry the same name and number.
 *
 * NEVER THROWS. Called after the commissioning transaction has committed; a
 * portal problem must never turn a successful commissioning into an error.
 */
class LaasCommissioningAccountService
{
    public function __construct(
        private BulkSmsNgService $sms,
        private LaasNotificationService $notifications,
        private ApplicantPhoneResolver $phones,
    ) {
    }

    /**
     * @param  array<int,string>  $fileNumbers  Freshly commissioned (or, for a
     *                                          backfill, already commissioned) files.
     * @param  array{send_sms?:bool, phone_confirmed?:bool, require_phone?:bool}  $options
     *         send_sms        false for backfills and tests: accounts are made,
     *                         nobody is texted, and the temporary passwords come
     *                         back in the report for the office to hand over.
     *         phone_confirmed the officer cleared the shared-number warning.
     *         require_phone   false lets a file with no usable phone still get an
     *                         account (backfill); the default skips it, since the
     *                         applicant could never be told how to sign in.
     * @return array<int,array<string,mixed>>  One entry per account touched.
     */
    public function provisionForFiles(array $fileNumbers, array $options = []): array
    {
        if (!config('laas.commissioning_accounts.enabled', true)) {
            return [];
        }

        $sendSms = ($options['send_sms'] ?? true) && config('laas.commissioning_accounts.sms', true);
        $requirePhone = $options['require_phone'] ?? true;

        try {
            $rows = $this->commissioningRows($fileNumbers);
        } catch (\Throwable $e) {
            Log::error('LAAS commissioning accounts: could not read commissioned files', [
                'files' => $fileNumbers,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        // Group the files by the account they belong to, so a batch for one
        // applicant produces one account and one SMS rather than five.
        $groups = [];
        foreach ($rows as $row) {
            $phone = LaasApplicant::normalizePhone((string) ($row->phone_no ?? ''));
            $email = $this->cleanEmail($row->email ?? null);
            $name = trim((string) ($row->file_name ?? ''));

            if ($phone === null && $requirePhone) {
                Log::info('LAAS commissioning accounts: skipped, no usable phone', [
                    'file_number' => $row->full_file_number,
                ]);
                continue;
            }

            // Without a phone the holder name alone groups the files — but only
            // within this one call (one commissioning, one batch), which an
            // officer raised for one applicant. Across calls such files never
            // match an existing account; see findOrCreateApplicant().
            $key = $email ?: (($phone ?? 'nophone') . '|' . HolderName::normalise($name));
            $groups[$key] ??= ['phone' => $phone, 'email' => $email, 'name' => $name, 'rows' => []];
            $groups[$key]['rows'][] = $row;
        }

        $report = [];
        foreach ($groups as $group) {
            $report[] = $this->provisionGroup($group, $sendSms, (bool) ($options['phone_confirmed'] ?? false));
        }

        return $report;
    }

    /** @param array{phone:?string,email:?string,name:string,rows:array<int,object>} $group */
    private function provisionGroup(array $group, bool $sendSms, bool $phoneConfirmed): array
    {
        $fileNumbers = array_map(fn ($r) => (string) $r->full_file_number, $group['rows']);

        try {
            [$applicant, $tempPassword, $created, $applications] = DB::connection('sqlsrv')->transaction(
                function () use ($group) {
                    [$applicant, $tempPassword, $created] = $this->findOrCreateApplicant($group);

                    $applications = [];
                    foreach ($group['rows'] as $row) {
                        $application = $this->applicationFor($applicant, $row);
                        if ($application) {
                            $applications[] = $application;
                        }
                    }

                    return [$applicant, $tempPassword, $created, $applications];
                }
            );
        } catch (\Throwable $e) {
            Log::error('LAAS commissioning accounts: provisioning failed', [
                'files' => $fileNumbers,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'files' => $fileNumbers, 'error' => $e->getMessage()];
        }

        $newApplications = array_values(array_filter($applications, fn ($a) => $a->wasRecentlyCreated));

        $result = [
            'status'           => $created ? 'account_created' : 'account_reused',
            'applicant_id'     => $applicant->id,
            'username'         => $applicant->username,
            'email'            => $applicant->email,
            'phone'            => $applicant->phone,
            'temp_password'    => $tempPassword,
            'files'            => $fileNumbers,
            'new_applications' => array_map(fn ($a) => $a->reference_no, $newApplications),
            'sms'              => 'not_sent',
        ];

        if (!$sendSms || (!$created && !$newApplications)) {
            return $result;
        }

        if ($applicant->phone && !$phoneConfirmed && $this->phones->describe($applicant->phone)['shared']) {
            // Same rule the commissioning SMS keeps: a number on file against many
            // other files is an agent's or a clerk's, and the officer did not
            // confirm it. The account stands; the office hands the login over.
            $result['sms'] = 'skipped_shared_number';
            $this->stampSms($newApplications, null, LaasApplicationEvent::SMS_SKIPPED, $applicant->phone);

            return $result;
        }

        // After the response, the way the commissioning SMS goes: the gateway is
        // slow and must never hold up (or fail) the commissioning screen.
        $messages = $this->loginMessages($applicant, $tempPassword, $fileNumbers, $created);
        $events = $newApplications;
        app()->terminating(function () use ($applicant, $messages, $events) {
            $this->deliver($applicant, $messages, $events);
        });
        $result['sms'] = 'queued';

        return $result;
    }

    /**
     * @return array{0:LaasApplicant,1:?string,2:bool} applicant, temporary
     *         password (only for a new account), whether it was created.
     */
    private function findOrCreateApplicant(array $group): array
    {
        $phone = $group['phone'];
        $email = $group['email'];
        $nameKey = HolderName::normalise($group['name']);

        if ($email) {
            $byEmail = LaasApplicant::where('email', $email)->first();
            if ($byEmail) {
                return [$byEmail, null, false];
            }
        }

        if ($phone && $nameKey !== '') {
            $byPhone = LaasApplicant::where('phone', $phone)->get()
                ->first(fn ($a) => HolderName::normalise((string) $a->name) === $nameKey);
            if ($byPhone) {
                return [$byPhone, null, false];
            }
        }

        $username = $this->uniqueUsername($group['name']);
        $tempPassword = $this->temporaryPassword();

        $applicant = LaasApplicant::create([
            'name'                 => $group['name'] !== '' ? mb_strtoupper($group['name']) : 'APPLICANT',
            'username'             => $username,
            'email'                => $email ?: $username . '@' . config('laas.commissioning_accounts.placeholder_email_domain', 'portal.klaes.local'),
            'phone'                => $phone ?? '',
            'password'             => Hash::make($tempPassword),
            'must_change_password' => true,
            'status'               => 'active',
            'account_origin'       => LaasApplicant::ORIGIN_COMMISSIONING,
        ]);

        return [$applicant, $tempPassword, true];
    }

    /** One portal application per commissioned file; an existing one is reused. */
    private function applicationFor(LaasApplicant $applicant, object $row): ?LaasApplication
    {
        $fileNumber = (string) $row->full_file_number;

        $existing = LaasApplication::where('file_number', $fileNumber)->orderByDesc('id')->first();
        if ($existing) {
            // Already on a portal account (a portal-filed application, or an
            // earlier run). Never move a file between two people's accounts here.
            return $existing->laas_applicant_id === $applicant->id ? $existing : null;
        }

        $landType = app(MlsCommissioningOssApplicationService::class)
            ->resolveApplicationType($row->land_use ?? null, $fileNumber);
        $progress = $this->registryProgress($fileNumber);
        $commissionedAt = $row->commissioning_date ?? $row->created_at ?? now();

        $application = LaasApplication::create([
            'reference_no'           => LaasApplication::nextReference(),
            'laas_applicant_id'      => $applicant->id,
            'applicant_name'         => $row->file_name ?: $applicant->name,
            'applicant_phone'        => $applicant->phone ?: null,
            'applicant_email'        => $applicant->email,
            'applicant_type'         => $row->customer_type ?? null,
            'land_use'               => $row->land_use ?? null,
            'purpose_id'             => $row->purpose_id ?? null,
            'location'               => $row->location ?? null,
            'plot_no'                => $row->plot_no ?? null,
            'stage'                  => $progress['stage'],
            'origin'                 => LaasApplication::ORIGIN_COMMISSIONING,
            'land_type'              => $landType,
            'form_data'              => $this->formData($row, $landType),
            'file_number'            => $fileNumber,
            'mls_file_no_id'         => $row->id,
            'survey_report_request_id' => $progress['survey_report_request_id'],
            'land_recommendation_id' => $progress['land_recommendation_id'],
            'rofo_id'                => $progress['rofo_id'],
            'fileno_assigned_at'     => $commissionedAt,
            'submitted_at'           => $commissionedAt,
            'completed_at'           => $progress['stage'] === LaasApplication::STAGE_ROFO_SIGNED ? now() : null,
        ]);

        $registry = strtoupper((string) ($row->system_sub_type ?? '')) === 'OSS' ? 'OSS' : 'Land';

        $this->notifications->record($application, LaasApplication::STAGE_FILENO_ASSIGNED, [
            'title' => 'File commissioned',
            'body'  => "File Number {$fileNumber} was commissioned in {$registry} File Commissioning"
                     . ' and added to your portal account.',
            'sms'   => false,
        ]);

        if ($progress['stage'] !== LaasApplication::STAGE_FILENO_ASSIGNED) {
            $this->notifications->record($application, $progress['stage'], [
                'title' => LaasApplication::label($progress['stage']),
                'body'  => 'Progress already recorded on this file before it was added to the portal.',
                'sms'   => false,
            ]);
        }

        return $application;
    }

    /**
     * Where the file already stands in the registry. Needed for files that had
     * moved on before they reached the portal (backfill, re-runs): the stage
     * hooks only fire forward from the moment the application exists.
     *
     * @return array{stage:string,survey_report_request_id:?int,land_recommendation_id:?int,rofo_id:?int}
     */
    public function registryProgress(string $fileNumber): array
    {
        $db = DB::connection('sqlsrv');
        $stage = LaasApplication::STAGE_FILENO_ASSIGNED;
        $surveyId = $recommendationId = $rofoId = null;

        $land12 = $db->table('survey_report_requests')
            ->where('file_number', $fileNumber)
            ->orderByDesc('id')
            ->first(['id', 'status']);
        if ($land12) {
            $surveyId = (int) $land12->id;
            $stage = match ((string) $land12->status) {
                'Completed'         => LaasApplication::STAGE_RECOMMENDATION_PENDING,
                'Sent to Cadastral' => LaasApplication::STAGE_AT_CADASTRAL,
                default             => LaasApplication::STAGE_LAND12_RAISED,
            };
        }

        $recommendation = $db->table('land_recommendations')
            ->where('file_number', $fileNumber)
            ->orderByDesc('id')
            ->first(['id', 'status', 'rofo_status', 'rofo_print_count']);
        if ($recommendation) {
            $recommendationId = (int) $recommendation->id;
            $candidate = LaasApplication::STAGE_RECOMMENDATION_PENDING;

            if ((string) $recommendation->status === LandRecommendation::STATUS_APPROVED) {
                $candidate = LaasApplication::STAGE_RECOMMENDATION_APPROVED;
            }
            if ((string) $recommendation->rofo_status === LandRecommendation::ROFO_GENERATED) {
                $candidate = LaasApplication::STAGE_ROFO_GENERATED;
                $rofoId = $recommendationId;
            }
            if ((int) ($recommendation->rofo_print_count ?? 0) > 0) {
                $candidate = LaasApplication::STAGE_ROFO_SIGNED;
                $rofoId = $recommendationId;
            }

            if (LaasApplication::rank($candidate) > LaasApplication::rank($stage)) {
                $stage = $candidate;
            }
        }

        return [
            'stage'                    => $stage,
            'survey_report_request_id' => $surveyId,
            'land_recommendation_id'   => $recommendationId,
            'rofo_id'                  => $rofoId,
        ];
    }

    /**
     * The application answers, from the OSS application row when the file has
     * one (the same form, same keys) and from the commissioning row otherwise.
     */
    private function formData(object $row, string $landType): array
    {
        $keys = array_flip(SroFormSchema::fieldKeys($landType));
        $data = [];

        try {
            $oss = DB::connection('sqlsrv')->table('oss_applications')
                ->where('file_no', $row->full_file_number)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderByDesc('id')
                ->first();

            foreach ((array) ($oss ?? []) as $column => $value) {
                if (isset($keys[$column]) && $value !== null && trim((string) $value) !== '') {
                    $data[$column] = $value;
                }
            }
        } catch (\Throwable $e) {
            // The commissioning row below still gives the essentials.
        }

        $fallback = [
            'applicant_name' => $row->file_name ?? null,
            'phone'          => $row->phone_no ?? null,
            'email'          => $this->cleanEmail($row->email ?? null),
            'plot_no'        => $row->plot_no ?? null,
            'plan_no'        => $row->tp_no ?? null,
            'location'       => $row->location ?? null,
            'district'       => $row->district ?? null,
            'lga'            => $row->lga ?? null,
            'land_use'       => $row->land_use ?? null,
        ];
        foreach ($fallback as $key => $value) {
            if (isset($keys[$key]) && empty($data[$key]) && $value !== null && trim((string) $value) !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /** Send the login text, then record what happened on each new application. */
    private function deliver(LaasApplicant $applicant, array $messages, array $applications): void
    {
        $delivered = null;

        try {
            $delivered = $this->sms->sendFirstAccepted((string) $applicant->phone, $messages);
        } catch (\Throwable $e) {
            Log::error('LAAS commissioning accounts: login SMS threw', [
                'applicant_id' => $applicant->id,
                'error'        => $e->getMessage(),
            ]);
        }

        $this->stampSms(
            $applications,
            $delivered,
            $delivered ? LaasApplicationEvent::SMS_SENT : LaasApplicationEvent::SMS_FAILED,
            (string) $applicant->phone
        );
    }

    /**
     * A timeline entry for the login text on each new application. The body
     * never repeats the password — the timeline is visible to anyone who later
     * signs in, and the password is meant to be changed.
     */
    private function stampSms(array $applications, ?string $delivered, string $status, ?string $phone): void
    {
        foreach ($applications as $application) {
            try {
                LaasApplicationEvent::create([
                    'laas_application_id'  => $application->id,
                    'stage'                => $application->stage,
                    'title'                => 'Portal sign-in details sent',
                    'body'                 => 'Your username and a temporary password were sent by SMS.',
                    'actor_type'           => 'system',
                    'visible_to_applicant' => true,
                    'sms_to'               => $phone,
                    'sms_body'             => $delivered ? '[sign-in details]' : null,
                    'sms_status'           => $status,
                    'sms_sent_at'          => $status === LaasApplicationEvent::SMS_SENT ? now() : null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('LAAS commissioning accounts: could not stamp SMS event', [
                    'reference_no' => $application->reference_no,
                    'error'        => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Best wording first, then a plainer twin for the gateway's content filter.
     * Avoids "approved", "assigned", "notice" — see LaasNotificationService.
     *
     * @return array<int,string>
     */
    private function loginMessages(LaasApplicant $applicant, ?string $tempPassword, array $fileNumbers, bool $created): array
    {
        $url = rtrim((string) config('app.url'), '/') . '/laas/login';
        $files = count($fileNumbers) === 1
            ? 'file ' . $fileNumbers[0] . ' is'
            : count($fileNumbers) . ' files are';

        if ($created) {
            return [
                "KLAES Lands Portal: your {$files} now on the portal. Sign in at {$url} "
                . "Username: {$applicant->username} Password: {$tempPassword} "
                . 'You will choose a new password at first sign-in.',
                "KLAES Lands portal sign-in. Username {$applicant->username} Password {$tempPassword} Site {$url}",
            ];
        }

        return [
            "KLAES Lands Portal: your {$files} now on your portal account ({$applicant->username}). Sign in at {$url}",
            "KLAES Lands portal: new file on account {$applicant->username}. Site {$url}",
        ];
    }

    /**
     * aminu.bello27 — the first two words of the name, then digits until free.
     * Honorifics are dropped so "ALH. AMINU BELLO" does not become alh.aminu.
     */
    public function uniqueUsername(string $name): string
    {
        // HolderName::normalise() squeezes the spaces out, so it cannot split a
        // name into words; the honorifics are dropped here word by word instead.
        $honorifics = ['alhaji', 'alh', 'hajiya', 'haj', 'mallam', 'mal', 'dr', 'mr', 'mrs', 'miss', 'chief', 'hon'];
        $words = preg_split('/[\s,]+/', Str::ascii(mb_strtolower(trim($name)))) ?: [];
        $words = array_values(array_filter(
            array_map(fn ($w) => preg_replace('/[^a-z0-9]/', '', $w), $words),
            fn ($w) => $w !== '' && !in_array($w, $honorifics, true)
        ));

        $base = implode('.', array_slice($words, 0, 2));
        $base = substr($base !== '' ? $base : 'applicant', 0, 40);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = $base . random_int($attempt < 20 ? 10 : 100, $attempt < 20 ? 99 : 9999);
            if (!LaasApplicant::where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $base . Str::lower(Str::random(6));
    }

    /** Readable, no look-alike characters: Kx7m-4821. */
    private function temporaryPassword(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
        $word = '';
        for ($i = 0; $i < 4; $i++) {
            $word .= $letters[random_int(0, strlen($letters) - 1)];
        }

        return $word . '-' . random_int(1000, 9999);
    }

    private function cleanEmail($email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** @return array<int,object> latest live commissioning row per file number */
    private function commissioningRows(array $fileNumbers): array
    {
        $fileNumbers = array_values(array_unique(array_filter(array_map('trim', $fileNumbers))));
        if (!$fileNumbers) {
            return [];
        }

        $rows = [];
        foreach (array_chunk($fileNumbers, 500) as $chunk) {
            $found = DB::connection('sqlsrv')->table('mls_file_no')
                ->whereIn('full_file_number', $chunk)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->orderByDesc('id')
                ->get();

            foreach ($found as $row) {
                $rows[strtoupper($row->full_file_number)] ??= $row;
            }
        }

        // Keep the caller's order, so a batch reads 1..n on the dashboard.
        $ordered = [];
        foreach ($fileNumbers as $fileNumber) {
            if (isset($rows[strtoupper($fileNumber)])) {
                $ordered[] = $rows[strtoupper($fileNumber)];
            }
        }

        return $ordered;
    }
}
