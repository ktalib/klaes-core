<?php
/**
 * Fix duplicate prop_id in instrument_capture table.
 *
 * Each IC row represents a different property (different person, registration number).
 * They were incorrectly assigned the same prop_id.
 *
 * Strategy:
 * - For each duplicate prop_id group, keep the OLDEST row's prop_id unchanged.
 * - For all newer rows, allocate a brand-new unique prop_id via PropertyIdAllocationService.
 * - Update PropID_Master with the new mapping.
 *
 * Usage: php fix_duplicate_ic_prop_ids.php [--dry-run]
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\PropertyIdAllocationService;

$dryRun = in_array('--dry-run', $argv ?? []);

echo $dryRun ? "=== DRY RUN MODE ===" : "=== LIVE MODE ===";
echo PHP_EOL . PHP_EOL;

$propIdService = app(PropertyIdAllocationService::class);

// ============================================================
// PART A: Fix duplicate prop_id in instrument_capture
// ============================================================
echo "=== PART A: instrument_capture ===" . PHP_EOL . PHP_EOL;

// Step 1: Find all duplicate prop_id groups
$duplicates = DB::connection('sqlsrv')
    ->select("
        SELECT prop_id, COUNT(*) as cnt
        FROM instrument_capture
        WHERE prop_id IS NOT NULL
          AND (is_deleted IS NULL OR is_deleted = 0)
        GROUP BY prop_id
        HAVING COUNT(*) > 1
        ORDER BY cnt DESC
    ");

$totalGroups = count($duplicates);
$totalRowsToFix = 0;
$fixed = 0;
$errors = 0;

echo "Found {$totalGroups} duplicate prop_id groups." . PHP_EOL . PHP_EOL;

foreach ($duplicates as $i => $dup) {
    $propId = $dup->prop_id;
    $count = $dup->cnt;

    // Get all rows in this group, ordered by id ASC (oldest first)
    $rows = DB::connection('sqlsrv')
        ->table('instrument_capture')
        ->where('prop_id', $propId)
        ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
        ->orderBy('id', 'asc')
        ->get(['id', 'prop_id', 'temp_fileno', 'mlsFNo', 'party_2_name', 'registration_number']);

    // Keep the first (oldest) row's prop_id, fix the rest
    $keepRow = $rows->first();
    $rowsToFix = $rows->slice(1);
    $totalRowsToFix += $rowsToFix->count();

    echo sprintf("[%d/%d] prop_id=%d  (%d rows, keeping ic.id=%d, fixing %d)",
        $i + 1, $totalGroups, $propId, $count, $keepRow->id, $rowsToFix->count()) . PHP_EOL;

    foreach ($rowsToFix as $row) {
        $tempFileno = $row->temp_fileno;
        $mlsFNo = $row->mlsFNo;
        $primaryFileNo = $mlsFNo ?: $tempFileno;

        try {
            if ($dryRun) {
                echo sprintf("  [DRY] ic.id=%d temp=%s  -> would allocate new prop_id", $row->id, $tempFileno) . PHP_EOL;
                $fixed++;
                continue;
            }

            // Allocate a new unique prop_id (skip_lookup to avoid finding the old shared one)
            $newPropId = $propIdService->allocateOrRetrievePropId(
                $primaryFileNo,
                $mlsFNo,
                null, // kangisFileNo
                null, // newKangisFileNo
                [
                    'temp_fileno' => $tempFileno,
                    'allow_temp_only' => true,
                    'skip_lookup' => true,
                ]
            );

            // Update the IC row
            DB::connection('sqlsrv')
                ->table('instrument_capture')
                ->where('id', $row->id)
                ->update([
                    'prop_id' => $newPropId,
                    'updated_at' => now(),
                ]);

            // Also sync to file_history_staging if there's a file number
            if ($tempFileno) {
                $propIdService->syncPropIdToFileHistory($tempFileno, $newPropId);
            }
            if ($mlsFNo) {
                $propIdService->syncPropIdToFileHistory($mlsFNo, $newPropId);
            }

            echo sprintf("  ic.id=%d temp=%s  prop_id: %d -> %d",
                $row->id, $tempFileno, $propId, $newPropId) . PHP_EOL;
            $fixed++;

        } catch (\Throwable $e) {
            echo sprintf("  ERROR ic.id=%d: %s", $row->id, $e->getMessage()) . PHP_EOL;
            $errors++;
        }
    }
}

echo PHP_EOL . "=== PART A SUMMARY ===" . PHP_EOL;
echo "Duplicate groups: {$totalGroups}" . PHP_EOL;
echo "Rows needing fix: {$totalRowsToFix}" . PHP_EOL;
echo "Fixed: {$fixed}" . PHP_EOL;
echo "Errors: {$errors}" . PHP_EOL;

if (!$dryRun) {
    $remaining = DB::connection('sqlsrv')->selectOne("
        SELECT COUNT(*) as cnt FROM (
            SELECT prop_id FROM instrument_capture
            WHERE prop_id IS NOT NULL AND (is_deleted IS NULL OR is_deleted = 0)
            GROUP BY prop_id HAVING COUNT(*) > 1
        ) x
    ");
    echo "Remaining IC duplicate groups: {$remaining->cnt}" . PHP_EOL;
}

// ============================================================
// PART B: Fix duplicate prop_id in deed_registrations
// ============================================================
echo PHP_EOL . "=== PART B: deed_registrations ===" . PHP_EOL . PHP_EOL;

$drDuplicates = DB::connection('sqlsrv')
    ->select("
        SELECT prop_id, COUNT(*) as cnt
        FROM deed_registrations
        WHERE prop_id IS NOT NULL
        GROUP BY prop_id
        HAVING COUNT(*) > 1
        ORDER BY cnt DESC
    ");

$drTotalGroups = count($drDuplicates);
$drTotalRowsToFix = 0;
$drFixed = 0;
$drErrors = 0;

echo "Found {$drTotalGroups} duplicate prop_id groups in deed_registrations." . PHP_EOL . PHP_EOL;

foreach ($drDuplicates as $i => $dup) {
    $propId = $dup->prop_id;
    $count = $dup->cnt;

    // Get all rows in this group, ordered by id ASC (keep oldest)
    $rows = DB::connection('sqlsrv')
        ->table('deed_registrations')
        ->where('prop_id', $propId)
        ->orderBy('id', 'asc')
        ->get(['id', 'prop_id', 'registration_number', 'grantor', 'grantee', 'instrument_type']);

    $keepRow = $rows->first();
    $rowsToFix = $rows->slice(1);
    $drTotalRowsToFix += $rowsToFix->count();

    echo sprintf("[%d/%d] prop_id=%d  (%d rows, keeping dr.id=%d, fixing %d)",
        $i + 1, $drTotalGroups, $propId, $count, $keepRow->id, $rowsToFix->count()) . PHP_EOL;

    foreach ($rowsToFix as $row) {
        try {
            // Look up the instrument_capture row that should link to this deed_registration
            // via matching registration_number
            $linkedIc = DB::connection('sqlsrv')
                ->table('instrument_capture')
                ->where('registration_number', $row->registration_number)
                ->where(function ($q) { $q->whereNull('is_deleted')->orWhere('is_deleted', 0); })
                ->orderByDesc('id')
                ->first(['id', 'prop_id', 'temp_fileno', 'mlsFNo']);

            if ($linkedIc && $linkedIc->prop_id && $linkedIc->prop_id != $propId) {
                // IC row already has a correct unique prop_id (fixed in Part A), use it
                $newPropId = $linkedIc->prop_id;
            } else {
                // No linked IC or IC has same prop_id — allocate a fresh one
                $regNo = $row->registration_number ?: "DR-{$row->id}";
                if ($dryRun) {
                    echo sprintf("  [DRY] dr.id=%d reg=%s grantee=%s -> would allocate new prop_id",
                        $row->id, $row->registration_number, $row->grantee) . PHP_EOL;
                    $drFixed++;
                    continue;
                }
                $newPropId = $propIdService->allocateOrRetrievePropId(
                    $linkedIc->temp_fileno ?? $linkedIc->mlsFNo ?? $regNo,
                    $linkedIc->mlsFNo ?? null,
                    null,
                    null,
                    [
                        'temp_fileno' => $linkedIc->temp_fileno ?? null,
                        'allow_temp_only' => true,
                        'skip_lookup' => true,
                    ]
                );
            }

            if ($dryRun) {
                echo sprintf("  [DRY] dr.id=%d reg=%s grantee=%s -> would set prop_id=%d",
                    $row->id, $row->registration_number, $row->grantee, $newPropId) . PHP_EOL;
                $drFixed++;
                continue;
            }

            DB::connection('sqlsrv')
                ->table('deed_registrations')
                ->where('id', $row->id)
                ->update([
                    'prop_id' => $newPropId,
                    'updated_at' => now(),
                ]);

            echo sprintf("  dr.id=%d reg=%s  prop_id: %d -> %d",
                $row->id, $row->registration_number, $propId, $newPropId) . PHP_EOL;
            $drFixed++;

        } catch (\Throwable $e) {
            echo sprintf("  ERROR dr.id=%d: %s", $row->id, $e->getMessage()) . PHP_EOL;
            $drErrors++;
        }
    }
}

echo PHP_EOL . "=== PART B SUMMARY ===" . PHP_EOL;
echo "Duplicate groups: {$drTotalGroups}" . PHP_EOL;
echo "Rows needing fix: {$drTotalRowsToFix}" . PHP_EOL;
echo "Fixed: {$drFixed}" . PHP_EOL;
echo "Errors: {$drErrors}" . PHP_EOL;

if (!$dryRun) {
    $drRemaining = DB::connection('sqlsrv')->selectOne("
        SELECT COUNT(*) as cnt FROM (
            SELECT prop_id FROM deed_registrations
            WHERE prop_id IS NOT NULL
            GROUP BY prop_id HAVING COUNT(*) > 1
        ) x
    ");
    echo "Remaining DR duplicate groups: {$drRemaining->cnt}" . PHP_EOL;
}

// ============================================================
// FINAL SUMMARY
// ============================================================
echo PHP_EOL . "=== FINAL SUMMARY ===" . PHP_EOL;
echo "instrument_capture:  {$fixed} fixed, {$errors} errors" . PHP_EOL;
echo "deed_registrations:  {$drFixed} fixed, {$drErrors} errors" . PHP_EOL;

if ($dryRun) {
    echo PHP_EOL . "This was a DRY RUN. Run without --dry-run to apply changes." . PHP_EOL;
}
