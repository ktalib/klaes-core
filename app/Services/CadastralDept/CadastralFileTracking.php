<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\FileTracker;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FileCommissioningTrackingService;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * File movement logging for the Cadastral Department (user, 2026-10-27).
 *
 * Every move between offices is written to the Ministry-wide tracker
 * (file_tracker.movement_log), in the same shape the Secretariat file log and Log a File
 * use, so one file keeps one trail across modules:
 *
 *   registered      the file is received (active) at the Cadastral Registry.
 *   sent            the line it was on is closed and a pending_acceptance line opens at the
 *                   next unit's office; the case waits for that unit to accept it.
 *   accepted        the unit accepts (button, or by scanning the file's QR): the pending line
 *                   turns active, and only then can the unit send it on (CaseWorkflow::advance).
 *   back            a file returning from another department (Land, Survey, PS...) is received
 *                   by the Cadastral desk that records the return: accepted at once.
 *
 * Desks map to offices in config cadastral_dept.desk_offices. A desk with no office closes the
 * current line, and the file is logged again where it next lands. Moves on the same office
 * (the next step on one desk, head approval) write nothing.
 *
 * The tracker: the case's own (file_tracker_id), else the file's newest one (the choice Quick
 * Search and the Secretariat log make). A file with none gets one: a KLAES-commissioned 2026 file
 * through DIIT (its commissioning line opens the history), anything else a new tracker that
 * keeps the tracking ID already printed on its QR (indexing / fileNumber), so later scans find it.
 *
 * Runs inside CaseWorkflow's transaction, fenced by a savepoint: a tracker failure is logged
 * and never blocks the workflow.
 */
class CadastralFileTracking
{
    public const MODULE = 'cadastral';

    private static ?bool $installed = null;

    public function __construct(private AuditService $audit)
    {
    }

    /** The acceptance columns exist (migration 2026_10_27_130000). */
    public static function installed(): bool
    {
        return self::$installed ??= Schema::connection('sqlsrv')->hasColumn('cadastral_cases', 'accepted_at');
    }

    /**
     * Files sent to a Cadastral unit are accepted there automatically (user, 2026-10-27: the
     * officers complained about the Accept step). The tracker still logs the send and the arrival.
     * Config cadastral_dept.auto_accept = false brings the manual Accept step back.
     */
    public static function autoAccept(): bool
    {
        return (bool) config('cadastral_dept.auto_accept', true);
    }

    /** The case is at an internal desk that has not accepted it yet (never, while auto-accept is on). */
    public static function awaiting(CadastralCase $case): bool
    {
        if (self::autoAccept() || !self::installed() || !$case->isOpen() || $case->accepted_at) {
            return false;
        }
        $desk = (new WorkflowDefinition())->desks()[$case->current_desk] ?? [];

        return !empty($desk['internal']);
    }

    /** Called by CaseWorkflow::move() for every movement row. */
    public function moved(CadastralCase $case, ?string $from, ?string $to, string $outcome, ?int $userId, ?string $fromDesk = null): void
    {
        if (!self::installed()) {
            return;
        }
        $def = new WorkflowDefinition();
        $wf = $case->workflow;
        $fromDesk = $from ? $def->step($wf, $from)['desk'] : $fromDesk;
        $toDesk = $to ? $def->step($wf, $to)['desk'] : null;
        if ($to === null || ($from !== null && $fromDesk === $toDesk)) {
            return;   // closed, or the same desk (next step, head approval): the file has not moved
        }
        $toInternal = !empty($def->desks()[$toDesk]['internal']);
        $fromExternal = $from && empty($def->desks()[$fromDesk]['internal']);
        $user = $userId ? User::find($userId) : null;

        // Registration, or back from another department: the Cadastral officer has the file in hand.
        $receivedNow = $from === null || ($fromExternal && $toInternal);
        $auto = $toInternal && !$receivedNow && self::autoAccept();
        $case->forceFill(match (true) {
            $receivedNow || !$toInternal => ['accepted_at' => now(), 'accepted_by' => $userId],
            $auto => ['accepted_at' => now(), 'accepted_by' => null],   // auto-accepted on arrival
            default => ['accepted_at' => null, 'accepted_by' => null],
        })->save();

        $this->fenced($case, function () use ($case, $def, $fromDesk, $toDesk, $receivedNow, $user, $from, $auto) {
            $tracker = $this->trackerFor($case, $user);
            $toOffice = $this->office($toDesk);
            if ($receivedNow) {
                if ($toOffice) {
                    $this->receive($tracker, $toOffice, $user, $from === null
                        ? "Registered in Cadastral ({$case->case_ref})"
                        : 'Back from ' . $def->deskLabel($fromDesk) . " ({$case->case_ref})");
                }
                return;
            }
            $this->send($tracker, $this->office($fromDesk), $toOffice, $user,
                'Sent to ' . $def->deskLabel($toDesk) . " ({$case->case_ref})");
            if ($auto && $toOffice) {
                $this->receive($tracker, $toOffice, null, "Auto-accepted at {$def->deskShort($toDesk)} ({$case->case_ref})");
            }
        });
    }

    /** The unit accepts the file it was sent. */
    public function accept(CadastralCase $case, ?int $userId, string $via = 'button'): string
    {
        if (!self::awaiting($case)) {
            throw WorkflowGuardException::because("{$case->case_ref} is not waiting to be accepted.");
        }

        return DB::connection('sqlsrv')->transaction(function () use ($case, $userId, $via) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            if (!self::awaiting($case)) {
                return "{$case->case_ref} was already accepted.";
            }
            $case->forceFill(['accepted_at' => now(), 'accepted_by' => $userId])->save();
            $def = new WorkflowDefinition();
            $user = $userId ? User::find($userId) : null;
            $this->fenced($case, function () use ($case, $user, $def, $via) {
                if ($office = $this->office($case->current_desk)) {
                    $this->receive($this->trackerFor($case, $user), $office, $user,
                        "Accepted at {$def->deskShort($case->current_desk)} ({$case->case_ref})" . ($via === 'scan' ? ', by QR scan' : ''));
                }
            });
            $this->audit->logAction('Cadastral File Accepted', 'CadastralCase', $case->id, null,
                ['desk' => $case->current_desk, 'via' => $via], "{$case->case_ref} accepted at " . $def->deskShort($case->current_desk));

            return "{$case->case_ref} accepted at " . $def->deskShort($case->current_desk) . '.';
        });
    }

    /** @return array{code:string,name:string,department:?string}|null */
    public function office(?string $desk): ?array
    {
        $code = $desk ? (config('cadastral_dept.desk_offices', [])[$desk] ?? null) : null;
        if (!$code) {
            return null;
        }
        $row = DB::connection('sqlsrv')->table('offices')->where('office_code', $code)->where('is_active', 1)
            ->first(['office_code', 'office_name', 'department']);

        return $row ? ['code' => $row->office_code, 'name' => trim($row->office_name), 'department' => $row->department] : null;
    }

    private function fenced(CadastralCase $case, callable $work): void
    {
        try {
            DB::connection('sqlsrv')->transaction(fn () => $work());
        } catch (\Throwable $e) {
            Log::warning('Cadastral file tracking failed', ['case' => $case->case_ref, 'error' => $e->getMessage()]);
        }
    }

    private function trackerFor(CadastralCase $case, ?User $user): FileTracker
    {
        if ($case->file_tracker_id && ($t = FileTracker::lockForUpdate()->find($case->file_tracker_id))) {
            return $t;
        }
        $t = FileTracker::lockForUpdate()
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [strtoupper(trim((string) $case->file_number))])
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('CANCELLED')")
            ->orderByDesc('id')->first();

        if (!$t) {
            $diit = app(FileCommissioningTrackingService::class);
            $info = $diit->infoFor($case->file_number);
            $t = $info ? $diit->materialize($info) : $this->create($case, $user);
        }
        $case->forceFill(array_filter([
            'file_tracker_id' => $t->id,
            'tracking_id' => $case->tracking_id ?: $t->tracking_id,
        ]))->save();

        return $t;
    }

    /** A new tracker for a file that has none, keeping the tracking ID already on its QR. */
    private function create(CadastralCase $case, ?User $user): FileTracker
    {
        $db = DB::connection('sqlsrv');
        $existing = $case->tracking_id
            ?: ($case->file_indexing_id ? $db->table('file_indexings')->where('id', $case->file_indexing_id)->value('tracking_id') : null)
            ?: $db->table('fileNumber')->where(fn ($q) => $q->where('mlsfNo', $case->file_number)->orWhere('kangisFileNo', $case->file_number)
                ->orWhere('NewKANGISFileNo', $case->file_number)->orWhere('st_file_no', $case->file_number))
                ->whereNotNull('tracking_id')->where('tracking_id', '!=', '')->value('tracking_id');
        $now = now();
        $registry = $this->office('registry');
        $t = new FileTracker([
            'tracking_id' => trim((string) $existing) ?: FileTracker::generateTrackingId(),
            'file_number' => $case->file_number,
            'file_title' => mb_substr((string) ($case->file_title ?: $case->file_number), 0, 255),
            'file_type' => 'File',
            'priority' => FileTracker::PRIORITY_MEDIUM,
            'created_by' => $user?->id,
            'created_by_name' => $this->name($user),
            'department' => 'Cadastral',
            'description' => "Tracking opened by the Cadastral Department ({$case->case_ref}).",
            'status' => FileTracker::STATUS_ACTIVE,
            'date_created' => $now,
            'total_offices' => 0,
            'origin_office_code' => $registry['code'] ?? null,
            'origin_office_name' => $registry['name'] ?? null,
            'origin_office_department' => 'Cadastral',
            'module' => self::MODULE,
        ]);
        $t->movement_log = [];
        $t->save();

        return $t;
    }

    /** The file arrives at $office: accept a pending hand-over in place, or log it in. */
    private function receive(FileTracker $t, array $office, ?User $user, string $note): void
    {
        $log = $t->movement_log ?: [];
        $i = count($log) - 1;
        $last = $i >= 0 ? $log[$i] : null;
        $status = strtolower((string) ($last['status'] ?? ''));
        $at = $last['receiving_office_code'] ?? $last['office_code'] ?? null;
        $now = now();
        $name = $user ? $this->name($user) : 'Auto-accepted on arrival';

        if ($last && $at === $office['code'] && $status === 'active') {
            return;   // already held here
        }
        if ($last && $at === $office['code'] && $status === 'pending_acceptance') {
            $log[$i] = array_merge($last, $this->where($office), [
                'log_in_time' => $now->format('H:i'), 'log_in_date' => $now->format('Y-m-d'),
                'status' => 'active', 'accepted_by' => $user?->id, 'accepted_by_name' => $name,
                'accepted_at' => $now->toIso8601String(), 'acceptance_source' => $user ? 'cadastral_dept' : 'cadastral_auto', 'acceptance_note' => $note,
            ], $user ? ['receiving_officer_id' => $user->id, 'receiving_officer_name' => $name] : []);
            $t->movement_log = $log;
        } else {
            if ($last && in_array($status, ['active', 'pending_acceptance', 'pending'], true)) {
                $t->completeCurrentMovement($now->format('H:i'), 'Received at ' . $office['name']);
            }
            $t->addMovementLog($office['code'], $office['name'], $now->format('H:i'), $now->format('Y-m-d'), $note,
                $user?->id, $name, ['status' => 'active', 'accepted_by' => $user?->id, 'accepted_by_name' => $name,
                    'acceptance_source' => $user ? 'cadastral_dept' : 'cadastral_auto', 'acceptance_note' => $note,
                    'receiving_officer_id' => $user?->id, 'receiving_officer_name' => $user ? $name : null, 'requires_acceptance' => false]);
            $log = $t->movement_log;
            $log[count($log) - 1] = array_merge($log[count($log) - 1], $this->where($office));
            $t->movement_log = $log;
        }
        $this->held($t, $office, $name);
        $t->save();
    }

    /** The file leaves $from for $to (pending until $to accepts). A null office closes the line only. */
    private function send(FileTracker $t, ?array $from, ?array $to, ?User $user, string $note): void
    {
        $log = $t->movement_log ?: [];
        $last = $log ? end($log) : null;
        $status = strtolower((string) ($last['status'] ?? ''));
        $now = now();
        $name = $this->name($user);

        // Not logged at the sending office yet (an older trail): log it there first, so the
        // trail shows where it left from.
        $at = $last['receiving_office_code'] ?? $last['office_code'] ?? null;
        if ($from && ($at !== $from['code'] || !in_array($status, ['active', 'pending_acceptance'], true))) {
            $this->receive($t, $from, $user, $note);
        }
        // Auto-accepted on arrival, nobody was named: the officer sending it on is the one who had it.
        $log = $t->movement_log ?: [];
        if ($log && $user && empty($log[count($log) - 1]['receiving_officer_name'])) {
            $log[count($log) - 1]['receiving_officer_id'] = $user->id;
            $log[count($log) - 1]['receiving_officer_name'] = $name;
            $t->movement_log = $log;
        }
        $t->completeCurrentMovement($now->format('H:i'), $note);
        if (!$to) {
            $t->save();
            return;
        }
        $t->addMovementLog($to['code'], $to['name'], null, null, $note, $user?->id, $name, [
            'status' => 'pending_acceptance', 'requires_acceptance' => true,
            'receiving_office_code' => $to['code'], 'receiving_office_name' => $to['name'],
        ]);
        $log = $t->movement_log;
        $log[count($log) - 1]['sent_from_office_code'] = $from['code'] ?? null;
        $log[count($log) - 1]['sent_from_office_name'] = $from['name'] ?? null;
        $log[count($log) - 1] = array_merge($log[count($log) - 1], $this->where($to), [
            'origin_office_code' => $from['code'] ?? null, 'origin_office_name' => $from['name'] ?? null,
            'sent_from_department' => $from['department'] ?? null,
        ]);
        $t->movement_log = $log;
        $t->receiving_office_code = $to['code'];
        $t->receiving_office_name = $to['name'];
        $t->assignment_status = FileTracker::ASSIGNMENT_PENDING;
        $t->assignment_accepted_at = null;
        if (strtoupper((string) $t->status) === FileTracker::STATUS_COMPLETED) {
            $t->status = FileTracker::STATUS_ACTIVE;
        }
        $t->save();
    }

    /** The department fields every Log a File entry carries (department = the office's own). */
    private function where(?array $office): array
    {
        return $office ? ['department' => $office['department'] ?? null, 'origin_office_department' => $office['department'] ?? null] : [];
    }

    private function held(FileTracker $t, array $office, string $name): void
    {
        $name = $name === 'Auto-accepted on arrival' ? $office['name'] : $name;   // nobody named yet: held at the office
        $t->current_office_code = $office['code'];
        $t->current_office_name = $office['name'];
        $t->receiving_office_code = $office['code'];
        $t->receiving_office_name = $office['name'];
        $t->assignment_status = FileTracker::ASSIGNMENT_ACCEPTED;
        $t->assignment_accepted_at = now();
        $t->current_holder = $name;
        $t->current_handler = $name;
        if (strtoupper((string) $t->status) === FileTracker::STATUS_COMPLETED) {
            $t->status = FileTracker::STATUS_ACTIVE;
        }
    }

    private function name(?User $user): string
    {
        return trim((string) ($user?->name ?? trim(($user?->first_name ?? '') . ' ' . ($user?->surname ?? '')))) ?: 'System';
    }
}
