<?php

namespace App\Services\Billing;

use App\Models\BillMaster;
use App\Models\BillPayment;
use App\Models\SmsDispatchLog;
use App\Models\SmsSetting;
use App\Services\Sms\KlaesSmsDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * KLAES REV-M SMS notices to the payer on a bill (user, 2026-10-21: notifications
 * are SMS). Three moments:
 *
 *   generated   a bill is generated in REV-M (generate, on-the-fly, a module bill
 *               generated, the next cycle of a recurring bill)
 *   payment     a Paystack or POS payment is recorded in REV-M
 *   due         the next payment of a recurring bill is near (bills:cycle)
 *
 * Everything goes through KlaesSmsDispatcher: the switches and wording are in the
 * SMS Control Centre (group "Revenue (REV-M)"), one message per event (dedupe key),
 * and the gateway's response is written to sms_dispatch_logs with
 * subject_type = 'bill_master', subject_id = the Bill Ref ID, which the bill card
 * shows. Never throws: a failed SMS must not undo a bill or a payment.
 */
class BillNotifier
{
    public const SUBJECT = 'bill_master';

    public function __construct(private KlaesSmsDispatcher $sms)
    {
    }

    public function generated(BillMaster $bill, bool $phoneConfirmed = false, bool $now = false, ?string $dedupe = null): void
    {
        $this->send(SmsSetting::KEY_BILL_GENERATED, $bill, $this->billTokens($bill), [
            'dedupe_key' => $dedupe ?? 'bill_generated:' . $bill->bill_ref_id,
            'phone_confirmed' => $phoneConfirmed,
        ], $now);
    }

    public function payment(BillMaster $bill, BillPayment $payment, bool $now = false, ?string $dedupe = null): void
    {
        $outstanding = max(0, round((float) $bill->amount - (float) $bill->amount_paid, 2));

        $this->send(SmsSetting::KEY_BILL_PAYMENT, $bill, $this->billTokens($bill) + [
            'Amount' => self::naira((float) $payment->amount),
            'Transaction ID' => $payment->transaction_id,
            'Balance' => self::naira($outstanding),
            'DateTime' => Carbon::parse($payment->paid_at ?? now())->setTimezone(config('klaes_sms.timezone', 'Africa/Lagos'))->format(config('klaes_sms.datetime_format', 'd/m/Y h:i A')),
        ], [
            'dedupe_key' => $dedupe ?? 'bill_payment:' . $payment->id,
            'event_at' => $payment->paid_at,
        ], $now);
    }

    /** The next payment of a recurring bill is near; $amount is what the next cycle will bill. */
    public function due(BillMaster $bill, bool $now = true, ?string $dedupe = null): ?SmsDispatchLog
    {
        return $this->send(SmsSetting::KEY_BILL_DUE, $bill, $this->billTokens($bill) + [
            'Due Date' => optional($bill->next_due_date)->format('d/m/Y'),
            'Cycle' => config('billing.cycles.' . $bill->payment_cycle . '.label', ''),
        ], [
            'dedupe_key' => $dedupe ?? 'bill_due:' . $bill->bill_ref_id . ':' . optional($bill->next_due_date)->format('Ymd'),
        ], $now);
    }

    /** The SMS sent (or skipped, with the reason) for one bill, newest first. */
    public static function log(string $billRef)
    {
        return SmsDispatchLog::query()->where('subject_type', self::SUBJECT)->where('subject_id', $billRef)
            ->orderByDesc('id')->limit(20)->get();
    }

    /** "N125,000.00": the naira sign is not in the GSM alphabet and would double the cost of every SMS. */
    public static function naira(float $amount): string
    {
        return 'N' . number_format($amount, 2);
    }

    /** How this bill can be paid, from its type's selectable payment methods: "Paystack (online) or POS at the Ministry". */
    public static function channels(BillMaster $bill): string
    {
        $labels = config('billing.notify.channel_labels', []);
        $methods = $bill->type ? $bill->type->paymentMethods() : [];
        $names = [];
        foreach ($methods as $method) {
            if (config("billing.payment_methods.{$method}.enabled") && isset($labels[$method])) {
                $names[] = $labels[$method];
            }
        }
        if (!$names) {
            return 'the Revenue Unit of the Ministry';
        }
        $last = array_pop($names);

        return $names ? implode(', ', $names) . ' or ' . $last : $last;
    }

    private function billTokens(BillMaster $bill): array
    {
        return [
            'Name' => $bill->payer_name ?: 'Sir/Madam',
            'Bill Type' => optional($bill->type)->name ?: $bill->bill_type_code,
            'Bill Ref' => $bill->bill_ref_id,
            'FileNo' => $bill->file_number,
            'Amount' => self::naira((float) $bill->amount),
            'Due Date' => optional($bill->due_date)->format('d/m/Y'),
            'Channels' => self::channels($bill),
            'Contact' => config('billing.notify.contact'),
        ];
    }

    private function send(string $key, BillMaster $bill, array $tokens, array $context, bool $now): ?SmsDispatchLog
    {
        try {
            $context += [
                'file_number' => $bill->file_number,
                'subject_type' => self::SUBJECT,
                'subject_id' => $bill->bill_ref_id,
            ];
            $phone = trim((string) $bill->payer_phone) ?: null;

            if ($now) {
                return $this->sms->sendNow($key, $phone, $tokens, $context);
            }
            $this->sms->queue($key, $phone, $tokens, $context);
        } catch (\Throwable $e) {
            Log::error('BillNotifier: could not send', ['key' => $key, 'bill' => $bill->bill_ref_id, 'message' => $e->getMessage()]);
        }

        return null;
    }
}
