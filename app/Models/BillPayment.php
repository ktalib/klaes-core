<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A payment that settles a bill, by Bill Ref ID. A bill can take several
 * (part payments). Methods: config('billing.payment_methods') — Paystack,
 * POS (Transaction ID recorded against the Bill Ref ID), Bank Transfer.
 * receipt_no is the official receipt, filled under Receipting.
 */
class BillPayment extends Model
{
    public const METHOD_PAYSTACK = 'paystack';
    public const METHOD_POS = 'pos';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';

    /** Reconciliation against the bank / gateway / POS settlement (config billing.reconciliation_statuses). Null = unreconciled. */
    public const RECON_UNRECONCILED = 'unreconciled';
    public const RECON_RECONCILED = 'reconciled';
    public const RECON_DISPUTED = 'disputed';
    public const RECON_BY_PAYSTACK = 'Paystack verify';

    protected $connection = 'sqlsrv';
    protected $table = 'bill_payments';

    protected $fillable = [
        'bill_ref_id', 'method', 'transaction_id', 'amount', 'paid_at', 'status', 'gateway_response',
        'recorded_by', 'validated_by', 'validated_at', 'receipt_no', 'receipt_date', 'notes',
        'reconciliation_status', 'reconciled_at', 'reconciled_by', 'reconciliation_note', 'settlement_ref',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'validated_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'receipt_date' => 'date',
    ];

    /** unreconciled / reconciled / disputed (no status yet = unreconciled). */
    public function reconciliation(): string
    {
        return $this->reconciliation_status ?: self::RECON_UNRECONCILED;
    }

    public function methodLabel(): string
    {
        return self::labelFor((string) $this->method);
    }

    /** Selectable methods (payment_methods) and system-recorded ones (recorded_methods). */
    public static function labelFor(?string $method): string
    {
        return config("billing.payment_methods.{$method}.label")
            ?? config("billing.recorded_methods.{$method}")
            ?? (string) $method;
    }
}
