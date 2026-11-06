<?php

namespace App\Http\Controllers\Survey\Concerns;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Export Records on a Survey register: the JSON behind exports.records_export_modal, whose driver
 * (public/js/records_export.js) builds the preview, the CSV and the PDF. See partials/_records_export.
 *
 * The page's own list is exported, so the file holds what the table holds. Read only.
 */
trait ExportsRecords
{
    /**
     * @param  callable  $list     Returns the page's list: a query with its order, or a collection. It runs after the
     *                             modal's Search has been copied to q, where the page's filter looks for it.
     * @param  array     $columns  key => label, in table order; S/N is added in front.
     * @param  callable  $row      One record => [key => value].
     * @param  string|null  $dateField  What Start/End Date filter: a column, or a key of the collection's rows. Null for none.
     * @param  callable|null  $summary  Given the row count, returns [[label, value], ...]: totals that go out with the rows.
     */
    protected function exportRecords(Request $r, callable $list, array $columns, callable $row, ?string $dateField = 'created_at', ?callable $summary = null): JsonResponse
    {
        // A whole register can run to 28,000 rows.
        @set_time_limit(300);

        $r->query->set('q', (string) $r->query('search', ''));
        $source = $list();

        $from = $this->exportDateFilter($r->query('start_date'));
        $to = $this->exportDateFilter($r->query('end_date'));

        $rows = [];
        $sn = 0;
        $add = function ($item) use (&$rows, &$sn, $row) {
            $rows[] = ['sn' => ++$sn] + array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row($item));
        };

        if ($source instanceof Collection) {
            if ($dateField && ($from || $to)) {
                $source = $source->filter(function ($item) use ($dateField, $from, $to) {
                    $on = $this->exportDate(data_get($item, $dateField), 'Y-m-d');

                    return $on !== '' && (! $from || $on >= $from) && (! $to || $on <= $to);
                });
            }
            $source->each($add);
        } else {
            if ($dateField && $from) $source->whereDate($dateField, '>=', $from);
            if ($dateField && $to) $source->whereDate($dateField, '<=', $to);
            $source->chunk(1000, function ($chunk) use ($add) {
                foreach ($chunk as $item) $add($item);
            });
        }

        $heads = [['key' => 'sn', 'label' => 'S/N', 'pdfWidth' => 9, 'wrap' => false]];
        $widths = $this->exportWidths($columns, $rows);
        foreach ($columns as $key => $label) {
            $heads[] = ['key' => $key, 'label' => $label, 'pdfWidth' => $widths[$key]];
        }

        return response()->json(['success' => true, 'columns' => $heads, 'data' => $rows, 'count' => $sn, 'summary' => $summary ? $summary($sn) : []]);
    }

    /**
     * PDF column widths in mm, shared out by what each column holds so they always fill the page. Without them the
     * driver gives every column at least 34 mm, and a register of more than seven columns runs off an A4 page.
     */
    private function exportWidths(array $columns, array $rows): array
    {
        $usable = 268; // A4 landscape less the margins and S/N.
        $weights = [];
        foreach ($columns as $key => $label) {
            $chars = 0;
            $longestWord = max(array_map('mb_strlen', explode(' ', $label)));
            foreach ($rows as $row) {
                $value = (string) ($row[$key] ?? '');
                $chars += mb_strlen($value);
                // A file number stays on one line where the room can be found.
                if ($value !== '') $longestWord = max($longestWord, min(18, max(array_map('mb_strlen', explode(' ', $value)))));
            }
            $weights[$key] = max(6, $longestWord, min(40, 1.3 * $chars / max(1, count($rows))));
        }

        $total = array_sum($weights) ?: 1;

        return array_map(fn ($w) => round($usable * $w / $total, 1), $weights);
    }

    /** A date as a table shows it; blank when there is none. */
    protected function exportDate($value, string $format = 'd M Y'): string
    {
        if (blank($value)) return '';

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /** A person's name from a creator-style relation. */
    protected function exportUser($user): ?string
    {
        return $user ? trim($user->first_name . ' ' . $user->last_name) : null;
    }

    private function exportDateFilter($value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
