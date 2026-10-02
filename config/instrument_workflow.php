<?php

/*
|--------------------------------------------------------------------------
| Instrument Registration Workflow
|--------------------------------------------------------------------------
|
| Lands module intake (application fee → checks → LIC → TIN), Instrument
| Capture → Send to BIR, and registration in /instrument_registration only
| after BIR approval.
|
| Two external systems are involved:
|   TIN       BIR server            (tin.driver)
|   Payment   Kano billing/Paystack (payment.driver)
|
| ALAES additionally issues an ABSSIN (Abia State Social Security ID) before
| the LIC and checks the applicant's outstanding state liability against it.
| Kano has no equivalent, so that step does not exist here — identity is
| NIN / RC number and the tax identity check is TIN only.
|
| The BIR TIN service has no live API yet, so its driver defaults to a demo
| implementation that never touches the network.
|
*/

return [

    'tin' => [
        'driver' => env('BIR_TIN_DRIVER', 'demo'),

        'http' => [
            'base_url' => env('BIR_TIN_BASE_URL'),
            'api_key' => env('BIR_TIN_API_KEY'),
            'timeout' => env('BIR_TIN_TIMEOUT', 20),
        ],
    ],

    'payment' => [
        /*
        | 'billing' raises the fee against Kano's existing dbo.billing, using the
        | source / source_id convention the other modules follow, so instrument
        | revenue is reported with the rest. 'paystack' adds the online channel
        | on top. 'demo' issues fake references and never touches the network —
        | it is the default until the billing wiring is signed off.
        */
        'driver' => env('INSTRUMENT_PAYMENT_DRIVER', 'demo'),

        'demo' => [
            'reference_prefix' => 'DEMO-REG',
            'transaction_prefix' => 'DEMO-TX',
        ],

        // Rows written into dbo.billing carry this in the `source` column.
        // Kept in step with App\Models\Billing::SOURCE_INSTRUMENT (a literal here
        // so the config stays readable when cached).
        'billing' => [
            'source' => 'INSTRUMENT_REGISTRATION',
        ],

        'paystack' => [
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        ],

        // Application fee bills and receipts (paid at Bank / KANGIS teller).
        'bill_prefix' => 'IRB',
        'receipt_prefix' => 'IRR',

        'banks' => ['Access Bank', 'Fidelity Bank', 'First Bank', 'FCMB', 'GTBank', 'Jaiz Bank', 'Keystone Bank', 'Polaris Bank', 'Sterling Bank', 'UBA', 'Union Bank', 'Zenith Bank'],
    ],

    'bir' => [
        // 'internal': BIR officers review inside KLAES. 'http': the BIR portal API (future).
        'driver' => env('BIR_DRIVER', 'internal'),

        'http' => [
            'base_url' => env('BIR_BASE_URL'),
            'api_key' => env('BIR_API_KEY'),
            'webhook_secret' => env('BIR_WEBHOOK_SECRET'),
        ],

        /*
        | Captures of these types register as before, without BIR. Matched
        | case-insensitively as a substring of the instrument type. CofO
        | pagination and Occupancy Permits are not taxable dealings.
        */
        'exempt_instrument_types' => ['Certificate of Occupancy', 'CofO', 'Occupancy Permit'],

        /*
        | OFF by default on KLAES. Every capture already in dbo.instrument_capture
        | has a null bir_status and registers exactly as it does today; turning
        | this on is what starts requiring BIR approval. Switch it on only once
        | the BIR reviewers have their role and the queue has been walked through.
        */
        'gate_enabled' => env('INSTRUMENT_BIR_GATE', false),

        // How long a submission may wait in the BIR queue before it counts as
        // overdue on the review dashboard.
        'sla_hours' => (int) env('BIR_SLA_HOURS', 48),
    ],

    /*
    | Instrument types a Lands application can be raised for. The list comes
    | from the instrument type table (new_instrument_types), minus the BIR-exempt
    | types above and minus these. OP dealings still go to BIR when captured in
    | Deeds; they are simply not applied for at this counter. Matched
    | case-insensitively against the whole name.
    */
    'intake' => [
        'excluded_instrument_types' => ['OP Direct Allocation', 'OP Resettlement', 'Transfer Of Title (OP)', 'Occupancy Permit (OP)'],
    ],

    /*
    | Role names per action, matched against User::assignedRoleNames(). Super
    | Admin always passes. Routes carry only 'auth'; InstrumentAccessGuard
    | enforces these inside every controller action.
    */
    'roles' => [
        'application.manage' => ['Instrument Workflow - Lands'],
        'payment.capture' => ['Instrument Workflow - Cashier'],
        'payment.validate' => ['Instrument Workflow - Accounts'],
        'receipt.issue' => ['Instrument Workflow - Cashier'],
        'check.lands' => ['Instrument Workflow - Lands'],
        // Recorded from Survey → Approvals.
        'check.survey' => ['Instrument Workflow - Survey', 'Survey - Approvals'],
        // Recorded from Physical Planning → PP Director → Instrument Registration Approval;
        // passing it generates the LIC. The Town Planning role name is kept so
        // existing assignments keep working.
        'check.planning' => ['Instrument Workflow - Physical Planning', 'Instrument Workflow - Town Planning', 'PP Director'],
        // The TIN lookup (ALAES also verifies an ABSSIN here; Kano has none).
        'codes.verify' => ['Instrument Workflow - Lands', 'Instrument Workflow - Cashier'],
        // The registration fee bill, raised after the checks pass.
        'registration_fee.manage' => ['Instrument Workflow - Cashier'],
        'bir.send' => ['Deeds Registration'],
        'bir.review' => ['BIR Officer'],
        'cor.print' => ['Deeds Registration'],
        'final.sign' => ['Land Officer'],
    ],

    'documents' => [
        'disk' => env('INSTRUMENT_WORKFLOW_DISK', 'ols_private'),
        'directory' => 'instrument-workflow',
        'max_size_kb' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],

        // Taken from the linked Lands application when the instrument is sent —
        // never uploaded. KLAES itself issues both: the Revenue Collector's
        // Receipt for the application fee (`print.receipt`, or the scanned
        // counter-signed copy when one was filed) and the LIC generated when
        // Physical Planning passes (`print.lic`). BirSystemDocumentService renders
        // them to PDF and attaches them to every submission version.
        'bir_system' => [
            'payment_evidence' => 'Evidence of Payment',
            'lic' => 'Land Information Certificate (LIC)',
        ],

        // Uploaded on the Instrument Capture form and sent to BIR. Nothing is
        // required by upload any more: the two documents that must be there come
        // from the system above, which is why a Lands application must be linked.
        'bir_required' => [],

        'bir_optional' => [
            'executed_instrument' => 'Executed Instrument',
            'applicant_identification' => 'Applicant Identification',
            'survey_plan' => 'Survey Plan',
            'other' => 'Other Supporting Document',
        ],
    ],

    /*
    | Tariff seeds (installed into instrument_fee_items; edit the rows, not this).
    | purpose application_fee: billed when the application is submitted.
    | purpose registration:    billed after the checks and the TIN lookup.
    |
    | These amounts are ALAES's, carried over as placeholders so the workflow
    | runs end to end. Kano's real tariff replaces them — either set here before
    | install or edited afterwards in Configurable Entries.
    */
    'fees' => [
        'application_form' => ['purpose' => 'application_fee', 'label' => 'Instrument Registration Application Form', 'calc_type' => 'flat', 'amount' => 10000.00],
        'registration_fee' => ['purpose' => 'registration', 'label' => 'Instrument Registration Fee', 'calc_type' => 'flat', 'amount' => 20000.00],
        'stamp_duty' => ['purpose' => 'registration', 'label' => 'Stamp Duty', 'calc_type' => 'percent_of_consideration', 'rate' => 1.50, 'min_amount' => 5000.00],
        'dicing' => ['purpose' => 'registration', 'label' => 'Dicing', 'calc_type' => 'flat', 'amount' => 5000.00],
    ],

    /*
    | Land Information Certificate. The signatories are Kano's and must be set
    | before an LIC is issued for real — the defaults are deliberately blank
    | rather than carrying Abia's officers across.
    */
    'lic' => [
        'director_lands' => env('LIC_DIRECTOR_LANDS', ''),
        'surveyor_general' => env('LIC_SURVEYOR_GENERAL', ''),
        'utm_zone' => '32N',
    ],

    /*
    | Registration numbering (InstrumentRegistrationService).
    |
    | unified_vault  The Single Unified Register: while it is live, EVERY
    |                instrument type takes its Serial/Page/Volume from one vault,
    |                serial = page, rolling to a new volume after page_limit.
    |                Live means: `enabled` is true here AND the vault row has
    |                is_shared = 1.
    |
    |                OFF by default on KLAES. Kano's twelve existing vaults in
    |                dbo.instrument_number_vaults number each instrument type on
    |                its own rules, and switching them onto one shared register
    |                changes live registration numbering — which is not part of
    |                this port. Turn it on deliberately, never as a side effect.
    | default_page_limit  The roll used for a vault whose row carries no
    |                page_limit. 300 is the historic Deeds rule.
    */
    'registration' => [
        'unified_vault' => [
            'enabled' => env('INSTRUMENT_UNIFIED_VAULT', false),
            'name' => 'Unified Register',
            'page_limit' => 100,
            'separate_vaults' => [],
        ],
        'default_page_limit' => 300,
    ],

    /* Bumped when identity handling changes and a fresh TIN lookup should be forced. */
    'version' => 1,
];

