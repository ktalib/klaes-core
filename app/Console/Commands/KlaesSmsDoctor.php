<?php

namespace App\Console\Commands;

use App\Models\SmsDispatchLog;
use App\Models\SmsSetting;
use App\Services\BulkSmsNgService;
use App\Services\Sms\ApplicantPhoneResolver;
use App\Services\Sms\KlaesSmsDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Explains why the transactional SMS are, or are not, sending on this machine.
 *
 * Every dependency this feature has can be absent on a deployed server without
 * anything visibly breaking: .env does not travel with a code upload so the
 * gateway credentials may be missing; the tables are created by a hand-run SQL
 * script so they may not exist; the wallet may be empty; and every message ships
 * switched off. Each of those looks identical from the outside -- nothing
 * arrives -- so this command names which one it is.
 *
 * It sends nothing unless --send is given.
 */
class KlaesSmsDoctor extends Command
{
    protected $signature = 'klaes:sms-doctor
                            {--key= : Show only this message}
                            {--file-number= : Show which phone number this file would be texted on}
                            {--send= : Send a real test message to this number (costs credit)}';

    protected $description = 'Check why the transactional SMS are or are not sending on this machine';

    public function handle(KlaesSmsDispatcher $dispatcher, ApplicantPhoneResolver $phones): int
    {
        $this->line('');
        $this->line('<options=bold>KLAES transactional SMS</>');
        $this->line('');

        $this->reportGateway();
        $this->reportTables();
        $this->reportMessages($dispatcher);
        $this->reportTraffic();

        if ($this->option('file-number')) {
            $this->reportFileNumber($phones, (string) $this->option('file-number'));
        }

        if ($this->option('send')) {
            return $this->sendTest((string) $this->option('send'));
        }

        return self::SUCCESS;
    }

    private function reportGateway(): void
    {
        $this->line('<options=bold>Gateway</> (Bulk-SMS.ng, promotional route)');

        $email = config('services.bulk_sms_ng.email');
        $password = config('services.bulk_sms_ng.password');
        $sender = config('services.bulk_sms_ng.sender');

        if (!$email || !$password) {
            // The single most likely cause on a freshly deployed server.
            $this->line('  credentials  <error>MISSING</error> — set BULK_SMS_NG_EMAIL and BULK_SMS_NG_PASSWORD in .env.');
            $this->line('               .env is gitignored, so a code upload does not carry them across.');
            $this->line('');

            return;
        }

        $this->line('  credentials  <info>present</info> (' . $email . ')');
        $this->line('  sender ID    ' . ($sender ?: '<comment>not set</comment>'));

        $balance = (new BulkSmsNgService())->balance();
        $this->line('  wallet       ' . ($balance !== null
            ? '<info>' . $balance . '</info>'
            : '<error>could not be read</error>'));

        $this->line('');
        $this->line('  <comment>Note:</comment> this account has only the promotional route, so messages do not');
        $this->line('  reach DND-blocked handsets, and anything sent 19:45–08:00 is held until 08:30.');
        $this->line('');
    }

    private function reportTables(): void
    {
        $this->line('<options=bold>Tables</> (SQL Server)');

        foreach (['sms_settings', 'sms_dispatch_logs'] as $table) {
            $present = false;

            try {
                $present = Schema::connection('sqlsrv')->hasTable($table);
            } catch (\Throwable $e) {
                // Reported as missing below.
            }

            $this->line('  ' . str_pad($table, 20) . ($present
                ? '<info>present</info>'
                : '<error>MISSING</error> — run database/sql/2026_09_06_create_sms_tables.sql'));
        }

        $this->line('');
    }

    private function reportMessages(KlaesSmsDispatcher $dispatcher): void
    {
        $only = $this->option('key');

        $this->line('<options=bold>Messages</>');

        $master = SmsSetting::isEnabled(SmsSetting::KEY_MASTER);
        $this->line('  master switch  ' . ($master
            ? '<info>ON</info>'
            : '<comment>OFF</comment> — every message below is silenced regardless of its own setting'));
        $this->line('');

        $rows = [];

        foreach (SmsSetting::catalogue() as $key => $definition) {
            if ($only && $key !== $only) {
                continue;
            }

            $enabled = SmsSetting::isEnabled($key);
            $preview = $dispatcher->preview($key);

            $rows[] = [
                $key,
                $definition['label'] ?? $key,
                $enabled ? 'ON' : 'off',
                // The name the recipient sees. An unregistered sender ID is
                // accepted, billed and never delivered, so it belongs in any
                // report about why messages are or are not arriving.
                SmsSetting::senderFor($key) ?: config('services.bulk_sms_ng.sender'),
                // Pages, because these wordings are long and this is what the
                // traffic will actually cost.
                $preview['pages'] ?: '—',
                $preview['characters'] ?: '—',
            ];
        }

        if (empty($rows)) {
            $this->line('  <error>No message matches --key=' . $only . '</error>');
            $this->line('');

            return;
        }

        $this->table(['Key', 'Message', 'State', 'Sent as', 'Pages', 'Chars'], $rows);
        $this->line('');
    }

    private function reportTraffic(): void
    {
        $this->line('<options=bold>Today</>');

        try {
            $counts = SmsDispatchLog::query()
                ->whereDate('created_at', now()->toDateString())
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status');

            if ($counts->isEmpty()) {
                $this->line('  nothing sent, failed or skipped today');
            } else {
                foreach ($counts as $status => $total) {
                    $this->line('  ' . str_pad((string) $status, 12) . $total);
                }
            }

            /*
             | The most recent thing that went wrong, in the gateway's own words.
             | A 'skipped' row is included on purpose: "no phone number on file"
             | is the commonest reason a message never arrives, and it is not a
             | failure anybody would otherwise go looking for.
             */
            $last = SmsDispatchLog::query()
                ->whereIn('status', [SmsDispatchLog::STATUS_FAILED, SmsDispatchLog::STATUS_SKIPPED])
                ->orderByDesc('id')
                ->first();

            if ($last) {
                $this->line('');
                $this->line('  last problem  [' . $last->status . '] ' . $last->message_key
                    . ($last->gateway_code ? ' (code ' . $last->gateway_code . ')' : ''));
                $this->line('                ' . ($last->failure_reason ?: 'no reason recorded'));
            }
        } catch (\Throwable $e) {
            $this->line('  <error>could not read sms_dispatch_logs</error> — ' . $e->getMessage());
        }

        $this->line('');
    }

    /**
     * Which number one file would actually be texted on, and whether we trust it.
     *
     * This is the question worth asking before switching a message on, because
     * file_indexings.phone is shared: one number in it sits on 1,211 files.
     */
    private function reportFileNumber(ApplicantPhoneResolver $phones, string $fileNumber): void
    {
        $this->line('<options=bold>File</> ' . $fileNumber);

        $verdict = $phones->forFileNumber($fileNumber);

        if ($verdict['phone'] === null) {
            $this->line('  <comment>No usable number.</comment> ' . ($verdict['raw']
                ? 'Found "' . $verdict['raw'] . '", which is not a Nigerian mobile.'
                : 'Nothing on file.'));
            $this->line('  Messages for this file would be recorded as skipped, not failed.');
            $this->line('');

            return;
        }

        $this->line('  number   <info>' . $verdict['phone'] . '</info>');
        $this->line('  source   ' . $verdict['source']);
        $this->line('  on       ' . $verdict['shared_count'] . ' file(s)');

        if ($verdict['shared']) {
            $this->line('  <comment>SHARED</comment> — this looks like a clerk or agent, not the applicant.');
            $this->line('  Messages are held unless an officer confirms the number on the form.');
        }

        $this->line('');
    }

    /**
     * Send one real message, to prove the whole path works end to end.
     *
     * Deliberately routed through the dispatcher's gateway rather than a message
     * key, so it works while every message is still switched off -- which is the
     * state you want to test from.
     */
    private function sendTest(string $number): int
    {
        $this->line('<options=bold>Test send</> to ' . $number);

        $normalised = BulkSmsNgService::normalizeNumber($number);

        if ($normalised === null) {
            $this->error('  "' . $number . '" is not a usable Nigerian mobile number.');

            return self::FAILURE;
        }

        $gateway = new BulkSmsNgService();
        $sent = $gateway->send(
            $normalised,
            'KLAES test message from the SMS doctor. No action is needed.'
        );

        if ($sent) {
            $this->info('  Accepted by the gateway (code ' . ($gateway->lastStatusCode() ?: 'n/a') . ').');
            $this->line('  Accepted is not delivered: on the promotional route a DND-blocked');
            $this->line('  handset receives nothing, and code 609 means it is held until 08:30.');

            return self::SUCCESS;
        }

        $this->error('  Not sent (code ' . ($gateway->lastStatusCode() ?: 'n/a') . ').');
        $this->line('  ' . ($gateway->lastFailureReason() ?: 'No reason given by the gateway.'));

        return self::FAILURE;
    }
}
