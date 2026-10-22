# KLAES REV-M — Bill Master Plan

**Status:** DRAFT v3 for review. Add corrections inline (mark them `> CORRECTION:`) and I will fold them in.
**Started:** 2026-10-18 · **Revised:** 2026-10-19 (v3: answers to the v2 open questions)
**LuC:** on hold until we have more information about it.
**Build approach:** the generate → pay → receipt flow is built **once, for every Bill Master type**. Bill Balance was only the example, because it is on the sidebar; it is not a "first" type.

---

## 1. What we agreed

| # | Decision |
|---|---|
| 1 | **Two consolidated bill tables**, each with a bill type column. No separate table per bill type. |
| 1a | **`bill_master`** holds **system bills**: every bill KLAES generates in any module (Bill Balance, Consent, ST initial bills, SPAS, Cadastral P&D, OSS, Legal Search, PHS portal, …). Membership depends on where the bill came from, not on its date. |
| 1b | **`bill_legacy`** holds **legacy bills**: the historical bills entered through File Indexing. |
| 2 | **The Bill Ref ID is issued by Bill Master** and is the number used to settle (pay) a bill. Every bill has one. |
| 3 | **System bill ref format:** one running sequence for every type, `BILL-{year}-{000001}`. |
| 4 | **Legacy bill ref format:** `LGB-{year}-{000001}`. |
| 5 | **Existing module bills are renumbered** to Bill Master refs. None have been printed yet. |
| 6 | **Existing module bills come in as UNPAID** (Bill Balance, Consent, etc.), whatever their current payment fields say. |
| 7 | **Payments go in their own table** (`bill_payments`), linked by Bill Ref ID. A bill can take several payments, each with its own Transaction ID. |
| 8 | **Every bill has line items** (`bill_items`), one line per charge. |
| 9 | **Bill settings live in Configurable Entries → Bill Settings**, with a sub-tab per bill type, each holding its settings and bill items. SPAS Bill Items moves there first. Each module **keeps its own items table** behind its sub-tab. |
| 10 | **The empty Bill Formulas engine (`billing_types`) is folded into Bill Settings.** It becomes the items/formula engine for bill types that have no module table yet. |
| 11 | **Legal search top-ups and the PHS portal are bills too**, so they go into `bill_master`. |
| 12 | **`bill_master.transaction_id` / `receipt_no` stay** as shortcuts to the latest payment, updated by the payment service. `bill_payments` is the full record. |
| 13 | **Three documents, kept apart** (section 3): the **POS invoice** (bill stage), **payment** (bill stage), and the **official Ministry receipt** in three copies (Receipting). |
| 14 | **Automated Billing (Bill Master) and Legacy Billing stay clearly separate.** |
| 15 | **The modules' own payment fields are left untouched** when their bills load into Bill Master as unpaid. |
| 16 | **Legacy bills keep the receipt captured at indexing.** They are history, not unpaid. |
| 17 | **One invoice layout.** After payment the same POS invoice reprints with a **PAID** stamp. |
| 18 | **Payment methods for now:** **Paystack** live (reusing the existing Paystack setup); **POS** = the officer records the POS Transaction ID against the Bill Ref ID (no terminal integration); **Bank Transfer** shown but **disabled**, so it is visible and not selectable. |
| 19 | **Official receipt numbers are system-generated:** `RCT-{year}-{000001}`. |
| 20 | **New PHS bill types:** "PHS Onboarding / Subscription" and "PHS Token Top-up". |

---

## 2. Where things stand today (inspected 2026-10-18)

### 2.1 Already built in this round
- `bill_master_types`: 14 types, including `legacy_bill`, which moves out (see 4.1).
- `bill_master`: empty, with `bill_ref_id` (required, unique, auto `BILL-YYYY-000001`) and `transaction_id`.
- REV-M sidebar rebuilt to the docx tree; every item opens a listing page (UI only).
- `App\Http\Controllers\Revenue\BillMasterController`, plus views in `resources/views/revenue/`.

### 2.2 System bills that exist in the modules (→ `bill_master`, as UNPAID)

| Source | Rows | Current ref | Ref generated in | Bill type |
|---|---|---|---|---|
| `deeds_bill_balances_metadata` + its `billing` row (`DEEDS_BILL_BALANCE`) | 600 (+597 `billing`) | `reference` = `DBBL-2026-00600` | `BillBalanceController.php:459` | Bill Balance |
| `consent_applications` (rows with a `bill_total`) | 77 of 699 | none | consent bill computation | Consent Bill |
| `billing`, source NULL (ST) | 112 | file number in `ref_id` | — | Initial Bill: 12 Primary, 100 unit (PuA/SuA) |
| `final_bills` | 1 | none | `FinalBillController` / `SubFinalBillController` | **Bill Balance** (the one generated for PuA), see 2.4 |
| `billing`, source `OSS_OP_APPLICATION_FEE` | 2 | file number | — | OSS Bill |
| `cadastral_bills` | 2 | `bill_ref` = `CBL-2026-0002` | `CadastralBillCalculator.php:278` | Fees, Plan & Description |
| `spa_bills` | 10 | `reference_id` = `SPAS-BILL-2026-014` | `SpaBill.php:58`, `SpaBillingService.php:126` | Contravention Bill |
| `legal_search_online_payments` | 13 | `reference` = `T…` | — | Legal Search (TTC) |
| `phs_onboarding_requests` (with an invoice) | 8 of 22 | `invoice_number` = `PHS-INV-20260614-0001` | PHS admin | **PHS Onboarding / Subscription** (new type) |
| `phs_token_transactions`, type `purchase` | 9 | `reference_no` | PHS token top-up | **PHS Token Top-up** (new type) |

> **Note:** a Deeds Bill Balance exists twice today: the metadata row has the ref, the `billing` row has the amounts. They consolidate to **one** Bill Master row. `billing.ref_id` holds file numbers that other code reads, so it is **not** overwritten; `billing` gets a new `bill_ref_id` column.

> **Note:** PHS `search_debit` (28) and `bonus` (2) transactions are spending from the wallet, not bills, so they stay out.

### 2.3 Legacy bills (→ `bill_legacy`)

| Source | Rows | Types |
|---|---|---|
| `file_indexing_bills` (+ its `billing` row) | 14,613 | `bill-balance` 8,673 · `grant-rent` 5,940 |

They have a receipt number and year range but no bill ref, so each gets an `LGB-…` ref. These are **history**: they keep the receipt captured at indexing. The "unpaid" rule (decision 6) is about the module bills, not these. *(Confirm.)*

### 2.4 `final_bills`: clarification (your question 5)
- **What it is:** the **ST Final Bill**, generated by `FinalBillController` from the ST application actions ("Final Bill" on a primary or unit application). It is the last bill before the certificate.
- **What it charges:** processing fee, survey fee, assignment fee, bill balance, recertification fee and development charges, plus a total. It has its own Bill Balance receipt fields.
- **What's in it:** **1 row**, dated 2025-11-13, for unit application 2061. That application no longer exists, so it looks like a **test bill**.
- **Decided 2026-10-19:** this is the **Bill Balance generated for PuA** (unit applications), entered from Programmes → Bills → "Final Bill Balance". It maps to the **Bill Balance** type, so no new type is needed. When the Bill Balance generator moves to `BillRef` (phase E), the Final Bill form moves with it.
- **The one existing row** points at unit application 2061, which doesn't exist (unit ids run 1–87). It is listed in the consolidation preview CSV, flagged **orphan**, and is **not loaded** unless you say so.

### 2.5 Bill settings that already exist in Configurable Entries

| Tab | What it is | Plan |
|---|---|---|
| **SPAS Bill Items** | `spa_bill_items` via `SpaBillItemTariff` (working) | First sub-tab under Bill Settings, moved as-is |
| **Bill Formulas** | `billing_types` + `billing_type_items` formula engine (empty) | Folded into Bill Settings as the engine for types with no module table |
| **Revenue Items** | Rates that formula items can point at | Stays; feeds the folded engine |
| **Land Rates (G.Rent & LUC)** | Hidden; tables / model / config were never created | LuC is on hold; untouched |
| **Cadastral** | Cadastral fee sheet (hidden) | Later: becomes the Fees, Plan & Description sub-tab |

---

## 3. How the process works

### 3.1 Bill generation: under Bill Master (e.g. Bill Balance)
1. The user opens a bill type under **Automated Billing → Bill Master** (e.g. **Bill Balance**).
2. They select the **file number** with the global file-number selector, never typed. The page backfills owner, Plot No., District, LGA, Location.
3. The system shows the bills that already exist for that file and type.
4. The user **generates the bill**:
   - Bill Master issues the **Bill Ref ID**.
   - The line items come from that type's Bill Settings.
   - The bill is saved as **unpaid**.
5. The user can print a **POS-format invoice**. This is **not** the official receipt.

**Row action menu** (three dots), opening in a **modal**:
- **View**: the bill, its items and payments
- **View application**: the source application/file record
- **Generate bill**
- **Print invoice**: the POS-format invoice

### 3.2 Payment: at the bill stage
On bill types where payment is expected (e.g. Bill Balance), the bill offers three payment methods:

| Method | State | How it settles |
|---|---|---|
| **Paystack** | Live | Online, using the existing Paystack keys and callback (as PHS and instruments do). Confirmed by Paystack's callback; the gateway reference becomes the Transaction ID. |
| **POS** | Live, record only | Paid at a POS terminal **using the Bill Ref ID**. The officer records the POS Transaction ID. No terminal integration for now. |
| **Bank Transfer** | **Disabled** | Shown in the list but greyed out and not selectable, until confirmation of bank transfers is decided. |

- Each payment is a `bill_payments` row against the Bill Ref ID. The bill moves to **part paid** / **paid**.
- **The invoice never shows payment status:** no PAID stamp and no payment lines (changed 2026-10-19). Payment is shown on the bill view and proven by the official receipt.

### 3.3 Official receipts: under Receipting (kept apart)
1. The user goes to **Receipting → Generate Receipt**.
2. They pick a paid bill and generate the **official Ministry bill/receipt**.
3. It prints in **three copies: Original, Duplicate, Triplicate**.
4. The receipt has its own system-generated **receipt number** (`RCT-YYYY-######`), stored on the payment and shown on the bill.
5. **e-Receipt** and **Print Receipt** list and reprint the official receipts.

### 3.4 Key design points
- Everything listed under **Bill Master** is a **bill type**, an entry point where bills are generated.
- The **POS invoice** (bill stage) and the **official receipt** (Receipting, three copies) are different documents.
- **Automated Billing (Bill Master)** and **Legacy Billing** stay clearly separate.

---

## 4. Target design

### 4.1 Tables

**`bill_master_types`** (exists):
- Remove `legacy_bill` (legacy has its own table).
- Add the new types: **PHS Onboarding / Subscription** and **PHS Token Top-up**. (ST Final Bill is Bill Balance, so it isn't a new type.)
- Add the columns `settings_tab` (its Bill Settings sub-tab), `accepts_payment` (shows the payment methods) and `payment_methods` (which of Paystack / POS / Bank Transfer).

**`bill_master`** (exists, system bills):
- `bill_ref_id` — `BILL-YYYY-######`
- `bill_type_code`
- file number, payer, Plot No. / District / LGA / Location
- amount, amount paid, years, bill date, due date
- status — **unpaid** / part paid / paid / cancelled
- `transaction_id` + `receipt_no` (latest-payment shortcuts)
- `source_table` / `source_id`
- `legacy_ref` — the module's old ref, kept when renumbering

**`bill_legacy`** (new, same shape):
- `bill_ref_id` — `LGB-YYYY-######`
- type `bill_balance` / `grant_rent`
- `file_indexing_id`, from/to year, the receipt captured at indexing
- source → `file_indexing_bills.id`

**`bill_items`** (new, every bill): bill ref, item name, quantity, rate, amount, sort order. The bill total is the sum of its items.

**`bill_payments`** (new):
- bill ref (system or legacy)
- method — `paystack` / `pos` / `bank_transfer`
- `transaction_id` (gateway reference / POS transaction / bank reference), amount, paid at
- status — pending / successful / failed / rejected
- validated by / at
- official receipt no. + date (filled under Receipting)

**`bill_receipts`** (new, Receipting): receipt no., bill ref, payment, issued by / at, copies printed (Original / Duplicate / Triplicate), reprint count.

> **Note on status names:** the listings built so far say "Pending". Under decision 6 this becomes **"Unpaid"**.

### 4.2 Bill Ref ID issuer
- One service, `App\Services\Billing\BillRef`:
  - `issue('system')` → `BILL-YYYY-######`
  - `issue('legacy')` → `LGB-YYYY-######`
  - A unique index catches races (retry on clash).
- Every module that creates a bill calls Bill Master in the same transaction:
  1. Create the `bill_master` row and its `bill_items`.
  2. Write the ref back into the module's own ref column.
- Generators to switch over:
  - DBBL (`BillBalanceController::nextReference`)
  - SPAS-BILL (`SpaBill`, `SpaBillingService`)
  - CBL (`CadastralBillCalculator`)
  - Consent bill computation, ST initial / final bill, OSS bill
  - legal search top-up, PHS onboarding invoice (`PHS-INV-…`), PHS token purchase

### 4.3 Renumbering and loading existing module bills
- One command, `bills:consolidate`, with `--preview` writing a CSV first: source row, old ref, new `BILL-…` ref, amount, items.
- **After your approval**, in short chunks (klas has no snapshot isolation):
  1. Insert into `bill_master` + `bill_items` **as unpaid**.
  2. Overwrite the module ref columns (`deeds_bill_balances_metadata.reference`, `spa_bills.reference_id`, `cadastral_bills.bill_ref`, `phs_onboarding_requests.invoice_number`, …).
  3. Keep the old ref in `bill_master.legacy_ref`.
- The affected rows are exported to CSV before anything is overwritten. The whole-DB `.bak` is the last resort.
- The module tables' own payment fields (e.g. `billing.bill_balance_reciept`, PHS `payment_status`, SPAS payments) are **left as they are** (decision 15). Only the Bill Master row starts unpaid.

### 4.4 Legacy backfill
- `bills:consolidate --legacy --preview`, then approval, then load: `file_indexing_bills` → `bill_legacy` with `LGB-…` refs.
- Every row is loaded, dirty ones included (import rule).
- New indexing bills then write straight to `bill_legacy` at capture.

### 4.5 Configurable Entries → Bill Settings
- **One "Bill Settings" tab** replaces "SPAS Bill Items" and "Bill Formulas", with a sub-tab per bill type (the `ce-subtab` style).
- Each sub-tab holds that bill type's **settings** and its **bill items**:
  - settings: on/off, accepts payment, payment methods, due days, invoice note
  - items: name, amount or rate, on/off, order
- **Each module keeps its own items table** behind its sub-tab (`spa_bill_items`, the cadastral schedule, …).
- **Types with no module table** use the folded **Bill Formulas engine** (`billing_types` + `billing_type_items`, with formulas over Revenue Items).

| Sub-tab | Items come from | When |
|---|---|---|
| Contravention (SPAS) | `spa_bill_items` (moved as-is) | First |
| Bill Balance | Bill Formulas engine | With Bill Balance |
| Consent Bill | Consent fee items (valuation-linked) via the engine | Later |
| Initial Bill (Primary / PuA / SuA) | ST initial fee items via the engine | Later |
| Fees, Plan & Description | Cadastral fee sheet | Later |
| Betterment, 5% Deed, OSS, Contravention, Legal Search, PHS | the engine | Later |
| Land Use Charge | — | **On hold** |

---

## 5. Phases and order

| Phase | Work | Data change? |
|---|---|---|
| **A** ✅ | Tables: remove `legacy_bill`; add types + columns; create `bill_legacy`, `bill_items`, `bill_payments`, `bill_receipts`; `BillRef` issuer; rename the "Pending" status to "Unpaid". **Done 2026-10-19:** migration `2026_10_19_090000_revm_bill_master_phase_a`; `App\Services\Billing\BillRef`; models `BillLegacy`, `BillItem`, `BillPayment`, `BillReceipt`; `config/billing.php` (payment methods, statuses, legacy types); Bill Legacy page at `/revenue/legacy` | New tables / columns |
| **B** ✅ | Configurable Entries → Bill Settings tab: SPAS moved as-is; Bill Formulas folded in. **Done 2026-10-19:**<br>• `?tab=bill-settings&sub={type}`: one sub-tab per top-level type, plus "Other formulas"<br>• each sub-tab has a settings card (on/off, accepts payment, methods; Bank Transfer greyed) saving via `configurable-entries.bill-settings.save`<br>• Contravention = the SPAS editor; LuC = on-hold notice; every other type = its Bill Formulas row (code = bill type code), created with "Set up bill items"<br>• old tabs `billing` / `spa-bill-items` added to `HIDDEN_TABS`; their routes still save | Moves screens |
| **C** ✅ | **Done 2026-10-19:**<br>• `App\Services\Billing\BillMasterService` (file details, items from Bill Settings, generate, POS, Paystack, totals)<br>• new routes on `BillMasterController`: lookup / generate / view / invoice / pay / Paystack callback<br>• modals on every bill type page; `public/js/revenue/bill-master.js`; POS invoice `revenue/bills/invoice.blade.php`<br>• added the missing `BillFormulaException` class<br><br>**Planned:** the bill flow, built once for every Bill Master type: select file → show bills → generate (ref + items) → POS invoice → pay (Paystack live, POS record-only, Bank Transfer disabled) → modal action menu (PAID stamp later removed, 2026-10-19) | New bills |
| **D** ✅ | **Done 2026-10-19:**<br>• `App\Services\Billing\BillReceiptService` + `Revenue\ReceiptController`<br>• Awaiting Receipt page (`/revenue/receipts/awaiting`, new sidebar item): one receipt per successful payment<br>• e-Receipt / Print Receipt now list `bill_receipts`<br>• `revenue/receipts/official.blade.php`: A4 Original / Duplicate / Triplicate, later prints marked REPRINT and counted<br>• "Send e-Receipt" greyed (not built)<br><br>**Planned:** Receipting: Generate Receipt (official, `RCT-…`, Original / Duplicate / Triplicate), e-Receipt, Print Receipt | New receipts |
| **E** ✅ | Switch each module's own bill generator to `BillRef` (E.1–E.8, all done 2026-10-19; see the corrections log) | New bills get `BILL-…` |
| E.1 ✅ | **Deeds Bill Balance** (2026-10-19):<br>• reference = Bill Master `BILL-` ref (`generateReference` / DBBL- removed); preview via `BillRef::next`<br>• mirrored by `App\Services\Billing\Modules\DeedsBillBalanceMirror` (lines = the printed bill: rent × years + 5 fees − deposit)<br>• an edit updates the mirror only for bills raised under Bill Master, and never changes the reference<br>• delete → Bill Master row cancelled<br>• an ST file's `ST-BILL-…` ref is kept as `legacy_ref`, so `final_bills` can share the same bill; a second bill for that ST bill is refused<br>• the 600 old DBBL- rows wait for phase F | New bills only |
| E.2 ✅ | **SPAS Contravention** (2026-10-19):<br>• `SpaBill::nextReference()` → Bill Master `BILL-` ref<br>• mirrored by `SpasBillMirror` from the automatic bill (`SpaBillingService`), the manual bill (`storeBill`), and when a when-needed item is added or removed<br>• master delete → mirror cancelled<br>• SPAS keeps its own payments (`spa_payments`); old SPAS-BILL- refs wait for phase F | New bills only |
| **F** | `bills:consolidate --preview`, then approval, then load the existing module bills as unpaid and renumber | **Yes**: overwrites module refs (CSV first) |
| **G** | `bills:consolidate --legacy --preview`, then approval, then load `bill_legacy` | Inserts only |
| **H** | REV-M menu rework around the two tables | UI |
| **I** ✅ | Bill and receipt print design (one layout for every type). **Receipt done 2026-10-20** to the Ministry sample (see the corrections log). Earlier (2026-10-19) this was on hold, waiting for the sample. Until then the current layouts stay as provisional: the 80 mm POS invoice (`revenue/bills/invoice.blade.php`) and the A4 three-copy official receipt (`revenue/receipts/official.blade.php`: Original / Duplicate / Triplicate). When the sample arrives, only these two views change. | Print |
| — | LuC | **On hold** |

---

## 6. Open questions

*None open.* (`final_bills` = Bill Balance for PuA; Paystack reuses the existing setup, both 2026-10-19.)

Resolved 2026-10-19 (now decisions 15–20): the build is generic rather than Bill Balance first; module payment fields stay; legacy keeps its receipts; one invoice with a PAID stamp; POS is record-only; Bank Transfer is visible but disabled; receipt numbers are generated (`RCT-`); the PHS type names are agreed.

---

## 7. Corrections log

- 2026-10-18 — v1 drafted from the voice note and answers 1–5.
- 2026-10-18 — v2:
  - Section 6 answers: one BILL- sequence; line items yes; keep module tables; fold Bill Formulas; legal search and PHS portal are bills; keep the latest-payment shortcuts.
  - LuC put on hold.
  - Existing module bills load as unpaid.
  - Process flow added (generation → POS invoice → payment methods → official receipt in three copies), with the modal action menu.
  - `final_bills` explained.
  - Consent (77) and PHS (8 + 9) added to the inventory.
  - First build moved from LuC to Bill Balance.
- 2026-10-19 — v3:
  - Bill Balance was only the example, so the flow is built once for every type.
  - Module payment fields stay as they are.
  - Legacy bills keep their receipts.
  - One invoice layout, with no PAID stamp (removed 2026-10-19).
  - Payment methods: Paystack live, POS record-only, Bank Transfer visible but disabled.
  - Receipt numbers system-generated as `RCT-YYYY-######`.
  - PHS type names agreed.
  - Later the same day: `final_bills` = the Bill Balance generated for PuA (Bill Balance type; the orphan test row is flagged, not loaded). Paystack reuse confirmed. No open questions remain.
- 2026-10-19 — Phase E.2 (SPAS) built. Tested in a rolled-back transaction: the auto bill is BILL-2026-000001 on both sides (2 lines, N57,500, plot backfilled); a re-save doesn't re-bill; adding or removing the penalty keeps the mirror in step; a manual bill is mirrored; an old SPAS-BILL- bill is not mirrored; cancel works.
- 2026-10-19 — **Phase H** (REV-M menu) built.
  - The Bill Master menu follows the sheet's order and every item opens its REV-M page: Bill Balance and OSS now go to REV-M, not their module pages; PHS Onboarding and PHS Token Top-up added.
  - New **All Bills** page (`revenue.bills.all`): the whole register, type and status filters, view / pay / print.
  - Type pages raised in a module have a **"Raise in …"** button (Deeds Bill Balance, One-Stop Shop, SPAS Bills, Consent Applications, Cadastral P&D Bills, ST Programmes).
  - **Bill Settings** link for System Settings / Super Admin.
  - `docs/SIDEBAR_MENU.md` updated.
  - Tested: menu order; All Bills and its filters; raise buttons; a Bill-Balance-only user sees only Bill Balance.
- 2026-10-19 — **Phase G LOADED** (`bills:legacy --load --force`, 03:52, 2.5 min, 0 errors). User approved loading the amounts as captured.
  - 14,613 bills from 8,952 files → `bill_legacy` LGB-2026-000001…014613, each with one line item: bill balance 6,552 paid / 2,121 unpaid; grant rent 4,992 paid / 948 unpaid.
  - Results: `storage/app/revm/legacy_load_20261019_035224.csv` (also in Downloads).
  - Only new rows were added.
  - **Hook on:** `FileIndexingBillService::syncForFile()` → `LegacyIndexingBillSync::afterIndexingSave()`. A re-save keeps the LGB- refs (bills matched by type + years + receipt); a removed bill is cancelled.
  - Checks: no duplicate refs; every indexing bill linked; a re-save in a rolled-back transaction kept the refs; the Bill Legacy page lists them.
  - **Data note:** 83 bills of N10m or more (N12.7bn) look like entry errors (e.g. LGB-2026-001324, N5.73bn); 58 have no indexing record; 357 have no amount. Fix at the indexing record and Bill Legacy follows.
- 2026-10-19 — **Phase F LOADED** (`bills:consolidate --load --force`, 02:34, 39 s, 0 errors). User decisions: receipted Deeds bills load paid; negative ones load as is; renumbering OK.
  - Backup first: `storage/app/revm/backup_20261019_023359/` (deeds metadata, spa_bills, cadastral_bills, OSS billing rows; JSON + CSV).
  - Results: `storage/app/revm/consolidation_load_20261019_023436.csv` (also in Downloads).
  - 816 bills (BILL-2026-000001…000816), 3,250 items, 571 payments.
  - Status: bill balance 361 paid / 239 unpaid; consent 77 unpaid; SPAS 2 paid / 8 unpaid; P&D 2 unpaid; ST initial 97 paid; OSS 2 unpaid; portal 28 paid.
  - Module refs renumbered: Deeds 600, SPAS 10, Cadastral 2, OSS 2; old refs kept in legacy_ref.
  - Checks: no duplicate refs; items = amount on every bill; REV-M pages list them.
  - 571 payments now wait in Awaiting Receipt for official RCT- receipts.
  - Undo if ever needed: restore the module refs from the backup JSON, then remove the bill_master / bill_items / bill_payments rows whose created_by = 'consolidation'.
- 2026-10-19 — Phase F **preview** built and run: `php artisan bills:consolidate --preview`. Read-only: each record runs through its real mirror in rolled-back chunks of 25; the plain command refuses.
  - CSV: `storage/app/revm/consolidation_preview_20261019_022443.csv` (also copied to Downloads).
  - 817 records, 816 to load: 600 Deeds, 10 SPAS, 2 P&D, 77 Consent, 97 ST initial (paid), 2 OSS, 28 portal (paid). 1 skipped (the orphan final bill).
  - Refs: BILL-2026-000001 … 000816.
  - **Decisions needed before the load:**
    1. 369 Deeds bills already carry a module receipt but would load **unpaid** (as agreed earlier). Confirm?
    2. 8 Deeds bills come out **negative** (deposit larger than the charges, N-138,451 total). Skip, load as N0 paid, or load as is?
    3. Module refs overwritten: Deeds 600, SPAS 10, Cadastral 2, OSS 2. Consent, ST and portal keep their own refs.
- 2026-10-19 — Phase E.8 **Legal Search + PHS portal** built. **Phase E is complete.**
  - **Decided:** mirror as already paid; the portals are unchanged.
  - `PortalBillMirror` is called from the `saved` hooks on `LegalSearchOnlinePayment` (status → paid; amount in kobo ÷ 100), `PhsTokenTransaction` (purchase/top-up → completed; debits ignored) and `PhsOnboardingRequest` (payment_status → completed).
  - Each is copied once with its payment (Paystack / bank ref as the Transaction ID), then waits in Awaiting Receipt.
  - Tested in a rolled-back transaction: pending not mirrored; paid → BILL- Paid; a re-save doesn't duplicate; a search debit is ignored; onboarding mirrored.
- 2026-10-19 — Phase E.7 **OSS Bill** built.
  - `LandsOneStopShop\ApplicationController::bill()` (the routed one; the copy in `App\Http\Controllers\ApplicationController` is not routed) uses a BILL- ref instead of OSS-1- and mirrors via `OssBillMirror`: 4 lines, unpaid in REV-M.
  - The module's own Payment_Status ('paid') is left as is, so the OSS workflow is not held up. The OSS screens take no payment.
  - Tested in a rolled-back transaction: BILL-2026-000001 on both sides, N40,000, payer and file backfilled.
- 2026-10-19 — Phase E.6 **ST Final Bill** (Bill Balance for ST) built.
  - `StFinalBillMirror::syncFor()` is called from `FinalBillController` / `SubFinalBillController::saveBill()`. Lines are the final bill's fees.
  - A unit bill keeps its shared ST-BILL-{sub}-{Ymd} ref as legacy_ref, so a final bill and a Deeds bill balance for the same ST file are ONE Bill Master bill, whichever comes first.
  - The orphan test row is not billed.
  - Programmes → Payments' bill-balance receipt is refused for a REV-M final bill.
  - Tested in a rolled-back transaction: both orders give one bill; an update keeps one bill; primary mirrored; orphan skipped; receipt refused.
- 2026-10-19 — Phase E.5 **ST Initial Bill** built. **Decided:** mirror as already paid; the ST forms are unchanged.
  - `StInitialBillMirror::forPrimary()` / `forUnit()` are called after the four inserts: `PrimaryForm` (Livewire), `PrimaryApplicationController`, `SecondaryFormController` (PuA), `SUAController` (SuA).
  - One line per fee (the text amounts like "10,000.00" are parsed); each captured receipt becomes a successful payment, method `captured` (config `billing.recorded_methods`, never selectable), with receipt no. as the Transaction ID.
  - These payments then wait in Awaiting Receipt for the official RCT-.
  - Never throws into the application save.
  - Tested in a rolled-back transaction on real rows: primary, PuA and SuA all Paid with their receipts; a re-save doesn't duplicate.
- 2026-10-19 — Phase E.4 **Consent Bill** built.
  - The bill is columns on `consent_applications`, so it is mirrored from `ConsentApplication::booted()` (saved) by `ConsentBillMirror`: created when an application's bill is first computed, updated when recomputed; delete → cancelled.
  - Lines: stamp duty only when no transaction type, otherwise registration + processing, so the total always equals `bill_total`.
  - Consent takes no payment. The 77 applications already billed wait for phase F.
  - Tested in a rolled-back transaction: first bill → BILL-2026-000001 (N80,000, 3 lines); recompute under a transaction type → N50,000, 2 lines, same ref; an old billed application is not mirrored; an unrelated save leaves the mirror alone.
- 2026-10-19 — Phase E.3 **Cadastral P&D** built. **Decided:** P&D is its own REV-M bill (Fees, Plan & Description).
  - `CadastralBillCalculator::issue()` uses a `BILL-` ref (CBL- removed) and mirrors via `CadastralPndMirror`; a superseded bill's mirror is cancelled.
  - The Deeds Bill Balance survey-fee lookup no longer pulls in a REV-M P&D bill (it shows a note instead).
  - Cadastral takes no payment, so there was nothing to remove. The 2 old CBL- bills wait for phase F.
  - Tested in a rolled-back transaction: issue → BILL-2026-000001 on both sides (N20,500, payer and plot backfilled); re-issue cancels the earlier mirror; the Bill Balance lookup returns the note.
- 2026-10-19 — **DECIDED (b): payment removed from module screens; the workflow is never blocked by payment.**
  - **SPAS:** "Record Payment" replaced by "Pay in KLAES REV-M"; a SPAS payment is refused on the server; `SpaNewFileService::settled()` counts a REV-M bill as settled, so Send to CoP isn't blocked; the tracker shows the REV-M status.
  - **Deeds Bill Balance:** the receipt fields are hidden and ignored for Bill Master bills.
  - Old SPAS-BILL- / DBBL- bills keep module payment until phase F moves them.
  - Tested in a rolled-back transaction. **Apply the same rule to every remaining module.**
- 2026-10-19 — Phase E.1 (Deeds Bill Balance) built. Tested in a rolled-back transaction: the form previews a BILL- ref; store gives BILL-2026-000001 on both sides; the Bill Master total equals the print's balance due; an edit updates the mirror; an old DBBL record is not mirrored and keeps its ref; the ST shared ref is kept and a second bill for it refused; delete cancels the mirror. Fixed along the way: an edit could overwrite the reference.
- 2026-10-19 — Phase D built. Tested in a rolled-back transaction: 2 POS part payments → Awaiting Receipt lists both (pending Paystack excluded) → RCT-2026-000001/2 issued; a repeat issue and an unpaid payment are refused → both lists show them → e-Receipt (1 copy, amount in words, balance after that payment) → print (3 copies) → reprint marked and counted.
- 2026-10-19 — Phase C built. Tested end to end in a rolled-back transaction, with Paystack faked: set up items → generate (backfilled, BILL- ref, 2 items) → listing + row menu → view → invoice → POS part payment → Paystack balance → PAID stamp. Bank Transfer refused. LuC disabled.
- 2026-10-19 — Phase B built: Bill Settings tab (no schema change). All 13 sub-tabs render; settings save and "Set up bill items" tested in a rolled-back transaction.
- 2026-10-19 — Phase A built and run on production. All five Bill Master tables are empty. Tested in a rolled-back transaction: BILL- / LGB- / RCT- numbering, items, POS payment, receipt, duplicate ref rejected.
- 2026-10-19 — User: "remove paid from the invoice and the step too" (read as: the PAID stamp). The POS invoice no longer shows the PAID stamp, the PAID INVOICE heading, or the payment / paid / outstanding lines. It always prints as a plain INVOICE.
- 2026-10-19 — User: module bill items are already entered on the module form; REV-M only takes the total. Bill types raised in a module or portal (`BillMasterController::MODULE_RAISED`: Bill Balance, OSS, SPAS/Contravention, Consent, Cadastral P&D, Initial Bill, Legal Search, PHS ×2) no longer use Bill Settings items in REV-M. Their Generate modal shows the file's module bills with Total, View and Invoice, plus a note when none has been raised, and has no Generate button. The server refuses to generate them. The "Raise in …" buttons were removed. Only Betterment, 5% Deed of Purchase and LuC (on hold) are generated from Bill Settings items.
- 2026-10-19 — User: "the invoice should be enabled only when the bill has been generated". `bill_master.generated_at` / `generated_by` were added (migration `2026_10_19_160000_bill_master_generated`). A module bill arrives **Not generated**. An officer generates it from the Generate modal, the row menu or the bill view (`POST revenue/bill/{ref}/generate`, Billing create permission), and only then does Print invoice open. The server refuses the invoice before that. Bills generated in REV-M, and bills that are paid or part paid, count as generated automatically. Backfill: 488 paid bills were marked generated; 329 unpaid module bills await generation (Bill Balance 239, Consent 78, SPAS 8, P&D 2, OSS 2). Payment was not gated at first; that changed in the next entry.
- 2026-10-19 — User: "remove the record payment from the action button, it should be on the generate bill, and it should be Pay, once generated it should be enabled; change Unpaid Not generated to Pending". Changes:
  - Record payment is gone from the row menus.
  - Each bill row in the Generate modal now has **Pay**, which is greyed out until the bill is generated. Pay opens the bill's Payments tab, and the payment button there reads Pay.
  - The server refuses payment before generation.
  - An unpaid bill that has not been generated shows as **Pending** (`BillMaster::displayStatus()`). DB status stays `unpaid`.
  - Status filters on system bills: Pending = unpaid and not generated; Unpaid = generated and not paid. Bill Legacy is unchanged.
- 2026-10-19 — User: "after generating the bill card should open automatically for that file". After a successful generate (a module bill's Generate, or a new REV-M bill), the response carries the bill `ref`. The page reloads with `?open={ref}` and opens that bill's View card (Bill · Payments · Application) automatically. The `?open` is then removed from the address bar.
- 2026-10-19 — User: hide All Bills from the sidebar and add back "a. Land Use Charge (LuC)" with "#" for now. All Bills is gone from the menu (its page `/revenue/bills` still works). LuC is now the first Bill Master item, as in the docx, and links to `#`.
- 2026-10-19 — User: hide Bill Settings from the bill sidebar. The REV-M menu link is gone. Bill Settings stays in System Settings → Configurable Entries → Bill Settings tab.
- 2026-10-20 — **Phase I: official receipt sample received** (3 photos, WhatsApp 2026-10-19). `revenue/receipts/official.blade.php` was redone to the Ministry layout:
  - three copies (Original / Duplicate / Triplicate) on one A4 page, cut apart along dashed lines
  - header: "OFFICIAL RECEIPT"; copy name and receipt no. on the right; Kano arms on the left; KANGIS seal on the right; Ministry name and "No. 2, Dr Bala Mohammed Road, P.M.B 3083, Kano Nigeria" in the centre
  - boxed fields: File Number · Bank Name · Date Paid / Payment ID · Teller Number · Receipt Date
  - then Received From · Amount in words · Being Payment For · Amount
  - Customer Signature · Revenue Officer Signature (officer's email) · Duration

  Mapping:
  - Payment ID = Bill Ref ID
  - Teller Number = Transaction ID
  - Bank Name = Paystack / POS / Bank Transfer / Online, or — for bank receipts captured in a module
  - Duration = the bill's from–to years, otherwise the payment year

  The sample's "Begin" and "Recieved" are printed correctly spelled. The sample's own series number (e.g. EXS8-B621 079829) is replaced by our RCT- number. The POS invoice is unchanged.
- 2026-10-20 — **Updated sidebar sheet** (image): REV-M → a. Dashboard (# for now), b. Billing → i. Automated Billing → a flat list. Bill Master is now an item (the whole register, the former All Bills page, retitled Bill Master), then LuC (#), Betterment, Initial Bill, Primary, PuA, SuA, OSS, 5% DoP, Consent, P&D, Legal Search, Contravention, Bill Balance. ii. Legacy Billing → Bill Legacy. The two PHS types are not on the sheet; they are kept at the end, pending the user's decision.
- 2026-10-20 — Receipt copies are now tinted like the carbon set (Original green, Duplicate blue, Triplicate pink, printed with exact colours), with `logo1.jpg` as a faint background watermark.
- 2026-10-20 — **REV-M Dashboard built** (`revenue.dashboard`, `/revenue/dashboard`, `Revenue\DashboardController`, `revenue/dashboard.blade.php`; read-only). It shows:
  - headline cards: billed, collected, outstanding, pending generation, collected today / this month, awaiting receipt, receipts issued
  - collections for the last six months
  - this month's payments by method
  - Bill Master by bill type (pending / unpaid / part paid / paid / billed / collected / outstanding)
  - the latest 10 payments
  - a Bill Legacy summary

  Open to anyone with a REV-M role (Billing, Generate Receipt, Bill Balance, LuC, TTC) or Super Admin. Data note: 3 captured ST receipts (₦110,000) carry a paid date of 05 Dec 2026 (in the future), so the latest-payments list is ordered by when each payment was recorded.
- 2026-10-21 — **SMS notifications, payment cycles, reconciliation, on-the-fly bills** (user: "notification should be SMS"; "if the phone number is found in file indexing or anywhere, it should be entered on the bill form during generating bill"). Migration `2026_10_21_090000_revm_cycles_reconciliation` (additive: `bill_master_types.is_recurring / payment_cycle / remind_days_before`; `bill_master.payment_cycle / period_start / period_end / next_due_date / previous_bill_ref / raised_via`; `bill_payments.reconciliation_status / reconciled_at / reconciled_by / reconciliation_note / settlement_ref`).
  - **SMS** (`App\Services\Billing\BillNotifier` → `KlaesSmsDispatcher`): three new messages in the SMS Control Centre, group "Revenue (REV-M)": `bill_generated`, `bill_payment_received`, `bill_payment_due`. **Shipped switched off** (`default_enabled` false). Sent when a bill is generated in REV-M (generate, on-the-fly, module bill generated, next cycle raised), when a Paystack / POS payment is recorded, and before a recurring bill's next payment is due. One per event (dedupe key); the gateway's response is in `sms_dispatch_logs` (subject `bill_master` / Bill Ref ID), shown on the bill card's new Notifications tab, which can also re-send (with a corrected phone). Response channels named in the SMS: [Channels] = the bill type's enabled payment methods (Paystack online / POS at the Ministry); [Contact] = `config('billing.notify.contact')` (env `REVM_SMS_CONTACT`). Amounts are written "N125,000.00" (₦ is not GSM and doubles the SMS cost).
  - **Phone backfill** (`BillPhoneFinder`): File Indexing, earlier bills on the file, then Consent, OSS, Land Recommendation, LAAS, Change of Purpose, Valuation, SPAS, ST file numbers, ST primary / unit applications, Recertification; placeholder numbers skipped. Prefills the generate form, the on-the-fly form and the Generate confirm box (module bills), with "Found in …".
  - **Payment cycles**: Bill Settings → each type → "Recurring bill" (cycle Monthly / Quarterly / Half-yearly / Annual, reminder days). A bill of a recurring type covers one period from its bill date; next payment due = the day after the period ends. `bills:cycle` (daily 07:30, in `SmsConsoleGuard::ALLOWED_COMMANDS`) sends the due SMS within the reminder window and on the due date raises the next cycle's bill (same file / payer / lines, `previous_bill_ref`, `raised_via = cycle`). New page Billing → **Payment Cycles** (`/revenue/cycles`), with "Send due reminder" and "Raise next cycle now". No type is recurring yet.
  - **Reconciliation** (`/revenue/reconciliation`, `BillReconciliationService`): every successful payment is Unreconciled / Reconciled / Disputed. Paystack payments verified on return are reconciled automatically; "Check with Paystack" re-verifies (a Pending one that Paystack confirms settles the bill); POS / captured / module receipts are ticked off by hand (settlement ref + note), singly or with the sticky footer bar. A "Bill totals check" lists bills whose amount paid differs from their payments, with "Recompute from payments" (none at build time). Existing payments start Unreconciled (572, ₦72.98m).
  - **On-the-fly bills**: "On-the-fly bill" button on Bill Master (+ sidebar item On-the-fly Bills): Bill Type (any active concrete type, LuC excluded) + file (global selector, details backfilled) + Revenue Code lines from the Revenue Items register (rate prefilled, editable; quantity × rate), or "Other" with a typed code (digits) and item. Generated at once, `raised_via = on_the_fly`. **The Revenue Items register is empty today**, so until codes are added in Configurable Entries → Revenue Items, lines are typed under "Other".
- 2026-10-21 — **User's answers:** (1) the REV-M SMS stay **switched off** for now; (2) recurring bills: **Ground Rent, Re-grant, etc.**; (3) "the code should be based on the bill type". Migration `2026_10_21_120000_revm_recurring_types_revenue_codes`:
  - New bill types **Ground Rent** (`ground_rent`) and **Re-grant** (`re_grant`), both recurring, **Annual**, SMS reminder 30 days before (changeable in Bill Settings). Each has its own page under Automated Billing (after LuC). Their "Generate bill" opens the on-the-fly form with the type fixed (`BillMasterController::CODE_BILLED`); they don't use Bill Formulas. Ground Rent is a **unit rate** (`config('billing.unit_rate_types')`): the line quantity is the plot size (m²), filled from the file and required (only 41,626 of 180,234 indexed files have a plot size; the officer types it otherwise).
  - **Revenue Codes per bill type**: new table `bill_type_revenue_items` (bill type ↔ `revenue_items`). Bill Settings → each type → new "Revenue Codes" card: pick a code from the Revenue Items register, or add a new one (it goes into the register too); remove takes it off the type only. An on-the-fly bill offers **only the codes of the chosen type** (children such as Primary / PuA / SuA also get their parent's); a type with one code has it picked already; the free-typed "Other" code is gone. A type with no codes cannot be billed on the fly ("Add them in Bill Settings").
  - Still open: other recurring types (LuC once off hold?); the actual Revenue Codes and rates for Ground Rent and Re-grant.
- 2026-10-21 — **On-the-fly Bills hidden from the sidebar** (user); the On-the-fly bill button on Bill Master stays. **Revenue Codes seeded for every bill type** (`database/seeders/RevenueCodesSeeder.php`, run 2026-10-21: 48 codes, 48 links; idempotent). No Kano Revenue Sub-Heads sheet exists on the server (ALAES's register is Abia's), so the user chose **provisional codes built from the bill type**: `9 TT II 00` (TT = bill type no., sort order / 10; Ground Rent 17, Re-grant 18; II = charge no.), e.g. Bill Balance Preparation fee = 9020100, Ground Rent Residential = 9170100. They start with 9 so they never clash with the state's 4000xxx codes, and are to be swapped for the Ministry's codes when the sheet arrives. Charges and rates = what KLAES bills today (most common rate on existing bills; `spa_bill_items`; `pp_betterment_rates`; ground rent per m² as used on land recommendations: Residential 8, Commercial 50, Industrial 20, Agricultural 0.50). Rate 0 = varies (entered on the bill). Preview: Downloads/revenue_codes_seed_20261021.csv.
- 2026-10-21 — On-the-fly bill card: a **Revenue Code** box at the top (user). Entering a code (Enter, Find, or on the 7th digit) finds its bill type, selects it and adds the code as a line with its rate; further codes add more lines. A code of another type asks before switching (lines are cleared); a code shared by several types asks which; on the Ground Rent / Re-grant pages only that type's codes are accepted.
- 2026-10-21 — **Correction (user): "the code should be for the bill type, not bill items."** Migration `2026_10_21_150000_revm_revenue_code_per_bill_type`: `bill_master_types.revenue_code` (unique, digits), **one code per bill type**, provisional `9TT0000` (Bill Balance 9020000, Betterment 9030000, Initial Bill 9040000 (Primary / PuA / SuA use it), OSS 9080000, 5% DoP 9090000, Consent 9100000, LuC 9110000, P&D 9120000, Legal Search 9130000, Contravention 9140000, PHS Onboarding 9150000, PHS Tokens 9160000, Ground Rent 9170000, Re-grant 9180000). The 48 per-charge codes seeded earlier were removed (unused by any bill; CSV backup Downloads/revenue_items_seeded_backup_20261021.csv) and the `bill_type_revenue_items` table dropped; `RevenueCodesSeeder` now sets the type codes (idempotent). Bill Settings: a **Revenue Code** field on each type's settings card (the Revenue Codes card is gone). On-the-fly card: enter the **bill type's** Revenue Code → its Bill Type is selected (Initial Bill's code asks Primary / PuA / SuA); the Bill Type list shows each code; the lines are the officer's **items** (item, quantity, rate), each stored with the bill type's code as `bill_items.item_code`. The bill card shows the type's code.
- 2026-10-21 — Configurable Entries → **Revenue Items** now opens with a read-only **Bill Type Revenue Codes** list (S/N, code, bill type, also used by, recurring, status). In **Bill Settings** the Revenue Code is shown **bold red and not editable**; saving the settings never changes it (the codes are set by the migration / `RevenueCodesSeeder`).
