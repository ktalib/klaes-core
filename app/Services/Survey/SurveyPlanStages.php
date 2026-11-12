<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyorPortalAccount;
use App\Models\Survey\SurveyorPortalNotification;
use App\Models\Survey\SurveyPlanFile as PlanFile;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Models\User;
use App\Services\UserNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The four stages of a survey plan (CR 11112026-2, item 5), for KLAES and the MLPP-SG portal alike.
 *
 *   1 -> 2  the checking unit (GKN or Examination/Verification) sends corrections: marked-up copies drawn on
 *           screen and/or a file, with a note, back to the uploader;
 *   1 -> 3  or passes the plan as it is, straight to the SG/DSG;
 *   2 -> 3  the uploader uploads the final copy, which goes straight to the SG/DSG;
 *   3 -> 4  the SG/DSG signs, digitally or by hand (download, print, sign, scan), and uploads the signed copy.
 *
 * Every step is told: the staff bell (which the KLAES app mirrors as a push) or the portal's own notices.
 */
class SurveyPlanStages
{
    public function __construct(private UserNotificationService $bell)
    {
    }

    /* ----------------------------------------------------------------- who may act */

    public static function isChecker(?User $u, Plan $plan): bool
    {
        if (! $u) return false;
        $role = Plan::UNIT_ROLES[$plan->unit] ?? null;

        return ($role && $u->canDo($role, 'view')) || $u->canDo('Survey - Records', 'view') || $u->isSuperAdmin();
    }

    public static function isSigner(?User $u): bool
    {
        if (! $u) return false;
        foreach (Plan::SIGNING_ROLES as $role) {
            if ($u->canDo($role, 'view')) return true;
        }

        return $u->isSuperAdmin();
    }

    /** The KLAES uploader of a KLAES plan, or Survey - Records, may send the final copy in. */
    public static function isStaffUploader(?User $u, Plan $plan): bool
    {
        return $u && $plan->source !== 'portal'
            && ((int) $plan->created_by === (int) $u->id || $u->canDo('Survey - Records', 'view') || $u->isSuperAdmin());
    }

    /* ----------------------------------------------------------------- the steps */

    /**
     * Stage 1 -> 2. $markups: PNG data URLs drawn on the plan on screen. $file: a marked file uploaded instead.
     */
    public function sendCorrections(Plan $plan, User $by, string $note, array $markups, ?UploadedFile $file): Plan
    {
        $this->expect($plan, [Plan::STAGE_UPLOADED, Plan::STAGE_CORRECTION]);
        $note = trim(preg_replace('/\s+/u', ' ', $note));
        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Say what has to be corrected.']);
        }
        $pngs = array_values(array_filter($markups, fn ($d) => is_string($d)
            && (str_starts_with($d, 'data:image/png;base64,') || str_starts_with($d, 'data:image/jpeg;base64,'))));
        if (! $pngs && ! $file) {
            throw ValidationException::withMessages(['correction_file' => 'Mark the corrections on the plan, or upload a marked copy.']);
        }

        return $this->write($plan, function () use ($plan, $by, $note, $pngs, $file) {
            foreach ($pngs as $i => $data) {
                $jpeg = str_starts_with($data, 'data:image/jpeg;');
                $bytes = base64_decode(substr($data, strpos($data, ',') + 1), true);
                $magicOk = $bytes !== false && ($jpeg ? str_starts_with($bytes, "\xFF\xD8") : str_starts_with($bytes, "\x89PNG"));
                if (! $magicOk || strlen($bytes) > 20 * 1024 * 1024) {
                    throw ValidationException::withMessages(['markups' => 'A marked page could not be read. Draw it again.']);
                }
                $ext = $jpeg ? 'jpg' : 'png';
                $path = $this->dir($plan) . '/' . Str::uuid() . '.' . $ext;
                Storage::disk(Plan::DISK)->put($path, $bytes);
                $this->record($plan, Plan::STAGE_CORRECTION, PlanFile::KIND_CORRECTION, $path,
                    "{$plan->upload_ref}-correction-" . ($i + 1) . '.' . $ext, $jpeg ? 'image/jpeg' : 'image/png', strlen($bytes), $note, $by, null);
            }
            if ($file) {
                $this->storeUpload($plan, Plan::STAGE_CORRECTION, PlanFile::KIND_CORRECTION, $file, $note, $by, null);
            }
            $plan->stage = Plan::STAGE_CORRECTION;
            $plan->status = Plan::STAGE_CODES[Plan::STAGE_CORRECTION];
            $plan->saveQuietly();

            $this->tellUploader($plan, "Corrections on {$plan->upload_ref}",
                "{$plan->unit} returned {$plan->upload_ref} with corrections: {$note} Upload the final copy when it is corrected.");
        });
    }

    /** Stage 1 -> 3: no corrections; the plan as uploaded goes to the SG/DSG. */
    public function passToSigning(Plan $plan, User $by, ?string $note): Plan
    {
        $this->expect($plan, [Plan::STAGE_UPLOADED]);

        return $this->write($plan, function () use ($plan, $by, $note) {
            // The plan as uploaded becomes the final copy: a row pointing at the same stored file.
            $this->record($plan, Plan::STAGE_FINAL, PlanFile::KIND_FINAL, $plan->file_path, $plan->file_name,
                $plan->file_mime, $plan->file_size, trim((string) $note) ?: 'Passed without corrections.', $by, null);
            $plan->stage = Plan::STAGE_FINAL;
            $plan->status = Plan::STAGE_CODES[Plan::STAGE_FINAL];
            $plan->saveQuietly();

            $this->tellSigners($plan, "{$plan->upload_ref} is ready for signing",
                "{$plan->unit} passed {$plan->upload_ref} ({$plan->plan_type}) without corrections. Sign and upload the signed copy.");
            $this->tellUploader($plan, "{$plan->upload_ref} passed",
                "{$plan->unit} passed {$plan->upload_ref} without corrections. It is with the SG/DSG for signing.");
        });
    }

    /** Stage 2 -> 3: the uploader's corrected plan, straight to the SG/DSG. */
    public function uploadFinal(Plan $plan, UploadedFile $file, ?User $staff, ?SurveyorPortalAccount $account, ?string $note): Plan
    {
        $this->expect($plan, [Plan::STAGE_CORRECTION]);
        $this->checkFile($file, 'final_file');

        return $this->write($plan, function () use ($plan, $file, $staff, $account, $note) {
            $this->storeUpload($plan, Plan::STAGE_FINAL, PlanFile::KIND_FINAL, $file, trim((string) $note) ?: null, $staff, $account);
            $plan->stage = Plan::STAGE_FINAL;
            $plan->status = Plan::STAGE_CODES[Plan::STAGE_FINAL];
            $plan->saveQuietly();

            $this->tellSigners($plan, "Final copy of {$plan->upload_ref} for signing",
                ($account ? $account->name : ($staff?->name ?? 'The uploader')) . " uploaded the corrected final copy of {$plan->upload_ref}. Sign and upload the signed copy.");
            $this->tellUnit($plan, "Final copy of {$plan->upload_ref} uploaded",
                "The corrected final copy of {$plan->upload_ref} went to the SG/DSG for signing.");
        });
    }

    /** Stage 3 -> 4: the signed copy, digital or manual. */
    public function sign(Plan $plan, User $by, string $method, UploadedFile $file, ?string $note): Plan
    {
        $this->expect($plan, [Plan::STAGE_FINAL]);
        if (! in_array($method, [Plan::SIGN_DIGITAL, Plan::SIGN_MANUAL], true)) {
            throw ValidationException::withMessages(['signing_method' => 'Choose Digital or Manual signing.']);
        }
        $this->checkFile($file, 'signed_file');

        return $this->write($plan, function () use ($plan, $by, $method, $file, $note) {
            $this->storeUpload($plan, Plan::STAGE_SIGNED, PlanFile::KIND_SIGNED, $file, trim((string) $note) ?: null, $by, null);
            $plan->stage = Plan::STAGE_SIGNED;
            $plan->signing_method = $method;
            $plan->status = Plan::STAGE_CODES[Plan::STAGE_SIGNED];
            $plan->saveQuietly();

            $how = $method === Plan::SIGN_DIGITAL ? 'digitally' : 'by hand';
            $this->tellUploader($plan, "{$plan->upload_ref} is signed",
                "{$plan->upload_ref} ({$plan->plan_type}) was signed {$how} by {$by->name}. Download the signed copy.");
            $this->tellUnit($plan, "{$plan->upload_ref} is signed", "{$plan->upload_ref} was signed {$how} by {$by->name}.");
        });
    }

    /* ----------------------------------------------------------------- files */

    public function checkFile(UploadedFile $file, string $field): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, Plan::FILE_TYPES, true) || $file->getSize() > 25 * 1024 * 1024) {
            throw ValidationException::withMessages([$field => 'Upload a PDF, JPG, PNG or TIFF of at most 25 MB.']);
        }
    }

    private function dir(Plan $plan): string
    {
        return 'app/survey/survey-plans/' . $plan->created_at?->format('Y/m') . '/' . $plan->upload_ref;
    }

    private function storeUpload(Plan $plan, int $stage, string $kind, UploadedFile $file, ?string $note, ?User $staff, ?SurveyorPortalAccount $account): void
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $path = Storage::disk(Plan::DISK)->putFileAs($this->dir($plan), $file, Str::uuid() . '.' . $ext);
        if (! $path) {
            throw new \RuntimeException('The file could not be stored.');
        }
        $this->record($plan, $stage, $kind, $path, mb_substr($file->getClientOriginalName(), 0, 255),
            mb_substr((string) $file->getMimeType(), 0, 120), $file->getSize(), $note, $staff, $account);
    }

    private function record(Plan $plan, int $stage, string $kind, string $path, ?string $name, ?string $mime, ?int $size, ?string $note, ?User $staff, ?SurveyorPortalAccount $account): void
    {
        PlanFile::create([
            'survey_plan_upload_id' => $plan->id, 'stage' => $stage, 'kind' => $kind,
            'file_path' => $path, 'file_name' => $name, 'file_mime' => $mime, 'file_size' => $size,
            'note' => $note !== null ? mb_substr($note, 0, 2000) : null,
            'by_user_id' => $staff?->id, 'by_portal_account_id' => $account?->id,
            'by_name' => $account ? $account->name : $staff?->name,
        ]);
    }

    /* ----------------------------------------------------------------- plumbing */

    private function expect(Plan $plan, array $stages): void
    {
        if (! in_array($plan->stage_no, $stages, true)) {
            throw ValidationException::withMessages(['stage' => "{$plan->upload_ref} is at {$plan->stage_label} ({$plan->stage_status}); that step is not open now."]);
        }
    }

    private function write(Plan $plan, callable $step): Plan
    {
        DB::connection('sqlsrv')->transaction(function () use ($plan, $step) {
            // Lock the row so two people cannot move the same plan at once.
            DB::connection('sqlsrv')->table('survey_plan_uploads')->where('id', $plan->id)->lockForUpdate()->first();
            $step();
        });
        Log::info('Survey plan stage', ['ref' => $plan->upload_ref, 'stage' => $plan->stage]);

        return $plan->fresh(['files', 'referenceBeacons']);
    }

    private function link(Plan $plan, bool $portal): string
    {
        return $portal ? route('surveyor-portal.survey-plans.show', $plan->id) : route('survey-module.survey-plans.show', $plan->id);
    }

    private function tellUploader(Plan $plan, string $title, string $body): void
    {
        DB::connection('sqlsrv')->afterCommit(function () use ($plan, $title, $body) {
            try {
                if ($plan->source === 'portal' && $plan->surveyor_portal_account_id) {
                    SurveyorPortalNotification::create(['surveyor_portal_account_id' => $plan->surveyor_portal_account_id,
                        'title' => $title, 'body' => $body, 'link' => $this->link($plan, true)]);
                } elseif ($plan->created_by) {
                    $this->bell->create((int) $plan->created_by, 'survey_plan_upload', $title, $body,
                        ['open_path' => parse_url($this->link($plan, false), PHP_URL_PATH)], ['module' => 'survey']);
                }
            } catch (\Throwable $e) {
                Log::warning('Survey plan: uploader not told', ['ref' => $plan->upload_ref, 'error' => $e->getMessage()]);
            }
        });
    }

    private function tellSigners(Plan $plan, string $title, string $body): void
    {
        $this->tellRoles($plan, Plan::SIGNING_ROLES, $title, $body);
    }

    private function tellUnit(Plan $plan, string $title, string $body): void
    {
        $this->tellRoles($plan, array_filter([Plan::UNIT_ROLES[$plan->unit] ?? null]), $title, $body);
    }

    private function tellRoles(Plan $plan, array $roles, string $title, string $body): void
    {
        DB::connection('sqlsrv')->afterCommit(function () use ($plan, $roles, $title, $body) {
            try {
                $users = User::query()
                    ->where(function ($q) use ($roles) {
                        foreach ($roles as $role) {
                            $q->orWhereRaw("',' + REPLACE(ISNULL(assign_role, ''), ', ', ',') + ',' LIKE ?", ['%,' . $role . ',%']);
                        }
                    })
                    ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
                    ->pluck('id');
                $path = parse_url($this->link($plan, false), PHP_URL_PATH);
                foreach ($users->unique() as $id) {
                    $this->bell->create((int) $id, 'survey_plan_upload', $title, $body, ['open_path' => $path], ['module' => 'survey']);
                }
            } catch (\Throwable $e) {
                Log::warning('Survey plan: role holders not told', ['ref' => $plan->upload_ref, 'error' => $e->getMessage()]);
            }
        });
    }
}
