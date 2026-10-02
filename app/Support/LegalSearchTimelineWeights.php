<?php

namespace App\Support;

/**
 * The single source of truth for Legal Search timeline weights.
 *
 * Mirrors TIMELINE_WEIGHTS in resources/views/legal_search/KLAES Legal Search Timeline.md.
 * Both consumers read these numbers rather than carrying their own copies:
 *   - the print slip, via LegalSearchService::buildPrintReport()
 *   - the on-screen timeline, via `@json(LegalSearchTimelineWeights::MAP)` in
 *     resources/views/legal_search/js.blade.php
 * //
 * Weights drive the PRIMARY sort (descending), with event timestamp ascending as the
 * secondary key. A null weight marks a "floating" event: it carries no rank of its own
 * and is injected chronologically without disturbing the weighted hierarchy around it.
 */
class LegalSearchTimelineWeights
{
    public const FILE_COMMISSIONING       = 'FILE_COMMISSIONING';
    public const TEMP_FILE_COMMISSIONING  = 'TEMP_FILE_COMMISSIONING';
    public const OCCUPANCY_PERMIT         = 'OCCUPANCY_PERMIT';
    public const TRANSFER_OF_TITLE_OP     = 'TRANSFER_OF_TITLE_OP';
    public const RIGHT_OF_OCCUPANCY       = 'RIGHT_OF_OCCUPANCY';
    public const KANGIS_RECERTIFICATION   = 'KANGIS_RECERTIFICATION';
    public const CERTIFICATE_OF_OCCUPANCY = 'CERTIFICATE_OF_OCCUPANCY';
    public const OTHER_INSTRUMENTS        = 'OTHER_INSTRUMENTS';
    public const PARCEL_UPDATE            = 'PARCEL_UPDATE';
    public const TITLE_STATUS_UPDATE      = 'TITLE_STATUS_UPDATE';
    public const FILE_DECOMMISSIONING     = 'FILE_DECOMMISSIONING';
    public const DCIV_COMMISSIONING       = 'DCIV_COMMISSIONING';
    public const CURRENT_YEAR_INSTRUMENT  = 'CURRENT_YEAR_INSTRUMENT';
    public const OLD_KN_COMMISSIONING     = 'OLD_KN_COMMISSIONING';

    /** @var array<string, int|null> */
    public const MAP = [
        // The old Ministry "KN 3686" file predates everything the land file itself records, so
        // its own File Commissioning line opens the block — above the land file's commissioning
        // row (12), per the client. Only that file's commissioning line ranks here; its
        // ordinary dealings stay in their own band. See isOldKnCommissioningRow().
        // Shares 13 with a Transfer of Title by the client's choice of number; the two do not
        // co-occur in practice, and a tie falls through to date-ascending like any other.
        self::OLD_KN_COMMISSIONING     => 13,
        // The Occupancy Permit is the grant the parcel begins with, so it outranks even the
        // commissioning of the file that later holds it — and the Transfer of Title that
        // follows it.
        self::OCCUPANCY_PERMIT         => 14,
        self::TRANSFER_OF_TITLE_OP     => 13,
        self::FILE_COMMISSIONING       => 12,
        self::TEMP_FILE_COMMISSIONING  => 12,
        self::RIGHT_OF_OCCUPANCY       => 11,
        // 8 keeps a recertification above the C of O it produced (First and Second alike —
        // they share this band). placeKangisRecertBeforeCofo() still enforces adjacency
        // when both share a timestamp.
        self::KANGIS_RECERTIFICATION   => 8,
        // A file's own dealings (assignments, mortgages, surrenders…) rank here — except for
        // current-year dealings, see -1 below. Dealings still order among THEMSELVES by date —
        // see getTransactionTimestamp(), which keys them off registration date.
        self::OTHER_INSTRUMENTS        => 1,
        // The C of O shares the dealings band rather than closing it. At its old rank of 0 it
        // sat below every dealing whatever the dates said, which pinned the 17 Jun 1982 C of O
        // on CON-AG-1982-172 beneath a 1984 assignment. On 1 it orders against those dealings
        // BY DATE, so it reads above the ones registered after it and below the ones before —
        // while still sitting under the commissioning line (12), which a floating C of O did
        // not do when its grant predated the file's own commissioning year.
        self::CERTIFICATE_OF_OCCUPANCY => 1,
        // Client exception to the band above: a dealing whose date falls in the CURRENT
        // year is the file's live transaction and must CLOSE the timeline, below even the
        // C of O. Ranked rather than floated so several current-year dealings still order
        // among themselves by date. See isCurrentYearInstrument().
        self::CURRENT_YEAR_INSTRUMENT  => -1,
        self::PARCEL_UPDATE            => null,
        self::TITLE_STATUS_UPDATE      => null,
        self::FILE_DECOMMISSIONING     => null,
        self::DCIV_COMMISSIONING       => null,
    ];

    /**
     * Transaction types that create or retire files. These anchor where lineage
     * commissioning rows sit, and are floating events in their own right.
     */
    public const PARCEL_UPDATE_PATTERN = '/subdivision|merger|change of purpose|plot extension|separation|parcel update/';

    /**
     * Classify a timeline row into one of the event keys above.
     *
     * $canonicalType must already have been through the caller's canonicaliser
     * (LegalSearchService's $canonicalTransactionType, or canonicalWeightingInstrumentType
     * in JS) — this class does not repeat that matching.
     */
    public static function classify(array $row, string $canonicalType): string
    {
        $source = trim((string) ($row['source_table'] ?? ''));

        // Tested before anything else: the old Ministry file's own opening line outranks every
        // other event, including the commissioning row it sits above.
        if (self::isOldKnCommissioningRow($row, $canonicalType)) {
            return self::OLD_KN_COMMISSIONING;
        }

        // Sources that only ever carry one synthetic event.
        switch ($source) {
            case 'File Commissioning':
                return self::FILE_COMMISSIONING;
            case 'Temporary File':
                return self::TEMP_FILE_COMMISSIONING;
            case 'File Decommissioning':
                return self::FILE_DECOMMISSIONING;
            case 'DCIV File Commissioning':
                return self::DCIV_COMMISSIONING;
        }

        // 'Related Fileno' is deliberately NOT in that list: it is a source, not an event,
        // and its rows carry real types — a Merger or Subdivision links files just as a
        // recertification does (see LegalSearchService::fetchDecommissionLineageRows).
        // Classifying on the source would rank a Subdivision as a recertification (8) and
        // lift it out of the floating events. Type wins; the source is only the fallback.
        if ($canonicalType === 'occupancy permit') {
            return self::OCCUPANCY_PERMIT;
        }
        if ($canonicalType === 'transfer of title') {
            return self::TRANSFER_OF_TITLE_OP;
        }
        if ($canonicalType === 'right of occupancy') {
            return self::RIGHT_OF_OCCUPANCY;
        }
        if (str_contains($canonicalType, 'recertification')) {
            return self::KANGIS_RECERTIFICATION;
        }
        if (str_contains($canonicalType, 'certificate of occupanc')) {
            return self::CERTIFICATE_OF_OCCUPANCY;
        }
        if (preg_match(self::PARCEL_UPDATE_PATTERN, $canonicalType)) {
            return self::PARCEL_UPDATE;
        }

        // Lineage rows — a parent/child file's own commissioning or decommissioning, which
        // reach the timeline through the 'Related Fileno' source rather than their own
        // source_table. Classified on TYPE here so they land in the commissioning tier and
        // the floating band respectively; without this they fell through to the untyped
        // 'Related Fileno' fallback below and were ranked as recertifications (8).
        if (str_contains($canonicalType, 'decommissioning')) {
            return self::FILE_DECOMMISSIONING;
        }
        if (str_contains($canonicalType, 'file commissioning')) {
            return self::FILE_COMMISSIONING;
        }

        // An untyped 'Related Fileno' row is the synthetic KANGIS recertification marker
        // emitted by LegalSearchService::fetchRelatedRecertificationRows().
        if ($source === 'Related Fileno') {
            return self::KANGIS_RECERTIFICATION;
        }

        return self::OTHER_INSTRUMENTS;
    }

    /**
     * An OLD Ministry KN number carries a SEPARATOR — "KN 3686", "KN-3686". A new-KANGIS
     * number is written solid, "KN3686", and is a different registry entirely.
     *
     * Mirrored by isOldKnFileNo() in resources/views/legal_search/js.blade.php, and the same
     * shape LegalSearchService::isOldMlsKnFileNo() tests.
     */
    public static function isOldKnFileNo(?string $fileNo): bool
    {
        return (bool) preg_match('/^KN[\s\-]\d+/i', trim((string) $fileNo));
    }

    /**
     * The old Ministry file's opening line — its commissioning or its recertification row —
     * as opposed to an ordinary dealing that merely happens to sit on that file. A mortgage
     * registered against "KN 3686" keeps its normal rank; only the line that OPENS the file
     * is hoisted above the land file's commissioning row.
     */
    public static function isOldKnCommissioningRow(array $row, string $canonicalType): bool
    {
        if (!str_contains($canonicalType, 'recertification') && !str_contains($canonicalType, 'file commissioning')) {
            return false;
        }

        foreach (['file_no', 'fileno', 'file_number', 'mlsFNo'] as $col) {
            $value = trim((string) ($row[$col] ?? ''));
            if ($value !== '' && $value !== '-') {
                return self::isOldKnFileNo($value);
            }
        }

        return false;
    }

    /**
     * Date fields a row's year is read from, in the order getTransactionTimestamp() prefers
     * for non-grant events (reg date first — pra and CofO_staging carry theirs in deeds_date).
     */
    private const DATE_FIELDS = [
        'reg_date', 'deeds_date', 'transaction_date',
        'cofo_date', 'certificateDate', 'approval_date', 'date',
    ];

    /**
     * The 4-digit year a row is dated to, or null when it carries no usable date.
     *
     * Read by regex rather than by parsing: the same row reaches this class as a raw
     * "2026-05-11", a printed "May 11, 2026", a "11/05/2026" and a bare "2026" (a legacy
     * commissioning year), and only the year matters here.
     */
    public static function eventYear(array $row): ?int
    {
        foreach (self::DATE_FIELDS as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value === '' || $value === '-') {
                continue;
            }
            if (preg_match('/(?:19|20)\d{2}/', $value, $m)) {
                return (int) $m[0];
            }
        }

        return null;
    }

    /**
     * Whether the current-year exception applies: a file's own dealing (assignment, mortgage,
     * surrender…) dated in the year we are in now.
     *
     * Deliberately scoped to OTHER_INSTRUMENTS. The grants (OP/ToT/RofO), the commissioning
     * tier, recertifications and the C of O keep their fixed rank however recently they were
     * registered — a 2026 Right of Occupancy still opens the file, it does not close it. Note
     * that the C of O shares the dealings band (1) but NOT this demotion: it classifies as
     * CERTIFICATE_OF_OCCUPANCY, and only OTHER_INSTRUMENTS is tested here.
     */
    public static function isCurrentYearInstrument(array $row, string $eventKey): bool
    {
        return $eventKey === self::OTHER_INSTRUMENTS
            && self::eventYear($row) === (int) date('Y');
    }

    /**
     * The sort weight for a row, or null when the event floats (see class docblock).
     */
    public static function weightFor(array $row, string $canonicalType): ?int
    {
        $key = self::classify($row, $canonicalType);

        // The demotion lives here, not in classify(): callers test the classification to pick
        // a date field and to find the C of O, and both must still see a current-year dealing
        // as OTHER_INSTRUMENTS.
        if (self::isCurrentYearInstrument($row, $key)) {
            $key = self::CURRENT_YEAR_INSTRUMENT;
        }

        // array_key_exists, not ??: a floating event's weight IS null, and ?? would read
        // that as "absent" and hand back the OTHER_INSTRUMENTS default of 0 — which would
        // rank parcel updates and decommissionings instead of letting them float.
        return array_key_exists($key, self::MAP)
            ? self::MAP[$key]
            : self::MAP[self::OTHER_INSTRUMENTS];
    }
}
