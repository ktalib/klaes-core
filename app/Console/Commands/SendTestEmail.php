<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Send a test email to verify email configuration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->info("Sending test email to: {$email}");

        try {
            Mail::send('email.test_email_notification', [
                'data' => [
                    'company_logo' => 'logo.png',
                ]
            ], function ($message) use ($email) {
                $message->to($email)
                    ->subject('Test Email - ' . env('APP_NAME'))
                    ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });

            $this->info("✅ Test email sent successfully to: {$email}");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to send test email: " . $e->getMessage());
            return 1;
        }
    }
}
