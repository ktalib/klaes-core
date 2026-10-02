<?php

namespace App\Console\Commands;

use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

/**
 * SendScheduledActivityReports
 *
 * Artisan command to generate and email scheduled activity reports
 * 
 * Usage: php artisan send:scheduled-activity-reports
 * Schedule: Should run daily via Laravel scheduler (e.g., 2:00 AM)
 */
class SendScheduledActivityReports extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'send:scheduled-activity-reports {--force : Force send all due reports} {--dry-run : Simulate without sending}';

    /**
     * The console command description.
     */
    protected $description = 'Generate and email scheduled activity log reports';

    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('Starting scheduled activity reports process...');
        $this->newLine();

        try {
            // Get due reports
            $dueReports = $this->getDueReports($force);

            if ($dueReports->isEmpty()) {
                $this->info('No reports due for generation');
                return Command::SUCCESS;
            }

            $this->info("Found {$dueReports->count()} report(s) due for generation");
            $this->newLine();

            $sent = 0;
            $failed = 0;

            // Process each due report
            foreach ($dueReports as $report) {
                try {
                    $this->info("Processing report ID {$report->id} for user {$report->user->name}...");

                    if (!$dryRun) {
                        // Generate report
                        $result = $this->generateReport($report);

                        if ($result['success']) {
                            // Send email
                            $emailResult = $this->sendReportEmail($report, $result);

                            if ($emailResult) {
                                $this->updateReportSuccess($report);
                                $sent++;
                                $this->line("  ✓ Report sent successfully to {$report->recipients}");
                            } else {
                                $this->updateReportFailure($report, 'Email delivery failed');
                                $failed++;
                                $this->line("  ✗ Failed to send email");
                            }
                        } else {
                            $this->updateReportFailure($report, $result['message'] ?? 'Unknown error');
                            $failed++;
                            $this->line("  ✗ Report generation failed: {$result['message']}");
                        }
                    } else {
                        $this->line("  [DRY-RUN] Would send to {$report->recipients}");
                        $sent++;
                    }

                    $this->newLine();

                } catch (\Exception $e) {
                    $this->error("Error processing report {$report->id}: {$e->getMessage()}");
                    $this->updateReportFailure($report, $e->getMessage());
                    $failed++;
                    $this->newLine();
                }
            }

            // Summary
            $this->newLine();
            $this->info("=== Summary ===");
            $this->info("Reports sent: {$sent}");
            $this->error("Reports failed: {$failed}");

            Log::info("Scheduled activity reports completed", [
                'sent' => $sent,
                'failed' => $failed,
                'dry_run' => $dryRun,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Fatal error: {$e->getMessage()}");
            Log::error("Scheduled reports command failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Get reports due for generation
     */
    private function getDueReports($force = false)
    {
        $query = DB::connection('sqlsrv')
            ->table('scheduled_activity_reports')
            ->where('is_active', true)
            ->with('user');

        if (!$force) {
            $query->where('next_run_at', '<=', now());
        }

        return $query->get();
    }

    /**
     * Generate report for schedule
     */
    private function generateReport($report): array
    {
        $filters = [
            'date_from' => $report->report_date_from ?? now()->subMonth()->toDateString(),
            'date_to' => $report->report_date_to ?? now()->toDateString(),
        ];

        if ($report->filters) {
            $filters = array_merge($filters, json_decode($report->filters, true) ?? []);
        }

        return $this->reportService->generateReport(
            $report->format,
            $filters,
            ['include_charts' => true]
        );
    }

    /**
     * Send report via email
     */
    private function sendReportEmail($report, $reportData): bool
    {
        try {
            $recipients = explode(',', $report->recipients);
            $recipients = array_map('trim', $recipients);

            // Build email
            $subject = "Scheduled Activity Report ({$report->frequency})";
            $message = $this->buildEmailMessage($report, $reportData);

            // Send email
            foreach ($recipients as $email) {
                Mail::raw($message, function ($msg) use ($email, $subject, $reportData) {
                    $msg->to($email)
                        ->subject($subject)
                        ->from(config('mail.from.address'));

                    // Attach report if size is reasonable
                    if ($reportData['size'] < 10 * 1024 * 1024) { // 10MB limit
                        $msg->attach($reportData['path'], [
                            'as' => $reportData['filename'],
                            'mime' => $this->getMimeType($reportData['format']),
                        ]);
                    }
                });
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Error sending report email: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Build email message
     */
    private function buildEmailMessage($report, $reportData): string
    {
        $nextRun = $this->calculateNextRun($report->frequency);

        return <<<EOT
Hello {$report->user->name},

Your scheduled activity report is ready for download.

Report Details:
- Format: {$report->format}
- Generated: {$reportData['generated_at']}
- File: {$reportData['filename']}
- Size: {$this->formatBytes($reportData['size'])}

Schedule: {$report->frequency}
Next Report: {$nextRun->format('Y-m-d H:i')}

If you have any questions about this report, please contact support.

Best regards,
KLAES GIS EDMS
EOT;
    }

    /**
     * Update report with success
     */
    private function updateReportSuccess($report): void
    {
        $nextRun = $this->calculateNextRun($report->frequency);

        DB::connection('sqlsrv')
            ->table('scheduled_activity_reports')
            ->where('id', $report->id)
            ->update([
                'next_run_at' => $nextRun,
                'last_run_at' => now(),
                'run_count' => $report->run_count + 1,
                'last_failure_at' => null,
                'last_failure_reason' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Update report with failure
     */
    private function updateReportFailure($report, $reason): void
    {
        DB::connection('sqlsrv')
            ->table('scheduled_activity_reports')
            ->where('id', $report->id)
            ->update([
                'last_failure_at' => now(),
                'last_failure_reason' => substr($reason, 0, 255),
                'updated_at' => now(),
            ]);
    }

    /**
     * Calculate next run time
     */
    private function calculateNextRun(string $frequency): Carbon
    {
        return match($frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'biweekly' => now()->addWeeks(2),
            'monthly' => now()->addMonth(),
            default => now()->addDay(),
        };
    }

    /**
     * Get MIME type
     */
    private function getMimeType(string $format): string
    {
        return match($format) {
            'pdf' => 'application/pdf',
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv',
        };
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
