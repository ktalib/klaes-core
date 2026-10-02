<?php

namespace App\Services;

use App\Models\FileTracking;
use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FileTrackingAssignmentService
{
    public function __construct(
        protected UserNotificationService $notificationService
    ) {
    }

    /**
     * Assign a file tracking record to another office/user.
     *
     * @throws \RuntimeException
     */
    public function assign(
        FileTracking $tracking,
        int $officeId,
        int $userId,
        ?int $assignedById = null,
        array $context = []
    ): FileTracking {
        $office = Office::on('sqlsrv')->find($officeId);
        if (!$office) {
            throw new RuntimeException('The selected destination office could not be found.');
        }

        $user = User::on('sqlsrv')->find($userId);
        if (!$user || !$this->isAssignableUser($user)) {
            throw new RuntimeException('The selected receiving officer is not available.');
        }

        $connection = $tracking->getConnectionName() ?: $tracking->getConnection();

        $assigned = DB::connection($connection)->transaction(function () use (
            $tracking,
            $office,
            $user,
            $assignedById,
            $context
        ) {
            $tracking->assigned_office = $office->id;
            $tracking->assigned_to = $user->id;
            $tracking->assigned_by = $assignedById ?: Auth::id();
            $tracking->markAssignmentPending();
            $this->appendPendingMovement($tracking, $office, $user, $context['reason'] ?? null);
            $tracking->save();

            $this->createNotification($tracking, $user, $office, $context);

            return $tracking->fresh([
                'assignedUser',
                'assignedByUser',
                'assignedOffice',
                'fileIndexing',
            ]);
        });

        return $assigned;
    }

    /**
     * Accept an assignment.
     *
     * @throws \RuntimeException
     */
    public function accept(FileTracking $tracking, int $userId): FileTracking
    {
        if ((int) $tracking->assigned_to !== (int) $userId) {
            throw new RuntimeException('You cannot accept an assignment that is not addressed to you.');
        }

        $connection = $tracking->getConnectionName() ?: $tracking->getConnection();

        $accepted = DB::connection($connection)->transaction(function () use ($tracking) {
            $tracking->markAssignmentAccepted();
            $tracking->loadMissing('assignedOffice', 'assignedUser');

            if ($tracking->assignedOffice) {
                $tracking->current_location = $tracking->assignedOffice->office_name ?? $tracking->assignedOffice->office_code;
                $tracking->current_office_name = $tracking->assignedOffice->office_name;
                $tracking->current_office_code = $tracking->assignedOffice->office_code;
            }
            if ($tracking->assignedUser) {
                $tracking->current_holder = $tracking->assignedUser->name ?? trim(($tracking->assignedUser->first_name ?? '') . ' ' . ($tracking->assignedUser->last_name ?? ''));
                $tracking->current_handler = $tracking->current_holder;
            }

            $this->updateLatestPendingMovement($tracking, function (&$entry) {
                $entry['status'] = 'active';
                $entry['log_in_time'] = now()->format('H:i');
                $entry['log_in_date'] = now()->format('Y-m-d');
            });

            $tracking->save();

            return $tracking->fresh([
                'assignedUser',
                'assignedByUser',
                'assignedOffice',
                'fileIndexing',
            ]);
        });

        $this->notifyAssignmentCreatorOutcome($accepted, 'accepted');

        return $accepted;
    }

    /**
     * Reject an assignment.
     *
     * @throws \RuntimeException
     */
    public function reject(FileTracking $tracking, int $userId, ?string $note = null): FileTracking
    {
        if ((int) $tracking->assigned_to !== (int) $userId) {
            throw new RuntimeException('You cannot reject an assignment that is not addressed to you.');
        }

        $connection = $tracking->getConnectionName() ?: $tracking->getConnection();

        $rejected = DB::connection($connection)->transaction(function () use ($tracking, $note) {
            $tracking->markAssignmentRejected($note);
            $this->updateLatestPendingMovement($tracking, function (&$entry) use ($note) {
                $entry['status'] = 'rejected';
                if ($note) {
                    $entry['reject_note'] = $note;
                }
            });
            $tracking->save();

            return $tracking->fresh([
                'assignedUser',
                'assignedByUser',
                'assignedOffice',
                'fileIndexing',
            ]);
        });

        $this->notifyAssignmentCreatorOutcome($rejected, 'rejected', $note);

        return $rejected;
    }

    protected function isAssignableUser(User $user): bool
    {
        if ($user->is_active !== null && (int) $user->is_active === 0) {
            return false;
        }

        return true;
    }

    protected function createNotification(FileTracking $tracking, User $user, Office $office, array $context = []): void
    {
        if (!$user->id) {
            return;
        }

        try {
            $tracking->loadMissing('fileIndexing');

            $title = trim((string) ($context['notification_title'] ?? 'File tracker assignment'));
            if ($title === '') {
                $title = sprintf('File assigned: %s', $tracking->fileIndexing->file_number ?? $tracking->qr_code ?? $tracking->id);
            }

            $defaultBodyParts = [
                'A file log has been assigned to you.',
                $tracking->fileIndexing?->file_number ? 'File No: ' . $tracking->fileIndexing->file_number : null,
                $tracking->fileIndexing?->file_title ? 'File Name: ' . $tracking->fileIndexing->file_title : null,
                'Office: ' . ($office->office_name ?? $office->office_code),
            ];

            $body = trim((string) ($context['notification_body'] ?? implode(' | ', array_filter($defaultBodyParts))));

            $data = [
                'file_tracking_id' => $tracking->id,
                'file_number' => $tracking->fileIndexing->file_number ?? null,
                'file_name' => $tracking->fileIndexing->file_title ?? null,
                'tracking_qr' => $tracking->qr_code,
                'assigned_office_id' => $office->id,
                'assigned_office_code' => $office->office_code,
                'assigned_office_name' => $office->office_name,
                'reason' => $context['reason'] ?? null,
                'assignment_status' => $tracking->assignment_status,
                'assigned_at' => optional($tracking->assigned_at)->toIso8601String(),
            ];

            $overrides = [
                'module' => 'file_tracking',
                'subject' => $title,
                'message' => $body,
                'name' => $user->name,
                'enabled_email' => (bool) Arr::get($context, 'send_email', false),
                'enabled_sms' => (bool) Arr::get($context, 'send_sms', false),
            ];

            $this->notificationService->create(
                $user->id,
                'file_tracking.assignment',
                $title,
                $body,
                $data,
                $overrides
            );
        } catch (\Throwable $exception) {
            Log::warning('Unable to create file tracking notification', [
                'tracking_id' => $tracking->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function appendPendingMovement(FileTracking $tracking, Office $office, User $user, ?string $reason = null): void
    {
        $movementLog = $tracking->movement_log ?: [];
        $movementLog[] = [
            'log_id' => 'LOG-' . now()->format('YmdHis') . '-' . str_pad(count($movementLog) + 1, 3, '0', STR_PAD_LEFT),
            'office_code' => $office->office_code,
            'office_name' => $office->office_name,
            'log_in_time' => null,
            'log_in_date' => null,
            'log_out_time' => null,
            'log_out_date' => null,
            'status' => 'pending_acceptance',
            'assigned_office_id' => $office->id,
            'assigned_office_name' => $office->office_name,
            'assigned_to' => $user->id,
            'assigned_to_name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'notes' => $reason,
            'timestamp' => now()->toIso8601String(),
        ];

        $tracking->movement_log = $movementLog;
    }

    protected function updateLatestPendingMovement(FileTracking $tracking, callable $mutator): void
    {
        $movementLog = $tracking->movement_log ?: [];
        for ($index = count($movementLog) - 1; $index >= 0; $index--) {
            $status = strtolower((string) ($movementLog[$index]['status'] ?? ''));
            if ($status === 'pending_acceptance') {
                $mutator($movementLog[$index]);
                $tracking->movement_log = $movementLog;
                break;
            }
        }
    }

    protected function notifyAssignmentCreatorOutcome(FileTracking $tracking, string $outcome, ?string $note = null): void
    {
        $creatorId = (int) $tracking->created_by;

        if ($creatorId <= 0) {
            return;
        }

        $creator = User::on('sqlsrv')->find($creatorId) ?? User::find($creatorId);
        if (!$creator) {
            return;
        }

        $tracking->loadMissing('fileIndexing', 'assignedUser');

        $title = $outcome === 'accepted'
            ? 'Assignment accepted'
            : 'Assignment rejected';

        $assigneeName = $tracking->assignedUser->name
            ?? trim(($tracking->assignedUser->first_name ?? '') . ' ' . ($tracking->assignedUser->last_name ?? ''))
            ?? 'Receiving officer';

        $bodyParts = array_filter([
            $tracking->fileIndexing?->file_number ? 'File No: ' . $tracking->fileIndexing->file_number : null,
            sprintf('%s has %s this assignment.', $assigneeName, $outcome),
            $note ? 'Note: ' . $note : null,
        ]);

        $body = $bodyParts ? implode(' | ', $bodyParts) : 'Assignment update logged.';

        $this->notificationService->create(
            $creatorId,
            "file_tracking.assignment.{$outcome}",
            $title,
            $body,
            [
                'file_tracker_id' => $tracking->id,
                'file_number' => $tracking->fileIndexing?->file_number,
                'file_name' => $tracking->fileIndexing?->file_title,
                'assignment_status' => $tracking->assignment_status,
                'note' => $note,
            ],
            [
                'module' => 'file_tracking',
                'subject' => $title,
                'message' => $body,
                'name' => $creator->name,
            ]
        );
    }
}
