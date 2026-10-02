<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * AuditService
 *
 * Centralized service for logging audit trail events
 */
class AuditService
{
    /**
     * Log an action
     */
    public function logAction(
        string $action,
        string $resourceType,
        $resourceId = null,
        array $oldValues = null,
        array $newValues = null,
        string $description = null
    ): AuditLog {
        try {
            $auditLog = AuditLog::create([
                'user_id' => Auth::id(),
                'action' => strtoupper($action),
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $this->getClientIp(),
                'user_agent' => Request::userAgent(),
            ]);

            Log::info("Audit logged", [
                'user_id' => Auth::id(),
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'description' => $description,
            ]);

            return $auditLog;

        } catch (\Exception $e) {
            Log::error("Error logging audit: {$e->getMessage()}", [
                'action' => $action,
                'resource_type' => $resourceType,
            ]);
            throw $e;
        }
    }

    /**
     * Log report generated
     */
    public function logReportGenerated(
        $reportId,
        string $format,
        array $filters = [],
        int $recordCount = 0
    ): AuditLog {
        return $this->logAction(
            'CREATED',
            'report',
            $reportId,
            null,
            [
                'format' => $format,
                'filters' => $filters,
                'record_count' => $recordCount,
                'generated_at' => now()->toIso8601String(),
            ],
            "Report generated in {$format} format with {$recordCount} records"
        );
    }

    /**
     * Log report downloaded
     */
    public function logReportDownloaded($reportId, string $format): AuditLog
    {
        return $this->logAction(
            'DOWNLOADED',
            'report',
            $reportId,
            null,
            [
                'format' => $format,
                'downloaded_at' => now()->toIso8601String(),
            ],
            "Report downloaded in {$format} format"
        );
    }

    /**
     * Log report deleted
     */
    public function logReportDeleted($reportId, array $reportData = []): AuditLog
    {
        return $this->logAction(
            'DELETED',
            'report',
            $reportId,
            $reportData,
            null,
            'Report deleted'
        );
    }

    /**
     * Log report exported
     */
    public function logReportExported(
        $reportId,
        string $exportFormat,
        string $destination = 'email'
    ): AuditLog {
        return $this->logAction(
            'EXPORTED',
            'report',
            $reportId,
            null,
            [
                'export_format' => $exportFormat,
                'destination' => $destination,
                'exported_at' => now()->toIso8601String(),
            ],
            "Report exported to {$destination} in {$exportFormat} format"
        );
    }

    /**
     * Log scheduled report created
     */
    public function logScheduleCreated(
        $scheduleId,
        string $frequency,
        string $format,
        array $recipients = []
    ): AuditLog {
        return $this->logAction(
            'CREATED',
            'scheduled_report',
            $scheduleId,
            null,
            [
                'frequency' => $frequency,
                'format' => $format,
                'recipients' => $recipients,
                'created_at' => now()->toIso8601String(),
            ],
            "Scheduled report created ({$frequency} in {$format} format)"
        );
    }

    /**
     * Log scheduled report updated
     */
    public function logScheduleUpdated(
        $scheduleId,
        array $oldValues,
        array $newValues
    ): AuditLog {
        return $this->logAction(
            'UPDATED',
            'scheduled_report',
            $scheduleId,
            $oldValues,
            $newValues,
            'Scheduled report updated'
        );
    }

    /**
     * Log scheduled report deleted
     */
    public function logScheduleDeleted(
        $scheduleId,
        array $scheduleData = []
    ): AuditLog {
        return $this->logAction(
            'DELETED',
            'scheduled_report',
            $scheduleId,
            $scheduleData,
            null,
            'Scheduled report deleted'
        );
    }

    /**
     * Log scheduled report executed
     */
    public function logScheduleExecuted(
        $scheduleId,
        bool $success = true,
        string $message = null,
        array $recipients = []
    ): AuditLog {
        return $this->logAction(
            'EXECUTED',
            'scheduled_report',
            $scheduleId,
            null,
            [
                'success' => $success,
                'message' => $message,
                'recipients' => $recipients,
                'executed_at' => now()->toIso8601String(),
            ],
            "Scheduled report executed (" . ($success ? 'success' : 'failed') . ")"
        );
    }

    /**
     * Log activity data accessed
     */
    public function logActivityDataAccessed(
        array $filters = [],
        int $recordsReturned = 0
    ): AuditLog {
        return $this->logAction(
            'VIEWED',
            'user_activity_log',
            null,
            null,
            [
                'filters' => $filters,
                'records_returned' => $recordsReturned,
                'accessed_at' => now()->toIso8601String(),
            ],
            "Activity data accessed ({$recordsReturned} records)"
        );
    }

    /**
     * Log analytics viewed
     */
    public function logAnalyticsViewed(
        string $analyticsType,
        array $parameters = []
    ): AuditLog {
        return $this->logAction(
            'VIEWED',
            'analytics',
            null,
            null,
            [
                'analytics_type' => $analyticsType,
                'parameters' => $parameters,
                'viewed_at' => now()->toIso8601String(),
            ],
            "Analytics viewed: {$analyticsType}"
        );
    }

    /**
     * Log bulk action
     */
    public function logBulkAction(
        string $action,
        string $resourceType,
        int $affectedCount,
        array $criteria = []
    ): AuditLog {
        return $this->logAction(
            $action,
            $resourceType,
            null,
            null,
            [
                'affected_count' => $affectedCount,
                'criteria' => $criteria,
                'action_at' => now()->toIso8601String(),
            ],
            "Bulk {$action} on {$affectedCount} {$resourceType} records"
        );
    }

    /**
     * Get audit history for resource
     */
    public function getResourceAudit(string $resourceType, $resourceId, int $limit = 50)
    {
        return AuditLog::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user audit history
     */
    public function getUserAudit($userId, int $limit = 100)
    {
        return AuditLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats(int $days = 30)
    {
        $since = now()->subDays($days);

        return [
            'total_audits' => AuditLog::where('created_at', '>=', $since)->count(),
            'by_action' => AuditLog::where('created_at', '>=', $since)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->get()
                ->pluck('count', 'action')
                ->toArray(),
            'by_resource_type' => AuditLog::where('created_at', '>=', $since)
                ->selectRaw('resource_type, COUNT(*) as count')
                ->groupBy('resource_type')
                ->get()
                ->pluck('count', 'resource_type')
                ->toArray(),
            'by_user' => AuditLog::where('created_at', '>=', $since)
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderByRaw('count DESC')
                ->limit(10)
                ->with('user:id,name')
                ->get()
                ->map(fn($audit) => [
                    'user_id' => $audit->user_id,
                    'user_name' => $audit->user->name ?? 'Unknown',
                    'count' => $audit->count,
                ])
                ->toArray(),
            'period_days' => $days,
        ];
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): ?string
    {
        // Check for IP from shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IP passed from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Check for remote address
        else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        // Validate IP address
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return null;
    }
}
