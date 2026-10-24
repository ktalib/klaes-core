<?php

/*
 * System Admin > KLAES Documentation (Change Request 22102026).
 *
 * Each section is a folder under {storage}/app/klaes-documentation/. To publish
 * a document, drop the PDF into its folder; nothing else needs changing. The
 * folder sits outside the public disk, so documents are only served to
 * signed-in users through the documentation route.
 */
return [
    'root' => 'app/klaes-documentation',

    'sections' => [
        'manuals' => ['label' => 'Manuals', 'icon' => 'book-open'],
        'sops' => ['label' => 'SoPs', 'icon' => 'list-checks'],
        'system' => ['label' => 'System Documentation', 'icon' => 'file-code-2'],
        'reports' => ['label' => 'Quarterly Reports', 'icon' => 'bar-chart-3'],
    ],

    /*
     * Quarter for a report whose file name does not say which one it is
     * (keyed by file name without the .pdf). Everything else is read from the
     * name: "Q3", "Quarter 3", "First Quarter", ...
     */
    'report_quarters' => [
        'CONSOLIDATED FILE TRACKING REPORT' => 'Q1 2026',
    ],
];
