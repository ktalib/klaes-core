# KLAES — Valuation-linked Consent Bill and Consent Letter

Status: implementation handoff and draft template; no application code has been inspected or changed. Based on the supplied recording, two-page FCTA reference letter and valuation screen. Rates below record requested software behaviour, not a determination of statutory tax rates.

## 1. Immediate objective

Connect the existing assignment valuation to Consent, rework the billing step inside the existing Consent workflow, and populate the new consent letter from that saved bill. Keep the change focused on the existing workflow rather than adding a separate sidebar module.

Flow: completed valuation → Consent details → Consent bill → consent letter preview/issue. Preserve existing approval and payment gates after inspecting the code; generating a bill must not itself approve consent or mark payment as received.

The latest screenshot confirms four steps: Basic Info → Application → Parties → Payments. Use the existing fourth step, Payments, for the valuation-linked consent bill. This supersedes the earlier spoken suggestion of step 3; do not replace the Parties step.

## 2. Decisions and conflicts

| Item | Implementation instruction | Confirmation needed |
|---|---|---|
| Value source | Valuation Section E, item 21 A: numeric opinion/recommendation amount | Identify actual database field and eligible valuation status in code |
| CGT | Latest explicit instruction: 3% of the valuation amount | Earlier speech mentioned 5% and 10%; confirm before live billing |
| Stamp duty | Latest explicit instruction: 5% of the valuation amount | Initial message said 3%; intermediate speech said 10%; confirm before live billing |
| Fixed fees | Registration, processing and consent/assignment fee | Confirm exact amounts and whether the third fee is named Consent or Assignment |
| ₦30,000 | Mentioned near the fixed fees | Unclear whether per fee, one specific fee, or combined; do not silently apply to all three |
| Continuation page | User explicitly says second page is not A4 | Obtain actual dimensions/orientation; do not infer paper size from the photograph |
| Letterhead | Use local state ministry/department/address and approved crest; use KLAES branding where requested | Exact ministry text, address, assets and authorised signatory |

Use editable configuration for rates and fixed fees, with draft values clearly marked provisional. Permit development and draft preview while unresolved; do not issue live financial demands using guessed values. Do not create both Consent and Assignment charges unless they are confirmed as distinct fees.

## 3. Valuation connection

- Locate the saved numeric amount behind Section E → Opinion/Recommendation → A) ₦. Amount in words is display content, not the calculation source.
- Link through the existing application/property/transaction identifiers. Do not rely on applicant name or ambiguous file-number text alone.
- Display valuation reference, date, property/file reference, status and amount in Consent. When more than one eligible valuation exists, require a deliberate selection and show enough detail to distinguish them.
- Use the completed/approved valuation according to the existing workflow. Missing, zero or invalid valuation must show a clear message and prevent automatic bill generation; never silently use zero.
- Preserve the valuation amount. A consent fee override must not alter the valuation record.
- On bill creation, save the valuation identifier/version and amount used. Later valuation edits must not silently change an issued bill or printed letter. Flag a changed source and use the existing revision/cancellation process.

## 4. Consent card / wizard layout

Retain the four steps shown in the supplied Create Application card:

1. **Basic Info:** retain existing file selection and metadata.
2. **Application:** retain application/property details and add recipient name and multiline correspondence address where appropriate. Prefill existing data without silently overwriting the applicant profile.
3. **Parties:** retain assignor/assignee capture and validation.
4. **Payments:** expand the current consideration-only layout into the consent bill. Show the linked valuation reference and read-only Section E amount, then CGT, stamp duty and the three configured fixed fees, followed by the total and bill/payment references. Preserve application date and tracking metadata.

Keep declared consideration distinct from assessed valuation: do not overwrite one with the other. The requested fee basis is the linked valuation amount. Add preview/print actions for the saved bill and consent letter within the existing workflow, without inventing a fifth step. Letter issue remains subject to existing approval controls.

Step 4 field design:

| Row | Default display | Interaction |
|---|---|---|
| Valuation amount | Read-only currency value with source reference | View valuation |
| CGT | Rate, base and calculated amount; muted read-only amount | Radio: Automatic / Manual amount |
| Stamp duty | Rate, base and calculated amount; muted read-only amount | Radio: Automatic / Manual amount |
| Registration fee | Configured fixed fee | Use existing fee-configuration permissions |
| Processing fee | Configured fixed fee | Use existing fee-configuration permissions |
| Consent or Assignment fee | Configured fixed fee and confirmed label | Use existing fee-configuration permissions |
| Total | Sum of effective line amounts | Read-only |

Each percentage row has its own radio control. Selecting Manual amount enables that row's amount input and a required reason. Preserve calculated amount, effective amount, source mode, actor and timestamp. Selecting Automatic restores the calculation and clears the active override while retaining audit history. Enforce permissions and calculations on the server; disabled browser fields are not authoritative. Do not submit a manual amount as though it were automatically calculated.

## 5. Calculation example

Provisional last-spoken rate interpretation:

| Charge | Formula for valuation of ₦5,000,000 | Amount |
|---|---|---:|
| CGT | ₦5,000,000 × 3% | ₦150,000.00 |
| Stamp duty | ₦5,000,000 × 5% | ₦250,000.00 |
| Percentage subtotal | CGT + stamp duty | ₦400,000.00 |
| Fixed fees | Registration + processing + consent/assignment | Pending configuration |
| Total | ₦400,000 + confirmed fixed fees | Pending configuration |

If all three fixed fees are confirmed at ₦30,000 each, the total would be ₦490,000. If ₦30,000 is their combined total, it would be ₦430,000. These are alternative examples, not selected defaults. The initial 5%/3% split has the same subtotal but different ledger allocations, so the labels still require confirmation.

Use decimal currency or integer kobo. Round each line to two decimal places using the existing financial rounding convention, then sum rounded effective amounts. Recompute server-side and reject negative or malformed amounts. Preserve both rates and amounts on the bill snapshot.

## 6. Billing and letter backfill

- Reuse existing revenue billing, bill numbering, payment references and receipt reconciliation. Inspect those integrations before adding any new tables or services.
- Saving a bill must be transactional and idempotent: repeated clicks/retries must not produce duplicate active bills.
- All consent-letter financial rows and totals come from the saved bill snapshot. Do not independently recalculate in the print template or require staff to type the same values again.
- The letter must reference its bill and valuation. Preserve the issued letter's bill version so reprints remain consistent.
- Show total billed, verified payments and outstanding balance separately. Draft preview must not claim payment has been made.
- Keep payment instructions for unpaid bills. For part payment show the balance. For fully paid bills show verified receipt references/payment acknowledgement rather than demanding the full sum again.
- Keep conditional approval distinct from final completion of registration. Do not bypass existing approvals simply because a payment has been received.

## 7. New letter template — draft content and bindings

Use the reference's structure, not its FCTA branding, named recipient, monetary values, address, bank/payment destination or signature. Use only approved local assets and configured ministry details. The text below is a layout/content draft requiring the ministry's approved wording before issue.

### Page 1 — letterhead and consent notice

**[APPROVED COAT OF ARMS / CREST]**  
**[STATE / MINISTRY NAME]**  
**[DEPARTMENT NAME]**  
[MINISTRY ADDRESS]

Our reference: {{letter_reference}}  
File number: {{file_number}}  
Date: {{issue_date}}

**{{recipient_name}}**  
{{recipient_address}}

**CONVEYANCE OF APPROVAL FOR CONSENT TO ASSIGN PLOT NO. {{plot_number}}, {{property_location}}, COVERED BY FILE NO. {{file_number}}, TO {{assignee_name}}**

Include cadastral zone, district and Certificate of Occupancy number in the subject when present and applicable; omit missing optional fragments cleanly.

I am directed to refer to your application concerning the above property and convey the approval of {{approving_authority}} for the assignment in favour of **{{assignee_name}}**, subject to the conditions stated in this letter.

2. The following charges apply under bill **{{bill_reference}}**:

| Description | Amount |
|---|---:|
| Capital Gains Tax | {{bill.cgt_amount}} |
| Stamp Duty | {{bill.stamp_duty_amount}} |
| Registration Fee | {{bill.registration_amount}} |
| {{bill.consent_or_assignment_label}} | {{bill.consent_or_assignment_amount}} |
| Processing Fee | {{bill.processing_amount}} |
| **Total assessed** | **{{bill.total}}** |

Where payments exist, additionally show verified amount paid and outstanding balance. The statement must reconcile with the bill and receipts.

3. {{approved_conditions_for_payment_and_submission_of_deed}}

4. {{approved_deadline_and_default_clause_if_applicable}}

### Continuation page — payment guidance and sign-off

Repeat a compact file number, bill reference and page number for identification. Use the confirmed paper dimensions, not an assumed A4 continuation sheet.

5. For an unpaid or part-paid bill: Please pay the outstanding amount of **{{bill.outstanding_balance}}** using **{{configured_payment_instructions}}** and quote **{{bill.payment_reference}}**. {{approved_receipt_submission_instruction}}

For a fully paid bill, replace the demand with: Payment of **{{verified_paid_amount}}** is recorded under receipt reference(s) **{{receipt_references}}**. {{remaining_document_submission_instruction}}

6. {{approved_counterpart_copy_clause_if_applicable}}

{{authorised_signature_block}}  
**{{signatory_name}}**  
{{signatory_designation}}  
For: {{approving_authority}}

Do not automatically adopt the sample's 21-day deadline, ₦20,000 daily penalty, ₦2,000 counterpart charge or Remita/FCTA payment route. They are reference content, not confirmed local requirements. If counterpart fees are enabled, bill them explicitly so the letter and bill totals stay aligned.

Print requirements: legible ministry heading, consistent spacing, wrapped long subject lines and addresses, right-aligned currency, protected fee table and signature blocks, deliberate pagination, no clipped text. Preview with long names/addresses. Configure paper size per page; confirm mixed-size PDF/printing support in the existing renderer before release. Do not insert a signature image without an authorised source.

## 8. Implementation sequence for the coding agent

1. Inspect existing Consent wizard, valuation model/controller, bill services, payment status handling and consent print view. Report actual file paths and field mapping before choosing schema changes.
2. Add or reuse the valuation relationship and recipient correspondence fields. Use additive migrations only where existing schema cannot hold required data. Preserve existing file numbers and records.
3. Implement one server-side bill calculation path, configuration and per-line audited manual overrides.
4. Rework the existing billing step and attach the generated bill to Consent. Reuse numbering, permissions and payment integrations.
5. Build the local ministry template and backfill from the bill snapshot. Preserve legacy letters and historical bill values.
6. Verify calculations, record linkage, permissions, duplicate prevention and rendering on a representative assignment.
7. Produce a draft sample for review, then apply confirmed fee configuration, wording and paper dimensions before live issue. Use the normal deployment/rollback process; do not promise production readiness before inspection and validation.

Proposed logical data to persist (names are not assertions about the current schema): consent/application ID, valuation ID/version/base amount, recipient address snapshot, bill ID/version, fee code/label/rate/calculated/effective amount/source mode, override reason/actor/time, configuration version, issue date and issued document reference. Reuse current equivalents wherever possible.

## 9. Acceptance checks

- A ₦5,000,000 valuation gives provisional CGT ₦150,000 and stamp duty ₦250,000, with configured fixed fees added exactly once.
- A different property's valuation cannot be linked by manipulating request IDs.
- Missing or unfinished valuation is handled according to eligibility rules, with no silent zero bill.
- Manual override affects only its selected charge; returning to Automatic restores the formula. Unauthorised override requests fail server-side.
- Repeated save requests create one active bill; server rejects client-tampered totals.
- Updating a valuation/configuration leaves issued bills and historical reprints unchanged; drafts show when refresh is needed.
- Consent letter amounts match the linked saved bill exactly, including overrides and total.
- Unpaid, part-paid and fully paid letters show correct instructions without implying unverified payments.
- Long names/addresses and the confirmed continuation paper size render without clipped content or orphaned signatures.
- Existing Consent records remain usable, and existing file numbers and approval gates are preserved.

## 10. Inputs needed to finish implementation

Repository or accessible KLAES development folder; confirmed CGT/stamp-duty split; each fixed fee and final Consent/Assignment label; continuation-page size; local letterhead/signatory assets and approved conditions. Code work can proceed on independent integration and layout portions while these configuration details are resolved.
