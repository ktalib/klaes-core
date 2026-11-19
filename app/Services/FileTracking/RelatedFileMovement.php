<?php

namespace App\Services\FileTracking;

use App\Models\FileTracker;
use App\Services\FileIndexing\BlockTrackingGroup;
use App\Services\FileLocationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mail/File Movement with Related Files (user, 2026-11-19: Land wanted two files moved "together in one
 * movement"). A file's related files are those on its indexing record, the ones entered under Land FC, the
 * related-file register, its Block and its KANGIS / Land counterparts. Each related file keeps its own
 * tracker and tracking ID, so each one's location stays true; the files sent together share one movement
 * group (MVG-…) stamped on each tracker's new log entry, and the register shows them as one movement.
 *
 * A related file can go along when it is held at the sending office, or has no tracker yet (one is
 * created there first). A file anywhere else shows greyed with where it is.
 */
class RelatedFileMovement
{
    public function __construct(private SecretariatFileLogService $log)
    {
    }

    /**
     * The related files of $fileNumber, each with where it is and whether it can go along from $officeCode.
     *
     * @return array<int, array{file_number:string, file_title:?string, sources:array<int,string>, tracker_id:?int, tracking_id:?string, where:?string, can_move:bool, note:string}>
     */
    public function candidates(string $fileNumber, string $officeCode): array
    {
        $numbers = $this->relatedNumbers($fileNumber);
        if (!$numbers) return [];

        $db = DB::connection('sqlsrv');
        $keys = array_keys($numbers);
        $titles = $db->table('file_indexings')
            ->where(fn ($w) => $w->whereIn('file_number', array_column($numbers, 'file_number'))->orWhereIn('temp_file_no', array_column($numbers, 'file_number')))
            ->orderByDesc('id')->get(['file_number', 'temp_file_no', 'file_title'])
            ->reduce(function ($c, $r) {
                foreach ([$r->file_number, $r->temp_file_no] as $n) {
                    $k = self::key($n);
                    if ($k !== '' && !isset($c[$k]) && trim((string) $r->file_title) !== '') $c[$k] = trim($r->file_title);
                }
                return $c;
            }, []);

        $out = [];
        foreach ($numbers as $key => $n) {
            $tracker = $this->tracker($n['file_number']);
            [$canMove, $where, $note, $heldHere] = $this->standing($tracker, $officeCode);
            $out[] = [
                'file_number' => $n['file_number'],
                'file_title'  => $titles[$key] ?? ($tracker?->file_title ?: null),
                'sources'     => array_values(array_unique($n['sources'])),
                'tracker_id'  => $tracker?->id,
                'tracking_id' => $tracker?->tracking_id,
                'where'       => $where,
                'can_move'    => $canMove,
                // Receiving (Log a file manually): any related file not already held here can be logged in with it.
                'held_here'   => $heldHere,
                'note'        => $note,
            ];
        }
        // Files that can go along first, then by number.
        usort($out, fn ($a, $b) => [!$a['can_move'], $a['file_number']] <=> [!$b['can_move'], $b['file_number']]);

        return $out;
    }

    /**
     * After the main file was sent: each ticked related file is logged in here if it has no tracker, then
     * sent with the same details, and every tracker of the movement gets the group on its new entry.
     * Runs inside the caller's transaction, so one refusal sends nothing.
     *
     * @param  string[]  $related  ticked file numbers
     * @param  array  $send  the forward() arguments after office and tracker id
     * @return array{group:string, files:array<int,string>}
     */
    public function sendAlong(string $officeCode, FileTracker $main, array $related, array $send, \App\Models\User $user): array
    {
        $allowed = collect($this->candidates((string) $main->file_number, $officeCode))->keyBy(fn ($c) => self::key($c['file_number']));
        $trackers = [$main];
        foreach (array_unique(array_map('trim', $related)) as $number) {
            $c = $allowed->get(self::key($number));
            if (!$c) throw new \RuntimeException("{$number} is not a related file of {$main->file_number}.");
            if (!$c['can_move']) throw new \RuntimeException("{$c['file_number']} cannot go along: {$c['note']}");

            $trackerId = $c['tracker_id'];
            if (!$trackerId) {
                // No tracker yet: log it in at this office first (user, 2026-11-19).
                $created = $this->log->receive($officeCode, [
                    'entry_type'  => $c['file_title'] !== null ? SecretariatFileLogService::ENTRY_FILE : SecretariatFileLogService::ENTRY_UNINDEXED,
                    'file_number' => $c['file_number'],
                    'notes'       => 'Logged to move with related file ' . $main->file_number,
                ], $user);
                $trackerId = $created['tracker']->id;
            }
            $trackers[] = $this->log->forward($officeCode, (int) $trackerId, ...$send);
        }

        return $this->stampGroup($officeCode, $main, $trackers, $user, 'sent');
    }

    /**
     * Log a file manually, receiving: the ticked related files came with it, so each is logged in at this office
     * (closing whatever movement it was on, as a manual receipt does), all in one movement group.
     */
    public function receiveAlong(string $officeCode, FileTracker $main, array $related, \App\Models\User $user): array
    {
        $allowed = collect($this->candidates((string) $main->file_number, $officeCode))->keyBy(fn ($c) => self::key($c['file_number']));
        $trackers = [$main];
        foreach (array_unique(array_map('trim', $related)) as $number) {
            $c = $allowed->get(self::key($number));
            if (!$c) throw new \RuntimeException("{$number} is not a related file of {$main->file_number}.");
            if ($c['held_here']) throw new \RuntimeException("{$c['file_number']} is already held at your office.");
            $trackers[] = $this->log->receive($officeCode, array_filter([
                'entry_type'  => $c['file_title'] !== null ? SecretariatFileLogService::ENTRY_FILE : SecretariatFileLogService::ENTRY_UNINDEXED,
                'tracker_id'  => $c['tracker_id'],
                'file_number' => $c['file_number'],
                'notes'       => 'Received with related file ' . $main->file_number,
            ]), $user)['tracker'];
        }

        return $this->stampGroup($officeCode, $main, $trackers, $user, 'received');
    }

    /** One movement group on the newest log entry of each tracker. */
    private function stampGroup(string $officeCode, FileTracker $main, array $trackers, \App\Models\User $user, string $how): array
    {
        $group ='MVG-' . now()->format('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $members = array_map(fn ($t) => ['tracker_id' => (int) $t->id, 'file_number' => (string) $t->file_number, 'tracking_id' => (string) $t->tracking_id], $trackers);
        foreach ($trackers as $t) {
            $t = FileTracker::lockForUpdate()->find($t->id);
            $log = $t->movement_log ?: [];
            $i = count($log) - 1;
            $log[$i]['movement_group'] = $group;
            $log[$i]['movement_group_files'] = $members;
            $log[$i]['movement_group_main'] = (string) $main->file_number;
            $t->movement_log = $log;
            $t->save();
        }
        Log::info('File Movement: related files ' . $how . ' together', ['group' => $group, 'office' => $officeCode, 'files' => array_column($members, 'file_number'), 'user_id' => $user->id]);

        return ['group' => $group, 'files' => array_column($members, 'file_number')];
    }

    /**
     * The other files of the movement this tracker came on, still waiting at the receiving office: offered
     * to receive together. Empty when it came alone.
     *
     * @return array<int, array{tracker_id:int, file_number:string, tracking_id:string}>
     */
    public function waitingWith(FileTracker $tracker, string $officeCode): array
    {
        $log = $tracker->movement_log ?: [];
        $entry = null;
        // The entry it was received on (just accepted) or is still waiting on.
        for ($i = count($log) - 1; $i >= 0; $i--) {
            if (!empty($log[$i]['movement_group'])) { $entry = $log[$i]; break; }
            if (in_array(strtolower((string) ($log[$i]['status'] ?? '')), ['active', 'pending_acceptance'], true)) break;
        }
        if (!$entry) return [];

        $out = [];
        foreach ($entry['movement_group_files'] ?? [] as $m) {
            if ((int) $m['tracker_id'] === (int) $tracker->id) continue;
            $other = FileTracker::find($m['tracker_id']);
            $last = $other ? collect($other->movement_log ?: [])->last() : null;
            if ($last && ($last['movement_group'] ?? null) === $entry['movement_group']
                && strtolower((string) ($last['status'] ?? '')) === 'pending_acceptance') {
                $out[] = ['tracker_id' => (int) $other->id, 'file_number' => (string) $other->file_number, 'tracking_id' => (string) $other->tracking_id,
                          'file_title' => $other->file_title, 'to' => $last['receiving_office_name'] ?? null];
            }
        }

        return $out;
    }

    /** @return array<string, array{file_number:string, sources:array<int,string>}> keyed by upper-cased number, the file itself left out */
    public function relatedNumbers(string $fileNumber): array
    {
        $self = self::key($fileNumber);
        if ($self === '') return [];
        $db = DB::connection('sqlsrv');
        $out = [];
        $add = function ($number, string $source) use (&$out, $self, &$add) {
            // Some register rows hold several numbers in one cell ("KNGP 28, KNGP 50").
            if (str_contains((string) $number, ',')) {
                foreach (explode(',', (string) $number) as $part) $add($part, $source);
                return;
            }
            $number = trim((string) $number, " \t\n\r\0\x0B\"'[]");
            $key = self::key($number);
            if ($key === '' || $key === $self || in_array($key, ['N/A', 'NULL', '-'], true)) return;
            $out[$key] ??= ['file_number' => $number, 'sources' => []];
            $out[$key]['sources'][] = $source;
        };
        $list = function ($raw) {
            $raw = trim((string) $raw);
            if ($raw === '') return [];
            $items = json_decode($raw, true);

            return is_array($items) ? $items : preg_split('/\s*,\s*/', $raw);
        };

        // 1. The related-file register (indexing record and Land FC both write to it), both directions.
        foreach ($db->table('related_file_number')->where('file_number', $fileNumber)->get(['related_fileno', 'transaction_type']) as $r) {
            $add($r->related_fileno, $r->transaction_type ?: 'Related File');
        }
        foreach ($db->table('related_file_number')->where('related_fileno', $fileNumber)->get(['file_number', 'transaction_type']) as $r) {
            $add($r->file_number, $r->transaction_type ?: 'Related File');
        }

        // 2. The indexing record's own related files and its linked files.
        $indexings = $db->table('file_indexings')->where(fn ($w) => $w->where('file_number', $fileNumber)->orWhere('temp_file_no', $fileNumber))
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))->get(['id', 'related_fileno']);
        foreach ($indexings as $row) {
            foreach ($list($row->related_fileno) as $n) $add($n, 'Related File');
        }
        if ($indexings->isNotEmpty()) {
            foreach ($db->table('file_indexing_links')->whereIn('file_indexing_id', $indexings->pluck('id')->all())->whereNull('deleted_at')
                ->where(fn ($w) => $w->whereNull('mfile')->orWhere('mfile', '!=', 1))->pluck('file_number') as $n) {
                $add($n, 'Related File');
            }
        }

        // 3. Land FC: related file numbers entered at commissioning.
        foreach ($db->table('fileNumber')->where(fn ($w) => $w->where('mlsfNo', $fileNumber)->orWhere('kangisFileNo', $fileNumber)->orWhere('NewKANGISFileNo', $fileNumber))
            ->whereNotNull('related_fileno')->pluck('related_fileno') as $raw) {
            foreach ($list($raw) as $n) $add($n, 'Land FC');
        }

        // 4. Its Block, and its KANGIS / Land counterparts.
        try {
            $block = app(BlockTrackingGroup::class)->forFileNumber($fileNumber);
            foreach ($block['files'] ?? [] as $f) $add($f['file_number'] ?? null, 'Block');
        } catch (\Throwable $e) {
            Log::warning('Related files: block lookup failed', ['file' => $fileNumber, 'error' => $e->getMessage()]);
        }
        $resolver = app(FileLocationResolver::class);
        $indexing = \App\Models\FileIndexing::query()->where('file_number', $fileNumber)->orderByDesc('id')->first();
        foreach ($resolver->kangisContainerNumbers([$fileNumber])[$self] ?? [] as $n) $add($n, 'KANGIS file');
        foreach ($resolver->landFilesFor($fileNumber, $indexing) as $f) $add($f['file_number'] ?? null, 'Land file');

        return $out;
    }

    /** [can it go along, where it is, the line shown under it] */
    private function standing(?FileTracker $tracker, string $officeCode): array
    {
        if (!$tracker) return [true, null, 'No tracker yet: one is created at your office.', false];

        $last = collect($tracker->movement_log ?: [])->last();
        $status = strtolower((string) ($last['status'] ?? ''));
        $at = $last['receiving_office_code'] ?? $last['office_code'] ?? null;
        $name = $last['receiving_office_name'] ?? $last['office_name'] ?? $tracker->receiving_office_name;
        if ($last && $at === $officeCode && ($status === 'active' || SecretariatFileLogService::isLegacyHandOver($last))) {
            return [true, $name, 'Held at your office.', true];
        }
        if ($status === 'pending_acceptance') {
            return [false, $name, $at === $officeCode ? 'On its way to your office.' : 'In transit to ' . $name . '.', false];
        }
        if (strtoupper((string) $tracker->status) === FileTracker::STATUS_COMPLETED) {
            return [false, $name, 'Last at ' . ($name ?: 'another office') . ' (movement closed).', false];
        }

        return [false, $name, 'At ' . ($name ?: 'another office') . '.', false];
    }

    private function tracker(string $fileNumber): ?FileTracker
    {
        return FileTracker::query()
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [self::key($fileNumber)])
            ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(status,'')))) NOT IN ('CANCELLED')")
            ->orderByDesc('id')->first();
    }

    public static function key(?string $n): string
    {
        return strtoupper(trim((string) $n));
    }
}
