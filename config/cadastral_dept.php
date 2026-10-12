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
| Registration is not a step: the Registry intake form opens the case and sends
| it straight to D Cad, so every workflow starts at dc_review.
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

$p1Review = fn (string $notRecommended) => [
    'dc_review' => [
        'phase' => 1, 'desk' => 'director_cadastral', 'label' => 'Recommend or not recommend',
        'question' => 'Recommend this file?', 'menu_label' => 'Recommendation',
        'outcomes' => [
            'recommended' => ['label' => 'Recommend', 'next' => 'ddc_review'],
            'not_recommended' => ['label' => $notRecommended, 'next' => 'registry_return', 'reason' => true],
        ],
    ],
    // DD Cad's refusal goes back to D Cad with the reason, and D Cad decides again.
    'ddc_review' => [
        'phase' => 1, 'desk' => 'deputy_director_cadastral', 'label' => 'Recommend or not recommend',
        'question' => 'Recommend this file?', 'menu_label' => 'Recommendation',
        'outcomes' => [
            'recommended' => ['label' => 'Recommend', 'next' => 'info_commission'],
            'not_recommended' => ['label' => $notRecommended . ' (back to D Cad)', 'next' => 'dc_review', 'reason' => true],
        ],
    ],
    'registry_return' => [
        'phase' => 1, 'desk' => 'registry', 'label' => 'Send back to source',
        'end' => 'returned',
    ],
];

// Planning-advice return, Land Department round trip, and the way back to
// AD P&D (shared by Conversion and both Direct sub-types).
$landRoundTrip = fn (string $afterDdc) => [
    'dc_after_advice' => [
        'phase' => 3, 'desk' => 'director_cadastral', 'label' => 'Send to Cadastral Information',
        'next' => 'info_update_fmc',
    ],
    'info_update_fmc' => [
        'phase' => 3, 'desk' => 'ad_information', 'label' => 'Planning Advice details on the FMC; send to Registry',
        'next' => 'registry_to_land',
    ],
    'registry_to_land' => [
        'phase' => 4, 'desk' => 'registry', 'label' => 'Detach correspondence file, send main file to Director Land',
        'tasks' => ['correspondence_detached' => 'Correspondence file detached and archived'],
        'next' => 'land_processing',
    ],
    'land_processing' => [
        'phase' => 4, 'desk' => 'director_land', 'label' => 'Recommendation and R of O (Land Department)',
        'external' => true, 'recorded_by' => 'registry',
        'next' => 'registry_receive_back',
    ],
    'registry_receive_back' => [
        'phase' => 4, 'desk' => 'registry', 'label' => 'Receive main file back, attach correspondence file',
        'tasks' => ['correspondence_attached' => 'Correspondence file attached'],
        'next' => 'dc_survey',
    ],
    'dc_survey' => [
        'phase' => 5, 'desk' => 'director_cadastral', 'label' => 'Send to DD Cad',
        'next' => 'ddc_survey',
    ],
    'ddc_survey' => [
        'phase' => 5, 'desk' => 'deputy_director_cadastral', 'label' => 'Send to AD P&D',
        'next' => $afterDdc,
    ],
];

// Government acquisition check (Conversion and Resettlement). A "yes" rejects
// the file, which goes back the way it came: Registry, then the source.
$compensation = fn (string $afterDsg) => [
    'compensation_check' => [
        'phase' => 3, 'desk' => 'compensation_unit', 'label' => 'Government acquisition check',
        'question' => 'Land falls on Government acquisition?', 'menu_label' => 'Acquisition check',
        'external' => true, 'recorded_by' => 'director_cadastral',
        'outcomes' => [
            'no' => ['label' => 'No: send to DSG', 'next' => 'dsg_review'],
            'yes' => ['label' => 'Yes: rejected, send back', 'next' => 'registry_return', 'reason' => true],
        ],
    ],
    'dsg_review' => [
        'phase' => 3, 'desk' => 'dsg', 'label' => 'DSG sends the file back to D Cad',
        'external' => true, 'recorded_by' => 'director_cadastral',
        'next' => $afterDsg,
    ],
];

// Billing and dispatch to Deeds (shared tail of Conversion and Direct).
$billingTail = [
    'pnd_billing' => [
        'phase' => 5, 'desk' => 'ad_pnd', 'label' => 'Generate the Fees P&D bill (no payment)',
        'tasks' => ['bill_generated' => 'Fees, Plan and Description bill generated'],
        'next' => 'ad_cadastral_review',
    ],
    'ad_cadastral_review' => [
        'phase' => 5, 'desk' => 'ad_cadastral', 'label' => 'Send to Registry',
        'next' => 'registry_dispatch',
    ],
    'registry_dispatch' => [
        'phase' => 5, 'desk' => 'registry', 'label' => 'Detach correspondence file, send main file to Deeds',
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
    */
    'desks' => [
        'registry' => ['label' => 'Cadastral Registry', 'short' => 'Registry', 'internal' => true],
        'director_cadastral' => ['label' => 'Director Cadastral', 'short' => 'D Cad', 'internal' => true],
        'deputy_director_cadastral' => ['label' => 'Deputy Director Cadastral', 'short' => 'DD Cad', 'internal' => true],
        'ad_information' => ['label' => 'Asst. Director Cadastral Information', 'short' => 'AD Cad Info', 'internal' => true],
        'ad_report' => ['label' => 'Asst. Director Cadastral Report', 'short' => 'AD Cad Report', 'internal' => true],
        'ad_pnd' => ['label' => 'Asst. Director Plans and Description', 'short' => 'AD P&D', 'internal' => true],
        // A separate officer who receives the billed file and passes it to Registry (user's default, 2026-10-10).
        'ad_cadastral' => ['label' => 'Asst. Director Cadastral', 'short' => 'AD Cad', 'internal' => true],

        'compensation_unit' => ['label' => 'Compensation Unit (Survey Dept)', 'short' => 'Compensation', 'internal' => false],
        'dsg' => ['label' => 'Deputy Director Survey (DSG)', 'short' => 'DSG', 'internal' => false],
        'kamma' => ['label' => 'KAMMA/Physical Planning', 'short' => 'KAMMA/Physical Planning', 'internal' => false],
        'physical_planning' => ['label' => 'Physical Planning Department', 'short' => 'Physical Planning', 'internal' => false],
        'permanent_secretary' => ['label' => 'Permanent Secretary', 'short' => 'PS', 'internal' => false],
        'director_land' => ['label' => 'Director Land (Land Dept)', 'short' => 'Land', 'internal' => false],
        'field_survey' => ['label' => 'Field Survey', 'short' => 'Field', 'internal' => false],
    ],

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
        // Registry is one link too (user, 2026-10-12): Attach Land File, Detach Correspondence
        // and Send to Deeds are row-menu actions on the files that need them, not sidebar lists.
        'registry' => ['group' => 'Registry', 'entries' => []],
        // D Cad and DD Cad are one link each (user, 2026-10-11): the table's Status column
        // shows what each file needs, and the row menu groups a decision into one item.
        'director_cadastral' => ['group' => 'D Cad', 'entries' => []],
        'deputy_director_cadastral' => ['group' => 'DD Cad', 'entries' => []],
        'ad_information' => ['group' => 'Cadastral Information', 'entries' => [
            // 'all' => ['label' => 'All files at AD Cad Info'],   // hidden from the sidebar for now (user, 2026-10-12)
            'fmc' => ['label' => 'Commission FMC', 'tasks' => ['fmc_commissioned']],
            'first_charting' => ['label' => '1st Charting', 'tasks' => ['chart_first']],
            // 'send' => ['label' => 'Send to Report', 'to' => ['ad_report']],   // hidden from the sidebar for now (user, 2026-10-12)
        ]],
        'ad_report' => ['group' => 'Cadastral Report', 'entries' => [
            'all' => ['label' => 'All files at AD Cad Report'],
            'charting' => ['label' => 'Charting', 'tasks' => ['charted', 'chart_second', 'charting_checked']],
            'correspondence' => ['label' => 'Commission Correspondence File', 'tasks' => ['correspondence_created']],
            'send' => ['label' => 'Send', 'ready' => true],
        ]],
        'ad_pnd' => ['group' => 'Plans & Descriptions', 'entries' => [
            'all' => ['label' => 'All files at AD P&D'],
            'field_jacket' => ['label' => 'Commission Field Jacket', 'tasks' => ['field_jacket']],
            'its' => ['label' => 'Assign to Surveyor (I to S)', 'tasks' => ['its']],
            'bill' => ['label' => 'Fees, Plan & Description', 'tasks' => ['bill_generated']],
            'send' => ['label' => 'Send', 'ready' => true],
        ]],
        'ad_cadastral' => ['group' => 'AD Cad', 'entries' => []],
    ],

    /*
    | File types offered at intake, per application type. Verification can be on
    | any file type. Under Direct Allocation only OP asks for a sub-type; every other
    | Direct file takes the Direct route (user's answer, 2026-10-10). `tenure`
    | preselects Statutory / Customary when the file is registered for Verification.
    */
    'file_types' => [
        'conversion_file' => ['label' => 'Conversion File', 'workflows' => ['conversion', 'verification'], 'tenure' => 'customary'],
        'quarry' => ['label' => 'Quarry File', 'workflows' => ['conversion', 'verification'], 'tenure' => 'customary'],
        'sit' => ['label' => 'SIT File', 'workflows' => ['conversion', 'verification'], 'tenure' => 'customary'],
        'gkn' => ['label' => 'GKN File', 'workflows' => ['direct', 'verification'], 'tenure' => 'statutory'],
        'misc_kn' => ['label' => 'Misc KN File', 'workflows' => ['direct', 'verification'], 'tenure' => 'statutory'],
        'op' => ['label' => 'OP File', 'workflows' => ['direct', 'verification'], 'asks_sub_type' => true, 'tenure' => 'statutory'],
        'dciv' => ['label' => 'DCIV File', 'workflows' => ['conversion', 'direct', 'verification']],
        'lpcc' => ['label' => 'LPCC File', 'workflows' => ['conversion', 'direct', 'verification']],
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
        'conversion' => [
            'label' => 'Conversion',
            'short' => 'Conversion',
            'applies_to' => 'Conversion, Quarry, SIT, and DCIV/LPCC files under Conversion',
            'colour' => 'yellow',
            'phases' => [
                1 => 'Initial Reception & Recommendation',
                2 => 'File Commissioning & Charting',
                3 => 'Acquisition Check & Planning Advice',
                4 => 'Land Department Processing & Return',
                5 => 'Survey Instruction & Billing',
            ],
            'start' => 'dc_review',
            'steps' => $p1Review('Not recommended') + [
                'info_commission' => [
                    'phase' => 2, 'desk' => 'ad_information', 'label' => 'Commission the File Movement Card (FMC)',
                    'tasks' => ['fmc_commissioned' => 'FMC commissioned'],
                    'next' => 'report_charting',
                ],
                'report_charting' => [
                    'phase' => 2, 'desk' => 'ad_report', 'label' => 'Charting, Land 12, Yes/No form, correspondence file',
                    'tasks' => [
                        'charted' => 'Charting card done (sheet charting is outside KLAES)',
                        'land12' => 'Land 12 and Yes/No form',
                        'correspondence_created' => 'Correspondence file created',
                    ],
                    'next' => 'compensation_check',
                ],
            ] + $compensation('dc_buffer') + [
                'dc_buffer' => [
                    'phase' => 3, 'desk' => 'director_cadastral', 'label' => 'Route for Planning Advice (buffer on GIS)',
                    'question' => 'Within the 10 km buffer from the town centre?', 'menu_label' => 'Planning Advice route',
                    'outcomes' => [
                        'inside_10km' => ['label' => 'Yes: send to KAMMA/Physical Planning', 'next' => 'kamma_advice'],
                        'outside_10km' => ['label' => 'No: send to Physical Planning', 'next' => 'ppd_advice'],
                    ],
                ],
                'kamma_advice' => [
                    'phase' => 3, 'desk' => 'kamma', 'label' => 'Planning Advice (KAMMA/Physical Planning)',
                    'external' => true, 'recorded_by' => 'director_cadastral',
                    'next' => 'perm_sec_review',
                ],
                'ppd_advice' => [
                    'phase' => 3, 'desk' => 'physical_planning', 'label' => 'Planning Advice (Physical Planning)',
                    'external' => true, 'recorded_by' => 'director_cadastral',
                    'next' => 'perm_sec_review',
                ],
                'perm_sec_review' => [
                    'phase' => 3, 'desk' => 'permanent_secretary', 'label' => 'PS reviews the Planning Advice',
                    'external' => true, 'recorded_by' => 'director_cadastral',
                    'next' => 'dc_after_advice',
                ],
            ] + $landRoundTrip('pnd_instruction') + [
                'pnd_instruction' => [
                    'phase' => 5, 'desk' => 'ad_pnd', 'label' => 'Commission Field Jacket, issue I to S',
                    'tasks' => [
                        'field_jacket' => 'Field jacket commissioned (job and surveyor attached)',
                        'its' => 'Instruction to Survey (I to S) issued',
                    ],
                    'next' => 'field_survey',
                ],
                'field_survey' => [
                    'phase' => 5, 'desk' => 'field_survey', 'label' => 'Survey in the field',
                    'external' => true, 'recorded_by' => 'ad_pnd',
                    'next' => 'pnd_billing',
                ],
            ] + $billingTail,
        ],

        /* ------------------------- 2. Direct Allocation ------------------------- */
        // Client's list (2026-10-11): Direct Allocation, split into Resettlement and Direct.
        'direct' => [
            'label' => 'Direct Allocation',
            'short' => 'Direct',
            'applies_to' => 'GKN, Misc KN, OP, and DCIV/LPCC files: Resettlement or Direct',
            'colour' => 'blue',
            // OP files choose; every other file takes the Direct route.
            'sub_type_label' => 'Route',
            'sub_types' => [
                'resettlement' => 'Resettlement',
                'direct_allocation' => 'Direct',
            ],
            'default_sub_type' => 'direct_allocation',
            'phases' => [
                1 => 'Initial Reception & Recommendation',
                2 => 'File Commissioning & Charting',
                3 => 'Acquisition / Genuineness Check',
                4 => 'Land Department Processing & Return',
                5 => 'Survey Plan & Billing',
            ],
            'start' => 'dc_review',
            'steps' => $p1Review('Not recommended') + [
                'info_commission' => [
                    'phase' => 2, 'desk' => 'ad_information', 'label' => 'Commission FMC, check layout plan, 1st charting',
                    'tasks' => [
                        'fmc_commissioned' => 'FMC commissioned',
                        'layout_checked' => 'Layout plan checked against the allocation paper',
                        'chart_first' => '1st charting done',
                    ],
                    'next' => 'report_charting',
                ],
                'report_charting' => [
                    'phase' => 2, 'desk' => 'ad_report', 'label' => '2nd charting, Land 12, Yes/No form, correspondence file',
                    'tasks' => [
                        'chart_second' => '2nd charting done',
                        'land12' => 'Land 12 and Yes/No form',
                        'correspondence_created' => 'Correspondence file created',
                    ],
                    'route_by' => 'sub_type',
                    'routes' => [
                        'resettlement' => 'compensation_check',
                        'direct_allocation' => 'land_genuineness',
                    ],
                ],
            ]
            // 3A Resettlement
            + $compensation('dc_after_advice') + [
                // 3B Direct Allocation
                'land_genuineness' => [
                    'phase' => 3, 'desk' => 'director_land', 'label' => 'Land checks the application is genuine',
                    'external' => true, 'recorded_by' => 'director_cadastral',
                    'next' => 'dc_after_advice',
                ],
            ] + $landRoundTrip('pnd_plan') + [
                // The Record Section attaches the survey plan; AD P&D ticks it (user's default, 2026-10-10).
                'pnd_plan' => [
                    'phase' => 5, 'desk' => 'ad_pnd', 'label' => 'Survey plan attached by the Record Section',
                    'tasks' => ['survey_plan_attached' => 'Survey plan attached (Record Section)'],
                    'next' => 'pnd_billing',
                ],
            ] + $billingTail,
        ],

        /* ------------------------- 3. Verification ------------------------- */
        'verification' => [
            'label' => 'Verification',
            'short' => 'Verification',
            'applies_to' => 'Statutory or Customary: any file type that already has a correspondence file',
            'colour' => 'green',
            // Client's list (2026-10-11): Verification is Statutory or Customary. Asked for every
            // file at intake (preselected from the file type); the route is the same for both.
            'asks_sub_type' => true,
            'sub_type_label' => 'Right of occupancy',
            'sub_types' => [
                'statutory' => 'Statutory',
                'customary' => 'Customary',
            ],
            'phases' => [
                1 => 'Initial Reception & Recommendation',
                2 => 'Charting Verification',
                3 => 'File Return',
            ],
            'start' => 'dc_review',
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
