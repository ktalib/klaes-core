<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyBeaconRegister;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * The public beacon numbers still to be issued (CR 03112026 item 14). Public prefixes run A, B ... Z,
 * AA ... ZZ, AAA ... and the pool starts at KDJ 0001 (user, 2026-11-05): anything older is only ever
 * captured by hand. Each prefix holds 0001 to 9999, one row per number.
 *
 * Rows are filled ahead of time by survey:beacon-pool in small chunks, so nobody waits at the Issue
 * window for a new prefix. A number stays in the pool once issued (status Issued); the register is
 * still what records it, and ux_survey_beacon_numbers_live is still the last guard against a duplicate.
 */
class BeaconPool
{
    public const START_PREFIX = 'KDJ';
    public const MAX_SEQ = 9999;
    /** When the free numbers drop below this, the next prefix is filled. */
    public const LOW_WATER = 500;
    /** Rows per insert: 8 columns x 250 stays under sqlsrv's 2,100 parameters, and short (no snapshot isolation). */
    public const CHUNK = 250;

    public const AVAILABLE = 'Available';
    public const ISSUED = 'Issued';

    private static ?bool $installed = null;

    public static function installed(): bool
    {
        return self::$installed ??= Schema::connection('sqlsrv')->hasTable('survey_beacon_pool');
    }

    private static function db()
    {
        return DB::connection('sqlsrv');
    }

    /** The prefix after this one: KDJ -> KDK, KDZ -> KEA, Z -> AA, ZZZ -> AAAA. */
    public static function nextPrefix(string $prefix): string
    {
        $letters = str_split(strtoupper($prefix));
        for ($i = count($letters) - 1; $i >= 0; $i--) {
            if ($letters[$i] !== 'Z') {
                $letters[$i] = chr(ord($letters[$i]) + 1);

                return implode('', $letters);
            }
            $letters[$i] = 'A';
        }

        return 'A' . implode('', $letters);
    }

    /** The stored number, no space: KDJ0001. Screens show it through SurveyBeaconRegister::display(). */
    public static function number(string $prefix, int $seq): string
    {
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** Prefixes filled so far, in issue order, with what is left on each. */
    public static function prefixes(): array
    {
        if (! self::installed()) return [];

        return self::db()->table('survey_beacon_pool')
            ->selectRaw('prefix, COUNT(*) AS total, MAX(seq) AS last_seq, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS available', [self::AVAILABLE])
            ->groupBy('prefix')->orderByRaw('LEN(prefix)')->orderBy('prefix')->get()->all();
    }

    /** The prefix numbers are coming from now, and how many free numbers there are in all. */
    public static function status(): array
    {
        $open = null;
        $available = 0;
        foreach (self::prefixes() as $p) {
            $available += (int) $p->available;
            if (! $open && (int) $p->available > 0) $open = $p->prefix;
        }

        return ['prefix' => $open, 'available' => $available];
    }

    /** The prefix the filler should load next, or null while the pool still has plenty. */
    public static function prefixToFill(): ?string
    {
        $all = self::prefixes();
        if (! $all) return self::START_PREFIX;

        $last = end($all);
        // A prefix interrupted part way through is finished first.
        if ((int) $last->last_seq < self::MAX_SEQ) return $last->prefix;

        return array_sum(array_map(fn ($p) => (int) $p->available, $all)) < self::LOW_WATER
            ? self::nextPrefix($last->prefix) : null;
    }

    /**
     * Load the serials of a prefix not yet in the pool, in short chunks with no wrapping transaction.
     * A number already in the register (captured by hand) goes in as Issued, so it is never offered.
     *
     * @return array{prefix:string, added:int, already_issued:int}
     */
    public static function fill(string $prefix): array
    {
        $prefix = strtoupper($prefix);
        $db = self::db();
        $have = (int) $db->table('survey_beacon_pool')->where('prefix', $prefix)->max('seq');
        $added = 0;
        $taken = 0;

        for ($start = $have + 1; $start <= self::MAX_SEQ; $start += self::CHUNK) {
            $end = min(self::MAX_SEQ, $start + self::CHUNK - 1);
            $used = self::registerLines($prefix, $start, $end);
            $now = now();
            $rows = [];
            for ($seq = $start; $seq <= $end; $seq++) {
                $line = $used[$seq] ?? null;
                $rows[] = [
                    'prefix' => $prefix, 'seq' => $seq, 'beacon_no' => self::number($prefix, $seq),
                    'status' => $line ? self::ISSUED : self::AVAILABLE,
                    'survey_beacon_register_id' => $line,
                    'issued_at' => $line ? $now : null,
                    'created_at' => $now, 'updated_at' => $now,
                ];
                $taken += $line ? 1 : 0;
            }
            $db->table('survey_beacon_pool')->insert($rows);
            $added += count($rows);
        }

        return ['prefix' => $prefix, 'added' => $added, 'already_issued' => $taken];
    }

    /** seq => register line, for the numbers of this prefix already in the register. */
    public static function registerLines(string $prefix, int $from, int $to): array
    {
        return self::db()->table('survey_beacon_numbers')->whereNull('deleted_at')
            ->where('beacon_series', $prefix . '|')->whereBetween('beacon_seq', [$from, $to])
            ->pluck('survey_beacon_register_id', 'beacon_seq')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * The next numbers, without taking them: what the Issue window shows. $skip leaves out those
     * already sitting in the window's table.
     *
     * @return array{prefix:?string, available:int, numbers:array<int,string>}
     */
    public static function peek(int $count, int $skip = 0): array
    {
        if (! self::installed()) return ['prefix' => null, 'available' => 0, 'numbers' => []];

        $status = self::status();
        $rows = $count < 1 ? [] : self::db()->table('survey_beacon_pool')->where('status', self::AVAILABLE)
            ->orderByRaw('LEN(prefix)')->orderBy('prefix')->orderBy('seq')
            ->offset(max(0, $skip))->limit($count)->pluck('beacon_no')->all();

        return ['prefix' => $status['prefix'], 'available' => $status['available'],
                'numbers' => array_map([SurveyBeaconRegister::class, 'display'], $rows)];
    }

    /**
     * Take the next numbers for issue. Call inside the save's transaction: the rows stay locked until
     * it commits, and READPAST lets a second person take the following numbers rather than wait.
     * Comes back as unbroken runs, since one register line holds one run.
     *
     * @return array<int, array{prefix:string, from:string, to:string}>
     */
    public static function claim(int $count): array
    {
        if (! self::installed()) {
            throw ValidationException::withMessages(['count' => 'The public beacon pool has not been set up yet.']);
        }

        $rows = self::db()->select(
            "SELECT TOP ({$count}) prefix, seq, beacon_no FROM survey_beacon_pool WITH (UPDLOCK, ROWLOCK, READPAST)
              WHERE status = ? ORDER BY LEN(prefix), prefix, seq", [self::AVAILABLE]);

        if (count($rows) < $count) {
            throw ValidationException::withMessages(['count' => 'Only ' . count($rows) . " public beacon number(s) are free in the pool; {$count} were asked for."]);
        }

        return self::runs($rows);
    }

    /**
     * Group rows (prefix, seq, beacon_no) into unbroken runs.
     *
     * @param array<int, object> $rows in issue order
     * @return array<int, array{prefix:string, from:string, to:string}>
     */
    public static function runs(array $rows): array
    {
        $runs = [];
        $i = -1;
        foreach (array_values($rows) as $n => $row) {
            $prev = $n ? $rows[$n - 1] : null;
            if (! $prev || $prev->prefix !== $row->prefix || (int) $prev->seq + 1 !== (int) $row->seq) {
                $i++;
                $runs[$i] = ['prefix' => $row->prefix, 'from' => $row->beacon_no, 'to' => $row->beacon_no];
            }
            $runs[$i]['to'] = $row->beacon_no;
        }

        return $runs;
    }

    /** Mark the numbers of a register line as taken, however they reached the register. */
    public static function markIssued(int $entryId, string $series, int $from, int $to): void
    {
        if (! self::installed() || ! str_ends_with($series, '|')) return;

        self::db()->table('survey_beacon_pool')
            ->where('prefix', substr($series, 0, -1))->whereBetween('seq', [$from, $to])
            ->update(['status' => self::ISSUED, 'survey_beacon_register_id' => $entryId, 'issued_at' => now(), 'updated_at' => now()]);
    }

    /** A deleted or changed line gives its numbers back. */
    public static function release(int $entryId): void
    {
        if (! self::installed()) return;

        self::db()->table('survey_beacon_pool')->where('survey_beacon_register_id', $entryId)
            ->update(['status' => self::AVAILABLE, 'survey_beacon_register_id' => null, 'issued_at' => null, 'updated_at' => now()]);
    }
}
