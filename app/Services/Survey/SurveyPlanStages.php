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
 * The four stages of a survey plan (Kunle, 2026-11-18), for KLAES and the MLPP-SG portal alike.
 *
 *   1      the surveyor uploads (or plots) the plan;
 *   1 -> 2 the checking unit (GKN or Examination/Verification) checks it: falls on location or not, corrections
 *          (marked on screen and/or a file, with a note) or none, and sends it back to the surveyor. Never to the SG;
 *   2 -> 3 the surveyor corrects (if any), produces the final survey plan and uploads it, to the SG/DSG;
 *   3 -> 4 the SG/DSG checks/downloads, prints, signs and uploads the signed copy.
 *
 * The uploader acts on stages 1 and 3 only: they cannot check or sign their own plan.
 * Every step is told: the staff bell (which the KLAES app mirrors as a push) or the portal's own notices.
 */
class SurveyPlanStages
{
    public function __construct(private UserNotificationService $bell)
    {
    }

    /* ----------------------------------------------------------------- who may act */

    /** The KLAES staff member who uploaded the plan (a portal plan has none). */
    public static function isOwnPlan(?User $u, Plan $plan): bool
    {
        return $u && $plan->source !== 'portal' && $plan->created_by && (int) $plan->created_by === (int) $u->id;
    }

    /** Stage 2: the plan's unit (or Survey - Records, or a Super Admin), never the uploader (Kunle, 2026-11-18). */
    public static function isChecker(?User $u, Plan $plan): bool
    {
        if (! $u || self::isOwnPlan($u, $plan)) return false;
        $role = Plan::UNIT_ROLES[$plan->unit] ?? null;

        return ($role && $u->canDo($role, 'view')) || $u->canDo('Survey - Records', 'view') || $u->isSuperAdmin();
    }

    /** Stage 4: the SG or DSG (or a Super Admin); with a plan, never its uploader. */
    public static function isSigner(?User $u, ?Plan $plan = null): bool
    {
        if (! $u || ($plan && self::isOwnPlan($u, $plan))) return false;
        foreach (Plan::SIGNING_ROLES as $role) {
            if ($u->canDo($role, 'view')) return true;
        }

        return $u->isSuperAdmin();
    }

    /** Stage 3: only the surveyor who uploaded a KLAES plan (Kunle, 2026-11-18: "stage three is for the surveyor who did it"). */
    public static function isStaffUploader(?User $u, Plan $plan): bool
    {
        return self::isOwnPlan($u, $plan);
    }

    /* ----------------------------------------------------------------- the steps */

    /**
     * Stage 2, Check: whether the plan falls on location ($location: falls | not_falls) and whether there are corrections
     * ($result: corrections | none). Either way it goes back to the surveyor who drew it for stage 3, never to the SG.
     * Corrections need a note; $markups (pages marked on screen, as image data URLs) and $file (a marked copy) are optional.
     */
    public function check(Plan $plan, User $by, string $result, string $location, ?string $note, array $markups = [], ?UploadedFile $file = null): Plan
    {
        $this->expect($plan, [Plan::STAGE_UPLOADED]);
        if (! array_key_exists($result, Plan::CHECK_RESULTS)) {
            throw ValidationException::withMessages(['check_result' => 'Say whether there are corrections.']);
        }
        if (! array_key_exists($location, Plan::CHECK_LOCATIONS)) {
            throw ValidationException::withMessages(['check_location' => 'Say whether the map falls on location.']);
        }
        $note = trim(preg_replace('/\s+/u', ' ', (string) $note));
        $corrections = $result === 'corrections';
        if ($corrections && $note === '') {
            throw ValidationException::withMessages(['note' => 'Say what has to be corrected.']);
        }
        $pngs = $corrections ? array_values(array_filter($markups, fn ($d) => is_string($d)
            && (str_starts_with($d, 'data:image/png;base64,') || str_starts_with($d, 'data:image/jpeg;base64,')))) : [];
        $file = $corrections ? $file : null;

        return $this->write($plan, function () use ($plan, $by, $note, $pngs, $file, $result, $location, $corrections) {
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
            $plan->status = $corrections ? 'Corrections Sent' : 'Checked - No Corrections';
            $plan->check_result = $result;
            $plan->check_location = $location;
            $plan->check_note = $note !== '' ? mb_substr($note, 0, 2000) : null;
            $plan->checked_by = $by->id;
            $plan->checked_by_name = mb_substr((string) $by->name, 0, 255);
            $plan->checked_at = now();
            $plan->saveQuietly();

            $where = Plan::CHECK_LOCATIONS[$location];
            $this->tellUploader($plan, ($corrections ? 'Corrections on ' : 'Checked: ') . $plan->upload_ref,
                "{$plan->unit} checked {$plan->upload_ref}: {$where}; " . ($corrections ? "corrections: {$note}" : 'no corrections' . ($note !== '' ? " ({$note})" : ''))
                . '. ' . ($corrections ? 'Correct it, produce' : 'Produce') . ' the final survey plan and upload it for the SG/DSG.');
        });
    }

    /** Stage 3: the surveyor's final survey plan (corrected, when there were corrections), to the SG/DSG. */
    public function uploadFinal(Plan $plan, UploadedFile $file, ?User $staff, ?SurveyorPortalAccount $account, ?string $note): Plan
    {
        $this->expect($plan, [Plan::STAGE_CORRECTION]);
        $this->checkFile($file, 'final_file');

        return $this->write($plan, function () use ($plan, $file, $staff, $account, $note) {
            $this->storeUpload($plan, Plan::STAGE_FINAL, PlanFile::KIND_FINAL, $file, trim((string) $note) ?: null, $staff, $account);
            $plan->stage = Plan::STAGE_FINAL;
            $plan->status = Plan::STAGE_CODES[Plan::STAGE_FINAL];
            $plan->saveQuietly();

            $this->tellSigners($plan, "Final survey plan {$plan->upload_ref} for signing",
                ($account ? $account->name : ($staff?->name ?? 'The surveyor')) . " uploaded the final survey plan {$plan->upload_ref}. Check, print, sign and upload the signed copy.");
            $this->tellUnit($plan, "Final survey plan {$plan->upload_ref} uploaded",
                "The final survey plan {$plan->upload_ref} went to the SG/DSG for signing.");
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
