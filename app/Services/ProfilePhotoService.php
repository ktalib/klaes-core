<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Single place that writes a user's passport photo.
 *
 * The `profile` column historically held three different shapes (a public-disk path,
 * a bare filename under upload/profile, and the "avatar.png" placeholder); every new
 * upload goes through here so it is always stored as a public-disk relative path,
 * which User::getProfileUrlAttribute() resolves for display.
 *
 * It is also the one place the face-check verdict on that photo is written or cleared,
 * so a new picture can never inherit the previous one's verdict.
 */
class ProfilePhotoService
{
    public const DIRECTORY = 'upload/profile';
    public const PLACEHOLDER = 'avatar.png';
    public const MAX_KILOBYTES = 2048;

    /** The picture shows one human face. */
    public const FACE_PASS = 'pass';

    /** The picture is not a photograph of a face — an avatar, a logo, a landscape. */
    public const FACE_FAIL = 'fail';

    /** Accepted by hand, against the detector. Never re-checked. */
    public const FACE_OVERRIDE = 'override';

    /**
     * Validation rules for an uploaded passport photo.
     */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'image',
            'mimes:jpeg,jpg,png,gif',
            'max:' . self::MAX_KILOBYTES,
        ];
    }

    /**
     * Store the file against the user and return the stored path.
     * The caller is responsible for saving the model.
     */
    public function store(UploadedFile $file, User $user): string
    {
        $filename = uniqid('profile_') . '_' . time() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->putFileAs(self::DIRECTORY, $file, $filename);

        $this->deletePrevious($user);

        $path = self::DIRECTORY . '/' . $filename;
        $user->profile = $path;
        $user->passport_photo_path = $path;

        // A verdict speaks for one picture only. Clearing it here means the new photo is
        // judged on its own merits at the user's next page load, rather than inheriting
        // the pass — or the rejection — of the one it replaced.
        $this->clearFaceVerdict($user);

        return $path;
    }

    /**
     * Record what the browser's face check made of the picture currently on file.
     *
     * The detector is client-side (face-api.js) — there is no PHP implementation — so
     * this is how a verdict reaches the server-side gate at all. The caller is
     * responsible for saving the model.
     *
     * Only an explicit pass/fail/override is stored; "the detector could not load" is
     * not a verdict and must never be written, or a blocked asset would lock out every
     * member of staff at once.
     */
    public function recordFaceVerdict(User $user, string $status, ?string $reason = null): bool
    {
        if (!in_array($status, [self::FACE_PASS, self::FACE_FAIL, self::FACE_OVERRIDE], true)) {
            return false;
        }

        if (!self::faceColumnsExist()) {
            return false;
        }

        $user->photo_face_status = $status;
        $user->photo_face_reason = $reason !== null ? mb_substr(trim($reason), 0, 160) : null;
        $user->photo_face_checked_at = now();
        // Which picture was judged, so the verdict is dropped if the file ever changes
        // through a path that did not come through store().
        $user->photo_face_path = self::currentPhotoPath($user);

        return true;
    }

    /**
     * Forget any verdict: the next page load checks the picture again.
     * The caller is responsible for saving the model.
     */
    public function clearFaceVerdict(User $user): void
    {
        if (!self::faceColumnsExist()) {
            return;
        }

        $user->photo_face_status = null;
        $user->photo_face_reason = null;
        $user->photo_face_checked_at = null;
        $user->photo_face_path = null;
    }

    /**
     * The stored value a verdict is pinned to — whichever column the photo URL resolves
     * from, in the same order User::getProfileUrlAttribute() reads them.
     */
    public static function currentPhotoPath(User $user): ?string
    {
        foreach ([$user->profile, $user->passport_photo_path] as $candidate) {
            $value = trim((string) ($candidate ?? ''));

            if ($value !== '' && !\App\Support\UserPhoto::isPlaceholder($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Have the photo_face_* columns been added to this database yet?
     *
     * .env and schema changes reach production separately from code (the SQL script is
     * run by hand), so the write path has to survive the window where the code is newer
     * than the table. Memoised: one information_schema query per request at most, and
     * only on a request that actually records a verdict.
     */
    public static function faceColumnsExist(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = Schema::connection('sqlsrv')->hasColumn('users', 'photo_face_status');
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }

    /**
     * Clear the user's photo: delete the file and blank both columns.
     * The caller is responsible for saving the model.
     *
     * Returns true when the user actually had a photo to remove.
     *
     * Note this re-arms the mandatory-photo gate — User::$needs_profile_photo becomes
     * true again, so RequireProfilePhoto will hold the user on the upload card at their
     * next request until they supply a new one. That is the intended effect of removing
     * a wrong or unacceptable photo.
     */
    public function remove(User $user): bool
    {
        $had = $user->profile_url !== null;

        // Both columns are cleared: profile is what the accessor reads first, but a
        // legacy row can name a different file in passport_photo_path, which would
        // otherwise resurface as the fallback.
        $this->deleteFile($user, (string) $user->profile);
        $this->deleteFile($user, (string) $user->passport_photo_path);

        $user->profile = null;
        $user->passport_photo_path = null;
        $this->clearFaceVerdict($user);

        return $had;
    }

    /**
     * Remove the user's current photo file, leaving the shared placeholder alone.
     */
    private function deletePrevious(User $user): void
    {
        $this->deleteFile($user, (string) $user->profile);
    }

    /**
     * Delete one stored photo path off the public disk.
     *
     * Skips the shared "avatar.png" placeholder, and skips any path another user row
     * still points at — legacy rows stored bare filenames, so two accounts can name the
     * same file and deleting it for one would blank the other.
     */
    private function deleteFile(User $user, string $stored): void
    {
        $stored = trim($stored);

        if ($stored === '' || \App\Support\UserPhoto::isPlaceholder($stored)) {
            return;
        }

        $sharedWith = User::where('id', '<>', $user->id)
            ->where(function ($query) use ($stored) {
                $query->where('profile', $stored)->orWhere('passport_photo_path', $stored);
            })
            ->exists();

        if ($sharedWith) {
            return;
        }

        // Older rows stored a bare filename that lived under upload/profile.
        $candidates = [$stored, self::DIRECTORY . '/' . $stored];

        foreach ($candidates as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                Storage::disk('public')->delete($candidate);
                return;
            }
        }
    }
}
