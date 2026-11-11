<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyorPortalAccount;
use App\Models\Survey\SurveyorPortalNotification;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Models\User;
use App\Services\UserNotificationService;
use App\Support\AddressBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Survey Plan Upload (Survey Update 5g-5i, user 2026-11-10), shared by the KLAES page and the MLPP-SG
 * portal: the short form's rules, the save, and the notices: the unit chosen (GKN or
 * Examination/Verification), the SG and DSG and the Super Admins on the staff bell; the uploader gets a confirmation.
 */
class SurveyPlanUploads
{
    public const OTHER = 'other';

    public function __construct(private UserNotificationService $bell)
    {
    }

    /** The short form's fields, validated. $staff adds the surveyor pick for "Drawn by". */
    public function validated(Request $r, bool $staff): array
    {
        $districts = \App\Http\Controllers\Survey\LookupController::options('districts');
        $d = $r->validate([
            'unit'           => ['required', Rule::in(array_keys(Plan::UNITS))],
            'plan_type'      => ['required', Rule::in(Plan::TYPES)],
            'plot_no'        => 'nullable|string|max:100',
            'district'       => ['required', 'string', 'max:150', Rule::in(array_merge($districts, [self::OTHER]))],
            'district_other' => ['nullable', 'string', 'max:150', Rule::requiredIf(fn () => $r->input('district') === self::OTHER)],
            'lga'            => ['required', Rule::in(CompensationListReader::LGAS)],
            'drawn_by'       => ['required', 'string', 'max:255'],
            'drawn_by_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $staff && $r->input('drawn_by') === self::OTHER)],
            'remarks'        => 'nullable|string|max:1000',
            'plan_file'      => ['required', 'file', 'max:25600', 'mimes:' . implode(',', Plan::FILE_TYPES), function ($attr, $file, $fail) {
                // mimes reads the contents; a wrong name is refused too.
                if (! in_array(strtolower($file->getClientOriginalExtension()), Plan::FILE_TYPES, true)) {
                    $fail('Upload the plan as a PDF, JPG, PNG or TIFF.');
                }
            }],
        ], [
            'unit.required'           => 'Choose the unit the plan is going to.',
            'plan_type.required'      => 'Choose the type: Final Survey Plan or Application Plan.',
            'district.required'       => 'Choose the district.',
            'district.in'             => 'Choose a district from the list, or Other.',
            'district_other.required' => 'Specify the district.',
            'lga.required'            => 'Choose the LGA.',
            'drawn_by.required'       => 'Say who drew the plan.',
            'drawn_by_other.required' => "Type the name of who drew the plan.",
            'plan_file.required'      => 'Upload the survey plan.',
            'plan_file.mimes'         => 'Upload the plan as a PDF, JPG, PNG or TIFF.',
            'plan_file.max'           => 'The plan can be at most 25 MB.',
        ]);

        $one = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) === '' ? null : $v;
        $district = $d['district'] === self::OTHER ? $one($d['district_other'] ?? null) : $d['district'];
        // A listed district typed under Other becomes that district.
        if ($d['district'] === self::OTHER && ($hit = collect($districts)->first(fn ($x) => mb_strtolower($x) === mb_strtolower((string) $district)))) {
            $district = $hit;
        }

        $surveyorId = null;
        $drawnBy = $one($d['drawn_by']);
        if ($staff) {
            if ($d['drawn_by'] === self::OTHER) {
                $drawnBy = $one($d['drawn_by_other'] ?? null);
            } elseif (ctype_digit((string) $d['drawn_by']) && ($s = SurveyorListEntry::find((int) $d['drawn_by']))) {
                [$surveyorId, $drawnBy] = [$s->id, $s->full_name];
            } else {
                throw ValidationException::withMessages(['drawn_by' => 'Choose who drew the plan, or Other.']);
            }
        }

        return [
            'unit'              => $d['unit'],
            'plan_type'         => $d['plan_type'],
            // An identifier: spacing tidied, case kept.
            'plot_no'           => $one($d['plot_no'] ?? null),
            'district'          => $district,
            'lga'               => $d['lga'],
            'location'          => AddressBuilder::propertyLocation(['district' => $district, 'lga' => $d['lga'], 'state' => 'Kano']),
            'surveyors_list_id' => $surveyorId,
            'drawn_by'          => $drawnBy,
            'remarks'           => \App\Services\CadastralDept\Normalise::text($d['remarks'] ?? null) ?: null,
        ];
    }

    /**
     * Save the plan and tell the unit. From the portal, $account says whose it is; saved quietly there,
     * because SurveyModel would stamp the portal account id into created_by, which holds staff ids.
     */
    public function store(array $fields, UploadedFile $file, ?User $staff, ?SurveyorPortalAccount $account = null): Plan
    {
        $stored = null;
        try {
            $plan = DB::connection('sqlsrv')->transaction(function () use ($fields, $file, $staff, $account, &$stored) {
                $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'pdf'));
                $stored = Storage::disk(Plan::DISK)->putFileAs('app/survey/survey-plans/' . now()->format('Y/m'), $file, Str::uuid() . '.' . $ext);
                if (! $stored) {
                    throw new \RuntimeException('The plan could not be stored.');
                }
                for ($try = 1; ; $try++) {
                    try {
                        $plan = new Plan($fields + [
                            'upload_ref'                 => Plan::nextRef('upload_ref', 'SPU', 4),
                            'file_path'                  => $stored,
                            'file_name'                  => mb_substr($file->getClientOriginalName(), 0, 255),
                            'file_mime'                  => mb_substr((string) $file->getMimeType(), 0, 120),
                            'file_size'                  => $file->getSize(),
                            'source'                     => $account ? 'portal' : 'klaes',
                            'surveyor_portal_account_id' => $account?->id,
                            'uploaded_by_name'           => $account ? $account->name : $staff?->name,
                            'created_by'                 => $staff?->id,
                            'updated_by'                 => $staff?->id,
                        ]);
                        $plan->saveQuietly();
                        break;
                    } catch (QueryException $e) {
                        // Two uploads at the same moment can take one ref; the unique index refuses the second.
                        if ($try >= 3 || ! str_contains($e->getMessage(), 'upload_ref')) throw $e;
                    }
                }
                $this->notifyUnit($plan, $staff?->id);

                return $plan;
            });
        } catch (\Throwable $e) {
            if ($stored) Storage::disk(Plan::DISK)->delete($stored);
            throw $e;
        }
        Log::info('Survey plan uploaded', ['ref' => $plan->upload_ref, 'unit' => $plan->unit, 'source' => $plan->source]);

        return $plan;
    }

    /** The unit's role holders (Survey - GKN or Survey - Examination/Verification). */
    public function recipients(string $unit)
    {
        $role = Plan::UNIT_ROLES[$unit] ?? null;
        if (! $role) return collect();

        return User::query()
            ->whereRaw("',' + REPLACE(ISNULL(assign_role, ''), ', ', ',') + ',' LIKE ?", ['%,' . $role . ',%'])
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->get(['id']);
    }

    /** Survey heads told of every upload (user, 2026-11-11), whichever unit it goes to. */
    public const HEAD_ROLES = [SurveyorPortalNotifier::SG_ROLE, 'Survey - DSG'];

    /** Holders of any of $roles, active. */
    private function roleHolders(array $roles)
    {
        return User::query()
            ->where(function ($q) use ($roles) {
                foreach ($roles as $role) {
                    $q->orWhereRaw("',' + REPLACE(ISNULL(assign_role, ''), ', ', ',') + ',' LIKE ?", ['%,' . $role . ',%']);
                }
            })
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->get(['id']);
    }

    /** Super Admins, as User::isSuperAdmin() reads them (type or assigned role). */
    private function superAdmins()
    {
        return User::query()
            ->where(function ($q) {
                $q->whereRaw("LOWER(ISNULL(type, '')) IN ('super admin', 'supper admin')");
                foreach (['Super Admin', 'Supper Admin'] as $role) {
                    $q->orWhereRaw("',' + REPLACE(ISNULL(assign_role, ''), ', ', ',') + ',' LIKE ?", ['%,' . $role . ',%']);
                }
            })
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->get(['id']);
    }

    /**
     * Bell notices once the upload has committed (user, 2026-11-11): everyone in the unit chosen, the Surveyor-General
     * and DSG, and the Super Admins, once each; the uploader gets a confirmation instead (a portal uploader on the
     * portal bell).
     */
    private function notifyUnit(Plan $plan, ?int $uploaderId): void
    {
        DB::connection('sqlsrv')->afterCommit(function () use ($plan, $uploaderId) {
            try {
                $route = $plan->unit === Plan::UNIT_GKN ? 'survey-module.gkn.uploaded-plans' : 'survey-module.workflow.uploaded-plans';
                $unitPath = parse_url(route($route), PHP_URL_PATH);
                $allPath = parse_url(route('survey-module.survey-plans.index'), PHP_URL_PATH);
                $who = $plan->source === 'portal' ? ($plan->uploaded_by_name . ' (MLPP-SG Portal)') : (string) $plan->uploaded_by_name;
                $where = trim(($plan->plot_no ? 'Plot ' . $plan->plot_no . ', ' : '') . (string) $plan->location, ', ');
                $title = "{$plan->plan_type} uploaded: {$plan->upload_ref}";
                $body = trim("{$who} sent a {$plan->plan_type} to {$plan->unit} for checking" . ($where !== '' ? " ({$where})" : '') . '. Drawn by ' . $plan->drawn_by . '.');

                // Unit holders open the unit's list; the heads and Super Admins open the list of every upload.
                $to = [];
                foreach ($this->recipients($plan->unit) as $u) $to[(int) $u->id] ??= $unitPath;
                foreach ($this->roleHolders(self::HEAD_ROLES) as $u) $to[(int) $u->id] ??= $allPath;
                foreach ($this->superAdmins() as $u) $to[(int) $u->id] ??= $allPath;
                if ($uploaderId) unset($to[$uploaderId]);

                foreach ($to as $id => $path) {
                    $this->bell->create($id, 'survey_plan_upload', $title, $body, ['open_path' => $path], ['module' => 'survey']);
                }

                $sent = "Your {$plan->plan_type} {$plan->upload_ref} was sent to {$plan->unit} for checking" . ($where !== '' ? " ({$where})" : '') . '.';
                if ($uploaderId) {
                    $this->bell->create($uploaderId, 'survey_plan_upload', "{$plan->plan_type} sent: {$plan->upload_ref}", $sent,
                        ['open_path' => $allPath], ['module' => 'survey']);
                } elseif ($plan->surveyor_portal_account_id) {
                    SurveyorPortalNotification::create(['surveyor_portal_account_id' => $plan->surveyor_portal_account_id,
                        'title' => "{$plan->plan_type} sent: {$plan->upload_ref}", 'body' => $sent,
                        'link' => route('surveyor-portal.survey-plans.index')]);
                }
            } catch (\Throwable $e) {
                Log::warning('Survey plan upload: the unit could not be told', ['ref' => $plan->upload_ref, 'error' => $e->getMessage()]);
            }
        });
    }
}
