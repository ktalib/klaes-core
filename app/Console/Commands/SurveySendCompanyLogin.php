<?php

namespace App\Console\Commands;

use App\Services\Survey\SurveyCompanyLogins;
use App\Services\Survey\SurveyCompanyRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Make an approved company's MLPP-SG portal login and send it once (user, 2026-11-10: "we will send login only
 * for the demo, when I'm ready"). For a company approved before logins existed; a company approved since gets its
 * login at approval. --phone / --email send it to those contacts instead of the company's own (they also become the
 * login's contacts, so the sign-in codes go there); the company record is not changed. Refuses a company that
 * already has a login (the details are sent once) unless --reissue: a new temporary password to the login's own
 * contacts and any --also-phone / --also-email (user, 2026-11-10). The password is never printed.
 */
class SurveySendCompanyLogin extends Command
{
    protected $signature = 'survey:send-company-login {company : survey_companies id}
        {--phone= : Send to this phone instead of the company\'s}
        {--email= : Send to this email instead of the company\'s}
        {--reissue : The company already has a login: give it a new temporary password and send that}
        {--also-phone=* : With --reissue, also text the new details to this phone}
        {--also-email=* : With --reissue, also email the new details here}';

    protected $description = 'Make an approved company\'s MLPP-SG portal login and send it once by SMS and email';

    public function handle(SurveyCompanyLogins $logins): int
    {
        $company = DB::connection('sqlsrv')->table('survey_companies')->where('id', (int) $this->argument('company'))->first();
        if (! $company) {
            $this->error('No such company.');
            return self::FAILURE;
        }
        $approved = $company->is_active && ($company->status === SurveyCompanyRegistry::APPROVED || $company->status === null);
        if (! $approved) {
            $this->error("{$company->name} is not approved.");
            return self::FAILURE;
        }
        if ($existing = $logins->for((int) $company->id)) {
            if (! $this->option('reissue')) {
                $this->error("{$company->name} already has its login ({$existing->username}); the details are sent once. Use --reissue for a new password.");
                return self::FAILURE;
            }

            return $this->reissue($logins, $company, $existing);
        }

        $to = clone $company;
        if ($this->option('phone')) $to->phone = (string) $this->option('phone');
        if ($this->option('email')) $to->email = (string) $this->option('email');

        $made = DB::connection('sqlsrv')->transaction(fn () => $logins->create($to));
        if (! $made) {
            $this->error("{$company->name} has no phone or email to send the login to.");
            return self::FAILURE;
        }
        $sent = $logins->send($made['account'], $made['password']);

        $this->info("{$company->name}: login {$made['account']->registration_id}, username {$made['account']->username}.");
        $this->line('SMS to ' . $made['account']->phone . ': ' . ($sent['sms'] ? 'sent' : 'NOT sent'));
        $this->line('Email to ' . $made['account']->email . ': ' . ($sent['email'] ? 'sent' : 'NOT sent'));

        return $sent['sms'] || $sent['email'] ? self::SUCCESS : self::FAILURE;
    }

    /** A new temporary password, to the login's own contacts and any --also-* ones. */
    private function reissue(SurveyCompanyLogins $logins, object $company, $account): int
    {
        $password = $logins->reissue($account);
        $this->info("{$company->name}: new temporary password for {$account->username}.");
        $any = false;
        $report = function (string $who, array $sent) use (&$any) {
            $any = $any || $sent['sms'] || $sent['email'];
            $this->line("{$who}: SMS " . ($sent['sms'] ? 'sent' : 'NOT sent') . ', email ' . ($sent['email'] ? 'sent' : 'NOT sent'));
        };
        $report("Login's own ({$account->phone}, {$account->email})", $logins->send($account, $password));

        $phones = array_values((array) $this->option('also-phone'));
        $emails = array_values((array) $this->option('also-email'));
        for ($i = 0, $n = max(count($phones), count($emails)); $i < $n; $i++) {
            $p = $phones[$i] ?? null; $e = $emails[$i] ?? null;
            $report('Also (' . trim(($p ?? '') . ', ' . ($e ?? ''), ', ') . ')',
                $logins->send($account, $password, $p ?? '', $e ?? ''));
        }

        return $any ? self::SUCCESS : self::FAILURE;
    }
}
