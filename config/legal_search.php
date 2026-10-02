<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Online Legal Search — request approval
    |--------------------------------------------------------------------------
    |
    | A public Online Legal Search no longer releases its report on payment.
    | Payment opens a request that a Director or Deputy Director must approve;
    | the report is then emailed to the requester as a PDF.
    |
    */

    'online_approval' => [

        /*
        | Exact `users.rank` values that may review Online Legal Search requests.
        | Ranks are free text on the users table, so keep this list in step with
        | what is actually stored (currently: Director Deeds, Director Land,
        | Permanent Secretary).
        */
        'approver_ranks' => [
            'Director Deeds',
            'Director Land',
            'Deputy Director Deeds',
            'Deputy Director Land',
        ],

        /*
        | Anything whose rank *starts with* one of these prefixes also qualifies.
        | Covers new Director / Deputy Director ranks added later without needing
        | a code change (e.g. "Deputy Director Legal").
        */
        'approver_rank_prefixes' => [
            'Director',
            'Deputy Director',
        ],

        /*
        | Ranks that are never approvers even though they match a prefix above.
        */
        'excluded_ranks' => [],

        /*
        | Super admins can always review, so the queue is never stuck when no
        | Director account is configured.
        */
        'allow_super_admin' => true,
    ],

];
