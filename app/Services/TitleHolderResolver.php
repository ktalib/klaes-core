<?php

namespace App\Services;

use App\Models\FileIndexing;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the THREE distinct ownership concepts a file carries, per the client
 * spec of 2026-08-20 (item 12, "Major update on Transaction History"):
 *
 *   Root of Title   — who the title came FROM. For SLTR/Conversion that is the
 *                     transferor of the dealing before the boundary (commissioning
 *                     where known, else the first Ministry title); for a Direct
 *                     Allocation it is the allottee of the OP / Allocation List.
 *   Original Holder — the person named on the FIRST Ministry title, whichever of
 *                     RofO or CofO came first. NOT the earliest party on record.
 *   Current Holder  — the grantee of the latest ownership-CHANGING dealing after
 *                     that title; equal to the Original Holder when none exists.
 *
 * All three lines print for every Application Type ("let's apply it to
 * everything"), a line with no answer showing a dash.
 *
 * The rule this replaces — "the earliest party in the transaction history is the
 * Original Holder" — is wrong whenever a sale predates the Ministry title, which
 * is the normal case on SLTR and Conversion files.
 *
 * Chain data comes from TimelineWeightingService (the same deduped, weighted set
 * that already feeds Quick Search, the mobile File Search and the indexing
 * timeline), so this service adds interpretation only — never a second query
 * layer that could disagree with what the timeline shows.
 *
 * Legal Search is deliberately NOT a consumer: it keeps its own
 * LegalSearchService::annotateRootOfTitle() remark. The two should agree; they
 * are not wired together.
 */
class TitleHolderResolver
{
    public const TYPE_SLTR              = 'SLTR';
    public const TYPE_CONVERSION        = 'Conversion';
    public const TYPE_DIRECT_ALLOCATION = 'Direct Allocation';
    public const TYPE_UNKNOWN           = 'Unknown';

    /**
     * The client's §12 table, transcribed. Each entry IS one row of the spec, so
     * a change to the paper table is a change to exactly one entry here.
     *
     *   anchor — which Ministry instrument names the Original Holder.
     *            'cofo'  : the CofO (rows i and ii say "Transactions before CofO"
     *                      and "the name on the CofO will ... be the Original
     *                      Holder"); falls back to a RofO when the file has no
     *                      CofO yet, so the line is not left blank.
     *            'grant' : earliest grant of either kind — row iii is the only
     *                      row that admits a RofO, as a root source.
     *   root   — how the Root of Title is derived.
     *            'pre_grant'  : the dealing predating the anchor.
     *            'allocation' : the OP, else the Allocation List (row iii).
     *   lines  — WHICH lines the interface prints, in order. Row iii ends
     *            "Original Holder / Current Holder" — two lines, no Root: on a
     *            direct allocation the allottee and the first grantee are the
     *            same person, so a Root line would print the same name twice
     *            (measured: 59% of sampled Direct Allocation files).
     *
     * Unknown is NOT a row of the table — 71% of indexed files with dealings are
     * none of the three types. It gets the two-line shape, plus the Root line
     * only when a pre-grant dealing is actually found, so a file whose route we
     * cannot determine never shows an empty Root of Title. See the class docblock.
     */
    private const SPEC_TABLE = [
        self::TYPE_SLTR              => ['root' => 'pre_grant'],
        self::TYPE_CONVERSION        => ['root' => 'pre_grant'],
        self::TYPE_DIRECT_ALLOCATION => ['root' => 'allocation'],
        self::TYPE_UNKNOWN           => ['root' => 'pre_grant'],
    ];

    /**
     * Every type prints all three lines — "I think let's apply it to everything"
     * (recording), and the rules doc extends the structure to "Direct Allocation
     * and other applicable file types". A line with no answer prints a dash.
     */
    private const LINES = ['root_of_title', 'original_holder', 'current_holder'];

    /**
     * Label and colour tone per line. The three must be visually distinct (client
     * request 2026-08-23); tone travels in the payload so all four interfaces
     * colour them identically instead of each picking its own.
     */
    private const LINE_META = [
        'root_of_title'   => ['label' => 'Root of Title',   'tone' => 'amber'],
        'original_holder' => ['label' => 'Original Holder', 'tone' => 'emerald'],
        'current_holder'  => ['label' => 'Current Holder',  'tone' => 'indigo'],
    ];

    /**
     * The two commissioning routes the legacy Root of Title rule can name. Same
     * strings LegalSearchService::annotateRootOfTitle() prints, so the file-title
     * fallback and the Legal Search remark agree on wording.
     */
    private const ROUTE_ALLOCATION_LIST = 'Allocation List';
    private const ROUTE_OP              = 'Occupancy Permit (OP)';

    /**
     * Provenance tag written onto a holder derived from file_indexings.file_title
     * rather than from a dealing. Callers that need to distinguish a derived line
     * from a chain-resolved one read holder['source'].
     */
    public const SOURCE_FILE_TITLE = 'file_title';

    /**
     * Dealings that MOVE the title to somebody else. Only these can promote a
     * party to Current Holder. Everything outside this list (mortgage, surrender
     * & release, caveat, recertification, change of purpose, a re-issued CofO...)
     * leaves ownership where it was — a mortgaged file is still the mortgagor's.
     */
    private const OWNERSHIP_CHANGING = [
        'deed of assignment',
        'transfer of title',
        'deed of sub-assignment',
        'deed of conveyance',
        'deed of gift',
        'deed of transfer',
        'vesting deed',
        'deed of vesting',
        'sale agreement',
        'deed of sale',
    ];

    /** Free-text fragments that also mean "ownership moved" on legacy rows. */
    private const OWNERSHIP_CHANGING_FRAGMENTS = [
        'assignment', 'transfer of title', 'conveyance', 'gift', 'vesting', 'sale',
    ];

    /** Never ownership-changing, whatever else the label happens to contain. */
    private const NEVER_OWNERSHIP_CHANGING = [
        'mortgage', 'surrender', 'release', 'caveat', 'search', 'recertification',
        'change of purpose', 'sub-lease', 'sublease', 'power of attorney', 'lease',
    ];

    public function __construct(private TimelineWeightingService $timeline)
    {
    }

    /**
     * The three holders for a file.
     *
     * @return array{
     *   application_type:string,
     *   root_of_title:?array{name:?string,instrument:?string,date:?string,source:?string},
     *   original_holder:?array{name:?string,instrument:?string,date:?string,source:?string},
     *   current_holder:?array{name:?string,instrument:?string,date:?string,source:?string}
     * }
     */
    public function resolve(?string $fileNumber, ?string $propId = null, ?FileIndexing $indexing = null): array
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '' && ! $propId) {
            return $this->emptyResult(self::TYPE_UNKNOWN);
        }

        $indexing ??= $this->findIndexing($fileNumber);
        $commissioning = $this->commissioningInfo($fileNumber);
        $chain         = $this->chain($fileNumber ?: null, $propId);
        $type          = $this->classify($fileNumber, $commissioning['source'], $indexing, $chain);
        $spec          = self::SPEC_TABLE[$type] ?? self::SPEC_TABLE[self::TYPE_UNKNOWN];

        // COMMISSIONING is the boundary — "anything that happened before that file
        // was commissioned is the root of title" (recording). The first Ministry
        // title at or after it names the Original Holder.
        $anchorIndex = $this->findGrantIndex($chain, $commissioning['date']);
        $anchor      = $anchorIndex === null ? null : $chain[$anchorIndex];
        $anchorDate  = $anchorIndex === null ? null : ($chain[$anchorIndex]['_holder_date'] ?? null);

        // Files with no commissioning date AND no year in their number fall back
        // to the first Ministry title as the boundary.
        $boundary = $commissioning['date'] ?? $anchorDate;

        $root = $spec['root'] === 'allocation'
            ? $this->allocationRoot($chain, $commissioning['source'], $anchor, $anchorDate)
            : $this->preGrantRoot($chain, $boundary, $anchorIndex);

        // Nothing predates the boundary: the title springs from the State grant
        // itself, so THAT is the root (RES-2010-4268 — a 2011 RofO to MALLAM
        // HALADU on a file commissioned in 2010, then assigned away in 2026).
        // This is the same fallback allocationRoot() already applies when a
        // Direct Allocation carries no OP row, generalised to every type per the
        // recording: "even if it is direct allocation, the root of title will
        // still show ... you can put the name and then the instrument into
        // brackets". It never invents a name — the grant naming the Original
        // Holder is the only row it can name, so Root == Original here by
        // design, exactly as Scenario D of the rules doc prints it.
        $root ??= $this->grantRoot($anchor);

        $original = $this->originalHolder($anchor, $type, $root, $indexing);
        $current  = $this->currentHolder($chain, $anchorIndex, $original, $indexing);

        // Last resort: the Root of Title keyed in on the File Indexing form. Deriving
        // it needs a pre-grant dealing on the chain, and most files have none — the
        // indexer holds the physical file and can read it off the documents, which is
        // why the form now requires it. Applied AFTER $original is resolved on purpose:
        // this is free text, and letting it feed the Direct Allocation branch of
        // originalHolder() would print an instrument description as an owner's name.
        $root ??= $this->indexedRoot($indexing);

        // Absolute last resort: no chain, no holder columns, no keyed-in root —
        // the file offers no dealing to read ownership off at all. It still
        // carries ONE recorded name, its file title: the party the file was
        // opened for (4,262 of the 4,495 indexed files with both holder columns
        // empty have one). Printing three dashes on a file titled "IBRAHIM
        // JIBRIL" hides the only answer on record, so the title fills all three
        // lines. Applied only when ALL THREE are empty: once the chain has named
        // anybody, that answer is better than the file title and must not be
        // overwritten or padded out with it.
        if ($root === null && $original === null && $current === null) {
            [$root, $original, $current] = $this->fileTitleFallback($fileNumber, $indexing, $commissioning['source']);
        }

        return [
            'application_type' => $type,
            'root_of_title'    => $root,
            'original_holder'  => $original,
            'current_holder'   => $current,
            'lines'            => self::LINES,
        ];
    }

    /**
     * Display-ready strings ("NAME (Instrument)") for the blade/JS layers.
     *
     * `lines` is the authoritative render list — an ordered [label, value] pair
     * per line the table says this Application Type prints. Interfaces should
     * loop over it rather than hard-coding three rows, so the table stays the
     * single place that decides the shape. The flat keys are kept alongside for
     * callers that want one specific holder.
     */
    public function resolveForDisplay(?string $fileNumber, ?string $propId = null, ?FileIndexing $indexing = null): array
    {
        $r = $this->resolve($fileNumber, $propId, $indexing);

        $flat = [
            'root_of_title'   => self::label($r['root_of_title']),
            'original_holder' => self::label($r['original_holder']),
            'current_holder'  => self::label($r['current_holder']),
        ];

        $lines = [];
        foreach ($r['lines'] as $key) {
            $lines[] = [
                'key'   => $key,
                'label' => self::LINE_META[$key]['label'] ?? $key,
                'tone'  => self::LINE_META[$key]['tone'] ?? 'gray',
                'value' => $flat[$key] ?? null,
            ];
        }

        return ['application_type' => $r['application_type'], 'lines' => $lines] + $flat;
    }

    /** "MUSA IBRAHIM (Sale Agreement)" — or just the name when no instrument is known. */
    public static function label(?array $holder): ?string
    {
        $name = trim((string) ($holder['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $instrument = trim((string) ($holder['instrument'] ?? ''));

        return $instrument === '' ? $name : "{$name} ({$instrument})";
    }

    // ---------------------------------------------------------------- chain --

    /** The deduped, weighted, chronologically ordered dealings on this file. */
    private function chain(?string $fileNumber, ?string $propId): array
    {
        $records = $this->timeline->getRawRecords($fileNumber, $propId);
        if (empty($records)) {
            return [];
        }

        $weighted = $this->timeline->getWeightedRecords($records);
        if (empty($weighted)) {
            return [];
        }

        // Resolve each row's date ONCE and carry it on the row: every rule below
        // compares dates rather than positions, because a row the digitisers left
        // undated must never be read as "this happened first".
        foreach ($weighted as &$row) {
            $row['_holder_date'] = $this->sortDate($row);
        }
        unset($row);

        // Strictly chronological — the holder chain is a sequence in TIME, unlike
        // holderHistory() which sorts by source weight first for display purposes.
        // An undated row sorts last but keeps its relative order (stable by id).
        usort($weighted, function ($a, $b) {
            $da = $a['_holder_date'] ?? '9999-12-31';
            $db = $b['_holder_date'] ?? '9999-12-31';
            if ($da !== $db) {
                return $da <=> $db;
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return array_values($weighted);
    }

    /**
     * A row's transaction date as Y-m-d, or null when it has none we can trust.
     *
     * Sentinel dates are treated as NO date. The staging tables are full of
     * "1900-01-01" / "1899-12-30" placeholders written where a date was unknown,
     * and taking them literally puts a later assignment BEFORE the CofO — which
     * would promote its assignee to Root of Title and freeze the Current Holder
     * at the original grantee. (Seen on CON-RES-1987-672.)
     */
    private function sortDate(array $row): ?string
    {
        $raw = $row['transaction_date'] ?? null;
        if (empty($raw)) {
            return null;
        }

        $parsed = rescue(fn () => \Carbon\Carbon::parse($raw)->toDateString(), null, false);

        return ($parsed === null || $parsed <= '1900-01-01') ? null : $parsed;
    }

    // ------------------------------------------------------------- classify --

    /**
     * SLTR / Conversion / Direct Allocation. The three routes carry different
     * Root of Title rules, so misclassifying a file silently moves a name to the
     * wrong line — hence the explicit, ordered tests below.
     */
    private function classify(string $fileNumber, ?string $source, ?FileIndexing $indexing, array $chain): string
    {
        $src = strtolower(trim((string) $source));
        $fno = strtoupper(trim($fileNumber));

        // A Conversion file carries the CON- prefix in its own number; mls_file_no
        // only knows the 4.5% of files commissioned through KLAES.
        if (str_starts_with($fno, 'CON-') || str_contains($src, 'conversion')) {
            return self::TYPE_CONVERSION;
        }

        // SLTR is a registry, not a number format — same test the mobile File
        // Search uses to badge a file's registry.
        $registry = strtoupper(trim((string) ($indexing->general_registry ?? '')))
            . ' ' . strtoupper(trim((string) ($indexing->registry ?? '')));
        if (str_contains($registry, 'SLTR')) {
            return self::TYPE_SLTR;
        }

        if (str_contains($src, 'allocation') || str_contains($src, 'resettlement')) {
            return self::TYPE_DIRECT_ALLOCATION;
        }

        // No recorded source (the legacy majority): an Occupancy Permit on the
        // file is itself the evidence that the plot came through a direct
        // allocation.
        foreach ($chain as $row) {
            if ($this->canonical($row) === 'occupancy permit') {
                return self::TYPE_DIRECT_ALLOCATION;
            }
        }

        return self::TYPE_UNKNOWN;
    }

    // ------------------------------------------------------------- the grant --

    /** RofO and CofO both grant title; nothing else the Ministry issues does. */
    private function isMinistryGrant(array $row): bool
    {
        return in_array(
            $this->canonical($row),
            ['right of occupancy', 'certificate of occupancy'],
            true
        );
    }

    /**
     * Index of the Ministry grant that names the Original Holder.
     *
     * It is the earliest grant of EITHER kind ("the first paper would be either
     * R of O or C of O ... the name on that R of O or C of O will be the original
     * holder") that falls AT OR AFTER commissioning. A grant older than
     * commissioning is the title the file was converted FROM, so it belongs to
     * the Root of Title — CON-RES-RC-1981-106 was commissioned in 1981 and its
     * 1980 RofO is the root, while the 1982 CofO names the Original Holder.
     *
     * Falls back to the earliest grant of any date when nothing sits after the
     * boundary, so the Original Holder line is not left blank.
     *
     * Earliest, not "first found": plenty of files carry a CofO dated years before
     * the RofO captured for them, and anchoring on the later one left the earlier
     * grant sitting in the pre-grant window (seen on RES-1982-144,
     * RES-RC-1981-234, RES-1981-684). On an equal date a RofO wins, since the
     * certificate follows the right.
     */
    private function findGrantIndex(array $chain, ?string $boundary): ?int
    {
        if ($boundary !== null) {
            $afterCommissioning = $this->earliestGrantIndex($chain, $boundary);
            if ($afterCommissioning !== null) {
                return $afterCommissioning;
            }
        }

        return $this->earliestGrantIndex($chain, null);
    }

    /** Earliest Ministry grant, optionally ignoring any dated before `$notBefore`. */
    private function earliestGrantIndex(array $chain, ?string $notBefore): ?int
    {
        $best = null;

        foreach ($chain as $i => $row) {
            if (! $this->isMinistryGrant($row)) {
                continue;
            }
            // An undated grant cannot be shown to fall after commissioning, so it
            // only qualifies on the unrestricted fallback pass.
            if ($notBefore !== null
                && (($row['_holder_date'] ?? null) === null || $row['_holder_date'] < $notBefore)) {
                continue;
            }
            if ($best === null) {
                $best = $i;
                continue;
            }

            $bestDate = $chain[$best]['_holder_date'] ?? null;
            $rowDate  = $row['_holder_date'] ?? null;

            // An undated grant never displaces a dated one.
            if ($rowDate === null) {
                continue;
            }
            if ($bestDate === null || $rowDate < $bestDate) {
                $best = $i;
                continue;
            }
            if ($rowDate === $bestDate
                && $this->canonical($row) === 'right of occupancy'
                && $this->canonical($chain[$best]) !== 'right of occupancy') {
                $best = $i;
            }
        }

        return $best;
    }

    // ------------------------------------------------------- root of title --

    /**
     * SLTR / Conversion / Unknown: the earliest real dealing that predates the
     * boundary — commissioning where we know it, otherwise the first Ministry
     * title. Returns null when nothing predates it; a blank Root of Title is
     * correct, and far better than falling back to the earliest party, which is
     * exactly the assumption the spec removes.
     *
     * The root holder is the TRANSFEROR, not the recipient. Scenario A of the
     * rules doc — "A sells to B via Sale Agreement, CofO issued to B" — expects
     * `Root of Title: A`, and the recording puts it plainly: "you are not the
     * original holder, you are just the root of title. I'm the original holder
     * because I now have a C of O carrying my name." The person the title came
     * FROM is the root; the person it landed on becomes the Original Holder.
     */
    private function preGrantRoot(array $chain, ?string $boundary, ?int $anchorIndex): ?array
    {
        // "Before the boundary" is unanswerable without a dated boundary. An
        // undated grant sorts to the END of the chain, so falling back to
        // position would read every later dealing as pre-grant and hand the Root
        // of Title to the wrong party. Blank is the honest answer.
        if ($boundary === null) {
            return null;
        }

        foreach ($chain as $i => $row) {
            if ($i === $anchorIndex) {
                continue;
            }
            $rowDate = $row['_holder_date'] ?? null;
            if ($rowDate === null || $rowDate >= $boundary) {
                continue;
            }

            $canonical = $this->canonical($row);

            // A Ministry grant older than commissioning is NOT skipped: on a
            // conversion it is the very title being converted, which is exactly
            // what the root of title means here (CON-RES-RC-1981-106).
            //
            // An OP is the allocation itself rather than a private dealing, but it
            // is still the root the title springs from.
            $isAllocationOrGrant = $canonical === 'occupancy permit' || $this->isMinistryGrant($row);
            if (! $isAllocationOrGrant && $this->isNeverOwnershipChanging($row)) {
                continue;
            }
            if ($this->rawType($row) === '') {
                continue;
            }

            // An allocation or grant hands the plot TO its holder, so there the
            // recipient is the root; a private sale hands it FROM the root.
            // (transferringParty already skips the State, so a grant would land
            // on the same name either way — this just says so explicitly.)
            $name = $isAllocationOrGrant
                ? $this->receivingParty($row)
                : $this->transferringParty($row);

            if ($name === null) {
                continue;
            }

            return $this->holder($name, $this->instrumentLabel($row), $row);
        }

        return null;
    }

    /**
     * Last-resort root: the Ministry grant that opens the chain. Used when no
     * dealing predates the boundary, which is the normal shape of a file the
     * State granted directly and that was only dealt with afterwards.
     *
     * The grantee is the root — a grant hands the plot TO its holder, the
     * opposite direction from the private sale preGrantRoot() reads — and the
     * instrument is the row's own label, never a hard-coded one.
     */
    private function grantRoot(?array $anchor): ?array
    {
        if ($anchor === null) {
            return null;
        }

        $name = $this->receivingParty($anchor);

        return $name === null
            ? null
            : $this->holder($name, $this->instrumentLabel($anchor), $anchor);
    }

    /**
     * Direct Allocation: the root is the allocation the plot came through — the
     * OP if one is on the chain, otherwise the Allocation List recorded as the
     * commissioning source. The ALLOTTEE's name goes on the line, with the
     * instrument in brackets ("MUSA ABDULLAHI (Occupancy Permit)"): here the plot
     * was handed TO the root holder, the opposite direction from a private sale.
     *
     * Root and Original Holder are usually the same person on these files, and
     * that is expected — Scenario D of the rules doc prints exactly that.
     */
    private function allocationRoot(array $chain, ?string $source, ?array $anchor, ?string $boundary): ?array
    {
        foreach ($chain as $row) {
            if ($this->canonical($row) !== 'occupancy permit') {
                continue;
            }

            // The allocation a title springs FROM cannot postdate that title. An
            // OP dated after the grant is a later dealing wearing an OP label
            // (RES-2001-5301 carries a 2008 "Occupancy Permit" transferring away
            // from the 2003 RofO holder), so it is not this file's root.
            $rowDate = $row['_holder_date'] ?? null;
            if ($boundary !== null && $rowDate !== null && $rowDate > $boundary) {
                continue;
            }

            $name = $this->receivingParty($row);
            if ($name !== null) {
                // The row's own instrument label, never a hard-coded "OP" — the
                // rules doc asks for the actual instrument on the record.
                return $this->holder($name, $this->instrumentLabel($row), $row);
            }
        }

        // No OP row on record: the allottee is whoever the Ministry granted to,
        // because on a direct allocation the allottee and the first grantee are
        // the same person. The instrument is then the commissioning source that
        // put the plot in their hands.
        $name = $anchor ? $this->receivingParty($anchor) : null;
        if ($name === null) {
            return null;
        }

        $src = strtolower(trim((string) $source));
        $instrument = (str_contains($src, 'op direct allocation') || str_contains($src, 'resettlement'))
            ? 'Occupancy Permit'
            : 'Allocation List';

        return $this->holder($name, $instrument, $anchor);
    }

    // ------------------------------------------------------------- holders --

    /**
     * The name on the first Ministry grant. When the file has no grant at all,
     * fall back to the Root of Title holder (a direct allocation whose OP is the
     * only record), then to the indexed original_holder — never to the earliest
     * party on the chain.
     */
    private function originalHolder(?array $anchor, string $type, ?array $root, ?FileIndexing $indexing): ?array
    {
        if ($anchor) {
            $name = $this->receivingParty($anchor);
            if ($name !== null) {
                return $this->holder($name, $this->instrumentLabel($anchor), $anchor);
            }
        }

        if ($type === self::TYPE_DIRECT_ALLOCATION && $root) {
            // On a direct allocation the allottee IS the original holder; the
            // instrument is dropped here so the two lines aren't identical.
            return $this->holder($root['name'], null, null, $root['date'] ?? null);
        }

        $indexed = $indexing?->formattedHolder('original_holder');

        return $indexed ? $this->holder($indexed, null, null) : null;
    }

    /**
     * The grantee of the latest ownership-changing dealing AFTER the grant. With
     * no such dealing the Original Holder is still the Current Holder.
     */
    private function currentHolder(array $chain, ?int $anchorIndex, ?array $original, ?FileIndexing $indexing): ?array
    {
        $anchorDate = $anchorIndex === null ? null : ($chain[$anchorIndex]['_holder_date'] ?? null);

        // Split the eligible dealings in two. A DATED dealing after the grant is
        // the strong answer; an undated one is a maybe. Undated rows sort last in
        // the chain, so without this split a row with no date at all would
        // outrank a genuinely later, dated transfer.
        $dated = [];
        $undated = [];
        foreach ($chain as $i => $row) {
            if ($i === $anchorIndex || ! $this->isOwnershipChanging($row)) {
                continue;
            }
            $rowDate = $row['_holder_date'] ?? null;
            if ($rowDate === null) {
                $undated[] = $row;
                continue;
            }
            // Only dealings from the grant onwards can move the Current Holder —
            // anything earlier is Root of Title territory. With no dated grant on
            // record every dealing is eligible.
            if ($anchorDate === null || $rowDate >= $anchorDate) {
                $dated[] = $row;
            }
        }

        foreach ([array_reverse($dated), array_reverse($undated)] as $candidates) {
            foreach ($candidates as $row) {
                $name = $this->receivingParty($row);
                if ($name === null) {
                    continue;
                }
                return $this->holder($name, $this->instrumentLabel($row), $row);
            }
        }

        if ($original) {
            return $original;
        }

        $indexed = $indexing?->formattedHolder('current_holder')
            ?? $indexing?->formattedHolder('original_holder');

        return $indexed ? $this->holder($indexed, null, null) : null;
    }

    // -------------------------------------------------------------- helpers --

    /**
     * Who the dealing moved the property TO. party_2 (grantee/assignee) is the
     * receiving side; a one-sided legacy row records only party_1, and on those
     * the single name IS the holder being recorded.
     */
    private function receivingParty(array $row): ?string
    {
        $party2 = trim((string) ($row['party_2'] ?? ''));
        if ($party2 !== '' && ! $this->isGovernmentParty($party2)) {
            return $party2;
        }

        $party1 = trim((string) ($row['party_1'] ?? ''));
        if ($party1 !== '' && ! $this->isGovernmentParty($party1)) {
            return $party1;
        }

        // Both sides are the State (a grant from government to government, or a
        // mis-captured row) — prefer whatever is there over nothing.
        return ($party2 ?: $party1) ?: null;
    }

    /**
     * Who the dealing moved the property FROM — the assignor/vendor. This is the
     * Root of Title on a private pre-Ministry dealing (see preGrantRoot). Falls
     * back to the recipient on a one-sided legacy row that records only one name.
     */
    private function transferringParty(array $row): ?string
    {
        $party1 = trim((string) ($row['party_1'] ?? ''));
        if ($party1 !== '' && ! $this->isGovernmentParty($party1)) {
            return $party1;
        }

        $party2 = trim((string) ($row['party_2'] ?? ''));
        if ($party2 !== '' && ! $this->isGovernmentParty($party2)) {
            return $party2;
        }

        return ($party1 ?: $party2) ?: null;
    }

    /**
     * The State grants land; it never "holds" it in the sense this display means.
     * On a RofO/CofO row the Grantor is the Kano State Government, so it must not
     * be picked up as a holder.
     */
    private function isGovernmentParty(string $name): bool
    {
        $n = strtolower($name);

        return str_contains($n, 'state government')
            || str_contains($n, 'governor')
            || str_contains($n, 'ministry of land');
    }

    /** Display label for a row's instrument ("RofO", "CofO", "Deed Of Assignment"). */
    private function instrumentLabel(array $row): ?string
    {
        $canonical = $this->canonical($row);

        return match ($canonical) {
            'right of occupancy'       => 'RofO',
            'certificate of occupancy' => 'CofO',
            'occupancy permit'         => 'OP',
            ''                         => ($this->rawType($row) ?: null),
            default                    => ucwords($canonical),
        };
    }

    private function rawType(array $row): string
    {
        $type = trim((string) ($row['transaction_type'] ?? ($row['instrument_type'] ?? '')));

        return ($type === '-' ) ? '' : $type;
    }

    private function canonical(array $row): string
    {
        return $this->timeline->canonicalInstrumentType($this->rawType($row));
    }

    /**
     * Public face of isOwnershipChanging(), for callers that need the same answer
     * about one timeline row without resolving a whole file's holders — currently
     * OpHolderMatchService, deciding whether a dealing on the chain explains how the
     * title reached the indexed holder. Kept as a wrapper so the rule itself (and
     * the "never ownership-changing" exclusions) lives in exactly one place.
     *
     * @param  array  $row  a timeline row: transaction_type / instrument_type
     */
    public function movesOwnership(array $row): bool
    {
        return $this->isOwnershipChanging($row);
    }

    private function isOwnershipChanging(array $row): bool
    {
        if ($this->isNeverOwnershipChanging($row)) {
            return false;
        }

        $canonical = $this->canonical($row);
        if (in_array($canonical, self::OWNERSHIP_CHANGING, true)) {
            return true;
        }

        $raw = strtolower($this->rawType($row));
        foreach (self::OWNERSHIP_CHANGING_FRAGMENTS as $fragment) {
            if (str_contains($raw, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function isNeverOwnershipChanging(array $row): bool
    {
        $haystack = strtolower($this->rawType($row) . ' ' . $this->canonical($row));
        foreach (self::NEVER_OWNERSHIP_CHANGING as $word) {
            if (str_contains($haystack, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The Root of Title as captured on the File Indexing form. Free text, so it is
     * carried as the name with no instrument — the officer writes whatever the
     * physical file says, instrument included.
     */
    private function indexedRoot(?FileIndexing $indexing): ?array
    {
        $indexed = $indexing?->formattedHolder('root_of_title');

        return $indexed ? $this->holder($indexed, null, null) : null;
    }

    /**
     * The three lines derived from file_indexings.file_title, for a file that
     * offers nothing else. Returns [root, original, current] — all three null
     * when the file has no title either, leaving the dashes exactly as before.
     *
     * The Root line additionally carries the ROUTE the title came by, from
     * legacyRootRoute() below.
     *
     * @return array{0:?array,1:?array,2:?array}
     */
    private function fileTitleFallback(string $fileNumber, ?FileIndexing $indexing, ?string $commissioningSource): array
    {
        $title = trim((string) ($indexing->file_title ?? ''));
        if ($title === '') {
            return [null, null, null];
        }

        $route = $this->legacyRootRoute($fileNumber, $commissioningSource);

        return [
            $this->fromFileTitle($title, $route),
            $this->fromFileTitle($title, null),
            $this->fromFileTitle($title, null),
        ];
    }

    /** A holder built off the file title, tagged with its provenance. */
    private function fromFileTitle(string $name, ?string $instrument): ?array
    {
        $holder = $this->holder($name, $instrument);
        if ($holder !== null) {
            $holder['source'] = self::SOURCE_FILE_TITLE;
        }

        return $holder;
    }

    /**
     * The Root of Title ROUTE, by the rule that answered this question before the
     * File Indexing form gained its own Root of Title field — the same ladder
     * LegalSearchService::annotateRootOfTitle() walks, reduced to the only case
     * that reaches it here: a file with an empty chain, so there is no OP row to
     * match (rule 1) and no earlier instrument to anchor on (rule 3's search).
     *
     *   - mls_file_no.source recorded the commissioning route: an allocation
     *     (Direct Allocation / Allocation List) or an Occupancy Permit.
     *   - Any OTHER recorded source (Conversion, Re-grant, Subdivision...) means
     *     the title did not start at commissioning; its root is an earlier
     *     instrument, and this file carries none — so no route is asserted.
     *   - Nothing recorded — the ~95% of files absent from mls_file_no. A legacy
     *     file came off an allocation list, EXCEPT a Conversion or SLTR file:
     *     that land was already held under an earlier title, so calling it
     *     "Allocation List" would state a root it does not have.
     */
    private function legacyRootRoute(string $fileNumber, ?string $commissioningSource): ?string
    {
        $src = strtolower(trim(preg_replace('/\s+/', ' ', (string) $commissioningSource)));

        if ($src !== '') {
            if ($src === 'direct allocation' || $src === 'allocation list') {
                return self::ROUTE_ALLOCATION_LIST;
            }
            if ($src === 'op' || str_starts_with($src, 'op ') || str_contains($src, 'occupancy permit')) {
                return self::ROUTE_OP;
            }

            return null;
        }

        return preg_match('~^(CON|SLTR)[-_/ ]~i', trim($fileNumber)) ? null : self::ROUTE_ALLOCATION_LIST;
    }

    private function holder(?string $name, ?string $instrument, ?array $row = null, ?string $date = null): ?array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return [
            'name'       => $name,
            'instrument' => $instrument ?: null,
            'date'       => $date ?? ($row ? $this->displayDate($row['transaction_date'] ?? null) : null),
            'source'     => $row['source_table'] ?? null,
        ];
    }

    private function displayDate($raw): ?string
    {
        if (empty($raw)) {
            return null;
        }
        return rescue(fn () => \Carbon\Carbon::parse($raw)->format('M j, Y'), is_string($raw) ? $raw : null, false);
    }

    /**
     * How and when the file was commissioned — only files KLAES commissioned are
     * in mls_file_no (roughly 4.5%), so both come back null for legacy files and
     * the callers fall back to the first Ministry title.
     *
     * Variants: a temporary file may be commissioned under its "(T)" number while
     * the search number is the base, or vice versa.
     *
     * @return array{date:?string,source:?string}
     */
    private function commissioningInfo(string $fileNumber): array
    {
        if ($fileNumber === '') {
            return ['date' => null, 'source' => null];
        }

        $base = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNumber);
        $candidates = array_values(array_unique(array_filter([$fileNumber, $base, $base . '(T)'])));

        $record = DB::connection('sqlsrv')->table('mls_file_no')
            ->whereIn('full_file_number', $candidates)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first(['commissioning_date', 'source']);

        $date = null;
        if ($record && ! empty($record->commissioning_date)) {
            $date = rescue(
                fn () => \Carbon\Carbon::parse($record->commissioning_date)->toDateString(),
                null,
                false
            );
            // Same sentinel guard the chain uses — a placeholder commissioning
            // date would move the boundary to 1900 and blank every Root of Title.
            if ($date !== null && $date <= '1900-01-01') {
                $date = null;
            }
        }

        // Fallback: the commissioning YEAR is embedded in the file number itself
        // (CON-RES-RC-1981-106 was commissioned in 1981), which is the only
        // boundary available for the ~95% of files absent from mls_file_no. Jan 1
        // is deliberately conservative — a dealing dated within the commissioning
        // year counts as at/after commissioning, not before it.
        $date ??= $this->commissioningYearFromFileNumber($fileNumber);

        return ['date' => $date, 'source' => trim((string) ($record->source ?? '')) ?: null];
    }

    /**
     * The commissioning year encoded in a file number, as a Jan-1 date string.
     *
     * Matches a hyphen/space separated token that is a plausible year, so
     * "CON-RES-RC-1981-106" gives 1981 while "SLTR-10577", "13586" and "KN 7928"
     * give nothing (5 digits, or outside the plausible range). The FIRST such
     * token wins — the trailing group is a serial, not a year.
     */
    private function commissioningYearFromFileNumber(string $fileNumber): ?string
    {
        $maxYear = (int) date('Y') + 1;

        foreach (preg_split('/[^0-9]+/', $fileNumber) as $token) {
            if (strlen($token) !== 4) {
                continue;
            }
            $year = (int) $token;
            if ($year >= 1900 && $year <= $maxYear) {
                return sprintf('%04d-01-01', $year);
            }
        }

        return null;
    }

    private function findIndexing(string $fileNumber): ?FileIndexing
    {
        if ($fileNumber === '') {
            return null;
        }

        return FileIndexing::whereRaw('UPPER(file_number) = ?', [strtoupper($fileNumber)])->first();
    }

    private function emptyResult(string $type): array
    {
        return [
            'application_type' => $type,
            'root_of_title'    => null,
            'original_holder'  => null,
            'current_holder'   => null,
            'lines'            => self::LINES,
        ];
    }
}
