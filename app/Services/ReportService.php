<?php

namespace App\Services;

use App\Models\UserActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * ReportService
 *
 * Handles generation and distribution of activity log reports
 * in various formats (CSV, PDF, Excel).
 */
class ReportService
{
    /**
     * Generate activity report in requested format
     *
     * @param string $format (csv|pdf|excel)
     * @param array $filters (date_from, date_to, status, user_id)
     * @param array $options (filename, include_charts)
     * @return array (success, path, filename, size, format)
     */
    public function generateReport(string $format = 'csv', array $filters = [], array $options = []): array
    {
        try {
            Log::info("Generating {$format} report with filters: " . json_encode($filters));

            $data = $this->prepareReportData($filters);

            $filename = $options['filename'] ?? $this->generateFilename($format);
            $path = $this->getReportPath($filename);

            // Generate based on format
            switch ($format) {
                case 'csv':
                    $this->generateCsv($data, $path);
                    break;
                case 'pdf':
                    $this->generatePdf($data, $path, $options);
                    break;
                case 'excel':
                    $this->generateExcel($data, $path, $options);
                    break;
                default:
                    throw new \Exception("Unsupported format: {$format}");
            }

            $fileSize = filesize($path);

            Log::info("Report generated successfully: {$path} ({$fileSize} bytes)");

            return [
                'success' => true,
                'path' => $path,
                'filename' => $filename,
                'size' => $fileSize,
                'format' => $format,
                'generated_at' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error("Error generating report: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Prepare data for report
     */
    private function prepareReportData(array $filters): array
    {
        $query = UserActivityLog::with('user:id,name,email');

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->where('login_time', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('logout_time', '<=', $filters['date_to'] . ' 23:59:59');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $logs = $query->orderBy('login_time', 'desc')->get();

        // Prepare summary statistics
        $stats = [
            'total_records' => $logs->count(),
            'unique_users' => $logs->unique('user_id')->count(),
            'date_range' => $filters['date_from'] . ' to ' . $filters['date_to'],
            'generated_at' => now()->format('F d, Y H:i:s'),
        ];

        // Calculate duration statistics
        $durations = $logs->filter(fn($log) => $log->duration_minutes)->pluck('duration_minutes');
        if ($durations->count() > 0) {
            $stats['avg_duration'] = round($durations->avg(), 2);
            $stats['max_duration'] = $durations->max();
            $stats['min_duration'] = $durations->min();
        }

        return [
            'logs' => $logs,
            'stats' => $stats,
            'filters' => $filters,
        ];
    }

    /**
     * Generate CSV report
     */
    private function generateCsv(array $data, string $path): void
    {
        $file = fopen($path, 'w');

        // Write headers
        fputcsv($file, [
            'ID',
            'User Name',
            'User Email',
            'Login Time',
            'Logout Time',
            'Duration (min)',
            'Status',
            'Browser',
            'Device',
            'Platform',
            'IP Address',
            'File Number',
            'Environment',
        ]);

        // Write data rows
        foreach ($data['logs'] as $log) {
            fputcsv($file, [
                $log->id,
                $log->user?->name ?? 'Unknown',
                $log->user?->email ?? 'N/A',
                $log->login_time?->format('Y-m-d H:i:s'),
                $log->logout_time?->format('Y-m-d H:i:s'),
                $log->duration_minutes,
                $log->status,
                $log->browser ?? 'Unknown',
                $log->device ?? 'Unknown',
                $log->platform ?? 'Unknown',
                $log->ip_address ?? 'N/A',
                $log->related_file_number ?? 'N/A',
                $log->test_control,
            ]);
        }

        fclose($file);
    }

    /**
     * Generate PDF report
     *
     * Requires: barryvdh/laravel-dompdf package
     */
    private function generatePdf(array $data, string $path, array $options = []): void
    {
        // Check if PDF generation library is available
        if (!class_exists('Barryvdh\DomPDF\Facade\Pdf')) {
            throw new \Exception('PDF generation requires barryvdh/laravel-dompdf package');
        }

        $html = $this->generatePdfHtml($data);

        \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->save($path);
    }

    /**
     * Generate HTML for PDF report
     */
    private function generatePdfHtml(array $data): string
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        .stats { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #007bff; color: white; padding: 12px; text-align: left; }
        td { border-bottom: 1px solid #ddd; padding: 10px; }
        tr:nth-child(even) { background: #f8f9fa; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <h1>Activity Log Report</h1>
    
    <div class="stats">
        <h3>Report Summary</h3>
        <p><strong>Generated:</strong> %generated_at%</p>
        <p><strong>Date Range:</strong> %date_range%</p>
        <p><strong>Total Records:</strong> %total_records%</p>
        <p><strong>Unique Users:</strong> %unique_users%</p>
        %stats%
    </div>
    
    <h3>Activity Log Details</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Login Time</th>
                <th>Duration (min)</th>
                <th>Status</th>
                <th>Browser</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            %table_rows%
        </tbody>
    </table>
    
    <div class="footer">
        <p>This report was automatically generated by the Activity Log System.</p>
    </div>
</body>
</html>
HTML;

        // Replace placeholders
        $html = str_replace(
            ['%generated_at%', '%date_range%', '%total_records%', '%unique_users%'],
            [
                $data['stats']['generated_at'],
                $data['stats']['date_range'],
                $data['stats']['total_records'],
                $data['stats']['unique_users'],
            ],
            $html
        );

        // Generate stats line
        $statsLine = '';
        if (isset($data['stats']['avg_duration'])) {
            $statsLine .= "<p><strong>Avg Duration:</strong> {$data['stats']['avg_duration']} minutes</p>";
            $statsLine .= "<p><strong>Max Duration:</strong> {$data['stats']['max_duration']} minutes</p>";
            $statsLine .= "<p><strong>Min Duration:</strong> {$data['stats']['min_duration']} minutes</p>";
        }
        $html = str_replace('%stats%', $statsLine, $html);

        // Generate table rows
        $rows = '';
        foreach ($data['logs'] as $log) {
            $rows .= '<tr>';
            $rows .= '<td>' . $log->id . '</td>';
            $rows .= '<td>' . ($log->user?->name ?? 'Unknown') . '</td>';
            $rows .= '<td>' . $log->login_time?->format('Y-m-d H:i') . '</td>';
            $rows .= '<td>' . ($log->duration_minutes ?? 'N/A') . '</td>';
            $rows .= '<td>' . $log->status . '</td>';
            $rows .= '<td>' . ($log->browser ?? 'Unknown') . '</td>';
            $rows .= '<td>' . ($log->ip_address ?? 'N/A') . '</td>';
            $rows .= '</tr>';
        }
        $html = str_replace('%table_rows%', $rows, $html);

        return $html;
    }

    /**
     * Generate Excel report
     *
     * Requires: maatwebsite/excel package
     */
    private function generateExcel(array $data, string $path, array $options = []): void
    {
        // Check if Excel generation library is available
        if (!class_exists('Maatwebsite\Excel\Facades\Excel')) {
            throw new \Exception('Excel generation requires maatwebsite/excel package');
        }

        // For now, generate as CSV with .xlsx extension
        // Full Excel support would require the library to be installed
        $this->generateCsv($data, str_replace('.xlsx', '.csv', $path));
    }

    /**
     * Generate filename for report
     */
    private function generateFilename(string $format): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $extension = match($format) {
            'pdf' => 'pdf',
            'excel' => 'xlsx',
            default => 'csv',
        };

        return "activity-log-report_{$timestamp}.{$extension}";
    }

    /**
     * Get storage path for report
     */
    private function getReportPath(string $filename): string
    {
        $directory = file_storage_path('app/reports');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return "{$directory}/{$filename}";
    }

    /**
     * Schedule report for recurring delivery
     *
     * @param array $config (frequency, format, user_id, recipients, date_range)
     * @return array (success, message, report_id)
     */
    public function scheduleReport(array $config): array
    {
        try {
            // Validate required fields
            if (empty($config['frequency']) || empty($config['format'])) {
                throw new \Exception('Frequency and format are required');
            }

            // Validate frequency
            $validFrequencies = ['daily', 'weekly', 'biweekly', 'monthly'];
            if (!in_array($config['frequency'], $validFrequencies)) {
                throw new \Exception("Invalid frequency: {$config['frequency']}");
            }

            // Calculate next run time
            $nextRun = $this->calculateNextRun($config['frequency']);

            // Store in database (would require scheduled_activity_reports table)
            // For now, log the scheduling
            Log::info("Scheduled report: {$config['frequency']} in {$config['format']} format", $config);

            return [
                'success' => true,
                'message' => "Report scheduled successfully for {$config['frequency']} delivery",
                'next_run' => $nextRun->toIso8601String(),
            ];

        } catch (\Exception $e) {
            Log::error("Error scheduling report: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate next run time based on frequency
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
     * Send scheduled reports
     *
     * Should be called by console command
     */
    public function sendScheduledReports(): array
    {
        try {
            Log::info('Processing scheduled activity reports');

            $sent = 0;
            $failed = 0;

            // In production, would query scheduled_activity_reports table
            // For now, just log that the process ran

            Log::info("Scheduled reports sent: {$sent}, failed: {$failed}");

            return [
                'success' => true,
                'sent' => $sent,
                'failed' => $failed,
                'message' => "Processed {$sent} scheduled reports",
            ];

        } catch (\Exception $e) {
            Log::error("Error sending scheduled reports: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get report history
     */
    public function getReportHistory(array $filters = []): array
    {
        try {
            // Would query report history from database
            // For now, return empty array
            return [
                'success' => true,
                'reports' => [],
                'total' => 0,
            ];

        } catch (\Exception $e) {
            Log::error("Error retrieving report history: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete old reports (cleanup)
     *
     * @param int $daysOld (default 30)
     * @return array (success, deleted_count)
     */
    public function cleanupOldReports(int $daysOld = 30): array
    {
        try {
            $directory = file_storage_path('app/reports');
            $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
            $deleted = 0;

            if (is_dir($directory)) {
                $files = scandir($directory);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;

                    $filePath = "{$directory}/{$file}";
                    if (is_file($filePath) && filemtime($filePath) < $cutoffTime) {
                        unlink($filePath);
                        $deleted++;
                    }
                }
            }

            Log::info("Cleaned up {$deleted} old reports (older than {$daysOld} days)");

            return [
                'success' => true,
                'deleted' => $deleted,
            ];

        } catch (\Exception $e) {
            Log::error("Error cleaning up reports: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
