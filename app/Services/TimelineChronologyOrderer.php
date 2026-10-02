<?php

namespace App\Services;

use App\Support\LegalSearchTimelineWeights;
use Carbon\Carbon;

/**
 * The Timeline Weighting Method (spec §3), extracted so surfaces OUTSIDE Legal
 * Search order a file the same way it does.
 *
 * Legal Search implements this twice already — as closures inside
 * LegalSearchService::buildPrintReport() and as sortTimelineChronologically()
 * in resources/views/legal_search/js.blade.php. This class is a third mirror,
 * for the general File History timeline (FileIndexViewController::timeline),
 * which used to sort on transaction_date alone and therefore read nothing like
 * the Legal Search timeline for the same file.
 *
 * The rules, all from LegalSearchTimelineWeights:
 *   - Weighted events sort by weight DESC, then timestamp ASC, then id.
 *   - Floating events (parcel updates, decommissionings, title-status updates)
 *     carry no rank and are spliced in after the last DATED weighted event on
 *     or before them, so they land chronologically without disturbing the
 *     hierarchy above them.
 *   - Undated floaters keep their arrival order at the very end.
 *
 * Rows are plain arrays. The general timeline feeds only file_history_staging
 * rows through here, so the Legal-Search-only inputs (synthetic commissioning
 * rows, lineage rows, `source_table`) are simply absent and classify() falls
 * through to the transaction type — which is exactly what those rows are.
 */
class TimelineChronologyOrderer
{
    protected TimelineWeightingService $weighting;

    public function __construct(TimelineWeightingService $weighting)
    {
        $this->weighting = $weighting;
    }

    /**
     * Order rows by the Legal Search timeline rules. Input order is the
     * tie-break of last resort, so callers should hand rows over in a stable
     * order (the SQL sort).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function sort(array $rows): array
    {
        $weighted = [];
        $floating = [];
        foreach ($rows as $row) {
            if ($this->weight($row) === null) {
                $floating[] = $row;
            } else {
                $weighted[] = $row;
            }
        }

        // Phase 1 — weight DESC, then timestamp ASC, then id.
        usort($weighted, function (array $a, array $b): int {
            $wa = $this->weight($a);
            $wb = $this->weight($b);
            if ($wa !== $wb) {
                return $wb <=> $wa;
            }
            return $this->compareByDateThenId($a, $b);
        });

        if (empty($floating)) {
            return $weighted;
        }

        // Phase 2 — inject each floater after the last DATED weighted event on
        // or before it. Sorted ascending first so several floaters parked on the
        // same anchor keep their own chronology (a Decommissioning must not
        // precede the Subdivision that caused it).
        usort($floating, fn(array $a, array $b): int => $this->compareByDateThenId($a, $b));

        $result = $weighted;
        foreach ($floating as $floater) {
            $ts = $this->timestamp($floater);
            if ($ts === null) {
                $result[] = $floater;
                continue;
            }

            // Only originally-weighted rows anchor the search; floaters inserted
            // on an earlier pass never act as anchors themselves.
            $insertAt = 0;
            foreach ($result as $i => $existing) {
                if (!$this->isDatedWeighted($existing)) {
                    continue;
                }
                if ($this->timestamp($existing) <= $ts) {
                    $insertAt = $i + 1;
                }
            }
            // Step past floaters already parked on this anchor and past UNDATED
            // weighted rows, which have no position in time and must stay with
            // their weight group rather than be split off by a dated floater.
            $count = count($result);
            while ($insertAt < $count && !$this->isDatedWeighted($result[$insertAt])) {
                $insertAt++;
            }
            array_splice($result, $insertAt, 0, [$floater]);
        }

        return array_values($result);
    }

    /**
     * The sort weight for a row, or null when the event floats.
     */
    public function weight(array $row): ?int
    {
        return LegalSearchTimelineWeights::weightFor($row, $this->canonicalType($row));
    }

    /**
     * The moment a row sits at, or null when it carries no usable date.
     *
     * OP / ToT / RofO carry their operative date in transaction_date; every
     * other event is keyed off its registration date. Mirrors
     * getTransactionTimestamp() in LegalSearchService / js.blade.php.
     */
    public function timestamp(array $row): ?int
    {
        $transactionDateFirst = in_array(
            LegalSearchTimelineWeights::classify($row, $this->canonicalType($row)),
            [
                LegalSearchTimelineWeights::OCCUPANCY_PERMIT,
                LegalSearchTimelineWeights::TRANSFER_OF_TITLE_OP,
                LegalSearchTimelineWeights::RIGHT_OF_OCCUPANCY,
            ],
            true
        );

        $regDate = ['date' => $row['reg_date'] ?? null, 'time' => $row['reg_time'] ?? null];
        $deedsDate = ['date' => $row['deeds_date'] ?? null, 'time' => $row['deeds_time'] ?? null];
        $txnDate = ['date' => $row['transaction_date'] ?? null, 'time' => $row['transaction_time'] ?? ($row['time'] ?? null)];

        $candidates = $transactionDateFirst
            ? [$txnDate, $deedsDate, $regDate]
            : [$regDate, $deedsDate, $txnDate];

        $candidates = array_merge($candidates, [
            ['date' => $row['cofo_date'] ?? null, 'time' => $row['time'] ?? null],
            ['date' => $row['certificateDate'] ?? null, 'time' => $row['time'] ?? null],
            ['date' => $row['approval_date'] ?? null, 'time' => $row['time'] ?? null],
            ['date' => $row['date'] ?? null, 'time' => $row['time'] ?? null],
        ]);

        foreach ($candidates as $candidate) {
            $ts = $this->parseDateValue($candidate['date'], $candidate['time']);
            if ($ts !== null) {
                return $ts;
            }
        }

        return null;
    }

    protected function canonicalType(array $row): string
    {
        return $this->weighting->canonicalInstrumentType(
            $row['transaction_type'] ?? ($row['instrument_type'] ?? '')
        );
    }

    /**
     * Only a weighted event that actually carries a date can anchor a floater.
     */
    protected function isDatedWeighted(array $row): bool
    {
        return $this->weight($row) !== null && $this->timestamp($row) !== null;
    }

    protected function compareByDateThenId(array $a, array $b): int
    {
        $ta = $this->timestamp($a);
        $tb = $this->timestamp($b);

        if ($ta === null && $tb === null) {
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        }
        // An undated row sorts to the end of its own band.
        if ($ta === null) {
            return 1;
        }
        if ($tb === null) {
            return -1;
        }
        if ($ta !== $tb) {
            return $ta <=> $tb;
        }

        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    }

    protected function parseDateValue($value, $timeValue = null): ?int
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $time = $this->parseTimeValue($timeValue);

        // d/m/Y — the shape file_history_staging stores (SQL Server style 105).
        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $text, $m)) {
            $dt = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1], $time['h'], $time['m'], $time['s']);
            return $dt ? $dt->timestamp : null;
        }

        // A bare 4-digit year must not reach Carbon::parse — strtotime reads
        // "2005" as the TIME 20:05 on TODAY's date. Anchor it to Jan 1 instead.
        if (preg_match('/^(?:19|20)\d{2}$/', $text)) {
            $dt = Carbon::create((int) $text, 1, 1, $time['h'], $time['m'], $time['s']);
            return $dt ? $dt->timestamp : null;
        }

        try {
            $dt = Carbon::parse($text);
            if ($timeValue) {
                $dt->setTime($time['h'], $time['m'], $time['s']);
            }
            return $dt->timestamp;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{h: int, m: int, s: int}
     */
    protected function parseTimeValue($value): array
    {
        $result = ['h' => 0, 'm' => 0, 's' => 0];
        if ($value === null || $value === '' || $value === '-') {
            return $result;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return $result;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AP]M)?$/i', $text, $m)) {
            $hour = (int) $m[1];
            $ampm = strtoupper((string) ($m[4] ?? ''));
            if ($ampm === 'PM' && $hour < 12) {
                $hour += 12;
            }
            if ($ampm === 'AM' && $hour === 12) {
                $hour = 0;
            }
            return ['h' => $hour, 'm' => (int) $m[2], 's' => isset($m[3]) ? (int) $m[3] : 0];
        }

        try {
            $parsed = Carbon::parse($text);
            return ['h' => (int) $parsed->format('H'), 'm' => (int) $parsed->format('i'), 's' => (int) $parsed->format('s')];
        } catch (\Throwable $e) {
            return $result;
        }
    }
}
