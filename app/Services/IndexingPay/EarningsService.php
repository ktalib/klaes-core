<?php

namespace App\Services\IndexingPay;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Indexing piece-rate earnings (Kunle, 2026-11-01; docs/plans/INDEXING_PIECE_RATE_PAYROLL_PLAN.md).
 *
 *   base fee  per file indexed through the File Indexing form, to the user who indexed it
 *             (file_indexings.indexed_by_user_id / indexed_at)
 *   + ₦ per   Transaction  (pra, CofO_staging), Ground Rent and Bill Balance
 *     record  (file_indexing_bills), to the user who captured the record, on that day
 *
 * Every screen (create page strip, profile card, My Earnings, supervisor sheet) reads
 * collect(), so the figures always agree. Amounts come from the rate card in force on
 * the day the item was earned (IndexingPaySettings::forDate).
 */
class EarningsService
{
    public function __construct(private IndexingPaySettings $settings)
    {
    }

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    // ---------------------------------------------------------------- who

    /** Anyone holding one of the eligible modules ("anyone that can index", user 2026-11-01). */
    public function isEligible(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $card = $this->settings->current();

        // Staff type first (MDC only, user 2026-11-02); an empty list means any staff type.
        $types = $card['eligible_staff_types'] ?? [];
        if ($types && ! in_array(strtoupper(trim((string) $user->staff_type_category)), $types, true)) {
            return false;
        }

        $wanted = array_map('mb_strtolower', $card['eligible_modules']);
        $held = array_map(fn ($r) => mb_strtolower(trim((string) $r)), $user->assignedRoleNames());
        if (array_intersect($wanted, $held)) {
            return true;
        }

        // ...or works at an indexing work station without holding the module (Super Admins
        // and others who index; user 2026-11-02).
        $stations = array_map('mb_strtolower', $card['eligible_work_stations'] ?? []);

        return $stations && in_array(mb_strtolower(trim((string) $user->work_station)), $stations, true);
    }

    /** @return int[] */
    public function eligibleUserIds(): array
    {
        $card = $this->settings->current();
        $modules = $card['eligible_modules'];
        $stations = $card['eligible_work_stations'] ?? [];
        if (! $modules && ! $stations) {
            return [];
        }

        $candidates = User::query()
            ->where(function ($q) use ($modules, $stations) {
                foreach ($modules as $module) {
                    $q->orWhere('assign_role', 'like', '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $module) . '%');
                }
                if ($stations) {
                    $q->orWhereIn('work_station', $stations);
                }
            })
            ->get();

        return $candidates->filter(fn (User $u) => $this->isEligible($u))->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    // ---------------------------------------------------------------- what

    /** The type a file is paid as. ITF is dormant, so it never comes out of here. */
    public static function fileType(?string $registry, ?string $fileNumber, $kangisLinkCount): string
    {
        $registry = mb_strtoupper(trim((string) $registry));
        $fileNumber = mb_strtoupper(trim((string) $fileNumber));

        if ($registry === 'KANGIS') {
            return 'kangis';
        }
        if ($registry === 'SLTR' || str_starts_with($fileNumber, 'SLTR-') || str_starts_with($fileNumber, 'SLTR ')) {
            return 'sltr';
        }
        if ((int) $kangisLinkCount > 0) {
            return 'kangis_land';
        }

        return 'regular';
    }

    /** Pay for one item under a rate card. */
    public static function amountFor(array $card, string $kind, string $fileType): float
    {
        if ($kind === 'file') {
            return (float) ($card['base'][$fileType] ?? $card['base']['regular']);
        }

        if (in_array($kind, ['gr', 'bb'], true) && $fileType === 'sltr' && ! $card['sltr_pays_gr_bb']) {
            return 0.0;
        }

        return (float) ($card['rates'][$kind] ?? 0);
    }

    // ---------------------------------------------------------------- collect

    /** The programme window [start, end] as Y-m-d, from the card in force today. */
    public function programme(): array
    {
        $card = $this->settings->current();

        return [$card['start_date'], $card['end_date']];
    }

    /**
     * Every paid item for these users between two dates (inclusive), clipped to the programme.
     *
     * @param  int[]  $userIds
     * @return array<int, array> items: user_id, date, time, kind, type, file_number, label, amount, source, source_id, note
     */
    public function collect(array $userIds, string $from, string $to): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        [$pStart, $pEnd] = $this->programme();
        $from = max($from, $pStart);
        $to = min($to, $pEnd);

        if (! $userIds || $from > $to) {
            return [];
        }

        $start = $from . ' 00:00:00';
        $endExclusive = Carbon::parse($to)->addDay()->toDateString() . ' 00:00:00';
        $idStrings = array_map('strval', $userIds);

        $items = array_merge(
            $this->files($userIds, $start, $endExclusive),
            $this->praTransactions($idStrings, $start, $endExclusive, $from, $to),
            $this->cofoTransactions($idStrings, $start, $endExclusive),
            $this->bills($idStrings, $start, $endExclusive)
        );

        // Price each item with the card of its own day.
        foreach ($items as &$item) {
            $card = $this->settings->forDate($item['date']);
            $item['amount'] = self::amountFor($card, $item['kind'], $item['type']);
            if ($item['amount'] == 0 && in_array($item['kind'], ['gr', 'bb'], true) && $item['type'] === 'sltr') {
                $item['note'] = 'SLTR file: Ground Rent / Bill Balance not paid';
            }
        }
        unset($item);

        usort($items, fn ($a, $b) => [$b['date'], $b['time']] <=> [$a['date'], $a['time']]);

        return $items;
    }

    /** Files indexed through the File Indexing form (only that form sets indexed_by_user_id). */
    private function files(array $userIds, string $start, string $endExclusive): array
    {
        $rows = $this->db()->table('file_indexings')
            ->select('id', 'file_number', 'registry', 'kangis_link_count', 'indexed_by_user_id', 'indexed_at')
            ->whereIn('indexed_by_user_id', $userIds)
            ->where('indexed_at', '>=', $start)
            ->where('indexed_at', '<', $endExclusive)
            ->whereRaw('ISNULL(is_deleted, 0) = 0')
            ->whereRaw("ISNULL(is_duplicate, '0') NOT IN ('1', 'true', 'yes')")
            ->whereRaw("ISNULL(file_classification, '') NOT IN ('Dummy File', 'Temporary')")
            ->get();

        return $rows->map(function ($r) {
            $at = Carbon::parse($r->indexed_at);
            $type = self::fileType($r->registry, $r->file_number, $r->kangis_link_count);

            return $this->item((int) $r->indexed_by_user_id, $at, 'file', $type, $r->file_number,
                IndexingPaySettings::TYPES[$type], 'file_indexings', $r->id);
        })->all();
    }

    /** SQL: a non-blank column. */
    private static function filled(string $col): string
    {
        return "NULLIF(LTRIM(RTRIM({$col})), '') IS NOT NULL";
    }

    /**
     * Transactions in pra. A record counts when it names an instrument and carries at least
     * one registration particular (serial, page or volume): the blank Right of Occupancy rows
     * created with every index are not transactions. A repeat of an earlier record (same file,
     * instrument, serial, page and volume) is never paid twice.
     */
    private function praTransactions(array $ids, string $start, string $endExclusive, string $from, string $to): array
    {
        $reg = '(' . self::filled('p.serialNo') . ' OR ' . self::filled('p.pageNo') . ' OR ' . self::filled('p.volumeNo') . ')';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // pra.created_at is nvarchar; a coarse string filter, then an exact check in PHP.
        $sql = "SELECT p.id, p.mlsFNo, p.NewKANGISFileno, p.kangisFileNo, p.fileno, p.instrument_type,
                       LTRIM(RTRIM(p.created_by)) AS uid, p.created_at
                FROM pra p WITH (NOLOCK)
                WHERE LTRIM(RTRIM(p.created_by)) IN ($placeholders)
                  AND p.created_at >= ?
                  AND " . self::filled('p.instrument_type') . "
                  AND $reg
                  AND ISNULL(p.is_deleted, 0) = 0 AND p.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM pra d WITH (NOLOCK)
                      WHERE d.id < p.id
                        AND ISNULL(d.is_deleted, 0) = 0 AND d.deleted_at IS NULL
                        AND (d.mlsFNo = p.mlsFNo
                             OR (d.mlsFNo IS NULL AND p.mlsFNo IS NULL
                                 AND ISNULL(d.NewKANGISFileno, '') = ISNULL(p.NewKANGISFileno, '')
                                 AND ISNULL(d.kangisFileNo, '') = ISNULL(p.kangisFileNo, '')
                                 AND ISNULL(d.fileno, '') = ISNULL(p.fileno, '')))
                        AND ISNULL(d.instrument_type, '') = ISNULL(p.instrument_type, '')
                        AND ISNULL(d.serialNo, '') = ISNULL(p.serialNo, '')
                        AND ISNULL(d.pageNo, '') = ISNULL(p.pageNo, '')
                        AND ISNULL(d.volumeNo, '') = ISNULL(p.volumeNo, ''))";

        $rows = $this->db()->select($sql, array_merge($ids, [substr($start, 0, 10)]));
        $fileTypes = $this->fileTypesFor(array_map(fn ($r) => $r->mlsFNo ?: $r->NewKANGISFileno ?: $r->kangisFileNo ?: $r->fileno, $rows));

        $items = [];
        foreach ($rows as $r) {
            $at = $this->parseDate($r->created_at);
            if (! $at || $at->toDateString() < $from || $at->toDateString() > $to) {
                continue;
            }
            $fileNo = $r->mlsFNo ?: $r->NewKANGISFileno ?: $r->kangisFileNo ?: $r->fileno;
            $items[] = $this->item((int) $r->uid, $at, 'trans', $fileTypes[mb_strtoupper(trim((string) $fileNo))] ?? 'regular',
                $fileNo, trim((string) $r->instrument_type), 'pra', $r->id);
        }

        return $items;
    }

    /** CofO transactions (the indexing form files a Certificate of Occupancy here, not in pra). */
    private function cofoTransactions(array $ids, string $start, string $endExclusive): array
    {
        $reg = '(' . self::filled('c.serialNo') . ' OR ' . self::filled('c.pageNo') . ' OR ' . self::filled('c.volumeNo') . ')';
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT c.id, c.mlsFNo, c.NewKANGISFileno, c.kangisFileNo, c.fileno, c.instrument_type,
                       LTRIM(RTRIM(c.created_by)) AS uid, c.created_at
                FROM CofO_staging c WITH (NOLOCK)
                WHERE LTRIM(RTRIM(c.created_by)) IN ($placeholders)
                  AND c.created_at >= ? AND c.created_at < ?
                  AND $reg
                  AND ISNULL(c.is_deleted, '0') NOT IN ('1', 'true') AND c.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM CofO_staging d WITH (NOLOCK)
                      WHERE d.id < c.id AND d.mlsFNo = c.mlsFNo
                        AND ISNULL(d.is_deleted, '0') NOT IN ('1', 'true') AND d.deleted_at IS NULL
                        AND ISNULL(d.serialNo, '') = ISNULL(c.serialNo, '')
                        AND ISNULL(d.pageNo, '') = ISNULL(c.pageNo, '')
                        AND ISNULL(d.volumeNo, '') = ISNULL(c.volumeNo, ''))
                  AND NOT EXISTS (
                      SELECT 1 FROM pra p WITH (NOLOCK)
                      WHERE p.mlsFNo = c.mlsFNo
                        AND ISNULL(p.is_deleted, 0) = 0 AND p.deleted_at IS NULL
                        AND ISNULL(p.serialNo, '') = ISNULL(c.serialNo, '')
                        AND ISNULL(p.pageNo, '') = ISNULL(c.pageNo, '')
                        AND ISNULL(p.volumeNo, '') = ISNULL(c.volumeNo, ''))";

        $rows = $this->db()->select($sql, array_merge($ids, [$start, $endExclusive]));
        $fileTypes = $this->fileTypesFor(array_map(fn ($r) => $r->mlsFNo ?: $r->NewKANGISFileno ?: $r->kangisFileNo ?: $r->fileno, $rows));

        return array_map(function ($r) use ($fileTypes) {
            $fileNo = $r->mlsFNo ?: $r->NewKANGISFileno ?: $r->kangisFileNo ?: $r->fileno;

            return $this->item((int) $r->uid, Carbon::parse($r->created_at), 'trans',
                $fileTypes[mb_strtoupper(trim((string) $fileNo))] ?? 'regular',
                $fileNo, trim((string) $r->instrument_type) ?: 'Certificate of Occupancy', 'CofO_staging', $r->id);
        }, $rows);
    }

    /** Ground Rent and Bill Balance rows that hold an amount or a receipt number. */
    private function bills(array $ids, string $start, string $endExclusive): array
    {
        $rows = $this->db()->table('file_indexing_bills as b')
            ->leftJoin('file_indexings as fi', 'fi.id', '=', 'b.file_indexing_id')
            ->select('b.id', 'b.file_number', 'b.bill_type', 'b.created_by', 'b.created_at', 'b.from_year', 'b.to_year',
                'fi.registry', 'fi.kangis_link_count')
            ->whereIn(DB::raw('LTRIM(RTRIM(b.created_by))'), $ids)
            ->where('b.created_at', '>=', $start)
            ->where('b.created_at', '<', $endExclusive)
            ->whereIn('b.bill_type', ['grant-rent', 'bill-balance'])
            ->where(function ($q) {
                $q->where('b.amount', '>', 0)->orWhereRaw(self::filled('b.receipt_no'));
            })
            ->get();

        return $rows->map(function ($r) {
            $kind = $r->bill_type === 'grant-rent' ? 'gr' : 'bb';
            $years = trim(($r->from_year ?: '') . ($r->to_year ? '–' . $r->to_year : ''), '–');
            $label = IndexingPaySettings::RECORDS[$kind] . ($years !== '' ? " ($years)" : '');

            return $this->item((int) trim((string) $r->created_by), Carbon::parse($r->created_at), $kind,
                self::fileType($r->registry, $r->file_number, $r->kangis_link_count), $r->file_number, $label,
                'file_indexing_bills', $r->id);
        })->all();
    }

    /** Pay type of each file number (for the SLTR rule on records). */
    private function fileTypesFor(array $fileNumbers): array
    {
        $numbers = array_values(array_unique(array_filter(array_map(fn ($n) => trim((string) $n), $fileNumbers))));
        $types = [];

        foreach (array_chunk($numbers, 500) as $chunk) {
            $rows = $this->db()->table('file_indexings')
                ->select('file_number', 'registry', 'kangis_link_count')
                ->whereIn('file_number', $chunk)
                ->get();
            foreach ($rows as $r) {
                $types[mb_strtoupper(trim((string) $r->file_number))] = self::fileType($r->registry, $r->file_number, $r->kangis_link_count);
            }
        }
        // A number never indexed still follows its prefix (SLTR-...).
        foreach ($numbers as $n) {
            $types[mb_strtoupper($n)] ??= self::fileType(null, $n, 0);
        }

        return $types;
    }

    private function parseDate($value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function item(int $userId, Carbon $at, string $kind, string $type, $fileNumber, string $label, string $source, $sourceId): array
    {
        return [
            'user_id'     => $userId,
            'date'        => $at->toDateString(),
            'time'        => $at->format('H:i'),
            'kind'        => $kind,
            'type'        => $type,
            'file_number' => trim((string) $fileNumber),
            'label'       => $label,
            'amount'      => 0.0,
            'source'      => $source,
            'source_id'   => (int) $sourceId,
            'note'        => null,
        ];
    }

    // ---------------------------------------------------------------- aggregate

    /** Totals for a list of items: files per type, records per kind, ₦. */
    public static function totals(array $items): array
    {
        $t = [
            'amount' => 0.0, 'files' => 0, 'records' => 0, 'items' => count($items),
            'by_type' => array_fill_keys(array_keys(IndexingPaySettings::TYPES), ['count' => 0, 'amount' => 0.0]),
            'by_record' => array_fill_keys(array_keys(IndexingPaySettings::RECORDS), ['count' => 0, 'amount' => 0.0]),
            'base_amount' => 0.0, 'records_amount' => 0.0,
        ];

        foreach ($items as $i) {
            $t['amount'] += $i['amount'];
            if ($i['kind'] === 'file') {
                $t['files']++;
                $t['base_amount'] += $i['amount'];
                $t['by_type'][$i['type']]['count']++;
                $t['by_type'][$i['type']]['amount'] += $i['amount'];
            } else {
                $t['records']++;
                $t['records_amount'] += $i['amount'];
                $t['by_record'][$i['kind']]['count']++;
                $t['by_record'][$i['kind']]['amount'] += $i['amount'];
            }
        }

        $t['amount'] = round($t['amount'], 2);

        return $t;
    }

    /**
     * Everything a user's own screens show: today, this week, the month, the month calendar,
     * the pace and the targets. Cached briefly; $fresh after a save.
     */
    public function summary(User $user, ?string $month = null, bool $fresh = false): array
    {
        $today = now()->toDateString();
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $month) ? $month : substr($today, 0, 7);
        $key = "indexing-pay:summary:{$user->id}:{$month}:{$today}";
        $ttl = (int) config('indexing_pay.cache_seconds', 60);

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, $ttl, fn () => $this->buildSummary($user, $month, $today));
    }

    private function buildSummary(User $user, string $month, string $today): array
    {
        $card = $this->settings->current();
        [$pStart, $pEnd] = $this->programme();
        $monthStart = Carbon::parse($month . '-01');
        $monthEnd = $monthStart->copy()->endOfMonth();
        $weekStart = Carbon::parse($today)->startOfWeek(Carbon::MONDAY)->toDateString();

        $from = min($monthStart->toDateString(), $weekStart);
        $to = max($monthEnd->toDateString(), $today);
        $items = $this->collect([$user->id], $from, min($to, $today));

        $inMonth = array_values(array_filter($items, fn ($i) => substr($i['date'], 0, 7) === $month));
        $todayItems = array_values(array_filter($items, fn ($i) => $i['date'] === $today));
        $weekItems = array_values(array_filter($items, fn ($i) => $i['date'] >= $weekStart && $i['date'] <= $today));

        // Days of this month inside the programme, weekends included.
        $mFrom = max($monthStart->toDateString(), $pStart);
        $mTo = min($monthEnd->toDateString(), $pEnd);
        $programmeDays = $mFrom <= $mTo ? Carbon::parse($mFrom)->diffInDays(Carbon::parse($mTo)) + 1 : 0;
        $elapsedTo = min($today, $mTo);
        $elapsedDays = $mFrom <= $elapsedTo ? Carbon::parse($mFrom)->diffInDays(Carbon::parse($elapsedTo)) + 1 : 0;

        $monthTotals = self::totals($inMonth);
        $byDay = [];
        foreach ($inMonth as $i) {
            $byDay[$i['date']] ??= [];
            $byDay[$i['date']][] = $i;
        }

        $calendar = [];
        for ($d = $monthStart->copy(); $d->lte($monthEnd); $d->addDay()) {
            $date = $d->toDateString();
            $dayTotals = self::totals($byDay[$date] ?? []);
            $calendar[] = [
                'date' => $date, 'day' => (int) $d->format('j'), 'weekday' => (int) $d->format('N'),
                'in_programme' => $date >= $pStart && $date <= $pEnd,
                'is_today' => $date === $today, 'is_future' => $date > $today,
                'amount' => $dayTotals['amount'], 'files' => $dayTotals['files'], 'records' => $dayTotals['records'],
            ];
        }

        $daysWorked = count($byDay);
        $avgPerElapsedDay = $elapsedDays > 0 ? $monthTotals['amount'] / $elapsedDays : 0;
        $status = $today < $pStart ? 'not_started' : ($today > $pEnd ? 'ended' : 'active');

        return [
            'eligible'   => true,
            'status'     => $status,
            'programme'  => ['start' => $pStart, 'end' => $pEnd],
            'today_date' => $today,
            'month'      => $month,
            'month_label'=> $monthStart->format('F Y'),
            'card'       => ['base' => $card['base'], 'rates' => $card['rates'], 'sltr_pays_gr_bb' => $card['sltr_pays_gr_bb']],
            'today'      => self::totals($todayItems),
            'week'       => self::totals($weekItems),
            'month_totals' => $monthTotals,
            'days_worked'  => $daysWorked,
            'programme_days' => $programmeDays,
            'elapsed_days'   => $elapsedDays,
            'projected'      => round($monthTotals['amount'] + $avgPerElapsedDay * max(0, $programmeDays - $elapsedDays), 2),
            'avg_per_day'    => round($avgPerElapsedDay, 2),
            'targets'    => ['daily_files' => $card['daily_file_target'], 'monthly' => $card['monthly_target']],
            'calendar'   => $calendar,
            'today_items'=> array_slice($todayItems, 0, 200),
        ];
    }

    /**
     * Supervisor sheet: one row per eligible user for a date range, plus anyone who indexed
     * through the form without holding an eligible module (listed, not paid).
     */
    public function sheet(string $from, string $to): array
    {
        $eligible = $this->eligibleUserIds();
        $items = $this->collect($eligible, $from, $to);

        $byUser = [];
        foreach ($items as $i) {
            $byUser[$i['user_id']][] = $i;
        }

        $users = User::whereIn('id', $eligible ?: [0])->get()->keyBy('id');
        $rows = [];
        foreach ($eligible as $id) {
            $u = $users->get($id);
            if (! $u) {
                continue;
            }
            $userItems = $byUser[$id] ?? [];
            $rows[] = [
                'user_id' => $id,
                'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->email ?? ('User ' . $id)),
                'days_worked' => count(array_unique(array_column($userItems, 'date'))),
                'totals' => self::totals($userItems),
            ];
        }

        usort($rows, fn ($a, $b) => [$b['totals']['amount'], $a['name']] <=> [$a['totals']['amount'], $b['name']]);

        [$pStart, $pEnd] = $this->programme();
        $outside = $this->db()->table('file_indexings')
            ->select('indexed_by_user_id', DB::raw('COUNT(*) AS files'))
            ->whereNotNull('indexed_by_user_id')
            ->when($eligible, fn ($q) => $q->whereNotIn('indexed_by_user_id', $eligible))
            ->where('indexed_at', '>=', max($from, $pStart) . ' 00:00:00')
            ->where('indexed_at', '<', Carbon::parse(min($to, $pEnd))->addDay()->toDateString() . ' 00:00:00')
            ->groupBy('indexed_by_user_id')
            ->get();
        $outsideUsers = User::whereIn('id', $outside->pluck('indexed_by_user_id')->all() ?: [0])->get()->keyBy('id');

        return [
            'rows' => $rows,
            'totals' => self::totals($items),
            'not_eligible' => $outside->map(fn ($r) => [
                'user_id' => (int) $r->indexed_by_user_id,
                'name' => optional($outsideUsers->get($r->indexed_by_user_id), fn ($u) => trim($u->first_name . ' ' . $u->last_name)) ?? ('User ' . $r->indexed_by_user_id),
                'files' => (int) $r->files,
            ])->values()->all(),
        ];
    }
}
