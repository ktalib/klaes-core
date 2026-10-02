<?php

namespace App\Services;

use App\Models\FileTracker;
use Illuminate\Support\Facades\DB;
use Exception;

class QuickActionsService
{
    /**
     * Get office data for Quick Actions
     */
    public static function getOfficeData(): array
    {
        return [
            'OFF-001' => ['name' => 'Reception', 'code' => 'RCP', 'department' => 'Customer Service'],
            'OFF-002' => ['name' => 'Customer Care Unit', 'code' => 'CCU', 'department' => 'Customer Service'],
            'OFF-003' => ['name' => 'Document Verification', 'code' => 'DVF', 'department' => 'Legal'],
            'OFF-004' => ['name' => 'Survey Department', 'code' => 'SUR', 'department' => 'Technical'],
            'OFF-005' => ['name' => 'Legal Department', 'code' => 'LEG', 'department' => 'Legal'],
            'OFF-006' => ['name' => 'Planning Department', 'code' => 'PLN', 'department' => 'Technical'],
            'OFF-007' => ['name' => "Director's Office", 'code' => 'DIR', 'department' => 'Management'],
            'OFF-008' => ['name' => 'Certificate Issuance', 'code' => 'CRT', 'department' => 'Operations'],
            'OFF-009' => ['name' => 'Archive', 'code' => 'ARC', 'department' => 'Records'],
            'OFF-010' => ['name' => 'Finance Department', 'code' => 'FIN', 'department' => 'Finance'],
            'OFF-011' => ['name' => 'IT Department', 'code' => 'ITD', 'department' => 'Technical'],
            'OFF-012' => ['name' => 'Registry', 'code' => 'REG', 'department' => 'Records']
        ];
    }

    /**
     * Get comprehensive statistics for dashboard
     */
    public static function getDashboardStatistics(): array
    {
        try {
            $baseQuery = FileTracker::query();

            // Basic counts
            $total = $baseQuery->count();
            $active = $baseQuery->where('status', 'Active')->count();
            $completed = $baseQuery->where('status', 'Completed')->count();
            $onHold = $baseQuery->where('status', 'On Hold')->count();
            $cancelled = $baseQuery->where('status', 'Cancelled')->count();

            // Priority breakdown
            $priorityStats = $baseQuery->select('priority', DB::raw('count(*) as count'))
                                     ->groupBy('priority')
                                     ->pluck('count', 'priority')
                                     ->toArray();

            // Department breakdown
            $departmentStats = $baseQuery->select('department', DB::raw('count(*) as count'))
                                        ->whereNotNull('department')
                                        ->groupBy('department')
                                        ->pluck('count', 'department')
                                        ->toArray();

            // Recent activity (last 30 days)
            $recentStats = [
                'day' => $baseQuery->whereDate('created_at', '>=', now()->subDay())->count(),
                'week' => $baseQuery->whereDate('created_at', '>=', now()->subWeek())->count(),
                'month' => $baseQuery->whereDate('created_at', '>=', now()->subMonth())->count()
            ];

            // Overdue trackers
            $overdue = $baseQuery->where('deadline', '<', now())
                                ->where('status', '!=', 'Completed')
                                ->count();

            // Average processing time
            $avgProcessingTime = $baseQuery->where('status', 'Completed')
                                          ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
                                          ->value('avg_days') ?? 0;

            return [
                'total' => $total,
                'active' => $active,
                'completed' => $completed,
                'pending' => $onHold,
                'cancelled' => $cancelled,
                'overdue' => $overdue,
                'priority' => [
                    'High' => $priorityStats['High'] ?? 0,
                    'Medium' => $priorityStats['Medium'] ?? 0,
                    'Low' => $priorityStats['Low'] ?? 0,
                    'Urgent' => $priorityStats['Urgent'] ?? 0
                ],
                'departments' => $departmentStats,
                'recent' => $recentStats,
                'avg_processing_days' => round($avgProcessingTime, 1),
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
            ];

        } catch (Exception $e) {
            return [
                'total' => 0,
                'active' => 0,
                'completed' => 0,
                'pending' => 0,
                'cancelled' => 0,
                'overdue' => 0,
                'priority' => ['High' => 0, 'Medium' => 0, 'Low' => 0, 'Urgent' => 0],
                'departments' => [],
                'recent' => ['day' => 0, 'week' => 0, 'month' => 0],
                'avg_processing_days' => 0,
                'completion_rate' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate bulk operation data
     */
    public static function validateBulkOperation(string $operation, array $data): array
    {
        $errors = [];

        switch ($operation) {
            case 'move':
                if (!isset($data['office_code']) || empty($data['office_code'])) {
                    $errors[] = 'Office code is required for move operation';
                }
                if (!isset($data['office_name']) || empty($data['office_name'])) {
                    $errors[] = 'Office name is required for move operation';
                }
                break;

            case 'priority':
                if (!isset($data['priority']) || !in_array($data['priority'], ['Low', 'Medium', 'High', 'Urgent'])) {
                    $errors[] = 'Valid priority (Low, Medium, High, Urgent) is required';
                }
                break;

            case 'archive':
                // No additional validation needed for archive
                break;

            case 'delete':
                if (!isset($data['confirm']) || $data['confirm'] !== true) {
                    $errors[] = 'Confirmation is required for delete operation';
                }
                break;

            default:
                $errors[] = 'Invalid operation type';
        }

        return $errors;
    }

    /**
     * Generate export filename based on format and date
     */
    public static function generateExportFilename(string $format, string $dateRange = null): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $dateStr = $dateRange ? "_{$dateRange}" : '';
        
        switch ($format) {
            case 'csv':
                return "file_trackers{$dateStr}_{$timestamp}.csv";
            case 'excel':
                return "file_trackers{$dateStr}_{$timestamp}.xlsx";
            case 'pdf':
                return "file_trackers_report{$dateStr}_{$timestamp}.pdf";
            default:
                return "file_trackers{$dateStr}_{$timestamp}.txt";
        }
    }

    /**
     * Format tracker data for display
     */
    public static function formatTrackerForDisplay(FileTracker $tracker): array
    {
        return [
            'id' => $tracker->id,
            'tracking_id' => $tracker->tracking_id,
            'file_number' => $tracker->file_number,
            'file_title' => $tracker->file_title,
            'file_type' => $tracker->file_type,
            'priority' => $tracker->priority,
            'priority_badge' => self::getPriorityBadge($tracker->priority),
            'status' => $tracker->status,
            'status_badge' => self::getStatusBadge($tracker->status),
            'current_office' => $tracker->current_office_name,
            'current_office_code' => $tracker->current_office_code,
            'department' => $tracker->department,
            'created_at' => $tracker->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $tracker->updated_at->format('Y-m-d H:i:s'),
            'deadline' => $tracker->deadline ? $tracker->deadline->format('Y-m-d') : null,
            'is_overdue' => $tracker->is_overdue,
            'days_until_deadline' => $tracker->days_until_deadline,
            'completion_percentage' => $tracker->completion_percentage,
            'movement_count' => count(json_decode($tracker->movement_log, true) ?? [])
        ];
    }

    /**
     * Get priority badge HTML
     */
    private static function getPriorityBadge(string $priority): string
    {
        $badges = [
            'Low' => '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Low</span>',
            'Medium' => '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Medium</span>',
            'High' => '<span class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800">High</span>',
            'Urgent' => '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Urgent</span>'
        ];

        return $badges[$priority] ?? $priority;
    }

    /**
     * Get status badge HTML
     */
    private static function getStatusBadge(string $status): string
    {
        $badges = [
            'Active' => '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Active</span>',
            'Completed' => '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Completed</span>',
            'On Hold' => '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">On Hold</span>',
            'Cancelled' => '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Cancelled</span>'
        ];

        return $badges[$status] ?? $status;
    }
}