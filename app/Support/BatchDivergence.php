<?php

namespace App\Support;

/**
 * Do the files in a batch currently agree on the fields an edit is about to overwrite?
 *
 * A batch of N file numbers is drawn as ONE row on the MLS File Commission list, so
 * "apply this edit to the whole batch" is a natural thing to ask for — and for most
 * batches it is exactly right: of 141 multi-file batches, 111 share one plot number and
 * 125 share one applicant name. They were commissioned as one allocation.
 *
 * The other 30 did not. Those hold genuinely different plot numbers per file, and a
 * batch-wide write would flatten real data into whatever the clicked row happened to say.
 * That loss is silent and unrecoverable, so the batch write is gated: if the members
 * disagree on a field being changed, the user is shown the competing values and has to
 * confirm before anything is written.
 *
 * Fields the members already agree on never raise a prompt — overwriting one shared value
 * with another is the ordinary case and needs no ceremony.
 */
class BatchDivergence
{
    /**
     * Fields whose divergence matters. Anything not listed is not worth interrupting for.
     *
     * @var array<string, string> form field => human label
     */
    public const WATCHED = [
        'file_name' => 'File Name',
        'plot_no'   => 'Plot Number',
        'tp_no'     => 'TP Number',
        'district'  => 'District',
        'lga'       => 'LGA',
        'location'  => 'Location',
    ];

    /**
     * Compare the batch members on every watched field the edit actually changes.
     *
     * @param  iterable<mixed>       $members   the batch's records (arrays or objects)
     * @param  array<string, mixed>  $changes   form field => new value, as submitted
     * @param  array<string, string> $columnMap form field => property name on $members
     * @return array<int, array{field: string, label: string, values: array<int, string>}>
     *         one entry per field the members disagree on, values sorted and de-duplicated
     */
    public static function detect(iterable $members, array $changes, array $columnMap): array
    {
        $rows = is_array($members) ? $members : iterator_to_array($members);

        // A single file cannot disagree with itself.
        if (count($rows) < 2) {
            return [];
        }

        $divergent = [];

        foreach (self::WATCHED as $field => $label) {
            // Only fields this edit is actually writing can destroy anything.
            if (!array_key_exists($field, $changes)) {
                continue;
            }

            $column = $columnMap[$field] ?? $field;
            $values = [];

            foreach ($rows as $row) {
                $values[] = self::normalise(self::read($row, $column));
            }

            $distinct = array_values(array_unique($values));

            if (count($distinct) < 2) {
                continue;
            }

            sort($distinct, SORT_NATURAL | SORT_FLAG_CASE);

            $divergent[] = [
                'field'  => $field,
                'label'  => $label,
                'values' => array_map(
                    fn ($v) => $v === '' ? '(blank)' : $v,
                    $distinct
                ),
            ];
        }

        return $divergent;
    }

    /**
     * A one-line summary for the confirmation dialog.
     *
     * @param  array<int, array{field: string, label: string, values: array<int, string>}>  $divergent
     */
    public static function summarise(array $divergent, int $memberCount): string
    {
        if (empty($divergent)) {
            return '';
        }

        $parts = array_map(function ($d) {
            $shown = array_slice($d['values'], 0, 4);
            $more  = count($d['values']) - count($shown);
            $list  = implode(', ', $shown) . ($more > 0 ? ", +{$more} more" : '');

            return "{$d['label']}: {$list}";
        }, $divergent);

        return "The {$memberCount} files in this batch do not currently hold the same values — "
            . implode(' · ', $parts)
            . '. Applying this edit to the whole batch will overwrite all of them.';
    }

    /** Read a property off either an array row or an object row. */
    private static function read($row, string $column)
    {
        if (is_array($row)) {
            return $row[$column] ?? null;
        }

        return is_object($row) ? ($row->{$column} ?? null) : null;
    }

    /**
     * Values are compared the way a registry clerk would read them: trimmed, and
     * case-insensitive, because the forms upper-case some fields and not others.
     * NULL and '' are the same absence.
     */
    private static function normalise($value): string
    {
        return mb_strtoupper(trim((string) ($value ?? '')));
    }
}
