<?php

/*
|--------------------------------------------------------------------------
| Cadastral Department workflow
|--------------------------------------------------------------------------
| Source: "Cadastral Department Workflow" (user doc, 2026-10-09) as changed by
| the client walk-through of 2026-10-10 (docs/plans/CADASTRAL_DEPT_WORKFLOW.md,
| "Client brief" and "Answers, round 2"). Every flow is data here.
| App\Services\CadastralDept\WorkflowDefinition reads it, and WorkflowEngine
| moves a case along it. Change routing here, not in code;
| tests/Unit/CadastralDept/WorkflowDefinitionTest checks the graph.
|
| Registration opens the case at registry_intake, already sent to the Head of Registry
| (AD CAD Registry > Approval); only the head sends it on to D Cad (client, 2026-10-16:
| "how can the file be sent to D Cad from Registry without AD Registry approval").
|
| A step is one stop of the main file at one desk:
|   phase     phase number from the doc
|   desk      key of `desks`
|   label     what happens there
|   question  optional: the yes/no question a decision asks
|   tasks     checklist the desk completes before forwarding (key => label)
|   next      the single next step, OR
|   outcomes  a decision: outcome key => [label, next, reason?], OR
|   route_by  a case attribute (sub_type) whose value picks the next step from `routes`
|   end       terminal status: completed | returned
|   action_label  optional: the button text of an end step (default: its label)
|   checks    optional: DeedsDispatchChecks keys that must pass before the step's
|             action (fmc, charting, correspondence, bill, detached)
| An outcome with 'reason' => true cannot be chosen without a written reason.
|
| External desks are other departments. The file leaves Cadastral and comes
| back; `recorded_by` names the Cadastral desk that receives it back and
| records what happened. File Movement Card updates are automatic (every
| movement is logged), so no step has an "update the FMC" task.
*/

$p1Review = fn (string $notRecommended, bool $ddcBackToDcad = true) => [
    // The registered file waits for the Head of Registry, who sends it to D Cad (client, 2026-10-16).
    'registry_intake' => [
        'phase' => 1, 'desk' => 'registry', 'label' => 'Registered; Head of Registry approves and sends it to D Cad',
        'menu_label' => 'Send to D Cad',
        // Step 0 on the step bar, before the user's 28 steps from (1) D Cad (user, 2026-10-18).
        'step_zero' => true,
        'next' => 'dc_review',
    ],
    'dc_review' => [
        'phase' => 1, 'desk' => 'director_cadastral', 'label' => 'Recommend or not recommend',
        'question' => 'Recommend this file?', 'menu_label' => 'Recommend',
        'outcomes' => [
            'recommended' => ['label' => 'Recommend', 'next' => 'ddc_review'],
            'not_recommended' => ['label' => $notRecommended, 'next' => 'registry_return', 'reason' => true],
        ],
    ],
    // DD Cad's refusal goes back to D Cad with the reason, and D Cad decides again (Conversion,
    // Verification). Under Direct Allocation it returns straight to Registry, then the source
    // (updated workflow doc, 2026-10-15).
    'ddc_review' => [
        'phase' => 1, 'desk' => 'deputy_director_cadastral', 'label' => 'Recommend or not recommend',
        'question' => 'Recommend this file?', 'menu_label' => 'Recommend',
        'outcomes' => [
            'recommended' => ['label' => 'Recommend', 'next' => 'info_commission'],
            'not_recommended' => $ddcBackToDcad
                ? ['label' => $notRecommended . ' (back to D Cad)', 'next' => 'dc_review', 'reason' => true]
                : ['label' => $notRecommended . ' (back to Registry)', 'next' => 'registry_return', 'reason' => true],
        ],
    ],
    'registry_return' => [
        'phase' => 1, 'desk' => 'registry', 'label' => 'Send back to source',
        'end' => 'returned',
    ],
];

// Government acquisition check (Conversion and Resettlement). Yes or No, the file goes to
// DSG with Compensation's comment; DSG minutes it and sends it to D Cad, who sends a rejected
// file (Yes: falls on Government acquisition) back to Registry (user, 2026-10-16).
$compensation = [
    'compensation_check' => [
        'phase' => 2, 'desk' => 'compensation_unit', 'label' => 'Government acquisition check',
        'question' => 'Land falls on Government acquisition?', 'menu_label' => 'Acquisition check',
        'external' => true, 'recorded_by' => 'director_cadastral',
        'outcomes' => [
            'no' => ['label' => 'No: send to DSG', 'next' => 'dsg_review'],
            'yes' => ['label' => 'Yes: falls on acquisition, send to DSG', 'next' => 'dsg_review', 'reason' => true],
        ],
    ],
    'dsg_review' => [
        'phase' => 2, 'desk' => 'dsg', 'label' => 'DSG minutes the Compensation answer and sends the file to D Cad',
        'external' => true, 'recorded_by' => 'director_cadastral',
        'next' => 'dc_buffer',
    ],
];

// Steps 7 to 28 of the updated workflow doc (2026-10-15), shared by Conversion and both
// Direct sub-types: GIS buffer, Planning Advice, Land round trip, survey, examination,
// final charting, SG, billing and dispatch to Deeds.
$sharedTail = [
    // Phase 2 (end): D Cad receives the file and sends it to the GIS Unit.
    'dc_buffer' => [
        'phase' => 2, 'desk' => 'director_cadastral', 'label' => 'Receive the file; send to the Cadastral GIS Unit for the buffer analysis',
        'question' => 'Send the file on for the buffer analysis?', 'menu_label' => 'GIS Unit or return',
        'outcomes' => [
            'to_gis' => ['label' => 'Send to the GIS Unit', 'next' => 'gis_buffer'],
            'rejected' => ['label' => 'Rejected (Government acquisition): send back to Registry', 'next' => 'registry_return', 'reason' => true],
        ],
    ],
    // GIS staff do the check; the head of the GIS Unit sends the file to KAMMA or Physical Planning.
    'gis_buffer' => [
        'phase' => 2, 'desk' => 'cadastral_gis', 'label' => 'Buffer analysis: within 10 km of the city centre?',
        'question' => 'Within the 10 km buffer from the city centre?', 'menu_label' => 'Buffer analysis',
        'outcomes' => [
            'inside_10km' => ['label' => 'Inside 10 km: send to KAMMA', 'next' => 'kamma_advice'],
            'outside_10km' => ['label' => 'Outside 10 km: send to Physical Planning', 'next' => 'ppd_advice'],
        ],
    ],
    // Phase 3: Planning Advice.
    // The Planning Advice (with the field comments, on the JSI) is uploaded when the file comes
    // back, so the next desks can view or download it (transcript, 2026-10-16; PlanningAdvice).
    'kamma_advice' => [
        'phase' => 3, 'desk' => 'kamma', 'label' => 'Planning Advice (KAMMA/Physical Planning)',
        'external' => true, 'recorded_by' => 'director_cadastral',
        'tasks' => ['planning_advice_uploaded' => 'Planning Advice uploaded'],
        'next' => 'perm_sec_review',
    ],
    'ppd_advice' => [
        'phase' => 3, 'desk' => 'physical_planning', 'label' => 'Planning Advice (Physical Planning)',
        'external' => true, 'recorded_by' => 'director_cadastral',
        'tasks' => ['planning_advice_uploaded' => 'Planning Advice uploaded'],
        'next' => 'perm_sec_review',
    ],
    'perm_sec_review' => [
        'phase' => 3, 'desk' => 'permanent_secretary', 'label' => 'PS reviews the Planning Advice and passes the file back',
        'external' => true, 'recorded_by' => 'director_cadastral',
        'next' => 'dc_after_advice',
    ],
    'dc_after_advice' => [
        'phase' => 3, 'desk' => 'director_cadastral', 'label' => 'Receive the file from the PS; send to Cadastral Information',
        'next' => 'info_update_fmc',
    ],
    // Phase 4: Registry and Land Department.
    'info_update_fmc' => [
        'phase' => 4, 'desk' => 'ad_information', 'label' => 'Planning Advice details on the FMC; send to Registry',
        'next' => 'registry_to_land',
    ],
    'registry_to_land' => [
        'phase' => 4, 'desk' => 'registry', 'label' => 'Detach and archive correspondence file, send main file to Land',
        'label_no_correspondence' => 'Send main file to Land',
        // The main file's page count going out (recording, 2026-10-18; PageCounts).
        'page_count' => 'out',
        // The pages are counted before the correspondence file is detached (client, 2026-10-18).
        'tasks' => ['correspondence_detached' => 'Correspondence file detached and archived'],
        'next' => 'land_processing',
    ],
    'land_processing' => [
        'phase' => 4, 'desk' => 'director_land', 'label' => 'Recommendation and R of O (Land Department)',
        'external' => true, 'recorded_by' => 'registry',
        'next' => 'registry_receive_back',
    ],
    'registry_receive_back' => [
        'phase' => 4, 'desk' => 'registry', 'label' => 'Receive main file back, update DMS, attach correspondence file',
        'label_no_correspondence' => 'Receive main file back, update DMS',
        // The count coming back; more pages than went out triggers the DMS update.
        'page_count' => 'back',
        // In this order (client, 2026-10-18): count the pages, update DMS, then attach.
        'tasks' => [
            // The file came back with signed pages: scan them into EDMS and page-type them (transcript, 2026-10-16).
            'edms_updated' => 'DMS updated: new pages scanned and page-typed',
            'correspondence_attached' => 'Correspondence file attached',
        ],
        'next' => 'dc_survey',
    ],
    // Phase 5: Survey instruction and execution.
    'dc_survey' => [
        'phase' => 5, 'desk' => 'director_cadastral', 'label' => 'Receive the file from Registry; send to DD Cad',
        'next' => 'ddc_survey',
    ],
    'ddc_survey' => [
        'phase' => 5, 'desk' => 'deputy_director_cadastral', 'label' => 'Forward the file to AD P&D',
        'next' => 'pnd_instruction',
    ],
    'pnd_instruction' => [
        'phase' => 5, 'desk' => 'ad_pnd', 'label' => 'Commission the Field Jacket and I to S; send to Cadastral Information',
        // P&D only commissions a plain field jacket and a plain I to S (no job, no surveyor);
        // AD Cad Info attaches the job and surveyor (transcript, 2026-10-16).
        'tasks' => [
            'field_jacket' => 'Field Jacket commissioned',
            'its' => 'I to S issued (attach a job and a surveyor)',
        ],
        'next' => 'info_job_assign',
    ],
    'info_job_assign' => [
        'phase' => 5, 'desk' => 'ad_information',
        'label' => 'Issue Job No. (Job Book) and assign: Conversion to a Private Surveyor, Government to the Survey Dept; send to Survey',
        'tasks' => [
            'job_attached' => 'Job and surveyor attached to the field jacket',
            'job_its' => 'Instruction to Surveyor issued',
        ],
        'next' => 'field_survey',
    ],
    'field_survey' => [
        'phase' => 5, 'desk' => 'field_survey', 'label' => 'Survey in the field',
        'external' => true, 'recorded_by' => 'ad_information',
        'next' => 'info_to_examination',
    ],
    // Phase 6: Examination and final charting.
    'info_to_examination' => [
        'phase' => 6, 'desk' => 'ad_information', 'label' => 'Receive the file after survey; send to the Examination Unit',
        'next' => 'survey_examination',
    ],
    'survey_examination' => [
        'phase' => 6, 'desk' => 'examination_unit', 'label' => 'Examine the survey plan; send to AD Drawing & Charting',
        'external' => true, 'recorded_by' => 'ad_drawing',
        'next' => 'final_charting',
    ],
    'final_charting' => [
        'phase' => 6, 'desk' => 'ad_drawing', 'label' => 'Final Charting; send to the Surveyor General',
        'tasks' => ['final_charting' => 'Final charting done'],
        'next' => 'sg_signing',
    ],
    'sg_signing' => [
        'phase' => 6, 'desk' => 'surveyor_general', 'label' => 'SG signs the Final Survey Plan',
        'external' => true, 'recorded_by' => 'ad_information',
        'next' => 'info_after_sg',
    ],
    // Phase 7: Billing and final hand-off.
    'info_after_sg' => [
        'phase' => 7, 'desk' => 'ad_information', 'label' => 'Receive the signed plan; send to AD P&D',
        'next' => 'pnd_billing',
    ],
    'pnd_billing' => [
        'phase' => 7, 'desk' => 'ad_pnd', 'label' => 'Generate the Fees P&D bill (no payment); send to Cadastral Information',
        'tasks' => ['bill_generated' => 'Fees, Plan and Description bill generated'],
        'next' => 'info_sign_bill',
    ],
    // Doc step 27: AD Cad Info signs, D Cad forwards to Registry (one step, as the doc numbers it).
    'info_sign_bill' => [
        'phase' => 7, 'desk' => 'ad_information', 'label' => 'Sign the Fees P&D bill; send through D Cad to Registry',
        'next' => 'registry_dispatch',
    ],
    'registry_dispatch' => [
        'phase' => 7, 'desk' => 'registry', 'label' => 'Archive correspondence file, send main file to Deeds (Bill Balance)',
        'label_no_correspondence' => 'Send main file to Deeds (Bill Balance)',
        'tasks' => ['correspondence_detached' => 'Correspondence file detached and archived'],
        // Send to Deeds is the last action, after the detach, and checks the file is complete.
        'checks' => ['fmc', 'charting', 'correspondence', 'bill', 'detached'],
        'action_label' => 'Send to Deeds',
        'end' => 'completed',
    ],
];

return [

    /*
    | Desks. `internal` desks are Cadastral posts; the rest are other departments.
    | `short` is the short form the client uses on screen.
    | `head`: a unit whose staff do the work and whose head sends it across (transcript,
    | 2026-10-16). Staff get "Send to <head>"; only the head (the users holding the desk's
    | `post`, or Cad - Records / Supper Admin while the post is vacant) sends the file to
    | another unit or department. Directors (D Cad, DD Cad) send directly. See HeadApproval.
    */
    'desks' => [
        // `post`: the officers notified in-app when a file reaches the desk (user, 2026-10-14) are the
        // users holding this post in Configurable Entries > Cadastral > Officers (cadastral_officers).
        'registry' => ['label' => 'Cadastral Registry', 'short' => 'Registry', 'internal' => true, 'post' => 'CAD_REGISTRY', 'head' => 'Head of Registry'],
        'director_cadastral' => ['label' => 'Director Cadastral', 'short' => 'D Cad', 'internal' => true, 'post' => 'D_CAD'],
        'deputy_director_cadastral' => ['label' => 'Deputy Director Cadastral', 'short' => 'DD Cad', 'internal' => true, 'post' => 'DD_CAD'],
        'ad_information' => ['label' => 'Asst. Director Cadastral Information', 'short' => 'AD Cad Info', 'internal' => true, 'post' => 'AD_CAD_INFO', 'head' => 'AD Cad Info'],
        'ad_report' => ['label' => 'Asst. Director Cadastral Report', 'short' => 'AD Cad Report', 'internal' => true, 'post' => 'AD_CAD_REP', 'head' => 'AD Cad Report'],
        'ad_pnd' => ['label' => 'Asst. Director Plans and Description', 'short' => 'AD P&D', 'internal' => true, 'post' => 'AD_PSD', 'head' => 'AD P&D'],
        // No "AD Cadastral" desk: it is not a post; every AD heads a unit (user, 2026-10-16).
        // Final charting (updated workflow doc, 2026-10-15). No desk role yet: Cad - Records covers it.
        'ad_drawing' => ['label' => 'Asst. Director Drawing & Charting', 'short' => 'AD Drawing & Charting', 'internal' => true, 'post' => 'AD_DRAW_CHART', 'head' => 'AD Drawing & Charting'],
        // The Cadastral GIS Unit does the buffer analysis (transcript, 2026-10-16); its head is AD GIS (sidebar, 2026-10-16).
        'cadastral_gis' => ['label' => 'Cadastral GIS Unit', 'short' => 'GIS Unit', 'internal' => true, 'post' => 'CAD_GIS', 'head' => 'AD GIS'],

        'compensation_unit' => ['label' => 'Compensation Unit (Survey Dept)', 'short' => 'Compensation', 'internal' => false],
        'dsg' => ['label' => 'Deputy Director Survey (DSG)', 'short' => 'DSG', 'internal' => false],
        'kamma' => ['label' => 'KAMMA/Physical Planning', 'short' => 'KAMMA/Physical Planning', 'internal' => false],
        'physical_planning' => ['label' => 'Physical Planning Department', 'short' => 'Physical Planning', 'internal' => false],
        'permanent_secretary' => ['label' => 'Permanent Secretary', 'short' => 'PS', 'internal' => false],
        'director_land' => ['label' => 'Director Land (Land Dept)', 'short' => 'Land', 'internal' => false],
        'field_survey' => ['label' => 'Field Survey', 'short' => 'Survey', 'internal' => false],
        // Updated workflow doc (2026-10-15).
        'examination_unit' => ['label' => 'Examination Unit (Survey Dept)', 'short' => 'Examination', 'internal' => false],
        'surveyor_general' => ['label' => 'Surveyor General', 'short' => 'SG', 'internal' => false],
    ],

    /*
    | Desk roles (user, 2026-10-15). A user_roles module per internal desk, granted through
    | users.assign_role like any module. It shows the desk's sidebar entry, opens the desk
    | page, and allows the actions on files whose current step that desk acts on (an external
    | step counts for the desk that records its return). `department_role` covers every desk
    | (supervisors, and everyone who had the department before desk roles existed).
    | Rows: database/migrations/manual/2026_10_15_cadastral_desk_roles.sql.
    */
    'department_role' => 'Cad - Records',
    'desk_roles' => [
        'registry' => 'Cad - Registry',
        'director_cadastral' => 'Cad - D Cad',
        'deputy_director_cadastral' => 'Cad - DD Cad',
        'ad_information' => 'Cad - Information',
        'ad_report' => 'Cad - Report',
        'ad_pnd' => 'Cad - P&D',
        'cadastral_gis' => 'Cad - GIS',
        'ad_drawing' => 'Cad - Drawing & Charting',   // role row: manual/2026_10_15_cadastral_drawing_charting_role.sql (run 2026-10-21)
    ],
    'verification_role' => 'Cad - Verification',
    // Central Admin (CADASTRAL UPDATE, 2026-10-26): works every desk and stands in for every unit
    // head, from one table (cadastral-dept.central-admin), so a file never sticks with an absent
    // officer. Super Admin passes too. Role row: manual/2026_10_26_cadastral_central_admin_role.sql.
    'central_admin_role' => 'Cad - Central Admin',

    /*
    | Sidebar lists per desk (user's proposed sidebar, 2026-10-11). Each entry
    | opens the desk's inbox filtered to the files where that action is due now.
    | A file matches when ANY of its rules holds:
    |   steps: its current step is one of these
    |   tasks: its current step has one of these tasks still open
    |   to:    its row menu can send it to one of these desks
    |   at:    it is out with one of these desks (this desk records the return)
    |   ready: every task of its step is done, so it can be sent on
    | An entry with no rule lists every file at the desk. `group` is the
    | sidebar heading for the desk.
    */
    'queues' => [
        // Sidebar per the user's KLAES sidebar doc (KLAES_Land_Admin_Enterprise_System-16, 2026-10-15):
        // each unit ends with "AD CAD <unit> > Approval": files its staff sent to the head, waiting
        // for the head to send them across (rule `awaiting_head`, HeadApproval).
        // Attach Land File, Detach Correspondence and Send to Deeds stay row-menu actions.
        'registry' => ['group' => 'Registry', 'entries' => [
            'approval' => ['label' => 'Approval', 'awaiting_head' => true],
            // DMS Update (sidebar, 2026-10-20): files back from Land with new pages.
            'dms_scan' => ['label' => 'Scan More', 'dms' => 'scan'],
            'dms_pages' => ['label' => 'More Pages', 'dms' => 'type'],
        ]],
        // D Cad and DD Cad: "Recommend" (was "Approval", user 2026-10-18; sidebar, WhatsApp image 2026-10-16 5.38 PM), every file at
        // the desk, since each waits for the director's decision; the Status column shows which.
        'director_cadastral' => ['group' => 'D Cad', 'entries' => [
            'approval' => ['label' => 'Recommend'],
        ]],
        'deputy_director_cadastral' => ['group' => 'DD Cad', 'entries' => [
            'approval' => ['label' => 'Recommend'],
        ]],
        'ad_information' => ['group' => 'Cadastral Information', 'entries' => [
            // 'all' => ['label' => 'All files at AD Cad Info'],   // hidden from the sidebar for now (user, 2026-10-12)
            'fmc' => ['label' => 'File Movement Card (FMC)', 'tasks' => ['fmc_commissioned']],
            'first_charting' => ['label' => '1st Charting (Direct Allocation Files)', 'tasks' => ['chart_first']],
            // Doc step 19: AD Cad Info issues the Job No. and assigns the surveyor.
            'job_no' => ['label' => 'Assign to Surveyor (I to S)', 'tasks' => ['job_attached', 'job_its']],
            'approval' => ['label' => 'Approval', 'awaiting_head' => true],
        ]],
        'ad_report' => ['group' => 'Cadastral Report', 'entries' => [
            'all' => ['label' => 'All Reports'],
            // Charting split in two (user's corrected sidebar, 2026-10-12).
            'charting_first' => ['label' => '1st Charting (Conversion Files)', 'tasks' => ['charted']],
            'charting_second' => ['label' => '2nd Charting (Direct Allocation Files)', 'tasks' => ['chart_second']],
            'charting' => ['label' => 'Charting', 'tasks' => ['charted', 'chart_second', 'charting_checked']],
            'correspondence' => ['label' => 'Commission Correspondence File', 'tasks' => ['correspondence_created']],
            'send' => ['label' => 'Send', 'ready' => true],
            'approval' => ['label' => 'Approval', 'awaiting_head' => true],
        ]],
        'ad_pnd' => ['group' => 'Plans & Descriptions', 'entries' => [
            'all' => ['label' => 'All Files at P&D'],
            'field_jacket' => ['label' => 'Commission Field Jacket', 'tasks' => ['field_jacket']],
            'its' => ['label' => 'I to S', 'tasks' => ['its']],
            'bill' => ['label' => 'Fees, Plan and Description', 'tasks' => ['bill_generated']],
            'send' => ['label' => 'Send', 'ready' => true],
            'approval' => ['label' => 'Approval', 'awaiting_head' => true],
        ]],
        'ad_drawing' => ['group' => 'Drawing & Charting', 'entries' => [
            'final_charting' => ['label' => 'Final Charting', 'tasks' => ['final_charting']],
            'approval' => ['label' => 'Approval', 'awaiting_head' => true],
        ]],
        'cadastral_gis' => ['group' => 'GIS Unit', 'entries' => [
            'buffer' => ['label' => 'Buffer Analysis', 'steps' => ['gis_buffer']],
            'approval' => ['label' => 'Approval', 'awaiting_head' => true],
        ]],
    ],

    /*
    | File types offered at intake, per application type. Verification can be on
    | any file type; it first asks Conversion or Direct Allocation and then lists that
    | group's file types (the `workflows` each type belongs to). Per type (user's voice
    | note, 2026-10-20):
    |   `tenure`  Statutory / Customary under Verification: fixed, null = asked, false = not
    |             asked or stored (DCIV/LPCC, user 2026-10-20).
    |   `route`   Resettlement / Direct: 'direct_allocation' = fixed Direct, 'ask' = asked
    |             (OP only), null = none (Conversion File). Under Direct Allocation it is the
    |             file's sub-type; under Verification its allocation_route.
    */
    'file_types' => [
        // Conversion (Customary): Conversion File, Quarry, SIT and DCIV/LPCC. Direct Allocation
        // (Statutory): GKN, Misc KN, DCIV/LPCC and OP (user's sidebar doc, 2026-10-20).
        'conversion_file' => ['label' => 'Conversion File', 'workflows' => ['conversion', 'verification'], 'tenure' => 'customary', 'route' => null],
        'quarry' => ['label' => 'Quarry File', 'workflows' => ['conversion', 'verification'], 'tenure' => 'customary', 'route' => 'direct_allocation'],
        'sit' => ['label' => 'SIT File', 'workflows' => ['conversion', 'verification'], 'tenure' => 'statutory', 'route' => 'direct_allocation'],
        'dciv' => ['label' => 'DCIV/LPCC File', 'workflows' => ['conversion', 'direct', 'verification'], 'tenure' => false, 'route' => 'direct_allocation'],
        'gkn' => ['label' => 'GKN File', 'workflows' => ['direct', 'verification'], 'tenure' => 'statutory', 'route' => 'direct_allocation'],
        'misc_kn' => ['label' => 'Misc KN File', 'workflows' => ['direct', 'verification'], 'tenure' => 'statutory', 'route' => 'direct_allocation'],
        'op' => ['label' => 'OP File', 'workflows' => ['direct', 'verification'], 'asks_sub_type' => true, 'tenure' => 'statutory', 'route' => 'ask'],
        // LPCC is the same file type as DCIV (user, 2026-10-15): one DCIV/LPCC File option.
    ],

    /*
    | Where the main file came from. "Send back to source" returns it there.
    | `lookup` is the matching CadastralRegistryLookup intake source, used to
    | preselect the source from the picked file's registry.
    */
    'file_sources' => [
        'land' => ['label' => 'Land', 'lookup' => 'Land'],
        'dciv' => ['label' => 'DCIV', 'lookup' => 'DCIV'],
        'survey' => ['label' => 'Survey', 'lookup' => null],
        'sltr' => ['label' => 'SLTR', 'lookup' => 'SLTR'],
        'st' => ['label' => 'Sectional Titling', 'lookup' => 'ST'],
        'kangis' => ['label' => 'KANGIS', 'lookup' => 'KANGIS'],
        'other' => ['label' => 'Other', 'lookup' => null],
    ],

    'workflows' => [

        /* ------------------------- 1. Conversion ------------------------- */
        // Updated workflow doc (2026-10-15): 28 steps in 7 phases.
        'conversion' => [
            'label' => 'Conversion',
            'short' => 'Conversion',
            'applies_to' => 'Customary – Conversion Files, Quarry File, SIT File and DCIV/LPCC',
            'colour' => 'yellow',
            'icon' => 'repeat',
            'phases' => [
                1 => 'Initial Reception & Recommendation',
                2 => 'Acquisition Check & GIS Buffer',
                3 => 'Planning Advice',
                4 => 'Registry & Land Dept Processing',
                5 => 'Survey Instruction & Execution',
                6 => 'Survey Examination & Final Charting',
                7 => 'Billing & Final Handoff',
            ],
            'start' => 'registry_intake',
            'steps' => $p1Review('Not recommended') + [
                'info_commission' => [
                    'phase' => 1, 'desk' => 'ad_information', 'label' => 'Commission the File Movement Card (FMC)',
                    'tasks' => ['fmc_commissioned' => 'FMC commissioned'],
                    'next' => 'report_charting',
                ],
                'report_charting' => [
                    'phase' => 1, 'desk' => 'ad_report', 'label' => 'Charting, Land 12, Yes/No form, correspondence file',
                    'tasks' => [
                        'charted' => 'Charting card done (sheet charting is outside KLAES)',
                        // Separate lines (transcript, 2026-10-16): Land 12, then the Yes/No form.
                        'land12' => 'Land 12',
                        'yes_no' => 'Yes/No form',
                        'correspondence_created' => 'Correspondence file created',
                    ],
                    'next' => 'compensation_check',
                ],
            ] + $compensation + $sharedTail,
        ],

        /* ------------------------- 2. Direct Allocation ------------------------- */
        // Client's list (2026-10-11): Direct Allocation, split into Resettlement and Direct.
        // Updated workflow doc (2026-10-15): the Conversion sequence, diverging only in phase 2.
        'direct' => [
            'label' => 'Direct Allocation',
            'short' => 'Direct',
            'applies_to' => 'Statutory – Direct Allocation Files, GKN File, Misc KN File and DCIV/LPCC (if they fall under the purview of Direct Allocation): Resettlement or Direct Allocation',
            'colour' => 'blue',
            'icon' => 'map-pin',
            // Only OP files choose Resettlement or Direct; GKN, Misc KN and DCIV/LPCC are Direct
            // (user's voice note, 2026-10-20; file_types `route`).
            'sub_type_label' => 'Route',
            'sub_types' => [
                'resettlement' => 'Resettlement',
                'direct_allocation' => 'Direct',
            ],
            'default_sub_type' => 'direct_allocation',
            'phases' => [
                1 => 'Initial Reception & Recommendation',
                2 => 'Acquisition Check & Routing',
                3 => 'Planning Advice',
                4 => 'Registry & Land Dept Processing',
                5 => 'Survey Instruction & Execution',
                6 => 'Survey Examination & Final Charting',
                7 => 'Billing & Final Handoff',
            ],
            'start' => 'registry_intake',
            'steps' => $p1Review('Not recommended', false) + [
                'info_commission' => [
                    'phase' => 1, 'desk' => 'ad_information', 'label' => 'Commission FMC, check layout plan, 1st charting',
                    'tasks' => [
                        'fmc_commissioned' => 'FMC commissioned',
                        'layout_checked' => 'Layout plan checked against the allocation paper',
                        'chart_first' => '1st charting done',
                    ],
                    'next' => 'report_charting',
                ],
                'report_charting' => [
                    'phase' => 1, 'desk' => 'ad_report', 'label' => '2nd charting, Land 12, Yes/No form, correspondence file',
                    'tasks' => [
                        'chart_second' => '2nd charting done',
                        // Separate lines (transcript, 2026-10-16): Land 12, then the Yes/No form.
                        'land12' => 'Land 12',
                        'yes_no' => 'Yes/No form',
                        'correspondence_created' => 'Correspondence file created',
                    ],
                    'route_by' => 'sub_type',
                    'routes' => [
                        'resettlement' => 'compensation_check',
                        'direct_allocation' => 'land_genuineness',
                    ],
                ],
            ]
            // Resettlement: Compensation, DSG, then D Cad.
            + $compensation + [
                // Direct: Land checks the application is genuine, then D Cad.
                'land_genuineness' => [
                    'phase' => 2, 'desk' => 'director_land', 'label' => 'Land checks the application is genuine',
                    'external' => true, 'recorded_by' => 'director_cadastral',
                    'next' => 'dc_buffer',
                ],
            ] + $sharedTail,
        ],

        /* ------------------------- 3. Verification ------------------------- */
        'verification' => [
            'label' => 'Verification',
            'short' => 'Verification',
            'applies_to' => 'Statutory or Customary: any file type that already has a correspondence file',
            'colour' => 'red',   // red (user, 2026-10-15)
            'icon' => 'shield-check',
            // Client's list (2026-10-11): Verification is Statutory or Customary. Asked for every
            // file at intake (preselected from the file type); the route is the same for both.
            'asks_sub_type' => true,
            'sub_type_label' => 'Right of occupancy',
            'sub_types' => [
                'statutory' => 'Statutory',
                'customary' => 'Customary',
            ],
            // Also Resettlement or Direct (user, 2026-10-12), using Direct Allocation's
            // sub-types; stored in allocation_route. The route is the same for both.
            'asks_allocation_route' => 'direct',
            'phases' => [
                1 => 'Initial Reception & Recommendation',
                2 => 'Charting Verification',
                3 => 'File Return',
            ],
            'start' => 'registry_intake',
            'steps' => $p1Review('Not recommended for verification') + [
                'info_commission' => [
                    'phase' => 2, 'desk' => 'ad_information', 'label' => 'Commission FMC, check charting information',
                    'tasks' => [
                        'fmc_commissioned' => 'FMC commissioned',
                        'charting_checked' => 'Charting information checked',
                    ],
                    'next' => 'report_check',
                ],
                'report_check' => [
                    'phase' => 2, 'desk' => 'ad_report', 'label' => 'Check charting information',
                    'tasks' => ['charting_checked' => 'Charting information checked'],
                    'next' => 'ddc_after',
                ],
                'ddc_after' => [
                    'phase' => 2, 'desk' => 'deputy_director_cadastral', 'label' => 'Send to D Cad',
                    'next' => 'dc_after',
                ],
                'dc_after' => [
                    'phase' => 2, 'desk' => 'director_cadastral', 'label' => 'Send to Cadastral Information',
                    'next' => 'info_update_movement',
                ],
                'info_update_movement' => [
                    'phase' => 2, 'desk' => 'ad_information', 'label' => 'FMC up to date; send to Registry',
                    'next' => 'registry_return_requester',
                ],
                'registry_return_requester' => [
                    'phase' => 3, 'desk' => 'registry', 'label' => 'Detach correspondence file, return main file to requester',
                    'label_no_correspondence' => 'Return main file to requester',
                    'tasks' => ['correspondence_detached' => 'Correspondence file detached'],
                    'checks' => ['detached'],
                    'action_label' => 'Return main file to requester',
                    'end' => 'completed',
                ],
            ],
        ],
    ],

    /*
    | Case reference format. {Y} year, {N} zero-padded sequence per year.
    */
    'case_ref_format' => 'CAD-{Y}-{N}',
    'case_ref_pad' => 5,
];
