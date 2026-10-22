<?php

/*
 * KLAES REV-M billing (docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 * Payment methods offered at the bill stage. A disabled method is still shown,
 * greyed out and not selectable (user, 2026-10-19).
 */
return [
    'payment_methods' => [
        'paystack' => [
            'label' => 'Paystack',
            'enabled' => true,
            'note' => 'Online payment through the existing Paystack setup.',
        ],
        'pos' => [
            'label' => 'POS',
            'enabled' => true,
            'note' => 'Paid at a POS terminal with the Bill Ref ID; record the POS Transaction ID.',
        ],
        'bank_transfer' => [
            'label' => 'Bank Transfer',
            'enabled' => false,
            'note' => 'Not available yet.',
        ],
    ],

    /** Payment methods recorded by the system, never offered for selection. */
    'recorded_methods' => [
        'captured' => 'Paid before application (receipt captured)',
        'module_receipt' => 'Paid (receipt recorded in the module)',
        'invoice' => 'Invoice (bank transfer, validated by PHS admin)',
        'online' => 'Online payment (portal)',
    ],

    'statuses' => [
        'unpaid' => 'Unpaid',
        'part_paid' => 'Part paid',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],

    /**
     * Payment cycles for recurring bill types (Bill Settings → "Recurring").
     * months = length of one period; the next payment falls due the day after it ends.
     */
    'cycles' => [
        'monthly' => ['label' => 'Monthly', 'months' => 1],
        'quarterly' => ['label' => 'Quarterly', 'months' => 3],
        'half_yearly' => ['label' => 'Half-yearly', 'months' => 6],
        'annual' => ['label' => 'Annual', 'months' => 12],
    ],

    /**
     * Bill types charged as a unit rate: the line's quantity is the plot size (m²), filled
     * from the file on an on-the-fly bill. Ground rent is rate x plot size, never a sum
     * of stored ground_rent amounts.
     */
    'unit_rate_types' => ['ground_rent'],

    /** Reconciliation of a successful payment against the bank / gateway / POS settlement. */
    'reconciliation_statuses' => [
        'unreconciled' => 'Unreconciled',
        'reconciled' => 'Reconciled',
        'disputed' => 'Disputed',
    ],

    /**
     * SMS notifications (bill generated, payment received, next payment due). The
     * switches and wording are in the SMS Control Centre (config/klaes_sms.php,
     * group "Revenue (REV-M)"). These are the response channels the messages name:
     * how the payer pays ([Channels], from the bill type's payment methods) and
     * whom to contact ([Contact]).
     */
    'notify' => [
        'contact' => env('REVM_SMS_CONTACT', 'the Revenue Unit, Ministry of Land and Physical Planning, No. 2 Dr Bala Mohammed Road, Kano'),
        'channel_labels' => [
            'paystack' => 'Paystack (online)',
            'pos' => 'POS at the Ministry',
            'bank_transfer' => 'bank transfer',
        ],
    ],

    /** Legacy bill types: what File Indexing captures. */
    'legacy_types' => [
        'bill_balance' => 'Bill Balance',
        'grant_rent' => 'Grant Rent',
    ],
];
