# Transaction History — missing Transfer of Title detection

**Status:** planned, not built
**Date:** 2026-09-01
**Scope:** the Transaction History card shown during File Indexing, **conversion (`CON-`) files only**
**Behaviour:** **advisory** — it warns and pre-fills, it does not block Submit
**Supersedes on this screen:** Match OP (commented out of the indexed-files action menu)

---

## Why this and not Match OP

Match OP asks one question: *does the file's Occupancy Permit name someone other than
the indexed holder?* That question is unanswerable on a conversion file, because
conversions almost never have an OP. Measured on 2026-09-01:

| population | files | with an OP in `pra` |
|---|---:|---:|
| `CON-` indexed files | 44,509 | **2** |

Both of those two are already accounted for. So the Match OP item could never fire on a
conversion, which is what made it read as broken when clicked.

This feature asks a different and better question, one a conversion file *can* answer:
**does the transaction history show ownership moving between two people without a
Transfer of Title recording the move?** It reads the chain, so it needs no OP.

---

## The rule

For the file's complete transaction set — everything already on the file **plus**
everything being captured in the card right now — sorted chronologically:

1. Read `party_1` as the party giving the interest, `party_2` as the party receiving it.
2. Consider consecutive links in the chain: where transaction *n* leaves the title with
   `X` and transaction *n+1* starts from `Y`, the leg `X → Y` is an ownership move.
3. A leg needs a Transfer of Title unless one **already exists between those same two
   parties**, whether on file or in the card.
4. Every leg is inspected. Not just the last owner against the applicant.
5. An existing TOT is never duplicated.

### What counts as an ownership move

Reuse `TitleHolderResolver::movesOwnership()` — already public, already the rule Legal
Search and the holder lines use. Do **not** restate the list here; it is:

- **moves ownership:** deed of assignment, transfer of title, deed of sub-assignment,
  deed of conveyance, deed of gift, deed of transfer, vesting deed, sale agreement, …
- **never moves ownership:** mortgage, surrender, release, caveat, search,
  recertification, change of purpose, sub-lease, power of attorney, lease

A mortgage between `X` and a bank is not a leg. A recertification is not a leg.

---

## Worked examples

### Example 1 — a single missing leg

On file:

| # | instrument | party_1 | party_2 |
|---|---|---|---|
| 1 | Right of Occupancy | Kano State Government | **AUDU BELLO** |
| 2 | Deed of Assignment | **MUSA IDRIS** | HALIMA SANI |

The chain leaves the title with `AUDU BELLO`, then the next dealing *starts* from
`MUSA IDRIS`. Nothing explains how it got from one to the other.

**Detected:** 1 missing TOT — `AUDU BELLO → MUSA IDRIS`.

### Example 2 — merger, two files, two different chains

The client's case. Two files merge into one; each carries its own chain and each is
checked **separately**.

```
File A:  Original owner ─────────────────────────────► New owner
File B:  Original owner ──► Intermediate owner ──► New owner
```

Legs requiring a TOT:

| chain | leg | needed? |
|---|---|---|
| File A | Original → New | yes |
| File B | Original → Intermediate | yes |
| File B | Intermediate → New | yes |

If, say, `Intermediate → New` is already captured, only the other two are requested.
**Three legs are examined, not one**, and a TOT that already exists is skipped.

### Example 3 — a long chain with a gap in the middle

| # | instrument | party_1 | party_2 |
|---|---|---|---|
| 1 | Occupancy Permit | Kano State Government | **Owner 1** |
| 2 | *(nothing on file)* | | |
| 3 | Transfer of Title | **Owner 2** | Owner 3 |
| 4 | Transfer of Title | Owner 3 | Owner 4 |
| 5 | Deed of Assignment | Owner 4 | **New owner** |

Legs 3→4, 4→5 are covered. The gap is `Owner 1 → Owner 2`.

**Detected:** 1 missing TOT — `Owner 1 → Owner 2`.

This is the case the rule exists for. A check that only compared the last owner
(`Owner 4`) with the new owner would report *nothing missing* and leave the break at
the top of the chain undetected forever.

### Example 4 — nothing missing

| # | instrument | party_1 | party_2 |
|---|---|---|---|
| 1 | Right of Occupancy | Kano State Government | ABBA SANI |
| 2 | Transfer of Title | ABBA SANI | YUSUF GARBA |
| 3 | Mortgage | YUSUF GARBA | First Bank plc |

Leg 1→2 is covered by the TOT. The mortgage is not an ownership move, so it opens no
leg of its own. **Detected: nothing.** Submit proceeds untouched.

---

## Interface behaviour — advisory

**Decided 2026-09-01: the check warns, it does not block.** The officer can always
complete the indexing. See "Why advisory" below.

On **Submit**, before the existing validations run:

- For each missing leg, append a transaction section to the **same** card.
- Style it red — red left border, red header strip, `TRANSFER OF TITLE MISSING` label.
- Pre-select **Transfer of Title** in the instrument dropdown.
- Pre-fill `party_1` / `party_2` from the leg, where both names are known.
- Leave every other field for the user (date, registration particulars, land use).
- One section per missing leg, in chain order, each naming its leg
  (*"Missing transfer: AUDU BELLO → MUSA IDRIS"*).
- Show a summary line above the card: *"2 ownership transfers on this file have no
  Transfer of Title. Complete them below, or submit without them."*
- **Submit stays enabled throughout.** The first press reveals the sections and does not
  save; a second press saves whatever is in the card, filled sections included and empty
  ones dropped. So the warning is unmissable but never a dead end.
- Re-run the check when sections are filled, so completing one leg does not silently
  leave another outstanding.

The **plus (+) button keeps working exactly as it does now.** Auto-added sections are
ordinary transaction blocks; the user may edit or delete them, and the red styling
clears once a section validates.

### Why advisory

A blocking gate assumes every leg it finds is real. On legacy conversion files that
assumption does not hold — names drift between capture sources, chains are incomplete
because the paper is incomplete, and some gaps are genuinely unknowable from the file.
A block would stop indexing on data the officer cannot fix, on the strength of a rule
that has never been measured against live conversion data. Advisory surfaces the same
finding, pre-fills the same work, and leaves the judgement with the person holding the
physical file.

If measurement later shows the legs are reliably real, tightening advisory → blocking is
a one-line change. Loosening a block after officers have been stopped by it is not.

### No server-side guard is needed

Because nothing is blocked, there is nothing for a direct post to bypass — the question
only arises for a rule that must always hold. `storeIndexing` is left alone.

The one thing worth considering is a **record** rather than a guard: when the officer
submits with legs still outstanding, log the file number and the legs. That turns the
warning into something reportable later ("which conversion files were indexed with a
known ownership gap?") without standing in anyone's way. Cheap to add, and it is the
only reason the server would need to hear about this at all.

---

## Where it goes

Everything is in
`resources/views/fileindexing/partial/property_transaction_modal.blade.php`
(2,629 lines, Alpine component). **The pieces this needs already exist:**

| existing | line | what it gives us |
|---|---|---|
| `transactions[]` | — | the capture blocks, `firstParty` / `secondParty` per block |
| `addTransaction()` | 222 | appends a block — reuse verbatim for the red sections |
| `fhOnFileRows` | 118 | history already on the file, loaded by `fhLoadFileHistory()` (709) |
| `fhCapturedRows()` | 519 | the capture blocks, normalised to the same shape |
| `fhSummaryRows()` | 566 | **on-file + captured, deduped and chronological** — the input |
| `fhSignature()` | 509 | honorific-stripped party/instrument key — the dedupe test |
| `fhSortTimestamp()` | 642 | the ordering already used by the card |
| `submitTransactions()` | 925 | where the gate is added, ahead of the existing checks |

So the new code is roughly: one function to walk `fhSummaryRows()` into legs, one to
test each leg against existing TOTs via `fhSignature()`, one to append red blocks, and
a guard at the top of `submitTransactions()`.

### `fhSignature()` is the duplicate test

It already normalises honorifics and punctuation
(`ALH TIJJANI` ≡ `Alh. Tijjani`) and folds in the registration number. A leg
`X → Y` is already covered when a Transfer of Title row exists whose signature matches
`TRANSFEROFTITLE|X|Y|`. Reusing it means the gap check and the card's own de-duplication
can never disagree about whether two rows are the same dealing.

---

## Traps

1. **Name spelling is not identity.** `MOHD` / `MUHD`, `OZATAMGBO` / `OZOTAMGBO` — 36
   files estate-wide are one person spelt two ways. A literal comparison invents a leg
   and asks the user to record a transfer from a man to himself. Apply the same
   `looksLikeSamePerson()` guard `OpHolderMatchService` uses (honorific-stripped,
   word-sorted, levenshtein ≥ 0.80): if the two sides of a leg look like the same
   person, **do not** raise a TOT — that is a name correction, not a dealing.
2. **Derived rows are not legs.** `File Commissioning` and `Temporary File` rows are
   synthetic context. `fhSummaryRows()` already flags them `derived: true`; skip those.
3. **Government grants open a chain, they do not continue one.** A Right of Occupancy or
   OP from `Kano State Government` is the start. Never raise a leg *into* a grant.
4. **Undated rows sort last, not to 1970.** `fhSortTimestamp()` handles this; do not
   re-implement date parsing.
5. **Only warn on what the user can fix.** A leg between two parties who both appear
   nowhere else on the file is more likely bad legacy data than a missing transfer.
   Consider a "review" tone rather than a hard block for those, pending live data.

---

## Decisions taken (2026-09-01)

| question | decision |
|---|---|
| Blocking or advisory? | **Advisory.** Warn, pre-fill, never stop the save. |
| Scope | **Conversion (`CON-`) files only.** Gate on the file number prefix. |
| Server-side guard | **Not needed** — nothing is blocked, so nothing can be bypassed. Optionally log outstanding legs at submit, as a record rather than a gate. |

### The scope gate

```js
const isConversionFile = /^\s*CON-/i.test(String(fileNumber || ''));
```

Same test the Match OP menu item used. On any other prefix the check does not run at
all — no legs computed, no sections added, `submitTransactions()` untouched. Worth
keeping the gate in **one named function** rather than inline, since the rule is not
conversion-specific and widening it later should be a single edit.

## Still open

- **Measure before building.** Run the leg-walk read-only across indexed `CON-` files
  and count how many legs a typical file raises. If the median is 0–1 the feature is
  useful as designed; if it is 5+, most are legacy noise and the pre-fill becomes
  clutter rather than help. This is a half-day script and it decides whether the red
  sections are worth adding at all.
- **Should completed sections save as ordinary transactions?** Assumed yes — they are
  normal blocks in the same card and go down the existing save path. Confirm.

---

## Related

- `app/Services/OpHolderMatchService.php` — the OP-based check this parallels; its
  `looksLikeSamePerson()`, `movesOwnership()` and merger handling all transfer over.
- `app/Services/TitleHolderResolver.php` — `movesOwnership()`, the shared rule.
- `docs/` memory notes: OP holder vs Indexing Match flow; Merger OP (OSS capture).
