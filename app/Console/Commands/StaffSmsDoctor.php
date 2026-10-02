<?php

namespace App\Console\Commands;

use App\Models\StaffSmsLog;
use App\Models\User;
use App\Services\BulkSmsNgService;
use App\Services\StaffAttendanceSmsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Why is the attendance SMS not going out on this server?
 *
 * Every dependency this feature has can be absent on a freshly deployed box
 * without anything visibly breaking: .env is gitignored so the gateway
 * credentials do not travel with a code upload, the table is created by a
 * migration whose ledger lives on a different database, and three quarters of
 * staff have no phone number on file. This prints the answer instead of leaving
 * it to be guessed from an empty log.
 */
class StaffSmsDoctor extends Command
{
    protected $signature = 'staff:sms-doctor
        {--user= : Check one user id and explain what they would receive today}
        {--send= : Actually send a test message to this user id (spends money)}
        {--to= : Send the test to a raw phone number instead of a user id (spends money)}
        {--route= : Override services.bulk_sms_ng.gateway for this test send}
        {--sender= : Override the sender ID for this test send}';

    protected $description = 'Diagnose the staff login/logout SMS: config, credentials, table, recipients.';

    public function handle(StaffAttendanceSmsService $service, BulkSmsNgService $gateway): int
    {
        $tz = config('staff_sms.timezone', 'Africa/Lagos');
        $now = Carbon::now($tz);

        $this->line('');
        $this->info('Staff attendance SMS — diagnosis');
        $this->line(str_repeat('=', 60));

        // ── Clock ────────────────────────────────────────────────────────────
        $this->line('');
        $this->comment('Clock');
        $this->line('  app.timezone .......... ' . config('app.timezone') . '  (now ' . Carbon::now(config('app.timezone'))->format('H:i') . ')');
        $this->line('  staff_sms.timezone .... ' . $tz . '  (now ' . $now->format('H:i') . ')');
        if (config('app.timezone') !== $tz) {
            $this->line('  NOTE: shift ends and the once-a-day date are judged on the ' . $tz . ' clock,');
            $this->line('        which is what the office wall clock shows.');
        }

        // ── Switches ─────────────────────────────────────────────────────────
        $this->line('');
        $this->comment('Switches');
        $this->status('staff_sms.enabled', config('staff_sms.enabled'));
        $this->status('login.enabled', config('staff_sms.login.enabled'));
        $this->status('logout.enabled', config('staff_sms.logout.enabled'));
        $this->line('  default shift end ..... ' . config('staff_sms.logout.default_shift_end'));

        // ── Gateway ──────────────────────────────────────────────────────────
        $this->line('');
        $this->comment('Gateway (Bulk-SMS.ng)');
        $email = config('services.bulk_sms_ng.email');
        $password = config('services.bulk_sms_ng.password');
        $this->line('  sender id ............. ' . config('services.bulk_sms_ng.sender'));
        $this->status('BULK_SMS_NG_EMAIL', (bool) $email, $email ? (string) $email : 'MISSING — add it to .env on this server');
        $this->status('BULK_SMS_NG_PASSWORD', (bool) $password, $password ? 'set' : 'MISSING — add it to .env on this server');

        if ($email && $password) {
            $balance = $gateway->balance();
            $this->line('  wallet balance ........ ' . ($balance !== null ? $balance : 'could not be read (see the log for the gateway error)'));
        }

        // ── Storage ──────────────────────────────────────────────────────────
        $this->line('');
        $this->comment('Storage');
        $hasTable = Schema::connection('sqlsrv')->hasTable('staff_sms_logs');
        $this->status('staff_sms_logs table', $hasTable, $hasTable
            ? 'present'
            : 'MISSING — run database/sql/2026_09_04_create_staff_sms_logs.sql against SQL Server');

        if (!$hasTable) {
            $this->line('');
            $this->error('Nothing can be sent until that table exists: it is the once-a-day throttle.');

            return self::FAILURE;
        }

        // ── Recipients ───────────────────────────────────────────────────────
        $this->line('');
        $this->comment('Recipients');
        $active = User::where('is_active', 1)->count();
        $withPhone = 0;
        $unusable = 0;
        User::where('is_active', 1)
            ->whereNotNull('phone_number')
            ->where('phone_number', '<>', '')
            ->select(['id', 'phone_number'])
            ->chunkById(500, function ($chunk) use (&$withPhone, &$unusable) {
                foreach ($chunk as $row) {
                    BulkSmsNgService::normalizeNumber((string) $row->phone_number) === null
                        ? $unusable++
                        : $withPhone++;
                }
            });

        $this->line('  active staff .......... ' . $active);
        $this->line('  reachable by SMS ...... ' . $withPhone);
        $this->line('  number unusable ....... ' . $unusable . ' (present but not a Nigerian mobile)');
        $this->line('  no number on file ..... ' . max(0, $active - $withPhone - $unusable));
        $this->line('  est. messages/day ..... ' . ($withPhone * 2) . ' (one sign-in + one sign-out each)');

        // ── Today ────────────────────────────────────────────────────────────
        $this->line('');
        $this->comment('Today (' . $now->toDateString() . ')');
        foreach ([StaffSmsLog::TYPE_LOGIN, StaffSmsLog::TYPE_LOGOUT] as $type) {
            $rows = StaffSmsLog::whereDate('sent_on', $now->toDateString())->where('sms_type', $type);
            $this->line(sprintf(
                '  %-7s sent %d, failed %d, pending %d',
                $type,
                (clone $rows)->where('status', StaffSmsLog::STATUS_SENT)->count(),
                (clone $rows)->where('status', StaffSmsLog::STATUS_FAILED)->count(),
                (clone $rows)->where('status', StaffSmsLog::STATUS_PENDING)->count()
            ));
        }

        $lastFailure = StaffSmsLog::where('status', StaffSmsLog::STATUS_FAILED)
            ->whereNotNull('failure_reason')
            ->latest('id')
            ->first();

        if ($lastFailure) {
            $this->line('');
            $this->warn('  Most recent failure (user ' . $lastFailure->user_id . ', ' . $lastFailure->sms_type . '):');
            $this->line('    ' . $lastFailure->failure_reason);
        }

        // ── Raw test send ────────────────────────────────────────────────────
        // For proving which route/sender the gateway actually honours: the name
        // that arrives on the handset is the only evidence, and it cannot be
        // read back from the API.
        if ($to = $this->option('to')) {
            $route = $this->option('route') ?: config('services.bulk_sms_ng.gateway', '1');
            $sender = $this->option('sender') ?: config('services.bulk_sms_ng.sender');
            $number = BulkSmsNgService::normalizeNumber((string) $to);

            $this->line('');
            $this->comment('Test send');

            if ($number === null) {
                $this->error('  "' . $to . '" is not a usable Nigerian mobile number.');

                return self::FAILURE;
            }

            $this->line('  to .................... ' . $number);
            $this->line('  sender id ............. ' . $sender);
            $this->line('  route (smsgateway) .... ' . $route);

            if (!$this->confirm('Send one real SMS now? This spends wallet credit.')) {
                return self::SUCCESS;
            }

            config([
                'services.bulk_sms_ng.gateway' => (string) $route,
                'services.bulk_sms_ng.sender' => $sender,
            ]);

            $text = 'KLAES test: checking which name this message arrives from. Route ' . $route . '.';

            if ($gateway->send($number, $text)) {
                $this->info('  Accepted by the gateway.');
                $this->line('  Now CHECK THE HANDSET: the sender name that actually arrives is the answer.');
                $this->line('  The full gateway response is in storage/logs — look for "BulkSmsNgService: send result".');
            } else {
                $this->error('  Refused — ' . ($gateway->lastFailureReason() ?: 'no reason given'));
            }

            return self::SUCCESS;
        }

        // ── One user ─────────────────────────────────────────────────────────
        $userId = $this->option('user') ?: $this->option('send');

        if ($userId) {
            $user = User::find((int) $userId);

            if (!$user) {
                $this->line('');
                $this->error('No user with id ' . $userId);

                return self::FAILURE;
            }

            $shift = $service->shiftFor($user);
            $phone = BulkSmsNgService::normalizeNumber((string) ($user->phone_number ?? ''));

            $this->line('');
            $this->comment('User #' . $user->id . ' — ' . $user->name);
            $this->line('  phone ................. ' . ($phone ?: 'none usable ("' . $user->phone_number . '")'));
            $this->line('  shift ................. ' . $shift['label'] . '  (ends ' . $shift['end'] . ')');
            $this->line('  shift ended yet? ...... ' . ($service->shiftHasEnded($user, $now) ? 'yes' : 'no — a sign-out now would send nothing'));
            $this->line('  sign-in sent today? ... ' . ($service->alreadySentToday($user, StaffSmsLog::TYPE_LOGIN, $now) ? 'yes' : 'no'));
            $this->line('  sign-out sent today? .. ' . ($service->alreadySentToday($user, StaffSmsLog::TYPE_LOGOUT, $now) ? 'yes' : 'no'));

            if ($this->option('send')) {
                if (!$this->confirm('Send a real sign-in SMS to ' . $phone . ' now? This spends wallet credit.')) {
                    return self::SUCCESS;
                }

                $sent = $service->sendLoginSms($user, $now);
                $sent
                    ? $this->info('  Sent.')
                    : $this->error('  Not sent — ' . ($gateway->lastFailureReason() ?: 'already sent today, or no usable number.'));
            }
        }

        $this->line('');

        return self::SUCCESS;
    }

    private function status(string $label, $ok, ?string $detail = null): void
    {
        $this->line(sprintf(
            '  %s %s %s',
            str_pad($label, 22, '.'),
            $ok ? '<info>OK</info>' : '<error>NO</error>',
            $detail ? '— ' . $detail : ''
        ));
    }
}
