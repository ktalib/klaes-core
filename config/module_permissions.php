<?php

/*
| Module-level permissions.
|
| "Modules" are the rows of user_roles — named menu areas, not job roles. A user holds a
| CSV of their names in users.assign_role; this config maps ROUTES onto those modules so
| one middleware can enforce create/edit/delete/print/export across the app.
|
| Before this existed there was no route-level authorization anywhere: 0 uses of `can:`
| middleware across ~1,040 mutating routes. Enforcement was the sidebar hiding links, which
| a typed URL walks straight past.
*/

return [

    /*
    | Enforce at all? Master switch — false makes the middleware a no-op, for bisecting a
    | production problem without a deploy.
    */
    'enabled' => env('MODULE_PERMISSIONS_ENABLED', true),

    /*
    | STRICT ROUTES — what to do with a route this file does not map.
    |
    | false (rollout): unmapped routes pass through and are logged once each, so the map is
    | completed from real traffic instead of guesswork. 1,982 named routes exist; hand-mapping
    | them blind and shipping that to a live registry would take the ministry offline.
    |
    | true (steady state): an unmapped MUTATING route is denied. Flip this only once
    | `php artisan permissions:unmapped` has gone quiet.
    */
    'strict_routes' => env('MODULE_PERMISSIONS_STRICT_ROUTES', false),

    /*
    | STRICT ACTIONS — what a missing module_permissions row means for a module the user
    | CAN see.
    |
    | false (rollout): allow everything except delete. Without this, deploying before
    | `permissions:backfill` runs would strip create/edit/print from 596 of 597 users at
    | once, on a database with no backups.
    |
    | true (steady state): a missing row denies. Flip after the backfill reconciles.
    */
    'strict_actions' => env('MODULE_PERMISSIONS_STRICT_ACTIONS', false),

    /*
    | WHICH ACTIONS THE ROUTE MIDDLEWARE ACTUALLY ENFORCES.
    |
    | Only 'delete' to begin with, and that is a deliberate retreat.
    |
    | The map files a route under a module by its NAME prefix, which is wrong whenever an
    | endpoint is called from somewhere else. property-records.storeFromIndexing lives under
    | property-records.* and so counts as "Deeds - Property Records", but it is posted from
    | the File Indexing screen by officers who hold Indexing and have no reason to hold Deeds.
    | The gate refused them. propertycard.search did the same to anyone opening the Property
    | Transaction History card from File Indexing.
    |
    | Both are map bugs, not permission decisions, and there is no way to be sure how many
    | more there are until the denial log has seen real traffic. So create/edit/print/export
    | are recorded and allowed through, while delete -- which no route borrows across modules,
    | and which destroys registry records -- is enforced from the start.
    |
    | Add actions here as `permissions:denials` goes quiet for them. The blade guards are
    | unaffected: buttons stay hidden where the permission is absent either way.
    */
    'enforced_actions' => explode(',', (string) env('MODULE_PERMISSIONS_ENFORCED_ACTIONS', 'delete')),

    /*
    | Routes that are never gated, whatever else matches. Auth, the profile screens a locked
    | account needs, and the notification/heartbeat endpoints every page polls.
    */
    'exempt_routes' => [
        'login',
        'logout',
        'register',
        'home',
        'dashboard',
        'password.*',
        'verification.*',
        'profile.*',
        'markWelcomePopupShown',
        'notifications.*',
        'session-lock.*',
        'two-factor.*',
        'otp.*',
        // Phone verification and the theme toggle are account-level, not module-level:
        // RequirePhoneVerification already gates the first, and the second is a display
        // preference every signed-in user sets for themselves.
        'phone.*',
        'theme.*',
        'impersonate*',
    ],

    /*
    | URI prefixes that are never gated — the public portals run on their own guards and are
    | not "the system" this gate is about. Mirrors RequireProfilePhoto::isAllowed().
    */
    'exempt_paths' => [
        'laas', 'laas/*',
        'phs', 'phs/*',
        'online-legal-search', 'online-legal-search/*',
        'api/*',
        'livewire/*',
        'debug/*',
    ],

    /*
    | ROUTE NAME PATTERN => MODULE NAME
    |
    | First match wins, so list a specific pattern before the prefix it sits under. The module
    | name must be spelled as user_roles spells it; App\Support\Permissions\ModuleName
    | normalizes dashes, spacing and case, so "ST – X" and "ST - x" both match.
    |
    | Seeded from resources/views/admin/menu/partials/modules/*.blade.php, which already pairs
    | every module with its routes inside @if($hasRole('...')) — that is the authoritative
    | mapping and it was already written; this is it extracted as data.
    */
    'modules' => [

        // ---- User, role and department administration (the escalation surface) ----------
        'users.*' => 'User Account',
        'user-roles.*' => 'User Roles',
        'departments.*' => 'Departments',
        'configurable-entries.*' => 'System Settings',
        'sms-control.*' => 'System Settings',
        'sms-management.*' => 'System Settings',
        'user-activity-logs.*' => 'Activity Logs',
        'activity-monitoring.*' => 'Activity Monitoring',

        // ---- Lands ---------------------------------------------------------------------
        'mls-fileno.*' => 'Lands - Manage MLSFileNo',
        'file-numbers.*' => 'Lands - Generate New FileNo (MLSFileNo)',
        'file-decommissioning.*' => 'Lands - File Decommissioning',
        'land-registration.*' => 'Land Registration',
        'land-recommendations.*' => 'Letter of Grant (RofO)',
        'allocation-list.*' => 'Allocation List',
        'land-rofo.*' => 'Letter of Grant (RofO)',

        // ---- Deeds ---------------------------------------------------------------------
        'instrument_registration.*' => 'Deeds Registration',
        'instruments.*' => 'Deeds Registration',
        'caveat.*' => 'Caveat',
        'property-records.*' => 'Deeds - Property Records',
        'propertycard.*' => 'Deeds - Property Records',
        'legalsearch.*' => 'Deeds - Legal Search Reports',
        'legal_search.*' => 'Deeds - Legal Search Reports',

        // ---- EDMS / indexing / scanning -------------------------------------------------
        'fileindexing.*' => 'Indexing',
        'pagetyping.*' => 'Document Page Types',
        'ptq-control.*' => 'PT Quality Control',
        'scanning.*' => 'Scanning',
        'blind-scanning.*' => 'Blind Scanning',
        'edms.*' => 'EDMS Update',
        'filearchive.*' => 'File Digital Library - Doc-WARE',

        // ---- File tracking and requests -------------------------------------------------
        'filetracker.*' => 'File Tracker/Tracking',
        'create-file-tracker.*' => 'File Tracker/Tracking',
        'digital-request.*' => 'Log a File',
        'file-search.*' => 'File Search',

        // ---- Print / label surfaces ------------------------------------------------------
        'cadastral_printlabel.*' => 'Print File Labels',
        'printlabel.*' => 'Print File Labels',
        'dciv-printlabel.*' => 'Print File Labels',

        // ---- Survey, Cadastral, GIS -----------------------------------------------------
        'survey_record.*' => 'Survey - Records',
        'survey-module.*' => 'Survey - Records',
        'gkn-generation.*' => 'Generate New FileNo (GKNFileNo)',

        /*
         | The Cadastral Module (Registry / Report / Information / Plan &
         | Description) maps onto the EXISTING Cad - Records role rather than a
         | new module name. The backfill is complete -- 537/537 users, 3,347
         | grant rows -- so Cad - Records holders already have a grant row and
         | can act from the first request. A brand-new module name would appear
         | in nobody's assign_role, and view is read from assign_role alone, so
         | it would be invisible to every non-super-admin until someone edited
         | hundreds of users.
         |
         | Above cadastral.* for readability only: Str::is('cadastral.*',
         | 'cadastral-module.x') is false, so neither swallows the other. It does
         | guard against a future 'cadastral*' being written here.
        */
        'cadastral-module.*' => 'Cad - Records',
        'cadastral.*' => 'Cad - Records',
        'gisedms.*' => 'GIS - Records',

        // ---- Programmes, Sectional Titling, SLTR, Physical Planning ----------------------
        /*
         | ST Certificate screens live under the programmes.* prefix but belong to ST, not
         | Physical Planning. They MUST stay above the programmes.* line: first match wins,
         | so the broad entry below would otherwise swallow all of them.
         */
        'programmes.certificates' => 'ST - Certificate',
        'programmes.cofo_complete*' => 'ST - Certificate',
        'programmes.print_cofo*' => 'ST - Certificate',
        'programmes.view_cofo' => 'ST - Certificate',
        'programmes.generate_cofo' => 'ST - Certificate',
        'programmes.save_cofo' => 'ST - Certificate',
        'programmes.rofo*' => 'ST - Certificate',
        'programmes.white_copy_rofo' => 'ST - Certificate',

        'programmes.*' => 'PHYSICAL PLANNING',
        'planning-recommendation.*' => 'Planning Recommendation',
        'sectionaltitling.*' => 'ST - Applications',
        'stmemo.*' => 'ST - ST Memo',
        'sltr-recommendations.*' => 'SLTR - Approvals',
        // SLTR CofO Workflow: one module per screen. The Front Page lines MUST stay above
        // the sltr-cofo.* catch-all — first match wins.
        'sltr-cofo.front-page*' => 'SLTR - CofO Front Page',
        'sltr-cofo.generate' => 'SLTR - CofO Front Page',
        'sltr-cofo.save' => 'SLTR - CofO Front Page',
        'sltr-cofo.*' => 'SLTR - CofO',
        'recertification.*' => 'Recertification',

        // ---- Revenue --------------------------------------------------------------------
        'bill.*' => 'Billing',
        'final-bill.*' => 'Billing',
        'betterment-bill.*' => 'Billing',
        'receipt.*' => 'Generate Receipt',

        // ---- Payroll / attendance --------------------------------------------------------
        'payroll.*' => 'Payroll',
        'attendance.*' => 'Attendance Tracking',
    ],

    /*
    | ROUTE NAME => ACTION, where the name-and-verb inference below gets it wrong.
    |
    | The classic case is a destructive POST whose name says nothing (805 POST routes against
    | 131 DELETE routes here, so plenty of deletes arrive as POSTs).
    */
    'overrides' => [
        // Reads like a lookup, updates rows (InstrumentController::resolveOpDuplicates
        // writes at ~line 2836). Named here rather than trusted to a pattern.
        'instruments.resolveOpDuplicates' => 'edit',

        // Listing screens whose NAME contains "certificate", which the print pattern below
        // would otherwise claim. These open a page; they do not print anything.
        'programmes.certificates' => 'view',
        'programmes.cofo_complete' => 'view',
        'programmes.view_cofo' => 'view',
        // Attaching the TDP amends an existing certificate rather than creating a record,
        // and StCofoTdpController checks 'edit' -- the two must not disagree.
        'programmes.cofo_complete.tdp.store' => 'edit',

        // SLTR CofO Workflow, matching SltrCofoController's own checks. The two screens are
        // plain listings; the front page form fills fields on an existing registered
        // capture (edit, not the create the *generate* pattern would infer); attaching a
        // TDP amends a certificate, as on ST.
        'sltr-cofo.index' => 'view',
        'sltr-cofo.front-page' => 'view',
        'sltr-cofo.generate' => 'edit',
        'sltr-cofo.tdp' => 'edit',

        'users.suspend' => 'edit',
        'users.unsuspend' => 'edit',
        'user-roles.bulk-delete' => 'delete',
        'role.bulk-delete' => 'delete',
    ],

    /*
    | ACTION INFERENCE, applied to the route name in this order; first hit wins. Falls back
    | to the HTTP verb when nothing matches.
    */
    'action_patterns' => [
        /*
         | READ-ONLY NAMES FIRST, whatever the verb.
         |
         | This app posts a great many things that only read: propertycard.search,
         | legalsearch.getRecord, edms.file-type.preview, digital-request.check-availability.
         | The verb fallback calls every POST a "create", so searching a property card asked
         | for create rights on Deeds - Property Records and refused officers who had no
         | business creating there. That reached production and blocked a live user.
         |
         | Anchored on a segment boundary (`.search`, `-lookup`) so these cannot swallow a
         | write: `.store`, `.save`, `.approve` and friends match nothing here. Anything
         | genuinely ambiguous is listed in `overrides` instead of guessed at --
         | instruments.resolveOpDuplicates reads like a lookup and updates rows.
        */
        'view' => [
            '*.search', '*-search', '*.search-*', '*-search-*',
            '*.navigate',
            '*.lookup', '*-lookup', '*.lookup-*',
            '*.preview', '*-preview',
            '*.fetch', '*.fetch-*',
            '*.get*',
            '*.check', '*.check-*',
            '*.details', '*-details',
            '*.availability', '*-availability',
            '*.filter', '*.filters', '*.options',
            '*.exists', '*-exists',
            '*.count', '*.counts', '*-count', '*-counts',
            '*.stats', '*.summary',
            '*.autocomplete*', '*.suggest*',
        ],

        /*
         | Approve/reject first, and matched EXACTLY rather than with a bare *approve*.
         |
         | A loose wildcard swallows names that are not approvals at all:
         | land-recommendations.approved-recommendation.show is a listing, and
         | ...approved-recommendation.destroy is a delete. Anchoring on `.approve`, `-approve`
         | and `approve-` keeps those out while still catching batch-approve,
         | approve-for-archiving and the bare `approve`/`reject` routes.
        */
        'approve' => [
            'approve', '*.approve', '*-approve', 'approve-*', '*.approve-*', '*-approve-*',
            'reject', '*.reject', '*-reject', 'reject-*', '*.reject-*', '*-reject-*',
        ],
        'delete' => ['*destroy*', '*delete*', '*remove*', '*discard*'],
        'export' => ['*export*', '*download*', '*.pdf', '*-pdf', '*csv*', '*excel*'],
        'print' => ['*print*', '*label*', '*acknowledgement*', '*certificate*'],
        'create' => ['*store*', '*create*', '*.add', '*-add', '*new*', '*generate*', '*initialize*'],
        'edit' => ['*update*', '*edit*', '*save*', '*assign*', '*mark*'],
    ],
];
