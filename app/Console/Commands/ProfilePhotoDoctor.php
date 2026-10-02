<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\UserPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Reports, in plain language, why user profile photographs are not displaying.
 *
 * Four different faults all show the same symptom — an empty circle where the face
 * should be — and they are indistinguishable from the screen:
 *
 *   1. APP_URL still holds a development value, so every <img src> is emitted against
 *      127.0.0.1 and the viewer's own browser fetches nothing. (Fixed at source in
 *      UserPhoto::url(), which now builds against the request host; this check remains
 *      because a stale APP_URL still breaks mail, PDFs and queued work.)
 *   2. public/storage does not exist, so /storage/... 404s even though the URL is right.
 *      A code upload never carries a symlink, and on Windows Server creating one needs
 *      an elevated shell — so this is the normal state of a fresh deployment here.
 *   3. The image files were never copied to this server. The database row is intact and
 *      the URL is correct; there is simply no file behind it.
 *   4. The config cache still holds the old APP_URL after .env was corrected.
 *
 * Cheapest check first, and it prints an arrow at whatever is actually broken.
 */
class ProfilePhotoDoctor extends Command
{
    protected $signature = 'profile:photo-doctor
                            {--link : Create the public/storage link if it is missing}
                            {--samples=5 : How many real users to resolve and check on disk}';

    protected $description = 'Check that user profile photographs can actually be served on this box';

    public function handle(): int
    {
        $this->info('KLAES User Profile Photos — display check');
        $this->newLine();

        $ok = true;

        $ok = $this->checkAppUrl() && $ok;
        $ok = $this->checkConfigCache() && $ok;
        $ok = $this->checkPublicStorageLink() && $ok;
        $ok = $this->checkFilesOnDisk() && $ok;

        $this->newLine();

        if ($ok) {
            $this->info('Profile photographs should display. If a specific user still shows an empty');
            $this->info('circle, that one row points at a file that is not on this server.');

            return self::SUCCESS;
        }

        $this->warn('At least one check failed — the lines marked FAIL above are why the photos are blank.');

        return self::FAILURE;
    }

    /** 1. APP_URL — the classic "works on the dev box" cause. */
    private function checkAppUrl(): bool
    {
        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: '';
        $isLocal = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)
            || $host === ''
            || str_ends_with(strtolower($host), '.test')
            || str_ends_with(strtolower($host), '.local');

        if (!$isLocal) {
            $this->line("  <fg=green>OK</>    APP_URL is {$appUrl}.");

            return true;
        }

        $this->line("  <fg=yellow>WARN</>  APP_URL is {$appUrl} — a development address.");
        $this->line('        Since 2026-09-03 the avatar URL is built from the request host, so the');
        $this->line('        screens are no longer affected. But anything rendered OUTSIDE a browser');
        $this->line('        request — emailed sheets, generated PDFs, queued jobs — still uses this');
        $this->line('        value and will point at the wrong server.');
        $this->line('        Set APP_URL in .env to this server address, then: php artisan config:clear');

        // A warning, not a failure: it no longer blanks the profile page itself.
        return true;
    }

    /** 4. A cached config keeps serving the old APP_URL after .env is corrected. */
    private function checkConfigCache(): bool
    {
        if (!app()->configurationIsCached()) {
            $this->line('  <fg=green>OK</>    Config is not cached, so .env changes take effect immediately.');

            return true;
        }

        $this->line('  <fg=yellow>WARN</>  Config IS cached. An edit to .env has no effect until you run');
        $this->line('        php artisan config:clear (or config:cache to rebuild it).');

        return true;
    }

    /**
     * 2. public/storage — proved by resolving a real file THROUGH it, rather than by
     * asking is_link(): on Windows the link may be a junction, and a plain is_link()
     * check reports differently depending on how it was created.
     */
    private function checkPublicStorageLink(): bool
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (!file_exists($link)) {
            $this->line('  <fg=red>FAIL</>  public/storage does not exist, so every /storage/... URL 404s.');
            $this->line("        Expected it to point at: {$target}");
            $this->line('        Fix: php artisan storage:link');
            $this->line('        On Windows Server that needs an ELEVATED shell (Run as administrator),');
            $this->line('        or, as an equivalent, in an elevated Command Prompt:');
            $this->line('          mklink /D "' . $link . '" "' . $target . '"');
            $this->line('        Re-run this command with --link to try creating it now.');

            if ($this->option('link')) {
                $this->attemptLink($link, $target);
            }

            return false;
        }

        // Does a file written to the public disk actually appear under public/storage?
        $probe = 'profile_photo_doctor_' . uniqid() . '.txt';
        $probePath = $target . DIRECTORY_SEPARATOR . $probe;

        try {
            File::put($probePath, 'probe');
            $visible = file_exists($link . DIRECTORY_SEPARATOR . $probe);
        } catch (\Throwable $e) {
            $this->line('  <fg=red>FAIL</>  Could not write to ' . $target . ' to test the link: ' . $e->getMessage());

            return false;
        } finally {
            if (isset($probePath) && file_exists($probePath)) {
                @unlink($probePath);
            }
        }

        if (!$visible) {
            $this->line('  <fg=red>FAIL</>  public/storage exists but does NOT resolve to ' . $target . '.');
            $this->line('        It is probably a real (empty) directory left behind by a file copy,');
            $this->line('        which silently shadows the link. Delete it, then: php artisan storage:link');

            return false;
        }

        $this->line('  <fg=green>OK</>    public/storage resolves to the public disk.');

        return true;
    }

    /** 3. The rows are fine but the images were never copied to this server. */
    private function checkFilesOnDisk(): bool
    {
        $placeholder = UserPhoto::PLACEHOLDER;

        try {
            // Through the model, never DB::table('users'): User is pinned to the sqlsrv
            // connection, while the DEFAULT connection has its own small `users` table.
            // A raw query would silently check the wrong two rows and report all clear.
            $rows = User::query()
                ->whereNotNull('profile')
                ->where('profile', '<>', '')
                ->where('profile', '<>', $placeholder)
                ->get(['id', 'username', 'profile', 'passport_photo_path']);
        } catch (\Throwable $e) {
            $this->line('  <fg=red>FAIL</>  Could not read the users table: ' . $e->getMessage());

            return false;
        }

        $this->line('  <fg=gray>·</>     Reading users from the "' . (new User)->getConnectionName() . '" connection.');

        if ($rows->isEmpty()) {
            $this->line('  <fg=yellow>WARN</>  No user has a profile photograph recorded at all.');
            $this->line('        Nothing is broken; there is simply nothing to display yet.');

            return true;
        }

        $missing = [];
        foreach ($rows as $row) {
            if (!$this->fileFor($row->profile)) {
                $missing[] = $row;
            }
        }

        $total = $rows->count();
        $present = $total - count($missing);

        if ($missing === []) {
            $this->line("  <fg=green>OK</>    All {$total} recorded profile photographs are present on disk.");
        } else {
            $this->line("  <fg=red>FAIL</>  " . count($missing) . " of {$total} profile photographs are MISSING from this server");
            $this->line('        (' . $present . ' present). The database rows are fine — the image files were');
            $this->line('        never copied here. Copy storage/app/public/upload/profile (and');
            $this->line('        storage/app/public/profiles) from the server the photos were uploaded on.');
            $this->newLine();
            foreach (array_slice($missing, 0, 10) as $row) {
                $this->line("        user #{$row->id} {$row->username}: {$row->profile}");
            }
            if (count($missing) > 10) {
                $this->line('        … and ' . (count($missing) - 10) . ' more.');
            }
        }

        $this->sampleUrls($rows->take((int) $this->option('samples')));

        return $missing === [];
    }

    /** Show what the browser would actually be asked to fetch, and whether it exists. */
    private function sampleUrls($rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('  Resolved URLs (as generated from the console — in a browser the host is');
        $this->line('  whatever the app is served from):');

        foreach ($rows as $row) {
            $url = UserPhoto::url($row->profile, $row->passport_photo_path);
            $onDisk = $this->fileFor($row->profile) ? '<fg=green>file present</>' : '<fg=red>NO FILE</>';
            $this->line("    #{$row->id} {$row->username}");
            $this->line("        {$url}");
            $this->line("        {$onDisk}");
        }
    }

    /**
     * Absolute path of the image behind a stored `profile` value, or null when there is
     * no file. Mirrors the shapes UserPhoto::url() accepts.
     */
    private function fileFor(?string $stored): ?string
    {
        $value = trim((string) $stored);

        if ($value === '' || strtolower($value) === UserPhoto::PLACEHOLDER) {
            return null;
        }

        $path = str_contains($value, '/') ? $value : 'upload/profile/' . $value;
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        $candidate = storage_path('app/public/' . ltrim($path, '/'));

        return is_file($candidate) ? $candidate : null;
    }

    /** Best-effort storage:link, since on Windows it usually needs elevation. */
    private function attemptLink(string $link, string $target): void
    {
        $this->newLine();
        $this->line('  Attempting to create the link…');

        try {
            $this->callSilent('storage:link');
        } catch (\Throwable $e) {
            $this->line('  <fg=red>FAIL</>  ' . $e->getMessage());
        }

        if (file_exists($link)) {
            $this->line('  <fg=green>OK</>    Created. Re-run this command to confirm it resolves.');

            return;
        }

        $this->line('  <fg=red>FAIL</>  Could not create it from this shell — this is expected on Windows');
        $this->line('        without elevation. Open Command Prompt as administrator and run:');
        $this->line('          mklink /D "' . $link . '" "' . $target . '"');
    }
}
