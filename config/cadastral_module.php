<?php

/*
|--------------------------------------------------------------------------
| Cadastral Module
|--------------------------------------------------------------------------
| Rates, formats and the report stage chains.
|
| Rates live here rather than in a table because the tables the rest of KLAES
| would have used for them -- land_use_rates, district_rates, tax_zones -- were
| never migrated (ConfigurableEntriesController references them and they do not
| exist). Every rate below is SNAPSHOTTED onto the cadastral_bills row when a
| bill is issued, following config/consent_bill.php's doctrine: a bill already
| saved keeps its own figures, so a reprint matches the copy the applicant
| holds. Changing a number here therefore affects new bills only.
*/

return [

    /*
    | File number prefixes. A "conversion" file is not charted -- it goes
    | straight to index-card commissioning (concept note 4.3a).
    |
    | Real file numbers in this database use hyphens (RES-1981-1). The concept
    | note's "RES/--/--" is notation, not format.
    */
    'file_numbers' => [
        'direct_prefixes'     => ['RES', 'COM', 'IND', 'AGR', 'AG', 'MIX'],
        'conversion_prefixes' => ['CON'],
        'source_registries'   => ['Land', 'SLTR', 'ST', 'DCIV', 'KANGIS', 'Deeds', 'Other'],
    ],

    /*
    | Survey job numbers.
    |
    | UNCONFIRMED FORMAT. Nobody in this codebase knows the SURCON-mandated
    | pattern; this placeholder is a KLAES-local serial. Confirm with the
    | Surveyor-General's office before go-live or numbers issued now will need
    | reissuing. cadastral-module:install warns while this is still the default.
    */
    'job_number' => [
        'format'         => 'KN/CAD/{year}/{serial}',
        'serial_pad'     => 4,
        'is_placeholder' => true,
    ],

    'its_number' => [
        'format'     => 'ITS/{year}/{serial}',
        'serial_pad' => 4,
    ],

    /*
    | Report stage chains. Verification has the extra Field Inspection step;
    | customary and statutory are the same chain without it (concept note 4.2).
    | required_post is matched against cadastral_officers.post_code.
    */
    'stage_chains' => [
        'verification' => [
            ['key' => 'registration',     'name' => 'Registration',           'post' => null],
            ['key' => 'field_inspection', 'name' => 'Field Inspection',       'post' => 'FIELD_OFFICER'],
            ['key' => 'manual_chart',     'name' => 'Manual Chart Upload',    'post' => 'CHART_OFFICER_I'],
            ['key' => 'digital_chart',    'name' => 'Digital Chart Creation', 'post' => 'CHART_OFFICER_II'],
            ['key' => 'report',           'name' => 'Cadastral Report',       'post' => 'REPORT_OFFICER'],
            ['key' => 'checking',         'name' => 'Checking',               'post' => 'OIC_VERIFICATION'],
            ['key' => 'approval',         'name' => 'Approval',               'post' => 'AD_CAD_REP'],
            ['key' => 'dispatch',         'name' => 'Dispatch',               'post' => null],
        ],
        'customary' => [
            ['key' => 'registration',  'name' => 'Registration',           'post' => null],
            ['key' => 'manual_chart',  'name' => 'Manual Chart Upload',    'post' => 'CHART_OFFICER_I'],
            ['key' => 'digital_chart', 'name' => 'Digital Chart Creation', 'post' => 'CHART_OFFICER_II'],
            ['key' => 'report',        'name' => 'Cadastral Report',       'post' => 'REPORT_OFFICER'],
            ['key' => 'checking',      'name' => 'Checking',               'post' => 'OIC_CUSTOMARY'],
            ['key' => 'approval',      'name' => 'Approval',               'post' => 'AD_CAD_REP'],
            ['key' => 'dispatch',      'name' => 'Dispatch',               'post' => null],
        ],
        'statutory' => [
            ['key' => 'registration',  'name' => 'Registration',           'post' => null],
            ['key' => 'manual_chart',  'name' => 'Manual Chart Upload',    'post' => 'CHART_OFFICER_I'],
            ['key' => 'digital_chart', 'name' => 'Digital Chart Creation', 'post' => 'CHART_OFFICER_II'],
            ['key' => 'report',        'name' => 'Cadastral Report',       'post' => 'REPORT_OFFICER'],
            ['key' => 'checking',      'name' => 'Checking',               'post' => 'OIC_STATUTORY'],
            ['key' => 'approval',      'name' => 'Approval',               'post' => 'AD_CAD_REP'],
            ['key' => 'dispatch',      'name' => 'Dispatch',               'post' => null],
        ],
    ],

    /*
    | Job posts. These are NOT user_roles rows: config/module_permissions.php
    | is explicit that modules are named menu areas, not job roles. They live in
    | cadastral_officers.post_code.
    */
    'posts' => [
        'D_CAD'            => 'Director, Cadastral',
        'DD_CAD'           => 'Deputy Director, Cadastral',
        'AD_CAD_INFO'      => 'Assistant Director, Cadastral Information',
        'AD_CAD_REP'       => 'Assistant Director, Cadastral Report',
        'OIC_STATUTORY'    => 'Officer in Charge, Statutory',
        'OIC_VERIFICATION' => 'Officer in Charge, Verification',
        'OIC_CUSTOMARY'    => 'Officer in Charge, Customary',
        'CHART_OFFICER_I'  => 'Chart Officer I',
        'CHART_OFFICER_II' => 'Chart Officer II',
        'REPORT_OFFICER'   => 'Report Officer',
        'FIELD_OFFICER'    => 'Field Officer',
        'AD_PSD'           => 'Assistant Director, Plans and Descriptions',
        'AD_COMP'          => 'Assistant Director, Compensation',
    ],

    /*
    | Fees (concept note 4.4). Pillars are 4,000 for BOTH government and private
    | types -- that is what the note says, and it is deliberate, not a typo.
    */
    'fees' => [
        'area_rate_per_sqm' => 20.00,

        'land_use_multiplier' => [
            'Residential'   => 1.00,
            'Commercial'    => 2.50,
            'Industrial'    => 2.00,
            'Agricultural'  => 0.50,
            'Institutional' => 0.75,
            'Mixed'         => 1.75,
        ],

        'zone_multiplier' => [
            'urban'      => 1.00,
            'semi_urban' => 0.75,
            'rural'      => 0.50,
        ],

        'pillar_unit_price'      => 4000.00,
        'service_charge_percent' => 5.0,

        'description_fee' => [
            'simple'   => 2500.00,
            'standard' => 5000.00,
            'complex'  => 10000.00,
        ],
    ],

    /*
    | The official fee sheet: "Right of Occupancy - Cadastral Fees and Area"
    | (docs/templates/cadastral/Cadastral-Fees-and-Area-RightofOccupancy.html).
    | Rebuild plan D1/D2: this replaces 'fees' above, which stays only until the
    | Phase 7 rewrite of CadastralBillCalculator stops reading it.
    |
    | THESE ARE FALLBACKS. System Admin -> Configurable Entries -> Cadastral saves
    | its edits to cadastral_settings / cadastral_area_fee_schedule /
    | cadastral_transport_bands, and App\Services\Cadastral\CadastralSettings reads
    | those first. A value here is used only for a key nobody has saved, or before
    | the 2026_10_01_100000 migration has been run.
    |
    | The keys of 'fee_sheet', 'job_number', 'its_number', 'file_numbers' and
    | 'area.plot_size_sqm' double as cadastral_settings.key (dot path under
    | cadastral_module), so the fallback for any setting is the same path here.
    */
    'fee_sheet' => [
        'rates' => [
            'investigation'       => 4000.00,   // Investigation and Search, flat
            'beacon'              => 4000.00,   // per beacon (cadastral_pillars row)
            'delay_per_day'       => 350.00,
            'field_work_per_day'  => 350.00,    // Additional field work
            'office_work_per_day' => 10000.00,
            'plan_print'          => 400.00,    // per file
        ],

        // Which fee column of the S.L.N. No. 3 of 1983 schedule bills use (D2).
        'area_schedule_column' => 'proposed',   // proposed | current

        // Q1, UNCONFIRMED: an area between two rows (0.45 Ha) is charged at the
        // next row up (4,200), or interpolated between the two (3,800).
        'between_rows_rule'      => 'next_row_up', // next_row_up | interpolate
        'between_rows_confirmed' => false,

        // Q2, UNCONFIRMED: the sheet's "per add hctr" notes above 9 Ha are
        // garbled ("100,00. per add hctr"), so the default refuses to bill.
        'above_max_area' => 'refuse',           // refuse | charge_max_row

        // Q3, UNCONFIRMED: the sheet reads "1.1 km / 11-5 km / over 50 km".
        'transport_bands_confirmed' => false,
    ],

    /*
    | Schedule of area fee, Kano S.L.N. No. 3 of 1983, transcribed row for row
    | from the fee sheet. The two note columns are kept verbatim, garbling and
    | all ("4,00per add hctr"), because what they mean is open question Q2 and a
    | tidied transcription would be a guess dressed as a fact.
    */
    'area_fee_schedule' => [
        ['hectares' => 0.02, 'current_fee' => 65.00,   'proposed_fee' => 200.00,   'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.03, 'current_fee' => 97.50,   'proposed_fee' => 300.00,   'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.04, 'current_fee' => 130.00,  'proposed_fee' => 400.00,   'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.05, 'current_fee' => 162.00,  'proposed_fee' => 500.00,   'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.06, 'current_fee' => 195.00,  'proposed_fee' => 600.00,   'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.07, 'current_fee' => 227.00,  'proposed_fee' => 700.00,   'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.08, 'current_fee' => 260.00,  'proposed_fee' => 800.00,   'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.09, 'current_fee' => 292.00,  'proposed_fee' => 900.00,   'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.10, 'current_fee' => 325.00,  'proposed_fee' => 1000.00,  'additional_note' => '4,00per add hctr', 'proposed_additional_note' => '10,00per add hctr'],
        ['hectares' => 0.20, 'current_fee' => 650.00,  'proposed_fee' => 1800.00,  'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.30, 'current_fee' => 975.00,  'proposed_fee' => 2600.00,  'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.40, 'current_fee' => 1300.00, 'proposed_fee' => 3400.00,  'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.50, 'current_fee' => 1625.00, 'proposed_fee' => 4200.00,  'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.60, 'current_fee' => 1950.00, 'proposed_fee' => 5000.00,  'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.70, 'current_fee' => 2275.00, 'proposed_fee' => 5800.00,  'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 0.80, 'current_fee' => 2600.00, 'proposed_fee' => 6600.00,  'additional_note' => '2,00per add hctr', 'proposed_additional_note' => '5,00per add hctr'],
        ['hectares' => 0.90, 'current_fee' => 2925.00, 'proposed_fee' => 7400.00,  'additional_note' => '0.01 - do -', 'proposed_additional_note' => '0.01 - do -'],
        ['hectares' => 1.00, 'current_fee' => 3250.00, 'proposed_fee' => 8200.00,  'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 2.00, 'current_fee' => 3650.00, 'proposed_fee' => 9600.00,  'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 3.00, 'current_fee' => 4050.00, 'proposed_fee' => 10200.00, 'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 4.00, 'current_fee' => 4450.00, 'proposed_fee' => 11200.00, 'additional_note' => '0.01 per add hctr', 'proposed_additional_note' => '0.01 per add hctr'],
        ['hectares' => 5.00, 'current_fee' => 4850.00, 'proposed_fee' => 12000.00, 'additional_note' => '100,00. per add hctr', 'proposed_additional_note' => '100,00. per add hctr'],
        ['hectares' => 6.00, 'current_fee' => 5050.00, 'proposed_fee' => 12400.00, 'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 7.00, 'current_fee' => 5250.00, 'proposed_fee' => 12800.00, 'additional_note' => '0.01 - do -', 'proposed_additional_note' => '0.01 - do -'],
        ['hectares' => 8.00, 'current_fee' => 5450.00, 'proposed_fee' => 13200.00, 'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
        ['hectares' => 9.00, 'current_fee' => 5650.00, 'proposed_fee' => 13600.00, 'additional_note' => '- do -', 'proposed_additional_note' => '- do -'],
    ],

    /*
    | Transport schedule, Q3 reading (UNCONFIRMED). Whole kilometres; max_km null
    | means "and over". The officer picks the band on the bill.
    */
    'transport_bands' => [
        ['label' => '1 - 10 km from Kano',  'min_km' => 1,  'max_km' => 10,   'fee' => 200.00],
        ['label' => '11 - 50 km from Kano', 'min_km' => 11, 'max_km' => 50,   'fee' => 300.00],
        ['label' => 'Over 50 km from Kano', 'min_km' => 51, 'max_km' => null, 'fee' => 500.00],
    ],

    /*
    | Area conversion. plot_size_sqm is snapshotted onto the plan/description row
    | so an old bill reprints the same plot count if the standard plot changes.
    */
    'area' => [
        'plot_size_sqm'   => 450.00,
        'sqm_per_hectare' => 10000.0,
        'sqm_per_acre'    => 4046.8564224,
        'precision'       => 2,
    ],

    /*
    | Land description templates. {tokens} are substituted by
    | Cadastral\LandDescriptionGenerator; the result stays editable.
    */
    'description_templates' => [

        'standard' => [
            'label' => 'Standard plot',
            'body'  => "ALL THAT piece or parcel of land known as Plot No. {plot_no}{block_clause} situate at {location}, measuring approximately {area_sqm} square metres ({area_ha} hectares), and more particularly delineated on Survey Plan No. {plan_no}.\n\nThe said parcel is bounded as follows:\n  North : {boundary_north}\n  South : {boundary_south}\n  East  : {boundary_east}\n  West  : {boundary_west}\n\nThe boundaries are demarcated by {pillar_count} survey pillar(s): {pillar_list}.",
        ],

        'layout' => [
            'label' => 'Within an approved layout',
            'body'  => "ALL THAT piece or parcel of land being Plot No. {plot_no}{block_clause} within the {layout_name} Layout, {location}, measuring approximately {area_sqm} square metres ({area_ha} hectares), as shown on Approved Plan No. {plan_no}.\n\nBounded:\n  North : {boundary_north}\n  South : {boundary_south}\n  East  : {boundary_east}\n  West  : {boundary_west}\n\nDemarcated by {pillar_count} pillar(s): {pillar_list}.",
        ],

        'agricultural' => [
            'label' => 'Agricultural / rural holding',
            'body'  => "ALL THAT piece or parcel of farmland situate at {location}, measuring approximately {area_ha} hectares ({area_sqm} square metres), held under customary tenure and delineated on Plan No. {plan_no}.\n\nBounded:\n  North : {boundary_north}\n  South : {boundary_south}\n  East  : {boundary_east}\n  West  : {boundary_west}\n\nBeacons: {pillar_list}.",
        ],
    ],
];
