<?php

namespace App\Console\Commands;

use App\Models\SpaNotice;
use App\Services\BulkSmsNgService;
use Illuminate\Console\Command;

/**
 * Diagnoses why notice SMS works on one machine and not another.
 *
 * Written because the failure is environmental, not logical: the same code
 * sends fine locally and silently fails in production, and the UI could only
 * say "SMS could not be sent". Each check below is a thing that differs
 * between the two boxes, ordered cheapest-to-check first.
 *
 * SPAS notices go out through Bulk-SMS.ng. They used to go through BetaSMS,
 * whose API is plain HTTP on port 80 — which the production server cannot make
 * outbound connections on, so every notice there died with a connection
 * timeout. Bulk-SMS.ng is https, so it reaches the internet on the port that
 * box actually has open.
 */
class SpaSmsDoctor extends Command
{
    protected $signature = 'spa:sms-doctor {--phone= : Also send a live test SMS to this number}';

    protected $description = 'Check why SPAS notice SMS is or is not sending on this machine';

    public function handle(): int
    {
        $this->info('SPAS SMS diagnostics — '.config('app.env').' @ '.gethostname());
        $this->line('provider: Bulk-SMS.ng (account.bulk-sms.ng)');
        $this->newLine();

        $ok = true;

        // 1. Credentials. .env is not deployed by a file upload (it is gitignored),
        //    so this is the single most likely difference on a fresh production box.
        $email    = config('services.bulk_sms_ng.email');
        $password = config('services.bulk_sms_ng.password');
        $sender   = config('services.bulk_sms_ng.sender');

        $this->line('1. Credentials (config/services.php ← .env)');
        $this->line('   BULK_SMS_NG_EMAIL    : '.($email ? $this->mask($email) : '<fg=red>NOT SET</>'));
        $this->line('   BULK_SMS_NG_PASSWORD : '.($password ? '<fg=green>set ('.strlen($password).' chars)</>' : '<fg=red>NOT SET</>'));
        $this->line('   BULK_SMS_NG_SENDER   : '.($sender ?: '<fg=red>NOT SET</>'));

        if (!$email || !$password) {
            $this->newLine();
            $this->error('   → Add these to this server\'s .env:');
            $this->line('     BULK_SMS_NG_EMAIL=...');
            $this->line('     BULK_SMS_NG_PASSWORD=...');
            $this->line('     BULK_SMS_NG_SENDER=SPAS');
            $this->line('     .env is gitignored, so it is NOT copied by a code upload — it must be edited on the server.');
            $ok = false;
        }

        // 2. Stale config cache: values can be correct in .env yet absent from the
        //    cached config the app actually reads.
        $this->newLine();
        $this->line('2. Config cache');
        $cached = file_exists($this->laravel->getCachedConfigPath());
        if ($cached) {
            $this->line('   <fg=yellow>config is cached</> — if you just edited .env, run: php artisan config:clear');
        } else {
            $this->line('   <fg=green>not cached</> — .env is read live');
        }

        // 3. Outbound HTTPS. The whole reason for this provider: the previous one
        //    needed outbound :80, which production does not have.
        $this->newLine();
        $this->line('3. Outbound connection to account.bulk-sms.ng (https, :443)');
        [$reachable, $detail] = $this->probeConnect('https://account.bulk-sms.ng/api/promotional/balance');
        if ($reachable) {
            $this->line('   <fg=green>reachable</> ('.$detail.')');
        } else {
            $this->error('   → UNREACHABLE: '.$detail);
            $this->line('     This server cannot reach the SMS provider. Check outbound :443 and DNS.');
            $this->line('     Public IP of this server: '.$this->publicIp());
            $ok = false;
        }

        // 4. Whether the gateway accepts these credentials, and what is left to
        //    spend. Reading the balance sends nothing and costs nothing, and it
        //    fails the same way bad credentials would (601).
        if ($email && $password && $reachable) {
            $this->newLine();
            $this->line('4. Account check (no SMS is sent)');
            $balance = app(BulkSmsNgService::class)->balance();

            if ($balance === null) {
                $this->error('   → could not read the balance — credentials rejected or the API changed.');
                $ok = false;
            } else {
                $this->line('   <fg=green>credentials accepted</> — balance: ₦'.$balance);
                // A notice is 2 pages; an empty wallet fails sends with 604 and
                // is invisible until someone tries to serve one.
                if ((float) $balance < 100) {
                    $this->line('   <fg=yellow>balance is low — top up before a serving run</>');
                }
            }
        }

        // 5. The wording. This provider has no equivalent of the previous one's
        //    content filter, but page count still drives what a send costs.
        $this->newLine();
        $this->line('5. Notice wording');
        foreach (['first', 'second'] as $type) {
            $body = SpaNotice::smsBody($type);
            $this->line(sprintf('   %-6s serve: %d chars, %d page(s)',
                $type,
                strlen($body),
                (int) ceil(mb_strlen($body) / 160)
            ));
        }

        // 6. Optional live send.
        if ($phone = $this->option('phone')) {
            $this->newLine();
            $this->line('6. Live test SMS to '.$phone);
            $sms  = app(BulkSmsNgService::class);
            $sent = $sms->send($phone, 'KLAES diagnostic message from '.config('app.env').'. The SPAS message route is working.');
            if ($sent) {
                $this->line('   <fg=green>accepted by the gateway</> — check the handset');
            } else {
                $this->error('   → NOT sent: '.$sms->lastFailureReason().' (code '.($sms->lastStatusCode() ?: 'none').')');
                $ok = false;
            }
        } else {
            $this->newLine();
            $this->line('6. Live test skipped — re-run with --phone=08012345678 to send one');
        }

        $this->newLine();
        $this->line($ok ? '<fg=green>No blocking problem found on this machine.</>' : '<fg=red>Problems found — see the arrows above.</>');

        return $ok ? 0 : 1;
    }

    /**
     * Can this machine open a connection to that URL at all?
     *
     * Any HTTP status counts: we are testing whether the network carries the
     * request, not what the endpoint thinks of it.
     *
     * @return array{0:bool, 1:string}
     */
    private function probeConnect(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $err ? [false, $err] : [true, 'HTTP '.$code];
    }

    /**
     * The address this server appears as on the internet, for a whitelist
     * request. Sends nothing about the server but the request itself.
     */
    private function publicIp(): string
    {
        $ch = curl_init('https://api.ipify.org');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $ip  = trim((string) curl_exec($ch));
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return '<fg=yellow>could not determine</>'.($err ? ' ('.$err.')' : '');
        }

        return $ip;
    }

    /** Show enough of the value to recognise it, not enough to reuse it. */
    private function mask(string $value): string
    {
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 3).str_repeat('*', max(3, strlen($value) - 6)).substr($value, -3);
    }
}
