---
name: batch-actions
description: Add bulk (batch) actions to a KLAES listing with the sticky footer bar - row checkboxes, select-all, and a floating "N selected" action bar. Use when a user asks for batch / bulk approve, reject, KAMMA, or any action applied to several rows of a table.
---

# Batch actions with the sticky footer bar

The user's chosen pattern for bulk actions (Change Request 08102026, live on the five
Simple Parcel Update listings and Duplex). Reuse it; do not invent a new bulk UI.

## Pieces

- `resources/views/deeds/parcel_update/partials/batch_check.blade.php` - the row checkbox.
  Params: `record`, optional `file` (label used in results), and the row's flags
  `canKnupda`, `canApprove`, `canReject`. Omit the flags to read them from a
  `<span hidden data-pu-batch-flags="{id}" data-can-...>` marker later in the row
  (Duplex does this because its rights are computed inside the actions cell).
- `resources/views/deeds/parcel_update/partials/batch_decisions.blade.php` - the bar and
  its JS. Params: `batchLabel` and `batchRoutes` (`knupda` / `approve` / `reject`, each
  `route('x.action', '__ID__')`). Include it once at the end of the page's scripts.
  For a different set of actions, copy the partial for that feature rather than
  adding switches to this one.

## Recipe

1. Header: put `<input type="checkbox" class="pu-batch-all ...">` before the S/N label in
   the first `<th>` (add `whitespace-nowrap`). No new column, so colspans stay intact.
2. Row: compute the flags in an `@php` block at the top of the row, from the SAME
   condition the row's own action menu uses (e.g. `SubdivisionWorkflow::blockers`,
   `$jsiCleared`, `knupda_status`, status). Then include `batch_check` before the S/N in
   the first `<td>`.
3. Include `batch_decisions` with the module's routes.
4. Render the page (rollback harness, admin user) and count `pu-batch-check` rows and
   flags; `node --check` the extracted script; rollback-test one per-record call with the
   exact payload the bar sends.

## Rules the bar enforces (keep them)

- A row with no available action is disabled and skipped by select-all.
- A button opens only when EVERY selected row is at that step, so workflow order holds
  (Approve / Reject stays shut while any selected file still needs KAMMA).
- Files go one at a time through the module's EXISTING per-record endpoint, so every
  server rule still applies; the result lists done / skipped / refused.
- Send back any field the per-record endpoint overwrites. Every KAMMA save writes
  `land_value` and `knupda_fee`, so the row carries its own values in `data-` attributes.
