<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProfilePhotoService;
use Illuminate\Console\Command;

/**
 * Who is held by the profile-picture face check, and the way back out.
 *
 * The check is a heuristic — a flat-colour measurement plus a confidence threshold — and
 * it decides whether somebody can use the system at all. A real photograph it refuses
 * would otherwise lock that person out for good: the upload card runs the same detector
 * on the same file, so re-uploading the picture cannot clear it. `--override` is the way
 * a human overrules the machine, and `--recheck` is the way to make it judge again after
 * the thresholds are tuned.
 *
 *   php artisan profile:face-status                    list every rejected account
 *   php artisan profile:face-status --all              also list passed/unchecked counts
 *   php artisan profile:face-status --override=NKARAYE accept that account's picture
 *   php artisan profile:face-status --recheck=NKARAYE  forget the verdict, check again
 *
 * A user is named by id or username.
 */
class ProfilePhotoFaceStatus extends Command
{
    protected $signature = 'profile:face-status
                            {--all : Show the totals for every state, not just rejections}
                            {--override= : Accept this user\'s current picture by hand (id or username)}
                            {--recheck= : Clear this user\'s verdict so the browser judges the picture again (id or username)}';

    protected $description = 'List accounts locked by the profile-picture face check, and override or re-check one';

    public function handle(): int
    {
        if (!ProfilePhotoService::faceColumnsExist()) {
            $this->error('The photo_face_* columns are not on this database yet.');
            $this->line('Run database/sql/2026_09_05_add_photo_face_check_to_users.sql against SQL Server');
            $this->line('(then the _ledger.mysql.sql companion against MySQL), or `php artisan migrate`.');

            return self::FAILURE;
        }

        if ($this->option('override') !== null) {
            return $this->apply((string) $this->option('override'), ProfilePhotoService::FACE_OVERRIDE);
        }

        if ($this->option('recheck') !== null) {
            return $this->apply((string) $this->option('recheck'), null);
        }

        return $this->report();
    }

    /**
     * Set or clear one account's verdict.
     */
    private function apply(string $needle, ?string $status): int
    {
        $user = $this->findUser($needle);

        if (!$user) {
            $this->error("No user matches \"{$needle}\" (give an id or a username).");

            return self::FAILURE;
        }

        $service = app(ProfilePhotoService::class);
        $label = trim($user->first_name . ' ' . $user->last_name) ?: $user->username;

        if ($status === null) {
            $service->clearFaceVerdict($user);
            $user->save();
            $this->info("Cleared the face-check verdict for {$label} (#{$user->id}).");
            $this->line('Their browser will check the picture again at their next page load.');

            return self::SUCCESS;
        }

        if (!$user->has_profile_photo) {
            $this->error("{$label} (#{$user->id}) has no picture on file — there is nothing to accept.");

            return self::FAILURE;
        }

        $service->recordFaceVerdict($user, $status, 'Accepted by hand from the console');
        $user->save();

        $this->info("Accepted {$label}'s current picture (#{$user->id}). It will not be checked again.");

        return self::SUCCESS;
    }

    /**
     * Who is currently held, and — with --all — how the whole staff list divides up.
     */
    private function report(): int
    {
        $rejected = User::query()
            ->where('photo_face_status', ProfilePhotoService::FACE_FAIL)
            ->orderBy('photo_face_checked_at', 'desc')
            ->get(['id', 'username', 'first_name', 'last_name', 'profile', 'passport_photo_path',
                   'photo_face_status', 'photo_face_reason', 'photo_face_checked_at', 'photo_face_path'])
            // A verdict pinned to a picture the account no longer carries does not hold
            // anybody, so it must not be listed as if it did.
            ->filter(fn (User $user) => $user->photo_face_rejected)
            ->values();

        if ($rejected->isEmpty()) {
            $this->info('No account is held by the face check.');
        } else {
            $this->warn($rejected->count() . ' account(s) locked until a real photograph is uploaded:');
            $this->newLine();

            $this->table(
                ['ID', 'Username', 'Name', 'Why', 'Checked'],
                $rejected->map(fn (User $user) => [
                    $user->id,
                    $user->username,
                    trim($user->first_name . ' ' . $user->last_name),
                    $user->photo_face_reason ?: '—',
                    optional($user->photo_face_checked_at)->format('Y-m-d H:i') ?: '—',
                ])->all()
            );

            $this->line('Overrule one with: php artisan profile:face-status --override=<id|username>');
        }

        if (!$this->option('all')) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Every account, by state:');

        $counts = [
            'No picture at all (already held by the missing-photo rule)' => 0,
            'Picture checked — passed' => 0,
            'Picture accepted by hand (override)' => 0,
            'Picture checked — rejected' => 0,
            'Picture not checked yet' => 0,
        ];

        User::query()
            ->select(['id', 'profile', 'passport_photo_path', 'photo_face_status', 'photo_face_path'])
            ->chunkById(500, function ($users) use (&$counts) {
                foreach ($users as $user) {
                    if (!$user->has_profile_photo) {
                        $counts['No picture at all (already held by the missing-photo rule)']++;
                        continue;
                    }

                    switch ($user->photo_face_verdict) {
                        case ProfilePhotoService::FACE_PASS:
                            $counts['Picture checked — passed']++;
                            break;
                        case ProfilePhotoService::FACE_OVERRIDE:
                            $counts['Picture accepted by hand (override)']++;
                            break;
                        case ProfilePhotoService::FACE_FAIL:
                            $counts['Picture checked — rejected']++;
                            break;
                        default:
                            $counts['Picture not checked yet']++;
                    }
                }
            });

        foreach ($counts as $label => $count) {
            $this->line('  ' . str_pad((string) $count, 6) . $label);
        }

        return self::SUCCESS;
    }

    private function findUser(string $needle): ?User
    {
        $needle = trim($needle);

        if ($needle === '') {
            return null;
        }

        if (ctype_digit($needle)) {
            return User::find((int) $needle);
        }

        return User::where('username', $needle)->first();
    }
}
