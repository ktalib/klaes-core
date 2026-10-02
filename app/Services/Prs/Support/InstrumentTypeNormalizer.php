<?php

namespace App\Services\Prs\Support;

/**
 * Folds the instrument-type spellings across the three deed tables onto the five
 * groups the PRS report sections need.
 *
 * The "verify variants" warning in docs/prs-2025/11-implementation-plan.md turned
 * out to be understated — every variant is real and in use. Measured 2026-08-02:
 * 
 *   pra                    Deed of Surrender and Release 3,300 · Surrender and Release 333 ·
 *                          Deed of Release 311   (three spellings, one PRS section)
 *                          Deed of Assignment 8,638 · Deed of Mortgage 5,817 ·
 *                          Tripartite Mortgage 1,833 · Deed of Devolution 604
 *   file_history_staging   adds upper-case duplicates: ASSIGNMENT 2,658 · MORTGAGE 1,407 ·
 *                          DEED OF SURRENDER AND RELEASE 1,072 · SURRENDER AND RELEASE 467
 *   deed_registrations     uses "Devolution Order" where pra uses "Deed of Devolution"
 *
 * A naive GROUP BY on instrument_type splits every one of these sections in two.
 */
class InstrumentTypeNormalizer
{
    public const ASSIGNMENT = 'assignment';
    public const MORTGAGE   = 'mortgage';
    public const RELEASE    = 'release';
    public const DEVOLUTION = 'devolution';
    public const COFO       = 'cofo';
    public const OP         = 'occupancy_permit';
    public const ROO        = 'right_of_occupancy';

    /**
     * Sectional Titling. Added 2026-08-05, after the deed source moved to
     * `deed_registrations` made it obvious that ST was being lost twice over:
     *
     *   ST Fragmentation (54 rows)                 matched NO group at all and was
     *                                              silently dropped from the report
     *   ST Assignment (Transfer of Title) (8 rows) matched %TRANSFER OF TITLE% and
     *                                              was counted as an Occupancy Permit
     *
     * The second rule was written for `pra`, where "Transfer of Title" really did
     * mean an OP transfer. In the ST register it means a sectional unit changing
     * hands, which is an assignment of a unit and not a permit.
     */
    public const ST_FRAGMENTATION = 'st_fragmentation';
    public const ST_TRANSFER      = 'st_transfer';

    /**
     * Everything no other group claims — Deed of Gift, Power of Attorney, and
     * whatever the registry books next. Not a PRS section in the source report, but
     * the residue has to be countable: an instrument type that matches no group
     * produces no section and no error, which is exactly how 54 ST Fragmentation
     * registrations stayed invisible.
     */
    public const OTHER = 'other';

    /** Section key => human label, for section titles. */
    public const LABELS = [
        self::ASSIGNMENT       => 'Deed of Assignment',
        self::MORTGAGE         => 'Deed of Mortgage',
        self::RELEASE          => 'Deed of Release',
        self::DEVOLUTION       => 'Deed of Devolution',
        self::COFO             => 'Certificate of Occupancy',
        self::OP               => 'Occupancy Permit',
        self::ROO              => 'Right of Occupancy',
        self::ST_FRAGMENTATION => 'ST Fragmentation',
        self::ST_TRANSFER      => 'ST Unit Transfer',
    ];

    /**
     * Anything sectional. Both the SQL and the PHP path test this first, so a
     * sectional instrument can never fall through to the OP or assignment rules.
     */
    private const ST_PREFIXES = ['ST ', 'SECTIONAL '];

    public function normalize(?string $raw): ?string
    {
        $v = strtoupper(trim((string) $raw));

        if ($v === '') {
            return null;
        }

        $v = trim(preg_replace('/\s+/', ' ', $v));

        // Order matters. Sectional instruments are tested first: "ST Assignment
        // (Transfer of Title)" would otherwise be read as an Occupancy Permit by the
        // TRANSFER OF TITLE rule, and "ST Fragmentation" matches nothing at all.
        // After that, "Tripartite Mortgage" must not fall through to assignment, and
        // "Deed of Surrender and Release" must not read as a surrender only.
        if ($this->isSectional($v)) {
            return str_contains($v, 'FRAGMENTATION')
                ? self::ST_FRAGMENTATION
                : self::ST_TRANSFER;
        }

        return match (true) {
            str_contains($v, 'FRAGMENTATION')                         => self::ST_FRAGMENTATION,
            str_contains($v, 'MORTGAGE')                              => self::MORTGAGE,
            str_contains($v, 'OCCUPANCY PERMIT') || str_contains($v, 'TRANSFER OF TITLE')
                || $v === 'OP'                                        => self::OP,
            str_contains($v, 'RIGHT OF OCCUPANCY')                    => self::ROO,
            str_contains($v, 'RELEASE') || str_contains($v, 'SURRENDER') => self::RELEASE,
            str_contains($v, 'DEVOLUTION') || str_contains($v, 'VESTING ASSENT')
                || str_contains($v, 'LETTERS OF ADMINISTRATION')      => self::DEVOLUTION,
            str_contains($v, 'CERTIFICATE OF OCCUPANCY')
                || $v === 'COFO' || $v === 'C OF O'                    => self::COFO,
            str_contains($v, 'ASSIGNMENT')                            => self::ASSIGNMENT,
            default                                                   => null,
        };
    }

    /** Does this instrument type name a sectional-title instrument? */
    private function isSectional(string $upper): bool
    {
        foreach (self::ST_PREFIXES as $prefix) {
            if (str_starts_with($upper, $prefix)) {
                return true;
            }
        }

        return str_contains($upper, 'SECTIONAL TITLE')
            || str_contains($upper, 'SECTIONAL TITLING');
    }

    /**
     * SQL fragment listing every raw spelling that belongs to a group, for pushing
     * the filter into the database rather than pulling 133,939 rows into PHP.
     */
    public function sqlPredicate(string $group, string $column): string
    {
        // The residue: NOT any of the real groups. Defined by subtraction so it can
        // never drift out of step with them.
        if ($group === self::OTHER) {
            $others = array_map(
                fn ($g) => 'NOT (' . $this->sqlPredicate($g, $column) . ')',
                self::ALL_GROUPS
            );

            return '(' . implode(' AND ', $others) . ')';
        }

        // Sectional groups are built separately: they need a prefix test ANDed with
        // an instrument test, which the flat OR-list below cannot express.
        if ($group === self::ST_FRAGMENTATION) {
            return "(" . $this->sqlIsSectional($column) . " AND UPPER($column) LIKE '%FRAGMENTATION%')";
        }

        if ($group === self::ST_TRANSFER) {
            return "(" . $this->sqlIsSectional($column) . ")"
                 . " AND UPPER($column) NOT LIKE '%FRAGMENTATION%'"
                 . " AND (UPPER($column) LIKE '%ASSIGNMENT%' OR UPPER($column) LIKE '%TRANSFER OF TITLE%')";
        }

        $like = match ($group) {
            self::MORTGAGE   => ["%MORTGAGE%"],
            self::RELEASE    => ["%RELEASE%", "%SURRENDER%"],
            self::DEVOLUTION => ["%DEVOLUTION%", "%VESTING ASSENT%", "%LETTERS OF ADMINISTRATION%"],
            self::COFO       => ["%CERTIFICATE OF OCCUPANCY%"],
            self::OP         => ["%OCCUPANCY PERMIT%", "%TRANSFER OF TITLE%"],
            self::ROO        => ["%RIGHT OF OCCUPANCY%"],
            self::ASSIGNMENT => ["%ASSIGNMENT%"],
            default          => [],
        };

        if ($like === []) {
            return '1 = 0';
        }

        $clauses = array_map(fn ($p) => "UPPER($column) LIKE '$p'", $like);
        $sql     = '(' . implode(' OR ', $clauses) . ')';

        // No non-sectional group may claim a sectional instrument. Without this,
        // "ST Assignment (Transfer of Title)" lands in BOTH the OP group (via
        // %TRANSFER OF TITLE%) and the assignment group, and the report counts a
        // sectional unit sale as an occupancy permit.
        if (in_array($group, [self::OP, self::ASSIGNMENT, self::COFO, self::ROO], true)) {
            $sql .= " AND NOT (" . $this->sqlIsSectional($column) . ")";
        }

        // Assignment is the fallback in normalize(), so exclude the groups a bare
        // LIKE '%ASSIGNMENT%' would otherwise sweep in — notably
        // "ST Assignment (Transfer of Title)", which is an OP transfer.
        if ($group === self::ASSIGNMENT) {
            $sql .= " AND UPPER($column) NOT LIKE '%MORTGAGE%'"
                  . " AND UPPER($column) NOT LIKE '%TRANSFER OF TITLE%'";
        }

        // "Right of Occupancy" and "Certificate of Occupancy" both contain
        // "OCCUPANCY"; keep the OP filter off both.
        if ($group === self::OP) {
            $sql .= " AND UPPER($column) NOT LIKE '%RIGHT OF OCCUPANCY%'"
                  . " AND UPPER($column) NOT LIKE '%CERTIFICATE OF OCCUPANCY%'";
        }

        return $sql;
    }

    /** The SQL twin of isSectional(). Kept adjacent so the two cannot drift. */
    private function sqlIsSectional(string $column): string
    {
        $tests = array_map(
            fn ($p) => "UPPER($column) LIKE '" . $p . "%'",
            self::ST_PREFIXES
        );

        $tests[] = "UPPER($column) LIKE '%SECTIONAL TITLE%'";

        // Parenthesised here, not at the call sites. AND binds tighter than OR, so
        // a bare "A OR B AND frag" reads as "A OR (B AND frag)" and the fragmentation
        // group claimed every sectional row — including the transfers.
        return '(' . implode(' OR ', $tests) . ')';
    }

    /**
     * Every group a row could be counted in, so the report can prove no instrument
     * type falls through unclaimed.
     *
     * This exists because 57 registrations — 54 ST Fragmentation, 2 Deed of Gift,
     * 1 Power of Attorney — were silently absent from the report until 2026-08-05.
     * A group that matches nothing produces no section and no error; only counting
     * the residue makes it visible.
     */
    public const ALL_GROUPS = [
        self::ASSIGNMENT, self::MORTGAGE, self::RELEASE, self::DEVOLUTION,
        self::COFO, self::OP, self::ROO, self::ST_FRAGMENTATION, self::ST_TRANSFER,
    ];
}
