<?php

/*
|--------------------------------------------------------------------------
| Deeds pipeline — stage order and how strictly it is enforced
|--------------------------------------------------------------------------
|
| The Deeds pipeline runs Valuation -> Consent -> Print -> Registration. This file says
| how hard each hand-off is enforced, so the rule can be tightened once the
| back catalogue has caught up without another code change.
|
| Each gate takes one of:
|
|   'block'  the action is refused until the previous stage exists.
|   'warn'   the action is allowed, but the response carries a warning and the
|            workflow strip shows the stage as skipped.
|   'off'    no check at all.
|
| WHY THE DEFAULTS DIFFER — measured on live data, 2026-09-26:
|
|   valuation_before_consent => 'warn'
|       Only 54 of the 112 consents captured in the previous 30 days were for
|       a file that has a valuation report at all. Set to 'block' on the day
|       this was measured and roughly half of daily consent capture stops.
|       Move it to 'block' once Valuation is routinely done first.
|
|   consent_before_registration => 'block'
|       253 of the 282 assignment/gift/mortgage captures in the previous 90
|       days already have a consent on the file, so blocking affects about one
|       capture in ten. This gate also already existed in the capture screen —
|       it was switched off client-side pending this work.
|
*/

return [

    'gates' => [
        // Set to 'block' on request, knowing the number below: until Valuation
        // is routinely done first, a little over half of consent capture will
        // be refused at the file-selection step. Drop to 'warn' to let it
        // through with a warning instead — nothing else needs to change.
        'valuation_before_consent' => env('DEEDS_GATE_VALUATION_BEFORE_CONSENT', 'block'),
        'consent_before_registration' => env('DEEDS_GATE_CONSENT_BEFORE_REGISTRATION', 'block'),
        'consent_print_before_registration' => env('DEEDS_GATE_CONSENT_PRINT_BEFORE_REGISTRATION', 'block'),
    ],

    // Which consent types are valued before they are captured.
    //
    // Only an Assignment is billed from a valuation: it is a sale, so the
    // Ministry has to know what the property is worth to assess stamp duty and
    // the registration fee. A Gift passes no consideration and a Mortgage is
    // secured on the property rather than sold, so neither is valued and the
    // Valuation-before-Consent gate does not apply to them.
    //
    // A consent type absent from this list skips the gate entirely, and its
    // workflow strip reports Valuation as "not required" rather than pending.
    'valuation_required_for' => ['Assignment', 'ST Assignment'],

    // The instruments that may only be registered against a consent, mapped to
    // the consent type that authorises each. An instrument absent from this
    // list is not gated at all.
    'consent_instruments' => [
        'Deed of Assignment' => 'Assignment',
        'Deed of Gift' => 'Gift',
        'Deed of Mortgage' => 'Mortgage',
        'Tripartite Mortgage' => 'Mortgage',
    ],

    // Consent types that may stand in for one another. An Assignment consent
    // backs a Deed of Gift and the reverse, the way the capture screen already
    // treats them; a Mortgage consent stands only for a mortgage.
    'consent_groups' => [
        'Assignment' => ['Assignment', 'Gift', 'ST Assignment'],
        'Gift' => ['Gift', 'Assignment', 'ST Assignment'],
        'Mortgage' => ['Mortgage'],
    ],

];
