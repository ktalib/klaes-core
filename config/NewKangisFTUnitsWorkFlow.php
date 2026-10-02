<?php

/**
 * New KANGIS File Tracking — 9-Stage Units Workflow (8 transfers)
 *
 * Step 1: New KANGIS File Indexing and Printing the KANGIS File Tracking Sheet
 * Step 2: Customer Service       --> One Stop Shop (OSS)     (Log Out)
 * Step 3: One Stop Shop (OSS)    --> Verification            (Log Out)
 * Step 4: Verification           --> Geometry (GIS)          (Log Out)
 * Step 5: Geometry (GIS)         --> Vetting Committee       (Log Out)
 * Step 6: Vetting Committee      --> Pagination (Deeds)      (Log Out)
 * Step 7: Pagination (Deeds)     --> Production (GIS)        (Log Out)
 * Step 8: Production (GIS)       --> Collection (DG)         (Log Out)
 * Step 9: Collection (DG)        --> KANGIS Registry         (Log In)
 */

return [
    1 => [
        'action' => 'File Indexing and Printing tracking sheet',
    ],
    2 => [
        'from_code' => 'CS',
        'from_name' => 'Commission Section',
        'to_code'   => 'OSS',
        'to_name'   => 'One Stop Shop (OSS)',
        'label'     => 'One Stop Shop (OSS)',
        'SMS'       => 'Dear applicant, your file [<file_number>] is now with One Stop Shop (OSS) for processing. We will keep you updated on the progress.',
        'action'    => 'log_out',
    ],
    3 => [
        'from_code' => 'OSS',
        'from_name' => 'One Stop Shop (OSS)',
        'to_code'   => 'VRF',
        'to_name'   => 'Verification',
        'label'     => 'Verification',
        'SMS'       => 'Dear applicant, your file [<file_number>] is now with the Verification unit for review. We will keep you updated on the progress.',
        'action'    => 'log_out',
    ],
    4 => [
        'from_code' => 'VRF',
        'from_name' => 'Verification',
        'to_code'   => 'GEO',
        'to_name'   => 'Geometry',
        'label'     => 'Geometry',
        'SMS'       => 'Dear applicant, your file [<file_number>] is now with the Geometry (GIS) unit for processing. We will keep you updated on the progress.',
        'action'    => 'log_out',
    ],
    5 => [
        'from_code' => 'GEO',
        'from_name' => 'Geometry',
        'to_code'   => 'VET',
        'to_name'   => 'Vetting Section',
        'label'     => 'Vetting',
        'SMS'       => 'Dear applicant, your file [<file_number>] is now with the Vetting Committee for review. We will keep you updated on the progress.',
        'action'    => 'log_out',
    ],
    6 => [
        'from_code' => 'VET',
        'from_name' => 'Vetting Section',
        'to_code'   => 'PAG',
        'to_name'   => 'Pagination (Deeds)',
        'label'     => 'Pagination',
        'SMS'       => 'Dear applicant, your file [<file_number>] is now with the Pagination (Deeds) unit for processing. We will keep you updated on the progress.',
        'action'    => 'log_out',
    ],
    7 => [
        'from_code' => 'PAG',
        'from_name' => 'Pagination (Deeds)',
        'to_code'   => 'PROD',
        'to_name'   => 'Production',
        'label'     => 'Production',
        'SMS'       => 'Dear applicant, your file [<file_number>] is now with the Production (GIS) unit for processing. We will keep you updated on the progress.',
        'action'    => 'log_out',
    ],
    8 => [
        'from_code' => 'PROD',
        'from_name' => 'Production',
        'to_code'   => 'DG',
        'to_name'   => 'DG, KANGIS',
        'label'     => 'Collection',
        'SMS'       => 'Dear applicant, your file [<file_number>] is now with the Collection (DG) unit for processing. We will keep you updated on the progress.',
        'action'    => 'log_out',
    ],
    9 => [
        'from_code' => 'DG',
        'from_name' => 'DG, KANGIS',
        'to_code'   => 'KRE',
        'to_name'   => 'KANGIS Registry',
        'label'     => 'Registry Log-In',
        'SMS'       => 'Dear applicant, your file [<file_number>] has been processed by the Collection (DG) unit and is now with KANGIS Registry. We will keep you updated on the progress.',
        'action'    => 'log_in',
    ],
];
