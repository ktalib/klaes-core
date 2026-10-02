<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BulkSmsNgService;
use App\Services\PhoneOtpService;
use Illuminate\Console\Command;

/**
 * The account-verification gate: who it is holding, and the way past it.
 *
 * This command exists because the gate can lock the entire Ministry out of its own
 * system through no fault of any user. The code travels by EMAIL (the default) or by
 * SMS, and each route has its own way of failing silently: email needs MAIL_* settings
 * that .env does not carry to a new server and an address that is a real mailbox rather
 * than one of the thousand seeded placeholders; SMS needs a wallet with credit, a
 * reachable gateway, a route that does not defer everything sent after 19:45 until
 * 08:30, and a handset that accepts bulk traffic. When any of those fails there has to
 * be a way through that does not involve editing the database by hand at eight in the
 * morning.
 *
 *   php artisan phone:verify-status                     what the gate is doing now
 *   php artisan phone:verify-status --user=42           one account, and why
 *   php artisan phone:verify-status --user=42 --verify  let that person through
 *   php artisan phone:verify-status --user=42 --reset   put them back behind it
 *   php artisan phone:verify-status --verify-all        lift it for everybody
 *
 * Turning the gate off entirely is a config change, not a command:
 * PHONE_VERIFICATION_ENABLED=false, or config/phone_verification.php. That leaves
 * every row untouched, so it can be switched back on later without redoing the work.
 */
class PhoneVerificationStatus extends Command
{
    protected $signature = 'phone:verify-status
        {--user= : One user id — show that account, and act on it with --verify / --reset}
        {--verify : Mark the account verified without sending a code (needs --user, or --verify-all)}
        {--verify-email : Also mark the account email verified (requires --user and --verify)}
        {--reset : Clear the verification and any pending code (needs --user)}
        {--verify-all : Mark EVERY account verified, lifting the gate for the whole staff list}
        {--limit=15 : How many held accounts to list}
        {--force : Do not ask before --verify-all}';

    protected $description = 'Inspect and override the account verification gate (email / SMS codes).';

    public function handle(PhoneOtpService $otp, BulkSmsNgService $gateway): int
    {
        $this->line('');
        $this->info('Account verification gate — status');
        $this->line(str_repeat('=', 60));

        // ── Switches ─────────────────────────────────────────────────────────
        $configured = (bool) config('phone_verification.enabled', true);
        $ready = $otp->columnsReady();

        $this->line('');
        $this->comment('Switches');
        $this->status('phone_verification.enabled', $configured);
        $this->status('columns deployed (users.is_otp_verified)', $ready);
        $this->status('email route', $otp->channelEnabled(PhoneOtpService::CHANNEL_EMAIL));
        $this->status('SMS route', $otp->channelEnabled(PhoneOtpService::CHANNEL_SMS));
        $this->line('  the card opens on ..... ' . $otp->defaultChannel());
        $this->line('  code expires after .... ' . $otp->ttlMinutes() . ' minutes');
        $this->line('  resend cooldown ....... ' . $otp->cooldownSeconds() . ' seconds');
        $this->line('  wrong codes allowed ... ' . $otp->maxAttempts());

        if (!$otp->channelColumnReady()) {
            $this->line('');
            $this->warn('  users.otp_channel is not on this database.');
            $this->line('  Codes still go out and the card still works — the account simply cannot');
            $this->line('  remember WHICH route was used, so a resend after a page reload falls back');
            $this->line('  to SMS and a changed email address does not void an email-proved account.');
            $this->line('  Run database/sql/2026_09_07_add_otp_channel_to_users.sql against SQL Server.');
        }

        if ($otp->enabledChannels() === []) {
            $this->line('');
            $this->warn('  BOTH routes are switched off, so the gate is holding NOBODY.');
            $this->line('  Set phone_verification.channels.email or .sms back to true.');
        }

        if (!$ready) {
            $this->line('');
            $this->warn('  The columns are not on this database, so the gate is holding NOBODY.');
            $this->line('  Run database/sql/2026_09_06_add_phone_otp_verification_to_users.sql');
            $this->line('  against SQL Server (then the .mysql.sql ledger file), or `php artisan migrate`.');

            return self::SUCCESS;
        }

        if (!$configured) {
            $this->line('');
            $this->warn('  The gate is switched OFF: everybody goes straight through.');
            $this->line('  Nothing has been un-verified — turning it back on resumes where it left off.');
        }

        // ── One account ──────────────────────────────────────────────────────
        if ($this->option('user')) {
            return $this->handleOneUser($otp);
        }

        if ($this->option('verify-all')) {
            return $this->handleVerifyAll();
        }

        if ($this->option('verify') || $this->option('reset')) {
            $this->error('--verify and --reset act on one account: add --user=<id> (or use --verify-all).');

            return self::FAILURE;
        }

        // ── The whole staff list ─────────────────────────────────────────────
        $this->reportPopulation($otp);
        $this->reportMailer();
        $this->reportWallet($gateway);

        return self::SUCCESS;
    }

    /**
     * Show — and optionally change — a single account.
     */
    private function handleOneUser(PhoneOtpService $otp): int
    {
        $identifier = trim((string) $this->option('user'));
        $user = ctype_digit($identifier)
            ? User::find((int) $identifier)
            : User::query()->whereRaw('UPPER(username) = ?', [strtoupper($identifier)])->first();

        if (!$user) {
            $this->error('No user with ID or username ' . $identifier . '.');

            return self::FAILURE;
        }

        if ($this->option('verify') && $this->option('reset')) {
            $this->error('--verify and --reset ask for opposite things. Pick one.');

            return self::FAILURE;
        }

        if ($this->option('verify-email') && !$this->option('verify')) {
            $this->error('--verify-email requires --verify.');

            return self::FAILURE;
        }

        if ($this->option('verify')) {
            $otp->markVerified($user);
            if ($this->option('verify-email') && !$user->getAttribute('email_verified_at')) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $this->line('');
            $this->info('  ' . $user->name . ' (id ' . $user->id . ') is now verified — no code was sent.');
        }

        if ($this->option('reset')) {
            $otp->reset($user);
            $this->line('');
            $this->info('  ' . $user->name . ' (id ' . $user->id . ') is back behind the gate.');
        }

        $user->refresh();
        $stored = (string) ($user->phone_number ?? '');
        $usable = BulkSmsNgService::normalizeNumber($stored);

        $storedEmail = trim((string) ($user->email ?? ''));
        $usableEmail = $otp->emailForCard($user);
        $emailNote = $usableEmail !== ''
            ? $usableEmail
            : ($storedEmail === ''
                ? 'NO address on the account'
                : ($otp->isPlaceholderEmail($storedEmail)
                    ? 'a PLACEHOLDER, not a mailbox — no code can reach it'
                    : 'NOT a valid address'));

        $channel = $otp->currentChannel($user);

        $this->line('');
        $this->comment('Account');
        $this->line('  id .................... ' . $user->id);
        $this->line('  name .................. ' . $user->name);
        $this->line('  active ................ ' . (((int) ($user->is_active ?? 0) === 1) ? 'yes' : 'no'));
        $this->line('  email on file ......... ' . ($storedEmail === '' ? '(none)' : $storedEmail));
        $this->line('  email verified ......... ' . ($user->email_verified_at ? $user->email_verified_at->copy()->setTimezone(config('phone_verification.timezone', 'Africa/Lagos'))->format('d/m/Y H:i') . ' (office clock)' : '(not verified)'));
        $this->line('  usable as ............. ' . $emailNote);
        $this->line('  phone on file ......... ' . ($stored === '' ? '(none)' : $stored));
        $this->line('  usable as ............. ' . ($usable ?? 'NOT a usable Nigerian mobile'));
        $this->status('is_otp_verified', (bool) $user->is_otp_verified);
        $this->line('  last code sent by ..... ' . ($otp->channelColumnReady()
            ? ($user->getAttribute('otp_channel') ?: '(none recorded — SMS, or no code yet)')
            : '(users.otp_channel is not deployed)'));
        $this->line('  code on file .......... ' . ($user->otp ? $user->otp . ($otp->hasExpired($user) ? '  (EXPIRED)' : '  (valid)') : '(none)'));
        $this->line('  code sent at .......... ' . ($user->otp_sent_at ? $user->otp_sent_at->copy()->setTimezone(config('phone_verification.timezone', 'Africa/Lagos'))->format('d/m/Y H:i') . ' (office clock)' : '(never)'));

        $this->line('');
        $this->comment('What this account meets at sign-in');

        if ($user->needs_profile_photo) {
            $this->line('  The passport-photo card — that gate comes first, so the confirmation card is not shown yet.');
        } elseif (!$otp->gateEnabled()) {
            $this->line('  Nothing: the gate is off or not deployed.');
        } elseif ($user->is_otp_verified) {
            $this->line('  Nothing: this account has been verified.');
        } elseif ($channel === PhoneOtpService::CHANNEL_EMAIL) {
            $this->line($usableEmail !== ''
                ? '  The confirmation card, offering to send a code to ' . $usableEmail . '.'
                : '  The confirmation card, with no address it can send to — they must type a real one in'
                    . ($otp->channelEnabled(PhoneOtpService::CHANNEL_SMS) ? ' or switch to SMS.' : '.'));
        } else {
            $this->line($usable !== null
                ? '  The confirmation card, offering to send a code to ' . $usable . '.'
                : '  The confirmation card, with no number it can send to — they must type a real one in'
                    . ($otp->channelEnabled(PhoneOtpService::CHANNEL_EMAIL) ? ' or switch to email.' : '.'));
        }

        return self::SUCCESS;
    }

    /**
     * Lift the gate for everybody at once.
     */
    private function handleVerifyAll(): int
    {
        $held = User::query()->where('is_otp_verified', false)->count();

        $this->line('');
        $this->warn('  This marks ' . number_format($held) . ' accounts verified WITHOUT any code being sent.');
        $this->line('  Their numbers stay exactly as they are — including the wrong ones. This is the');
        $this->line('  emergency lever, not a rollout step; to pause the gate instead, set');
        $this->line('  PHONE_VERIFICATION_ENABLED=false, which can be undone without losing anything.');

        if (!$this->option('force') && !$this->confirm('Verify all ' . number_format($held) . ' accounts?', false)) {
            $this->line('  Nothing changed.');

            return self::SUCCESS;
        }

        $changed = User::query()
            ->where('is_otp_verified', false)
            ->update(['is_otp_verified' => true, 'otp' => null]);

        $this->info('  ' . number_format($changed) . ' accounts marked verified.');

        return self::SUCCESS;
    }

    /**
     * Who the gate is holding, and how many of them it can actually reach.
     */
    private function reportPopulation(PhoneOtpService $otp): void
    {
        $this->line('');
        $this->comment('Who is held');

        $total = User::query()->count();
        $active = User::query()->where('is_active', 1)->count();
        $verified = User::query()->where('is_otp_verified', true)->count();

        $this->line('  users ................. ' . number_format($total) . ' (' . number_format($active) . ' active)');
        $this->line('  verified .............. ' . number_format($verified));
        $this->line('  still held ............ ' . number_format($total - $verified));

        // How many of the held accounts could be sent a code right now, without the
        // user having to type a new address in. This is the number that decides
        // whether enabling the gate is a morning of support calls or a fortnight —
        // and it is counted per ROUTE, because the two reach very different halves
        // of the staff list. Email is the default precisely because of this split.
        $byEmail = 0;
        $bySms = 0;
        $unreachable = [];

        User::query()
            ->where('is_active', 1)
            ->where('is_otp_verified', false)
            ->select(['id', 'first_name', 'last_name', 'phone_number', 'email'])
            ->chunkById(500, function ($chunk) use ($otp, &$byEmail, &$bySms, &$unreachable) {
                foreach ($chunk as $user) {
                    $email = $otp->emailForCard($user) !== '';
                    $sms = BulkSmsNgService::normalizeNumber((string) ($user->phone_number ?? '')) !== null;

                    if ($email) {
                        $byEmail++;
                    }

                    if ($sms) {
                        $bySms++;
                    }

                    if (!$email && !$sms && count($unreachable) < 5000) {
                        $unreachable[] = $user;
                    }
                }
            });

        $this->line('  held, emailable now ... ' . number_format($byEmail) . '  (a real address is on file)');
        $this->line('  held, textable now .... ' . number_format($bySms) . '  (a usable Nigerian mobile is on file)');
        $this->line('  held, neither ......... ' . number_format(count($unreachable)) . '  (must type a real address into the card first)');

        $limit = max(0, (int) $this->option('limit'));

        if ($limit > 0 && $unreachable) {
            $this->line('');
            $this->line('  First ' . min($limit, count($unreachable)) . ' with neither a mailbox nor a usable number:');
            foreach (array_slice($unreachable, 0, $limit) as $user) {
                $this->line(sprintf(
                    '    %-6s %-28s %-34s %s',
                    $user->id,
                    mb_substr($user->name, 0, 28),
                    mb_substr((string) ($user->email ?: '(no email)'), 0, 34),
                    ($user->phone_number ?: '(no number)')
                ));
            }
        }
    }

    /**
     * The mailer, because the default route now runs through it.
     *
     * MAIL_* lives in .env, and .env does not travel with a code upload — so the
     * single most likely state of a freshly deployed server is a mailer pointed
     * at nothing, which turns the card's default route into a dead end. There is
     * no way to prove delivery from here; this reports the settings and names the
     * command that actually sends something.
     */
    private function reportMailer(): void
    {
        $this->line('');
        $this->comment('Email');

        $mailer = (string) config('mail.default', '');
        $host = (string) config('mail.mailers.' . $mailer . '.host', '');
        $username = (string) config('mail.mailers.' . $mailer . '.username', '');
        $from = (string) config('mail.from.address', '');

        $this->line('  transport ............. ' . ($mailer ?: '(not set)'));
        $this->line('  host .................. ' . ($host ?: '(not set)'));
        $this->line('  username .............. ' . ($username ?: '(not set)'));
        $this->line('  from .................. ' . ($from ?: '(not set)'));

        if ($mailer === 'smtp' && ($host === '' || $username === '')) {
            $this->warn('  SMTP is selected but not configured. Codes cannot be emailed on this server —');
            $this->line('  .env does not travel with a code upload, which is the usual cause.');
        }

        $this->line('  Prove it end to end with: php artisan email:test you@yourdomain.com');
        $this->line('  NOTE: codes are sent synchronously, NOT queued — a queue worker is not needed');
        $this->line('        and a stopped one cannot hold anybody up.');
    }

    /**
     * The wallet, because an empty one is the single most likely reason an SMS
     * code never arrives — and it fails silently from the user's side.
     */
    private function reportWallet(BulkSmsNgService $gateway): void
    {
        $this->line('');
        $this->comment('Gateway');

        $email = config('services.bulk_sms_ng.email');

        if (!$email) {
            $this->line('  BULK_SMS_NG_EMAIL / PASSWORD are not set on this server, so no code can be');
            $this->line('  sent at all. .env does not travel with a code upload — that is the usual cause.');

            return;
        }

        $balance = $gateway->balance();

        $this->line('  account ............... ' . $email);
        $this->line('  sender ID ............. ' . config('services.bulk_sms_ng.sender', 'SPAS'));
        $this->line('  balance ............... ' . ($balance === null ? 'could not be read' : '₦' . $balance));
        $this->line('  route ................. promotional (transactional is not enabled on this account)');
        $this->line('  NOTE: promotional traffic is held by the telcos between 19:45 and 08:00, and');
        $this->line('        never reaches a DND-blocked handset. Both look like "no code arrived".');
    }

    private function status(string $label, $value): void
    {
        $on = (bool) $value;
        $this->line(sprintf('  %s %s %s', str_pad($label, 40, '.'), $on ? '[ on ]' : '[ off ]', ''));
    }
}
