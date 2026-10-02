<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "The OP names one holder, File Indexing names another, and nothing on the file
 * explains how the title moved."
 *
 * A file whose root of title is an Occupancy Permit carries the OP's grantee in
 * pra.party_2. When that file is later transferred, a Transfer of Title row records
 * the move and File Indexing's file_title follows the new holder. On some files the
 * indexing was updated but the transfer was never captured, so the register says the
 * title is with somebody the chain never gave it to.
 *
 * This service answers two questions for one file number:
 *
 *   check()       — is this file in that state, and what does its chain look like?
 *   generateTot() — write the missing transfer (OP holder -> indexed holder).
 *
 * WHAT DOES *NOT* QUALIFY (2026-08-29 decision, from the data): a file whose OP
 * grantee differs from the file title but which ALREADY carries a working transfer.
 * On 9 of the 11 files that first looked wrong, the transfer was there and only the
 * spelling of its party_2 differed from the file title (OZATAMGBO/OZOTAMGBO,
 * MOHD/MUHD, ...). The dealing is recorded on those files; generating another would
 * put a second transfer on a file that already has one. Those are name corrections,
 * handled by a human, and this service deliberately leaves them alone.
 *
 * A "working" transfer is one whose two parties are different names. A self-transfer
 * (IDRIS SANI -> IDRIS SANI, which RES-2024-2236 carries) moves nothing and does not
 * count as the bridge.
 *
 * The rule is the one in database/sql/2026_08_29_op_holder_vs_indexing_check.sql;
 * that query and this service must keep answering the same way.
 */
class OpHolderMatchService
{
    /** transaction_type / instrument_type written on the row this service creates. */
    public const TOT_TYPE = 'Transfer of Title (OP)';

    /**
     * Dropped before two names are compared for being the same person. MOHD and MUHD
     * are in here because they are used interchangeably as a whole given name in this
     * register, not because they are titles.
     */
    private const HONORIFICS = [
        'MR', 'MRS', 'MISS', 'MS', 'ALH', 'ALHAJI', 'ALHAJA', 'HAJIYA', 'HAJIA',
        'MAL', 'MALAM', 'MALLAM', 'DR', 'ENGR', 'ARC', 'BARR', 'PROF', 'CHIEF',
        'SIR', 'LATE', 'HON', 'HRH', 'MOHD', 'MUHD',
    ];

    /** pra.source / system_source stamped on it, so the row is traceable to this flow. */
    private const SOURCE = 'OP Holder Match';
    private const SYSTEM_SOURCE = 'OPHOLDERMATCH';

    /**
     * Columns never carried over from the source OP: the OP's own identity, its
     * registration particulars, its batch/merger membership, and the caveat state,
     * which belongs to the OP row and not to a transfer derived from it.
     */
    private const DO_NOT_COPY = [
        'id', 'resolved_fileno', 'updated_at', 'updated_by', 'deleted_at',
        'instrument_capture_id', 'op_batch', 'merger_group_id', 'is_merger_op',
        'is_subdivided', 'op_count', 'source_pra_id', 'source_op_table', 'source_op_id',
        'is_caveated', 'caveated_comment', 'caveat_id',
    ];

    public function __construct(
        private TitleHolderResolver $titleHolders,
        private LegalSearchService $legalSearch,
    ) {
    }

    /**
     * The file's state for the recommendation form.
     *
     * `applies` is the only field the caller has to act on: true means show the
     * Match option. Everything else is there so the officer can see WHY before
     * they press it — the chain, the two names, and the reason when it does not
     * apply.
     *
     * @return array{
     *   applies:bool, reason:string, file_number:string, indexing_name:?string,
     *   op:?array, timeline:array, root_of_title:?string, roots:array,
     *   has_working_transfer:bool, name_spelling_only:bool, matched:bool
     * }
     */
    public function check(?string $fileNumber): array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber === '') {
            return $this->inspect('');
        }

        // The chain comes from LegalSearchService::buildPrintReport(), which merges
        // four tables and takes 3-5 seconds. The form asks this question twice for
        // one capture — once to draw the card, once again as the officer saves — and
        // a second wait of that length on the save button would be paid by every
        // capture in the register, not just the handful this flow is about.
        //
        // Five minutes is long enough to cover one capture and short enough that a
        // file corrected in another screen is re-read while the officer is still
        // working. generateTot() clears the key the moment it writes, so the answer
        // can never outlive the state it describes.
        return Cache::remember(
            $this->cacheKey($fileNumber),
            300,
            fn () => $this->inspect($fileNumber)
        );
    }

    private function cacheKey(string $fileNumber): string
    {
        return 'op-holder-match.' . md5(strtoupper($fileNumber));
    }

    /** The real check. @see check() */
    private function inspect(string $fileNumber): array
    {
        $base = [
            'applies'              => false,
            'reason'               => '',
            'file_number'          => $fileNumber,
            'indexing_name'        => null,
            'op'                   => null,
            'timeline'             => [],
            'root_of_title'        => null,
            // Every Occupancy Permit this file is rooted in, with the plot each was
            // granted over. One entry on an ordinary file; two or more on a merged
            // one, where naming only the first would misdescribe the title.
            'roots'                => [],
            'has_working_transfer' => false,
            'name_spelling_only'   => false,
            'matched'              => false,
            // Set when the OP's pra row carries a DIFFERENT file number in another
            // column, so the officer can see the ambiguity before acting on it.
            'file_conflict'        => null,
            // Set only when this file is rooted in more than one Occupancy Permit —
            // a merger. Carries the grants being combined so the card can show which
            // permits and which plots are going into the one file.
            'merger'               => null,
            // Set whenever `matched` is true: WHICH of the three ways this file is
            // already accounted for, and the row that accounts for it. A file can be
            // matched because nothing ever moved, because a dealing on it reaches the
            // indexed holder, or because a merger sibling carries the transfer under
            // another number — and an officer told only "Matched" cannot tell those
            // apart or check the one that applies.
            'match'                => null,
        ];

        if ($fileNumber === '') {
            return $base + ['reason' => 'No file number given.'];
        }

        $indexing = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('file_number', $fileNumber)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first(['id', 'file_number', 'file_title', 'prop_id']);

        if (! $indexing) {
            $base['reason'] = 'This file has no File Indexing record.';
            return $base;
        }

        $indexedName = trim((string) $indexing->file_title);
        $base['indexing_name'] = $indexedName;

        $rows = $this->chain($fileNumber, $indexing->prop_id ?? null);
        $rows = $this->markSystemGenerated($rows, $fileNumber);
        $rows = $this->nameCommissioningFromIndexing($rows, $fileNumber, $indexedName);
        $rows = $this->attachParcelDetails($rows, $fileNumber);
        $base['timeline'] = $rows;

        if ($indexedName === '') {
            $base['reason'] = 'File Indexing holds no file title for this file, so there is nothing to compare the OP against.';
            return $base;
        }

        // An OP anywhere in this file's payload is this file's OP. The report gathers
        // a parcel, and a row can name a second file number, but a pra row keyed to
        // this file by ANY of its four columns is on this file as far as the register
        // is concerned — hiding the card because the engine preferred the other
        // column takes the decision away from the officer instead of showing it.
        //
        // Where the two numbers disagree the card says so (see $base['file_conflict'])
        // rather than either silently trusting it or silently dropping it.
        $ops = array_values(array_filter($rows, fn ($r) => $r['is_op']));

        if (! $ops) {
            $base['reason'] = 'The root of title is not an Occupancy Permit.';
            return $base;
        }

        // The line above the chain must name the SAME grant the chain badges "-RoT",
        // or the card contradicts itself in the space of two rows.
        $base['root_of_title'] = $this->rootOfTitleFor($ops[0], $fileNumber, $indexing->prop_id ?? null);

        // Every permit this file is rooted in, not just the first one the engine
        // badges. A merged file has two, and naming one of them above the chain says
        // the land came from one man while the history directly below shows it came
        // from two — which is the same contradiction the "-RoT" ordering exists to
        // avoid. Filled in EVERY state, not only where Match is offered: the file is
        // rooted in both permits before the transfer is written and just as much
        // after it, so the box must not shrink to one name the moment it is matched.
        //
        // Read from the file's OWN pra rows — the set the merger transfer is built
        // from — so the roots box and the transfer that combines them can never name
        // different people. The chain is deliberately not the source: it gathers a
        // parcel, so it can carry a permit belonging to another file on the same land.
        $base['roots'] = $this->rootHolders($fileNumber);

        // An OP that already names the indexed holder means the file never moved.
        foreach ($ops as $op) {
            if ($this->sameName($op['party_2'], $indexedName)) {
                $base['matched'] = true;
                $base['reason'] = 'The Occupancy Permit was granted to ' . $indexedName . ' — nothing has moved since.';
                $base['match'] = [
                    'kind'    => 'never_moved',
                    'title'   => 'The land never changed hands',
                    'detail'  => 'The Occupancy Permit was granted straight to ' . $indexedName
                        . ', which is the name File Indexing holds. There is no transfer to record because '
                        . 'there was no transfer.',
                    'type'    => $op['type'],
                    'party_1' => trim((string) $op['party_1']),
                    'party_2' => trim((string) $op['party_2']),
                    'date'    => $op['date'],
                    'source'  => $op['source'] ?? null,
                    'file_no' => $fileNumber,
                    'system_generated' => false,
                ];

                return $base;
            }
        }

        // The OP names the same person, spelt differently ("ALH KABIRU USMAN KULO"
        // against "KABIRU USMAN KULO", "MARYSAM MUSA" against "MARYAM MUSA").
        //
        // Nothing moved here, so there is no transfer to record: Match would write a
        // row transferring the title from a man to himself. 36 files in the estate
        // are in this state, and every one of them needs its name corrected on one
        // side or the other — which is a person's judgement about which spelling is
        // right, not something this flow can decide.
        //
        // Deliberately generous (any close match withholds the button): an officer
        // sent to check a name they did not need to check has lost a minute, while a
        // self-transfer written into the register is a false dealing on somebody's
        // title.
        foreach ($ops as $op) {
            if ($this->looksLikeSamePerson($op['party_2'], $indexedName)) {
                $base['name_spelling_only'] = true;
                $base['reason'] = 'The Occupancy Permit names ' . trim((string) $op['party_2'])
                    . ' and File Indexing holds ' . $indexedName
                    . '. These look like the same person spelt two ways, so no transfer is recorded — '
                    . 'correct the spelling on whichever side is wrong.';
                return $base;
            }
        }

        // Any DEALING THAT MOVES OWNERSHIP counts as the explanation, not just a row
        // typed "Transfer of Title". COM-2010-39 reaches BLUEFIELDS OIL & PETROLEUM
        // through two Deeds of Assignment held in file_history_staging; reading only
        // pra's transfers, this service called that file unexplained and offered to
        // write a transfer it did not need. What is and is not ownership-changing is
        // TitleHolderResolver's rule (a mortgage or a caveat is not), asked here
        // rather than restated.
        $dealings = array_values(array_filter($rows, fn ($r) => $this->movesOwnership($r)));

        foreach ($dealings as $d) {
            if ($this->sameName($d['party_2'], $indexedName)) {
                $wasWrittenHere = ! empty($d['system_generated']);

                $base['matched'] = true;
                $base['reason'] = 'The ' . $d['type'] . ' from ' . trim((string) $d['party_1'])
                    . ' to ' . trim((string) $d['party_2']) . ' is recorded on this file.';
                $base['match'] = [
                    'kind'   => 'dealing',
                    'title'  => $wasWrittenHere
                        // Worth separating: a transfer this flow reconstructed carries no
                        // registration particulars and never was presented to the registry,
                        // which is a different thing from a deed an officer keyed off paper.
                        ? 'Already matched — the transfer was recorded here'
                        : 'The transfer is already on file',
                    'detail' => $wasWrittenHere
                        ? 'Match has already been used on this file. The ' . $d['type']
                            . ' below was reconstructed from the Occupancy Permit, so it carries no '
                            . 'registration particulars (0/0/0). Nothing further is needed.'
                        : 'A dealing recorded on this file already carries the title to ' . $indexedName
                            . ', so the Occupancy Permit and File Indexing are accounted for. '
                            . 'Nothing needs to be generated.',
                    'type'    => $d['type'],
                    'party_1' => trim((string) $d['party_1']),
                    'party_2' => trim((string) $d['party_2']),
                    'date'    => $d['date'],
                    'reg_no'  => $d['reg_no'] ?? null,
                    'source'  => $d['source'] ?? null,
                    'file_no' => $fileNumber,
                    'system_generated' => $wasWrittenHere,
                    // Only ever set for a transfer THIS flow wrote. It is what the
                    // capture form stores as op_match_tot_pra_id when the Match itself
                    // happened earlier, on the Match OP page.
                    'pra_id'  => $wasWrittenHere ? ($d['system_generated_pra_id'] ?? null) : null,
                ];

                return $base;
            }
        }

        // The spelling-drift case: a dealing that moved the title is on file, so the
        // change IS recorded even though its grantee is not spelt the way File
        // Indexing spells it. Nothing to generate — that is a name correction.
        foreach ($dealings as $d) {
            if (! $this->sameName($d['party_1'], $d['party_2'])) {
                $base['has_working_transfer'] = true;
                $base['reason'] = 'A ' . $d['type'] . ' is already recorded on this file ('
                    . trim((string) $d['party_1']) . ' to ' . trim((string) $d['party_2'])
                    . '). If that name is wrong, it is a correction on the existing record — not a new transfer.';
                return $base;
            }
        }

        // The pra Occupancy Permit rows for this file — the grants the transfer will be
        // built from. Read from pra directly rather than matched back by holder name:
        // two permits can name the same person (one man merging two of his own plots),
        // and pairing by name would collapse them into one grant and lose a parcel.
        //
        // Only the SELECTED FILE's own permits. This never searches the register for
        // other files sharing an OP's serial number or details, which is precisely what
        // keeps a subdivision out of this path: a subdivision is one permit spread
        // across several files, so each of those files presents exactly one OP here and
        // is matched the way it always was. Nothing about that flow changes.
        $grants = array_values(array_filter($this->praRows($fileNumber), fn ($r) => $this->isOp($r)));

        if ($grants === []) {
            $base['reason'] = 'The Occupancy Permit for this file is not held in the deeds register (pra), '
                . 'so the transfer cannot be reconstructed from it here.';
            return $base;
        }

        // A merger whose transfer is already recorded — but recorded against a SIBLING
        // permit's file number rather than this one, so the chain above cannot see it.
        // matchOpMerger() anchors its transfer on the oldest OP in the group, and where
        // that OP sat on a TEMP number while its sibling held the real file number, the
        // transfer landed on the TEMP. RES-2023-5645 is in exactly that state: its
        // transfer exists as pra #167330 under TEMP-42337. Without this guard the card
        // would offer to write it a second time.
        $sibling = $this->mergerSiblingTransfer($grants, $indexedName);
        if ($sibling !== null) {
            $filedUnder = trim((string) ($sibling->mlsFNo ?: $sibling->fileno));

            $base['matched'] = true;
            $base['reason']  = 'This file is part of a merger whose Transfer of Title is already recorded ('
                . trim((string) $sibling->party_1) . ' to ' . trim((string) $sibling->party_2)
                . '), filed under ' . $filedUnder . '.';
            $base['match'] = [
                'kind'   => 'merger_sibling',
                'title'  => 'Already matched, under another file number',
                // The one matched case an officer genuinely has to act on afterwards: the
                // transfer exists but is filed somewhere the file's own history cannot
                // show, so it looks missing on every screen that reads this file alone.
                'detail' => 'The permits on this file belong to a merger set, and the merger\'s '
                    . 'Transfer of Title was filed under ' . $filedUnder . ' rather than '
                    . $fileNumber . '. It is recorded, so Match is not offered — but this file\'s own '
                    . 'history cannot show it while it sits under the other number.',
                'type'    => (string) ($sibling->transaction_type ?: $sibling->instrument_type),
                'party_1' => trim((string) $sibling->party_1),
                'party_2' => trim((string) $sibling->party_2),
                'date'    => null,
                'source'  => 'pra',
                'file_no' => $filedUnder,
                'pra_id'  => (int) $sibling->id,
                'system_generated' => false,
            ];

            return $base;
        }

        // Earliest grant. On a single-OP file it is the only one; on a merger it is the
        // row the transfer inherits its parcel details from, and it is taken from this
        // file's own permits so the transfer cannot be filed under a sibling's number.
        $anchor = $grants[0];

        // pra #126911 is mlsFNo COM-2016-219 AND fileno COM-2026-219 — one Occupancy
        // Permit row claiming two separately indexed files. Only 3 rows estate-wide
        // do this, but on those the officer has to decide, so the card tells them.
        $otherNumber = $this->conflictingFileNumber($anchor, $fileNumber);
        if ($otherNumber !== null) {
            $base['file_conflict'] = $otherNumber;
        }

        // Distinct transferring holders, in the order the permits were granted.
        $holders = [];
        foreach ($grants as $g) {
            $name = trim((string) $g->party_2);
            if ($name === '') {
                continue;
            }
            foreach ($holders as $seen) {
                if ($this->sameName($seen, $name)) {
                    continue 2;
                }
            }
            $holders[] = $name;
        }

        if ($holders === []) {
            $base['reason'] = 'The Occupancy Permit on this file names no holder, so there is no transfer to record.';
            return $base;
        }

        $base['applies'] = true;
        $base['op'] = [
            'pra_id'           => (int) $anchor->id,
            'holder'           => trim((string) $anchor->party_2),
            'grantor'          => trim((string) $anchor->party_1),
            'transaction_type' => (string) ($anchor->transaction_type ?: $anchor->instrument_type),
            'date'             => $this->displayDate($anchor),
            'prop_id'          => $anchor->prop_id,
        ];

        // ── One grant ────────────────────────────────────────────────────────────
        if (count($grants) < 2) {
            $base['reason'] = 'The Occupancy Permit was granted to ' . $holders[0]
                . ', File Indexing holds ' . $indexedName
                . ', and no dealing on this file explains the change.';

            return $base;
        }

        // ── Several grants: a merger ─────────────────────────────────────────────
        // Two or more permits over two or more parcels, presented on one file and
        // going to one applicant. That is ONE transfer with every OP holder on the
        // giving side — not one transfer per permit, which would read as though the
        // land changed hands twice.
        $base['merger'] = [
            'op_count'  => count($grants),
            'holders'   => $holders,
            'party_1'   => $this->joinHolders($holders),
            'plots'     => $this->distinctValues($grants, 'plot_no'),
            'serials'   => $this->distinctValues($grants, 'op_serial_number'),
            'anchor_id' => (int) $anchor->id,
            'grants'    => array_map(fn ($g) => [
                'pra_id'  => (int) $g->id,
                'holder'  => trim((string) $g->party_2),
                'grantor' => trim((string) $g->party_1),
                'plot_no' => trim((string) $g->plot_no),
                'serial'  => trim((string) $g->op_serial_number),
                'date'    => $this->displayDate($g),
                'prop_id' => $g->prop_id,
            ], $grants),
        ];

        $plots = $base['merger']['plots'];

        $base['reason'] = count($grants) . ' Occupancy Permits are presented on this file'
            . ($plots ? ' (' . implode(', ', array_map(fn ($p) => 'Plot ' . $p, $plots)) . ')' : '')
            . ', granted to ' . $this->joinHolders($holders)
            . '. File Indexing holds ' . $indexedName
            . ', and no dealing on this file explains the change. Matching records ONE '
            . 'Transfer of Title combining all ' . count($grants) . ' permits.';

        return $base;
    }

    /**
     * A transfer already recorded against another permit in the same merger group.
     *
     * Merger sets are held together by pra.merger_group_id. A transfer written for the
     * set is filed under ONE of the group's file numbers, and where that is not the
     * file being searched, this file's own chain has no sight of it — so the plain
     * "no dealing explains the change" test comes back true on a file that has already
     * been dealt with, and Match would duplicate a dealing on somebody's title.
     *
     * @param  array<int,object>  $grants
     */
    private function mergerSiblingTransfer(array $grants, string $indexedName): ?object
    {
        $groups = [];
        foreach ($grants as $g) {
            $group = trim((string) ($g->merger_group_id ?? ''));
            if ($group !== '') {
                $groups[$group] = true;
            }
        }

        if ($groups === []) {
            return null;
        }

        $siblings = DB::connection('sqlsrv')
            ->table('pra')
            ->whereIn('merger_group_id', array_keys($groups))
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->get(['id', 'party_1', 'party_2', 'transaction_type', 'instrument_type', 'mlsFNo', 'fileno']);

        foreach ($siblings as $row) {
            if (! $this->typeContains($row, 'Transfer of Title')) {
                continue;
            }

            // Only a transfer that actually lands on the indexed holder answers the
            // question this card asks. A group carrying some other transfer has not
            // explained why File Indexing holds the name it holds.
            if ($this->sameName($row->party_2, $indexedName)) {
                return $row;
            }
        }

        return null;
    }

    /** "A", "A and B", "A, B and C" — how the register writes joint parties. */
    private function joinHolders(array $holders): string
    {
        $holders = array_values(array_filter(array_map('trim', $holders), fn ($h) => $h !== ''));

        if (count($holders) < 2) {
            return $holders[0] ?? '';
        }

        $last = array_pop($holders);

        return implode(', ', $holders) . ' and ' . $last;
    }

    /**
     * Distinct non-empty values of one column across a set of rows.
     *
     * Sorted naturally rather than left in permit order, because these read as a list
     * of parcels on the card and in the transfer's own description — "PLOTS 1380A &
     * 1380B", not "1380B & 1380A" because that permit happened to be granted first.
     * Natural order so 1380A/1380B sort as text but 9/10 do not sort as 10/9.
     *
     * No pairing with the holder list is implied by either order, and none is drawn.
     *
     * @param  array<int,object>  $rows
     * @return array<int,string>
     */
    private function distinctValues(array $rows, string $column): array
    {
        $out = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '' && ! in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        natcasesort($out);

        return array_values($out);
    }

    /**
     * Put each OP row's parcel back on it.
     *
     * The report engine returns a chain of dealings, not of parcels, so an OP row
     * arrives without the plot it was granted over. On a merger that is the one detail
     * that tells the two permits apart — both say "Occupancy Permit (OP)", both say
     * "Kano State Government →", and only "Plot 1380A" against "Plot 1380B" shows the
     * officer that two separate properties are being combined.
     *
     * Matched back by holder name against this file's pra rows. A row that cannot be
     * paired keeps what it had; nothing here is used for a verdict.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function attachParcelDetails(array $rows, string $fileNumber): array
    {
        $ops = array_values(array_filter($this->praRows($fileNumber), fn ($r) => $this->isOp($r)));

        if ($ops === []) {
            return $rows;
        }

        foreach ($rows as $i => $row) {
            if (empty($row['is_op'])) {
                continue;
            }

            foreach ($ops as $op) {
                if (! $this->sameName($op->party_2, $row['party_2'])) {
                    continue;
                }

                $rows[$i]['plot_no'] = trim((string) $op->plot_no);
                $rows[$i]['op_serial_number'] = trim((string) $op->op_serial_number);
                $rows[$i]['pra_id'] = (int) $op->id;
                break;
            }
        }

        return $rows;
    }

    /**
     * Write the missing Transfer of Title: OP holder -> indexed holder.
     *
     * Re-running check() first is not optional — this is the same guard the button
     * was drawn from, applied at the moment of writing, so a file that was matched
     * in another tab a second earlier cannot be matched twice.
     *
     * @return array{ok:bool, message:string, pra_id:?int}
     */
    public function generateTot(?string $fileNumber, ?int $userId = null): array
    {
        $state = $this->check($fileNumber);

        if (! $state['applies']) {
            return ['ok' => false, 'message' => $state['reason'] ?: 'This file does not need a transfer.', 'pra_id' => null];
        }

        $merger = $state['merger'] ?? null;

        $op = DB::connection('sqlsrv')->table('pra')->where('id', $state['op']['pra_id'])->first();

        if (! $op) {
            return ['ok' => false, 'message' => 'The Occupancy Permit record could not be read back.', 'pra_id' => null];
        }

        // On a merger every OP holder is on the giving side of the one transfer. On an
        // ordinary file that list is one name, and this is the same line it always was.
        $grantor = $merger ? (string) $merger['party_1'] : trim((string) $op->party_2);
        $grantee = trim((string) $state['indexing_name']); // File Indexing's holder is the transferee

        if ($grantor === '' || $grantee === '') {
            return ['ok' => false, 'message' => 'Both party names are needed to record a transfer.', 'pra_id' => null];
        }

        $payload = $this->buildTotPayload($op, $grantor, $grantee, $userId);

        // The permits going into the merger, re-read now rather than trusted from the
        // cached check — this is the set that is about to be stamped.
        $grantIds = $merger ? array_map(fn ($g) => (int) $g['pra_id'], $merger['grants']) : [];
        $group = $merger ? (string) Str::uuid() : null;

        if ($merger) {
            $payload = $this->mergeIntoTotPayload($payload, $merger, $group);
        }

        $id = DB::connection('sqlsrv')->transaction(function () use ($payload, $grantIds, $group) {
            $newId = (int) DB::connection('sqlsrv')->table('pra')->insertGetId($payload);

            // The permits and the transfer are one set. Stamped inside the same
            // transaction as the insert, so the register can never hold a merger
            // transfer whose sources are not marked, or sources marked for a transfer
            // that failed to write.
            if ($grantIds !== []) {
                DB::connection('sqlsrv')->table('pra')
                    ->whereIn('id', $grantIds)
                    ->update([
                        'merger_group_id' => $group,
                        // 1 marks a source permit. The transfer itself is written with
                        // 0 (see mergeIntoTotPayload), so OP and ToT stay tellable
                        // apart inside a group — reading the group is how the sibling
                        // guard avoids offering a second transfer.
                        'is_merger_op'    => 1,
                        'updated_at'      => Carbon::now()->toDateTimeString(),
                    ]);
            }

            return $newId;
        });

        // The file has just changed in the exact way the cached answer denies, so the
        // key goes before anything can read it again — including this method's own
        // caller, which re-checks to redraw the card.
        Cache::forget($this->cacheKey($state['file_number']));

        Log::info('op-holder-match.tot-generated', [
            'file_number'     => $state['file_number'],
            'op_pra_id'       => (int) $op->id,
            'new_pra_id'      => $id,
            'party_1'         => $grantor,
            'party_2'         => $grantee,
            'merger_group_id' => $group,
            'merger_op_ids'   => $grantIds,
            'user_id'         => $userId,
        ]);

        $message = $merger
            ? 'Merger recorded: one Transfer of Title from ' . $grantor . ' to ' . $grantee
                . ', combining ' . $merger['op_count'] . ' Occupancy Permits'
                . ($merger['plots'] ? ' (' . implode(', ', array_map(fn ($p) => 'Plot ' . $p, $merger['plots'])) . ')' : '') . '.'
            : 'Transfer of Title recorded: ' . $grantor . ' to ' . $grantee . '.';

        return [
            'ok'      => true,
            'message' => $message,
            'pra_id'  => $id,
        ];
    }

    /**
     * Turn a single-permit transfer payload into the merged one.
     *
     * What makes this a merger rather than two transfers is that only ONE row is
     * written: the land moved once, from the several people who held the several
     * plots to the one person taking the whole of it. Writing one transfer per permit
     * would read as though the parcel changed hands twice.
     *
     * parent_prop_id takes ALL the source parcels as a comma-separated list, which is
     * the shape LegalSearchService already reads for merger rows — it expands a search
     * across every prop_id named there, so both source properties and their permits
     * surface on the merged file's history without anything further being written.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $merger
     * @return array<string,mixed>
     */
    private function mergeIntoTotPayload(array $payload, array $merger, string $group): array
    {
        // Every distinct source parcel, the anchor's own included: the merged parcel is
        // made of all of them, and the history has to be able to reach each one.
        $parents = [];
        foreach ($merger['grants'] as $g) {
            $pid = trim((string) ($g['prop_id'] ?? ''));
            if ($pid !== '' && ! in_array($pid, $parents, true)) {
                $parents[] = $pid;
            }
        }

        if ($parents !== []) {
            $payload['parent_prop_id'] = implode(',', $parents);
        }

        // Both plot numbers stay on the transfer itself, so the row says what it
        // combined without the reader having to open the permits behind it.
        if (! empty($merger['plots'])) {
            $payload['plot_no'] = implode(', ', $merger['plots']);

            // The description is inherited from the anchor permit, so on a merger it
            // names ONE of the plots and reads as though the transfer covered only that
            // parcel. Rewrite the plot clause to cover all of them, and only that clause:
            // the rest of the line is the district and LGA, which the merger does not
            // change. A description not in that shape is left exactly as it was.
            $all = 'PLOTS ' . implode(' & ', $merger['plots']);

            foreach (['location', 'property_description'] as $field) {
                $value = trim((string) ($payload[$field] ?? ''));

                if ($value !== '' && preg_match('/^PLOTS?\s+[^,]+/i', $value)) {
                    $payload[$field] = preg_replace('/^PLOTS?\s+[^,]+/i', $all, $value);
                }
            }
        }

        if (! empty($merger['serials'])) {
            $payload['op_serial_number'] = implode(', ', $merger['serials']);
        }

        $payload['merger_group_id'] = $group;
        $payload['is_merger_op']    = 0;   // this is the transfer, not one of the sources

        $payload['remarks'] = trim(
            (string) ($payload['remarks'] ?? '')
            . ' Merger of ' . $merger['op_count'] . ' Occupancy Permits'
            . (! empty($merger['plots']) ? ' over ' . implode(' and ', array_map(fn ($p) => 'Plot ' . $p, $merger['plots'])) : '')
            . ' into one file.'
        );

        return $payload;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * The transfer row. Modelled on GenerateStagedTots::buildTotPayload so a
     * transfer reconstructed here is indistinguishable from one reconstructed by
     * the backfill command: copied from the OP, re-typed, re-partied, with the
     * registration particulars zeroed.
     *
     * prop_id comes across untouched: the transfer is a dealing on the same parcel,
     * and prop_id identifies a parcel, not a transaction.
     */
    private function buildTotPayload(object $op, string $grantor, string $grantee, ?int $userId): array
    {
        $payload = (array) $op;

        foreach (self::DO_NOT_COPY as $column) {
            unset($payload[$column]);
        }

        $now = Carbon::now();

        $payload['transaction_type'] = self::TOT_TYPE;
        $payload['instrument_type']  = self::TOT_TYPE;

        $payload['Grantor'] = $grantor;
        $payload['party_1'] = $grantor;
        $payload['Grantee'] = $grantee;
        $payload['party_2'] = $grantee;

        // A reconstructed bridging transfer was never presented to the registry, so
        // it carries no registration particulars — the 0/0/0 convention the OSS
        // Change-of-Name flow and tot:generate-from-staging both use.
        $payload['regNo']     = '0/0/0';
        $payload['serialNo']  = '0';
        $payload['pageNo']    = '0';
        $payload['volumeNo']  = '0';

        $payload['source_op_table'] = 'pra';
        $payload['source_op_id']    = (int) $op->id;

        $payload['is_deleted']    = 0;
        $payload['source']        = self::SOURCE;
        $payload['system_source'] = self::SYSTEM_SOURCE;
        $payload['created_at']    = $now->toDateTimeString();
        $payload['created_by']    = $userId !== null ? (string) $userId : null;

        $payload['remarks'] = trim(
            trim((string) ($op->remarks ?? ''))
            . ' Transfer of Title reconstructed from the OP holder / File Indexing mismatch on '
            . $now->toDateString() . ' (Recommendation capture, Match).'
        );

        return $payload;
    }

    /**
     * The file's chain, read from the SAME engine every other timeline in KLAES
     * reads: LegalSearchService::buildPrintReport(). The Legal Search timeline, the
     * Property Timeline modal, the PHS portal and the emailed report all render
     * these rows, so this card cannot show a file differently from the rest of the
     * system — and, more importantly, cannot judge one differently.
     *
     * That matters because the register keeps dealings in four tables. Reading only
     * pra, this service missed the two Deeds of Assignment in file_history_staging
     * that carry COM-2010-39 from ALH INUWA WADA to BLUEFIELDS OIL & PETROLEUM, and
     * offered to write a transfer the file already had.
     *
     * Ordering is the engine's, not ours: it is the order every other screen shows,
     * and a card that reordered it would be the odd one out.
     *
     * Falls back to pra alone when the report engine yields nothing for a file it
     * cannot key on, so the card still works rather than silently going blank.
     *
     * @return array<int,array<string,mixed>>
     */
    private function chain(string $fileNumber, $propId): array
    {
        $rows = $this->reportRows($fileNumber, $propId);

        if ($rows === []) {
            $rows = $this->praFallbackRows($fileNumber);
        }

        return $this->promoteOccupancyPermit($rows, $fileNumber);
    }

    /**
     * Flag the timeline rows that THIS flow wrote, so the card can say so.
     *
     * Read off pra.system_source rather than guessed from the party names: a file can
     * carry a transfer between the same two people that an officer captured from a
     * real deed, and calling that "system generated" would misdescribe a document
     * somebody actually holds. Only rows stamped by generateTot() are marked.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function markSystemGenerated(array $rows, string $fileNumber): array
    {
        $generated = DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($q) use ($fileNumber) {
                $q->where('mlsFNo', $fileNumber)
                  ->orWhere('fileno', $fileNumber)
                  ->orWhere('kangisFileNo', $fileNumber)
                  ->orWhere('NewKANGISFileno', $fileNumber);
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where('system_source', self::SYSTEM_SOURCE)
            ->orderBy('id')
            ->get(['id', 'party_1', 'party_2', 'transaction_type', 'instrument_type']);

        if ($generated->isEmpty()) {
            return $rows;
        }

        // The instrument is part of the key, not just the two names. COM-2013-45
        // carries a Deed of Assignment between the SAME pair as the transfer this
        // flow wrote — a real deed an officer captured — and matching on names alone
        // labelled that deed "system generated", which is exactly the misdescription
        // this method exists to avoid.
        // Keyed to the pra id, not just to true/false. A file matched on the Match OP
        // page is captured minutes or days later on a form that finds nothing left to
        // press, so the id of the transfer written here is the only way that capture
        // can record WHICH row put it into "existing recommendation" mode. Earliest id
        // wins on the (impossible in practice) tie, so the answer is stable.
        $keys = [];
        foreach ($generated as $row) {
            $type = $this->norm($row->transaction_type ?: $row->instrument_type);
            $key = $type . '|' . $this->norm($row->party_1) . '|' . $this->norm($row->party_2);
            $keys[$key] = $keys[$key] ?? (int) $row->id;
        }

        foreach ($rows as $i => $row) {
            $key = $this->norm($row['type'] ?? '') . '|'
                . $this->norm($row['party_1'] ?? '') . '|'
                . $this->norm($row['party_2'] ?? '');
            $rows[$i]['system_generated'] = isset($keys[$key]);
            $rows[$i]['system_generated_pra_id'] = $keys[$key] ?? null;
        }

        return $rows;
    }

    /**
     * The OTHER file number on the OP's row, when it names one that is not the file
     * being captured — or null when the row is unambiguous.
     *
     * TEMP- numbers are excluded: an unlinked OP capture sits on a system placeholder
     * rather than on a file of its own, so it is not a competing claim.
     */
    private function conflictingFileNumber(object $source, string $fileNumber): ?string
    {
        $wanted = $this->norm($fileNumber);

        foreach ([$source->mlsFNo ?? '', $source->fileno ?? ''] as $candidate) {
            $value = trim((string) $candidate);

            if ($value === '' || $this->norm($value) === $wanted) {
                continue;
            }

            if (stripos($value, 'TEMP-') === 0) {
                continue;
            }

            return $value;
        }

        return null;
    }

    /**
     * On a file that has an Occupancy Permit, the OP leads the chain and IS the root
     * of title.
     *
     * The report engine does not always say so. COM-2016-219 comes back with File
     * Commissioning first, badged "RoT: Allocation List", and the OP third — while
     * TitleHolderResolver, which fills the Root of Title box at the top of this same
     * card, names the OP holder. Two answers to one question on one card is worse
     * than either answer, and on this screen the OP is the answer that matters: it is
     * the grant the missing transfer would be reconstructed from.
     *
     * The engine does not always agree. On COM-2016-219 it returns File Commissioning
     * first, badged "RoT: Allocation List", because the OP row names a second file
     * number (COM-2026-219) in another column and its rule 1 reads that one. On this
     * card the OP leads and holds the badge regardless — an OP is the root of title
     * wherever there is one — and where the row's two file numbers disagree the card
     * says so instead of quietly picking a side.
     *
     * Applied to this card only. The same ordering inside LegalSearchService drives
     * the printed Legal Search report, the PHS portal and the online report, and
     * implements an agreed spec — it is not rewritten from here.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function promoteOccupancyPermit(array $rows, string $fileNumber): array
    {
        $ops = [];
        $rest = [];

        foreach ($rows as $row) {
            if ($row['is_op']) {
                $ops[] = $row;
            } else {
                $rest[] = $row;
            }
        }

        if ($ops === []) {
            return $rows;
        }

        // Whatever the engine had marked gives up the badge, so the roots of title are
        // the OPs and only the OPs.
        foreach ($rest as $i => $row) {
            $rest[$i]['root_of_title'] = '';
        }

        // EVERY OP is badged, not just the first. On a merger the file is rooted in two
        // grants at once — 1380A to one holder and 1380B to another — and calling only
        // the earlier one the root of title would describe the parcel as if half of it
        // arrived from nowhere. On the ordinary single-OP file this is the same one
        // badge it always was.
        foreach ($ops as $i => $row) {
            $ops[$i]['root_of_title'] = $row['root_of_title'] ?: $row['type'];
        }

        return array_values(array_merge($ops, $rest));
    }

    /**
     * The File Commissioning row carries the name the file is INDEXED under.
     *
     * The report engine reads that row's Party 2 off the instrument that opened the
     * file (LegalSearchService::COMMISSIONING_HOLDER_SOURCES — an OP contributes its
     * grantee) and only falls back to the file title. On a file in exactly the state
     * this card exists for, that prints the OP holder twice: RES-2022-1447 shows the
     * OP granted to Lamash and then "commissioned for" Lamash, while the file itself
     * is indexed to YAHAYA KARAMI SANI. The card then reads as though only File
     * Indexing were out of step, when the point being made is that the file is held
     * in one name and the chain ends in another.
     *
     * So on this card the commissioning row shows the name on the file. The OP row
     * above keeps the OP's grantee, which is what keeps the two names — and the gap
     * between them — visible.
     *
     * Only the searched file's own commissioning rows: an ST, DCIV or successor
     * commissioning row belongs to a different file and keeps whatever the engine
     * gave it. "(T)" is ignored in that comparison so the Temporary File row, which
     * carries the same holder and the same file under its temporary number, is
     * renamed with it.
     *
     * Display only. A commissioning row is neither an OP nor an ownership-changing
     * dealing (TitleHolderResolver decides the latter), so no verdict below is read
     * from the name written here.
     *
     * Applied to this card only — the same row on the printed Legal Search report,
     * the PHS portal and the online report is the engine's to name.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function nameCommissioningFromIndexing(array $rows, string $fileNumber, string $indexedName): array
    {
        if (trim($indexedName) === '') {
            return $rows;
        }

        $key = fn ($v) => strtoupper(preg_replace('/[\s\-_\/]+|\(\s*T\s*\)$/i', '', trim((string) $v)));
        $target = $key($fileNumber);

        foreach ($rows as $i => $row) {
            $source = trim((string) ($row['source'] ?? ''));

            if (! in_array($source, ['File Commissioning', 'Temporary File'], true)) {
                continue;
            }

            // A row with no file number of its own is the searched file's — the
            // engine only ever emits these two for the file being searched.
            $rowFileNo = trim((string) ($row['file_no'] ?? ''));
            if ($rowFileNo !== '' && $target !== '' && $key($rowFileNo) !== $target) {
                continue;
            }

            $rows[$i]['party_2'] = trim($indexedName);
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function reportRows(string $fileNumber, $propId): array
    {
        try {
            $query = ['file_number' => $fileNumber];
            if (trim((string) $propId) !== '') {
                $query['prop_id'] = trim((string) $propId);
            }

            $report = $this->legalSearch->buildPrintReport($query);

            if (($report['status'] ?? null) !== 200) {
                return [];
            }

            $rows = $report['payload']['data']['rows'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('op-holder-match.report-engine-failed', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);

            return [];
        }

        // "-" is the report's placeholder for an absent value; the card renders its
        // own em-dash for empty, so translate rather than printing a literal dash.
        $blank = fn ($v) => ($v === null || trim((string) $v) === '' || trim((string) $v) === '-')
            ? '' : trim((string) $v);

        $mapped = [];
        foreach ($rows as $row) {
            $type = $this->tidyInstrument($blank($row['instrument_type'] ?? null) ?: 'Transaction');

            $mapped[] = [
                'type'     => $type,
                'party_1'  => $blank($row['grantor'] ?? null),
                'party_2'  => $blank($row['grantee'] ?? null),
                // transaction_date FIRST, reg_date only as a fallback — for the OP
                // above all, whose date is the date of the grant. Registration is a
                // later, separate event, and on a chain that argues about when the
                // title moved, showing the registration date as if it were the
                // dealing's date misdates the grant the transfer is built from.
                'date'     => $blank($row['transaction_date'] ?? null) ?: $blank($row['reg_date'] ?? null),
                'reg_no'   => $blank($row['reg_no'] ?? null),
                'source'   => $blank($row['source_table'] ?? null),
                // Which file the row actually belongs to. The report gathers a whole
                // PARCEL, so a row here can belong to a different file that shares
                // the prop_id — and a dealing on someone else's file says nothing
                // about this one's title.
                'file_no'  => $blank($row['lifecycle_file_no'] ?? null) ?: $blank($row['file_no'] ?? null),
                // The engine stamps this on the ONE row that is the root of title,
                // and that row is the whole subject of this card — so it is carried
                // through and badged exactly as the Property Timeline badges it.
                'root_of_title' => $blank($row['root_of_title'] ?? null),
                'is_op'    => stripos($type, 'Occupancy Permit') !== false,
                'is_tot'   => stripos($type, 'Transfer of Title') !== false,
                'system_generated' => false,
            ];
        }

        return $mapped;
    }

    /**
     * pra on its own, shaped like a report row. Only reached when the report engine
     * returns nothing for the file.
     *
     * @return array<int,array<string,mixed>>
     */
    private function praFallbackRows(string $fileNumber): array
    {
        return array_map(function ($r) use ($fileNumber) {
            $type = (string) ($r->transaction_type ?: $r->instrument_type ?: 'Transaction');

            return [
                'type'    => $type,
                'party_1' => trim((string) $r->party_1),
                'party_2' => trim((string) $r->party_2),
                'date'    => $this->displayDate($r),
                'reg_no'  => trim((string) $r->regNo),
                'source'  => 'pra',
                'file_no' => $fileNumber,
                'root_of_title' => '',
                'is_op'   => stripos($type, 'Occupancy Permit') !== false,
                'is_tot'  => stripos($type, 'Transfer of Title') !== false,
                'system_generated' => false,
            ];
        }, $this->praRows($fileNumber));
    }

    /** Every live pra row for the file, oldest first. */
    private function praRows(string $fileNumber): array
    {
        return DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($q) use ($fileNumber) {
                $q->where('mlsFNo', $fileNumber)
                  ->orWhere('fileno', $fileNumber)
                  ->orWhere('kangisFileNo', $fileNumber)
                  ->orWhere('NewKANGISFileno', $fileNumber);
            })
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderBy('id')
            ->get([
                'id', 'party_1', 'party_2', 'transaction_type', 'instrument_type',
                'transaction_date', 'created_at', 'prop_id', 'regNo', 'source',
                // Both file-number columns: conflictingFileNumber() compares them, and
                // a row that names two different files has to be able to say so.
                'mlsFNo', 'fileno',
                // The parcel each OP was granted over. On a merger the whole point is
                // that two DIFFERENT plots became one file, so the plot numbers have to
                // survive as far as the card — 1380A and 1380B are how the officer
                // recognises which permit is which.
                'plot_no', 'op_serial_number',
                // Membership of an existing merger set, read so this service never
                // offers to write a transfer a sibling row already carries.
                'merger_group_id', 'is_merger_op',
            ])
            ->all();
    }

    /**
     * Does this dealing move the title? Asked of TitleHolderResolver so a mortgage,
     * a surrender, a caveat or a recertification is treated here exactly as it is
     * treated in Legal Search and on the holder lines.
     */
    private function movesOwnership(array $row): bool
    {
        return $this->titleHolders->movesOwnership([
            'transaction_type' => $row['type'],
            'instrument_type'  => $row['type'],
        ]);
    }

    /**
     * The "Root of title" line, guaranteed to describe the grant the chain badges.
     *
     * TitleHolderResolver means something narrower by Root of Title — the dealing the
     * grant DERIVED FROM, which most files simply do not have — so on this card it can
     * disagree with the row marked "-RoT" two lines below it, in two ways:
     *
     *   CON-AG-2005-57   returns nothing at all (its permit is typed "Occupancy Permit"
     *                    with no "(OP)" suffix), printing a dash under a chain whose OP
     *                    is plainly the root.
     *   CON-RES-2024-306 returns the later Transfer of Title as the root, naming an
     *                    instrument the card has not badged.
     *
     * So the resolver's wording is kept only when it is describing THIS OP — same
     * holder, and an Occupancy Permit. Otherwise the line is built from the badged row.
     * That holds the client's rule ("where an OP exists it is always the Root of
     * Title") and keeps the card internally consistent, which a reader can check by
     * eye and would otherwise catch us out on.
     *
     * @param  array<string,mixed>  $op
     */
    private function rootOfTitleFor(array $op, string $fileNumber, $propId): ?string
    {
        $holder = trim((string) $op['party_2']);
        $resolved = $this->rootOfTitleLabel($fileNumber, $propId);

        if ($resolved !== null
            && $holder !== ''
            && stripos($resolved, $holder) === 0
            && preg_match('/\(\s*(OP|Occupancy Permit)\s*\)/i', $resolved)) {
            return $resolved;
        }

        if ($holder === '') {
            return $resolved;
        }

        return $holder . ' (OP)';
    }

    /**
     * The holder of every Occupancy Permit on this file, with the plot it was
     * granted over.
     *
     * `root_of_title` stays exactly as it was — one line, the grant the engine
     * badges "-RoT" — and this is what the card lists instead when there is more
     * than one. Two permits granted to two people over two plots ARE the root of
     * title of a merged file, in every state the card can be in.
     *
     * Distinct by holder AND plot: one man merging two of his own plots is two
     * permits and must read as two, while the same permit reaching this list twice
     * must not.
     *
     * @return array<int,array{holder:string, plot_no:string, serial:string, pra_id:int}>
     */
    private function rootHolders(string $fileNumber): array
    {
        $grants = array_values(array_filter($this->praRows($fileNumber), fn ($r) => $this->isOp($r)));

        $out = [];
        foreach ($grants as $g) {
            $holder = trim((string) $g->party_2);
            $plot   = trim((string) $g->plot_no);

            if ($holder === '') {
                continue;
            }

            $key = $this->norm($holder) . '|' . $this->norm($plot);
            if (isset($out[$key])) {
                continue;
            }

            $out[$key] = [
                'holder'  => $holder,
                'plot_no' => $plot,
                'serial'  => trim((string) $g->op_serial_number),
                'pra_id'  => (int) $g->id,
            ];
        }

        return array_values($out);
    }

    private function rootOfTitleLabel(string $fileNumber, $propId): ?string
    {
        try {
            $holders = $this->titleHolders->resolveForDisplay($fileNumber, $propId ?: null);
            return $holders['root_of_title'] ?: null;
        } catch (\Throwable $e) {
            // The card is worth showing without this line; it is context, not the rule.
            Log::warning('op-holder-match.root-of-title-failed', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Undo what the report engine's title-casing does to instrument names.
     *
     * It upper-cases the first letter of every word, which turns the acronyms into
     * words: "Occupancy Permit (OP)" comes back as "(Op)" and "(OSS)" as "(Oss)".
     * The engine cannot tell an acronym from a word, so the names are restored here,
     * where the card knows which ones they are.
     */
    private function tidyInstrument(string $type): string
    {
        $type = preg_replace_callback(
            '/\((op|oss|rc|cofo|dciv|sltr|st)\)/i',
            fn ($m) => '(' . strtoupper($m[1]) . ')',
            $type
        );

        // "Transfer Of Title" is the same casing artefact one word further in.
        // Word-bounded: unbounded, this reaches inside names like "Ofoegbu".
        return preg_replace('/\bOf\b/', 'of', $type);
    }

    private function isOp(object $row): bool
    {
        return $this->typeContains($row, 'Occupancy Permit');
    }

    private function typeContains(object $row, string $needle): bool
    {
        return stripos((string) $row->transaction_type, $needle) !== false
            || stripos((string) $row->instrument_type, $needle) !== false;
    }

    /**
     * Case- and whitespace-insensitive only. Deliberately NOT fuzzy: a name that
     * merely SOUNDS like the file title is the spelling-drift case, which must fall
     * through to has_working_transfer rather than be treated as a match.
     */
    private function sameName($a, $b): bool
    {
        return $this->norm($a) !== '' && $this->norm($a) === $this->norm($b);
    }

    private function norm($value): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $value)));
    }

    /**
     * Honorifics and spelling apart, are these two the same person?
     *
     * Titles are stripped (ALH, HAJIYA, DR, ...) and the remaining words sorted, so
     * "AHMAD MUHAMMAD" and "MUHAMMAD AHMAD" collapse together; what is left is
     * compared on edit distance, which catches MARYSAM/MARYAM and IBRAHIM/IBRAJIM.
     *
     * 0.80 was read off the data rather than picked: every pair at or above it in
     * the estate is one person written twice, and the nearest genuinely different
     * pair sits well below.
     *
     * This is the OPPOSITE of sameName(), which is exact on purpose. The two answer
     * different questions — "is this the holder on record" (exact) against "would
     * transferring between these two be a nonsense" (fuzzy).
     */
    private function looksLikeSamePerson($a, $b): bool
    {
        $left  = $this->personKey($a);
        $right = $this->personKey($b);

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        $longest = max(strlen($left), strlen($right));

        return $longest > 0 && (1 - (levenshtein($left, $right) / $longest)) >= 0.80;
    }

    /** Upper-cased, punctuation-free, honorific-free, word-sorted. */
    private function personKey($value): string
    {
        $clean = preg_replace('/[^A-Z0-9 ]+/', ' ', strtoupper(trim((string) $value)));
        $words = array_values(array_filter(
            preg_split('/\s+/', trim($clean)),
            fn ($w) => $w !== '' && ! in_array($w, self::HONORIFICS, true)
        ));

        sort($words);

        return implode(' ', $words);
    }

    /**
     * The transfers this flow has written, newest first.
     *
     * The Match OP page is worked as a run — file after file — and without this the
     * officer has no sight of what the run has produced: the confirmation dialog is
     * gone the moment it is dismissed, and the card only ever describes the ONE file
     * in the box. This is the record of the work.
     *
     * Scoped to `system_source` rather than to the transaction type, so it lists what
     * MATCH wrote and nothing else. Every other Transfer of Title in the register —
     * deeds keyed off paper, OSS captures — belongs on the ToT dashboard, which lists
     * them all; this table answers "what have we matched", not "what transfers exist".
     *
     * @return array<int,object>
     */
    public function recentMatches(int $limit = 25): array
    {
        $rows = $this->matchedByHand()
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get([
                'id', 'mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno',
                'party_1', 'party_2', 'transaction_type', 'instrument_type',
                'plot_no', 'op_serial_number', 'prop_id', 'merger_group_id',
                'transaction_date', 'created_at', 'created_by',
                // Carries the backfill's run id when the command wrote the row. Two
                // different acts produce these transfers and the table must not
                // present them as one — see backfillRunId().
                'remarks',
            ])
            ->all();

        $userIds = [];
        foreach ($rows as $row) {
            $id = (int) $row->created_by;
            if ($id > 0) {
                $userIds[$id] = true;
            }
        }

        $names = [];
        if ($userIds !== []) {
            try {
                foreach (\App\Models\User::whereIn('id', array_keys($userIds))->get(['id', 'first_name', 'last_name']) as $user) {
                    $names[(int) $user->id] = trim($user->first_name . ' ' . $user->last_name);
                }
            } catch (\Throwable $e) {
                // A missing name is not worth losing the table over.
                Log::warning('op-holder-match.recent-matches-names-failed', ['error' => $e->getMessage()]);
            }
        }

        foreach ($rows as $row) {
            $row->run_id      = self::backfillRunId($row->remarks ?? null);
            $row->recorded_by = $names[(int) $row->created_by] ?? null;
        }

        return $rows;
    }

    /**
     * The backfill run that wrote this transfer, or null when a person pressed Match.
     *
     * Two things write with this system_source — the Match button, one file at a time,
     * and `op-match:backfill`, which does hundreds in a single command — and reading
     * the table without that distinction, an officer sees a hundred files they never
     * touched and concludes the page has recorded transfers on its own. The run id is
     * stamped into pra.remarks as "[OPMB-<date>-<time>]" by the command, and it is
     * also what `op-match:rollback` undoes a run by.
     */
    public static function backfillRunId(?string $remarks): ?string
    {
        if (! $remarks) {
            return null;
        }

        return preg_match('/\[(OPMB-\d{8}-\d{6})\]/', $remarks, $m) ? $m[1] : null;
    }

    /** How many transfers have been matched by hand. Same scope as the list. */
    public function matchCount(): int
    {
        return (int) $this->matchedByHand()->count();
    }

    /**
     * Transfers matched BY HAND on the Match OP page — the backfill's are excluded.
     *
     * Two things write with this system_source, and they are not the same act. A
     * person picks a file, reads its chain and presses Match; `op-match:backfill`
     * does hundreds in one command. Listing them together showed an officer a
     * hundred files nobody on this page had touched, which reads as the page having
     * recorded transfers on its own — so until the backfill has a presentation of
     * its own, its rows are not listed here at all.
     *
     * The command stamps its run id into pra.remarks as "[OPMB-<date>-<time>]", so
     * that is what identifies them. "[" opens a character class in T-SQL LIKE, hence
     * "[[]" to match one literally; the NULL arm is needed because NOT LIKE against
     * NULL is NULL, which would drop every row that has no remarks — which is most
     * of the hand-matched ones.
     */
    private function matchedByHand(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('sqlsrv')
            ->table('pra')
            ->where('system_source', self::SYSTEM_SOURCE)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->where(function ($q) {
                $q->whereNull('remarks')->orWhere('remarks', 'NOT LIKE', '%[[]OPMB-%');
            });
    }

    private function displayDate(object $row): ?string
    {
        $raw = $row->transaction_date ?: ($row->created_at ?? null);

        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('d M Y');
        } catch (\Throwable $e) {
            return (string) $raw;
        }
    }
}
