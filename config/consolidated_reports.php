<?php

return [
    'bill-balance' => [
        'title' => 'Bill Balance', 'department' => 'Deeds Department',
        'dateLabel' => 'Date created',
        'statusOptions' => ['' => 'All payment statuses', 'paid' => 'Paid', 'pending' => 'Pending'],
    ],
    'valuation' => [
        'title' => 'Valuation', 'department' => 'Deeds Department',
        'dateLabel' => 'Date generated',
        'statusOptions' => ['' => 'All print statuses', 'printed' => 'Printed', 'unprinted' => 'Unprinted'],
    ],
    'consent' => [
        'title' => 'Consent', 'department' => 'Deeds Department',
        'dateLabel' => 'Date generated',
        'statusOptions' => ['' => 'All consent types', 'Assignment' => 'Assignment', 'Gift' => 'Gift', 'Mortgage' => 'Mortgage'],
        'statusLabel' => 'Consent type',
    ],
    'st-fc' => [
        'title' => 'ST Final Conveyance', 'department' => 'Sectional Titling Department',
        'dateLabel' => 'Date generated',
        'statusOptions' => ['' => 'All generated conveyances'],
    ],
    'st-commissioning' => [
        'title' => 'ST File Commissioning', 'department' => 'Sectional Titling Department',
        'dateLabel' => 'Date commissioned',
        'statusOptions' => ['' => 'All file types', 'PRIMARY' => 'Primary', 'PUA' => 'Parented Unit', 'SUA' => 'Standalone Unit'],
        'statusLabel' => 'File type',
    ],
    'st-applications' => [
        'title' => 'ST Applications', 'department' => 'Sectional Titling Department',
        'dateLabel' => 'Date captured',
        'statusOptions' => ['' => 'All application types', 'PRIMARY' => 'Primary', 'PUA' => 'Parented Unit', 'SUA' => 'Standalone Unit'],
        'statusLabel' => 'Application type',
    ],
    'st-rofo' => [
        'title' => 'ST RofO', 'department' => 'Sectional Titling Department',
        'dateLabel' => 'Date generated',
        'statusOptions' => ['' => 'All print statuses', 'printed' => 'Printed', 'unprinted' => 'Unprinted'],
    ],
    'sltr-rofo' => [
        'title' => 'SLTR RofO', 'department' => 'SLTR Department',
        'dateLabel' => 'Date generated',
        'statusOptions' => ['' => 'All print statuses', 'printed' => 'Printed', 'unprinted' => 'Unprinted'],
    ],
];
