<?php

namespace App\Services\InstrumentWorkflow;

use App\Models\CustomerRecord;
use App\Models\InstrumentWorkflow\BirSubmission;
use App\Models\InstrumentWorkflow\InstrumentApplication;
use App\Models\InstrumentWorkflow\InstrumentApplicationCheck;
use App\Models\InstrumentWorkflow\InstrumentApplicationEvent;
use App\Models\InstrumentWorkflow\InstrumentInvoice;
use App\Models\InstrumentWorkflow\InstrumentPayment;
use App\Models\InstrumentWorkflow\TaxIdentityCheck;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only writer of instrument_applications.stage.
 *
 * Every move runs in one sqlsrv transaction with the application row locked,
 * checks the TRANSITIONS map, re-checks the business guard for the target stage
 * against the database, and appends an event row. A refused move throws
 * WorkflowGuardException with a message fit for the officer.
 */
class InstrumentWorkflowService
{
    public function __construct(private InstrumentFileResolver $files)
    {
    }

    // =====================================================================
    // 1. Application and application fee (KANGIS payment process)
    // =====================================================================

    /**
     * The applicant submits to the Lands Department: the application is raised
     * against a File Indexing record and a customer, and the application fee
     * bill is generated in the same transaction.
     */
    public function create(array $data): InstrumentApplication
    {
        $record = $this->files->find((int) $data['file_indexing_id']);
        if (!$record) {
            throw WorkflowGuardException::because('The selected file was not found in File Indexing.');
        }

        return $this->db()->transaction(function () use ($data, $record) {
            $customer = $this->resolveCustomer($data, $record);

            // A provisional file is opened before the applicant's name is known, so
            // give it the name once there is one - it is what officers search on
            // until the permanent number is issued at commissioning.
            if (TempFileNumberAllocator::isTemporary($record->file_number)
                && in_array(trim((string) $record->file_title), ['', 'Awaiting file number'], true)) {
                $record->forceFill(['file_title' => $customer->full_name])->save();
            }

            $application = new InstrumentApplication();
            $application->fill([
                'reference' => InstrumentApplication::nextReference(),
                'customer_record_id' => $customer->id,
                'file_indexing_id' => $record->id,
                'file_number' => $record->file_number ?: ($record->legacy_file_number ?: (string) ($data['typed_file_number'] ?? '')),
                'instrument_type' => $data['instrument_type'] ?? null,
                'consideration_amount' => 0,
                'applicant_type' => $customer->customer_type,
                'applicant_name' => $customer->full_name,
                'phone' => $data['phone'] ?? $customer->phone,
                'email' => $data['email'] ?? $customer->email,
                'address' => $data['address'] ?? $customer->full_address,
                'identity_type' => $customer->customer_type === CustomerRecord::TYPE_CORPORATE ? 'RC' : 'NIN',
                'rc_number' => $data['rc_number'] ?? $customer->rc_number,
                'is_demo' => config('instrument_workflow.tin.driver') === 'demo',
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $application->setNin($data['nin'] ?? null);
            $application->stage = InstrumentApplication::STAGE_FEE_PENDING;
            $application->stage_changed_at = now();
            $application->save();

            $this->record($application, 'created', null, InstrumentApplication::STAGE_FEE_PENDING, 'Application submitted to the Lands Department.', [
                'file_indexing_id' => $record->id,
                'customer_record_id' => $customer->id,
                'typed_file_number' => $data['typed_file_number'] ?? null,
            ]);

            $bill = app(InstrumentBillingService::class)->issueApplicationFeeBill($application);
            $this->record($application, 'bill_generated', $application->stage, $application->stage, "Application fee bill {$bill->reference} generated (₦" . number_format((float) $bill->total, 2) . ').', ['invoice_id' => $bill->id]);

            return $application;
        });
    }

    public function markFeePaid(InstrumentApplication $application, InstrumentPayment $payment): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_FEE_PAID, [
            'event' => 'payment_captured',
            'note' => "Payment captured from deposit slip: {$payment->channelLabel()}, Transaction ID {$payment->transaction_id}.",
            'meta' => ['payment_id' => $payment->id],
        ]);
    }

    public function markFeeRejected(InstrumentApplication $application, InstrumentPayment $payment): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_FEE_PENDING, [
            'event' => 'payment_rejected',
            'note' => "Accounts rejected Transaction ID {$payment->transaction_id}: {$payment->validation_note}",
            'meta' => ['payment_id' => $payment->id],
        ]);
    }

    public function markFeeValidated(InstrumentApplication $application, InstrumentPayment $payment): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_FEE_VALIDATED, [
            'event' => 'payment_validated',
            'note' => "Payment validated by KANGIS Accounts; receipt {$payment->receipt_number} generated.",
            'meta' => ['payment_id' => $payment->id],
        ]);
    }

    /**
     * Counter-signed receipt scanned into DMS and issued: the application goes to the
     * first check that is on (Configurable Entries), or straight to the LIC when all are off.
     */
    public function markReceiptIssued(InstrumentApplication $application, InstrumentPayment $payment): InstrumentApplication
    {
        $note = "Signed receipt {$payment->receipt_number} scanned and issued. Blank Land Information Certificate form raised.";
        $meta = ['payment_id' => $payment->id, 'document_id' => $payment->receipt_scan_document_id];

        $result = $this->db()->transaction(function () use ($application, $note, $meta) {
            $locked = $this->lock($application);
            $this->expectStage($locked, InstrumentApplication::STAGE_FEE_VALIDATED);

            $next = $this->pipeline()->nextStage($locked);
            if ($next === InstrumentApplication::STAGE_LIC_ISSUED) {
                $this->record($locked, 'receipt_issued', $locked->stage, $locked->stage, $note . ' Every ministry check is turned off.', $meta);

                return $this->issueLicAutomatically($locked);
            }

            $type = $this->pipeline()->typeForStage($next);

            return $this->transition($locked, $next, [
                'event' => 'receipt_issued',
                'note' => $note . ' Sent to ' . $this->pipeline()->department($type) . '.',
                'meta' => $meta,
            ]);
        });

        $application->setRawAttributes($result->getAttributes(), true);

        return $result;
    }

    // =====================================================================
    // 2. Ministry checks and LIC
    // =====================================================================

    public function recordCheck(InstrumentApplication $application, string $outcome, ?string $notes, array $details = []): InstrumentApplication
    {
        $step = InstrumentApplication::CHECK_STAGES[$application->stage] ?? null;
        if (!$step) {
            throw WorkflowGuardException::because('No ministry check is due at "' . $application->stageLabel() . '".');
        }

        if ($outcome === InstrumentApplicationCheck::OUTCOME_FAILED && trim((string) $notes) === '') {
            throw WorkflowGuardException::because('A failed check must state the reason.');
        }

        if ($outcome === InstrumentApplicationCheck::OUTCOME_FAILED) {
            return $this->transition($application, InstrumentApplication::STAGE_CHECK_FAILED, [
                'event' => 'check_recorded',
                'note' => $notes,
                'meta' => ['check_type' => $step['type'], 'outcome' => $outcome],
                'columns' => ['failure_reason' => $notes],
                'before' => fn (InstrumentApplication $locked) => $this->expectStage($locked, $application->stage),
                'after' => fn (InstrumentApplication $locked) => $this->storeCheck($locked, $step['type'], $outcome, $notes, null, $details),
            ]);
        }

        // Passed: store it, then move to the next check that is on and not yet passed,
        // in the configured order. When none is left the Land Information Certificate
        // is generated in the same transaction: nobody types an LIC number.
        $result = $this->db()->transaction(function () use ($application, $step, $outcome, $notes, $details) {
            $locked = $this->lock($application);
            $this->expectStage($locked, $application->stage);
            $this->storeCheck($locked, $step['type'], $outcome, $notes, null, $details);

            return $this->advanceAfterCheck($locked, $step['type'], $notes, $outcome);
        });

        $application->setRawAttributes($result->getAttributes(), true);

        return $result;
    }

    /**
     * Applications waiting at a check that has just been turned off move on, as if
     * the check had been passed (it is recorded as skipped). Returns how many moved.
     */
    public function releaseTurnedOffChecks(): int
    {
        $moved = 0;

        foreach ($this->pipeline()->steps() as $step) {
            if ($step['enabled']) {
                continue;
            }

            $waiting = InstrumentApplication::query()->where('stage', $step['stage'])->pluck('id');
            foreach ($waiting as $id) {
                $this->db()->transaction(function () use ($id, $step, &$moved) {
                    $locked = InstrumentApplication::query()->lockForUpdate()->find($id);
                    if (!$locked || $locked->stage !== $step['stage']) {
                        return;
                    }
                    $note = "{$step['label']} was turned off in Configurable Entries; the application moved on.";
                    $this->storeCheck($locked, $step['key'], InstrumentApplicationCheck::OUTCOME_SKIPPED, $note, 'System');
                    $this->advanceAfterCheck($locked, $step['key'], $note, InstrumentApplicationCheck::OUTCOME_SKIPPED);
                    $moved++;
                });
            }
        }

        return $moved;
    }

    /** After a check is passed or skipped: next check, or the LIC. Runs inside the caller's transaction. */
    private function advanceAfterCheck(InstrumentApplication $locked, string $type, ?string $notes, string $outcome): InstrumentApplication
    {
        $next = $this->pipeline()->nextStage($locked);
        $meta = ['check_type' => $type, 'outcome' => $outcome];

        if ($next === InstrumentApplication::STAGE_LIC_ISSUED) {
            $this->record($locked, 'check_recorded', $locked->stage, $locked->stage, $notes, $meta);

            return $this->issueLicAutomatically($locked);
        }

        return $this->transition($locked, $next, [
            'event' => 'check_recorded',
            'note' => $notes,
            'meta' => $meta,
        ]);
    }

    /**
     * Every check that is on has passed: record the ones that are off as skipped and
     * generate the Land Information Certificate. Runs inside the caller's transaction.
     */
    private function issueLicAutomatically(InstrumentApplication $locked): InstrumentApplication
    {
        $done = InstrumentApplicationCheck::query()->where('application_id', $locked->id)->pluck('check_type')->unique()->all();
        foreach ($this->pipeline()->steps() as $step) {
            if (!$step['enabled'] && !in_array($step['key'], $done, true)) {
                $this->storeCheck($locked, $step['key'], InstrumentApplicationCheck::OUTCOME_SKIPPED, "{$step['label']} is turned off in Configurable Entries.", 'System');
            }
        }

        $licNumber = InstrumentApplication::nextLicNumber();

        return $this->transition($locked, InstrumentApplication::STAGE_LIC_ISSUED, [
            'event' => 'lic_issued',
            'note' => "All checks passed. Land Information Certificate {$licNumber} generated automatically.",
            'meta' => ['lic_number' => $licNumber],
            'columns' => [
                'lic_number' => $licNumber,
                'lic_issued_at' => now(),
                'lic_issued_by' => Auth::id(),
            ],
        ]);
    }

    private function pipeline(): WorkflowPipeline
    {
        return app(WorkflowPipeline::class);
    }

    // =====================================================================
    // 3. Codes: TIN, registration fee bill
    // =====================================================================

    public function markCodesVerified(InstrumentApplication $application, TaxIdentityCheck $check, string $instrumentType, float $consideration): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_CODES_VERIFIED, [
            'note' => "TIN {$check->tin} (" . ($check->tin_status === TaxIdentityCheck::TIN_REGISTERED ? 'newly registered' : 'found on the BIR register') . ").",
            'meta' => ['tax_identity_check_id' => $check->id],
            'columns' => [
                'tin' => $check->tin,
                'instrument_type' => $instrumentType,
                'consideration_amount' => $consideration,
            ],
        ]);
    }

    public function markRegistrationInvoiced(InstrumentApplication $application, InstrumentInvoice $invoice): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_REGISTRATION_INVOICED, [
            'note' => "registration fee bill {$invoice->reference} generated by Kano billing (₦" . number_format((float) $invoice->total, 2) . ').',
            'meta' => ['invoice_id' => $invoice->id],
            'columns' => ['payment_reference' => $invoice->reference],
        ]);
    }

    public function markRegistrationVoided(InstrumentApplication $application, InstrumentInvoice $invoice, string $reason): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_CODES_VERIFIED, [
            'event' => 'registration_voided',
            'note' => "registration fee bill {$invoice->reference} voided: {$reason}",
            'meta' => ['invoice_id' => $invoice->id],
            'columns' => ['payment_reference' => null],
        ]);
    }

    public function markRegistrationPaid(InstrumentApplication $application, InstrumentPayment $payment): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_REGISTRATION_PAID, [
            'note' => "Registration fee paid via Kano billing (transaction {$payment->transaction_id}). Ready for Instrument Capture.",
            'meta' => ['payment_id' => $payment->id],
        ]);
    }

    // =====================================================================
    // 4. BIR (driven from Instrument Capture) and completion
    // =====================================================================

    public function onSentToBir(InstrumentApplication $application, BirSubmission $submission): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_PENDING_BIR, [
            'event' => $submission->version > 1 ? 'bir_resubmitted' : 'bir_submitted',
            'note' => "Instrument capture #{$submission->instrument_capture_id} sent to BIR (submission v{$submission->version}).",
            'meta' => ['submission_id' => $submission->id],
            'columns' => ['instrument_capture_id' => $submission->instrument_capture_id],
        ]);
    }

    public function onBirDecision(InstrumentApplication $application, BirSubmission $submission): InstrumentApplication
    {
        $approved = $submission->status === BirSubmission::STATUS_APPROVED;

        return $this->transition($application, $approved ? InstrumentApplication::STAGE_BIR_APPROVED : InstrumentApplication::STAGE_BIR_DECLINED, [
            'event' => $approved ? 'bir_approved' : 'bir_declined',
            'note' => $approved
                ? 'Approved by BIR; stamp duty and dicing processed. Available for registration.' . ($submission->bir_reference ? " BIR ref {$submission->bir_reference}." : '')
                : $submission->decline_reason,
            'meta' => ['submission_id' => $submission->id],
        ]);
    }

    /** Silent for captures with no application, so registration paths need no check. */
    public function onRegistered(int $captureId, int $deedRegistrationId): void
    {
        $application = InstrumentApplication::query()->where('instrument_capture_id', $captureId)->first();
        if (!$application || $application->stage !== InstrumentApplication::STAGE_BIR_APPROVED) {
            return;
        }

        $this->transition($application, InstrumentApplication::STAGE_REGISTERED, [
            'note' => 'Registered in Instrument Registration; registration particulars generated.',
            'meta' => ['deed_registration_id' => $deedRegistrationId],
            'columns' => ['deed_registration_id' => $deedRegistrationId],
        ]);
    }

    public function onCorPrinted(InstrumentApplication $application, bool $isReprint, ?string $reason): void
    {
        if ($application->stage === InstrumentApplication::STAGE_REGISTERED) {
            $this->transition($application, InstrumentApplication::STAGE_COR_PRINTED, [
                'note' => 'Confirmation of Registration sticker printed.',
            ]);

            return;
        }

        $this->logEvent($application, $isReprint ? 'cor_reprinted' : 'cor_printed', $reason);
    }

    public function finalSign(InstrumentApplication $application, string $officerName, ?string $note): InstrumentApplication
    {
        return $this->transition($application, InstrumentApplication::STAGE_COMPLETED, [
            'note' => $note ?: "Signed by {$officerName}.",
            'columns' => [
                'signed_at' => now(),
                'signed_by' => Auth::id(),
                'signing_officer_name' => $officerName,
            ],
        ]);
    }

    public function cancel(InstrumentApplication $application, string $reason): InstrumentApplication
    {
        if (trim($reason) === '') {
            throw WorkflowGuardException::because('A cancellation must state the reason.');
        }

        return $this->transition($application, InstrumentApplication::STAGE_CANCELLED, [
            'note' => $reason,
            'columns' => ['failure_reason' => $reason],
        ]);
    }

    // =====================================================================
    // Engine
    // =====================================================================

    /**
     * @param array{event?: string, note?: ?string, meta?: array, columns?: array,
     *              before?: callable, after?: callable} $options
     */
    public function transition(InstrumentApplication $application, string $target, array $options = []): InstrumentApplication
    {
        return $this->db()->transaction(function () use ($application, $target, $options) {
            $locked = $this->lock($application);

            if (isset($options['before'])) {
                ($options['before'])($locked);
            }

            if (!$locked->canTransitionTo($target)) {
                throw WorkflowGuardException::illegal($locked, $target);
            }

            if (!empty($options['columns'])) {
                $locked->fill($options['columns']);
            }

            $this->assertGuards($locked, $target);

            $from = $locked->stage;
            $locked->stage = $target;
            $locked->stage_changed_at = now();
            $locked->updated_by = Auth::id();
            $locked->save();

            if (isset($options['after'])) {
                ($options['after'])($locked);
            }

            $this->record($locked, $options['event'] ?? 'stage_changed', $from, $target, $options['note'] ?? null, $options['meta'] ?? []);

            $application->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    public function logEvent(InstrumentApplication $application, string $event, ?string $note = null, array $meta = []): void
    {
        $this->record($application, $event, $application->stage, $application->stage, $note, $meta);
    }

    /** Reasons the application cannot arrive at $target, checked against the database. */
    public function blockers(InstrumentApplication $application, string $target): array
    {
        $blockers = [];
        $fee = fn () => $application->activeInvoice(InstrumentInvoice::PURPOSE_APPLICATION_FEE);
        $feePayment = fn () => $fee()?->livePayment();

        switch ($target) {
            case InstrumentApplication::STAGE_FEE_PAID:
                if ($feePayment()?->status !== InstrumentPayment::STATUS_CAPTURED) {
                    $blockers[] = 'No payment has been captured against the application fee bill.';
                }
                break;

            case InstrumentApplication::STAGE_FEE_PENDING:
                if ($feePayment()) {
                    $blockers[] = 'The application fee still has a live payment.';
                }
                break;

            case InstrumentApplication::STAGE_FEE_VALIDATED:
                $payment = $feePayment();
                if ($payment?->status !== InstrumentPayment::STATUS_VALIDATED || !$payment->receipt_number) {
                    $blockers[] = 'The payment has not been validated by KANGIS Accounts.';
                }
                break;

            case InstrumentApplication::STAGE_LANDS_CHECK:
            case InstrumentApplication::STAGE_SURVEY_CHECK:
            case InstrumentApplication::STAGE_PLANNING_CHECK:
                // Leaving the fee stage for the first check needs the issued receipt.
                if ($application->stage === InstrumentApplication::STAGE_FEE_VALIDATED) {
                    $blockers = array_merge($blockers, $this->receiptBlockers($feePayment()));
                }
                break;

            case InstrumentApplication::STAGE_LIC_ISSUED:
                if ($application->stage === InstrumentApplication::STAGE_FEE_VALIDATED) {
                    $blockers = array_merge($blockers, $this->receiptBlockers($feePayment()));
                }
                foreach ($this->pipeline()->outstandingChecks($application) as $outstanding) {
                    $blockers[] = "{$outstanding['department']} has not passed the application.";
                }
                if (trim((string) $application->lic_number) === '') {
                    $blockers[] = 'The LIC number was not generated.';
                }
                break;

            case InstrumentApplication::STAGE_CODES_VERIFIED:
                if (!$application->lic_number) {
                    $blockers[] = 'The LIC has not been issued.';
                }
                if (trim((string) $application->tin) === '') {
                    $blockers[] = 'No TIN has been obtained from BIR.';
                }
                if (trim((string) $application->instrument_type) === '') {
                    $blockers[] = 'The instrument type is required.';
                }
                break;

            case InstrumentApplication::STAGE_REGISTRATION_INVOICED:
                if (!$application->activeInvoice(InstrumentInvoice::PURPOSE_REGISTRATION)) {
                    $blockers[] = 'No registration fee bill has been generated.';
                }
                break;

            case InstrumentApplication::STAGE_REGISTRATION_PAID:
                $registrationInvoice = $application->activeInvoice(InstrumentInvoice::PURPOSE_REGISTRATION);
                if (!$registrationInvoice || $registrationInvoice->status !== InstrumentInvoice::STATUS_PAID || $registrationInvoice->livePayment()?->status !== InstrumentPayment::STATUS_VALIDATED) {
                    $blockers[] = 'The registration fee bill has not been paid.';
                }
                break;

            case InstrumentApplication::STAGE_PENDING_BIR:
                $registrationInvoice = $application->activeInvoice(InstrumentInvoice::PURPOSE_REGISTRATION);
                if (!$registrationInvoice || $registrationInvoice->status !== InstrumentInvoice::STATUS_PAID) {
                    $blockers[] = 'The registration fee bill has not been paid.';
                }
                if (!$application->instrument_capture_id) {
                    $blockers[] = 'No captured instrument is linked.';
                }
                break;

            case InstrumentApplication::STAGE_BIR_APPROVED:
            case InstrumentApplication::STAGE_BIR_DECLINED:
                $latest = BirSubmission::query()
                    ->where('instrument_capture_id', $application->instrument_capture_id)
                    ->orderByDesc('version')
                    ->first();
                $expected = $target === InstrumentApplication::STAGE_BIR_APPROVED ? BirSubmission::STATUS_APPROVED : BirSubmission::STATUS_DECLINED;
                if (!$latest || $latest->status !== $expected) {
                    $blockers[] = 'The BIR decision has not been recorded on the latest submission.';
                }
                break;

            case InstrumentApplication::STAGE_REGISTERED:
                if (!$application->deed_registration_id) {
                    $blockers[] = 'No registration is recorded.';
                }
                break;

            case InstrumentApplication::STAGE_COMPLETED:
                if (!$application->signed_at || trim((string) $application->signing_officer_name) === '') {
                    $blockers[] = 'The signing Land Officer must be recorded.';
                }
                break;

            case InstrumentApplication::STAGE_CHECK_FAILED:
            case InstrumentApplication::STAGE_CANCELLED:
                if (trim((string) $application->failure_reason) === '') {
                    $blockers[] = 'A reason is required.';
                }
                break;
        }

        return $blockers;
    }

    // ---------------------------------------------------------------------

    /**
     * The applicant's customer record: the one picked from the register, or a
     * new workflow customer. The picked record's contact details are refreshed
     * from what the officer confirmed on the form.
     */
    private function resolveCustomer(array $data, $fileRecord): CustomerRecord
    {
        $customer = !empty($data['customer_record_id'])
            ? CustomerRecord::query()->find((int) $data['customer_record_id'])
            : null;

        if (!$customer) {
            $customer = new CustomerRecord([
                'customer_type' => ($data['applicant_type'] ?? 'individual') === 'corporate' ? CustomerRecord::TYPE_CORPORATE : CustomerRecord::TYPE_INDIVIDUAL,
                'full_name' => trim((string) $data['applicant_name']),
                'file_number' => $fileRecord->file_number,
                'file_indexing_id' => $fileRecord->id,
                'source' => CustomerRecord::SOURCE_WORKFLOW,
                'created_by' => Auth::id(),
            ]);
        }

        $customer->fill(array_filter([
            'phone' => CustomerRecord::normalizePhone($data['phone'] ?? null) ?: $customer->phone,
            'email' => $data['email'] ?? null,
            'full_address' => $data['address'] ?? null,
            'rc_number' => $data['rc_number'] ?? null,
            'updated_by' => Auth::id(),
        ], fn ($value) => $value !== null && $value !== ''));

        if (!empty($data['nin'])) {
            $customer->setNin($data['nin']);
        }

        $customer->save();

        return $customer;
    }

    private function assertGuards(InstrumentApplication $application, string $target): void
    {
        $blockers = $this->blockers($application, $target);

        if ($blockers !== []) {
            throw WorkflowGuardException::because(implode(' ', $blockers));
        }
    }

    private function lock(InstrumentApplication $application): InstrumentApplication
    {
        return InstrumentApplication::query()->lockForUpdate()->findOrFail($application->id);
    }

    private function expectStage(InstrumentApplication $locked, string $stage): void
    {
        if ($locked->stage !== $stage) {
            throw WorkflowGuardException::because('The application has moved on since this page was loaded. Refresh and try again.');
        }
    }

    private function storeCheck(InstrumentApplication $application, string $type, string $outcome, ?string $notes, ?string $byName = null, array $details = []): void
    {
        InstrumentApplicationCheck::create([
            'application_id' => $application->id,
            'check_type' => $type,
            'outcome' => $outcome,
            'notes' => $notes,
            // LIC Section A / B particulars (InstrumentApplicationCheck::LIC_FIELDS).
            'details' => array_filter($details, fn ($v) => $v !== null && $v !== '') ?: null,
            'checked_by' => $byName ? null : Auth::id(),
            'checked_by_name' => $byName ?? Auth::user()?->name,
            'checked_at' => now(),
        ]);
    }

    private function receiptBlockers(?InstrumentPayment $payment): array
    {
        if (!$payment || $payment->status !== InstrumentPayment::STATUS_VALIDATED) {
            return ['The application fee has not been validated.'];
        }

        $blockers = [];
        if (!$payment->counter_signed_at) {
            $blockers[] = 'The customer has not counter-signed the payment receipt.';
        }
        if (!$payment->receipt_scan_document_id) {
            $blockers[] = 'The signed payment receipt has not been scanned into DMS.';
        }

        return $blockers;
    }

    private function record(InstrumentApplication $application, string $event, ?string $from, ?string $to, ?string $note, array $meta = []): void
    {
        try {
            InstrumentApplicationEvent::create([
                'application_id' => $application->id,
                'event' => $event,
                'from_stage' => $from,
                'to_stage' => $to,
                'actor_id' => Auth::id(),
                'actor_name' => Auth::user()?->name,
                'note' => $note,
                'meta' => $meta ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // The history is part of the audit trail: a step that cannot be
            // recorded must not happen, so let the transaction unwind.
            Log::error('Instrument workflow event could not be recorded', [
                'application_id' => $application->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function db(): Connection
    {
        return DB::connection('sqlsrv');
    }
}

