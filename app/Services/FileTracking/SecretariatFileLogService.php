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

    /**
     * The office a user logs files for, from the Rank and Department on their
     * account (config/file_movement.php). Null when nothing maps — such a user
     * cannot log files until an override or a mapping is added.
     *
     * @return array{code:string,name:string,department:?string,source:string}|null
     */
    public function officeFor(User $user): ?array
    {
        $map = config('file_movement');
        $rank = trim((string) $user->rank);

        $code = $map['user_offices'][$user->id] ?? null;
        $source = 'override';

        if (!$code && $rank !== '') {
            $code = collect($map['rank_offices'] ?? [])
                ->first(fn ($office, $name) => strcasecmp($name, $rank) === 0);
            $source = 'rank';
        }

        if (!$code && $user->department_id) {
            $department = DB::connection('sqlsrv')->table('departments')->where('id', $user->department_id)->value('name');
            $offices = collect($map['department_offices'] ?? [])
                ->first(fn ($o, $name) => strcasecmp($name, trim((string) $department)) === 0);
            if ($offices) {
                $isDeputy = collect($map['deputy_ranks'] ?? [])->contains(fn ($r) => strcasecmp($r, $rank) === 0);
                $code = $isDeputy ? ($offices['deputy'] ?? $offices['director']) : $offices['director'];
                $source = 'department';
            }
        }

        if (!$code) {
            return null;
        }

        try {
            return $this->office($code) + ['source' => $source];
        } catch (RuntimeException $e) {
            return null; // mapped to an office that is missing or inactive
        }
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
        if (($data['received_via'] ?? null) === 'manual') {
            if (!$this->purposeChoice($data['request_purpose_id'] ?? null, $data['request_purpose_other'] ?? null)) {
                throw new RuntimeException('Choose a request purpose, or choose Other and specify it.');
            }
            if (!$this->officerChoice($data['receiving_officer_id'] ?? null, $data['receiving_officer_other'] ?? null)) {
                throw new RuntimeException('Choose a receiving officer, or choose Other and specify the name.');
            }
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
                $this->receiptOptions($data, $user, $userName)
            );

            $this->markHeld($tracker, $office, $userName);
            $this->stampPurpose($tracker, $data);
            if (strtoupper((string) $tracker->status) === FileTracker::STATUS_COMPLETED) {
                $tracker->status = FileTracker::STATUS_ACTIVE;
            }
            $tracker->save();

            return ['tracker' => $tracker->refresh(), 'action' => 'logged_in'];
        });
    }

    /**
     * Active MLPP staff, as offered by the main Log a File page, with their
     * department name so the page can list the destination's people first.
     */
    public function receivingOfficers(): Collection
    {
        return DB::connection('sqlsrv')->table('users as u')
            ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->where('u.is_active', 1)
            ->where('u.staff_type_category', 'MLPP')
            ->orderBy('u.first_name')->orderBy('u.last_name')
            ->get(['u.id', 'u.first_name', 'u.last_name', 'u.rank', 'u.username', 'd.name as department'])
            ->map(fn ($o) => [
                'id'         => (int) $o->id,
                'name'       => trim($o->first_name . ' ' . $o->last_name),
                'rank'       => $o->rank,
                // Several staff share a name; the username tells them apart.
                'username'   => $o->username,
                'department' => $o->department,
            ]);
    }

    /** @return FileTracker */
    public function forward(string $officeCode, int $trackerId, string $toOfficeCode, ?int $purposeId, ?int $officerId, ?string $notes, User $user,
                            ?string $purposeOther = null, ?string $officerOther = null): FileTracker
    {
        $office = $this->office($officeCode);
        $to = $this->office($toOfficeCode);

        // {id, name}; id is null for an "Other" purpose / officer typed by hand.
        $purpose = $this->purposeChoice($purposeId, $purposeOther);
        if (!$purpose) {
            throw new RuntimeException('Choose a request purpose, or choose Other and specify it.');
        }
        $officer = $this->officerChoice($officerId, $officerOther);
        if (!$officer) {
            throw new RuntimeException('Choose a receiving officer, or choose Other and specify the name.');
        }
        $officerName = $officer->name;

        if ($to['code'] === $office['code']) {
            throw new RuntimeException('Choose a different office to send the file to.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($office, $to, $trackerId, $purpose, $officer, $officerName, $notes, $user) {
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
                    'status'                 => 'pending_acceptance',
                    'requires_acceptance'    => true,
                    'purpose'                => $purpose->name,
                    'receiving_office_code'  => $to['code'],
                    'receiving_office_name'  => $to['name'],
                    'receiving_officer_id'   => $officer->id,
                    'receiving_officer_name' => $officerName,
                ]
            );

            // addMovementLog only keeps known keys — stamp the sender on the new entry.
            $log = $tracker->movement_log;
            $log[count($log) - 1]['sent_from_office_code'] = $office['code'];
            $log[count($log) - 1]['sent_from_office_name'] = $office['name'];
            $tracker->movement_log = $log;

            $tracker->receiving_office_code = $to['code'];
            $tracker->receiving_office_name = $to['name'];
            $tracker->receiving_officer_id = $officer->id;
            $tracker->receiving_officer_name = $officerName;
            $tracker->request_purpose_id = $purpose->id;
            $tracker->request_purpose_name = $purpose->name;
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
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('CANCELLED', 'COMPLETED')")
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        // A file can have several trackers (commissioning, each log-out, re-logs).
        // Only the newest says where the file is now; an older cycle that still ends
        // in a pending hand-over would otherwise list the same file as both
        // "pending" and "held".
        $newestIds = $this->newestTrackerIds($atMyOffice->pluck('file_number'));

        $pending = [];
        $held = [];
        foreach ($atMyOffice as $tracker) {
            $key = strtoupper(trim((string) $tracker->file_number));
            if ($key !== '' && isset($newestIds[$key]) && (int) $newestIds[$key] !== (int) $tracker->id) {
                continue;
            }
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

    /** @return array<string,int> upper-cased file number => newest live tracker id */
    private function newestTrackerIds(Collection $fileNumbers): array
    {
        $numbers = $fileNumbers->filter(fn ($n) => trim((string) $n) !== '')->map(fn ($n) => trim($n))->unique()->values();
        $out = [];

        foreach ($numbers->chunk(500) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('file_tracker')
                ->whereIn('file_number', $chunk->all())
                ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('CANCELLED')")
                ->groupBy('file_number')
                ->selectRaw('file_number, MAX(id) AS id')
                ->get();
            foreach ($rows as $row) {
                $key = strtoupper(trim((string) $row->file_number));
                $out[$key] = max((int) $row->id, $out[$key] ?? 0);
            }
        }

        return $out;
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
            // The newest tracker is the file's current cycle — the same choice
            // Quick Search (FileTrackerApiController::track) and the profile make.
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Indexed files matching a file number or title (read-only), newest first;
     * the most recently indexed files when the search is empty.
     */
    public function searchIndexed(string $q, int $limit = 25): array
    {
        $q = trim($q);
        $query = DB::connection('sqlsrv')->table('file_indexings')
            ->select('id', 'file_number', 'file_title')
            ->whereNotNull('file_number')->where('file_number', '<>', '')
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0));

        if ($q !== '') {
            $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $q) . '%';
            $query->where(fn ($w) => $w->where('file_number', 'like', $like)->orWhere('file_title', 'like', $like));
            // Exact file number first, then numbers starting with the search, then the rest.
            $prefix = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $q) . '%';
            $query->orderByRaw('CASE WHEN file_number = ? THEN 0 WHEN file_number LIKE ? THEN 1 ELSE 2 END', [$q, $prefix]);
        }

        return $query->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'file_number' => trim($r->file_number), 'file_title' => $r->file_title])
            ->all();
    }

    private function createAtOffice(array $office, array $data, User $user, string $entryType, ?string $fileNumber): FileTracker
    {
        $now = now();
        $userName = $this->userName($user);
        // An indexed file keeps its indexed title; the form's field is locked for it.
        $indexedTitle = ($entryType === self::ENTRY_FILE && $fileNumber)
            ? $this->clean(DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNumber)->orderByDesc('id')->value('file_title'))
            : null;
        $title = $indexedTitle ?: ($this->clean($data['file_title'] ?? null) ?: ($fileNumber ?: 'Untitled document'));
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
            // The file a letter/memo relates to. Kept here, not in file_number, so the
            // document never stands in for that file's own tracker.
            'related_file_number' => $entryType === self::ENTRY_NON_FILE ? $this->clean($data['related_file_number'] ?? null) : null,
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
            $this->receiptOptions($data, $user, $userName)
        );

        $this->markHeld($tracker, $office, $userName);
        $this->stampPurpose($tracker, $data);
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
            'related_file_number' => $meta['related_file_number'] ?? null,
            // 'received' is the internal tag on a receipt entry, not a purpose.
            'purpose'     => (($entry['purpose'] ?? null) && strtolower($entry['purpose']) !== 'received')
                ? $entry['purpose']
                : $tracker->request_purpose_name,
            'notes'       => $entry['notes'] ?? null,
        ], $extra);
    }

    /** Identity of one stored log entry, stable across reads. */
    public static function logKey(array $entry): string
    {
        return md5(implode('|', [
            $entry['log_id'] ?? '',
            $entry['timestamp'] ?? '',
            $entry['office_code'] ?? '',
            $entry['log_in_date'] ?? '',
            $entry['log_in_time'] ?? '',
        ]));
    }

    /**
     * Admin clean-up: remove one log entry from a tracker's movement log.
     *
     * The full before-image is written to the audit file first. Removing the
     * newest entry makes the one before it the file's current location again
     * (reopened as held there). A tracker's only entry is never removed, so no
     * empty tracker is left behind.
     */
    public function deleteLogEntry(int $trackerId, int $index, string $key, string $reason, User $user): FileTracker
    {
        $reason = $this->clean($reason);
        if (!$reason) {
            throw new RuntimeException('Give a reason for deleting the log.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($trackerId, $index, $key, $reason, $user) {
            $tracker = FileTracker::lockForUpdate()->find($trackerId);
            if (!$tracker) {
                throw new RuntimeException('File tracker not found.');
            }

            $log = array_values($tracker->movement_log ?: []);
            if (!isset($log[$index]) || !is_array($log[$index]) || self::logKey($log[$index]) !== $key) {
                throw new RuntimeException('This log has changed since the page loaded. Reload the file and try again.');
            }
            if (count($log) === 1) {
                throw new RuntimeException("This is the tracker's only log, so it cannot be deleted here.");
            }

            $before = [
                'movement_log'           => $log,
                'status'                 => $tracker->status,
                'current_office_code'    => $tracker->current_office_code,
                'current_office_name'    => $tracker->current_office_name,
                'receiving_office_code'  => $tracker->receiving_office_code,
                'receiving_office_name'  => $tracker->receiving_office_name,
                'receiving_officer_id'   => $tracker->receiving_officer_id,
                'receiving_officer_name' => $tracker->receiving_officer_name,
                'assignment_status'      => $tracker->assignment_status,
                'current_holder'         => $tracker->current_holder,
                'current_handler'        => $tracker->current_handler,
            ];
            $removed = $log[$index];
            $wasNewest = $index === count($log) - 1;
            array_splice($log, $index, 1);

            if ($wasNewest) {
                $lastIndex = count($log) - 1;
                $last = $log[$lastIndex];
                // The step it left by the deleted movement is where the file is again.
                if (strtolower((string) ($last['status'] ?? '')) === 'completed') {
                    unset($last['log_out_date'], $last['log_out_time'], $last['completion_notes']);
                    $last['status'] = 'active';
                    $log[$lastIndex] = $last;
                }
                $status = strtolower((string) ($last['status'] ?? ''));
                $code = $last['receiving_office_code'] ?? $last['office_code'] ?? null;
                $name = $last['receiving_office_name'] ?? $last['office_name'] ?? null;
                $tracker->current_office_code = $code;
                $tracker->current_office_name = $name;
                $tracker->receiving_office_code = $code;
                $tracker->receiving_office_name = $name;
                $tracker->receiving_officer_id = $last['receiving_officer_id'] ?? null;
                $tracker->receiving_officer_name = $last['receiving_officer_name'] ?? null;
                if ($status === 'pending_acceptance') {
                    $tracker->assignment_status = FileTracker::ASSIGNMENT_PENDING;
                    $tracker->assignment_accepted_at = null;
                } elseif ($status === 'active') {
                    $tracker->assignment_status = FileTracker::ASSIGNMENT_ACCEPTED;
                    if (strtoupper((string) $tracker->status) === FileTracker::STATUS_COMPLETED) {
                        $tracker->status = FileTracker::STATUS_ACTIVE;
                    }
                }
                $holder = $last['accepted_by_name'] ?? $last['user_name'] ?? null;
                if ($holder) {
                    $tracker->current_holder = $holder;
                    $tracker->current_handler = $holder;
                }
            }

            $tracker->movement_log = $log;
            $tracker->total_offices = count($log);

            // Audit first: if the copy cannot be written, nothing is deleted.
            $this->auditDeletion([
                'at'          => now()->toIso8601String(),
                'by_user_id'  => $user->id,
                'by_user'     => $this->userName($user),
                'reason'      => $reason,
                'tracker_id'  => $tracker->id,
                'tracking_id' => $tracker->tracking_id,
                'file_number' => $tracker->file_number,
                'index'       => $index,
                'removed'     => $removed,
                'before'      => $before,
                'after_movement_log' => $log,
            ]);

            $tracker->save();

            return $tracker->refresh();
        });
    }

    /**
     * Admin clean-up: delete a whole tracker, as the main Log a File page does.
     * The full row is written to the audit file first (so it can be restored).
     * An index record pointing at it is cleared the same way the main page does
     * when it removes a tracker (FileTrackerApiController::destroyLogEntry), so
     * the file falls back to its resolved location; the old values are audited.
     * A tracker that any other table points at is refused.
     */
    public function deleteTracker(int $trackerId, string $reason, User $user): array
    {
        $reason = $this->clean($reason);
        if (!$reason) {
            throw new RuntimeException('Give a reason for deleting the tracker.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($trackerId, $reason, $user) {
            $db = DB::connection('sqlsrv');
            $row = $db->table('file_tracker')->lockForUpdate()->where('id', $trackerId)->first();
            if (!$row) {
                throw new RuntimeException('File tracker not found.');
            }

            $linked = [];
            foreach (['kangis_checkout_approvals', 'indexing_duplicates', 'file_tracker_department_backfill'] as $table) {
                $n = $db->table($table)->where('file_tracker_id', $trackerId)->count();
                if ($n) {
                    $linked[] = "{$table} ({$n})";
                }
            }
            if ($linked) {
                throw new RuntimeException('This tracker is linked from ' . implode(', ', $linked)
                    . ', so it cannot be deleted here. Delete its logs one by one instead.');
            }

            $indexingLinks = $db->table('file_indexings')->where('file_tracker_id', $trackerId)
                ->get(['id', 'file_number', 'file_tracker_id', 'tracking_status', 'location_status_manual']);

            $this->auditDeletion([
                'type'        => 'tracker',
                'at'          => now()->toIso8601String(),
                'by_user_id'  => $user->id,
                'by_user'     => $this->userName($user),
                'reason'      => $reason,
                'tracker_id'  => $row->id,
                'tracking_id' => $row->tracking_id,
                'file_number' => $row->file_number,
                'row'         => (array) $row,
                'indexing_links_cleared' => $indexingLinks->map(fn ($r) => (array) $r)->all(),
            ]);

            if ($indexingLinks->isNotEmpty()) {
                $db->table('file_indexings')->where('file_tracker_id', $trackerId)->update([
                    'file_tracker_id'        => null,
                    'tracking_status'        => null,
                    'location_status_manual' => null,
                ]);
            }
            $db->table('file_tracker')->where('id', $trackerId)->delete();

            return ['id' => (int) $row->id, 'file_number' => $row->file_number, 'file_title' => $row->file_title];
        });
    }

    private function auditDeletion(array $record): void
    {
        $dir = storage_path('app/audits');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create the audit folder; nothing was deleted.');
        }
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        if (file_put_contents($dir . '/file-movement-log-deletions.jsonl', $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Could not write the audit copy; nothing was deleted.');
        }
        \Illuminate\Support\Facades\Log::info(($record['type'] ?? '') === 'tracker' ? 'File movement tracker deleted' : 'File movement log deleted', [
            'tracker_id' => $record['tracker_id'], 'index' => $record['index'] ?? null, 'by' => $record['by_user_id'], 'reason' => $record['reason'],
        ]);
    }

    /**
     * Movement options for a file received at my office. A manual log names the
     * officer who took it and the request purpose; a scan receipt has neither.
     */
    private function receiptOptions(array $data, User $user, string $userName): array
    {
        $options = [
            'status'              => 'active',
            'requires_acceptance' => false,
            'accepted_by'         => $user->id,
            'accepted_by_name'    => $userName,
            'acceptance_source'   => 'secretariat_receive',
            'purpose'             => 'received',
        ];

        if ($purpose = $this->purposeChoice($data['request_purpose_id'] ?? null, $data['request_purpose_other'] ?? null)) {
            $options['purpose'] = $purpose->name;
        }
        if ($officer = $this->officerChoice($data['receiving_officer_id'] ?? null, $data['receiving_officer_other'] ?? null)) {
            $options['receiving_officer_id'] = $officer->id;
            $options['receiving_officer_name'] = $officer->name;
        }

        return $options;
    }

    /**
     * A request purpose from the list, or "Other" typed by hand (id null), as on
     * Quick Search. Null when neither is given.
     */
    private function purposeChoice($id, ?string $other): ?object
    {
        if (!empty($id)) {
            $purpose = \App\Models\RequestPurpose::find((int) $id);
            if (!$purpose) {
                throw new RuntimeException('That request purpose no longer exists.');
            }
            return (object) ['id' => $purpose->id, 'name' => $purpose->name];
        }
        $other = $this->clean($other);

        return $other ? (object) ['id' => null, 'name' => mb_substr($other, 0, 255)] : null;
    }

    /** An active officer from the list, or "Other" typed by hand (id null). */
    private function officerChoice($id, ?string $other): ?object
    {
        if (!empty($id)) {
            $officer = User::find((int) $id);
            if (!$officer || !$officer->is_active) {
                throw new RuntimeException('Choose an active receiving officer.');
            }
            return (object) ['id' => $officer->id, 'name' => $this->userName($officer)];
        }
        $other = $this->clean($other);

        return $other ? (object) ['id' => null, 'name' => mb_substr($other, 0, 255)] : null;
    }

    private function stampPurpose(FileTracker $tracker, array $data): void
    {
        if ($purpose = $this->purposeChoice($data['request_purpose_id'] ?? null, $data['request_purpose_other'] ?? null)) {
            $tracker->request_purpose_id = $purpose->id;
            $tracker->request_purpose_name = $purpose->name;
        }
        if ($officer = $this->officerChoice($data['receiving_officer_id'] ?? null, $data['receiving_officer_other'] ?? null)) {
            $tracker->receiving_officer_id = $officer->id;
            $tracker->receiving_officer_name = $officer->name;
        }
    }

    private function composeNotes(array $data, ?string $fromName): ?string
    {
        $fromName = $this->clean($data['from_office_other'] ?? null) ?: $fromName;
        $parts = array_filter([
            $fromName ? "From: {$fromName}" : null,
            $this->clean($data['sender'] ?? null) ? 'Sender: ' . $this->clean($data['sender']) : null,
            $this->clean($data['reference'] ?? null) ? 'Ref: ' . $this->clean($data['reference']) : null,
            ($data['entry_type'] ?? null) === self::ENTRY_NON_FILE && $this->clean($data['related_file_number'] ?? null)
                ? 'File: ' . $this->clean($data['related_file_number']) : null,
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
