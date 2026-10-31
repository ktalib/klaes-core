{{-- Shared look for the Survey listings: put class="sv-dt" on the table. Cells opt in with nowrap / num / ctr / muted / code / tp / wide. --}}
<style>
    .sv-dt { font-size: 13px; }
    .survey-proto .sv-dt thead th { padding: 10px 12px; font-size: 11px; }
    .survey-proto .sv-dt tbody td { padding: 10px 12px; line-height: 1.35; }
    .sv-dt .nowrap { white-space: nowrap; }
    .sv-dt .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sv-dt .ctr { text-align: center; font-variant-numeric: tabular-nums; }
    .sv-dt .muted { color: var(--gray-400); }
    .sv-dt .sub { display: block; font-size: 11px; color: var(--gray-500); margin-top: 2px; white-space: nowrap; }
    .sv-dt .code { font-weight: 600; color: var(--gray-900); white-space: nowrap; }
    .sv-dt .strong { font-weight: 500; color: var(--gray-900); }
    .sv-dt .wide { min-width: 190px; }
    .sv-dt .mid { min-width: 150px; }
    .sv-dt .tp { display: inline-block; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px;
                 background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 4px; padding: 2px 6px; white-space: nowrap; color: var(--gray-700); }
    .sv-dt .chips { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
    .sv-dt .chip { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 12px; color: var(--gray-700);
                   background: var(--gray-100); border-radius: 999px; padding: 2px 3px 2px 9px; }
    .sv-dt .chip b { display: inline-grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
                     background: #fff; border: 1px solid var(--gray-200); font-size: 11px; font-weight: 600; color: var(--gray-700); }
    .sv-dt .status-badge { white-space: nowrap; }
    .sv-dt .col-actions { position: sticky; right: 0; background: #fff; width: 48px; box-shadow: -1px 0 0 var(--gray-100); }
    .sv-dt thead .col-actions { background: var(--gray-50); }
    .sv-dt tbody tr:hover .col-actions { background: var(--gray-50); }

    /* Rows loaded from the paper lists (import_ref set). */
    .sv-imported { display: inline-flex; align-items: center; gap: 3px; margin-top: 3px; padding: 1px 6px; border-radius: 4px;
                   font-size: 10px; font-weight: 600; letter-spacing: .3px; text-transform: uppercase; white-space: nowrap;
                   color: #92400e; background: #fef3c7; border: 1px solid #fde68a; cursor: help; vertical-align: middle; }
</style>
