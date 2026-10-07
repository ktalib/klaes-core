<?php

namespace App\Services\FileTracking;

use App\Models\FileTracker;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The Log File (LoF) register for the HC / PS / Directors' secretaries.
 *
 * Every movement lands in the shared file_tracker.movement_log, so a file logged
 * here has one continuous trail with every other module. Three operations:
 *
 *   receive  — the file arrives. Close whatever movement it was on, and open an
 *              accepted entry at my office. A pending hand-over addressed to my
 *              office is accepted in place instead of duplicated.
 *   forward  — the file leaves. Close my entry and open a pending entry at the
 *              next office, which shows as "sent" until that office receives it.
 *   lists    — pending receipt / held here / sent, for one office.
 *
 * Files with no tracker yet (no tracking sheet, unindexed files, letters and
 * memos that are not regular files) get one created on receipt, tagged
 * module = 'secretariat'. Nothing is ever deleted.
 */
class SecretariatFileLogService
{
    public const MODULE = 'secretariat';

    public const ENTRY_FILE = 'file';            // indexed / known file
    public const ENTRY_UNINDEXED = 'unindexed';  // file number exists, not indexed
    public const ENTRY_NON_FILE = 'non_file';    // letter, memo, not a regular file

    /** @return array{code:string,name:string,department:?string} */
    public function office(string $code): array
    {
        $row = DB::connection('sqlsrv')->table('offices')
            ->where('office_code', $code)->where('is_active', 1)
            ->first(['office_code', 'office_name', 'department']);

        if (!$row) {
            throw new RuntimeException("Unknown office \"{$code}\".");
        }

        return ['code' => $row->office_code, 'name' => trim($row->office_name), 'department' => $row->department];
    }

    public function offices(): Collection
    {
        return DB::connection('sqlsrv')->table('offices')->where('is_active', 1)
            ->orderByRaw("CASE WHEN department = 'Management' THEN 0 ELSE 1 END")
            ->orderBy('department')->orderBy('office_name')
            ->get(['office_code', 'office_name', 'department']);
    }

    /**
     * @param array{entry_type:string, tracker_id?:?int, file_number?:?string, file_title?:?string,
     *              from_office?:?string, sender?:?string, reference?:?string, notes?:?string,
     *              received_via?:?string} $data
     * @return array{tracker:FileTracker, action:string}
     */
    public function receive(string $officeCode, array $data, User $user): array
    {
        $office = $this->office($officeCode);
        $entryType = $data['entry_type'] ?? self::ENTRY_FILE;
        $fileNumber = $this->clean($data['file_number'] ?? null);

        if ($entryType !== self::ENTRY_NON_FILE && !$fileNumber && empty($data['tracker_id'])) {
            throw new RuntimeException('A file number is required.');
        }
        if ($entryType === self::ENTRY_NON_FILE && !$this->clean($data['file_title'] ?? null)) {
            throw new RuntimeException('Give the document a title or subject.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($office, $data, $user, $entryType, $fileNumber) {
            $tracker = $this->findTracker($data['tracker_id'] ?? null, $entryType === self::ENTRY_NON_FILE ? null : $fileNumber);
            $now = now();
            $userName = $this->userName($user);

            if (!$tracker) {
                return ['tracker' => $this->createAtOffice($office, $data, $user, $entryType, $fileNumber), 'action' => 'created'];
            }

            $log = $tracker->movement_log ?: [];
            $lastIndex = count($log) - 1;
            $last = $lastIndex >= 0 ? $log[$lastIndex] : null;
            $lastStatus = strtolower((string) ($last['status'] ?? ''));
            $lastOffice = $last['receiving_office_code'] ?? $last['office_code'] ?? null;

            // log_out_date is not a "left the office" signal: Log a File stamps it at
            // creation on the destination entry. Status alone says where the file is.
            if ($last && $lastStatus === 'active' && $lastOffice === $office['code']) {
                return ['tracker' => $tracker, 'action' => 'already_here'];
            }

            // A hand-over already addressed to my office: accept it in place.
            if ($last && $lastStatus === 'pending_acceptance' && $lastOffice === $office['code']) {
                $log[$lastIndex] = array_merge($last, [
                    'log_in_time'       => $now->format('H:i'),
                    'log_in_date'       => $now->format('Y-m-d'),
                    'status'            => 'active',
                    'accepted_by'       => $user->id,
                    'accepted_by_name'  => $userName,
                    'accepted_at'       => $now->toIso8601String(),
                    'acceptance_source' => 'secretariat_receive',
                ]);
                if ($this->clean($data['notes'] ?? null)) {
                    $log[$lastIndex]['acceptance_note'] = $this->clean($data['notes']);
                }
                $tracker->movement_log = $log;
                $this->markHeld($tracker, $office, $userName);
                $tracker->save();

                return ['tracker' => $tracker->refresh(), 'action' => 'accepted'];
            }

            // Anywhere else: complete the movement it was on, then log it in here.
            $fromName = $last['receiving_office_name'] ?? $last['office_name'] ?? null;
            if ($last && in_array($lastStatus, ['active', 'pending_acceptance', 'pending'], true)) {
                $tracker->completeCurrentMovement($now->format('H:i'), 'Received at ' . $office['name'] . ' (File Movement)');
            }

            $tracker->addMovementLog(
                $office['code'],
                $office['name'],
                $now->format('H:i'),
                $now->format('Y-m-d'),
                $this->composeNotes($data, $fromName),
                $user->id,
                $userName,
                [
                    'status'            => 'active',
                    'accepted_by'       => $user->id,
                    'accepted_by_name'  => $userName,
                    'acceptance_source' => 'secretariat_receive',
                    'purpose'           => 'received',
                ]
            );

            $this->markHeld($tracker, $office, $userName);
            if (strtoupper((string) $tracker->status) === FileTracker::STATUS_COMPLETED) {
                $tracker->status = FileTracker::STATUS_ACTIVE;
            }
            $tracker->save();

            return ['tracker' => $tracker->refresh(), 'action' => 'logged_in'];
        });
    }

    /** @return FileTracker */
    public function forward(string $officeCode, int $trackerId, string $toOfficeCode, ?string $purpose, ?string $notes, User $user): FileTracker
    {
        $office = $this->office($officeCode);
        $to = $this->office($toOfficeCode);

        if ($to['code'] === $office['code']) {
            throw new RuntimeException('Choose a different office to send the file to.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($office, $to, $trackerId, $purpose, $notes, $user) {
            $tracker = FileTracker::lockForUpdate()->find($trackerId);
            if (!$tracker) {
                throw new RuntimeException('File tracker not found.');
            }

            $log = $tracker->movement_log ?: [];
            $last = $log ? end($log) : null;
            $lastStatus = strtolower((string) ($last['status'] ?? ''));
            $lastOffice = $last['receiving_office_code'] ?? $last['office_code'] ?? null;

            // Only the office that is holding the file can send it on.
            if (!$last || $lastStatus !== 'active' || $lastOffice !== $office['code']) {
                throw new RuntimeException("This file is not currently held at {$office['name']}. Receive it first.");
            }

            $now = now();
            $userName = $this->userName($user);

            $tracker->completeCurrentMovement($now->format('H:i'), $this->clean($notes) ?: ('Sent to ' . $to['name']));

            $tracker->addMovementLog(
                $to['code'],
                $to['name'],
                null,
                null,
                $this->clean($notes),
                $user->id,
                $userName,
                [
                    'status'                => 'pending_acceptance',
                    'requires_acceptance'   => true,
                    'purpose'               => $this->clean($purpose),
                    'receiving_office_code' => $to['code'],
                    'receiving_office_name' => $to['name'],
                ]
            );

            // addMovementLog only keeps known keys — stamp the sender on the new entry.
            $log = $tracker->movement_log;
            $log[count($log) - 1]['sent_from_office_code'] = $office['code'];
            $log[count($log) - 1]['sent_from_office_name'] = $office['name'];
            $tracker->movement_log = $log;

            $tracker->receiving_office_code = $to['code'];
            $tracker->receiving_office_name = $to['name'];
            $tracker->receiving_officer_id = null;
            $tracker->receiving_officer_name = null;
            $tracker->assignment_status = FileTracker::ASSIGNMENT_PENDING;
            $tracker->assignment_accepted_at = null;
            $tracker->save();

            return $tracker->refresh();
        });
    }

    /**
     * pending: addressed to my office, not yet received.
     * held:    received here and not sent on.
     * sent:    sent from my office in the last $days days, with where it is now.
     */
    public function lists(string $officeCode, int $days = 7): array
    {
        $office = $this->office($officeCode);
        $code = $office['code'];

        $atMyOffice = FileTracker::where(function ($q) use ($code) {
            $q->where('current_office_code', $code)->orWhere('receiving_office_code', $code);
        })
            ->whereRaw("UPPER(ISNULL(status,'')) NOT IN ('CANCELLED')")
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $pending = [];
        $held = [];
        foreach ($atMyOffice as $tracker) {
            $log = $tracker->movement_log ?: [];
            $last = $log ? end($log) : null;
            if (!$last) {
                continue;
            }
            $status = strtolower((string) ($last['status'] ?? ''));
            $lastOffice = $last['receiving_office_code'] ?? $last['office_code'] ?? null;
            if ($lastOffice !== $code) {
                continue;
            }
            $prev = count($log) > 1 ? $log[count($log) - 2] : null;

            if ($status === 'pending_acceptance') {
                $pending[] = $this->row($tracker, $last, [
                    'from' => $last['sent_from_office_name'] ?? ($prev['office_name'] ?? $tracker->origin_office_name),
                    'since' => $last['timestamp'] ?? null,
                ]);
            } elseif ($status === 'active') {
                $held[] = $this->row($tracker, $last, [
                    'from' => $prev['office_name'] ?? $tracker->origin_office_name,
                    'since' => $last['accepted_at'] ?? $last['timestamp'] ?? null,
                ]);
            }
        }

        // Sent: any tracker whose log passes through my office, touched recently.
        $needle = '%"office_code":"' . str_replace(['%', '_', '['], ['[%]', '[_]', '[[]'], $code) . '"%';
        $recent = FileTracker::where('movement_log', 'like', $needle)
            ->where('updated_at', '>=', now()->subDays(max(1, $days)))
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $sent = [];
        foreach ($recent as $tracker) {
            $log = array_values($tracker->movement_log ?: []);
            for ($i = count($log) - 2; $i >= 0; $i--) {
                $entryOffice = $log[$i]['receiving_office_code'] ?? $log[$i]['office_code'] ?? null;
                if ($entryOffice !== $code || empty($log[$i]['log_out_date'])) {
                    continue;
                }
                $next = $log[$i + 1];
                $sentAt = $log[$i]['log_out_date'] . ' ' . ($log[$i]['log_out_time'] ?? '');
                if (strtotime($sentAt) && strtotime($sentAt) < now()->subDays(max(1, $days))->getTimestamp()) {
                    break;
                }
                $nextStatus = strtolower((string) ($next['status'] ?? ''));
                $sent[] = $this->row($tracker, $next, [
                    'to' => $next['office_name'] ?? null,
                    'since' => trim($sentAt),
                    'state' => $nextStatus === 'pending_acceptance' ? 'In transit' : 'Received',
                    'now_at' => $tracker->current_office_name,
                ]);
                break;
            }
        }

        return compact('pending', 'held', 'sent');
    }

    private function findTracker(?int $trackerId, ?string $fileNumber): ?FileTracker
    {
        if ($trackerId) {
            return FileTracker::lockForUpdate()->find($trackerId);
        }
        if (!$fileNumber) {
            return null;
        }

        return FileTracker::lockForUpdate()
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper($fileNumber)])
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('CANCELLED')")
            ->orderByDesc('updated_at')
            ->first();
    }

    private function createAtOffice(array $office, array $data, User $user, string $entryType, ?string $fileNumber): FileTracker
    {
        $now = now();
        $userName = $this->userName($user);
        $title = $this->clean($data['file_title'] ?? null) ?: ($fileNumber ?: 'Untitled document');
        $from = !empty($data['from_office']) ? $this->officeOrNull($data['from_office']) : null;

        $tracker = new FileTracker([
            'tracking_id'              => FileTracker::generateTrackingId(),
            'file_number'              => $entryType === self::ENTRY_NON_FILE ? null : $fileNumber,
            'file_title'               => mb_substr($title, 0, 255),
            'file_type'                => $entryType === self::ENTRY_NON_FILE ? 'NON_FILE' : null,
            'priority'                 => FileTracker::PRIORITY_MEDIUM,
            'created_by'               => $user->id,
            'created_by_name'          => $userName,
            'department'               => $office['department'],
            'description'              => $this->clean($data['notes'] ?? null),
            'status'                   => FileTracker::STATUS_ACTIVE,
            'date_created'             => $now,
            'total_offices'            => 1,
            'origin_office_code'       => $from['code'] ?? $office['code'],
            'origin_office_name'       => $from['name'] ?? $office['name'],
            'origin_office_department' => $from['department'] ?? $office['department'],
            'receiving_office_code'    => $office['code'],
            'receiving_office_name'    => $office['name'],
            'assignment_status'        => FileTracker::ASSIGNMENT_ACCEPTED,
            'assignment_accepted_at'   => $now,
            'module'                   => self::MODULE,
        ]);

        $tracker->module_meta = json_encode(['secretariat' => array_filter([
            'entry_type'   => $entryType,
            'not_indexed'  => $entryType === self::ENTRY_UNINDEXED ? true : null,
            'sender'       => $this->clean($data['sender'] ?? null),
            'reference'    => $this->clean($data['reference'] ?? null),
            'received_via' => $this->clean($data['received_via'] ?? null),
            'logged_at'    => $office['code'],
        ], fn ($v) => $v !== null)]);

        $tracker->movement_log = [];
        $tracker->save();

        $tracker->addMovementLog(
            $office['code'],
            $office['name'],
            $now->format('H:i'),
            $now->format('Y-m-d'),
            $this->composeNotes($data, $from['name'] ?? null),
            $user->id,
            $userName,
            [
                'status'            => 'active',
                'accepted_by'       => $user->id,
                'accepted_by_name'  => $userName,
                'acceptance_source' => 'secretariat_receive',
                'purpose'           => 'received',
            ]
        );

        $this->markHeld($tracker, $office, $userName);
        $tracker->save();

        return $tracker->refresh();
    }

    private function markHeld(FileTracker $tracker, array $office, string $userName): void
    {
        $tracker->current_office_code = $office['code'];
        $tracker->current_office_name = $office['name'];
        $tracker->receiving_office_code = $office['code'];
        $tracker->receiving_office_name = $office['name'];
        $tracker->assignment_status = FileTracker::ASSIGNMENT_ACCEPTED;
        $tracker->assignment_accepted_at = now();
        $tracker->current_holder = $userName;
        $tracker->current_handler = $userName;
    }

    private function row(FileTracker $tracker, array $entry, array $extra): array
    {
        $meta = json_decode((string) $tracker->module_meta, true);
        $meta = is_array($meta) ? ($meta['secretariat'] ?? []) : [];

        return array_merge([
            'id'          => $tracker->id,
            'tracking_id' => $tracker->tracking_id,
            'file_number' => $tracker->file_number,
            'file_title'  => $tracker->file_title,
            'file_type'   => $tracker->file_type,
            'not_indexed' => !empty($meta['not_indexed']),
            'reference'   => $meta['reference'] ?? null,
            'purpose'     => $entry['purpose'] ?? null,
            'notes'       => $entry['notes'] ?? null,
        ], $extra);
    }

    private function composeNotes(array $data, ?string $fromName): ?string
    {
        $parts = array_filter([
            $fromName ? "From: {$fromName}" : null,
            $this->clean($data['sender'] ?? null) ? 'Sender: ' . $this->clean($data['sender']) : null,
            $this->clean($data['reference'] ?? null) ? 'Ref: ' . $this->clean($data['reference']) : null,
            $this->clean($data['notes'] ?? null),
        ]);

        return $parts ? implode(' | ', $parts) : null;
    }

    private function officeOrNull(string $code): ?array
    {
        try {
            return $this->office($code);
        } catch (RuntimeException $e) {
            return null;
        }
    }

    private function userName(User $user): string
    {
        return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? $user->email ?? 'User');
    }

    private function clean($value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
