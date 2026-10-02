<?php

namespace App\Console\Commands;

use App\Services\BulkSmsNgService;
use Illuminate\Console\Command;

/**
 * Find out which message wordings the SMS gateway will actually accept.
 *
 * Points at Bulk-SMS.ng, the provider LAAS sends through. Only its promotional
 * route is enabled on this account, and gateways on that route are the ones
 * that filter on wording — so knowing which phrasings survive is worth being
 * able to check rather than guess. The variants below were built against
 * BetaSMS's 1713 content filter and remain the useful set to compare.
 *
 * This sends REAL messages and spends REAL credit, so it asks first, sends one
 * variant at a time with a pause between, and stops early on any failure that
 * is not about the message itself (a bad number or an empty account will not
 * become truer on the fourth attempt).
 */
class LaasSmsProbe extends Command
{
    protected $signature = 'laas:sms-probe
                            {phone : Number to send the probes to, e.g. 08031234567}
                            {--only= : Run a single variant by name}
                            {--list : Show the variants without sending anything}
                            {--delay=6 : Seconds to wait between sends}';

    protected $description = 'Probe which SMS wordings the gateway accepts (sends real messages)';

    /**
     * Ordered plainest-last. Each isolates one suspected trigger so a pass/fail
     * pair actually tells you something: `otp_classic` differs from `no_code_word`
     * only in the word "code", and so on.
     */
    private function variants(string $code): array
    {
        $mins = 10;

        return [
            'plain_digits'   => "KLAES LAAS confirmation: {$code}",
            'no_code_word'   => "KLAES LAAS: {$code} is your confirmation number for the phone change on your land application account. It is valid for {$mins} minutes.",
            'otp_classic'    => "KLAES LAAS: your phone change code is {$code}. It expires in {$mins} minutes. If you did not request this, ignore this message.",
            'word_code'      => "KLAES LAAS: your code is {$code}.",
            'word_otp'       => "KLAES LAAS: your OTP is {$code}.",
            'word_verify'    => "KLAES LAAS: verify your number with {$code}.",
            'word_notice'    => "KLAES LAAS: this is a notice that your number is changing.",
            'no_digits'      => "KLAES LAAS: your phone change request has been received.",

            // The live workflow wordings. Observed on BetaSMS: `wf_submitted`
            // was accepted while `wf_approved` and `wf_fileno` were both refused
            // — the suspects are "approved", "assigned" and "quote", the
            // vocabulary of loan and prize spam. These five isolate them.
            'wf_submitted'   => "KLAES LAAS: your land allocation application LAAS-2026-000001 has been received and processing has started. You will be updated at each stage.",
            'wf_approved'    => "KLAES LAAS: your application LAAS-2026-000001 has been approved by the Director. Your file number will be assigned shortly.",
            'wf_fileno'      => "KLAES LAAS: your application LAAS-2026-000001 has been assigned File Number AG-2026-6. Please quote this number in all correspondence.",
            'wf_fallback'    => "KLAES LAAS: there is an update on your application LAAS-2026-000001. Please sign in to the portal to see it.",
            'wf_fileno_alt'  => "KLAES LAAS: AG-2026-6 is the file number for your application LAAS-2026-000001.",
        ];
    }

    public function handle(BulkSmsNgService $sms): int
    {
        $code     = (string) random_int(100000, 999999);
        $variants = $this->variants($code);

        if ($only = $this->option('only')) {
            if (!isset($variants[$only])) {
                $this->error("Unknown variant '{$only}'. Known: " . implode(', ', array_keys($variants)));

                return self::FAILURE;
            }
            $variants = [$only => $variants[$only]];
        }

        if ($this->option('list')) {
            foreach ($variants as $name => $message) {
                $this->line("<info>{$name}</info>");
                $this->line("  {$message}");
            }

            return self::SUCCESS;
        }

        $phone = (string) $this->argument('phone');
        $count = count($variants);

        $this->warn("This sends {$count} REAL SMS to {$phone} and spends real credit.");

        if (!$this->confirm('Continue?', false)) {
            $this->info('Nothing sent.');

            return self::SUCCESS;
        }

        $delay   = max(0, (int) $this->option('delay'));
        $results = [];
        $i       = 0;

        foreach ($variants as $name => $message) {
            $i++;

            if ($i > 1 && $delay > 0) {
                sleep($delay);
            }

            $ok     = $sms->send($phone, $message);
            $status = $sms->lastStatusCode() ?? 'no response';

            $results[] = [$name, $ok ? 'ACCEPTED' : 'refused', $status, mb_strimwidth($message, 0, 58, '…')];
            $this->line(sprintf('  %-14s %-9s %s', $name, $ok ? 'ACCEPTED' : 'refused', $status));

            // Anything other than a content refusal means the account or the
            // number is the problem; more wording will not fix it.
            if (!$ok && $status !== BulkSmsNgService::CODE_REWORDABLE) {
                $this->newLine();
                $this->error("Stopped: status {$status} is not about the message. Check the account balance, sender ID, and the number.");
                break;
            }
        }

        $this->newLine();
        $this->table(['variant', 'result', 'status', 'message'], $results);

        $accepted = array_values(array_filter($results, fn ($r) => $r[1] === 'ACCEPTED'));

        if ($accepted) {
            $this->info('Accepted wordings: ' . implode(', ', array_column($accepted, 0)));
            $this->line('Use the shortest accepted variant as the primary template in LaasProfileController::sendCode().');
        } else {
            $this->warn('Nothing was accepted. The gateway is refusing this sender or account outright rather than specific words — take it up with Bulk-SMS.ng.');
        }

        return self::SUCCESS;
    }
}
