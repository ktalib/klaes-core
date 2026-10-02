<?php

namespace App\Services;

use App\Support\LegalSearchTimelineWeights;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegalSearchService
{
    private const ARRANGEMENT_TABLE = 'legal_search_timeline_arrangements';
    private array $softDeleteColumnCache = [];
    private ?array $decommissionedFileNumbers = null;

    /** 
     * Main search dispatcher.
     * Searches across 4 staging tables: file_history_staging, CofO_staging, pra, deed_registrations.
     * Returns all records arranged chronologically by transaction date.
     * 
     * 



     */
    public function search(array $params): array
    {
        $fileNo = trim($params['query'] ?? '');
        $guarantorName = trim($params['guarantorName'] ?? '');
        $guaranteeName = trim($params['guaranteeName'] ?? '');
        $lga = trim($params['lga'] ?? '');
        $district = trim($params['district'] ?? '');
        $location = trim($params['location'] ?? '');
        $plotNumber = trim($params['plotNumber'] ?? '');
        $planNumber = trim($params['planNumber'] ?? '');
        $size = trim($params['size'] ?? '');
        $caveat = trim($params['caveat'] ?? '');

        $hasSearchCriteria = $fileNo !== '' || $guarantorName !== '' || $guaranteeName !== '' ||
            $lga !== '' || $district !== '' || $location !== '' ||
            $plotNumber !== '' || $planNumber !== '' || $size !== '' || $caveat !== '';

        if (!$hasSearchCriteria) {
            return $this->emptyResult();
        }

        $conn = DB::connection('sqlsrv');

        // Preserve exactly what the user typed — before KANGIS canonicalization below rewrites
        // $fileNo — so the file-number display ("SEARCHED (LINKED)") always leads with the
        // number actually searched, whichever format it was in.
        $searchedFileNo = $fileNo;

        // A KANGIS number (e.g. "MLKN 2455") is an alias of a mother MLS file. Resolve it to
        // the mother's MLS number up front so the ENTIRE search — SME family detection, prop_id
        // expansion, and File Information — runs identically to searching the MLS number directly.
        // Without this, a KANGIS search is diverted into the SME branch, which bypasses prop_id
        // expansion and therefore never sees the mother's consolidated parcel.
        // Fail-open: only rewrites a confidently-mapped KANGIS-format input; otherwise unchanged.
        if ($fileNo !== '') {
            $canonicalFileNo = $this->resolveKangisCanonical($conn, $fileNo);
            if (!empty($canonicalFileNo)) {
                $fileNo = $canonicalFileNo;
            }
        }

        $filters = compact('fileNo', 'guarantorName', 'guaranteeName', 'lga', 'district', 'location', 'plotNumber', 'planNumber', 'size', 'caveat');

        // Fetch SME allowed file numbers to prevent prop_id conversions/collisions
        $allowedSmeFileNos = $this->getSmeAllowedFileNos($fileNo, $conn);
        $filters['allowedSmeFileNos'] = $allowedSmeFileNos;

        // Sectional Titling: when the searched file is an ST mother/scheme (searched by its
        // LAND number, e.g. RES-2025-115, or the ST number, e.g. ST-RES-2026-2), its unit
        // files' transactions are keyed to the ST number (deed_registrations.parent_fileno)
        // or the unit numbers, NOT the land number — so a mother search would otherwise miss
        // them. Resolve the scheme number + unit numbers and (a) add them to the file-number
        // search set and (b) whitelist them past the cross-property contamination guard. This
        // is a SEPARATE channel from allowedSmeFileNos so it never triggers the SME-mode
        // bypass of the mother's own prop_id expansion. A UNIT search returns [] here
        // (sibling exclusion — the unit's own number already pulls its own transactions).
        $stRelatedFileNos = $this->resolveStRelatedFileNos($conn, $fileNo);
        $filters['stRelatedFileNos'] = $stRelatedFileNos;

        $fileHistoryRecords = $this->searchFileHistoryStaging($conn, $filters);
        $cofoRecords = $this->searchCofoStaging($conn, $filters);
        $praRecords = $this->searchPra($conn, $filters);
        $deedRecords = $this->searchDeedRegistrations($conn, $filters);

        // Fetch active prop_id and parent_prop_id from active indexes if available (bypass for SME searches)
        $activePropIds = [];
        if ($fileNo !== '' && empty($allowedSmeFileNos)) {
            // Match on file_number OR temp_file_no (and their base/"(T)" variants) so a temp-file
            // search resolves the same prop_id as searching the main number, and vice versa.
            $fileNoVariants = $this->fileNumberVariants($fileNo);
            $activeIndexing = $conn->table('file_indexings')
                ->where(function ($q) use ($fileNoVariants) {
                    $q->whereIn('file_number', $fileNoVariants)
                        ->orWhereIn('temp_file_no', $fileNoVariants);
                })
                ->whereNull('deleted_at')
                ->first(['prop_id', 'parent_prop_id']);
            if ($activeIndexing) {
                if ($activeIndexing->prop_id) {
                    $activePropIds[] = (string) $activeIndexing->prop_id;
                }
                if ($activeIndexing->parent_prop_id) {
                    $activePropIds = array_merge($activePropIds, array_map('trim', explode(',', $activeIndexing->parent_prop_id)));
                }
            }
            
            $activeFileNo = $conn->table('fileNumber')
                ->whereIn('mlsfNo', $fileNoVariants)
                ->first(['parent_prop_id']);
            if ($activeFileNo && $activeFileNo->parent_prop_id) {
                $activePropIds = array_merge($activePropIds, array_map('trim', explode(',', $activeFileNo->parent_prop_id)));
            }

            // PRA merger records store parent_prop_id (source file prop_ids) directly on the row.
            // Extract these so the search expands to include the source files' records.
            foreach ($praRecords as $praRow) {
                $ppid = trim((string) ($praRow['parent_prop_id'] ?? ''));
                if ($ppid !== '') {
                    foreach (array_map('trim', explode(',', $ppid)) as $pid) {
                        if ($pid !== '') {
                            $activePropIds[] = $pid;
                        }
                    }
                }
            }
        }

        // --- prop_id cross-table expansion (bypass for SME searches) ---
        // Collect prop_ids from initial results, then pull related records from all 4 tables
        $propIds = [];
        if (empty($allowedSmeFileNos)) {
            $propIds = $this->collectPropIds(array_merge($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords));
            foreach ($activePropIds as $pid) {
                if (trim($pid) !== '') {
                    $propIds[] = trim($pid);
                }
            }
            $propIds = array_values(array_unique($propIds));
        }

        if (!empty($propIds)) {
            $existingIds = $this->buildExistingIdMap($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords);

            $extraFH = $this->searchByPropIds($conn, 'file_history_staging', $propIds, $existingIds['file_history_staging'] ?? []);
            $extraCofO = $this->searchByPropIds($conn, 'CofO_staging', $propIds, $existingIds['CofO_staging'] ?? []);
            $extraPRA = $this->searchByPropIds($conn, 'pra', $propIds, $existingIds['pra'] ?? []);
            $extraDeed = $this->searchByPropIds($conn, 'deed_registrations', $propIds, $existingIds['deed_registrations'] ?? []);

            $fileHistoryRecords = array_merge($fileHistoryRecords, $extraFH);
            $cofoRecords = array_merge($cofoRecords, $extraCofO);
            $praRecords = array_merge($praRecords, $extraPRA);
            $deedRecords = array_merge($deedRecords, $extraDeed);
        }

        // --- inherited (mother-file) history expansion ---
        // A subdivision / change-of-purpose CHILD carries its mother's prop_id in parent_prop_id
        // (confirmed: CON-COM-2026-430/431 & CON-AG-2026-108/109 all point to parent_prop_id 7530,
        // which is the mother CON-AG-2014-35's own prop_id). The mother's foundational transactions
        // (Right of Occupancy, Deed of Mortgage, Certificate of Occupancy) live under HER prop_id,
        // so a child search must expand the ancestor prop_id(s) to surface them. This runs even in
        // SME mode, where the block above is bypassed. Because parent_prop_id points strictly upward
        // and we fetch ONLY by the ancestor's own prop_id, siblings (their own distinct prop_ids)
        // are never pulled in. The contamination guard below keeps these rows because it already
        // folds the searched file's parent_prop_id into the allowed set. Fully fail-open.
        try {
            if ($fileNo !== '') {
                $ancestorPropIds = $this->resolveAncestorPropIds(
                    $conn,
                    $fileNo,
                    array_merge($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords)
                );
                if (!empty($ancestorPropIds)) {
                    $existingIds = $this->buildExistingIdMap($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords);
                    $fileHistoryRecords = array_merge($fileHistoryRecords, $this->searchByPropIds($conn, 'file_history_staging', $ancestorPropIds, $existingIds['file_history_staging'] ?? []));
                    $cofoRecords = array_merge($cofoRecords, $this->searchByPropIds($conn, 'CofO_staging', $ancestorPropIds, $existingIds['CofO_staging'] ?? []));
                    $praRecords = array_merge($praRecords, $this->searchByPropIds($conn, 'pra', $ancestorPropIds, $existingIds['pra'] ?? []));
                    $deedRecords = array_merge($deedRecords, $this->searchByPropIds($conn, 'deed_registrations', $ancestorPropIds, $existingIds['deed_registrations'] ?? []));
                }
            }
        } catch (\Throwable $e) {
            // fail-open: inherited-history expansion must never break the core search
        }

        // Merge all and sort chronologically
        $all = array_merge($fileHistoryRecords, $cofoRecords, $praRecords, $deedRecords);

        // Guard against cross-property contamination.
        // related_file_number recertification links and SME (related_fileno) expansion can pull
        // in transaction records that belong to a DIFFERENT property (different prop_id) — e.g.
        // a KANGIS recertification row tying RES-2026-2270 (prop_id A) to MLKN 3020 (prop_id B).
        // Establish the prop_id(s) that genuinely belong to the searched file number, then drop
        // any transaction row whose prop_id differs. Merger parents (parent_prop_id) are kept.
        if ($fileNo !== '') {
            $matchesSearchedFile = function (array $row) use ($fileNo): bool {
                foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                    $v = trim((string) ($row[$col] ?? ''));
                    if ($v !== '' && strcasecmp($v, $fileNo) === 0) {
                        return true;
                    }
                }
                return false;
            };

            // Helper to check if file numbers are truly distinct (not just prefix matches)
            $isDistinctFile = function (string $rowFileNo) use ($fileNo): bool {
                $rowFileNo = trim(strtoupper($rowFileNo));
                $searchFileNo = trim(strtoupper($fileNo));

                // Exact match is not distinct
                if ($rowFileNo === $searchFileNo) {
                    return false;
                }

                // Check if rowFileNo contains searchFileNo as a substring but is a completely different file
                // Example: searching "RES-1992-2508" should exclude "CON-RES-2018-487"
                // They share "RES" but have different years and numbers
                if (strpos($rowFileNo, $searchFileNo) === false && strpos($searchFileNo, $rowFileNo) === false) {
                    return true; // Completely different strings
                }

                // Extract the core pattern (PREFIX-YEAR-NUMBER)
                $extractCore = function($fn) {
                    // Match patterns like COM-2025-123 or CON-RES-2018-487 or RES-1992-2508
                    if (preg_match('/((?:CON-)?(?:RES|COM|IND|AG))-(\d{4})-(\d+)/', $fn, $m)) {
                        return $m[1] . '-' . $m[2] . '-' . $m[3];
                    }
                    return $fn;
                };

                $rowCore = $extractCore($rowFileNo);
                $searchCore = $extractCore($searchFileNo);

                // If cores are different, they're distinct files
                return $rowCore !== $searchCore;
            };

            $searchedPropIds = [];
            foreach ($all as $row) {
                if (($row['source_table'] ?? '') === 'Related Fileno') {
                    continue; // synthetic recert rows don't define the property
                }
                if ($matchesSearchedFile($row)) {
                    $pid = trim((string) ($row['prop_id'] ?? ''));
                    if ($pid !== '') {
                        $searchedPropIds[$pid] = true;
                    }
                    $ppid = trim((string) ($row['parent_prop_id'] ?? ''));
                    foreach (array_filter(array_map('trim', explode(',', $ppid))) as $pp) {
                        $searchedPropIds[$pp] = true;
                    }
                }
            }

            // Allow resolved active and ancestor property IDs to pass the contamination guard
            foreach ($activePropIds as $pid) {
                if (trim($pid) !== '') {
                    $searchedPropIds[trim($pid)] = true;
                }
            }
            if (isset($ancestorPropIds)) {
                foreach ($ancestorPropIds as $pid) {
                    if (trim($pid) !== '') {
                        $searchedPropIds[trim($pid)] = true;
                    }
                }
            }

            // The searched file's explicit lineage set (SME siblings derived from related_fileno).
            // These are legitimately linked files (e.g. a merger's source files) and must survive
            // the contamination guard even when their prop_id was never linked/remapped to the
            // searched file — as happens with manual-linkage backfilled mergers/subdivisions.
            $smeAllowed = [];
            foreach (($allowedSmeFileNos ?? []) as $sme) {
                $s = trim((string) $sme);
                if ($s !== '') {
                    $smeAllowed[strtoupper($s)] = true;
                }
            }
            // ST scheme siblings (unit numbers + scheme number) are legitimately linked to the
            // searched mother — keep their (often prop_id-less) transaction rows past the guard.
            foreach (($stRelatedFileNos ?? []) as $stFn) {
                $s = trim((string) $stFn);
                if ($s !== '') {
                    $smeAllowed[strtoupper($s)] = true;
                }
            }

            // TEMPORARY prop_id-misassignment guard (until the prop_id data is cleaned):
            // a shared prop_id alone must not pull an UNRELATED file's rows onto the timeline.
            // Build the sets of LEGITIMATELY-linked files/parcels so only they survive a
            // prop_id-ONLY match; everything else that collides on a (mis)assigned prop_id is
            // dropped. This self-disables once no unrelated file shares a prop_id.
            //   1) OP/TOT parcels — an Occupancy Permit (temp, or temp+land) and its Transfer of
            //      Title (land) legitimately share ONE prop_id. Keyed by prop_id.
            $opTotPropIds = [];
            foreach ($all as $row) {
                if ($this->isOpTotRow($row)) {
                    $p = trim((string) ($row['prop_id'] ?? ''));
                    if ($p !== '') {
                        $opTotPropIds[$p] = true;
                    }
                }
            }
            //   2) PropID_Master-registered aliases of the searched parcel(s) — the KANGIS/MLS/temp
            //      number formats of the SAME file, which must never be treated as "unrelated".
            $masterAliasFiles = [];
            if (!empty($searchedPropIds)) {
                foreach ($conn->table('PropID_Master')
                    ->whereIn('prop_id', array_keys($searchedPropIds))
                    ->get(['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno']) as $mr) {
                    foreach (['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'] as $col) {
                        $v = strtoupper(trim((string) ($mr->{$col} ?? '')));
                        if ($v !== '') {
                            $masterAliasFiles[$v] = true;
                        }
                    }
                }
            }

            // Only filter when the searched file resolves to a definite prop_id; otherwise leave
            // file-number / orphan-only results untouched.
            if (!empty($searchedPropIds)) {
                $all = array_values(array_filter($all, function ($row) use ($searchedPropIds, $matchesSearchedFile, $isDistinctFile, $fileNo, $smeAllowed, $opTotPropIds, $masterAliasFiles) {
                    // Always keep synthetic recertification rows (contextual markers).
                    if (($row['source_table'] ?? '') === 'Related Fileno') {
                        return true;
                    }

                    // Get the row's file number
                    $rowFileNo = '';
                    foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                        $v = trim((string) ($row[$col] ?? ''));
                        if ($v !== '') {
                            $rowFileNo = $v;
                            break;
                        }
                    }

                    // Keep rows belonging to the searched file's explicit lineage set (related_fileno
                    // siblings), regardless of prop_id — otherwise a merger's source files whose
                    // prop_id was never remapped are wrongly dropped as "cross-property".
                    if ($rowFileNo !== '' && isset($smeAllowed[strtoupper($rowFileNo)])) {
                        return true;
                    }

                    $pid = trim((string) ($row['prop_id'] ?? ''));
                    if ($pid !== '' && isset($searchedPropIds[$pid])) {
                        // Shared prop_id. Keep it UNLESS it's a distinctly-different file linked
                        // ONLY by that (possibly mis-assigned) prop_id. Legit shares survive:
                        // OP/TOT parcels, the row being an OP/TOT instrument, and PropID_Master
                        // registered aliases (KANGIS/MLS/temp formats of the same file). SME
                        // lineage was already kept above.
                        if ($rowFileNo !== '' && $isDistinctFile($rowFileNo)
                            && !isset($opTotPropIds[$pid])
                            && !$this->isOpTotRow($row)
                            && !isset($masterAliasFiles[strtoupper($rowFileNo)])) {
                            return false; // unrelated file colliding on a (mis)assigned prop_id
                        }
                        return true;
                    }

                    $ppid = trim((string) ($row['parent_prop_id'] ?? ''));
                    foreach (array_filter(array_map('trim', explode(',', $ppid))) as $pp) {
                        if ($pp !== '' && isset($searchedPropIds[$pp])) {
                            return true;
                        }
                    }
                    
                    // If this row's file number is distinctly different from the searched file, exclude it
                    if ($rowFileNo !== '' && $isDistinctFile($rowFileNo)) {
                        return false;
                    }
                    
                    // Keep orphan rows (no prop_id) only if they directly match the searched file number.
                    if ($pid === '' && $matchesSearchedFile($row)) {
                        return true;
                    }
                    return false;
                }));
            }
        }

        // Fetch matching related_file_number entries (orange Recertification rows)
        // and append them as synthetic timeline rows.
        $relatedRecertRows = $this->fetchRelatedRecertificationRows($conn, $fileNo, $all);
        if (!empty($relatedRecertRows)) {
            $all = array_merge($all, $relatedRecertRows);
        }

        // When the searched file is linked (as a child) to a DCIV/LPCC master file in
        // master_dciv_links, surface that DCIV file as a "DCIV File Commissioning" row.
        $dcivCommissioningRows = $this->fetchDcivCommissioningRows($conn, $fileNo, $all);
        if (!empty($dcivCommissioningRows)) {
            $all = array_merge($all, $dcivCommissioningRows);
        }

        // When the searched file is a Sectional Titling (ST) file — a primary ST
        // file (ST-COM-2025-5) or one of its commissioned unit files
        // (ST-COM-2025-5-001) — surface the scheme's commissioning lifecycle:
        // an "ST File Commissioning" row for the primary application and an
        // "ST File Commissioning – Fragmentation" row for each commissioned unit.
        $stCommissioningRows = $this->fetchStCommissioningRows($conn, $fileNo, $all);
        if (!empty($stCommissioningRows)) {
            $all = array_merge($all, $stCommissioningRows);
        }

        // Surface predecessor decommission events (Change of Purpose / Subdivision / Merger /
        // Extension) as timeline rows when viewing the successor file, so the transition that
        // produced this file is visible instead of only the inherited history.
        $decomLineageRows = $this->fetchDecommissionLineageRows($conn, $fileNo, $all);
        if (!empty($decomLineageRows)) {
            $all = array_merge($all, $decomLineageRows);
        }

        // If searched by a subdivided unit (standard or Sectional Titling), only keep its own records and the mother's records,
        // and explicitly exclude other unit records.
        $motherFileNo = null;
        if ($this->isSubdividedUnit($fileNo, $motherFileNo)) {
            $all = array_filter($all, function ($row) use ($fileNo, $motherFileNo) {
                // Get the row's file number
                $rowFileNo = trim($row['fileno'] ?? ($row['file_number'] ?? ($row['mlsFNo'] ?? '')));
                if ($rowFileNo === '') {
                    return true; // Keep if no file number (e.g. orphan record with same prop_id)
                }
                
                // Keep if matches the searched unit or the mother file number
                if (strcasecmp($rowFileNo, $fileNo) === 0 || strcasecmp($rowFileNo, $motherFileNo) === 0) {
                    return true;
                }
                
                // If it's a subdivided unit file number pattern but not the searched one, exclude it!
                if ($this->isSubdividedUnit($rowFileNo)) {
                    return false; // Exclude other units!
                }
                
                return true;
            });
            $all = array_values($all);
        }

        // When the DIRECTLY-searched file has itself been genuinely decommissioned/superseded by a
        // land transaction (Subdivision, Merger, Separation, Change of Purpose, File Extension), do
        // not present it as an active file: drop the rows that carry the searched (old) number.
        // Rows pulled in via prop_id / parent_prop_id expansion (the SUCCESSOR's history) are kept,
        // which both preserves lineage and effectively soft-redirects to the successor's records.
        // false_decommissioning = 1 rows are Title-Status flags (not real decommissions) and are
        // excluded from this set. Synthetic 'Related Fileno' lineage markers are always preserved.
        if ($fileNo !== '') {
            $decommissioned = $this->getDecommissionedFileNumbers($conn);
            // Only redirect (drop the searched file's own rows) for a 1:1 rename-type decommission,
            // where the successor inherits the SAME prop_id and therefore already carries the full
            // history (Change of Purpose, Change of Name, recertification, amendment, cancellation).
            // For a SPLIT/MERGE (Plot Subdivision / Merger / Separation), the successors get NEW
            // prop_ids, so the searched file's own history (e.g. a subdivided mother's Deed of
            // Mortgage) is not represented anywhere else — keep it instead of wiping it out.
            $decomReason = $decommissioned[strtoupper($fileNo)] ?? null;
            $isSplitOrMerge = $decomReason !== null
                && preg_match('/subdivision|merg|separation|fragment/i', (string) $decomReason);
            if ($decomReason !== null && !$isSplitOrMerge) {
                $all = array_values(array_filter($all, function ($row) use ($fileNo) {
                    if (($row['source_table'] ?? '') === 'Related Fileno') {
                        return true;
                    }
                    foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                        $v = trim((string) ($row[$col] ?? ''));
                        if ($v !== '' && strcasecmp($v, $fileNo) === 0) {
                            return false;
                        }
                    }
                    return true;
                }));
            }
        }

        usort($all, function ($a, $b) {
            $dateA = $a['sort_date'] ?? '9999-12-31';
            $dateB = $b['sort_date'] ?? '9999-12-31';
            return strcmp($dateA, $dateB);
        });

        // Apply saved arrangement order if one exists for the common prop_id
        $all = $this->applyArrangementOrder($all);

        // KANGIS Recertification placement is lifecycle-phase-specific. It is applied
        // during per-lifecycle arrangement, never as a global pre-group shuffle.

        // Change of Purpose and Subdivision are Ministry-initiated actions, so Party 1 (the
        // grantor/authority) is always the Ministry — not the file owner. Normalize it here so
        // both the on-screen timeline and the printed report (which reads party_1 from these
        // transactions) are consistent. Party 2 (the owner/beneficiary) is left untouched.
        // Recertification Party 1 (grantor/authority) is the issuing body: a Ministry
        // recertification ("Ministry Of Land & Physical Planning Recertification") is issued
        // by the Ministry; every other recertification (KANGIS) is issued by the Kano
        // Geographic Information Service — never the file owner.
        foreach ($all as &$_row) {
            $_type = strtolower((string) ($_row['transaction_type'] ?? ''));
            if (str_contains($_type, 'recertification')) {
                // 'land recertification' is required here: fetchRelatedRecertificationRows() has
                // already rewritten a Ministry recert's type via recertDisplayLabel() to
                // "Land Recertification (File Commissioning)", which contains neither 'ministry'
                // nor 'physical planning' — without this token every Ministry recertification
                // would be credited to KANGIS (and contradict makePrintMinistryRecertRow()).
                $_row['party_1'] = (str_contains($_type, 'ministry')
                        || str_contains($_type, 'physical planning')
                        || str_contains($_type, 'land recertification'))
                    ? 'Kano State Ministry of Land and Physical Planning'
                    : 'Kano Geographic Information Service';
            } elseif (str_contains($_type, 'change of purpose') || str_contains($_type, 'subdivision')) {
                $_row['party_1'] = 'Kano State Ministry of Land and Physical Planning';
            }
        }
        unset($_row);

        // Look up file info from file_indexings for the primary file number
        $fileIndexingData = null;
        if (!empty($all)) {
            $primaryCandidates = array_values(array_unique(array_filter([
                $all[0]['fileno'] ?? null,
                $all[0]['file_number'] ?? null,
                $all[0]['mlsFNo'] ?? null,
                $fileNo,
            ])));

            if (!empty($primaryCandidates)) {
                $fileIndexingDataList = $conn->table('file_indexings')
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($primaryCandidates) {
                        foreach ($primaryCandidates as $candidate) {
                            $q->orWhere('file_number', $candidate)
                                ->orWhere('temp_file_no', $candidate)
                                ->orWhere('related_fileno', 'like', '%' . $candidate . '%');
                        }
                    })
                    ->select('file_title', 'district', 'lga', 'land_use_type', 'plot_number', 'plot_size', 'tp_no', 'related_fileno', 'file_number', 'location', 'has_temp_file', 'temp_file_no', 'latitude', 'longitude', 'ground_rent_amount', 'ground_rent_receipt_date', 'term')
                    ->get();

                $fileIndexingData = $this->pickBestIndexingRow($fileIndexingDataList, $primaryCandidates);
            }
        }

        // The picked indexing row may be only a LOOSE match — found via its
        // related_fileno pointing back at the searched file (e.g. a surviving
        // subdivision child of a decommissioned mother). Its own file_number is
        // then a DIFFERENT file and must not masquerade as the searched number
        // in the File Information display or the commissioning lookup.
        $ownIndexedNumber = null;
        if ($fileIndexingData) {
            // Treat the picked indexing row as the searched file's OWN record ONLY when it
            // matches the searched number (or the temp number the user searched) — never a
            // related/child row that merely sorted to the top of $all. Matching against the
            // whole $primaryCandidates set (which includes $all[0]) let a subdivision mother
            // that is not itself indexed borrow a CHILD's number for the File Information
            // header (e.g. searching RES-1992-7536 showed RES-RC-1991-39).
            $searchedOwnNumbers = array_filter([$searchedFileNo, $fileNo]);
            foreach ($searchedOwnNumbers as $candidate) {
                if (strcasecmp((string) ($fileIndexingData->file_number ?? ''), (string) $candidate) === 0
                    || strcasecmp((string) ($fileIndexingData->temp_file_no ?? ''), (string) $candidate) === 0) {
                    $ownIndexedNumber = (string) $fileIndexingData->file_number;
                    break;
                }
            }
        }

        // Size: the file indexing record is the editable source of truth (the Edit File
        // Information modal writes to file_indexings.plot_size), so it takes priority —
        // this mirrors buildPrintReport() so the on-screen "Size" and the printed report
        // agree, and a manually-entered size actually shows after a refresh.
        $fileSize = null;
        if ($fileIndexingData && trim((string) ($fileIndexingData->plot_size ?? '')) !== '') {
            $fileSize = trim((string) $fileIndexingData->plot_size);
        }

        // Otherwise derive it from the transactions using source weighting:
        // CofO(4) > FH(3) > PRA(2) > Deed(1).
        if (!$fileSize) {
            $bestSizeScore = -1;
            $sourceScores = [
                'CofO_staging' => 4,
                'file_history_staging' => 3,
                'pra' => 2,
                'deed_registrations' => 1,
            ];
            foreach ($all as $t) {
                $s = $t['size'] ?? null;
                if ($s && $s !== '-' && trim($s) !== '') {
                    $src = $t['source_table'] ?? '';
                    $score = $sourceScores[$src] ?? 0;
                    if ($score > $bestSizeScore) {
                        $bestSizeScore = $score;
                        $fileSize = trim($s);
                    }
                }
            }
        }
        if (!$fileSize && !empty($all)) {
            // Fallback: query PRA table directly via prop_id
            $propId = $all[0]['prop_id'] ?? null;
            if ($propId) {
                $praSize = $conn->table('pra')
                    ->where('prop_id', $propId)
                    ->whereNotNull('plot_size')
                    ->where('plot_size', '!=', '')
                    ->orderByDesc('id')
                    ->value('plot_size');
                if ($praSize) {
                    $fileSize = trim($praSize);
                }
            }
        }

        // Temporary "(T)" file number tied to the searched file — displayed as a
        // second line beside the primary file number in the File Information card.
        $tempFileNumber = $this->resolveTempFileNumber($conn, $fileNo, $fileIndexingData);

        // DCIV investigation flag — a searched file tied to a DCIV (on either side
        // of master_dciv_links) is marked Under Investigation.
        $investigation = $this->resolveDcivInvestigation([$fileNo], $all);

        // File Title: prefer the indexed title, but fall back to the commissioned file's
        // owner name in fileNumber.FileName. Some commissioned files were never indexed
        // (no file_indexings row), so the title otherwise shows blank even though the name
        // exists on the fileNumber record and in the transactions.
        $resolvedFileTitle = trim((string) ($fileIndexingData->file_title ?? ''));
        if ($resolvedFileTitle === '') {
            $baseNo = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', (string) $fileNo));
            $titleCandidates = array_values(array_unique(array_filter([
                trim((string) $fileNo),
                $baseNo,
                $baseNo !== '' ? $baseNo . '(T)' : '', // record may be stored under the "(T)" number
                trim((string) ($fileIndexingData->file_number ?? '')),
            ], fn($v) => $v !== '')));
            if (!empty($titleCandidates)) {
                $fnName = $conn->table('fileNumber')
                    ->whereIn('mlsfNo', $titleCandidates)
                    ->whereNotNull('FileName')
                    ->where('FileName', '<>', '')
                    ->value('FileName');
                if ($fnName) {
                    $resolvedFileTitle = trim((string) $fnName);
                }
            }
        }

        // Resolve the commissioning date and, crucially, WHICH file number was
        // commissioned so the timeline can place the date on the permanent or the
        // "(T)" temporary row as appropriate.
        $commissioningInfo = $this->resolveCommissioningInfo(
            $ownIndexedNumber ?? $fileNo,
            $fileNo
        );

        // W/R/C flag — file tagged [WRC] in duplicate_fileno (matched by number
        // variants or prop_id). Used to conditionally reveal the W/R/C remark
        // editor in the LS comments panel (mirrors the report's own gating).
        $isWrcFile = $this->resolveIsWrcFile($conn, $fileNo, $all[0]['prop_id'] ?? null);

        // Lon/Lat from the file indexing record, formatted "longitude, latitude"
        // — mirrors the printed report's Lon/Lat field.
        $fileLonLat = null;
        $fiLon = trim((string) ($fileIndexingData->longitude ?? ''));
        $fiLat = trim((string) ($fileIndexingData->latitude ?? ''));
        if ($fiLon !== '' && $fiLat !== '') {
            $fileLonLat = $fiLon . ', ' . $fiLat;
        } elseif ($fiLon !== '' || $fiLat !== '') {
            $fileLonLat = $fiLon !== '' ? $fiLon : $fiLat;
        }

        // "SEARCHED (LINKED)" file-number display — same rule used by the printable report and
        // Pay-Per-Search template, so the LS Timeline shows the identical KANGIS/MLS pairing.
        $fileNumberDisplay = $this->resolveFileNumberDisplay(
            $conn,
            $searchedFileNo,
            $ownIndexedNumber ?? $fileNo,
            $all,
            $fileIndexingData->related_fileno ?? null,
            $all[0]['kangisFileNo'] ?? null
        );

        // Stamp every returned transaction with its lifecycle owner so the UI can
        // group the timeline by file lifecycle. Seed the KANGIS-alias map from the
        // "MAIN (KANGIS)" display so the searched file's KANGIS rows roll into it.
        // A neutral "Related File" link row is redundant when the related file number already
        // displays with real transactions of its own; keep it (with a blank type) only when the
        // related file has no transactions at all.
        $all = $this->suppressRedundantRelatedFileRows($all);

        $all = $this->tagRowsWithLifecycleFileNo(
            $all,
            $this->resolveAliasHintOwners($all, $this->aliasHintsFromDisplay($fileNumberDisplay))
        );

        // Build lifecycle metadata for every distinct lifecycle file surfaced in the
        // result set. The frontend uses this to synthesize commissioning/temp/
        // decommissioning rows for related files without a second round-trip.
        $lifecycleMeta = [];
        $lifecycleFiles = [];
        foreach ($all as $row) {
            $fno = $row['lifecycle_file_no'] ?? null;
            if ($fno && $fno !== '') {
                $lifecycleFiles[$fno] = true;
            }
        }
        // Always include the searched file (and its main/base number for temp searches).
        $normSearched = $this->normalizeLifecycleFileNo($fileNo);
        if ($normSearched !== '') {
            $lifecycleFiles[$normSearched] = true;
        }
        $mainSearchedNo = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $normSearched);
        $mainSearchedNo = trim($mainSearchedNo) !== '' ? trim($mainSearchedNo) : $normSearched;
        if ($mainSearchedNo !== '' && $mainSearchedNo !== $normSearched) {
            $lifecycleFiles[$mainSearchedNo] = true;
        }
        foreach (array_keys($lifecycleFiles) as $fno) {
            $lifecycleMeta[$fno] = $this->resolveLifecycleFileMeta($conn, $fno);
        }

        // Authoritative block order (Rule 11): ancestors first — including TRANSITIVE grandparents,
        // which orderLifecycleFiles resolves by walking the whole successor chain. Handed to the JS so
        // the on-screen timeline matches the print report instead of re-deriving from the searched
        // file's DIRECT predecessors only (which missed the grandmother).
        $lifecycleOrder = $this->orderLifecycleFiles(array_keys($lifecycleFiles), $mainSearchedNo, $lifecycleMeta);

        return [
            'transactions' => $all,
            'lifecycle_meta' => $lifecycleMeta,
            'lifecycle_order' => $lifecycleOrder,
            'file_number_display' => $fileNumberDisplay,
            'under_investigation' => $investigation !== null,
            'is_wrc' => $isWrcFile,
            'investigation_note' => $investigation['note'] ?? null,
            'investigation_reason' => $investigation['reason'] ?? null,
            'investigation_dciv_file_number' => $investigation['dciv_file_number'] ?? null,
            'file_title' => $resolvedFileTitle !== '' ? $resolvedFileTitle : null,
            'file_location' => $fileIndexingData->location ?? null,
            'file_district' => $fileIndexingData->district ?? null,
            'file_lga' => $fileIndexingData->lga ?? null,
            'file_land_use' => (trim((string) ($fileIndexingData->land_use_type ?? '')) !== '')
                ? $fileIndexingData->land_use_type
                : $this->detectLandUseFromFileNumber($fileIndexingData->file_number ?? $fileNo),
            'file_plot_number' => $fileIndexingData->plot_number ?? null,
            'file_tp_no' => $fileIndexingData->tp_no ?? null,
            'file_lon_lat' => $fileLonLat,
            'file_size' => $fileSize,
            'file_ground_rent_amount' => $fileIndexingData->ground_rent_amount ?? null,
            'file_ground_rent_date' => $fileIndexingData->ground_rent_receipt_date ?? null,
            // Saved Term (Edit File Information). When present the UI shows it instead
            // of the term derived from land use, and the Residual Term derives from it.
            'file_term' => trim((string) ($fileIndexingData->term ?? '')) ?: null,
            'file_related_fileno' => $fileIndexingData->related_fileno ?? null,
            'file_index_number' => $fileIndexingData->file_number ?? null,
            // Whether the searched file has its own file_indexings row. Drives whether
            // the synthetic "File Commissioning" row is shown in the timeline — an
            // un-indexed file must not display one.
            'is_indexed' => $this->isFileIndexed($ownIndexedNumber ?? $fileNo),
            'file_temp_number' => $tempFileNumber,
            'file_history_count' => count($fileHistoryRecords),
            'cofo_count' => count($cofoRecords),
            'pra_count' => count($praRecords),
            'deed_count' => count($deedRecords),
            'total_count' => count($all),
            'file_commissioning_date' => $commissioningInfo['date'],
            'file_commissioned_number' => $commissioningInfo['number'],
            'file_commissioning_holder' => $this->resolveCommissioningHolder($conn, $fileNo),
            'lineage' => $this->enrichLineageWithCommissioning(
                $conn,
                $this->resolveFileLineage($conn, $fileNo),
                $fileNo
            ),
        ];
    }

    /**
     * Enrich a resolved lineage with per-file commissioning info so the timeline
     * can render the commissioning chain the client requires: searched file's
     * commissioning → history → subdivision/CoP/merger → successor commissioning.
     *
     * Adds:
     *  - previous_files: [{file_no, commissioning_date, file_title}, …] for each
     *    predecessor (informational only — predecessors get no commissioning row)
     *  - successor_files / successor_commissioning_date / successor_file_title
     *
     * The successor walk is RECURSIVE (breadth-first, generation by generation): a child
     * can itself be retired by a further land transaction, e.g. a Subdivision child
     * (CON-AG-2026-108) later retired by a Change of Purpose into CON-COM-2026-430. Each
     * successor therefore also carries its OWN decommission info, so the timeline can render
     * that generation's "File Decommissioning" row alongside the grandchild's commissioning.
     */
    private function enrichLineageWithCommissioning($conn, array $lineage, ?string $rootFileNo = null): array
    {
        $lineage['previous_files'] = [];
        foreach ($lineage['previous_file_nos'] as $prevNo) {
            $lineage['previous_files'][] = [
                'file_no' => $prevNo,
                'commissioning_date' => rescue(
                    fn () => $this->resolveCommissioningInfo($prevNo)['date'],
                    '-',
                    false
                ),
                'file_title' => $this->resolveFileTitleForNumber($conn, $prevNo),
            ];
        }

        // successor_file_no may be a CSV list (batch subdivision retires the mother
        // into several children at once) — resolve each successor separately so the
        // timeline can render one File Commissioning row per child instead of a
        // single row captioned with the raw comma-joined string.
        $lineage['successor_files'] = [];

        // Never revisit the searched file or a number already emitted — guards against a
        // cyclic decommission chain looping forever.
        $seen = [];
        if ($rootFileNo !== null && trim($rootFileNo) !== '') {
            $seen[strtoupper(trim($rootFileNo))] = true;
        }
        $splitFileNos = function ($csv): array {
            return array_values(array_filter(array_map('trim', explode(',', (string) $csv)), fn($v) => $v !== ''));
        };

        $generation = $splitFileNos($lineage['successor_file_no'] ?? '');
        $depth = 0;
        while (!empty($generation) && $depth < 10) {
            $next = [];
            foreach ($generation as $succ) {
                $key = strtoupper($succ);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                // The successor's own lineage: was IT later retired (e.g. by a Change of
                // Purpose), and into which file(s)?
                $succLineage = rescue(
                    fn () => $this->resolveFileLineage($conn, $succ),
                    [],
                    false
                );

                $lineage['successor_files'][] = [
                    'file_no' => $succ,
                    'commissioning_date' => rescue(
                        fn () => $this->resolveCommissioningInfo($succ)['date'],
                        '-',
                        false
                    ),
                    'file_title' => $this->resolveFileTitleForNumber($conn, $succ),
                    // This successor's own decommissioning (null/false when still active).
                    'is_superseded' => (bool) ($succLineage['is_superseded'] ?? false),
                    'decommission_file_no' => $succLineage['decommission_file_no'] ?? null,
                    'decommission_date' => $succLineage['decommission_date'] ?? null,
                    'decommission_reason' => $succLineage['decommission_reason'] ?? null,
                    'decommission_holder' => $succLineage['decommission_holder'] ?? null,
                    'decommission_event_type' => $succLineage['decommission_event_type'] ?? null,
                ];

                foreach ($splitFileNos($succLineage['successor_file_no'] ?? '') as $grandChild) {
                    $next[] = $grandChild;
                }
            }
            $generation = $next;
            $depth++;
        }
        if (!empty($lineage['successor_files'])) {
            $lineage['successor_commissioning_date'] = $lineage['successor_files'][0]['commissioning_date'];
            $lineage['successor_file_title'] = $lineage['successor_files'][0]['file_title'];
        }

        return $lineage;
    }

    /**
     * Best-effort file title/holder lookup for a bare file number
     * (file_indexings.file_title, else fileNumber.FileName, else the party
     * name on the file's own transaction — see below).
     */
    private function resolveFileTitleForNumber($conn, string $fileNo): ?string
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return null;
        }

        try {
            $variants = $this->fileNumberVariants($fileNo);

            $title = $conn->table('file_indexings')
                ->whereNull('deleted_at')
                ->whereIn('file_number', $variants)
                ->value('file_title');
            if (trim((string) $title) !== '') {
                return trim((string) $title);
            }

            $title = $conn->table('fileNumber')
                ->whereIn('mlsfNo', $variants)
                ->orderByDesc('id')
                ->value('FileName');
            if (trim((string) $title) !== '') {
                return trim((string) $title);
            }

            // A newly created lineage file (e.g. a subdivision/change-of-purpose
            // child) has no file_indexings or fileNumber record yet — its only
            // record is its own PRA/file-history transaction. Fall back to that
            // transaction's party (grantee before grantor), same heuristic used
            // for the searched file's own title.
            $schema = Schema::connection($conn->getName());
            foreach (['pra', 'file_history_staging'] as $table) {
                if (!$schema->hasTable($table)) {
                    continue;
                }
                $query = $conn->table($table)
                    ->where(function ($q) use ($variants) {
                        foreach ($variants as $v) {
                            $q->orWhere('mlsFNo', $v)->orWhere('fileno', $v);
                        }
                    });
                $this->applySoftDeleteFilter($query, $table);
                $row = $query->orderByDesc('id')->select('party_2', 'party_1')->first();
                if ($row) {
                    $name = trim((string) ($row->party_2 ?? '')) ?: trim((string) ($row->party_1 ?? ''));
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Title is cosmetic — never break search.
        }

        return null;
    }

    /**
     * Resolve the previous/current/successor lineage for a searched file so the UI can display
     * the complete file history chain instead of treating each commissioned file as unrelated.
     *
     *  - is_superseded / successor_file_no: set when the searched file was itself decommissioned
     *    by a land transaction (from decommissioned_files.successor_file_no).
     *  - previous_file_nos: the file(s) that came before this one — the parents recorded on the
     *    file's own file_indexings.related_fileno, plus any files superseded INTO this file.
     */
    private function resolveFileLineage($conn, string $fileNo): array
    {
        $lineage = [
            'is_superseded'        => false,
            'successor_file_no'    => null,
            'decommission_reason'  => null,
            'decommission_date'    => null,
            'decommission_holder'  => null,
            'decommission_file_no' => null,
            'decommission_event_type' => null,
            'previous_file_nos'    => [],
        ];

        if ($fileNo === '') {
            return $lineage;
        }

        $needle = strtoupper(trim($fileNo));
        $prev = [];

        try {
            $schema = Schema::connection($conn->getName());
            if ($schema->hasTable('decommissioned_files')) {
                $hasSuccessor = $schema->hasColumn('decommissioned_files', 'successor_file_no');

                // Was the searched file itself superseded?
                $dq = $conn->table('decommissioned_files')
                    ->where(function ($q) use ($needle) {
                        $q->whereRaw('UPPER(LTRIM(RTRIM(file_no))) = ?', [$needle])
                            ->orWhereRaw('UPPER(LTRIM(RTRIM(mls_file_no))) = ?', [$needle]);
                    });
                if ($schema->hasColumn('decommissioned_files', 'false_decommissioning')) {
                    $dq->where(function ($q) {
                        $q->where('false_decommissioning', 0)->orWhereNull('false_decommissioning');
                    });
                }
                $decRow = $dq->orderByDesc('id')->first();
                if ($decRow) {
                    $lineage['is_superseded'] = true;
                    $lineage['decommission_reason'] = $decRow->decommissioning_reason ?? null;
                    // Provenance (added 2026_07_21): 'parcel_update_new'/'title_status_update' show the
                    // real Date Decommissioned; 'backfill' (reconstructed lineage) shows a blank date.
                    $lineage['decommission_event_type'] = $decRow->event_type ?? null;
                    if ($hasSuccessor) {
                        $lineage['successor_file_no'] = $decRow->successor_file_no ?: null;
                    }
                    // Captured so the timeline can render a "File Decommissioning" row for the
                    // searched file: the date the file was retired, the holder name, and the
                    // exact stored file number (kept as-is for display fidelity).
                    $lineage['decommission_file_no'] = trim((string) ($decRow->file_no ?? '')) ?: null;
                    $lineage['decommission_holder'] = trim((string) ($decRow->file_name ?? '')) ?: null;
                    if (!empty($decRow->decommissioning_date)) {
                        $decDate = rescue(fn () => Carbon::parse($decRow->decommissioning_date), null, false);
                        if ($decDate) {
                            $lineage['decommission_date'] = $decDate->format('M j, Y');
                        }
                    }
                }

                // Files that were superseded INTO the searched file (its predecessors).
                if ($hasSuccessor) {
                    $preds = $conn->table('decommissioned_files')
                        ->whereRaw('UPPER(LTRIM(RTRIM(successor_file_no))) = ?', [$needle])
                        ->pluck('file_no')->all();
                    foreach ($preds as $p) {
                        $p = trim((string) $p);
                        if ($p !== '') {
                            $prev[strtoupper($p)] = $p;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Lineage is best-effort — never break search.
        }

        // Parents recorded on the file's own indexing row.
        try {
            $rel = $conn->table('file_indexings')
                ->whereNull('deleted_at')
                ->where('file_number', $fileNo)
                ->value('related_fileno');
            foreach ($this->parseRelatedFileno($rel) as $p) {
                if ($p !== '' && strcasecmp($p, $fileNo) !== 0) {
                    $prev[strtoupper($p)] = $p;
                }
            }
        } catch (\Throwable $e) {
        }

        $lineage['previous_file_nos'] = array_values($prev);

        return $lineage;
    }

    /**
     * Decode a related_fileno value that may be a JSON array, a CSV string, or a bare file number.
     */
    private function parseRelatedFileno($raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(
                    array_map(fn($v) => trim((string) $v), $decoded),
                    fn($v) => $v !== ''
                ));
            }
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== ''));
    }

    /**
     * Pull rows from related_file_number that match the searched file_number
     * OR any prop_id that's already in the result set, and turn each into a
     * synthetic timeline entry tagged as 'Related Fileno' / Recertification.
     */
    private function fetchRelatedRecertificationRows($conn, string $fileNo, array $existingRows): array
    {
        if (!Schema::connection($conn->getName())->hasTable('related_file_number')) {
            return [];
        }

        // Collect prop_ids already in the result set
        $propIds = [];
        foreach ($existingRows as $r) {
            $pid = trim((string) ($r['prop_id'] ?? ''));
            if ($pid !== '') {
                $propIds[$pid] = true;
            }
        }
        $propIds = array_keys($propIds);

        if ($fileNo === '' && empty($propIds)) {
            return [];
        }

        // Build the set of file numbers this search is "about": the searched file plus every
        // file number already resolved into the result set, each expanded to its base/"(T)"
        // variants. Recertification rows almost always carry an empty prop_id, so they can only
        // be reached by matching a stored endpoint string — matching against all of the
        // property's known numbers (not just the raw searched text) widens reach past exact
        // matches and format drift the file is otherwise known by.
        $candidateNumbers = [];
        if ($fileNo !== '') {
            $candidateNumbers[$fileNo] = true;
        }
        foreach ($existingRows as $r) {
            foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                $v = trim((string) ($r[$col] ?? ''));
                if ($v !== '') {
                    $candidateNumbers[$v] = true;
                }
            }
        }
        $candidates = $this->fileNumberVariants(...array_keys($candidateNumbers));

        if (empty($candidates) && empty($propIds)) {
            return [];
        }

        // Normalized set of the searched identifiers (uppercase, whitespace-collapsed, per-segment
        // leading zeros stripped) — used below to decide which endpoint of each link to display.
        $norm = function ($v) {
            $v = strtoupper(trim((string) $v));
            $v = preg_replace('/\s+/', ' ', $v);
            return preg_replace('/(?<=\s|-)0+(\d)/', '$1', $v);
        };
        $searchedSet = [];
        foreach ($candidates as $c) {
            $n = $norm($c);
            if ($n !== '') {
                $searchedSet[$n] = true;
            }
        }

        // The searched file's OWN identity (all number formats it is known by), as opposed to
        // $searchedSet which also contains ancestors pulled into the result set via prop_id
        // expansion. Built from the typed file number plus every number column of the existing
        // rows that DIRECTLY match it — so a file's MLS+KANGIS aliases are covered, but an
        // ancestor's numbers are not. Used below to reject sibling links (mother ↔ another
        // subdivision child) that only matched because the mother is present in the result set.
        $searchedOwnSet = [];
        if ($fileNo !== '') {
            $addOwn = function ($v) use (&$searchedOwnSet, $norm) {
                foreach ($this->fileNumberVariants($v) as $c) {
                    $n = $norm($c);
                    if ($n !== '') {
                        $searchedOwnSet[$n] = true;
                    }
                }
            };
            $numCols = ['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'];
            $addOwn($fileNo);
            foreach ($existingRows as $er) {
                $isOwn = false;
                foreach ($numCols as $col) {
                    $v = trim((string) ($er[$col] ?? ''));
                    if ($v !== '' && strcasecmp($v, $fileNo) === 0) {
                        $isOwn = true;
                        break;
                    }
                }
                if ($isOwn) {
                    foreach ($numCols as $col) {
                        $v = trim((string) ($er[$col] ?? ''));
                        if ($v !== '') {
                            $addOwn($v);
                        }
                    }
                }
            }
        }

        $query = $conn->table('related_file_number AS rfn')
            ->leftJoin('file_indexings AS fi_rel', function ($j) {
                $j->on('fi_rel.file_number', '=', 'rfn.related_fileno')
                  ->whereNull('fi_rel.deleted_at');
            })
            ->where(function ($q) use ($candidates, $propIds) {
                if (!empty($candidates)) {
                    $q->orWhereIn('rfn.file_number', $candidates)
                      ->orWhereIn('rfn.related_fileno', $candidates);
                }
                if (!empty($propIds)) {
                    $q->orWhereIn('rfn.prop_id', $propIds);
                }
            })
            ->select(
                'rfn.*',
                'fi_rel.file_title    AS related_file_title',
                'fi_rel.current_holder AS related_current_holder'
            );

        $rows = $query->orderBy('rfn.id')->get();

        // Second hop — a recertification of a newly-discovered ancestor. The query above only
        // matches links whose endpoint is one of the SEARCHED file's own numbers. A
        // recertification frequently sits one link further out: searching a Change-of-Purpose
        // child (e.g. CON-COM-2026-430) surfaces its mother (CON-AG-2014-35) through a
        // Subdivision link, but the KANGIS Recertification belongs to that mother
        // (CON-AG-2014-35 ↔ MLKN 2455), whose endpoints aren't in the searched candidate set —
        // so it never appears. Collect the endpoints just discovered that are NOT already
        // searched candidates (the ancestors), and pull in ONLY their Recertification links.
        // Restricting the extra hop to recertifications keeps the family's recert visible
        // without dragging in the ancestor's unrelated subdivisions/mergers.
        $discovered = [];
        foreach ($rows as $row) {
            foreach ([$row->file_number, $row->related_fileno] as $e) {
                foreach ($this->parseRelatedFileno($e) as $one) {
                    $one = trim($one);
                    if ($one !== '' && !isset($searchedSet[$norm($one)])) {
                        $discovered[$one] = true;
                    }
                }
            }
        }
        $discovered = array_keys($discovered);
        if (!empty($discovered)) {
            $extraVariants = $this->fileNumberVariants(...$discovered);
            if (!empty($extraVariants)) {
                $recertRows = $conn->table('related_file_number AS rfn')
                    ->leftJoin('file_indexings AS fi_rel', function ($j) {
                        $j->on('fi_rel.file_number', '=', 'rfn.related_fileno')
                          ->whereNull('fi_rel.deleted_at');
                    })
                    ->where(function ($q) use ($extraVariants) {
                        $q->whereIn('rfn.file_number', $extraVariants)
                          ->orWhereIn('rfn.related_fileno', $extraVariants);
                    })
                    ->where('rfn.transaction_type', 'like', '%Recertification%')
                    ->select(
                        'rfn.*',
                        'fi_rel.file_title    AS related_file_title',
                        'fi_rel.current_holder AS related_current_holder'
                    )
                    ->orderBy('rfn.id')->get();

                // Merge the recert rows in, de-duping by id against what was already fetched.
                $seenIds = [];
                foreach ($rows as $row) { $seenIds[$row->id] = true; }
                $merged = $rows->all();
                foreach ($recertRows as $rr) {
                    if (!isset($seenIds[$rr->id])) {
                        $seenIds[$rr->id] = true;
                        $merged[] = $rr;
                    }
                }
                $rows = collect($merged);
            }
        }

        // These synthetic rows have no transaction date of their own — related_file_number only
        // records when the LINK was created. Displaying that link-creation timestamp made every
        // recently-linked row read as "today" instead of the real transaction date. Borrow the
        // actual date from the linked file's pra history: gather each endpoint's transaction dates
        // (keyed by normalized file number) so the loop below can prefer a real event date.
        $endpointNos = [];
        foreach ($rows as $row) {
            foreach ([$row->file_number, $row->related_fileno] as $e) {
                foreach ($this->parseRelatedFileno($e) as $one) {
                    $one = trim($one);
                    if ($one !== '') { $endpointNos[$one] = true; }
                }
            }
        }
        $endpointNos = array_keys($endpointNos);

        // Endpoints decommissioned via the Manual Linkage tool: its "Manual Linkage: X -> Y"
        // reason names a successor but not a real transaction date, so the family/link-creation
        // date fallbacks below would be misleading here too (same reasoning as
        // fetchDecommissionLineageRows) — these must not display a Transaction Date either.
        $manualLinkageFiles = [];
        if (!empty($endpointNos) && Schema::connection($conn->getName())->hasTable('decommissioned_files')) {
            try {
                $mlQuery = $conn->table('decommissioned_files')
                    ->whereIn('file_no', $endpointNos)
                    ->where('decommissioning_reason', 'like', 'Manual Linkage:%');
                if (Schema::connection($conn->getName())->hasColumn('decommissioned_files', 'false_decommissioning')) {
                    $mlQuery->where(function ($q) {
                        $q->where('false_decommissioning', 0)->orWhereNull('false_decommissioning');
                    });
                }
                foreach ($mlQuery->pluck('file_no') as $mlFileNo) {
                    $k = $norm($mlFileNo);
                    if ($k !== '') { $manualLinkageFiles[$k] = true; }
                }
            } catch (\Throwable $e) { /* non-fatal: fall back to showing the date */ }
        }

        $praDates = [];
        if (!empty($endpointNos)) {
            try {
                $praRows = $conn->table('pra')
                    ->where(function ($q) use ($endpointNos) {
                        $q->whereIn('mlsFNo', $endpointNos)->orWhereIn('fileno', $endpointNos);
                    })
                    ->whereNotNull('transaction_date')
                    ->get(['mlsFNo', 'fileno', 'transaction_type', 'transaction_date']);
                foreach ($praRows as $pr) {
                    $d = trim((string) $pr->transaction_date);
                    if ($d === '') { continue; }
                    foreach (['mlsFNo', 'fileno'] as $col) {
                        $k = $norm($pr->$col ?? '');
                        if ($k === '') { continue; }
                        $praDates[$k][] = [
                            'type' => strtoupper(trim((string) $pr->transaction_type)),
                            'date' => $d,
                        ];
                    }
                }
            } catch (\Throwable $e) { /* non-fatal: fall back to link-creation date */ }
        }

        // Family transaction date: the most recent real transaction already on this timeline.
        // Used as the date for links that have no transaction date of their own AND no per-endpoint
        // pra date to borrow (e.g. a KANGIS Recertification, whose endpoints are an old KANGIS file
        // and the searched file) — such a link was established as part of the family's current
        // transaction, so it should read as that date, not the link-creation timestamp.
        $familyMaxSort = null;
        foreach ($existingRows as $er) {
            $sd = trim((string) ($er['sort_date'] ?? ''));
            if ($sd !== '' && ($familyMaxSort === null || strcmp($sd, $familyMaxSort) > 0)) {
                $familyMaxSort = $sd;
            }
        }

        $out = [];
        foreach ($rows as $row) {

            // Sibling guard. A specific file was searched, yet this related_file_number link has
            // BOTH endpoints pointing at OTHER files — it only matched because an ANCESTOR of the
            // searched file (e.g. the subdivision mother) is present in the result set, dragging in
            // the mother's links to her OTHER children (the searched file's siblings). Those
            // siblings are not this file's own relations, so drop the link. Recertification links
            // are exempt: an ancestor's recertification is surfaced deliberately for context (the
            // dedicated second hop above exists to reach exactly those).
            if ($fileNo !== '' && stripos((string) $row->transaction_type, 'RECERT') === false) {
                $touchesSearched = false;
                foreach ([$row->file_number, $row->related_fileno] as $e) {
                    foreach ($this->parseRelatedFileno($e) as $one) {
                        if (isset($searchedOwnSet[$norm($one)])) {
                            $touchesSearched = true;
                            break 2;
                        }
                    }
                }
                if (!$touchesSearched) {
                    continue;
                }
            }

            // Display the endpoint that is NOT the searched file. A link stores two endpoints —
            // file_number (parent) and related_fileno (counterpart). When the searched file is the
            // related_fileno side, echoing related_fileno back would show the user their own number;
            // flip to file_number so the linked parent is shown instead. When matched on the
            // file_number side (or via prop_id), keep related_fileno (unchanged behavior).
            $relatedNorm = $norm($row->related_fileno);
            $parentNorm  = $norm($row->file_number);
            $displaySource = $row->related_fileno;     // endpoint shown in the orange cell
            $otherSide     = $row->file_number;         // the linked counterpart (for context)
            $showParent    = false;
            if (isset($searchedSet[$relatedNorm]) && !isset($searchedSet[$parentNorm])
                && trim((string) $row->file_number) !== '') {
                $displaySource = $row->file_number;
                $otherSide     = $row->related_fileno;
                $showParent    = true;
            }

            // A KANGIS Recertification records the recertification of a KANGIS-legacy file
            // (e.g. "MLKN 2455") that also carries an MLS number (e.g. "CON-AG-2014-35"). The
            // event belongs to the KANGIS file, so the row must display the KANGIS-format
            // endpoint — never the MLS counterpart — regardless of which side was searched.
            // The generic flip above can't achieve this: when both endpoints are family numbers
            // it fires for neither, and the reciprocal-collapse below then prefers the
            // not-searched endpoint (the MLS number). Pin the displayed side to the KANGIS-format
            // endpoint here so both stored directions of the pair resolve to the same number.
            if (stripos((string) $row->transaction_type, 'KANGIS') !== false) {
                $looksKangis = fn ($v) => (bool) preg_match('/^[A-Z]{2,4}\s?\d{2,6}$/i', trim((string) $v));
                if ($looksKangis($row->related_fileno) && !$looksKangis($row->file_number)) {
                    $displaySource = $row->related_fileno;
                    $otherSide     = $row->file_number;
                    $showParent    = false;
                } elseif ($looksKangis($row->file_number) && !$looksKangis($row->related_fileno)) {
                    $displaySource = $row->file_number;
                    $otherSide     = $row->related_fileno;
                    $showParent    = true;
                }
            }

            // Prefer the real transaction date of the linked event over the link-creation
            // timestamp. Match a pra transaction on either endpoint whose type equals this link's
            // type (e.g. "Subdivision"); for an untyped link, fall back to the endpoints' most
            // recent real transaction date. Typed links with no pra counterpart (e.g. KANGIS
            // Recertification, which is not a pra transaction type) keep the link-creation date.
            $linkType = strtoupper(trim((string) $row->transaction_type));
            $candDates = [];
            foreach ([$norm($row->file_number), $norm($row->related_fileno)] as $k) {
                if ($k !== '' && isset($praDates[$k])) {
                    foreach ($praDates[$k] as $entry) { $candDates[] = $entry; }
                }
            }
            $chosenDate = null;
            if (!empty($candDates)) {
                $typed = array_values(array_filter(
                    $candDates,
                    fn ($e) => $linkType !== '' && $e['type'] === $linkType
                ));
                if (!empty($typed)) {
                    usort($typed, fn ($a, $b) => strcmp($a['date'], $b['date'])); // earliest match
                    $chosenDate = $typed[0]['date'];
                } elseif ($linkType === '') {
                    usort($candDates, fn ($a, $b) => strcmp($b['date'], $a['date'])); // most recent
                    $chosenDate = $candDates[0]['date'];
                }
            }
            // Prefer a real per-endpoint date; otherwise adopt the family's current transaction
            // date; only if neither exists fall back to the link-creation timestamp.
            $dateSource = $chosenDate ?: ($familyMaxSort ?: ($row->created_at ?? null));
            $sortDate = null;
            $displayDate = '-';
            if ($dateSource) {
                try {
                    $c = Carbon::parse($dateSource);
                    $sortDate = $c->toDateString();
                    $displayDate = $c->format('M j, Y');
                } catch (\Exception $e) { /* ignore */ }
            }

            // Title/holder of the endpoint being shown: the fi_rel join resolves the related_fileno
            // side, while file_title on the rfn row is the parent's. Prefer the side we display.
            $displayTitle = $showParent
                ? (($row->file_title ?? null) ?: $row->related_file_title ?: $row->related_current_holder ?: '-')
                : ($row->related_file_title ?: $row->related_current_holder ?: ($row->file_title ?? null) ?: '-');

            // A single related_file_number endpoint may carry several file numbers (the Manual
            // Linkage backfill stores a merger's sources as one CSV string, e.g.
            // "RES-2021-2865, RES-2021-2866, ..."). Emit one synthetic timeline row per
            // file number so each source displays on its own line.
            $relatedNos = $this->parseRelatedFileno($displaySource);
            if (empty($relatedNos)) {
                continue;
            }

            foreach ($relatedNos as $relNo) {
            // A related_fileno endpoint that points back at the searched file itself (any of its
            // own number formats) is redundant — the file is already represented by its own rows,
            // so it must never appear in the timeline as a separate "Related Fileno" row.
            if (isset($searchedOwnSet[$norm($relNo)])) {
                continue;
            }
            // Manual Linkage endpoints never get a real transaction date (see above) —
            // suppress the display value per-endpoint even though $displayDate/$sortDate were
            // computed once for the whole (possibly multi-file) related_file_number row.
            $relIsManualLinkage = isset($manualLinkageFiles[$norm($relNo)]);

            $txType = $this->recertDisplayLabel(
                $this->isKangisFormat($relNo) ? $relNo : $otherSide,
                $row->transaction_type
            );

            $commentVal = $row->comment ?: '-';
            if ($txType === 'Land Recertification (File Commissioning)') {
                $knFileNo = null;
                if ($this->isOldMlsKnFileNo($relNo)) {
                    $knFileNo = $relNo;
                } elseif ($this->isOldMlsKnFileNo($otherSide)) {
                    $knFileNo = $otherSide;
                } else {
                    $knFileNo = $this->resolveKnFileNoForLandFile($relNo) ?: $this->resolveKnFileNoForLandFile($otherSide);
                }
                if ($knFileNo) {
                    $commentVal = $knFileNo;
                }
            }

            $txDate = $relIsManualLinkage ? '-' : $displayDate;

            // A KANGIS Recertification's true date is NOT recorded anywhere: related_file_number
            // stores only the link, and "KANGIS Recertification" is not a pra transaction type, so
            // the resolution above can never find a real per-endpoint date for it. It therefore
            // falls through to $familyMaxSort — the family's most recent transaction — which prints
            // a confident but invented date (typically the KANGIS C of O's, making the two rows look
            // like one event). Print a dash instead: unknown must read as unknown. sort_date is left
            // intact so the row keeps its timeline position.
            if (stripos($txType, 'KANGIS Recertification') !== false) {
                $txDate = '-';
            }

            if ($txType === 'Land Recertification (File Commissioning)' && ($txDate === '-' || trim($txDate) === '')) {
                if (preg_match('/(?:^|[-_\/ ])(19\d{2}|20\d{2})(?:[-_\/ ]|$)/', $relNo, $matches)) {
                    $txDate = $matches[1];
                }
            }

            $out[] = [
                'id'                => $row->id,
                'file_number'       => $relNo, // orange-highlighted column
                'mlsFNo'            => $relNo,
                'fileno'            => $relNo,
                'kangisFileNo'      => null,
                'NewKANGISFileno'   => null,
                'transaction_type'  => $txType,
                'transaction_date'  => $txDate,
                'sort_date'         => $sortDate,
                // Title/holder of the endpoint shown in the orange cell (see $displayTitle above).
                // A deprecated_records fallback for any remaining blanks is applied after the loop.
                'party_1'           => $displayTitle,
                'party_2'           => $row->party_2 ?: '-',
                'party_3'           => '-',
                'party_4'           => '-',
                'land_use'          => '-',
                'location'          => $row->location ?: '-',
                'lgsaOrCity'        => '-',
                'districtName'      => '-',
                'registration'      => '-',
                'regNo'             => '-',
                'serial_no'         => '-',
                'page_no'           => '-',
                'volume_no'         => '-',
                'size'              => '-',
                'caveat'            => '-',
                'caveat_id'         => null,
                'caveated_comment'  => null,
                'is_caveated'       => 0,
                'plot_no'           => '-',
                'comments'          => $commentVal,
                'cofo_comment'      => null,
                'prop_id'           => $row->prop_id,
                'parent_prop_id'    => null,
                'deeds_date'        => null,
                'deeds_time'        => null,
                'reg_date'          => null,
                'reg_time'          => null,
                'tp_no'             => null,
                'source_table'      => 'Related Fileno',
                // The linked counterpart endpoint (the file on the other side of this link),
                // for context and the reciprocal-collapse pass below.
                'parent_file_number' => $otherSide,
            ];
            }
        }

        // Collapse reciprocal recertification pairs: the same link is often stored twice
        // (A -> B from file_indexings and B -> A from pra), and the two sources disagree on
        // zero-padding ("MLKN 2455" vs "MLKN 02455"), so the duplicate also fails its title
        // lookup. Compare endpoints zero-insensitively, keep one row per pair — preferring the
        // side that points AWAY from the searched file — and display the cleaner number.
        $normNo = function ($v) {
            $v = strtoupper(trim((string) $v));
            $v = preg_replace('/\s+/', ' ', $v);
            return preg_replace('/(?<=\s|-)0+(\d)/', '$1', $v); // strip leading zeros per segment
        };
        $searchedNorm = $normNo($fileNo);
        $pairGroups = [];
        foreach ($out as $i => $r) {
            $a = $normNo($r['fileno'] ?? '');
            $b = $normNo($r['parent_file_number'] ?? '');
            if ($a === '' || $b === '') {
                continue;
            }
            $key = ($a < $b) ? "$a|$b" : "$b|$a";
            $pairGroups[$key][] = $i;
        }
        $drop = [];
        foreach ($pairGroups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            // Pick the row to keep: prefer one whose displayed number is not the searched file.
            $keep = $indexes[0];
            foreach ($indexes as $i) {
                if ($searchedNorm !== '' && $normNo($out[$i]['fileno'] ?? '') !== $searchedNorm) {
                    $keep = $i;
                    break;
                }
            }
            // Canonicalise the kept row's displayed number: if a sibling row's parent_file_number
            // is the same file in a cleaner form (no stripped zeros), use that string.
            $keptNorm = $normNo($out[$keep]['fileno'] ?? '');
            $current = trim((string) ($out[$keep]['fileno'] ?? ''));
            if ($normNo($current) !== strtoupper($current)) { // current form contains padding
                foreach ($indexes as $i) {
                    $candidate = trim((string) ($out[$i]['parent_file_number'] ?? ''));
                    if ($candidate !== '' && $normNo($candidate) === $keptNorm
                        && strtoupper($candidate) === $normNo($candidate)) {
                        $out[$keep]['fileno'] = $candidate;
                        $out[$keep]['file_number'] = $candidate;
                        $out[$keep]['mlsFNo'] = $candidate;
                        break;
                    }
                }
            }
            foreach ($indexes as $i) {
                if ($i !== $keep) {
                    $drop[$i] = true;
                }
            }
        }
        if (!empty($drop)) {
            $out = array_values(array_diff_key($out, $drop));
        }

        // Canonicalise zero-padded numbers that had no clean reciprocal sibling to borrow from
        // (e.g. a lone "MLKN 02455" row): when the stripped form is a known file number anywhere,
        // display that instead — which also lets the title lookups below resolve.
        foreach ($out as $i => $r) {
            $current = trim((string) ($r['fileno'] ?? ''));
            if ($current === '') {
                continue;
            }
            $stripped = preg_replace('/(?<=\s|-)0+(\d)/', '$1', $current);
            if (strcasecmp($stripped, $current) === 0) {
                continue;
            }
            try {
                $known = $conn->table('file_indexings')->whereNull('deleted_at')->where('file_number', $stripped)->exists()
                    || $conn->table('fileNumber')->where(function ($q) use ($stripped) {
                            $q->where('mlsfNo', $stripped)->orWhere('kangisFileNo', $stripped);
                        })->exists()
                    || $conn->table('deprecated_records')->where('file_number', $stripped)->exists()
                    || $conn->table('decommissioned_files')->where('file_no', $stripped)->exists();
            } catch (\Throwable $e) {
                $known = false;
            }
            if ($known) {
                $out[$i]['fileno'] = $stripped;
                $out[$i]['file_number'] = $stripped;
                $out[$i]['mlsFNo'] = $stripped;
            }
        }

        // Fallback for any rows still missing party_1 (related file has neither a live indexing
        // row nor an rfn.file_title): pull the title/holder from the decommission archive.
        $missing = [];
        foreach ($out as $r) {
            if (($r['party_1'] ?? '-') === '-' && !empty($r['fileno'])) {
                $missing[strtoupper(trim((string) $r['fileno']))] = trim((string) $r['fileno']);
            }
        }
        if (!empty($missing) && Schema::connection($conn->getName())->hasTable('deprecated_records')) {
            $drRows = $conn->table('deprecated_records')
                ->whereIn('file_number', array_values($missing))
                ->orderBy('id')
                ->get(['file_number', 'file_title', 'current_holder', 'original_holder']);
            $map = [];
            foreach ($drRows as $d) {
                $k = strtoupper(trim((string) $d->file_number));
                if (!isset($map[$k])) {
                    $map[$k] = $d->file_title ?: ($d->current_holder ?: ($d->original_holder ?: null));
                }
            }
            foreach ($out as $i => $r) {
                if (($r['party_1'] ?? '-') === '-') {
                    $k = strtoupper(trim((string) ($r['fileno'] ?? '')));
                    if (!empty($map[$k])) {
                        $out[$i]['party_1'] = $map[$k];
                    }
                }
            }
        }

        // Last-resort fallback: the Manual Linkage backfill records the holder on
        // manual_file_linkages.applicant_name with the source files in old_file_numbers
        // (JSON). Covers sources that never had an indexing/archive row of their own.
        $stillMissing = [];
        foreach ($out as $r) {
            if (($r['party_1'] ?? '-') === '-' && !empty($r['fileno'])) {
                $stillMissing[strtoupper(trim((string) $r['fileno']))] = trim((string) $r['fileno']);
            }
        }
        if (!empty($stillMissing) && Schema::connection($conn->getName())->hasTable('manual_file_linkages')) {
            $mflRows = $conn->table('manual_file_linkages')
                ->where(function ($q) use ($stillMissing) {
                    foreach ($stillMissing as $no) {
                        $q->orWhere('old_file_numbers', 'like', '%' . $no . '%');
                    }
                })
                ->whereNotNull('applicant_name')
                ->where('applicant_name', '<>', '')
                ->orderBy('id')
                ->get(['old_file_numbers', 'applicant_name']);

            $mflMap = [];
            foreach ($mflRows as $m) {
                foreach ($this->parseRelatedFileno($m->old_file_numbers) as $no) {
                    $k = strtoupper(trim($no));
                    if ($k !== '' && !isset($mflMap[$k])) {
                        $mflMap[$k] = trim((string) $m->applicant_name);
                    }
                }
            }
            foreach ($out as $i => $r) {
                if (($r['party_1'] ?? '-') === '-') {
                    $k = strtoupper(trim((string) ($r['fileno'] ?? '')));
                    if (!empty($mflMap[$k])) {
                        $out[$i]['party_1'] = $mflMap[$k];
                    }
                }
            }
        }

        // These lineage markers describe a transition where the holder is unchanged,
        // so mirror the resolved name into Party 2 when it is empty.
        foreach ($out as $i => $r) {
            if (($r['party_2'] ?? '-') === '-' && ($r['party_1'] ?? '-') !== '-') {
                $out[$i]['party_2'] = $r['party_1'];
            }
        }

        // Collapse rows that are indistinguishable on screen. A single event (e.g. the mother
        // being subdivided into three children) is stored as three separate related_file_number
        // links, each flipping to display the same counterpart — producing three identical
        // "<mother> | Subdivision" rows. The reciprocal-collapse pass above can't merge them
        // because each is a distinct unordered pair. Since the displayed columns (file number,
        // type, parties, date) are identical, keep only the first of each visual group.
        $seenDisplay = [];
        $deduped = [];
        foreach ($out as $r) {
            $key = implode('|', [
                $normNo($r['fileno'] ?? ''),
                strtoupper(trim((string) ($r['transaction_type'] ?? ''))),
                strtoupper(trim((string) ($r['party_1'] ?? ''))),
                strtoupper(trim((string) ($r['party_2'] ?? ''))),
            ]);
            if (isset($seenDisplay[$key])) {
                continue;
            }
            $seenDisplay[$key] = true;
            $deduped[] = $r;
        }
        $out = $deduped;

        return $out;
    }

    /**
     * The neutral field set shared by synthetic lineage timeline rows, so each row carries
     * every column the timeline/report pipeline reads. Callers override what they need.
     */
    private function blankLineageRow(): array
    {
        return [
            'kangisFileNo'      => null,
            'NewKANGISFileno'   => null,
            'party_3'           => '-',
            'party_4'           => '-',
            'land_use'          => '-',
            'location'          => '-',
            'lgsaOrCity'        => '-',
            'districtName'      => '-',
            'registration'      => '-',
            'regNo'             => '-',
            'serial_no'         => '-',
            'page_no'           => '-',
            'volume_no'         => '-',
            'size'              => '-',
            'caveat'            => '-',
            'caveat_id'         => null,
            'caveated_comment'  => null,
            'is_caveated'       => 0,
            'plot_no'           => '-',
            'comments'          => '-',
            'cofo_comment'      => null,
            'prop_id'           => null,
            'parent_prop_id'    => null,
            'deeds_date'        => null,
            'deeds_time'        => null,
            'reg_date'          => null,
            'reg_time'          => null,
            'tp_no'             => null,
        ];
    }

    /**
     * Build synthetic timeline rows for the decommission events that PRODUCED the searched
     * file: every decommissioned_files row whose successor_file_no names the searched file
     * (exact member of the possibly-CSV successor list). E.g. viewing CON-COM-2026-430 shows
     * "CON-AG-2026-108 — Change of Purpose to CON-COM-2026-430" with the decommission date.
     * Files already displayed as Related Fileno rows (recertification links) are skipped to
     * avoid duplicates. Best-effort: returns [] on any failure.
     */
    private function fetchDecommissionLineageRows($conn, string $fileNo, array $existingRows): array
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return [];
        }

        try {
            $schema = Schema::connection($conn->getName());
            if (!$schema->hasTable('decommissioned_files')
                || !$schema->hasColumn('decommissioned_files', 'successor_file_no')) {
                return [];
            }

            // false_decommissioning = 1 rows are Title-Status flags (not real decommissions) —
            // excluded here for the same reason getDecommissionedFileNumbers() excludes them.
            $candidatesQuery = $conn->table('decommissioned_files')
                ->where('successor_file_no', 'like', '%' . $fileNo . '%');
            if ($schema->hasColumn('decommissioned_files', 'false_decommissioning')) {
                $candidatesQuery->where(function ($q) {
                    $q->where('false_decommissioning', 0)->orWhereNull('false_decommissioning');
                });
            }
            $candidates = $candidatesQuery->get(['id', 'file_no', 'file_name', 'decommissioning_date', 'decommissioning_reason', 'successor_file_no']);

            if ($candidates->isEmpty()) {
                return [];
            }

            // A predecessor may already have a typed Related Fileno row (e.g. a Merger or KANGIS
            // recert link from related_file_number). A Merger row doesn't convey the decommission
            // event itself (retiring authority, decommission date/reason), so both are kept. A
            // Recertification row (KANGIS / Land & Physical Planning) IS the file's own lifecycle
            // event for a KANGIS-legacy file, so the decommission row is redundant there and stays
            // suppressed.
            $recertified = [];
            foreach ($existingRows as $r) {
                if (($r['source_table'] ?? '') === 'Related Fileno'
                    && stripos((string) ($r['transaction_type'] ?? ''), 'Recertification') !== false) {
                    $v = strtoupper(trim((string) ($r['fileno'] ?? '')));
                    if ($v !== '') {
                        $recertified[$v] = true;
                    }
                }
            }

            $needle = strtoupper($fileNo);
            $out = [];
            foreach ($candidates as $d) {
                // successor_file_no may be a CSV list (batch subdivision) — require an exact
                // member match so LIKE cannot over-match similar numbers.
                $successors = array_map(fn($v) => strtoupper(trim($v)), explode(',', (string) $d->successor_file_no));
                if (!in_array($needle, $successors, true)) {
                    continue;
                }

                $oldNo = trim((string) $d->file_no);
                if ($oldNo === '' || isset($recertified[strtoupper($oldNo)])) {
                    continue;
                }

                // Labelled "File Decommissioning" to match the row shown when this predecessor
                // is searched directly (see the synthetic row built from $lineage['is_superseded']
                // below) — the specific event (Change of Purpose / Subdivision / Merger / ...)
                // stays visible in 'comments' via the raw decommissioning_reason text.
                $reason = trim((string) ($d->decommissioning_reason ?? ''));
                $label = 'File Decommissioning';

                // decommissioning_date is when the record was administratively archived
                // (often years after the real-world transaction it describes), not a genuine
                // transaction date — kept as sort_date for chronological placement, but not
                // shown in the Transaction Date column.
                $sortDate = null;
                if ($d->decommissioning_date) {
                    try {
                        $sortDate = Carbon::parse($d->decommissioning_date)->toDateString();
                    } catch (\Exception $e) { /* ignore */ }
                }

                // A file's life on the timeline runs commissioned → … → decommissioned. This
                // predecessor's decommissioning is being shown, so surface its File Commissioning
                // row too, directly ABOVE it — the pair belongs in the lineage block after the
                // searched file's own transactions, not floated to the top of the timeline (only
                // the SEARCHED file's commissioning opens the report).
                //
                // Pairing is achieved by giving this row the SAME sort-relevant fields as the
                // decommission row below (source_table, id, transaction_date, sort_date) and
                // emitting it first: both sorters key on weight → timestamp → id, and both are
                // stable (PHP 8 usort / ES2019 Array.sort), so an identical key preserves this
                // emission order. Legacy predecessors have no genuine KLAES commissioning date,
                // so the year embedded in the file number (CON-RES-2005-148 → 2005) is reported
                // in the comment instead.
                $commDate = rescue(
                    fn () => $this->resolveCommissioningInfo($oldNo)['date'],
                    '-',
                    false
                );
                if ($commDate === '-' || $commDate === '') {
                    $commDate = $this->extractYearFromFileNumber($oldNo) ?? '';
                }
                $commHolder = $this->resolveCommissioningHolder($conn, $oldNo)
                    ?: ($this->resolveFileTitleForNumber($conn, $oldNo) ?: (trim((string) $d->file_name) ?: '-'));

                $out[] = array_merge($this->blankLineageRow(), [
                    'id'                => (int) $d->id,
                    'file_number'       => $oldNo,
                    'mlsFNo'            => $oldNo,
                    'fileno'            => $oldNo,
                    'transaction_type'  => 'File Commissioning',
                    'transaction_date'  => $commDate !== '' ? $commDate : '-',
                    'sort_date'         => $sortDate,
                    'party_1'           => 'Kano State Ministry of Land and Physical Planning',
                    'party_2'           => $commHolder,
                    'comments'          => '-',
                    'source_table'      => 'Related Fileno',
                    'parent_file_number' => $fileNo,
                ]);

                $out[] = [
                    'id'                => (int) $d->id,
                    'file_number'       => $oldNo,
                    'mlsFNo'            => $oldNo,
                    'fileno'            => $oldNo,
                    'kangisFileNo'      => null,
                    'NewKANGISFileno'   => null,
                    'transaction_type'  => $label,
                    'transaction_date'  => '-',
                    'sort_date'         => $sortDate,
                    // Mirrors the self-decommission row: grantor is always the retiring
                    // authority, grantee is the file's holder at the time of decommission.
                    'party_1'           => 'Kano State Ministry of Land and Physical Planning',
                    'party_2'           => $d->file_name ?: '-',
                    'party_3'           => '-',
                    'party_4'           => '-',
                    'land_use'          => '-',
                    'location'          => '-',
                    'lgsaOrCity'        => '-',
                    'districtName'      => '-',
                    'registration'      => '-',
                    'regNo'             => '-',
                    'serial_no'         => '-',
                    'page_no'           => '-',
                    'volume_no'         => '-',
                    'size'              => '-',
                    'caveat'            => '-',
                    'caveat_id'         => null,
                    'caveated_comment'  => null,
                    'is_caveated'       => 0,
                    'plot_no'           => '-',
                    'comments'          => $reason !== '' ? $reason : '-',
                    'cofo_comment'      => null,
                    'prop_id'           => null,
                    'parent_prop_id'    => null,
                    'deeds_date'        => null,
                    'deeds_time'        => null,
                    'reg_date'          => null,
                    'reg_time'          => null,
                    'tp_no'             => null,
                    'source_table'      => 'Related Fileno',
                    'parent_file_number' => $fileNo,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * When the searched file is linked (as the related/child side) to a DCIV or LPCC
     * master file in master_dciv_links, surface that DCIV file as a synthetic
     * "DCIV File Commissioning" timeline row.
     *
     * Transaction Date resolution:
     *  - If the DCIV file number was actually generated through the "DCIV File Number
     *    Commissioning" page (/dciv/generation), a matching dciv_file_no row exists —
     *    its genuine commissioning_date is used.
     *  - Otherwise (e.g. a DCIV/LPCC number linked manually or migrated in without going
     *    through that page) there is no real commissioning date on record, so the year
     *    embedded in the DCIV file number itself (e.g. DCIV-2025-8 → 2025) is shown instead.
     */
    private function fetchDcivCommissioningRows($conn, string $fileNo, array $existingRows): array
    {
        try {
            $schema = Schema::connection($conn->getName());
            if (!$schema->hasTable('master_dciv_links')) {
                return [];
            }

            $candidates = [];
            $add = function ($v) use (&$candidates) {
                $v = strtoupper(trim((string) $v));
                if ($v !== '' && $v !== '-') {
                    $candidates[$v] = true;
                }
            };
            $add($fileNo);
            foreach ($existingRows as $r) {
                foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                    $add($r[$col] ?? null);
                }
            }
            $candidates = array_keys($candidates);
            if (empty($candidates)) {
                return [];
            }

            $links = $conn->table('master_dciv_links')
                ->whereIn(DB::raw('UPPER(LTRIM(RTRIM(related_file_number)))'), $candidates)
                ->whereNotNull('dciv_file_number')
                ->orderBy('id')
                ->get(['id', 'dciv_file_number', 'dciv_reason']);

            if ($links->isEmpty()) {
                return [];
            }

            $out = [];
            $seen = [];
            foreach ($links as $link) {
                $dcivNo = trim((string) $link->dciv_file_number);
                if ($dcivNo === '') {
                    continue;
                }
                $key = strtoupper($dcivNo);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                // Title is informational only — dciv_file_no.file_name first, then the
                // file's own indexing title.
                $title = trim((string) $conn->table('dciv_file_no')
                    ->whereRaw('UPPER(LTRIM(RTRIM(full_file_number))) = ?', [$key])
                    ->where(function ($q) {
                        $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                    })
                    ->value('file_name'));
                if ($title === '') {
                    $title = (string) $conn->table('file_indexings')
                        ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$key])
                        ->value('file_title');
                }

                // dciv_file_no.commissioning_date is NOT trusted on its own — many DCIV/LPCC
                // rows there were backfilled/migrated with a timestamp that has nothing to do
                // with a genuine commissioning event. Reuse the same authoritative check used
                // for the searched file's own "File Commissioning" row (mls_file_no, then the
                // legacy fileNumber "MLS_Commissioned%" record, then a year-matched indexing
                // date) so a DCIV number only shows a real date when one genuinely exists.
                $commissioningInfo = $this->resolveCommissioningInfo($dcivNo);
                $displayDate = $commissioningInfo['date'];
                $sortDate = null;
                if ($displayDate !== '-') {
                    $sortDate = rescue(fn () => Carbon::parse($displayDate)->toDateString(), null, false);
                }
                if ($displayDate === '-') {
                    $year = $this->extractYearFromFileNumber($dcivNo);
                    if ($year) {
                        $displayDate = $year;
                        $sortDate = $year . '-01-01';
                    }
                }

                $out[] = [
                    'id'                => (int) $link->id,
                    'file_number'       => $dcivNo,
                    'mlsFNo'            => $dcivNo,
                    'fileno'            => $dcivNo,
                    'kangisFileNo'      => null,
                    'NewKANGISFileno'   => null,
                    'transaction_type'  => 'DCIV File Commissioning',
                    'transaction_date'  => $displayDate,
                    'sort_date'         => $sortDate,
                    'party_1'           => 'Kano State Ministry of Land and Physical Planning',
                    'party_2'           => $title !== '' ? $title : '-',
                    'party_3'           => '-',
                    'party_4'           => '-',
                    'land_use'          => '-',
                    'location'          => '-',
                    'lgsaOrCity'        => '-',
                    'districtName'      => '-',
                    'registration'      => '-',
                    'regNo'             => '-',
                    'serial_no'         => '-',
                    'page_no'           => '-',
                    'volume_no'         => '-',
                    'size'              => '-',
                    'caveat'            => '-',
                    'caveat_id'         => null,
                    'caveated_comment'  => null,
                    'is_caveated'       => 0,
                    'plot_no'           => '-',
                    'comments'          => trim((string) ($link->dciv_reason ?? '')) ?: '-',
                    'cofo_comment'      => null,
                    'prop_id'           => null,
                    'parent_prop_id'    => null,
                    'deeds_date'        => null,
                    'deeds_time'        => null,
                    'reg_date'          => null,
                    'reg_time'          => null,
                    'tp_no'             => null,
                    'source_table'      => 'DCIV File Commissioning',
                    'parent_file_number' => $fileNo,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Sectional Titling (ST) commissioning lifecycle rows.
     *
     * When the searched file is an ST file — a primary ST file number such as
     * ST-COM-2025-5, or one of its commissioned unit files ST-COM-2025-5-001 —
     * surface the scheme's commissioning lifecycle as synthetic timeline rows:
     *   - one "ST File Commissioning" row for the PRIMARY application, and
     *   - one "ST File Commissioning – Fragmentation" row per commissioned unit.
     *
     * Scope (per client spec):
     *   - Searching the PRIMARY  → primary commissioning + every commissioned unit.
     *   - Searching a UNIT       → primary commissioning + THAT unit only (siblings
     *     excluded, mirroring the standard subdivided-unit rule).
     *
     * Authoritative source: st_file_numbers. `np_fileno` groups a scheme;
     * `file_no_type` distinguishes PRIMARY from PUA/SUA units; `date_commissioned`
     * is the genuine ST commissioning date. When no date_commissioned is on record
     * the row falls back to the bare year embedded in the file number.
     */
    /**
     * ST scheme file numbers whose TRANSACTIONS must be pulled into a mother/scheme
     * search: the ST scheme number (np_fileno) — so deed_registrations.parent_fileno
     * matches surface unit deeds — plus every commissioned unit file number. Returns []
     * for a non-ST file OR for a UNIT search (a unit already pulls its own transactions
     * by its own number; siblings are deliberately excluded).
     */
    private function resolveStRelatedFileNos($conn, string $fileNo): array
    {
        try {
            $schema = Schema::connection($conn->getName());
            if (!$schema->hasTable('st_file_numbers')) {
                return [];
            }
            $searched = strtoupper(trim($fileNo));
            if ($searched === '') {
                return [];
            }

            $match = $conn->table('st_file_numbers')
                ->where(function ($q) use ($searched) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(np_fileno))) = ?', [$searched])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(fileno))) = ?', [$searched])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(mls_fileno))) = ?', [$searched]);
                })
                ->orderByRaw("CASE WHEN UPPER(file_no_type) = 'PRIMARY' THEN 0 ELSE 1 END")
                ->get();
            if ($match->isEmpty()) {
                return [];
            }

            $primaryNo = trim((string) ($match->first()->np_fileno ?? ''));
            if ($primaryNo === '') {
                return [];
            }

            // A UNIT search (searched number is a non-PRIMARY unit's own fileno) adds
            // nothing — sibling exclusion; the unit's own number already pulls its rows.
            foreach ($match as $m) {
                $type = strtoupper((string) ($m->file_no_type ?? ''));
                $fn = strtoupper(trim((string) ($m->fileno ?? '')));
                if ($type !== 'PRIMARY' && $fn === $searched && $fn !== strtoupper($primaryNo)) {
                    return [];
                }
            }

            // Mother/scheme search: scheme number + every commissioned unit number.
            $related = [$primaryNo];
            $units = $conn->table('st_file_numbers')
                ->whereRaw('UPPER(LTRIM(RTRIM(np_fileno))) = ?', [strtoupper($primaryNo)])
                ->whereIn(DB::raw('UPPER(file_no_type)'), ['PUA', 'SUA'])
                ->whereIn(DB::raw('UPPER(status)'), ['ACTIVE', 'USED'])
                ->whereNotNull('fileno')
                ->pluck('fileno');
            foreach ($units as $u) {
                $u = trim((string) $u);
                if ($u !== '' && strtoupper($u) !== strtoupper($primaryNo)) {
                    $related[] = $u;
                }
            }
            return array_values(array_unique($related));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchStCommissioningRows($conn, string $fileNo, array $existingRows): array
    {
        try {
            $schema = Schema::connection($conn->getName());
            if (!$schema->hasTable('st_file_numbers')) {
                return [];
            }

            $searched = strtoupper(trim($fileNo));
            if ($searched === '') {
                return [];
            }

            // Locate the searched file in st_file_numbers. An ST scheme file may be
            // identified by ANY of its numbers — the ST np_fileno (ST-RES-2026-2), the
            // applied/MLS number (RES-2025-115), or a unit's fileno — so match on all
            // three. (The old ST- prefix gate missed primaries stored under their MLS
            // number.) PRIMARY rows sort first so first() yields the scheme's primary.
            $match = $conn->table('st_file_numbers')
                ->where(function ($q) use ($searched) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(np_fileno))) = ?', [$searched])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(fileno))) = ?', [$searched])
                      ->orWhereRaw('UPPER(LTRIM(RTRIM(mls_fileno))) = ?', [$searched]);
                })
                ->orderByRaw("CASE WHEN UPPER(file_no_type) = 'PRIMARY' THEN 0 ELSE 1 END")
                ->get();

            if ($match->isEmpty()) {
                return [];
            }

            // The scheme's primary ST file number.
            $primaryNo = trim((string) ($match->first()->np_fileno ?? ''));
            if ($primaryNo === '') {
                return [];
            }

            // Classify the searched file: does it resolve to the PRIMARY record itself
            // (by ANY of its numbers), or to a specific unit? A unit search emits only
            // that unit's fragmentation row (sibling exclusion).
            $searchedMatchesPrimary = false;
            $searchedUnitNo = null;
            foreach ($match as $m) {
                $type = strtoupper((string) ($m->file_no_type ?? ''));
                $np = strtoupper(trim((string) ($m->np_fileno ?? '')));
                $fn = strtoupper(trim((string) ($m->fileno ?? '')));
                $mls = strtoupper(trim((string) ($m->mls_fileno ?? '')));
                if ($type === 'PRIMARY' && ($np === $searched || $fn === $searched || $mls === $searched)) {
                    $searchedMatchesPrimary = true;
                } elseif ($type !== 'PRIMARY' && $fn === $searched && $fn !== strtoupper($primaryNo)) {
                    $searchedUnitNo = trim((string) $m->fileno);
                }
            }

            // Avoid duplicating rows already present in the result set.
            $existing = [];
            foreach ($existingRows as $r) {
                $t = strtoupper(trim((string) ($r['transaction_type'] ?? '')));
                foreach (['fileno', 'file_number', 'mlsFNo'] as $col) {
                    $v = strtoupper(trim((string) ($r[$col] ?? '')));
                    if ($v !== '') {
                        $existing[$t . '|' . $v] = true;
                    }
                }
            }

            $rowId = -900000; // synthetic negative ids, distinct from other synth rows

            $buildRow = function (string $rowFileNo, string $label, $stRow, array $extra = []) use (&$rowId) {
                $displayDate = '-';
                $sortDate = null;
                $commDate = $stRow->date_commissioned ?? null;
                if (!empty($commDate)) {
                    $parsed = rescue(fn () => Carbon::parse($commDate), null, false);
                    if ($parsed) {
                        $displayDate = $parsed->format('M j, Y');
                        $sortDate = $parsed->toDateString();
                    }
                }
                if ($displayDate === '-') {
                    $year = $this->extractYearFromFileNumber($rowFileNo);
                    if ($year) {
                        $displayDate = $year;
                        $sortDate = $year . '-01-01';
                    }
                }

                $holder = trim((string) ($stRow->file_name ?? ''));
                if ($holder === '') {
                    $holder = trim(implode(' ', array_filter([
                        $stRow->applicant_title ?? null,
                        $stRow->surname ?? null,
                        $stRow->first_name ?? null,
                        $stRow->middle_name ?? null,
                        $stRow->corporate_name ?? null,
                    ], static fn ($v) => trim((string) $v) !== '')));
                }

                $base = [
                    'id'                => $rowId--,
                    'file_number'       => $rowFileNo,
                    'mlsFNo'            => $rowFileNo,
                    'fileno'            => $rowFileNo,
                    'kangisFileNo'      => null,
                    'NewKANGISFileno'   => null,
                    'transaction_type'  => $label,
                    'transaction_date'  => $displayDate,
                    'sort_date'         => $sortDate,
                    'party_1'           => 'Kano State Ministry of Land and Physical Planning',
                    'party_2'           => $holder !== '' ? $holder : '-',
                    'party_3'           => '-',
                    'party_4'           => '-',
                    'land_use'          => trim((string) ($stRow->land_use ?? '')) ?: '-',
                    'location'          => '-',
                    'lgsaOrCity'        => '-',
                    'districtName'      => '-',
                    'registration'      => '-',
                    'regNo'             => '-',
                    'serial_no'         => '-',
                    'page_no'           => '-',
                    'volume_no'         => '-',
                    'size'              => '-',
                    'caveat'            => '-',
                    'caveat_id'         => null,
                    'caveated_comment'  => null,
                    'is_caveated'       => 0,
                    'plot_no'           => '-',
                    'comments'          => '-',
                    'cofo_comment'      => null,
                    'prop_id'           => null,
                    'parent_prop_id'    => null,
                    'deeds_date'        => null,
                    'deeds_time'        => null,
                    'reg_date'          => null,
                    'reg_time'          => null,
                    'tp_no'             => null,
                    'source_table'      => 'ST File Commissioning',
                    '_synthesized'      => true,
                    'parent_file_number' => $rowFileNo,
                ];
                return array_merge($base, $extra);
            };

            $out = [];

            // 1. PRIMARY commissioning row. Always resolve the scheme's actual PRIMARY
            // record so its commissioning date is consistent regardless of whether the
            // primary or one of its units was searched. SUA schemes carry no PRIMARY
            // st_file_numbers row, so the orderBy fallback yields the scheme's own record.
            $primaryRow = $match->firstWhere('file_no_type', 'PRIMARY');
            if (!$primaryRow) {
                $primaryRow = $conn->table('st_file_numbers')
                    ->whereRaw('UPPER(LTRIM(RTRIM(np_fileno))) = ?', [strtoupper($primaryNo)])
                    ->orderByRaw("CASE WHEN UPPER(file_no_type) = 'PRIMARY' THEN 0 ELSE 1 END")
                    ->first();
            }
            if (!$primaryRow) {
                $primaryRow = $match->first();
            }

            // The land ("mother") file number is the primary's applied/MLS number
            // (e.g. RES-2025-115) — the number a Land File Commissioning row is keyed on.
            // The mother has TWO commissioning events that must BOTH display, in order:
            //   Land File Commissioning (mls_fileno) → transactions → ST File Commissioning
            //   (np_fileno) → transactions. So the ST primary row is DISPLAYED on its ST
            // number ($primaryNo) but tagged to the land file's lifecycle group
            // (lifecycle_file_no) so it folds INTO the mother block, and flagged
            // (_st_primary_commissioning + _pinned) so the frontend places it by DATE among
            // the transactions instead of hoisting it to the top with the land commissioning.
            $landFileNo = trim((string) ($primaryRow->mls_fileno ?? '')) ?: trim((string) ($primaryRow->fileno ?? '')) ?: $primaryNo;
            if (!isset($existing['ST FILE COMMISSIONING|' . strtoupper($primaryNo)])) {
                $out[] = $buildRow($primaryNo, 'ST File Commissioning', $primaryRow, [
                    'lifecycle_file_no'         => $landFileNo,
                    'parent_file_number'        => $landFileNo,
                    '_st_primary_commissioning' => true,
                    '_pinned'                   => true,
                ]);
            }

            // 2. Fragmentation rows for commissioned units.
            $unitsQuery = $conn->table('st_file_numbers')
                ->whereRaw('UPPER(LTRIM(RTRIM(np_fileno))) = ?', [strtoupper($primaryNo)])
                ->whereIn(DB::raw('UPPER(file_no_type)'), ['PUA', 'SUA'])
                ->whereIn(DB::raw('UPPER(status)'), ['ACTIVE', 'USED'])
                ->whereNotNull('fileno');

            if ($searchedUnitNo !== null) {
                // Unit search → only the searched unit (sibling exclusion).
                $unitsQuery->whereRaw('UPPER(LTRIM(RTRIM(fileno))) = ?', [strtoupper($searchedUnitNo)]);
            }

            $units = $unitsQuery->orderBy('unit_sequence')->get();
            $seenUnits = [];
            foreach ($units as $u) {
                $unitNo = trim((string) $u->fileno);
                if ($unitNo === '' || strtoupper($unitNo) === strtoupper($primaryNo)) {
                    continue;
                }
                $key = strtoupper($unitNo);
                if (isset($seenUnits[$key])) {
                    continue;
                }
                $seenUnits[$key] = true;
                if (isset($existing['ST FILE COMMISSIONING – FRAGMENTATION|' . $key])) {
                    continue;
                }
                $out[] = $buildRow($unitNo, 'ST File Commissioning – Fragmentation', $u);
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function emptyResult(): array
    {
        return [
            'transactions' => [],
            'under_investigation' => false,
            'investigation_note' => null,
            'investigation_reason' => null,
            'investigation_dciv_file_number' => null,
            'file_title' => null,
            'file_location' => null,
            'file_district' => null,
            'file_lga' => null,
            'file_land_use' => null,
            'file_plot_number' => null,
            'file_tp_no' => null,
            'file_lon_lat' => null,
            'file_size' => null,
            'file_ground_rent_amount' => null,
            'file_ground_rent_date' => null,
            'file_term' => null,
            'file_related_fileno' => null,
            'file_index_number' => null,
            'file_history_count' => 0,
            'cofo_count' => 0,
            'pra_count' => 0,
            'deed_count' => 0,
            'total_count' => 0,
            'file_commissioning_date' => '-',
            'file_commissioned_number' => null,
        ];
    }

    /**
     * Resolve a file's commissioning date for the default "File Commissioning" /
     * "Temporary File" timeline records. Returns both the formatted date and the
     * exact file number (fileNumber.mlsfNo) that was commissioned, so the UI can
     * attach the date to the correct row — a "(T)" temporary file that was
     * commissioned must show the date on its own row, not on the permanent one.
     *
     * Returns ['date' => 'M j, Y'|'-', 'number' => string|null]. The date is
     * populated when the file was commissioned within KLAES and has a genuine
     * stored commissioning_date. A KLAES commissioning may legitimately postdate
     * the file's transactions — old files are recommissioned into the system
     * long after their history — so the stored date is always trusted.
     *
     * mls_file_no.full_file_number is the authoritative record of every file
     * commissioned through KLAES regardless of module (MLS commissioning, File
     * Indexing, etc.), so it is checked first. The legacy fileNumber table
     * (SOURCE starting with 'MLS_Commissioned') is checked next for older
     * records that predate mls_file_no. file_indexings.created_at — the date
     * the file actually entered KLAES — is the final fallback so the timeline
     * still shows a real date rather than nothing. Only when none of the three
     * has anything on record (truly legacy files) does the caller fall back
     * further to the bare year embedded in the file number.
     */
    private function resolveCommissioningInfo(?string $fileNumber, ?string $altFileNo = null): array
    {
        $default = ['date' => '-', 'number' => null];

        // Match ALL variants of the file number (base, base(T)), not just the two
        // exact strings passed in — a temporary file may be commissioned under its
        // "(T)" number while the search/index number is the base (or vice versa).
        $candidates = $this->fileNumberVariants($fileNumber, $altFileNo);
        if (empty($candidates)) {
            return $default;
        }

        // 1. mls_file_no — authoritative source for every file commissioned in
        // KLAES, including ones (like File Indexing) that never get a matching
        // 'MLS_Commissioned%' row in the legacy fileNumber table.
        $mlsRecord = DB::connection('sqlsrv')->table('mls_file_no')
            ->whereIn('full_file_number', $candidates)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->first(['full_file_number', 'commissioning_date']);

        // Presence in mls_file_no is THE authoritative signal that a file was
        // commissioned through KLAES. A file absent from mls_file_no was NOT
        // KLAES-commissioned — its File Commissioning row must show only the bare
        // year embedded in the file number (the caller's fallback), never a legacy
        // fileNumber or file_indexings date. So bail out to year-only here.
        if (!$mlsRecord) {
            return $default;
        }

        $number = trim((string) $mlsRecord->full_file_number) ?: null;

        if ($mlsRecord->commissioning_date) {
            $date = rescue(fn () => \Carbon\Carbon::parse($mlsRecord->commissioning_date), null, false);
            if ($date) {
                return ['date' => $date->format('M j, Y'), 'number' => $number];
            }
        }

        // 2. Legacy fileNumber table (SOURCE starts with 'MLS_Commissioned') for
        // older records that predate mls_file_no.
        $record = DB::connection('sqlsrv')->table('fileNumber')
            ->whereIn('mlsfNo', $candidates)
            ->where('SOURCE', 'like', 'MLS_Commissioned%')
            ->orderByDesc('id')
            ->first(['SOURCE', 'mlsfNo', 'commissioning_date', 'created_at']);

        if ($record) {
            $number = $number ?: (trim((string) $record->mlsfNo) ?: null);

            // Only a genuine, stored commissioning_date is trustworthy. Do NOT fall back
            // to fileNumber.created_at — for a legacy file digitized into KLAES that is
            // merely the digitization timestamp, not the date the land was actually
            // commissioned.
            if ($record->commissioning_date) {
                $date = rescue(fn () => \Carbon\Carbon::parse($record->commissioning_date), null, false);
                if ($date) {
                    return ['date' => $date->format('M j, Y'), 'number' => $number];
                }
            }
        }

        // 3. No genuine commissioning date anywhere. Fall back to the date the
        // file was indexed into KLAES (file_indexings.created_at) — but ONLY
        // when the file was commissioned as a new file at indexing time, i.e.
        // the year embedded in the file number matches the indexing year. For
        // a legacy file (e.g. RES-2001-… indexed in 2026) created_at is merely
        // the digitization timestamp — the client requires those to stay blank,
        // so the caller falls back to the bare year from the file number.
        $indexedAt = DB::connection('sqlsrv')->table('file_indexings')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $q->orWhere('file_number', $candidate)
                        ->orWhere('temp_file_no', $candidate);
                }
            })
            ->orderByDesc('id')
            ->value('created_at');

        if ($indexedAt) {
            $date = rescue(fn () => \Carbon\Carbon::parse($indexedAt), null, false);
            $embeddedYear = $this->extractYearFromFileNumber($fileNumber)
                ?? $this->extractYearFromFileNumber($altFileNo);
            if ($date && $embeddedYear && (int) $date->format('Y') === (int) $embeddedYear) {
                return ['date' => $date->format('M j, Y'), 'number' => $number];
            }
        }

        return ['date' => '-', 'number' => $number];
    }

    /**
     * Resolve the "commissioning holder" name for the File Commissioning / Temporary File
     * rows: the assignor (Party 1) of the file's Deed of Assignment registered through KLAES
     * (instrument capture). At commissioning the file belonged to the original allottee, who
     * is precisely the ASSIGNOR of the first KLAES-captured Deed of Assignment — the file
     * title, by contrast, reflects the latest owner (the assignee). Matching the searched
     * file's number variants (base and "(T)"). Returns the grantor name, or null when the
     * file has no KLAES-registered Deed of Assignment.
     */
    private function resolveCommissioningHolder($conn, ?string $fileNo): ?string
    {
        // TEMPORARILY DISABLED: do not derive the File Commissioning holder from the
        // grantor of the earliest Deed of Assignment. Returning null makes every call
        // site fall back to the file title (latest owner) instead. Remove this early
        // return to restore the assignor-based "original holder" behaviour.
        return null;

        $variants = $this->fileNumberVariants($fileNo);
        if (empty($variants)) {
            return null;
        }

        $row = $conn->table('deed_registrations')
            ->whereIn('fileno', $variants)
            ->whereNotNull('instrument_capture_id')
            ->whereRaw("UPPER(instrument_type) LIKE '%DEED OF ASSIGNMENT%'")
            ->when(Schema::hasColumn('deed_registrations', 'is_deleted'), function ($q) {
                $q->where(function ($qq) {
                    $qq->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
            })
            ->orderByRaw('TRY_CONVERT(DATE, deeds_date) ASC')
            ->orderBy('id')
            ->first(['grantor']);

        $holder = trim((string) ($row->grantor ?? ''));
        return $holder !== '' ? $holder : null;
    }

    /**
     * Place the synthetic commissioning rows into a print-report row list
     * according to the lineage chain:
     *
     *   searched file's commissioning (+ its "(T)" temp row) → history →
     *   subdivision/CoP/merger row → successor commissioning (after the
     *   parcel-update row that retired the searched file).
     *
     * Only the SEARCHED file gets a "File Commissioning" row at the top.
     * Predecessor ("mother") files appear through their real transactions
     * (CofO, recertification, parcel-update rows) but never as a synthetic
     * commissioning row — the client flagged a predecessor commissioning row
     * (MLKN 2455) as misleading when it was not the searched number.
     */
    private function placeCommissioningRows(
        array $rows,
        array $commissioningRow,
        ?array $tempRow,
        array $lineage
    ): array {
        $norm = fn ($v) => strtoupper(preg_replace(
            '/\s+/',
            '',
            preg_replace('/\(\s*T\s*\)\s*$/i', '', (string) $v)
        ));

        $isParcelUpdateRow = function (array $row): bool {
            $source = (string) ($row['source_table'] ?? '');
            if (in_array($source, ['File Commissioning', 'Temporary File'], true)) {
                return false;
            }
            return (bool) preg_match(
                '/subdivision|merger|change of purpose|plot extension|separation|parcel update/i',
                (string) ($row['instrument_type'] ?? '')
            );
        };

        // Print-format commissioning row for a successor lineage file.
        // Real commissioning date prints in Reg Date (like the searched file's own
        // row); a legacy file with no date on record falls back to the year
        // embedded in its file number as the Transaction Date.
        $makeLineageRow = function (string $no, ?string $date, ?string $title): ?array {
            $no = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $no));
            if ($no === '') {
                return null;
            }
            $regDate = ($date && $date !== '-') ? $date : '-';
            $txnDate = $regDate === '-' ? ($this->extractYearFromFileNumber($no) ?? '-') : '-';
            return [
                'sn' => 0, // renumbered by the caller
                'file_no' => $no,
                'grantor' => 'Kano State Ministry of Land and Physical Planning',
                'grantee' => trim((string) $title) !== '' ? trim((string) $title) : '-',
                'party_3' => '-',
                'party_4' => '-',
                'instrument_type' => 'File Commissioning',
                'transaction_date' => $txnDate,
                'reg_time' => '-',
                'reg_date' => $regDate,
                'reg_no' => '0/0/0',
                'size' => '-',
                'caveat' => 'No',
                'comments' => '-',
                'source_table' => 'File Commissioning',
                'location' => '',
            ];
        };

        $searchedPair = $tempRow ? [$commissioningRow, $tempRow] : [$commissioningRow];
        $searchedKey = $norm($commissioningRow['file_no'] ?? '');

        // The searched file's commissioning always opens the report. Predecessors
        // (lineage previous_files) get NO synthetic commissioning row.
        $rows = array_merge($searchedPair, $rows);

        // "File Decommissioning" row — when the searched file has itself been decommissioned,
        // its own history ends at the decommission. Insert the row immediately BEFORE the first
        // parcel-update row (the Subdivision / CoP / Merger event that retired it), i.e. right
        // after the file's last own transaction, so the successor commissioning rows below land
        // after the parcel-update row.
        if (!empty($lineage['is_superseded'])) {
            $decNo = trim((string) ($lineage['decommission_file_no'] ?? ''))
                ?: trim((string) ($commissioningRow['file_no'] ?? ''));
            $decNo = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $decNo));
            if ($decNo !== '') {
                $decDate = $this->decommissionDisplayDate(
                    $lineage['decommission_event_type'] ?? null,
                    $lineage['decommission_date'] ?? '-'
                );
                $decHolder = trim((string) ($lineage['decommission_holder'] ?? ''));
                if ($decHolder === '') {
                    $decHolder = trim((string) ($commissioningRow['grantee'] ?? ''));
                }
                $decommissioningRow = [
                    'sn' => 0, // renumbered by the caller
                    'file_no' => $decNo,
                    'grantor' => 'Kano State Ministry of Land and Physical Planning',
                    'grantee' => $decHolder !== '' ? $decHolder : '-',
                    'party_3' => '-',
                    'party_4' => '-',
                    'instrument_type' => 'File Decommissioning',
                    'transaction_date' => $decDate,
                    'reg_time' => '-',
                    'reg_date' => $decDate,
                    '_decommission_event_type' => $lineage['decommission_event_type'] ?? null,
                    'reg_no' => '0/0/0',
                    'size' => '-',
                    'caveat' => 'No',
                    'comments' => trim((string) ($lineage['decommission_reason'] ?? '')) ?: '-',
                    'source_table' => 'File Decommissioning',
                    'location' => '',
                ];
                $firstParcelIdx = -1;
                foreach ($rows as $i => $row) {
                    if ($isParcelUpdateRow($row)) {
                        $firstParcelIdx = $i;
                        break;
                    }
                }
                if ($firstParcelIdx >= 0) {
                    array_splice($rows, $firstParcelIdx, 0, [$decommissioningRow]);
                } else {
                    $rows[] = $decommissioningRow;
                }
            }
        }

        // Successor commissioning — one row PER successor (a batch subdivision
        // retires the mother into several children), placed after the last
        // parcel-update row (the transaction that retired the searched file),
        // else at the very end.
        $successorRows = [];
        $seenSuccKeys = [];
        foreach (($lineage['successor_files'] ?? []) as $succ) {
            $succNo = trim((string) ($succ['file_no'] ?? ''));
            $succKey = $norm($succNo);
            if ($succNo === '' || $succKey === $searchedKey || isset($seenSuccKeys[$succKey])) {
                continue;
            }
            $seenSuccKeys[$succKey] = true;

            $row = $makeLineageRow(
                $succNo,
                $succ['commissioning_date'] ?? null,
                $succ['file_title'] ?? null
            );
            if ($row) {
                $successorRows[] = $row;
            }

            // This successor was itself later retired (e.g. a Subdivision child retired by a
            // Change of Purpose) — its own File Decommissioning row sits directly below its
            // commissioning, ahead of the grandchild's commissioning row.
            if (!empty($succ['is_superseded'])) {
                $sDecNo = trim((string) ($succ['decommission_file_no'] ?? '')) ?: $succNo;
                $sDecNo = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $sDecNo));
                if ($sDecNo !== '') {
                    $sDecDate = (!empty($succ['decommission_date']) && $succ['decommission_date'] !== '-')
                        ? $succ['decommission_date'] : '-';
                    $sHolder = trim((string) ($succ['decommission_holder'] ?? ''))
                        ?: trim((string) ($succ['file_title'] ?? ''));
                    $successorRows[] = [
                        'sn' => 0, // renumbered by the caller
                        'file_no' => $sDecNo,
                        'grantor' => 'Kano State Ministry of Land and Physical Planning',
                        'grantee' => $sHolder !== '' ? $sHolder : '-',
                        'party_3' => '-',
                        'party_4' => '-',
                        'instrument_type' => 'File Decommissioning',
                        'transaction_date' => $sDecDate,
                        'reg_time' => '-',
                        'reg_date' => $sDecDate,
                        'reg_no' => '0/0/0',
                        'size' => '-',
                        'caveat' => 'No',
                        'comments' => trim((string) ($succ['decommission_reason'] ?? '')) ?: '-',
                        'source_table' => 'File Decommissioning',
                        'location' => '',
                    ];
                }
            }
        }
        if ($successorRows) {
            $retiredIdx = -1;
            foreach ($rows as $i => $row) {
                if ($isParcelUpdateRow($row)) {
                    $retiredIdx = $i;
                }
            }
            if ($retiredIdx >= 0) {
                array_splice($rows, $retiredIdx + 1, 0, $successorRows);
            } else {
                $rows = array_merge($rows, $successorRows);
            }
        }

        return $rows;
    }

    /**
     * Build the "(T)" / base / base(T) variants of a file number for lookups.
     */
    private function fileNumberVariants(?string ...$numbers): array
    {
        $variants = [];
        foreach ($numbers as $n) {
            $n = trim((string) $n);
            if ($n === '') {
                continue;
            }
            $variants[$n] = $n;
            $base = trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $n));
            if ($base !== '') {
                $variants[$base] = $base;
                $variants[$base . '(T)'] = $base . '(T)';
            }
        }
        return array_values($variants);
    }

    /**
     * Extract the commissioning year embedded in a land file number, e.g.
     * "RES-2001-3874" → "2001", "CON-RES-1993-387" → "1993". Matches a
     * dash-delimited 4-digit segment starting with 19 or 20 so serial numbers of
     * the same length (e.g. "3874") aren't mistaken for a year.
     */
    private function extractYearFromFileNumber(?string $fileNo): ?string
    {
        if (!$fileNo) {
            return null;
        }

        foreach (explode('-', $fileNo) as $part) {
            $part = trim($part);
            if (preg_match('/^(?:19|20)\d{2}$/', $part)) {
                return $part;
            }
        }

        return null;
    }

    /**
     * Whether the searched file is tagged [WRC] (Withdrawn / Revoked / Cancelled)
     * in duplicate_fileno — matched by its file number variants or prop_id. Mirrors
     * the gating used when rendering the W/R/C notice on the report.
     */
    private function resolveIsWrcFile($conn, ?string $fileNo, $propId = null): bool
    {
        $variants = $this->fileNumberVariants($fileNo);
        if (empty($variants) && !$propId) {
            return false;
        }

        $comments = $conn->table('duplicate_fileno')
            ->where(function ($qq) use ($variants, $propId) {
                if (!empty($variants)) {
                    $qq->whereIn('file_number', $variants);
                }
                if ($propId) {
                    $qq->orWhere('prop_id', $propId);
                }
            })
            ->pluck('comment');

        foreach ($comments as $c) {
            if (str_starts_with(strtoupper(trim((string) $c)), '[WRC]')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a file number (or its "(T)"/base variant) exists as an indexed or commissioned
     * file. Used to decide whether a transaction-less search still warrants a print report —
     * a commissioned file always has at least its commissioning event.
     */
    private function reportFileExists(string $fileNo): bool
    {
        $variants = $this->fileNumberVariants($fileNo);
        if (empty($variants)) {
            return false;
        }

        $conn = DB::connection('sqlsrv');

        $inIndexing = $conn->table('file_indexings')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('file_number', $v)->orWhere('temp_file_no', $v);
                }
            })
            ->exists();
        if ($inIndexing) {
            return true;
        }

        return $conn->table('fileNumber')->whereIn('mlsfNo', $variants)->exists();
    }

    /**
     * Whether the searched file (or its "(T)"/base variant) has its OWN row in
     * file_indexings — i.e. it has actually been indexed, as opposed to merely
     * commissioned (present in fileNumber) or loosely linked via another file's
     * related_fileno. Used to gate the synthetic "File Commissioning" timeline row:
     * a file that is not yet indexed must not show a commissioning row.
     */
    private function isFileIndexed(?string $fileNo): bool
    {
        $variants = $this->fileNumberVariants($fileNo);
        if (empty($variants)) {
            return false;
        }

        return DB::connection('sqlsrv')->table('file_indexings')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('file_number', $v)->orWhere('temp_file_no', $v);
                }
            })
            ->exists();
    }

    /**
     * Registered owner name from fileNumber.FileName for a file number and its variants.
     * Used as a File Title fallback for commissioned-but-unindexed files.
     */
    private function resolveFileNameFallback(?string $fileNumber, ?string $fileNo): ?string
    {
        $variants = $this->fileNumberVariants($fileNumber, $fileNo);
        if (empty($variants)) {
            return null;
        }

        $name = DB::connection('sqlsrv')->table('fileNumber')
            ->whereIn('mlsfNo', $variants)
            ->whereNotNull('FileName')
            ->where('FileName', '<>', '')
            ->value('FileName');

        return $name ? trim((string) $name) : null;
    }

    /**
     * Resolve the temporary "(T)" file number tied to the searched file.
     *
     * A temporary file (e.g. "RES-1991-545(T)") is registered against its parent
     * file ("RES-1991-545"). It may live on the parent's file_indexings row
     * (has_temp_file / temp_file_no) or exist as its own record in file_indexings
     * or fileNumber. Returns the "(T)" number to show as a second line, or null.
     */
    /**
     * Choose the best-matching file_indexings row for a set of candidate file
     * numbers. A file can be indexed under several sibling rows (e.g. a base
     * number and its temporary "(T)" number, or a title-less shell alongside the
     * titled record). Exact matches on file_number/temp_file_no win over loose
     * related_fileno matches, and — crucially — a row that actually carries a
     * file_title is preferred over an otherwise-equal title-less sibling so the
     * File Name (Party 2 on the File Commissioning row) resolves correctly.
     */
    private function pickBestIndexingRow($rows, array $candidates)
    {
        if ($rows === null || $rows->isEmpty()) {
            return null;
        }

        $hasTitle = fn($row) => trim((string) ($row->file_title ?? '')) !== '';
        $isExact = function ($row) use ($candidates) {
            foreach ($candidates as $candidate) {
                if (strcasecmp((string) ($row->file_number ?? ''), (string) $candidate) === 0
                    || strcasecmp((string) ($row->temp_file_no ?? ''), (string) $candidate) === 0) {
                    return true;
                }
            }
            return false;
        };

        $exact = $rows->filter($isExact)->values();

        // 1. Exact match that also carries a title.
        // 2. Any exact match.
        // 3. Loose match (related_fileno) that carries a title.
        // 4. First row of any kind.
        return $exact->first($hasTitle)
            ?? $exact->first()
            ?? $rows->first($hasTitle)
            ?? $rows->first();
    }

    private function resolveTempFileNumber($conn, string $fileNo, $fileIndexingData): ?string
    {
        // 1. Prefer the temp_file_no recorded on the matched parent indexing row.
        $recorded = trim((string) ($fileIndexingData->temp_file_no ?? ''));
        if ($recorded !== '') {
            return $recorded;
        }

        // Candidate base numbers to derive a "(T)" sibling from.
        $bases = array_values(array_unique(array_filter([
            trim((string) ($fileIndexingData->file_number ?? '')),
            trim($fileNo),
        ])));
        if (empty($bases)) {
            return null;
        }

        foreach ($bases as $base) {
            // Skip when the searched value is itself already a temporary number.
            if (preg_match('/\(\s*T\s*\)\s*$/i', $base)) {
                continue;
            }
            $tempCandidate = $base . '(T)';

            // 2. Parent indexing row that flags a temporary file.
            $viaParent = $conn->table('file_indexings')
                ->whereNull('deleted_at')
                ->where('file_number', $base)
                ->where('has_temp_file', 1)
                ->value('temp_file_no');
            if ($viaParent) {
                return trim((string) $viaParent);
            }

            // 3. Temporary file existing as its own indexing record.
            $inIndexing = $conn->table('file_indexings')
                ->whereNull('deleted_at')
                ->where('file_number', $tempCandidate)
                ->exists();
            if ($inIndexing) {
                return $tempCandidate;
            }

            // 4. Temporary file existing in the fileNumber register.
            $inFileNumber = $conn->table('fileNumber')
                ->where('mlsfNo', $tempCandidate)
                ->exists();
            if ($inFileNumber) {
                return $tempCandidate;
            }
        }

        return null;
    }

    // --- file_history_staging ----------------------------------------
    // Actual columns: NO size, NO caveat. Has plot_size, is_caveated (bit), caveated_comment

    private function searchFileHistoryStaging($conn, array $f): array
    {
        $query = $conn->table('file_history_staging')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                'districtName',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                'title_status',
                'title_status_remark',
                DB::raw("plot_size AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'caveat_id',
                'caveated_comment',
                'is_caveated',
                'plot_no',
                'deeds_date',
                'deeds_time',
                'reg_date',
                'reg_time',
                'tp_no',
                DB::raw("'file_history_staging' AS source_table"),
            ]);

        $this->applyFilters($query, $f, 'file_history_staging', ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);

        $this->applySoftDeleteFilter($query, 'file_history_staging');

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'File History'))->toArray();
    }

    // --- CofO_staging ------------------------------------------------
    // Actual columns: NO size, NO caveat, NO party_1/party_2/party_3. Has is_caveated (bit), caveated_comment, np_fileno

    private function searchCofoStaging($conn, array $f): array
    {
        $query = $conn->table('CofO_staging')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                'title_status',
                'title_status_remark',
                DB::raw("NULL AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'caveat_id',
                'caveated_comment',
                'is_caveated',
                'plot_no',
                // Return the real deeds_date/deeds_time so values edited on the
                // record actually display; for time, fall back to transaction_time
                // when deeds_time hasn't been set (preserves the prior display).
                DB::raw("deeds_date AS deeds_date"),
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(deeds_time)), ''), transaction_time) AS deeds_time"),
                DB::raw("NULL AS reg_date"),
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(deeds_time)), ''), transaction_time) AS reg_time"),
                DB::raw("NULL AS tp_no"),
                DB::raw("'CofO_staging' AS source_table"),
            ]);

        $this->applyFilters($query, $f, 'CofO_staging', ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);

        $this->applySoftDeleteFilter($query, 'CofO_staging');

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'CofO'))->toArray();
    }

    // --- pra ---------------------------------------------------------
    // Actual columns: NO size, NO caveat. Has plot_size, is_caveated (bit), caveated_comment

    private function searchPra($conn, array $f): array
    {
        $query = $conn->table('pra')
            ->select([
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                DB::raw("COALESCE(property_description, location) AS location"),
                'lgsaOrCity',
                'districtName',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'parent_prop_id',
                'comments',
                'title_status',
                'title_status_remark',
                DB::raw("plot_size AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'caveat_id',
                'caveated_comment',
                'is_caveated',
                'plot_no',
                'deeds_date',
                'deeds_time',
                DB::raw("NULL AS reg_date"),
                DB::raw("NULL AS reg_time"),
                'tp_no',
                DB::raw("'pra' AS source_table"),
            ]);

        $this->applyFilters($query, $f, 'pra', ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno']);

        $this->applySoftDeleteFilter($query, 'pra');

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'PRA'))->toArray();
    }

    // --- deed_registrations ------------------------------------------
    // Actual columns: HAS size, NO caveat, NO is_caveated. Has plot_number (not plot_no), district, lga

    private function searchDeedRegistrations($conn, array $f): array
    {
        $query = $conn->table('deed_registrations')
            ->select([
                'id',
                DB::raw("fileno AS file_number"),
                DB::raw("fileno AS mlsFNo"),
                'fileno',
                DB::raw("NULL AS kangisFileNo"),
                DB::raw("NULL AS NewKANGISFileno"),
                DB::raw("instrument_type AS transaction_type"),
                DB::raw("TRY_CONVERT(DATE, deeds_date) AS transaction_date"),
                DB::raw("grantor AS party_1"),
                DB::raw("grantee AS party_2"),
                DB::raw("NULL AS party_3"),
                DB::raw("NULL AS party_4"),
                DB::raw("NULL AS assignor"),
                DB::raw("NULL AS assignee"),
                DB::raw("NULL AS mortgagor"),
                DB::raw("NULL AS mortgagee"),
                DB::raw("grantor"),
                DB::raw("grantee"),
                DB::raw("NULL AS surrenderor"),
                DB::raw("NULL AS surrenderee"),
                DB::raw("NULL AS lessor"),
                DB::raw("NULL AS lessee"),
                DB::raw("NULL AS land_use"),
                DB::raw("COALESCE(district, lga) AS location"),
                'district',
                'lga',
                'serial_no',
                'page_no',
                'volume_no',
                DB::raw("registration_number AS regNo"),
                'prop_id',
                DB::raw("property_description AS comments"),
                'title_status',
                'title_status_remark',
                'size',
                DB::raw("NULL AS caveat"),
                DB::raw("NULL AS caveat_id"),
                DB::raw("NULL AS caveated_comment"),
                DB::raw("CAST(0 AS BIT) AS is_caveated"),
                DB::raw("plot_number AS plot_no"),
                'deeds_date',
                'deeds_time',
                DB::raw("NULL AS reg_date"),
                DB::raw("NULL AS reg_time"),
                DB::raw("NULL AS tp_no"),
                DB::raw("'deed_registrations' AS source_table"),
            ]);

        $this->applyFilters($query, $f, 'deed_registrations', ['fileno', 'parent_fileno']);

        $this->applySoftDeleteFilter($query, 'deed_registrations');

        // Certificate of Occupancy instruments are mirrored into CofO_staging
        // (the CofO tab). Exclude them here so the same CofO does not also show
        // up under Deeds Registration as a duplicate.
        $this->excludeCofoFromDeedRegistrations($query);

        return $query->get()->map(fn($r) => $this->normalizeRow($r, 'Deed Registration'))->toArray();
    }

    /**
     * Drop Certificate of Occupancy rows from a deed_registrations query.
     * CofOs live in CofO_staging now; keeping them here would duplicate the
     * record across the Deeds Registration and CofO tabs. Null instrument
     * types are kept (they are not CofO).
     */
    private function excludeCofoFromDeedRegistrations($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('instrument_type')
              ->orWhere('instrument_type', 'NOT LIKE', '%Certificate of Occupancy%');
        });
    }

    // --- Shared filter logic -----------------------------------------
    // Table-aware: uses correct real column names per table

    private function applyFilters($query, array $f, string $tableName, array $fileColumns): void
    {
        $isDeed = ($tableName === 'deed_registrations');
        $isCofO = ($tableName === 'CofO_staging');

        // File number filter (Exact match based on File Number Selector, support subdivision parent/unit search)
        if ($f['fileNo'] !== '') {
            $searchedFileNo = $f['fileNo'];
            
            if (!empty($f['allowedSmeFileNos'])) {
                $searchFileNos = $f['allowedSmeFileNos'];
            } else {
                $searchFileNos = [$searchedFileNo];
                $motherFileNo = null;
                if ($this->isSubdividedUnit($searchedFileNo, $motherFileNo)) {
                    $searchFileNos[] = $motherFileNo;
                }
            }

            // Sectional Titling scheme expansion (ST mother/scheme search): pull the units'
            // transactions by also matching the ST scheme number (→ deed_registrations.parent_fileno)
            // and each unit file number. Additive — never replaces the searched number.
            if (!empty($f['stRelatedFileNos'])) {
                $searchFileNos = array_merge($searchFileNos, $f['stRelatedFileNos']);
            }

            // Expand to base/"(T)" variants so searching the temp file number (e.g.
            // "RES-2003-537(T)") finds records stored under the base number and vice versa —
            // both refer to the same physical file.
            $searchFileNos = $this->fileNumberVariants(...$searchFileNos);
            
            $query->where(function ($subQ) use ($searchFileNos, $fileColumns) {
                foreach ($fileColumns as $col) {
                    foreach ($searchFileNos as $fn) {
                        $subQ->orWhereRaw("UPPER(LTRIM(RTRIM({$col}))) = UPPER(?)", [$fn]);
                    }
                }
            });
        }

        // Guarantor / party_1 filter
        if ($f['guarantorName'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['guarantorName']);
            $query->where(function ($subQ) use ($escaped, $isDeed, $isCofO) {
                if ($isDeed) {
                    $subQ->whereRaw("UPPER(grantor) LIKE UPPER(?)", ["%{$escaped}%"]);
                } else {
                    if (!$isCofO) {
                        $subQ->orWhereRaw("UPPER(party_1) LIKE UPPER(?)", ["%{$escaped}%"]);
                    }
                    $subQ->orWhereRaw("UPPER(Assignor) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Grantor) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Mortgagor) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Lessor) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Surrenderor) LIKE UPPER(?)", ["%{$escaped}%"]);
                }
            });
        }

        // Guarantee / party_2 filter
        if ($f['guaranteeName'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['guaranteeName']);
            $query->where(function ($subQ) use ($escaped, $isDeed, $isCofO) {
                if ($isDeed) {
                    $subQ->whereRaw("UPPER(grantee) LIKE UPPER(?)", ["%{$escaped}%"]);
                } else {
                    if (!$isCofO) {
                        $subQ->orWhereRaw("UPPER(party_2) LIKE UPPER(?)", ["%{$escaped}%"]);
                    }
                    $subQ->orWhereRaw("UPPER(Assignee) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Grantee) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Mortgagee) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Lessee) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(Surrenderee) LIKE UPPER(?)", ["%{$escaped}%"]);
                }
            });
        }

        // LGA filter — actual column names differ per table
        if ($f['lga'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['lga']);
            if ($isDeed) {
                $query->whereRaw("UPPER(lga) LIKE UPPER(?)", ["%{$escaped}%"]);
            } else {
                // file_history_staging, CofO_staging, pra all have lgsaOrCity
                $query->whereRaw("UPPER(lgsaOrCity) LIKE UPPER(?)", ["%{$escaped}%"]);
            }
        }

        // District filter
        if ($f['district'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['district']);
            if ($isDeed) {
                $query->whereRaw("UPPER(district) LIKE UPPER(?)", ["%{$escaped}%"]);
            } else if (!$isCofO) {
                // file_history_staging, pra have districtName
                $query->whereRaw("UPPER(districtName) LIKE UPPER(?)", ["%{$escaped}%"]);
            } else {
                // CofO_staging has no districtName — fall back to location
                $query->whereRaw("UPPER(location) LIKE UPPER(?)", ["%{$escaped}%"]);
            }
        }

        // Location filter
        if ($f['location'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['location']);
            if ($isDeed) {
                $query->where(function ($subQ) use ($escaped) {
                    $subQ->orWhereRaw("UPPER(district) LIKE UPPER(?)", ["%{$escaped}%"])
                        ->orWhereRaw("UPPER(lga) LIKE UPPER(?)", ["%{$escaped}%"]);
                });
            } else {
                $query->whereRaw("UPPER(location) LIKE UPPER(?)", ["%{$escaped}%"]);
            }
        }

        // Plot number filter — deed_registrations uses plot_number
        if ($f['plotNumber'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['plotNumber']);
            $plotCol = $isDeed ? 'plot_number' : 'plot_no';
            $query->whereRaw("UPPER(CAST({$plotCol} AS NVARCHAR(100))) LIKE UPPER(?)", ["%{$escaped}%"]);
        }

        // Size filter — file_history_staging/pra use plot_size, deed uses size, CofO has neither
        if ($f['size'] !== '') {
            $escaped = str_replace(['%', '_'], ['\%', '\_'], $f['size']);
            if ($isDeed) {
                $query->whereRaw("UPPER(CAST(size AS NVARCHAR(100))) LIKE UPPER(?)", ["%{$escaped}%"]);
            } else if (!$isCofO) {
                $query->whereRaw("UPPER(CAST(plot_size AS NVARCHAR(100))) LIKE UPPER(?)", ["%{$escaped}%"]);
            }
            // CofO_staging has no size column — skip
        }

        // Caveat filter — all 3 staging tables use is_caveated (bit), deed has nothing
        if ($f['caveat'] !== '' && !$isDeed) {
            $caveatBit = (strtolower($f['caveat']) === 'yes') ? 1 : 0;
            $query->where('is_caveated', $caveatBit);
        }
    }

    // --- Row normalizer ----------------------------------------------

    private function normalizeRow($row, string $sourceLabel): array
    {
        $transactionDate = $row->transaction_date;
        $sortDate = null;
        $displayDate = null;

        if ($transactionDate) {
            try {
                $carbon = Carbon::parse($transactionDate);
                $sortDate = $carbon->toDateString();
                $displayDate = $carbon->format('M j, Y');
            } catch (\Exception $e) {
                $displayDate = (string) $transactionDate;
            }
        }

        // Determine primary parties
        $party1 = $row->party_1 ?: ($row->assignor ?: ($row->mortgagor ?: ($row->grantor ?: ($row->surrenderor ?: ($row->lessor ?? null)))));
        $party2 = $row->party_2 ?: ($row->assignee ?: ($row->mortgagee ?: ($row->grantee ?: ($row->surrenderee ?: ($row->lessee ?? null)))));

        // Build registration string
        $serialNo = $row->serial_no;
        $pageNo = $row->page_no;
        $volumeNo = $row->volume_no;

        $registration = null;
        if ($serialNo || $pageNo || $volumeNo) {
            $registration = trim(($serialNo ?: '0') . '/' . ($pageNo ?: '0') . '/' . ($volumeNo ?: '0'));
        }

        // When the file has been decommissioned via Title Status Update
        // (Withdrawal/Cancellation/Revocation/etc.), surface the status remark
        // as the row comment so it appears in the Legal Search timeline.
        $titleStatus       = $row->title_status ?? 0;
        $titleStatusRemark = $row->title_status_remark ?? null;
        $comments          = $row->comments ?? '-';
        if ((int) $titleStatus === 1 && trim((string) $titleStatusRemark) !== '') {
            $comments = $titleStatusRemark;
        }

        return [
            'id' => $row->id,
            'file_number' => $row->file_number,
            'mlsFNo' => $row->mlsFNo,
            'fileno' => $row->fileno,
            'kangisFileNo' => $row->kangisFileNo,
            'NewKANGISFileno' => $row->NewKANGISFileno,
            'transaction_type' => $row->transaction_type ?: '-',
            'transaction_date' => $displayDate ?: '-',
            'sort_date' => $sortDate,
            'party_1' => $party1 ?: '-',
            'party_2' => $party2 ?: '-',
            'party_3' => $row->party_3 ?? '-',
            'party_4' => $row->party_4 ?? '-',
            'land_use' => $row->land_use ?: '-',
            'location' => $row->location ?: '-',
            'lgsaOrCity' => $row->lgsaOrCity ?? ($row->lga ?? '-'),
            'districtName' => $row->districtName ?? ($row->district ?? '-'),
            'registration' => $registration ?: '-',
            'regNo' => $row->regNo ?? '-',
            'serial_no' => $serialNo ?? '-',
            'page_no' => $pageNo ?? '-',
            'volume_no' => $volumeNo ?? '-',
            'size' => $row->size ?? '-',
            'caveat' => $row->caveat ?? '-',
            'caveat_id' => $row->caveat_id ?? null,
            'caveated_comment' => $row->caveated_comment ?? null,
            'is_caveated' => $row->is_caveated ?? 0,
            'plot_no' => $row->plot_no ?? '-',
            'comments' => $comments,
            'title_status' => (int) $titleStatus,
            'title_status_remark' => $titleStatusRemark,
            'cofo_comment' => $row->cofo_comment ?? null,
            'prop_id' => $row->prop_id ?? null,
            'parent_prop_id' => $row->parent_prop_id ?? null,
            'deeds_date' => $row->deeds_date ?? null,
            'deeds_time' => $row->deeds_time ?? null,
            'reg_date' => $row->reg_date ?? null,
            'reg_time' => $row->reg_time ?? null,
            'tp_no' => $row->tp_no ?? null,
            'source_table' => $sourceLabel,
        ];
    }

    /**
     * Collect non-empty prop_ids from normalized result rows.
     */
    private function collectPropIds(array $rows): array
    {
        $propIds = [];
        foreach ($rows as $row) {
            $pid = $row['prop_id'] ?? null;
            if ($pid && trim((string) $pid) !== '') {
                $propIds[trim((string) $pid)] = true;
            }
        }
        // Return as explicit strings so PDO binds them as NVARCHAR.
        // PHP silently converts numeric string keys to int, which causes SQL Server
        // to cast all nvarchar prop_id column values to INT, failing on dirty data.
        return array_values(array_map('strval', array_keys($propIds)));
    }

    /**
     * Build a map of existing {source_table => [id, id, ...]} to avoid duplicates.
     */
    private function buildExistingIdMap(array ...$resultSets): array
    {
        $map = [];
        foreach ($resultSets as $rows) {
            foreach ($rows as $row) {
                $source = $row['source_table'] ?? '';
                $tableKey = match ($source) {
                    'File History' => 'file_history_staging',
                    'CofO' => 'CofO_staging',
                    'PRA' => 'pra',
                    'Deed Registration' => 'deed_registrations',
                    default => $source,
                };
                $map[$tableKey][] = $row['id'];
            }
        }
        return $map;
    }

    /**
     * Search a table by prop_id list, excluding already-fetched IDs.
     */
    private function searchByPropIds($conn, string $tableName, array $propIds, array $excludeIds): array
    {
        if (empty($propIds))
            return [];

        $selectMap = [
            'file_history_staging' => [
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                'districtName',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                'title_status',
                'title_status_remark',
                DB::raw("plot_size AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'plot_no',
                DB::raw("'file_history_staging' AS source_table"),
            ],
            'CofO_staging' => [
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                DB::raw("NULL AS party_1"),
                DB::raw("NULL AS party_2"),
                DB::raw("NULL AS party_3"),
                DB::raw("NULL AS party_4"),
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'comments',
                'title_status',
                'title_status_remark',
                DB::raw("NULL AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'plot_no',
                DB::raw("'CofO_staging' AS source_table"),
            ],
            'pra' => [
                'id',
                DB::raw("COALESCE(mlsFNo, fileno) AS file_number"),
                'mlsFNo',
                'fileno',
                'kangisFileNo',
                'NewKANGISFileno',
                'transaction_type',
                DB::raw("TRY_CONVERT(DATE, transaction_date) AS transaction_date"),
                'party_1',
                'party_2',
                'party_3',
                'party_4',
                DB::raw("Assignor AS assignor"),
                DB::raw("Assignee AS assignee"),
                DB::raw("Mortgagor AS mortgagor"),
                DB::raw("Mortgagee AS mortgagee"),
                DB::raw("Grantor AS grantor"),
                DB::raw("Grantee AS grantee"),
                DB::raw("Surrenderor AS surrenderor"),
                DB::raw("Surrenderee AS surrenderee"),
                DB::raw("Lessor AS lessor"),
                DB::raw("Lessee AS lessee"),
                'land_use',
                'location',
                'lgsaOrCity',
                'districtName',
                DB::raw("serialNo AS serial_no"),
                DB::raw("pageNo AS page_no"),
                DB::raw("volumeNo AS volume_no"),
                'regNo',
                'prop_id',
                'parent_prop_id',
                'comments',
                'title_status',
                'title_status_remark',
                DB::raw("plot_size AS size"),
                DB::raw("CASE WHEN is_caveated = 1 THEN 'Yes' ELSE 'No' END AS caveat"),
                'plot_no',
                DB::raw("'pra' AS source_table"),
            ],
            'deed_registrations' => [
                'id',
                DB::raw("fileno AS file_number"),
                DB::raw("fileno AS mlsFNo"),
                'fileno',
                DB::raw("NULL AS kangisFileNo"),
                DB::raw("NULL AS NewKANGISFileno"),
                DB::raw("instrument_type AS transaction_type"),
                DB::raw("TRY_CONVERT(DATE, deeds_date) AS transaction_date"),
                DB::raw("grantor AS party_1"),
                DB::raw("grantee AS party_2"),
                DB::raw("NULL AS party_3"),
                DB::raw("NULL AS party_4"),
                DB::raw("NULL AS assignor"),
                DB::raw("NULL AS assignee"),
                DB::raw("NULL AS mortgagor"),
                DB::raw("NULL AS mortgagee"),
                DB::raw("grantor"),
                DB::raw("grantee"),
                DB::raw("NULL AS surrenderor"),
                DB::raw("NULL AS surrenderee"),
                DB::raw("NULL AS lessor"),
                DB::raw("NULL AS lessee"),
                DB::raw("NULL AS land_use"),
                DB::raw("COALESCE(district, lga) AS location"),
                'district',
                'lga',
                'serial_no',
                'page_no',
                'volume_no',
                DB::raw("registration_number AS regNo"),
                'prop_id',
                DB::raw("property_description AS comments"),
                'title_status',
                'title_status_remark',
                'size',
                DB::raw("NULL AS caveat"),
                DB::raw("plot_number AS plot_no"),
                DB::raw("'deed_registrations' AS source_table"),
            ],
        ];

        $labelMap = [
            'file_history_staging' => 'File History',
            'CofO_staging' => 'CofO',
            'pra' => 'PRA',
            'deed_registrations' => 'Deed Registration',
        ];

        $select = $selectMap[$tableName] ?? null;
        if (!$select)
            return [];

        $query = $conn->table($tableName)
            ->select($select)
            ->whereIn('prop_id', array_map('strval', $propIds));

        if (!empty($excludeIds)) {
            // Cast exclude IDs to int to match the INT primary key column.
            $query->whereNotIn('id', array_map('intval', array_filter($excludeIds, 'is_numeric')));
        }

        // CofOs are surfaced from CofO_staging; never from deed_registrations,
        // to avoid the same record appearing in both the Deeds Registration and
        // CofO tabs.
        if ($tableName === 'deed_registrations') {
            $this->excludeCofoFromDeedRegistrations($query);
        }

        $this->applySoftDeleteFilter($query, $tableName);

        return $query->get()->map(fn($r) => $this->normalizeRow($r, $labelMap[$tableName]))->toArray();
    }

    // ================================================================
    // Cleanup Mode operations: Match, Drop, Remove, Update
    // ================================================================

    /**
     * Valid table names for cleanup operations (whitelist).
     */
    private const VALID_TABLES = [
        'file_history_staging',
        'CofO_staging',
        'pra',
        'deed_registrations',
    ];

    /**
     * Editable columns per table (whitelist for update operations).
     */
    private const EDITABLE_COLUMNS = [
        'file_history_staging' => [
            'mlsFNo',
            'kangisFileNo',
            'NewKANGISFileno',
            'fileno',
            'transaction_type',
            'transaction_date',
            'land_use',
            'location',
            'party_1',
            'party_2',
            'party_3',
            'party_4',
            'Assignor',
            'Assignee',
            'Mortgagor',
            'Mortgagee',
            'Grantor',
            'Grantee',
            'Surrenderor',
            'Surrenderee',
            'Lessor',
            'Lessee',
            'serialNo',
            'pageNo',
            'volumeNo',
            'regNo',
            // file_history_staging genuinely has reg_date/reg_time columns
            // (unlike pra/CofO_staging — see below), so these map 1:1.
            'reg_date',
            'reg_time',
            'plot_no',
            'plot_size',
            'lgsaOrCity',
            'districtName',
            'comments',
            'remarks',
        ],
        'CofO_staging' => [
            'mlsFNo',
            'kangisFileNo',
            'NewKANGISFileno',
            'fileno',
            'np_fileno',
            'transaction_type',
            'transaction_date',
            'land_use',
            'location',
            'Assignor',
            'Assignee',
            'Mortgagor',
            'Mortgagee',
            'Grantor',
            'Grantee',
            'Surrenderor',
            'Surrenderee',
            'Lessor',
            'Lessee',
            'serialNo',
            'pageNo',
            'volumeNo',
            'regNo',
            // CofO_staging has no literal reg_date/reg_time columns; its
            // registration date/time is tracked as deeds_date/deeds_time (same as
            // pra), which is what the report/timeline prefer for the "Reg Date".
            // transaction_time is kept as the last-resort Reg Time fallback.
            'deeds_date',
            'deeds_time',
            'transaction_time',
            'plot_no',
            'lgsaOrCity',
            'comments',
            'remarks',
            'period',
            'period_unit',
        ],
        'pra' => [
            'mlsFNo',
            'kangisFileNo',
            'NewKANGISFileno',
            'fileno',
            'transaction_type',
            'transaction_date',
            'land_use',
            'location',
            'party_1',
            'party_2',
            'party_3',
            'Assignor',
            'Assignee',
            'Mortgagor',
            'Mortgagee',
            'Grantor',
            'Grantee',
            'Surrenderor',
            'Surrenderee',
            'Lessor',
            'Lessee',
            'Donor',
            'Donee',
            'Vendor',
            'Purchaser',
            'serialNo',
            'pageNo',
            'volumeNo',
            'regNo',
            // pra has no reg_date/reg_time columns at all — its registration
            // date/time is tracked as deeds_date/deeds_time instead.
            'deeds_date',
            'deeds_time',
            'plot_no',
            'plot_size',
            'lgsaOrCity',
            'districtName',
            'comments',
            'remarks',
        ],
        'deed_registrations' => [
            'fileno',
            'parent_fileno',
            'instrument_type',
            'registration_number',
            'volume_no',
            'page_no',
            'serial_no',
            'deeds_date',
            'deeds_time',
            'instrument_date',
            'grantor',
            'grantee',
            'lga',
            'district',
            'plot_number',
            'size',
            'property_description',
        ],
    ];

    /**
     * Validate that table name is in the whitelist.
     */
    private function validateTable(string $table): void
    {
        if (!in_array($table, self::VALID_TABLES, true)) {
            throw new \InvalidArgumentException("Invalid table: {$table}");
        }
    }

    /**
     * Match: Assign orphan record(s) to a prop_id group.
     * Sets the prop_id on the specified record(s).
     *
     * @param string $table     The source table name
     * @param array  $ids       Record IDs to update
     * @param string $propId    The target prop_id to assign
     * @return int Number of records updated
     */
    public function matchRecords(string $table, array $ids, string $propId): int
    {
        $this->validateTable($table);

        if (empty($ids) || empty(trim($propId))) {
            return 0;
        }

        return DB::connection('sqlsrv')
            ->table($table)
            ->whereIn('id', $ids)
            ->update([
                'prop_id' => trim($propId),
                'updated_at' => now(),
            ]);
    }

    /**
     * Drop: Unlink record(s) from a prop_id group.
     * Sets prop_id = NULL so they become orphan records.
     *
     * @param string $table  The source table name
     * @param array  $ids    Record IDs to unlink
     * @return int Number of records updated
     */
    public function dropRecords(string $table, array $ids): int
    {
        $this->validateTable($table);

        if (empty($ids)) {
            return 0;
        }

        return DB::connection('sqlsrv')
            ->table($table)
            ->whereIn('id', $ids)
            ->update([
                'prop_id' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Remove: Soft-delete record(s) by setting is_deleted = 1.
     *
     * @param string $table  The source table name
     * @param array  $ids    Record IDs to soft-delete
     * @return int Number of records updated
     */
    public function removeRecords(string $table, array $ids): int
    {
        $this->validateTable($table);

        if (empty($ids)) {
            return 0;
        }

        return DB::connection('sqlsrv')
            ->table($table)
            ->whereIn('id', $ids)
            ->update([
                'is_deleted' => 1,
                'updated_at' => now(),
            ]);
    }

    /**
     * Update: Edit fields on a single record.
     * Only whitelisted columns per table are accepted.
     *
     * @param string $table  The source table name
     * @param int    $id     Record ID
     * @param array  $fields Associative array of column => value
     * @return bool
     */
    public function updateRecord(string $table, int $id, array $fields): bool
    {
        $this->validateTable($table);

        $allowedColumns = self::EDITABLE_COLUMNS[$table] ?? [];
        $safeFields = [];

        foreach ($fields as $col => $val) {
            if (in_array($col, $allowedColumns, true)) {
                $safeFields[$col] = $val;
            }
        }

        if (empty($safeFields)) {
            return false;
        }

        $safeFields['updated_at'] = now();

        return DB::connection('sqlsrv')
            ->table($table)
            ->where('id', $id)
            ->update($safeFields) > 0;
    }

    /**
     * Transfer caveat fields from one record to another record.
     * Supported tables: pra and CofO_staging.
     */
    public function transferCaveat(string $sourceTable, int $sourceId, string $targetTable, int $targetId): bool
    {
        $allowed = ['pra', 'CofO_staging'];
        if (!in_array($sourceTable, $allowed, true) || !in_array($targetTable, $allowed, true)) {
            throw new \InvalidArgumentException('Caveat transfer supports only PRA and CofO records.');
        }

        if ($sourceTable === $targetTable && $sourceId === $targetId) {
            throw new \InvalidArgumentException('Source and target records cannot be the same.');
        }

        $source = DB::connection('sqlsrv')
            ->table($sourceTable)
            ->where('id', $sourceId)
            ->first(['id', 'is_caveated', 'caveat_id', 'caveated_comment']);

        if (!$source) {
            throw new \InvalidArgumentException('Source record not found.');
        }

        $targetExists = DB::connection('sqlsrv')
            ->table($targetTable)
            ->where('id', $targetId)
            ->exists();

        if (!$targetExists) {
            throw new \InvalidArgumentException('Target record not found.');
        }

        $hasCaveat = ((int) ($source->is_caveated ?? 0) === 1)
            || !empty($source->caveat_id)
            || trim((string) ($source->caveated_comment ?? '')) !== '';

        if (!$hasCaveat) {
            throw new \InvalidArgumentException('Source record has no caveat to transfer.');
        }

        return DB::connection('sqlsrv')->transaction(function () use ($sourceTable, $sourceId, $targetTable, $targetId, $source) {
            $now = now();

            $targetUpdate = [
                'is_caveated' => 1,
                'caveat_id' => $source->caveat_id,
                'caveated_comment' => $source->caveated_comment,
                'updated_at' => $now,
            ];

            $sourceClear = [
                'is_caveated' => 0,
                'caveat_id' => null,
                'caveated_comment' => null,
                'updated_at' => $now,
            ];

            $targetAffected = DB::connection('sqlsrv')
                ->table($targetTable)
                ->where('id', $targetId)
                ->update($targetUpdate);

            $sourceAffected = DB::connection('sqlsrv')
                ->table($sourceTable)
                ->where('id', $sourceId)
                ->update($sourceClear);

            return $targetAffected > 0 && $sourceAffected > 0;
        });
    }

    /**
     * Get a single record by ID and table for editing.
     */
    public function getRecord(string $table, int $id): ?object
    {
        $this->validateTable($table);

        $query = DB::connection('sqlsrv')
            ->table($table)
            ->where('id', $id);

        $this->applySoftDeleteFilter($query, $table);

        return $query->first();
    }

    /**
     * Set of genuinely decommissioned file numbers (normalised UPPER/trimmed), used to hide
     * superseded originals from search results. Rows flagged false_decommissioning = 1 are
     * Title-Status markers, not real decommissions, so they are excluded from this set.
     * Fails open (returns []) if the audit table is unavailable — search must never break.
     */
    private function getDecommissionedFileNumbers($conn): array
    {
        if ($this->decommissionedFileNumbers !== null) {
            return $this->decommissionedFileNumbers;
        }

        $this->decommissionedFileNumbers = [];

        try {
            $schema = Schema::connection($conn->getName());
            if (!$schema->hasTable('decommissioned_files')) {
                return $this->decommissionedFileNumbers;
            }

            $query = $conn->table('decommissioned_files');

            // Only exclude REAL decommissions. If the column is absent (older schema),
            // treat every row as a real decommission.
            if ($schema->hasColumn('decommissioned_files', 'false_decommissioning')) {
                $query->where(function ($q) {
                    $q->where('false_decommissioning', 0)->orWhereNull('false_decommissioning');
                });
            }

            $hasReason = $schema->hasColumn('decommissioned_files', 'decommissioning_reason');
            $cols = ['file_no', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no'];
            $rows = $query->get($hasReason ? array_merge($cols, ['decommissioning_reason']) : $cols);

            // Map each decommissioned file number to its decommissioning_reason (or '' if unknown).
            // Callers use isset() for presence AND read the reason to distinguish a 1:1 rename
            // (Change of Purpose / recertification — successor inherits the prop_id) from a
            // split/merge (Subdivision / Merger — successors get NEW prop_ids).
            $set = [];
            foreach ($rows as $r) {
                $reason = $hasReason ? (string) ($r->decommissioning_reason ?? '') : '';
                foreach ($cols as $col) {
                    $v = trim((string) ($r->$col ?? ''));
                    if ($v !== '') {
                        $set[strtoupper($v)] = $reason;
                    }
                }
            }
            $this->decommissionedFileNumbers = $set;
        } catch (\Throwable $e) {
            $this->decommissionedFileNumbers = [];
        }

        return $this->decommissionedFileNumbers;
    }

    private function applySoftDeleteFilter($query, string $tableName): void
    {
        if ($this->tableHasIsDeletedColumn($tableName)) {
            $query->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });
        }
    }

    private function tableHasIsDeletedColumn(string $tableName): bool
    {
        if (array_key_exists($tableName, $this->softDeleteColumnCache)) {
            return $this->softDeleteColumnCache[$tableName];
        }

        $this->softDeleteColumnCache[$tableName] = Schema::connection('sqlsrv')->hasColumn($tableName, 'is_deleted');

        return $this->softDeleteColumnCache[$tableName];
    }

    /**
     * Detect prop_id conflicts in a set of selected records.
     * Returns distinct prop_ids found across the selection.
     */
    public function detectPropIdConflicts(array $selections): array
    {
        $propIds = [];

        foreach ($selections as $sel) {
            $table = $sel['table'] ?? '';
            $ids = $sel['ids'] ?? [];

            if (!in_array($table, self::VALID_TABLES, true) || empty($ids)) {
                continue;
            }

            $records = DB::connection('sqlsrv')
                ->table($table)
                ->whereIn('id', $ids)
                ->whereNotNull('prop_id')
                ->where('prop_id', '!=', '')
                ->pluck('prop_id')
                ->toArray();

            foreach ($records as $pid) {
                $propIds[trim($pid)] = true;
            }
        }

        return array_keys($propIds);
    }

    /**
     * Persist timeline order for a prop_id.
     *
     * @param string   $propId
     * @param array    $items Each item: ['table' => string, 'id' => int, 'order' => int]
     * @param int|null $userId
     * @return int Number of rows written
     */
    public function saveTimelineArrangement(string $propId, array $items, ?int $userId = null): int
    {
        $propId = trim($propId);
        if ($propId === '' || empty($items)) {
            return 0;
        }

        $payload = [];
        $seen = [];

        foreach ($items as $item) {
            $table = (string) ($item['table'] ?? '');
            $id = (int) ($item['id'] ?? 0);
            $order = (int) ($item['order'] ?? 0);

            $this->validateTable($table);
            if ($id <= 0 || $order <= 0) {
                continue;
            }

            $key = $table . ':' . $id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $payload[] = [
                'prop_id' => $propId,
                'source_table' => $table,
                'source_id' => $id,
                'display_order' => $order,
                'arranged_by' => $userId,
                'arranged_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($payload)) {
            return 0;
        }

        return DB::connection('sqlsrv')->transaction(function () use ($propId, $payload) {
            DB::connection('sqlsrv')
                ->table(self::ARRANGEMENT_TABLE)
                ->where('prop_id', $propId)
                ->delete();

            DB::connection('sqlsrv')
                ->table(self::ARRANGEMENT_TABLE)
                ->insert($payload);

            return count($payload);
        });
    }

    /**
     * Label-to-table mapping for arrangement keys.
     */
    private const SOURCE_LABEL_TO_TABLE = [
        'File History' => 'file_history_staging',
        'CofO' => 'CofO_staging',
        'PRA' => 'pra',
        'Deed Registration' => 'deed_registrations',
    ];

    /**
     * Apply saved arrangement order to merged results.
     * If a common prop_id exists and has a saved arrangement, reorder accordingly.
     * Arranged items come first (by display_order), unarranged items follow in original order.
     */
    private function applyArrangementOrder(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        // Find the common prop_id (use the first non-empty one)
        $propId = null;
        foreach ($rows as $row) {
            $pid = $row['prop_id'] ?? null;
            if ($pid && trim((string) $pid) !== '') {
                $propId = trim((string) $pid);
                break;
            }
        }

        if (!$propId) {
            return $rows;
        }

        $arrangement = $this->getTimelineArrangement($propId);
        if (empty($arrangement)) {
            return $rows;
        }

        // Sort: arranged items first by display_order, unarranged items keep original order
        $indexed = [];
        foreach ($rows as $idx => $row) {
            $dbTable = self::SOURCE_LABEL_TO_TABLE[$row['source_table'] ?? ''] ?? ($row['source_table'] ?? '');
            $key = $dbTable . ':' . ($row['id'] ?? '');
            $indexed[] = [
                'row' => $row,
                'idx' => $idx,
                'order' => $arrangement[$key] ?? null,
            ];
        }

        usort($indexed, function ($a, $b) {
            $hasA = $a['order'] !== null;
            $hasB = $b['order'] !== null;
            if ($hasA && $hasB)
                return $a['order'] - $b['order'];
            if ($hasA && !$hasB)
                return -1;
            if (!$hasA && $hasB)
                return 1;
            return $a['idx'] - $b['idx'];
        });

        return array_map(fn($x) => $x['row'], $indexed);
    }

    /**
     * Lifecycle-transaction rule: keep each KANGIS Recertification immediately before its
     * corresponding KANGIS Certificate of Occupancy, and suppress duplicate recert rows for the
     * same KANGIS file key. This method is intentionally called only on the current lifecycle
     * group's transaction phase, never on the global mixed-row list.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function placeKangisRecertBeforeCofo(array $rows): array
    {
        $typeOf = function (array $row): string {
            return strtolower((string) ($row['transaction_type'] ?? ($row['instrument_type'] ?? '')));
        };
        $isCofo = fn(array $row): bool => str_contains($typeOf($row), 'certificate of occupanc');
        $isRecert = fn(array $row): bool => str_contains($typeOf($row), 'recertification');

        $cofoKeys = [];
        foreach ($rows as $row) {
            if ($isCofo($row)) {
                $k = $this->extractKangisLifecycleKey($row);
                if ($k !== '') {
                    $cofoKeys[$k] = true;
                }
            }
        }

        $seenRecert = [];
        $pendingRecertByKey = [];
        $pendingOrder = [];
        $result = [];

        foreach ($rows as $row) {
            if ($isRecert($row)) {
                $key = $this->extractKangisLifecycleKey($row);
                $dedupeKey = $key !== ''
                    ? $key
                    : ('ROW:' . $this->normalizeLifecycleFileNo($this->extractOwnFileNo($row)));

                if (isset($seenRecert[$dedupeKey])) {
                    continue;
                }
                $seenRecert[$dedupeKey] = true;

                if ($key !== '' && isset($cofoKeys[$key])) {
                    $pendingRecertByKey[$key] = $row;
                    $pendingOrder[] = $key;
                    continue;
                }

                $result[] = $row;
                continue;
            }

            if ($isCofo($row)) {
                $key = $this->extractKangisLifecycleKey($row);
                if ($key !== '' && isset($pendingRecertByKey[$key])) {
                    $result[] = $pendingRecertByKey[$key];
                    unset($pendingRecertByKey[$key]);
                }
            }

            $result[] = $row;
        }

        foreach ($pendingOrder as $key) {
            if (isset($pendingRecertByKey[$key])) {
                $result[] = $pendingRecertByKey[$key];
                unset($pendingRecertByKey[$key]);
            }
        }

        return $this->orderRecertGenerations($result);
    }

    /**
     * Lifecycle-transaction rule: recertification exercises print in generation order —
     * First KANGIS Recertification (old KNML/MLKN/KNGP, 2014–2024) before Second KANGIS
     * Recertification (new KN, 2025–present) — and each generation's Certificate of
     * Occupancy stays with its own recertification.
     *
     * The date sort alone gets this wrong: a Second Recertification usually has no C of O
     * of its own, so placeKangisRecertBeforeCofo() leaves it wherever it landed, which can
     * be ABOVE the First Recertification / C of O pair it must follow.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function orderRecertGenerations(array $rows): array
    {
        $typeOf = function (array $row): string {
            return strtolower((string) ($row['transaction_type'] ?? ($row['instrument_type'] ?? '')));
        };
        $isCofo = fn(array $row): bool => str_contains($typeOf($row), 'certificate of occupanc');
        $isRecert = fn(array $row): bool => str_contains($typeOf($row), 'recertification');

        // 0 = not a generational KANGIS recert (e.g. the Ministry recertification, which is
        // hoisted under File Commissioning by its own rule and must not be reordered here).
        $genOf = function (array $row) use ($typeOf, $isRecert): int {
            if (!$isRecert($row)) {
                return 0;
            }
            $t = $typeOf($row);
            if (str_contains($t, 'second')) {
                return 2;
            }
            return str_contains($t, 'first') ? 1 : 0;
        };

        // A KANGIS C of O inherits the generation of the recertification sharing its file key,
        // so it travels with that generation instead of being stranded.
        $genByKey = [];
        foreach ($rows as $row) {
            $g = $genOf($row);
            if ($g > 0) {
                $key = $this->extractKangisLifecycleKey($row);
                if ($key !== '') {
                    $genByKey[$key] = $g;
                }
            }
        }

        $gens = [];
        foreach ($rows as $i => $row) {
            $g = $genOf($row);
            if ($g === 0 && $isCofo($row)) {
                $key = $this->extractKangisLifecycleKey($row);
                $g = ($key !== '' && isset($genByKey[$key])) ? $genByKey[$key] : 0;
            }
            $gens[$i] = $g;
        }

        // Each recertification plus the C of O rows already parked directly beneath it forms
        // one indivisible block. Blocks are reordered among themselves; every other row keeps
        // its position, so nothing escapes the lifecycle group.
        $blocks = [];
        $slots = [];
        $n = count($rows);
        for ($i = 0; $i < $n;) {
            if ($gens[$i] === 0) {
                $i++;
                continue;
            }
            $gen = $gens[$i];
            $block = [$rows[$i]];
            $slots[] = $i;
            $j = $i + 1;
            while ($j < $n && $gens[$j] === $gen && $isCofo($rows[$j])) {
                $block[] = $rows[$j];
                $slots[] = $j;
                $j++;
            }
            $blocks[] = ['gen' => $gen, 'rows' => $block];
            $i = $j;
        }

        if (count($blocks) < 2) {
            return $rows;
        }

        $order = array_keys($blocks);
        usort($order, function (int $a, int $b) use ($blocks): int {
            return ($blocks[$a]['gen'] <=> $blocks[$b]['gen']) ?: ($a <=> $b);
        });

        $flat = [];
        foreach ($order as $b) {
            foreach ($blocks[$b]['rows'] as $r) {
                $flat[] = $r;
            }
        }

        foreach ($slots as $k => $idx) {
            $rows[$idx] = $flat[$k];
        }

        return $rows;
    }

    /**
     * Load saved timeline order for a prop_id.
     *
     * @return array<string,int> map key => order where key is "table:id"
     */
    public function getTimelineArrangement(string $propId): array
    {
        $propId = trim($propId);
        if ($propId === '') {
            return [];
        }

        $rows = DB::connection('sqlsrv')
            ->table(self::ARRANGEMENT_TABLE)
            ->select(['source_table', 'source_id', 'display_order'])
            ->where('prop_id', $propId)
            ->orderBy('display_order')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->source_table . ':' . $row->source_id] = (int) $row->display_order;
        }

        return $map;
    }

    /**
     * Identify file number type by pattern.
     * .
     */
    public function identifyFileNumberType($fileNo): string
    {
        if (empty($fileNo))
            return 'unknown';

        $cleanFileNo = trim($fileNo);

        if (preg_match('/^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i', $cleanFileNo))
            return 'st';
        if (preg_match('/^ST-(RES|COM|IND|AG)-\d{4}-\d+$/i', $cleanFileNo))
            return 'parent';

        if (
            preg_match('/^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d{4}-\d+$/i', $cleanFileNo) ||
            preg_match('/^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d+$/i', $cleanFileNo)
        ) {
            return 'mls';
        }

        // Legacy KANGIS. The prefix is enumerated rather than [A-Z]{4} so unrelated 4-letter
        // numbers cannot be swept in, and the shape is deliberately permissive about what the
        // registry actually stores:
        //   - 1-6 digits: "MLKN 42", "KNML 66" are real files; requiring 3 digits stranded 79
        //     recertification links, whose rows were then dropped as untyped "Related File".
        //   - optional unit suffix: "MLKN 2280-1", "KNML 3855_3" (130 further links).
        if (preg_match('/^(MLKN|KNML|KNGP)\s?\d{1,6}([-_]\d{1,3})?$/i', $cleanFileNo))
            return 'kangis';
        // New KANGIS. A separator is tolerated ("KN 2690") because the registry writes both
        // forms; note this must stay disjoint from the old-MLS "KN 1234" files matched by
        // isOldMlsKnFileNo(), which are Ministry recertifications, not KANGIS ones — those are
        // distinguished downstream by the link's stored transaction_type, not by shape.
        if (preg_match('/^KN[\s-]?\d{2,6}$/i', $cleanFileNo))
            return 'new_kangis';

        return 'unknown';
    }

    /**
     * Helper: Check if a file number is a subdivided unit, and return its mother file number.
     * Excludes ST (Sectional Titling) files completely per project rules.
     */
    public function isSubdividedUnit($fileNo, &$motherFileNo = null): bool
    {
        $cleanFileNo = trim($fileNo);
        if (empty($cleanFileNo)) {
            return false;
        }

        // ST (Sectional Titling) is a separate system; exclude it completely
        if (str_starts_with(strtoupper($cleanFileNo), 'ST-')) {
            return false;
        }
        
        $parts = explode('-', $cleanFileNo);
        $count = count($parts);
        
        // Standard subdivided unit: COM-2025-4-001 (4 parts)
        if ($count === 4) {
            // Region-prefixed MLS file numbers like CON-COM-2026-302 have the 4-digit year at index 2 (third part).
            // Subdivided units without region prefix (COM-2025-4-001) have the 4-digit year at index 1 (second part).
            // If the third part is a 4-digit year, it is a standard MLS file number, NOT a subdivided unit.
            if (isset($parts[2]) && strlen($parts[2]) === 4 && is_numeric($parts[2])) {
                return false;
            }
            array_pop($parts);
            $motherFileNo = implode('-', $parts);
            return true;
        }
        
        // Standard subdivided unit: COM-4-001 (3 parts, second part is not a 4-digit year)
        if ($count === 3 && strlen($parts[1]) !== 4) {
            array_pop($parts);
            $motherFileNo = implode('-', $parts);
            return true;
        }
        
        return false;
    }

    /**
     * Build the print-report data payload for a file/prop_id.
     *
     * This is the shared engine behind both the staff Legal Search print
     * (LegalSearchController::reportTemplateData) and the PHS Portal search slip
     * (PhsSlipController). It dedupes FH/PRA duplicates, canonicalizes instrument
     * types, applies the OP>TOT>RoFO priority + chronological sort, detects
     * caveats/mortgage encumbrances, folds in staging comments, and honors a
     * client-supplied timeline_order and display_* overrides.
     *
     * @param array $q keys: file_number, prop_id, display_file_number, display_file_title,
     *                 display_district_lga, display_land_use, display_size, display_plot_no,
     *                 display_tpno, display_residual_term, display_commencement_date,
     *                 client_name, client_address, timeline_order
     * @return array{status:int, payload:array}
     */
    public function buildPrintReport(array $q): array
    {
        $fileNo = trim((string) ($q['file_number'] ?? ''));
        $searchedFileNo = $fileNo; // Preserve the user's searched file number — fallback may overwrite $fileNo later.
        $propId = trim((string) ($q['prop_id'] ?? ''));
        $displayFileNumber = trim((string) ($q['display_file_number'] ?? ''));
        $displayFileTitle = trim((string) ($q['display_file_title'] ?? ''));
        $displayDistrictLga = trim((string) ($q['display_district_lga'] ?? ''));
        $displayLandUse = trim((string) ($q['display_land_use'] ?? ''));
        $displaySize = trim((string) ($q['display_size'] ?? ''));
        $displayPlotNo = trim((string) ($q['display_plot_no'] ?? ''));
        $displayTpno = trim((string) ($q['display_tpno'] ?? ''));
        $displayResidualTerm = trim((string) ($q['display_residual_term'] ?? ''));
        // Client details originate from the legal_search_tokens row (Pay-Per-Search)
        // and are prefilled/editable in the UI, then passed through here for printing.
        $clientName = trim((string) ($q['client_name'] ?? ''));
        $clientAddress = trim((string) ($q['client_address'] ?? ''));
        // Fallback: if the UI did not carry client details (e.g. Super Admin bypass or
        // an already-used token), resolve them from the latest token for this file.
        if (($clientName === '' && $clientAddress === '') && $fileNo !== '') {
            $tokenClient = DB::connection('sqlsrv')->table('legal_search_tokens')
                ->where('file_number', $fileNo)
                ->orderByDesc('created_at')
                ->first(['client_name', 'client_address']);
            if ($tokenClient) {
                $clientName = trim((string) ($tokenClient->client_name ?? ''));
                $clientAddress = trim((string) ($tokenClient->client_address ?? ''));
            }
        }

        if ($fileNo === '' && $propId === '') {
            return ['status' => 422, 'payload' => ['success' => false, 'message' => 'file_number or prop_id is required']];
        }

        $timelineOrderRaw = trim((string) ($q['timeline_order'] ?? ''));
        $timelineOrderKeys = [];
        if ($timelineOrderRaw !== '') {
            foreach (array_filter(array_map('trim', explode(',', $timelineOrderRaw))) as $tok) {
                $timelineOrderKeys[$tok] = true;
            }
        }

        // Rows the on-screen timeline dropped as duplicates (its "Excluded /
        // Duplicate Records" panel, `window._excludedRelatedTransactions`) are passed
        // here as `db:id` keys so the printed report hides exactly the same rows. The
        // heavy weighting/dedup lives in the JS (dedupeTransactionsForTimelineAndReport);
        // rather than maintain a second copy in PHP, we honour its verdict directly.
        $excludedRaw = trim((string) ($q['excluded_keys'] ?? ''));
        $excludedKeys = [];
        if ($excludedRaw !== '') {
            foreach (array_filter(array_map('trim', explode(',', $excludedRaw))) as $tok) {
                $excludedKeys[$tok] = true;
            }
        }

        $results = $this->search(['query' => $fileNo]);
        $transactions = $results['transactions'] ?? [];

        $labelToDbKey = [
            'PRA' => 'pra',
            'File History' => 'file_history_staging',
            'CofO' => 'CofO_staging',
            'Deed Registration' => 'deed_registrations',
        ];
        // Drop the rows the timeline excluded as duplicates (matched by their
        // source-table + id key), so the report never prints a record the operator
        // sees crossed out on screen.
        $dropExcluded = function (array $rows) use ($excludedKeys, $labelToDbKey): array {
            if (empty($excludedKeys)) {
                return $rows;
            }
            return array_values(array_filter($rows, function ($row) use ($excludedKeys, $labelToDbKey) {
                $id = (string) ($row['id'] ?? '');
                if ($id === '') {
                    return true; // synthetic rows have no db id — never excluded here
                }
                $label = (string) ($row['source_table'] ?? '');
                $db = $labelToDbKey[$label] ?? $label;
                return !isset($excludedKeys[$db . ':' . $id]);
            }));
        };
        $transactions = $dropExcluded($transactions);

        $allowedPropIds = $propId !== '' ? [$propId] : [];
        foreach ($transactions as $tx) {
            $ppid = trim((string) ($tx['parent_prop_id'] ?? ''));
            if ($ppid !== '') {
                foreach (array_map('trim', explode(',', $ppid)) as $pid) {
                    if ($pid !== '') {
                        $allowedPropIds[] = $pid;
                    }
                }
            }
        }
        $allowedPropIds = array_values(array_unique($allowedPropIds));

        $labelToDb = [
            'PRA' => 'pra',
            'File History' => 'file_history_staging',
            'CofO' => 'CofO_staging',
            'Deed Registration' => 'deed_registrations',
        ];

        if (!empty($transactions) && $propId !== '') {
            $transactions = array_values(array_filter($transactions, function ($row) use ($allowedPropIds, $timelineOrderKeys, $labelToDb) {
                $rowPropId = trim((string) ($row['prop_id'] ?? ''));
                if (in_array($rowPropId, $allowedPropIds, true)) {
                    return true;
                }
                if (!empty($timelineOrderKeys)) {
                    $label = (string) ($row['source_table'] ?? '');
                    $db = $labelToDb[$label] ?? $label;
                    $key = $db . ':' . (string) ($row['id'] ?? '');
                    return isset($timelineOrderKeys[$key]);
                }
                return false;
            }));
        }

        if (empty($transactions) && $propId !== '') {
            $fallback = DB::connection('sqlsrv')
                ->table('file_history_staging')
                ->where('prop_id', $propId)
                ->where(function ($qq) {
                    $qq->where('is_deleted', 0)->orWhereNull('is_deleted');
                })
                ->first();

            if ($fallback) {
                $fileNo = (string) ($fallback->fileno ?? $fallback->mlsFNo ?? '');
                $results = $this->search(['query' => $fileNo]);
                $transactions = $dropExcluded($results['transactions'] ?? []);
            }
        }

        $norm = function ($value): string {
            $v = trim(strtolower((string) $value));
            $v = preg_replace('/\s+/', ' ', $v);
            $v = str_replace([',', '.'], '', $v);
            return $v;
        };

        $sourceBaseScore = function (array $row): float {
            $source = trim((string) ($row['source_table'] ?? ''));
            if ($source === 'PRA')
                return 5.0;
            if ($source === 'Deed Registration')
                return 5.0;
            if ($source === 'CofO')
                return 5.0;
            if ($source === 'File History')
                return 2.5;
            return 1.0;
        };

        $canonicalTransactionType = function ($value) use ($norm): string {
            $raw = $norm($value);
            if ($raw === '')
                return '';

            if (str_contains($raw, 'right of occupancy') || str_contains($raw, 'right of occupanc')) {
                return 'right of occupancy';
            }
            if (preg_match('/^r\s*of\s*o$/i', $raw)) {
                return 'right of occupancy';
            }
            $compact = preg_replace('/\s+/', '', $raw);
            if (preg_match('/^r[o0]f[o0]$/', $compact) || preg_match('/^r[o0]f[o0]occupanc(y)?$/', $compact)) {
                return 'right of occupancy';
            }
            if ($raw === 'customary right of occupancy' || $raw === 'statutory right of occupancy') {
                return 'right of occupancy';
            }

            if (str_contains($raw, 'occupancy permit'))
                return 'occupancy permit';
            if (preg_replace('/\s+/', '', $raw) === 'op')
                return 'occupancy permit';

            if (str_contains($raw, 'transfer of title'))
                return 'transfer of title';

            if ($raw === 'tripartite mortgage')
                return 'deed of mortgage';
            if ($raw === 'legal mortgage')
                return 'deed of mortgage';
            if ($raw === 'equitable mortgage')
                return 'deed of mortgage';

            if ($raw === 'deed of surrender' || $raw === 'deed of release' || $raw === 'deed of surrender & release') {
                return 'deed of surrender and release';
            }

            if (str_contains($raw, 'power of attorney'))
                return 'power of attorney';
            if ($compact === 'poa' || $compact === 'ipoa')
                return 'power of attorney';

            return $raw;
        };

        $cleanNumericValue = function ($value): string {
            if ($value === null)
                return '';
            $s = trim((string) $value);
            if ($s === '')
                return '';
            return preg_replace('/\.0$/', '', $s);
        };

        $hasText = function ($value): bool {
            $s = trim(strtolower((string) ($value ?? '')));
            return $s !== '' && $s !== '-' && $s !== '--' && $s !== 'n/a';
        };

        $hasRegValue = function ($value) use ($cleanNumericValue, $hasText): bool {
            $s = $cleanNumericValue($value);
            return $hasText($s) && $s !== '0';
        };

        $richnessScore = function (array $row) use ($hasText, $hasRegValue): float {
            $source = trim((string) ($row['source_table'] ?? ''));
            if ($source !== 'File History' && $source !== 'PRA') {
                return 0.0;
            }

            $score = 0.0;
            $party1 = $row['party_1'] ?? '';
            $party2 = $row['party_2'] ?? '';
            $party3 = $row['party_3'] ?? '';
            $date = $row['transaction_date'] ?? ($row['deeds_date'] ?? ($row['reg_date'] ?? ''));
            $regDate = $row['reg_date'] ?? ($row['deeds_date'] ?? '');
            $regTime = $row['reg_time'] ?? ($row['deeds_time'] ?? ($row['transaction_time'] ?? ''));
            $serialNo = $row['serial_no'] ?? ($row['serialNo'] ?? '');
            $pageNoVal = $row['page_no'] ?? ($row['pageNo'] ?? '');
            $volumeNo = $row['volume_no'] ?? ($row['volumeNo'] ?? '');

            if ($hasText($party1) || $hasText($party2) || $hasText($party3))
                $score += 2;
            if ($hasRegValue($serialNo) || $hasRegValue($pageNoVal) || $hasRegValue($volumeNo))
                $score += 2;
            if ($hasText($date))
                $score += 2;
            if ($hasText($regTime))
                $score += 2;
            if ($hasText($regDate))
                $score += 2;

            return $score;
        };

        $totalScore = function (array $row) use ($sourceBaseScore): float {
            return $sourceBaseScore($row);
        };

        $recordKey = function (array $row) use ($norm, $canonicalTransactionType, $cleanNumericValue): ?string {
            $source = trim((string) ($row['source_table'] ?? ''));
            if (!in_array($source, ['File History', 'PRA'], true)) {
                return null;
            }

            $transactionType = $canonicalTransactionType($row['transaction_type'] ?? ($row['instrument_type'] ?? ''));
            if ($transactionType === '') {
                return null;
            }

            $serialNo = $cleanNumericValue($row['serial_no'] ?? null) ?: '0';
            $pageNoVal = $cleanNumericValue($row['page_no'] ?? null) ?: '0';
            $volumeNo = $cleanNumericValue($row['volume_no'] ?? null) ?: '0';
            // Treat a placeholder dash as "no reg particulars" (matching the JS timeline
            // and TimelineWeightingService). Otherwise a PRA copy with serial/page/volume
            // of '-' is mistaken for real particulars and keyed differently from its
            // File History twin (which stores '0'), so the duplicate never collapses.
            $hasRealReg = ($serialNo !== '0' && $serialNo !== '' && $serialNo !== '-') ||
                ($pageNoVal !== '0' && $pageNoVal !== '' && $pageNoVal !== '-') ||
                ($volumeNo !== '0' && $volumeNo !== '' && $volumeNo !== '-');

            if ($hasRealReg) {
                return 'reg|' . $transactionType . '|' . $serialNo . '/' . $pageNoVal . '/' . $volumeNo;
            }

            $party1 = $norm($row['party_1'] ?? '');
            $party2 = $norm($row['party_2'] ?? '');
            $party3 = $norm($row['party_3'] ?? '');
            $party4 = $norm($row['party_4'] ?? '');
            $date = $norm($row['transaction_date'] ?? ($row['deeds_date'] ?? ($row['reg_date'] ?? '')));

            $keyDate = $transactionType === 'right of occupancy' ? '' : $date;

            $hasSignal = $transactionType !== '' || $party1 !== '' || $party2 !== '' || $keyDate !== '';
            if (!$hasSignal) {
                return null;
            }

            // Include the row's own file number in the weak (no-reg-particulars) key. Without it,
            // distinct sibling files that share a transaction_type + parties + date collapse into
            // one — e.g. three subdivision fragments, or two Change-of-Purpose children of the same
            // mother. A PRA row and its File-History twin carry the SAME file number, so they still
            // collapse as intended; only genuinely different files are now kept apart.
            $rowFileNo = '';
            foreach (['mlsFNo', 'fileno', 'file_number', 'kangisFileNo', 'NewKANGISFileno'] as $fnCol) {
                $v = $norm($row[$fnCol] ?? '');
                if ($v !== '') {
                    $rowFileNo = $v;
                    break;
                }
            }

            return implode('|', [$transactionType, $party1, $party2, $party3, $party4, $keyDate, $rowFileNo]);
        };

        if (!empty($transactions)) {
            $deduped = [];
            $keyToIndex = [];

            foreach ($transactions as $row) {
                $key = $recordKey($row);
                if ($key === null) {
                    $deduped[] = $row;
                    continue;
                }

                if (!array_key_exists($key, $keyToIndex)) {
                    $keyToIndex[$key] = count($deduped);
                    $deduped[] = $row;
                    continue;
                }

                $idx = $keyToIndex[$key];
                $rowRichness = $richnessScore($row);
                $existingRichness = $richnessScore($deduped[$idx]);

                if ($rowRichness > $existingRichness) {
                    $deduped[$idx] = $row;
                    continue;
                }

                if ($rowRichness === $existingRichness && $totalScore($row) > $totalScore($deduped[$idx])) {
                    $deduped[$idx] = $row;
                }
            }

            $transactions = $deduped;
        }

        $parseTimelineTimeValue = function ($value): array {
            $result = ['h' => 0, 'm' => 0, 's' => 0];
            if ($value === null || $value === '' || $value === '-')
                return $result;

            $text = trim((string) $value);
            if ($text === '')
                return $result;

            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([AP]M)?$/i', $text, $m)) {
                $hour = (int) $m[1];
                $minute = (int) $m[2];
                $second = isset($m[3]) ? (int) $m[3] : 0;
                $ampm = strtoupper((string) ($m[4] ?? ''));

                if ($ampm === 'PM' && $hour < 12)
                    $hour += 12;
                if ($ampm === 'AM' && $hour === 12)
                    $hour = 0;

                return ['h' => $hour, 'm' => $minute, 's' => $second];
            }

            try {
                $parsed = \Carbon\Carbon::parse($text);
                return ['h' => (int) $parsed->format('H'), 'm' => (int) $parsed->format('i'), 's' => (int) $parsed->format('s')];
            } catch (\Throwable $e) {
                return $result;
            }
        };

        $parseTimelineDateValue = function ($value, $timeValue = null) use ($parseTimelineTimeValue): ?int {
            if ($value === null || $value === '' || $value === '-')
                return null;

            $text = trim((string) $value);
            if ($text === '')
                return null;

            $time = $parseTimelineTimeValue($timeValue);

            if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $text, $m)) {
                $day = (int) $m[1];
                $month = (int) $m[2];
                $year = (int) $m[3];
                $dt = \Carbon\Carbon::create($year, $month, $day, $time['h'], $time['m'], $time['s']);
                return $dt ? $dt->timestamp : null;
            }

            // A bare 4-digit year (legacy commissioning rows carry e.g. "2005", the year
            // embedded in the file number) must not reach Carbon::parse — strtotime reads
            // "2005" as the TIME 20:05 on TODAY's date, which dates the row to today and
            // sorts it to the very bottom. Anchor it to Jan 1 of that year instead.
            if (preg_match('/^(?:19|20)\d{2}$/', $text)) {
                $dt = \Carbon\Carbon::create((int) $text, 1, 1, $time['h'], $time['m'], $time['s']);
                return $dt ? $dt->timestamp : null;
            }

            try {
                $dt = \Carbon\Carbon::parse($text);
                if ($timeValue) {
                    $dt->setTime($time['h'], $time['m'], $time['s']);
                }
                return $dt->timestamp;
            } catch (\Throwable $e) {
                return null;
            }
        };

        // OP / TOT / RofO carry their operative date in transaction_date; every other event
        // is keyed off its registration date. Mirrors getTransactionTimestamp() in
        // resources/views/legal_search/js.blade.php — without this branch the printed slip
        // dated those three off reg_date and could order them differently from the screen.
        $getTransactionTimestamp = function (array $item) use ($parseTimelineDateValue, $canonicalTransactionType): ?int {
            $txType = $canonicalTransactionType($item['transaction_type'] ?? ($item['instrument_type'] ?? ''));
            $transactionDateFirst = in_array(
                LegalSearchTimelineWeights::classify($item, $txType),
                [
                    LegalSearchTimelineWeights::OCCUPANCY_PERMIT,
                    LegalSearchTimelineWeights::TRANSFER_OF_TITLE_OP,
                    LegalSearchTimelineWeights::RIGHT_OF_OCCUPANCY,
                ],
                true
            );

            $regDate = ['date' => $item['reg_date'] ?? null, 'time' => $item['reg_time'] ?? null];
            $deedsDate = ['date' => $item['deeds_date'] ?? null, 'time' => $item['deeds_time'] ?? null];
            $txnDate = ['date' => $item['transaction_date'] ?? null, 'time' => $item['transaction_time'] ?? ($item['time'] ?? null)];

            $candidates = $transactionDateFirst
                ? [$txnDate, $deedsDate, $regDate]
                : [$regDate, $txnDate, $deedsDate];

            $candidates = array_merge($candidates, [
                ['date' => $item['cofo_date'] ?? null, 'time' => $item['time'] ?? null],
                ['date' => $item['certificateDate'] ?? null, 'time' => $item['time'] ?? null],
                ['date' => $item['approval_date'] ?? null, 'time' => $item['time'] ?? null],
                ['date' => $item['date'] ?? null, 'time' => $item['time'] ?? null],
            ]);

            foreach ($candidates as $candidate) {
                $ts = $parseTimelineDateValue($candidate['date'], $candidate['time']);
                if ($ts !== null)
                    return $ts;
            }
            return null;
        };

        // Weights come from the shared map so the printed slip and the on-screen timeline
        // (resources/views/legal_search/js.blade.php) order a file identically. Returns null
        // for floating events — parcel updates, decommissionings — which are injected
        // chronologically rather than ranked. See LegalSearchTimelineWeights.
        $getTransPriorityWeight = function (array $row) use ($canonicalTransactionType): ?int {
            $txType = $canonicalTransactionType($row['transaction_type'] ?? ($row['instrument_type'] ?? ''));
            return LegalSearchTimelineWeights::weightFor($row, $txType);
        };

        // The Timeline Weighting Method (spec §3) — the PHP twin of
        // sortTimelineChronologically() in resources/views/legal_search/js.blade.php.
        // Weighted and floating events rank on different keys, so they are sorted in two
        // phases: mixing them in one comparator makes it intransitive (a floater is ordered
        // against weighted rows by date only, never by weight) and the result would then
        // depend on input order.
        $compareByDateThenId = function (array $a, array $b) use ($getTransactionTimestamp): int {
            $ta = $getTransactionTimestamp($a);
            $tb = $getTransactionTimestamp($b);

            if ($ta === null && $tb === null) {
                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
            if ($ta === null)
                return 1;
            if ($tb === null)
                return -1;
            if ($ta !== $tb)
                return $ta <=> $tb;

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        };

        $weighted = [];
        $floating = [];
        foreach ($transactions as $t) {
            if ($getTransPriorityWeight($t) === null) {
                $floating[] = $t;
            } else {
                $weighted[] = $t;
            }
        }

        // Phase 1 — weight DESC, then timestamp ASC, then id.
        usort($weighted, function ($a, $b) use ($compareByDateThenId, $getTransPriorityWeight) {
            $wa = $getTransPriorityWeight($a);
            $wb = $getTransPriorityWeight($b);
            if ($wa !== $wb)
                return $wb <=> $wa;
            return $compareByDateThenId($a, $b);
        });

        // Phase 2 — inject each floater after the last weighted event dated on or before it,
        // so it lands chronologically without disturbing the hierarchy above it.
        usort($floating, $compareByDateThenId);

        // Only a weighted event that actually carries a date can anchor a floater in time.
        $isDatedWeighted = function (array $item) use ($getTransPriorityWeight, $getTransactionTimestamp): bool {
            return $getTransPriorityWeight($item) !== null && $getTransactionTimestamp($item) !== null;
        };

        $transactions = $weighted;
        foreach ($floating as $floater) {
            $ts = $getTransactionTimestamp($floater);
            if ($ts === null) {
                $transactions[] = $floater;
                continue;
            }

            // Anchor = the last dated weighted event on or before this floater. Only
            // originally-weighted rows anchor the search; floaters inserted on an earlier
            // pass never act as anchors themselves.
            $insertAt = 0;
            foreach ($transactions as $i => $existing) {
                if (!$isDatedWeighted($existing))
                    continue;
                if ($getTransactionTimestamp($existing) <= $ts)
                    $insertAt = $i + 1;
            }
            // Advance to just before the next dated weighted event: past floaters already
            // parked on this anchor ($floating is sorted ascending, so this one belongs
            // after them — a Decommissioning must not precede the Subdivision that caused
            // it), and past UNDATED weighted rows, which have no position in time and must
            // stay attached to their weight group rather than be split off by a floater.
            $count = count($transactions);
            while ($insertAt < $count && !$isDatedWeighted($transactions[$insertAt])) {
                $insertAt++;
            }
            array_splice($transactions, $insertAt, 0, [$floater]);
        }
        $transactions = array_values($transactions);

        if (empty($transactions)) {
            // No transactions, but a commissioned/indexed file still has a commissioning event
            // (and, for a "(T)", a temporary-file record). Only bail out when the file does not
            // exist at all; otherwise fall through and render the synthetic commissioning rows.
            if (!$this->reportFileExists($fileNo)) {
                return ['status' => 404, 'payload' => ['success' => false, 'message' => 'No records found']];
            }
        }

        if ($timelineOrderRaw !== '') {
            $orderTokens = array_values(array_filter(array_map('trim', explode(',', $timelineOrderRaw))));
            $orderIndex = [];
            foreach ($orderTokens as $i => $tok) {
                $orderIndex[$tok] = $i;
            }
            $rank = function (array $row) use ($orderIndex, $labelToDb) {
                $label = (string) ($row['source_table'] ?? '');
                $db = $labelToDb[$label] ?? $label;
                $key = $db . ':' . (string) ($row['id'] ?? '');
                return $orderIndex[$key] ?? PHP_INT_MAX;
            };
            usort($transactions, function ($a, $b) use ($rank) {
                $ra = $rank($a);
                $rb = $rank($b);
                if ($ra === $rb) {
                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                }
                return $ra <=> $rb;
            });
            $transactions = array_values($transactions);
        }

        $first = $transactions[0] ?? []; // may be empty when the file has no transactions

        $tc = fn($v) => $v && $v !== '-' ? mb_convert_case(mb_strtolower($v), MB_CASE_TITLE, 'UTF-8') : '-';

        // Title-casing a FILE NUMBER corrupts it ("KN2690" -> "Kn2690", "MLKN 3673" -> "Mlkn 3673").
        // A recertification row's Comment column holds exactly that — the bare KANGIS number — so
        // comments are passed through $tcComment, which leaves a recognisable file number alone
        // and title-cases genuine prose as before.
        $tcComment = function ($v) use ($tc) {
            $s = trim((string) $v);
            if ($s === '' || $s === '-') {
                return '-';
            }
            if (preg_match('/^((MLKN|KNML|KNGP)\s?\d{1,6}([-_]\d{1,3})?|KN[\s-]?\d{2,6}|(CON-)?(RES|COM|IND|AG)(-RC)?-\d{4}-\d+)$/i', $s)) {
                return strtoupper($s);
            }
            return $tc($s);
        };

        $fileNumber = $searchedFileNo ?: (($first['fileno'] ?? null) ?: (($first['file_number'] ?? null) ?: (($first['mlsFNo'] ?? null) ?: '-')));
        $kangisNumber = $first['kangisFileNo'] ?? null;
        if ($kangisNumber === '-')
            $kangisNumber = null;
        $indexedRelatedFileno = null;

        $fileTitle = '-';
        $fiPlotNo = null;
        $fiSize = null;
        $fiTpNo = null;
        $fiLandUse = null;
        $fiLocation = null;
        $fiGroundRentAmount = null;
        $fiGroundRentReceiptDate = null;
        $fiTerm = null;
        $lonLat = '-';
        if ($fileNumber && $fileNumber !== '-') {
            $fileNumberCandidates = array_values(array_unique(array_filter([$fileNumber, $fileNo])));
            $fiList = DB::connection('sqlsrv')->table('file_indexings')
                ->whereNull('deleted_at')
                ->where(function ($qq) use ($fileNumberCandidates) {
                    foreach ($fileNumberCandidates as $candidate) {
                        $qq->orWhere('file_number', $candidate)
                            ->orWhere('temp_file_no', $candidate)
                            ->orWhere('related_fileno', 'like', '%' . $candidate . '%');
                    }
                })
                ->select('file_title', 'plot_number', 'plot_size', 'tp_no', 'land_use_type', 'related_fileno', 'file_number', 'has_temp_file', 'temp_file_no', 'latitude', 'longitude', 'location', 'ground_rent_amount', 'ground_rent_receipt_date', 'term')
                ->get();

            $fi = $this->pickBestIndexingRow($fiList, $fileNumberCandidates);
            if ($fi) {
                if ($fi->file_title)
                    $fileTitle = $tc($fi->file_title);
                $fiPlotNo = $fi->plot_number ?: null;
                $fiSize = $fi->plot_size ?: null;
                $fiTpNo = $fi->tp_no ?: null;
                $fiLandUse = $fi->land_use_type ?: null;
                $fiLocation = trim((string) ($fi->location ?? '')) ?: null;
                $fiGroundRentAmount = $fi->ground_rent_amount ?: null;
                $fiGroundRentReceiptDate = $fi->ground_rent_receipt_date ?: null;
                $fiTerm = trim((string) ($fi->term ?? '')) ?: null;

                // Lon/Lat sourced from the file indexing record (replaces District/LGA
                // on the report). Formatted "longitude, latitude" to match the label.
                $lon = trim((string) ($fi->longitude ?? ''));
                $lat = trim((string) ($fi->latitude ?? ''));
                if ($lon !== '' && $lat !== '') {
                    $lonLat = $lon . ', ' . $lat;
                } elseif ($lon !== '') {
                    $lonLat = $lon;
                } elseif ($lat !== '') {
                    $lonLat = $lat;
                }

                $indexedRelatedFileno = $fi->related_fileno ?? null;
            }
        }

        // Shared with the on-screen LS Timeline and the Pay-Per-Search template so the
        // "SEARCHED (LINKED)" format is identical everywhere it's printed.
        $fileNumberDisplay = $this->resolveFileNumberDisplay(
            DB::connection('sqlsrv'),
            $searchedFileNo,
            $fileNumber,
            $transactions,
            $indexedRelatedFileno,
            $kangisNumber
        );
        if ($fileTitle === '-') {
            // Prefer the registered owner on the fileNumber record (matches the on-screen
            // search() resolution) before the transaction-party heuristic. Needed for
            // commissioned-but-unindexed files whose name lives only in fileNumber.FileName.
            $fnName = $this->resolveFileNameFallback($fileNumber, $fileNo);
            if ($fnName !== null && $fnName !== '') {
                $fileTitle = $tc($fnName);
            }
        }
        if ($fileTitle === '-') {
            $fileTitle = $tc(($first['party_2'] ?? null) ?: (($first['party_1'] ?? null) ?: '-'));
        }
        $district = ($first['districtName'] ?? null) ?: null;
        $lga = ($first['lgsaOrCity'] ?? null) ?: null;
        if ($district === '-')
            $district = null;
        if ($lga === '-')
            $lga = null;
        $districtLga = $tc(implode(', ', array_filter([$district, $lga])) ?: (($first['location'] ?? null) ?: '-'));
        $plotNo = $fiPlotNo ?: (($first['plot_no'] ?? null) ?: '-');

        // Size: the file indexing record is the editable source of truth (Edit File
        // Information modal writes to file_indexings.plot_size), so it takes priority.
        // Only fall back to the source-weighted transaction value when no indexing
        // record has a size on file.
        $size = $fiSize ?: '-';
        if ($size === '-') {
            $bestSizeScore = -1;
            $sizeSourceScores = [
                'CofO_staging' => 4,
                'file_history_staging' => 3,
                'pra' => 2,
                'deed_registrations' => 1,
            ];
            foreach ($transactions as $t) {
                $s = $t['size'] ?? null;
                if ($s && $s !== '-' && trim($s) !== '') {
                    $src = $t['source_table'] ?? '';
                    $score = $sizeSourceScores[$src] ?? 0;
                    if ($score > $bestSizeScore) {
                        $bestSizeScore = $score;
                        $size = trim($s);
                    }
                }
            }
        }
        if (($size === '-' || $size === '') && $propId !== '') {
            $praSize = DB::connection('sqlsrv')->table('pra')
                ->where('prop_id', $propId)
                ->whereNotNull('plot_size')
                ->where('plot_size', '!=', '')
                ->orderByDesc('id')
                ->value('plot_size');
            if ($praSize) {
                $size = $praSize;
            }
        }

        // Fall back to auto-detecting the land use from the file number prefix (e.g. legacy files
        // with no indexing row), matching the on-screen File Information behaviour. Transaction
        // rows carry '-' for an empty land use, so treat that as absent before detecting.
        $firstLandUse = trim((string) ($first['land_use'] ?? ''));
        if ($firstLandUse === '-') {
            $firstLandUse = '';
        }
        $landUse = $tc($fiLandUse ?: ($firstLandUse ?: ($this->detectLandUseFromFileNumber($fileNo) ?: '-')));

        // Prefer the location recorded on the file indexing record; fall back to
        // the longest location string found across the transaction rows.
        $bestLocation = $fiLocation ?: '-';
        if ($bestLocation === '-') {
            foreach ($transactions as $t) {
                $loc = $t['location'] ?? '';
                if ($loc && $loc !== '-' && mb_strlen($loc) > mb_strlen($bestLocation === '-' ? '' : $bestLocation)) {
                    $bestLocation = $loc;
                }
            }
        }
        $plotDescription = $tc($bestLocation);

        $tpno = $fiTpNo ?: (($first['tp_no'] ?? null) ?: '-');

        $rows = [];
        foreach ($transactions as $idx => $t) {
            $regDate = $t['deeds_date'] ?? null;
            if (!$regDate)
                $regDate = $t['reg_date'] ?? null;
            if ($regDate) {
                try {
                    $regDate = \Carbon\Carbon::parse($regDate)->format('M j, Y');
                } catch (\Exception $e) { /* keep as-is */
                }
            }
            if (!$regDate)
                $regDate = $t['transaction_date'] ?: '-';

            $regTime = $t['deeds_time'] ?? null;
            if (!$regTime)
                $regTime = $t['reg_time'] ?? null;
            if ($regTime && $regTime !== '-') {
                try {
                    $regTime = \Carbon\Carbon::parse($regTime)->format('g:i A');
                } catch (\Exception $e) { /* keep as-is */
                }
            }
            $regTime = $regTime ?: '-';

            $serialNo = $t['serial_no'] ?? null;
            $pageNoVal = $t['page_no'] ?? null;
            $volumeNo = $t['volume_no'] ?? null;
            $cleanReg = fn($v) => ($v && $v !== '-') ? preg_replace('/\.0$/', '', trim($v)) : '0';
            $hasAnyReg = ($serialNo && $serialNo !== '-') || ($pageNoVal && $pageNoVal !== '-') || ($volumeNo && $volumeNo !== '-');
            $regNoDisplay = $hasAnyReg
                ? $cleanReg($serialNo) . '/' . $cleanReg($pageNoVal) . '/' . $cleanReg($volumeNo)
                : '0/0/0';

            // Transaction Date — shown in its own report column, distinct from the
            // registration date. Formatted like reg_date when parseable.
            $txnDate = trim((string) ($t['transaction_date'] ?? ''));
            if ($txnDate !== '' && $txnDate !== '-') {
                // A bare 4-digit year (a legacy file's commissioning year, taken from its file
                // number) prints as the year itself — Carbon::parse would read "2005" as the
                // time 20:05 today and stamp the row with today's date.
                if (!preg_match('/^(?:19|20)\d{2}$/', $txnDate)) {
                    $parsedTxn = rescue(fn() => \Carbon\Carbon::parse($txnDate), null, false);
                    if ($parsedTxn) {
                        $txnDate = $parsedTxn->format('M j, Y');
                    }
                }
            } else {
                $txnDate = '-';
            }

            $rowFileNo = (string) ($t['fileno'] ?: ($t['file_number'] ?: ($t['mlsFNo'] ?: '-')));
            // A SYSTEM temporary number ("TEMP-xxx") is an internal placeholder — never
            // shown in the File No field.
            if ($this->isSystemTempFileNo($rowFileNo)) {
                $rowFileNo = '-';
            }
            $rows[] = [
                'sn' => $idx + 1,
                'file_no' => $rowFileNo,
                'lifecycle_file_no' => $this->extractLifecycleFileNo($t),
                'grantor' => $tc($t['party_1'] ?: '-'),
                'grantee' => $tc($t['party_2'] ?: '-'),
                'party_3' => $tc($t['party_3'] ?: '-'),
                'party_4' => $tc($t['party_4'] ?: '-'),
                'instrument_type' => $tc($t['transaction_type'] ?: '-'),
                'transaction_date' => $txnDate,
                'reg_time' => $regTime,
                'reg_date' => $regDate,
                'reg_no' => $regNoDisplay,
                'size' => $t['size'] ?: '-',
                'caveat' => $t['caveat'] ?: '-',
                'comments' => $t['is_caveated'] ? $tcComment($t['caveated_comment'] ?: ($t['comments'] ?: '-')) : $tcComment($t['comments'] ?: '-'),
                // Extra metadata (ignored by the print slip) so consumers that render
                // the same LS-weighed timeline on-screen — e.g. the PHS portal —
                // can show source badges and location identically to the slip.
                'source_table' => $labelToDb[$t['source_table'] ?? ''] ?? ($t['source_table'] ?? ''),
                'location' => $t['location'] ?? '',
                // Linked counterpart of a Related Fileno row (e.g. a KANGIS recert's land file).
                // Needed by resolveAliasHintOwners()/extractMainEndpoint() so a KANGIS alias that
                // belongs to an ANCESTOR folds into the ancestor's block, not the searched child's.
                'parent_file_number' => $t['parent_file_number'] ?? null,
                // Preserve the mother-ST-commissioning flag so the lifecycle dedupe keeps it
                // distinct from the Land File Commissioning row and arrange places it
                // chronologically (not hoisted) within the land block.
                '_st_primary_commissioning' => $t['_st_primary_commissioning'] ?? null,
            ];
        }

        // KANGIS Recertification placement is applied per lifecycle transaction phase
        // inside groupTimelineByLifecycle().

        // ── Default "File Commissioning" record (always the first timeline row) ──
        // Highest priority (weight 12), so it precedes every other transaction.
        // Commissioning Date = the file's genuine KLAES commissioning date
        // (fileNumber.SOURCE starting with 'MLS_Commissioned'). For a legacy file
        // digitized into KLAES the real commissioning date is unknown, so it prints the
        // year embedded in the file number instead. Reg particulars are 0/0/0.
        $commissioningDate = $this->resolveCommissioningInfo($fileNumber, $fileNo)['date'];

        // Party 2 of the commissioning / temporary rows is the ASSIGNOR of the file's
        // KLAES-registered Deed of Assignment (the original allottee), falling back to the
        // file title (latest owner) when the file has no such deed.
        $commissioningHolder = $this->resolveCommissioningHolder(DB::connection('sqlsrv'), $fileNo)
            ?: ($fileTitle ?: '-');

        // ── "Temporary File" record — printed directly below File Commissioning ──
        // Present when the searched file has a temporary "(T)" sibling, OR when the searched
        // file is itself the temporary "(T)" number. In the latter case resolveTempFileNumber()
        // returns null (there is no further child temp), so fall back to the searched "(T)" itself
        // so its own commissioning row still appears above its transactions.
        $tempFileNumber = $this->resolveTempFileNumber(DB::connection('sqlsrv'), $fileNo, $fi ?? null);
        if (!$tempFileNumber) {
            foreach ([$fileNumber, $fileNo] as $cand) {
                if (preg_match('/\(\s*T\s*\)\s*$/i', (string) $cand)) {
                    $tempFileNumber = trim((string) $cand);
                    break;
                }
            }
        }
        $tempRow = null;
        if ($tempFileNumber) {
            $tempRow = [
                'sn' => 0, // renumbered below
                'file_no' => $tempFileNumber,
                'grantor' => 'Kano State Ministry of Land and Physical Planning',
                'grantee' => $commissioningHolder,
                'party_3' => '-',
                'party_4' => '-',
                'instrument_type' => 'Temporary File',
                'transaction_date' => '-',
                'reg_time' => '-',
                'reg_date' => '-',
                'reg_no' => '0/0/0',
                'size' => '-',
                'caveat' => 'No',
                'comments' => '-',
                'source_table' => 'Temporary File',
                'location' => '',
            ];
        }

        // The File Commissioning row represents the permanent/main file, so it
        // always carries the main file number. When the searched file is itself a
        // temporary "(T)" number, strip the "(T)" — the temporary number appears on
        // its own "Temporary File" row directly below.
        $commissioningFileNo = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', (string) $fileNumber);
        $commissioningFileNo = trim($commissioningFileNo) !== '' ? trim($commissioningFileNo) : $fileNumber;

        // Legacy files digitized into KLAES (no genuine commissioning_date, so
        // $commissioningDate is '-') have no real commissioning date on record, but
        // their file number itself encodes the year they were originally commissioned
        // (e.g. RES-2001-3874 -> 2001, CON-RES-1993-387 -> 1993). Surface that year as
        // the Transaction Date on the row instead of leaving it blank.
        $commissioningTxnDate = '-';
        if ($commissioningDate === '-') {
            $commissioningTxnDate = $this->extractYearFromFileNumber($commissioningFileNo) ?? '-';
        }

        // An ST mother file has TWO commissioning events (Land + ST). When the searched
        // file is the land number of an ST scheme primary, relabel this generic row as
        // "Land File Commissioning" so it reads distinctly from the "ST File Commissioning"
        // row (np_fileno) that folds into the same block. source_table stays
        // 'File Commissioning' so classification/hoisting are unaffected.
        $isStMother = false;
        try {
            if (Schema::connection('sqlsrv')->hasTable('st_file_numbers')) {
                $landNorm = strtoupper(trim((string) $commissioningFileNo));
                $isStMother = $landNorm !== '' && DB::connection('sqlsrv')->table('st_file_numbers')
                    ->where('file_no_type', 'PRIMARY')
                    ->where(function ($q) use ($landNorm) {
                        $q->whereRaw('UPPER(LTRIM(RTRIM(mls_fileno))) = ?', [$landNorm])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(fileno))) = ?', [$landNorm]);
                    })
                    ->exists();
            }
        } catch (\Throwable $e) {
            $isStMother = false;
        }

        $commissioningRow = [
            'sn' => 0, // renumbered below
            'file_no' => $commissioningFileNo ?: '-',
            'lifecycle_file_no' => $this->normalizeLifecycleFileNo($commissioningFileNo),
            // Party 1 is the commissioning authority; Party 2 is the file owner/title
            // (the Ministry commissioned the file for them).
            'grantor' => 'Kano State Ministry of Land and Physical Planning',
            'grantee' => $commissioningHolder,
            'party_3' => '-',
            'party_4' => '-',
            'instrument_type' => $isStMother ? 'Land File Commissioning' : 'File Commissioning',
            'transaction_date' => $commissioningTxnDate,
            'reg_time' => '-',
            'reg_date' => $commissioningDate,
            'reg_no' => '0/0/0',
            'size' => '-',
            'caveat' => 'No',
            'comments' => '-',
            'source_table' => 'File Commissioning',
            'location' => '',
        ];

        if ($tempRow) {
            $tempRow['lifecycle_file_no'] = $this->normalizeLifecycleFileNo($tempFileNumber);
            $rows[] = $tempRow;
        }
        // A KANGIS-format file (KNML/MLKN/KNGP/KN…) is an alias of a permanent land file,
        // not a lifecycle of its own — it is never shown as a File Commissioning event
        // (only its Recertification appears). A SYSTEM temporary number ("TEMP-xxx") is an
        // internal placeholder and is suppressed the same way.
        if (!$this->isKangisFormat($commissioningFileNo) && !$this->isSystemTempFileNo($commissioningFileNo)) {
            $rows[] = $commissioningRow;
        }

        // Group the entire timeline by lifecycle owner: each file's complete
        // lifecycle (commissioning -> transactions -> decommissioning) is rendered
        // before the next related file begins.
        $rows = $this->groupTimelineByLifecycle(
            $rows,
            $commissioningFileNo,
            $fileNo,
            $this->resolveAliasHintOwners($rows, $this->aliasHintsFromDisplay($fileNumberDisplay ?? null))
        );

        // Renumber serial numbers so the first commissioning row is #1.
        foreach ($rows as $rowIndex => &$rowRef) {
            $rowRef['sn'] = $rowIndex + 1;
        }
        unset($rowRef);

        $caveatedRecord = collect($transactions)->first(fn($t) => $t['is_caveated']);
        $caveatId = $caveatedRecord ? ($caveatedRecord['caveat_id'] ?? null) : null;

        $caveatNumber = null;
        if ($caveatId) {
            $caveatNumber = DB::connection('sqlsrv')->table('caveats')
                ->where('id', $caveatId)
                ->value('caveat_number');
        }
        if (!$caveatNumber) {
            $caveatNumber = DB::connection('sqlsrv')->table('caveats')
                ->where('status', 'active')
                ->where(function ($qq) use ($fileNumber, $fileNo, $propId) {
                    $variants = array_unique(array_filter([$fileNumber, $fileNo]));
                    foreach ($variants as $i => $fn) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $qq->{$method}(function ($sub) use ($fn) {
                            $sub->where('file_number_mlsf', $fn)
                                ->orWhere('file_number_kangis', $fn)
                                ->orWhere('file_number_new_kangis', $fn);
                        });
                    }
                    if ($propId) {
                        $qq->orWhere('prop_id', $propId);
                    }
                })
                ->orderByDesc('id')
                ->value('caveat_number');
        }

        $mortgageCaveat = false;

        // Match the instrument FAMILY, not one exact label. The digitised records
        // store mortgages as "MORTGAGE", "Deed of Mortgage", "Tripartite/Legal/
        // Equitable Mortgage" and discharges as "SURRENDER AND RELEASE" or "Deed of
        // Surrender and Release" — so detection keys on the family word(s), never a
        // fixed "deed of …" prefix (which silently missed the release rows and left
        // a discharged mortgage flagged as active).
        $isMortgageType = fn($t) => stripos($t['transaction_type'] ?? '', 'mortgage') !== false;
        $isReleaseType = fn($t) => stripos($t['transaction_type'] ?? '', 'surrender') !== false
            && stripos($t['transaction_type'] ?? '', 'release') !== false;

        // A mortgage keeps the title encumbered until it is discharged by a Deed of
        // Surrender & Release. Detection is by COUNT, not mere presence and
        // independently of dates: each Surrender & Release discharges one mortgage,
        // so the title stays "Under an Active Mortgage" while there are MORE
        // mortgages than releases on the file. This correctly flags a file that
        // carries a second, unrelated mortgage (e.g. from a different lender) that
        // was never surrendered, and still auto-raises the remark for mortgages
        // recorded only by registration particulars (no reg_date) — the searcher
        // should NOT have to save the Title Encumbrance Remark by hand. Excluded/
        // duplicate rows are already dropped from $transactions above. Mirrors the
        // frontend rule in showCommentSections() (js.blade.php).
        $mortgageCount = collect($transactions)->filter($isMortgageType)->count();
        $releaseCount = collect($transactions)->filter($isReleaseType)->count();
        $hasMortgage = $mortgageCount > 0;
        $mortgageCaveat = $mortgageCount > $releaseCount;

        $isCaveated = (bool) $caveatedRecord || $mortgageCaveat;

        $hasCofo = collect($transactions)->contains(function ($t) {
            $type = strtolower($t['transaction_type'] ?? '');
            return str_contains($type, 'certificate of occupancy')
                || str_contains($type, 'c of o')
                || str_contains($type, 'cofo')
                || str_contains($type, 'c.of.o')
                || str_contains($type, 'c/o/o');
        });

        $now = now();
        $user = auth()->user();
        $generatedBy = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? $user->name ?? '')) : 'system';
        if (trim($generatedBy) === '') {
            $generatedBy = 'system';
        }

        $remarksTimestamp = 'These details are as at ' . $now->format('l, F j, Y g:i A');
        $investigation = $this->resolveDcivInvestigation([$fileNumber, $fileNo], $transactions);

        // A record flagged via Title Status Update (title_status = 1, e.g. an
        // initiated Withdrawal/Cancellation/Revocation) means the title is NOT
        // free from encumbrances, regardless of caveat/mortgage state — its
        // title_status_remark takes precedence over the default wording.
        $flaggedTxn = collect($transactions)->first(fn($t) => (int) ($t['title_status'] ?? 0) === 1);
        $flaggedRemark = $flaggedTxn ? trim((string) ($flaggedTxn['title_status_remark'] ?? '')) : '';

        if ($caveatedRecord && $mortgageCaveat) {
            $caveatNote = 'This Property is Under an Active Mortgage and Caveat' . ($caveatNumber ? " (See, {$caveatNumber})" : '') . '!!!';
        } elseif ($caveatedRecord) {
            $caveatNote = 'N.B. This Property is Under an Active Caveat' . ($caveatNumber ? " (See, {$caveatNumber})" : '') . '!!!';
        } elseif ($mortgageCaveat) {
            $caveatNote = 'This Property is Under an Active Mortgage !!!';
        } elseif (!$hasCofo) {
            $caveatNote = 'Based on our available records, the subject title is currently at the Letter of Grant stage, hence Certificate of Occupancy is yet to be issued. However the title is free from encumbrances.';
        } else {
            $caveatNote = 'Based on our available records, the title is free from encumbrances.';
        }

        if ($flaggedTxn) {
            $caveatNote = $flaggedRemark !== '' ? $flaggedRemark : 'N.B. This title is not free from encumbrances.';
        }

        if ($investigation !== null) {
            // If the property has no additional caveat/mortgage, only show the investigation notice.
            // Do NOT show "free from encumbrances" alongside an active investigation.
            if ($caveatedRecord || $mortgageCaveat) {
                $caveatNote = 'This Property is under Investigation. ' . $caveatNote;
            } else {
                $caveatNote = 'This Property is under Investigation.';
            }
        }

        $comments = DB::connection('sqlsrv')->table('ls_comment_staging')
            ->where(function ($qq) use ($fileNumber, $fileNo, $propId) {
                $qq->where('file_number', $fileNumber);
                if ($fileNo && $fileNo !== $fileNumber) {
                    $qq->orWhere('file_number', $fileNo);
                }
                if ($propId) {
                    $qq->orWhere('prop_id', $propId);
                }
            })
            ->get()
            ->keyBy('comment_type');

        // Ground Rent: the receipted payment on file (file_indexings) and the
        // manually-entered "Not Paid" comment are independent facts (e.g. an
        // earlier year's charge was paid, a later year's is outstanding) — both
        // print together when both are present, rather than one hiding the other.
        $groundRentParts = [];
        if ($fiGroundRentAmount && $fiGroundRentReceiptDate) {
            $grDate = rescue(fn() => \Carbon\Carbon::parse($fiGroundRentReceiptDate)->format('M j, Y'), null, false);
            $groundRentParts[] = 'Ground Rent Including Land Use Charge (Amounting to ₦' . number_format($fiGroundRentAmount, 2) . ' paid on ' . ($grDate ?: $fiGroundRentReceiptDate) . ')';
        }
        $groundRent = $comments->get('ground_rent');
        if ($groundRent) {
            if ($groundRent->amount) {
                $notPaidText = 'Ground Rent Including Land Use Charge Not Paid (Amounting to ₦' . number_format($groundRent->amount, 2) . ')';
                if ($groundRent->comment) {
                    $notPaidText .= ' — ' . $groundRent->comment;
                }
                $groundRentParts[] = $notPaidText;
            } elseif ($groundRent->comment) {
                $groundRentParts[] = $groundRent->comment;
            }
        }
        $groundRentText = !empty($groundRentParts) ? implode('. ', $groundRentParts) : null;

        $noCofoComment = null;
        $noCofo = $comments->get('no_cofo');
        if ($noCofo && $noCofo->comment) {
            $noCofoComment = $noCofo->comment;
        }

        $encumbranceComment = null;
        $encumbrance = $comments->get('encumbrance');
        if ($encumbrance && $encumbrance->comment) {
            $encumbranceComment = $encumbrance->comment;
        }

        // The no-CofO / "Letter of Grant" reassurance can arrive from two
        // channels at once: the auto-generated caveat note (produced above when
        // the file has no CofO) and a saved No CofO remark. When a saved remark
        // is present it supersedes the auto note, so drop the auto note to avoid
        // printing the same sentence twice. Only the untouched positive defaults
        // are dropped — a genuine caveat/mortgage/investigation/flagged warning
        // won't exact-match and is always preserved.
        $autoFreeFromDefaults = [
            'Based on our available records, the subject title is currently at the Letter of Grant stage, hence Certificate of Occupancy is yet to be issued. However the title is free from encumbrances.',
            'Based on our available records, the title is free from encumbrances.',
        ];
        if ($noCofoComment && is_string($caveatNote) && in_array($caveatNote, $autoFreeFromDefaults, true)) {
            $caveatNote = null;
        }

        // The standalone encumbrance reassurance ("...the subject title is free
        // from encumbrances") is redundant when another remark already carries
        // that phrase — e.g. the no-CofO / Letter of Grant caveat note or a saved
        // No CofO remark both end with "the title is free from encumbrances".
        // Suppress it so the reassurance doesn't print twice.
        if ($encumbranceComment
            && ((is_string($caveatNote) && stripos($caveatNote, 'free from encumbrances') !== false)
                || (is_string($noCofoComment) && stripos($noCofoComment, 'free from encumbrances') !== false))
        ) {
            $encumbranceComment = null;
        }

        $litigationComment = null;
        $litigation = $comments->get('litigation');
        if ($litigation && $litigation->comment) {
            $litigationComment = $litigation->comment;
        }

        $generalComment = null;
        $general = $comments->get('general');
        if ($general && $general->comment) {
            $generalComment = $general->comment;
        }

        // W/R/C and CoFO status from the duplicate_fileno registry.
        // Mirrors the Caveat note: when the searched file exists in
        // duplicate_fileno under a [WRC] or [COFO_*] tag, surface a general
        // notice on the report. Category is carried by the comment tag prefix
        // (the registry column is not reliable). Match on the searched file's
        // number variants or its prop_id.
        //
        // The notice text can be edited per file from the LS comments panel: a
        // saved ls_comment_staging row (comment_type 'wrc' / 'cofo') overrides
        // the default wording. The notice itself is ONLY rendered when the file
        // genuinely qualifies here, so an override can never fabricate a notice
        // for a clean title.
        $wrcComment = null;
        $cofoComment = null;
        $isWrcFile = false;
        $cofoState = null; // 'collected' | 'ready' | null
        $dupVariants = $this->fileNumberVariants(
            ...array_filter([$fileNumber, $fileNo], fn ($v) => trim((string) $v) !== '')
        );
        if (!empty($dupVariants) || $propId) {
            $dupComments = DB::connection('sqlsrv')->table('duplicate_fileno')
                ->where(function ($qq) use ($dupVariants, $propId) {
                    if (!empty($dupVariants)) {
                        $qq->whereIn('file_number', $dupVariants);
                    }
                    if ($propId) {
                        $qq->orWhere('prop_id', $propId);
                    }
                })
                ->pluck('comment');

            foreach ($dupComments as $c) {
                $tag = strtoupper(trim((string) $c));
                if (str_starts_with($tag, '[WRC]')) {
                    $isWrcFile = true;
                } elseif (str_starts_with($tag, '[COFO_COLLECTED]')) {
                    // Collected is the stronger state — always overrides "ready".
                    $cofoState = 'collected';
                } elseif (str_starts_with($tag, '[COFO_READY]') && $cofoState === null) {
                    $cofoState = 'ready';
                }
            }
        }

        if ($isWrcFile) {
            $wrcStaged = $comments->get('wrc');
            $wrcOverride = $wrcStaged ? trim((string) $wrcStaged->comment) : '';
            $wrcComment = $wrcOverride !== '' ? $wrcOverride : 'N.B. This Application has been Cancelled !!!';

            // A cancelled application must not also carry the reassuring
            // "free from encumbrances / Letter of Grant" note — that would
            // contradict the cancellation. Suppress that positive note. Genuine
            // adverse notes (active caveat / mortgage / investigation / flagged
            // title_status) still stand.
            if (!$caveatedRecord && !$mortgageCaveat && $investigation === null && !$flaggedTxn) {
                $caveatNote = null;
            }
        }
        if ($cofoState !== null) {
            $cofoStaged = $comments->get('cofo');
            $cofoOverride = $cofoStaged ? trim((string) $cofoStaged->comment) : '';
            if ($cofoOverride !== '') {
                $cofoComment = $cofoOverride;
            } else {
                $cofoComment = $cofoState === 'collected'
                    ? 'The Certificate of Occupancy for this property has been collected.'
                    : 'The Certificate of Occupancy for this property is ready for collection.';
            }
        }

        // DCIV investigation flag: when the searched file — or any file number
        // resolved onto the timeline rows — is tied to a DCIV in
        // master_dciv_links (on EITHER side), the property is under investigation.
        // NOTE: The DCIV "Under Investigation" note is injected into each matching
        // row's Comments cell inside search() (called above), so it already flows
        // through into the report rows — no separate handling is needed here.

        $generatedByText = 'Generated by ' . $generatedBy . ' at ' . $now->format('g:i A & d/m/Y');

        $qrData = substr(hash_hmac('sha256', $fileNumber . $now->timestamp, config('app.key')), 0, 11);

        if ($displayFileNumber !== '')
            $fileNumberDisplay = $displayFileNumber;
        if ($displayFileTitle !== '')
            $fileTitle = $tc($displayFileTitle);
        if ($displayDistrictLga !== '')
            $districtLga = $tc($displayDistrictLga);
        if ($displayLandUse !== '')
            $landUse = $tc($displayLandUse);
        if ($displaySize !== '')
            $size = $displaySize;
        if ($displayPlotNo !== '')
            $plotNo = $displayPlotNo;
        if ($displayTpno !== '')
            $tpno = $displayTpno;

        // Commencement date of the R of O term: the UI-passed date wins, then the
        // saved per-file date (ls_comment_staging 'commencement_date'), then the
        // CofO/R of O grant's Transaction/Reg Date (CofO takes priority — see
        // commencementDateFromTransactions()). Residual Term = the land-use term
        // minus years elapsed since it, unless the Residual Term editor passed
        // an explicit value. The source badge ("CofO"/"RofO") only applies to
        // the untouched auto-filled value — a UI-passed or staged override has
        // no associated source.
        $displayCommencementDate = trim((string) ($q['display_commencement_date'] ?? ''));
        $displayCommencementSource = trim((string) ($q['display_commencement_source'] ?? ''));
        $stagedCommencementDate = trim((string) ($comments->get('commencement_date')->comment ?? ''));
        $commencementDate = null;
        $commencementSource = null;
        if ($displayCommencementDate !== '' && $displayCommencementDate !== '-') {
            $commencementDate = rescue(fn() => \Carbon\Carbon::parse($displayCommencementDate), null, false);
            if ($commencementDate) {
                $commencementSource = $displayCommencementSource !== '' ? $displayCommencementSource : null;
            }
        }
        if (!$commencementDate && $stagedCommencementDate !== '' && $stagedCommencementDate !== '-') {
            $commencementDate = rescue(fn() => \Carbon\Carbon::parse($stagedCommencementDate), null, false);
        }
        if (!$commencementDate) {
            [$commencementDate, $commencementSource] = $this->commencementDateFromTransactions($transactions);
        }
        // Term of the R of O: the value saved on the file indexing record (Edit File
        // Information) overrides the one derived from land use, and the Residual Term
        // is computed from whichever applies — unless the Residual Term editor passed
        // an explicit value, which always wins.
        $termYearsOverride = null;
        if ($fiTerm !== null && preg_match('/\d+/', $fiTerm, $m)) {
            $termYearsOverride = (int) $m[0];
        }
        $term = $fiTerm ?: $this->termFromLandUse($landUse);
        $residualTerm = $displayResidualTerm !== ''
            ? $displayResidualTerm
            : $this->residualTermFromYear(
                $landUse,
                $commencementDate ? (int) $commencementDate->year : null,
                $termYearsOverride
            );

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => [
                    'date_line' => 'Date: ' . $now->format('F j, Y'),
                    'file_number' => $fileNumberDisplay,
                    'file_title' => $fileTitle,
                    'district_lga' => $districtLga,
                    'lon_lat' => $lonLat,
                    'land_use' => $landUse,
                    'plot_no' => $plotNo,
                    'size' => $size,
                    'plot_description' => $plotDescription,
                    'tpno' => $tpno,
                    'term' => $term,
                    'residual_term' => $residualTerm,
                    'commencement_date' => $commencementDate ? $commencementDate->format('jS F, Y') : null,
                    'commencement_source' => $commencementSource,
                    'client_name' => $clientName !== '' ? $clientName : null,
                    'client_address' => $clientAddress !== '' ? $clientAddress : null,
                    'rows' => $rows,
                    'remarks' => $remarksTimestamp,
                    'caveat_note' => $caveatNote,
                    'is_caveated' => $isCaveated,
                    'is_flagged' => (bool) $flaggedTxn,
                    'under_investigation' => $investigation !== null,
                    'has_cofo' => $hasCofo,
                    'ground_rent' => $groundRentText,
                    'no_cofo_comment' => $noCofoComment,
                    'encumbrance_comment' => $encumbranceComment,
                    'litigation_comment' => $litigationComment,
                    'general_comment' => $generalComment,
                    'wrc_comment' => $wrcComment,
                    'cofo_comment' => $cofoComment,
                    'generated_by' => $generatedByText,
                    'generated_date' => $now->format('F j, Y'),
                    'full_name' => $generatedBy,
                    'rank' => $user ? trim((string) ($user->rank ?? '')) : '',
                    'qr_data' => $qrData,
                ],
            ],
        ];
    }

    /**
     * Commencement date of the R of O term. Two possible sources, checked in
     * priority order (mirrors lsFindCommencementSource() in js.blade.php):
     *   1. Certificate of Occupancy — earliest dated row's transaction_date.
     *   2. Right of Occupancy — earliest dated row's Transaction Date
     *      (falling back to Reg Date) — used only when no dated CofO exists.
     * Returns [Carbon|null $date, 'CofO'|'RofO'|null $source].
     */
    private function commencementDateFromTransactions(array $transactions): array
    {
        $earliestFor = function (callable $isType) use ($transactions): ?\Carbon\Carbon {
            $found = null;
            foreach ($transactions as $t) {
                $type = strtolower(trim((string) ($t['transaction_type'] ?? '')));
                $compact = preg_replace('/[^a-z0-9]/', '', $type);
                if (!$isType($type, $compact)) {
                    continue;
                }
                foreach ([$t['transaction_date'] ?? null, $t['reg_date'] ?? null, $t['deeds_date'] ?? null] as $cand) {
                    $cand = trim((string) $cand);
                    if ($cand === '' || $cand === '-') {
                        continue;
                    }
                    $parsed = rescue(fn() => \Carbon\Carbon::parse($cand), null, false);
                    if ($parsed && ($found === null || $parsed->lt($found))) {
                        $found = $parsed;
                    }
                    break; // Transaction Date wins; only fall through when it is absent.
                }
            }
            return ($found !== null && !$found->isFuture()) ? $found : null;
        };

        $cofoDate = $earliestFor(fn($type, $compact) =>
            str_contains($type, 'certificate of occupanc')
            || preg_match('/^c\s*of\s*o\b/', $type)
            || preg_match('/^c[o0]f[o0]$/', (string) $compact)
        );
        if ($cofoDate !== null) {
            return [$cofoDate, 'CofO'];
        }

        $rofoDate = $earliestFor(fn($type, $compact) =>
            str_contains($type, 'right of occupanc')
            || preg_match('/^r\s*of\s*o\b/', $type)
            || preg_match('/^r[o0]f[o0]/', (string) $compact)
        );
        if ($rofoDate !== null) {
            return [$rofoDate, 'RofO'];
        }

        return [null, null];
    }

    /**
     * Term of the Right of Occupancy derived from land use: Residential/
     * Agricultural = 99 years, Commercial/Industrial = 40 years. Returns the
     * year count, or null when the land use has no defined term.
     */
    private function termYearsFromLandUse(?string $landUse): ?int
    {
        $lu = strtoupper(trim((string) $landUse));
        if (str_contains($lu, 'RESIDENT') || str_starts_with($lu, 'RES') || str_contains($lu, 'AGRIC') || str_starts_with($lu, 'AG')) {
            return 99;
        }
        if (str_contains($lu, 'COMMERC') || str_starts_with($lu, 'COM') || str_contains($lu, 'INDUSTR') || str_starts_with($lu, 'IND')) {
            return 40;
        }
        return null;
    }

    /**
     * The land-use term as printed, e.g. "99 Years", or null when unknown.
     */
    private function termFromLandUse(?string $landUse): ?string
    {
        $years = $this->termYearsFromLandUse($landUse);
        return $years === null ? null : $years . ' Years';
    }

    /**
     * Residual Term of the Right of Occupancy: the term minus the years elapsed
     * since the commencement year. Returns e.g. "28 Years", or null when the
     * term is unknown or the year is unusable. $termYearsOverride is the Term
     * saved on the file indexing record and takes priority over the land-use
     * derived term.
     */
    private function residualTermFromYear(?string $landUse, ?int $commencementYear, ?int $termYearsOverride = null): ?string
    {
        $termYears = $termYearsOverride ?: $this->termYearsFromLandUse($landUse);
        $nowYear = (int) now()->year;
        if ($termYears === null || $commencementYear === null || $commencementYear <= 1000 || $commencementYear > $nowYear) {
            return null;
        }
        return max($termYears - ($nowYear - $commencementYear), 0) . ' Years';
    }

    /**
     * Determine whether a file is under DCIV investigation, and why.
     *
     * A property is "Under Investigation" when the searched file — or any of the
     * file-number variants resolved onto its timeline rows — appears in
     * master_dciv_links on EITHER side: as the DCIV parent (dciv_file_number)
     * or as a linked child (related_file_number). The note is accompanied by the
     * DCIV's reason (dciv_reason) and parent file number when available.
     *
     * @param array $fileNos      Primary candidate file numbers (searched + resolved)
     * @param array $transactions Result rows whose file-number variants are also candidates
     * @return array{note:string, reason:?string, dciv_file_number:?string}|null
     */
    private function resolveDcivInvestigation(array $fileNos, array $transactions = []): ?array
    {
        $seen = [];
        $candidates = [];
        $add = function ($value) use (&$seen, &$candidates) {
            $v = trim((string) $value);
            if ($v === '' || $v === '-') {
                return;
            }
            $u = strtoupper($v);
            if (isset($seen[$u])) {
                return;
            }
            $seen[$u] = true;
            $candidates[] = $u;
        };

        foreach ($fileNos as $fn) {
            $add($fn);
        }
        foreach ($transactions as $row) {
            foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                $add($row[$col] ?? null);
            }
        }

        if (empty($candidates)) {
            return null;
        }

        if (!Schema::connection('sqlsrv')->hasTable('master_dciv_links')) {
            return null;
        }

        $matches = DB::connection('sqlsrv')->table('master_dciv_links')
            ->where(function ($q) use ($candidates) {
                $q->whereIn(DB::raw('UPPER(LTRIM(RTRIM(dciv_file_number)))'), $candidates)
                    ->orWhereIn(DB::raw('UPPER(LTRIM(RTRIM(related_file_number)))'), $candidates);
            })
            ->orderByDesc('id')
            ->get(['dciv_file_number', 'related_file_number', 'dciv_reason']);

        if ($matches->isEmpty()) {
            return null;
        }

        // Prefer a match that actually carries a reason.
        $best = $matches->first(fn($m) => trim((string) ($m->dciv_reason ?? '')) !== '') ?? $matches->first();

        // Capture the searched-side file numbers that are actually under a DCIV,
        // so callers can flag only the matching timeline rows (not the whole group).
        $candidateSet = array_flip($candidates);
        $fileNumbers = [];
        foreach ($matches as $m) {
            foreach (['dciv_file_number', 'related_file_number'] as $col) {
                $u = strtoupper(trim((string) ($m->$col ?? '')));
                if ($u !== '' && isset($candidateSet[$u])) {
                    $fileNumbers[$u] = true;
                }
            }
        }

        return [
            'note' => 'Under Investigation',
            'reason' => trim((string) ($best->dciv_reason ?? '')) ?: null,
            'dciv_file_number' => trim((string) ($best->dciv_file_number ?? '')) ?: null,
            'file_numbers' => array_keys($fileNumbers),
        ];
    }

    /**
     * Build the "Under Investigation" comment text (note + reason).
     * This is used for the transaction rows' Comments column.
     */
    private function investigationCommentText(array $investigation): string
    {
        $text = $investigation['note'] ?? 'Under Investigation';
        $reason = trim((string) ($investigation['reason'] ?? ''));
        if ($reason !== '') {
            $text .= ' — ' . $reason;
        }
        return $text;
    }

    /**
     * Whether a result/report row's file number is one of the investigated files.
     *
     * @param array $row         Row carrying file-number variants
     * @param array $fileNumbers UPPERCASE investigated file numbers
     * @param array $columns     Which row keys hold file numbers
     */
    private function rowIsUnderInvestigation(array $row, array $fileNumbers, array $columns): bool
    {
        if (empty($fileNumbers)) {
            return false;
        }
        $set = array_flip($fileNumbers);
        foreach ($columns as $col) {
            $u = strtoupper(trim((string) ($row[$col] ?? '')));
            if ($u !== '' && isset($set[$u])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the "SEARCHED (LINKED)" file-number display string shared by the on-screen LS
     * Timeline, the Pay-Per-Search template and the printable report. The SEARCHED number
     * always leads, with its counterpart parenthesised:
     *   - Searched by the land/MLS file number (e.g. CON-AG-2014-35) → "CON-AG-2014-35 (MLKN 2455)"
     *   - Searched by the KANGIS file number (e.g. MLKN 2455)       → "MLKN 2455 (CON-AG-2014-35)"
     * When no counterpart is on record the searched number is shown alone. Presentation
     * only — the underlying stored values are never touched.
     *
     * @param array $transactions Normalized timeline rows (as produced by search()/buildPrintReport()).
     * @param string|null $indexedRelatedFileno Raw file_indexings.related_fileno CSV, if already loaded.
     * @param string|null $firstRowKangisFileNo kangisFileNo column off the primary transaction row.
     */
    private function resolveFileNumberDisplay(
        $conn,
        string $searchedFileNo,
        string $resolvedFileNo,
        array $transactions,
        ?string $indexedRelatedFileno = null,
        ?string $firstRowKangisFileNo = null
    ): string {
        // KANGIS-format detection: legacy KNML/MLKN/KNGP + digits AND new-KANGIS "KN"+digits.
        // Delegates to identifyFileNumberType so a "KN3754" alias is recognised too (the old
        // local regex only matched the 4-letter legacy form).
        $isKangis = fn ($value): bool => $this->isKangisFormat($value);

        $kangisNumber = trim((string) $firstRowKangisFileNo);
        if ($kangisNumber === '' || $kangisNumber === '-') {
            $kangisNumber = null;
        }

        // NOTE: file_indexings.related_fileno is a general lineage field (subdivision siblings,
        // merger sources, KANGIS recerts — all mixed together), so it is deliberately NOT used
        // here to seed $relatedMls: this display is exclusively for the KANGIS <-> MLS pairing
        // (see docblock above), and a land-format token from related_fileno (e.g. a Subdivision
        // predecessor like "RES-1999-469") is never a valid KANGIS counterpart. Only the
        // KANGIS-typed row below, or resolveKangisCanonical() further down, may populate it.
        $relatedMls = null;

        // Resolve the KANGIS Recertification counterpart from the timeline itself, regardless
        // of which number format was searched. The recert row's own file number is always
        // pinned to the KANGIS-format endpoint (see fetchRelatedRecertificationRows), while its
        // 'parent_file_number' carries the linked MLS/land-format endpoint, so either search
        // direction can be resolved from it.
        if ($relatedMls === null || $relatedMls === '') {
            foreach ($transactions as $tx) {
                $src = trim((string) ($tx['source_table'] ?? ''));
                $txType = trim((string) ($tx['transaction_type'] ?? ''));
                if ($src !== 'Related Fileno' || stripos($txType, 'KANGIS') === false) {
                    continue;
                }

                $ownNo = trim((string) ($tx['file_number'] ?? ($tx['fileno'] ?? ($tx['mlsFNo'] ?? ''))));
                $otherNo = trim((string) ($tx['parent_file_number'] ?? ''));

                if ($isKangis($searchedFileNo)) {
                    $candidate = ($otherNo !== '' && !$isKangis($otherNo)) ? $otherNo
                        : (($ownNo !== '' && !$isKangis($ownNo)) ? $ownNo : null);
                } else {
                    $candidate = ($ownNo !== '' && $isKangis($ownNo)) ? $ownNo
                        : (($otherNo !== '' && $isKangis($otherNo)) ? $otherNo : null);
                }

                if ($candidate) {
                    $relatedMls = $candidate;
                    break;
                }
            }
        }

        // Last-resort fallback for a KANGIS-format search with no recert row on the timeline
        // (e.g. the link is only recorded in PropID_Master).
        if (($relatedMls === null || $relatedMls === '') && $isKangis($searchedFileNo)) {
            $relatedMls = $this->resolveKangisCanonical($conn, $searchedFileNo);
        }

        // Land-format search: the KANGIS alias is NOT on the searched file's own rows and
        // no "KANGIS Recertification" timeline row exists to seed $relatedMls. Resolve it
        // authoritatively (file_indexings related_fileno back-link, own rows, recert links)
        // so e.g. "RES-1991-772" displays "RES-1991-772 (KNML 9213)". Fail-open: null → land-only.
        if (($relatedMls === null || $relatedMls === '') && $kangisNumber === null
            && $searchedFileNo !== '' && !$isKangis($searchedFileNo)) {
            $kangisNumber = $this->resolveKangisAliasForLandFile($conn, $resolvedFileNo, $transactions);
        }

        $display = $resolvedFileNo;

        // When the user searched the temporary "(T)" number, lead the display with the number
        // they actually searched — the resolved/indexed number is the base permanent form
        // (e.g. searching "RES-2003-537(T)" resolves to "RES-2003-537"), but File Information
        // should reflect the searched temp file.
        $strip = fn($v) => trim((string) preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', (string) $v));
        if (preg_match('/\(\s*T\s*\)\s*$/i', $searchedFileNo)
            && strcasecmp($strip($searchedFileNo), $strip($resolvedFileNo)) === 0) {
            $display = trim($searchedFileNo);
        }

        if ($isKangis($searchedFileNo)) {
            // Searched by the KANGIS alias (e.g. "MLKN 3725") — it leads the display, with its
            // land/MLS counterpart parenthesised: "MLKN 3725 (CON-IND-2021-18)". $display holds
            // the resolved land number here, so the land number is the counterpart (either the
            // recert-derived $relatedMls or the resolved $resolvedFileNo — same value); using it
            // as the counterpart rather than the lead is what avoids the old
            // "CON-IND-2021-18 (CON-IND-2021-18)" duplicate.
            $landNo = ($relatedMls && !$isKangis($relatedMls))
                ? $relatedMls
                : (!$isKangis($resolvedFileNo) ? $resolvedFileNo : null);
            $display = trim($searchedFileNo);
            if ($landNo && strcasecmp(trim($landNo), trim($searchedFileNo)) !== 0) {
                $display .= ' (' . $landNo . ')';
            }
        } elseif ($searchedFileNo === '') {
            if ($kangisNumber) {
                $display = $kangisNumber;
                if ($relatedMls && strcasecmp(trim($relatedMls), trim($kangisNumber)) !== 0) {
                    $display .= ' (' . $relatedMls . ')';
                }
            } elseif ($relatedMls && $isKangis($resolvedFileNo)) {
                $display = $resolvedFileNo . ' (' . $relatedMls . ')';
            }
        } else {
            // Never append a parenthetical equal to the lead itself — guards against
            // "MLKN 139 (MLKN 139)" when an upstream canonicalization has already resolved
            // the display lead ($resolvedFileNo) to the same KANGIS number.
            $lead = trim($display);
            if ($relatedMls && strcasecmp(trim($relatedMls), trim($searchedFileNo)) !== 0
                && strcasecmp(trim($relatedMls), $lead) !== 0) {
                $display .= ' (' . $relatedMls . ')';
            } elseif ($kangisNumber && strcasecmp(trim($kangisNumber), trim($searchedFileNo)) !== 0
                && strcasecmp(trim($kangisNumber), $lead) !== 0) {
                $display .= ' (' . $kangisNumber . ')';
            }
        }

        return $display;
    }

    /**
     * Resolve a KANGIS legacy number (e.g. "MLKN 2455", "KNML 7444", "KNGP 12") to its
     * mother MLS file number, so a search by the KANGIS alias behaves exactly like a search
     * by the MLS number. Returns null for anything that is not a confidently-mapped KANGIS
     * input, so the caller can safely keep the original value (fail-open).
     *
     * Mapping sources, in order:
     *   1. PropID_Master.kangisFileNo → the mother row's mlsFNo / primary_file_number
     *      (populated when a recertified file has been data-corrected onto the mother parcel).
     *   2. related_file_number 'KANGIS Recertification' links → the MLS-formatted endpoint
     *      (covers files not yet data-corrected).
     * Leading zeros in the numeric part are ignored ("MLKN 02455" == "MLKN 2455").
     */
    private function resolveKangisCanonical($conn, string $fileNo): ?string
    {
        $fileNo = trim($fileNo);
        // Only attempt for KANGIS numbers; normal MLS searches short-circuit here.
        // Accepts BOTH generations: legacy MLKN/KNML/KNGP (optionally unit-suffixed, e.g.
        // "MLKN 2280-1") and new-KANGIS "KN…". The new-KANGIS form was previously absent,
        // so every Second-Recertification file failed to resolve to its land file.
        if ($fileNo === '' || !preg_match('/^((MLKN|KNML|KNGP)\s?\d{1,6}([-_]\d{1,3})?|KN[\s-]?\d{2,6})$/i', $fileNo)) {
            return null;
        }

        $key = strtoupper(preg_replace('/\s+/', '', $fileNo));                 // "MLKN2455"
        $keyNoZero = preg_replace('/^([A-Z]+)0*(\d+)$/', '$1$2', $key);         // strip zero-pad
        // Must recognise BOTH generations, otherwise $pickMls below can hand back a new-KANGIS
        // number ("KN2690") as though it were the land file's MLS number.
        $isKangis = fn ($v) => (bool) preg_match('/^((MLKN|KNML|KNGP)\s?\d|KN[\s-]?\d)/i', trim((string) $v));

        $pickMls = function ($candidates) use ($isKangis, $fileNo): ?string {
            foreach ($candidates as $cand) {
                $cand = trim((string) $cand);
                if ($cand !== '' && !$isKangis($cand) && strcasecmp($cand, $fileNo) !== 0) {
                    return $cand;
                }
            }
            return null;
        };

        // 1) PropID_Master — mother row carrying this KANGIS alias. Both alias columns must be
        //    searched: the columns are routinely mis-slotted (e.g. prop_id 147163 stores the MLS
        //    number in kangisFileNo and the OLD KANGIS number "MLKN 3673" in NewKANGISFileno), so
        //    matching kangisFileNo alone silently fails to canonicalise.
        try {
            $pm = $conn->table('PropID_Master')
                ->where(function ($q) use ($key, $keyNoZero) {
                    $q->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(kangisFileNo,''))),' ','')) IN (?, ?)", [$key, $keyNoZero])
                      ->orWhereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(NewKANGISFileno,''))),' ','')) IN (?, ?)", [$key, $keyNoZero]);
                })
                ->first(['mlsFNo', 'primary_file_number']);
            if ($pm) {
                $mls = $pickMls([$pm->mlsFNo ?? null, $pm->primary_file_number ?? null]);
                if ($mls !== null) {
                    return $mls;
                }
            }
        } catch (\Throwable $e) { /* fail-open */ }

        // 2) related_file_number — KANGIS Recertification link (either endpoint).
        try {
            $links = $conn->table('related_file_number')
                ->where('transaction_type', 'like', '%Recertification%')
                ->where(function ($q) use ($key, $keyNoZero) {
                    $q->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(file_number,''))),' ','')) IN (?, ?)", [$key, $keyNoZero])
                      ->orWhereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(related_fileno,''))),' ','')) IN (?, ?)", [$key, $keyNoZero]);
                })
                ->get(['file_number', 'related_fileno']);
            foreach ($links as $l) {
                $mls = $pickMls([$l->file_number ?? null, $l->related_fileno ?? null]);
                if ($mls !== null) {
                    return $mls;
                }
            }
        } catch (\Throwable $e) { /* fail-open */ }

        // 3) parent_prop_id walk — the canonical Option A shape: Land / Old KANGIS / New KANGIS
        //    each hold their OWN prop_id and point UPWARD via parent_prop_id. A new-KANGIS file
        //    created that way (e.g. KN2690 -> parent 147163) has no related_fileno of its own and
        //    no recertification link, so paths 1 and 2 both miss it. Resolve the parent prop_id to
        //    its land file number instead. Restricted to a NON-KANGIS parent number so an alias
        //    can never canonicalise to another alias.
        try {
            $ownRow = $conn->table('file_indexings')
                ->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(file_number,''))),' ','')) IN (?, ?)", [$key, $keyNoZero])
                ->whereNull('deleted_at')
                ->whereNotNull('parent_prop_id')
                ->first(['prop_id', 'parent_prop_id']);

            if ($ownRow && trim((string) $ownRow->parent_prop_id) !== '') {
                foreach (array_filter(array_map('trim', explode(',', (string) $ownRow->parent_prop_id))) as $parentPid) {
                    // Never resolve to the file's own parcel — that is a self-reference, not a parent.
                    if ($parentPid === trim((string) $ownRow->prop_id)) {
                        continue;
                    }

                    $pm = $conn->table('PropID_Master')
                        ->where('prop_id', $parentPid)
                        ->first(['mlsFNo', 'primary_file_number']);
                    $mls = $pm ? $pickMls([$pm->mlsFNo ?? null, $pm->primary_file_number ?? null]) : null;

                    if ($mls === null) {
                        // Fall back to the parent parcel's own LAND-registry indexing row.
                        $parentFile = $conn->table('file_indexings')
                            ->where('prop_id', $parentPid)
                            ->whereNull('deleted_at')
                            ->where('registry', '<>', 'KANGIS')
                            ->value('file_number');
                        $mls = $pickMls([$parentFile]);
                    }

                    if ($mls !== null) {
                        return $mls;
                    }
                }
            }
        } catch (\Throwable $e) { /* fail-open */ }

        return null;
    }

    /**
     * Resolve the KANGIS alias to DISPLAY alongside a LAND-format searched file, from the
     * authoritative sources (per project decision): the KANGIS file's own file_indexings
     * row that back-links to this land file via related_fileno; the KANGIS / New-KANGIS
     * columns carried on the file's own transaction rows; and related_file_number
     * "KANGIS Recertification" links. PropID_Master is deliberately NOT used as a value
     * source here — its kangisFileNo is not reliably a KANGIS number (it can hold an MLS
     * successor, e.g. RES-1991-772 -> "RES-2021-4444").
     *
     * A KANGIS file is stored in its OWN file_indexings row (registry='KANGIS') whose
     * related_fileno JSON lists the land file(s) it was recertified from — e.g.
     * KNML 9213 -> ["RES-2021-4444","RES-1991-772","KN3754"]. A search by the land file
     * must therefore reverse-look that row up. Prefers a legacy KANGIS number
     * (KNML/MLKN/KNGP) and falls back to a new-KANGIS ("KN…") number. Returns null when
     * nothing confidently maps (fail-open: the display simply stays land-only).
     *
     * @param  array  $transactions  the assembled timeline rows for the searched file
     */
    private function resolveKangisAliasForLandFile($conn, string $landFileNo, array $transactions): ?string
    {
        $landFileNo = trim($landFileNo);
        if ($landFileNo === '' || $this->isKangisFormat($landFileNo)) {
            return null;
        }

        $variants = $this->fileNumberVariants($landFileNo);
        $legacy = null;
        $newk = null;
        $take = function ($value) use (&$legacy, &$newk): void {
            $v = trim((string) $value);
            if ($v === '' || $v === '-') {
                return;
            }
            $t = $this->identifyFileNumberType($v);
            if ($t === 'kangis' && $legacy === null) {
                $legacy = $v;
            } elseif ($t === 'new_kangis' && $newk === null) {
                $newk = $v;
            }
        };

        // 1) file_indexings reverse lookup — the KANGIS row that lists this land file in
        //    its related_fileno JSON. Match the QUOTED token so "RES-1991-772" cannot
        //    partial-match "RES-1991-7720".
        try {
            $rows = $conn->table('file_indexings')
                ->where(function ($q) use ($variants) {
                    foreach ($variants as $v) {
                        $q->orWhere('related_fileno', 'like', '%"' . $v . '"%');
                    }
                })
                ->whereNull('deleted_at')
                ->get(['file_number', 'kangis_fileno_resolved', 'kangis_file_no', 'new_kangis_file_no']);
            foreach ($rows as $r) {
                $take($r->kangis_fileno_resolved ?? null);
                $take($r->kangis_file_no ?? null);
                $take($r->file_number ?? null);
                $take($r->new_kangis_file_no ?? null);
            }
        } catch (\Throwable $e) { /* fail-open */ }

        // 2) The searched file's own transaction rows may carry the KANGIS number directly.
        if ($legacy === null && $newk === null) {
            foreach ($transactions as $tx) {
                $take($tx['kangisFileNo'] ?? null);
                $take($tx['NewKANGISFileno'] ?? null);
            }
        }

        // 3) related_file_number "KANGIS Recertification" link — either endpoint.
        if ($legacy === null && $newk === null) {
            try {
                $links = $conn->table('related_file_number')
                    ->where('transaction_type', 'like', '%Recertification%')
                    ->where(function ($q) use ($variants) {
                        foreach ($variants as $v) {
                            $q->orWhere('file_number', $v)->orWhere('related_fileno', $v);
                        }
                    })
                    ->get(['file_number', 'related_fileno']);
                foreach ($links as $l) {
                    $take($l->file_number ?? null);
                    $take($l->related_fileno ?? null);
                }
            } catch (\Throwable $e) { /* fail-open */ }
        }

        return $legacy ?? $newk;
    }

    /**
     * Auto-detect the land use from a file number prefix when it is not otherwise recorded
     * (e.g. a legacy file with no file_indexings row). Follows the KLAES land-use prefix mapping:
     * handles direct (RES/COM/IND/AG), conversion (CON-RES ...), and recertification (RES-RC ...,
     * CON-RES-RC ...) forms — the meaningful token is the land-use code, which this extracts by
     * scanning the '-'/'_'-separated tokens. Returns null when no known code is present.
     */
    private function detectLandUseFromFileNumber(?string $fileNo): ?string
    {
        $fileNo = strtoupper(trim((string) $fileNo));
        if ($fileNo === '') {
            return null;
        }

        $map = [
            'RES' => 'Residential',
            'COM' => 'Commercial',
            'IND' => 'Industrial',
            'AG'  => 'Agriculture',
            'AGR' => 'Agriculture',
        ];

        foreach (preg_split('/[^A-Z]+/', str_replace(['_', '\\', '/'], '-', $fileNo)) as $token) {
            if ($token !== '' && isset($map[$token])) {
                return $map[$token];
            }
        }

        return null;
    }

    /**
     * Split a comma-separated parent_prop_id value into a clean list of prop_id strings.
     *
     * @return string[]
     */
    private function splitPropIds($raw): array
    {
        $out = [];
        foreach (explode(',', (string) $raw) as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * Resolve the ancestor (mother / grandmother) prop_id(s) for a searched file by walking the
     * parent_prop_id lineage strictly UPWARD. A subdivision / change-of-purpose CHILD stores its
     * mother's prop_id in parent_prop_id (confirmed: CON-COM-2026-430 -> parent_prop_id 7530, which
     * is the mother CON-AG-2014-35's own prop_id). Expanding those prop_ids surfaces the mother's
     * foundational transactions (Right of Occupancy, Deed of Mortgage, Certificate of Occupancy) in
     * a child search. parent_prop_id never points at siblings, so this cannot re-introduce sibling
     * contamination. Bounded depth; fully fail-open.
     *
     * @param  array  $ownRecords  the searched file's already-fetched transaction rows
     * @return string[] distinct ancestor prop_ids (excludes the searched file's own prop_ids)
     */
    private function resolveAncestorPropIds($conn, string $fileNo, array $ownRecords): array
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return [];
        }

        // Rows that belong to the searched file itself — used both to seed parent_prop_ids and to
        // record the file's OWN prop_ids so we never mistake them for ancestors.
        $ownPropIds = [];
        $seed = [];
        $matchesSelf = function (array $row) use ($fileNo): bool {
            foreach (['fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                $v = trim((string) ($row[$col] ?? ''));
                if ($v !== '' && strcasecmp($v, $fileNo) === 0) {
                    return true;
                }
            }
            return false;
        };
        foreach ($ownRecords as $row) {
            if (!$matchesSelf($row)) {
                continue;
            }
            $pid = trim((string) ($row['prop_id'] ?? ''));
            if ($pid !== '') {
                $ownPropIds[$pid] = true;
            }
            foreach ($this->splitPropIds($row['parent_prop_id'] ?? '') as $p) {
                $seed[$p] = true;
            }
        }

        // Seed from the authoritative index sources too, so this works even in SME mode (where the
        // main prop_id expansion — and the active-index lookup — are skipped) and even when the
        // searched file has no transaction rows of its own yet.
        try {
            $fi = $conn->table('file_indexings')
                ->where('file_number', $fileNo)
                ->whereNull('deleted_at')
                ->first(['prop_id', 'parent_prop_id']);
            if ($fi) {
                if (!empty($fi->prop_id)) {
                    $ownPropIds[trim((string) $fi->prop_id)] = true;
                }
                foreach ($this->splitPropIds($fi->parent_prop_id ?? '') as $p) {
                    $seed[$p] = true;
                }
            }
            $fn = $conn->table('fileNumber')->where('mlsfNo', $fileNo)->first(['parent_prop_id']);
            if ($fn) {
                foreach ($this->splitPropIds($fn->parent_prop_id ?? '') as $p) {
                    $seed[$p] = true;
                }
            }
        } catch (\Throwable $e) {
            // ignore — fall back to whatever the records carry
        }

        // Walk upward: for each ancestor prop_id, look up the row that OWNS it and fold in ITS
        // parent_prop_id, so multi-generation lineage (mother -> grandmother) is captured too.
        $ancestors = [];
        $queue = array_keys($seed);
        $depth = 0;
        while (!empty($queue) && $depth < 6) {
            $next = [];
            foreach ($queue as $pid) {
                $pid = trim((string) $pid);
                if ($pid === '' || isset($ownPropIds[$pid]) || isset($ancestors[$pid])) {
                    continue;
                }
                $ancestors[$pid] = true;
                try {
                    $parent = $conn->table('file_indexings')
                        ->where('prop_id', $pid)
                        ->whereNull('deleted_at')
                        ->value('parent_prop_id');
                    foreach ($this->splitPropIds($parent) as $p) {
                        if (!isset($ancestors[$p]) && !isset($ownPropIds[$p])) {
                            $next[] = $p;
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore this hop
                }
            }
            $queue = $next;
            $depth++;
        }

        // Return as explicit strings so PDO binds them as NVARCHAR (see collectPropIds()).
        return array_values(array_map('strval', array_keys($ancestors)));
    }

    /**
     * Retrieve explicitly related file numbers for Subdivision, Merger, and Extension (SME)
     * using the related_fileno identifier array from file_indexings.
     * Bypasses ST (Sectional Titling) files completely.
     */
    public function getSmeAllowedFileNos(string $fileNo, $conn): array
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return [];
        }

        // ST is a separate module; ignore ST prefix
        if (str_starts_with(strtoupper($fileNo), 'ST-')) {
            return [];
        }

        $normalizeForQuery = function (string $value): string {
            $value = strtoupper(trim($value));
            $value = preg_replace('/[\/=_]+/', '-', $value);
            $value = preg_replace('/\s+/', '', $value);
            return $value;
        };

        $normalizedFileNo = $normalizeForQuery($fileNo);
        if ($normalizedFileNo === '') {
            return [];
        }

        $allowed = [$fileNo];
        $isSme = false;

        $parseRelatedFilenos = function (?string $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return [];
            }

            $trimmed = trim($raw, "[]\n\r\t\0\x0B ");
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && !empty($decoded)) {
                return array_values(array_filter(array_map(function ($item) {
                    return trim((string) $item);
                }, $decoded)));
            }

            $parts = preg_split('/[,;|]+/', $trimmed);
            $parts = array_values(array_filter(array_map(function ($part) {
                return trim(trim((string) $part), "'\" ");
            }, $parts)));

            if (!empty($parts)) {
                return $parts;
            }

            return [trim($trimmed, "'\" ")];
        };

        // 1. Check active indexing
        $active = $conn->table('file_indexings')
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(file_number, ''))), '/', '-'), '=', '-'), '_', '-')) = ?", [$normalizedFileNo])
            ->whereNull('deleted_at')
            ->first(['related_fileno']);

        if ($active && !empty($active->related_fileno)) {
            $decoded = $parseRelatedFilenos($active->related_fileno);
            if (!empty($decoded)) {
                $isSme = true;
                foreach ($decoded as $fn) {
                    if ($fn !== '') {
                        $allowed[] = trim($fn);
                    }
                }
            }
        }

        // 2. If the file is a child or decommissioned parent of an SME group,
        //    find the active parent record and include its related file numbers.
        if (!$isSme) {
            // Rule 9 (bidirectional): match the file number as a WHOLE JSON token — the related_fileno
            // column stores a JSON array (e.g. ["RES-1991-772"]), so bound the match with quotes to
            // avoid the substring false positive where "CON-AG-2014-3" matched "CON-AG-2014-35". A file
            // may be referenced by SEVERAL parents, so union them all rather than taking only the first.
            $activeParents = $conn->table('file_indexings')
                ->whereNull('deleted_at')
                ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(related_fileno, ''))), '/', '-'), '=', '-'), '_', '-')) LIKE ?", ['%"' . $normalizedFileNo . '"%'])
                ->get(['file_number', 'related_fileno']);

            foreach ($activeParents as $activeParent) {
                if (str_starts_with(strtoupper((string) $activeParent->file_number), 'ST-')) {
                    continue;
                }
                $decoded = $parseRelatedFilenos($activeParent->related_fileno);
                if (!empty($decoded)) {
                    $isSme = true;
                    $allowed[] = trim((string) $activeParent->file_number);
                    foreach ($decoded as $fn) {
                        if ($fn !== '') {
                            $allowed[] = trim($fn);
                        }
                    }
                }
            }
        }

        // Lineage completion. The related_fileno strings that flip on SME mode are frequently
        // fragmented — a subdivided mother may have no active indexing row, and each child often
        // references only one sibling. The authoritative family link lives on pra.parent_prop_id.
        // When SME mode is active, widen the allowed set to (a) the searched file's own-prop_id
        // aliases (e.g. a Change-of-Purpose rename that inherits the prop_id) and (b) its direct
        // children (rows whose parent_prop_id references the searched file's prop_id), so the full
        // subdivision/rename family is fetched instead of only the string-reachable fragment.
        // Only runs when SME mode is already active; non-SME searches use prop_id expansion instead.
        if ($isSme) {
            try {
                $ownPropIds = $conn->table('pra')
                    ->where(function ($q) use ($normalizedFileNo) {
                        $q->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(mlsFNo, ''))), '/', '-'), '=', '-'), '_', '-')) = ?", [$normalizedFileNo])
                          ->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(fileno, ''))), '/', '-'), '=', '-'), '_', '-')) = ?", [$normalizedFileNo]);
                    })
                    ->whereNotNull('prop_id')
                    ->pluck('prop_id')
                    ->map(fn ($v) => trim((string) $v))
                    ->filter(fn ($v) => $v !== '')
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($ownPropIds)) {
                    $family = $conn->table('pra')
                        ->where(function ($q) use ($ownPropIds) {
                            $q->whereIn('prop_id', $ownPropIds); // same-prop_id aliases (rename)
                            foreach ($ownPropIds as $pid) {
                                // parent_prop_id may be a CSV of prop_ids; match as a whole token.
                                $q->orWhereRaw("',' + REPLACE(LTRIM(RTRIM(ISNULL(parent_prop_id, ''))), ' ', '') + ',' LIKE ?", ['%,' . $pid . ',%']);
                            }
                        })
                        ->get(['mlsFNo', 'fileno']);

                    foreach ($family as $row) {
                        foreach (['mlsFNo', 'fileno'] as $col) {
                            $fn = trim((string) ($row->$col ?? ''));
                            if ($fn !== '') {
                                $allowed[] = $fn;
                            }
                        }
                    }

                    // Same-prop_id aliases in the OTHER staging tables. A KANGIS-legacy
                    // Certificate of Occupancy (or file-history / deed row) is frequently keyed
                    // under the KANGIS number (e.g. "MLKN 2455") even though the file's pra
                    // history was renamed to its MLS number (e.g. "CON-AG-2014-35"). Those rows
                    // share the mother's own prop_id but a file number that appears NOWHERE in
                    // pra or related_fileno, so SME mode — which matches by file number and skips
                    // prop_id expansion — would never surface them. Pull their file numbers into
                    // the allowed set so the COFO/history reappears once the file is subdivided.
                    $aliasTables = [
                        'CofO_staging'         => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
                        'file_history_staging' => ['mlsFNo', 'fileno', 'kangisFileNo', 'NewKANGISFileno'],
                        'deed_registrations'   => ['fileno'],
                    ];
                    foreach ($aliasTables as $aliasTable => $aliasCols) {
                        try {
                            $aliasRows = $conn->table($aliasTable)
                                ->whereIn('prop_id', $ownPropIds)
                                ->get($aliasCols);
                            foreach ($aliasRows as $aliasRow) {
                                foreach ($aliasCols as $col) {
                                    $fn = trim((string) ($aliasRow->$col ?? ''));
                                    if ($fn !== '') {
                                        $allowed[] = $fn;
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            // Non-fatal: a missing table/column must not break the allowed set.
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal: fall back to the related_fileno-derived allowed set.
            }
        }

        if ($isSme) {
            return array_values(array_unique($allowed));
        }

        return [];
    }

    /**
     * Normalize a file number so it can be used as a lifecycle-group key.
     * Uppercases, trims, collapses whitespace and normalises the "(T)" temp suffix.
     */
    private function normalizeLifecycleFileNo(?string $fileNo): string
    {
        $fileNo = trim((string) $fileNo);
        if ($fileNo === '') {
            return '';
        }
        $fileNo = strtoupper($fileNo);
        $fileNo = preg_replace('/\s+/', ' ', $fileNo);
        $fileNo = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '(T)', $fileNo);
        return $fileNo;
    }

    /**
     * Extract the lifecycle-owner file number from a search or print row.
     * Prefers the explicit lifecycle_file_no when present, then the canonical
     * file_no used by print rows, then the various file-number columns.
     */
    private function extractLifecycleFileNo(array $row): ?string
    {
        if (!empty($row['lifecycle_file_no'])) {
            return $this->normalizeLifecycleFileNo($row['lifecycle_file_no']);
        }

        $candidates = [
            $row['file_no'] ?? null,
            $row['fileno'] ?? null,
            $row['file_number'] ?? null,
            $row['mlsFNo'] ?? null,
            $row['kangisFileNo'] ?? null,
            $row['NewKANGISFileno'] ?? null,
        ];

        foreach ($candidates as $c) {
            $c = trim((string) $c);
            if ($c !== '' && $c !== '-') {
                return $this->normalizeLifecycleFileNo($c);
            }
        }

        return null;
    }

    /**
     * Stamp every row with a normalized lifecycle_file_no.
     *
     * KANGIS Recertification rows (and any CofO rows tied to a KANGIS alias) are
     * assigned to the MAIN MLS/ST file's lifecycle, because the recertification
     * event conceptually belongs to that main file — not to the KANGIS alias.
     */
    /**
     * Parse the two numbers inside a "MAIN (ALIAS)" display string (e.g.
     * "CON-COM-2023-197 (KNML 6992)") into a [normalizedKangis => normalizedMain]
     * pair, order-independent. Returns [] when it isn't a KANGIS/land pairing.
     */
    private function aliasHintsFromDisplay(?string $display): array
    {
        $s = trim((string) $display);
        if ($s === '') {
            return [];
        }
        $parts = [$s];
        if (preg_match('/^(.+?)\s*\(\s*([^()]+?)\s*\)\s*$/', $s, $m)) {
            $parts = [trim($m[1]), trim($m[2])];
        }
        $kangisSide = null;
        $mainSide = null;
        foreach ($parts as $p) {
            if ($p === '' || $p === '-') {
                continue;
            }
            if ($this->isKangisFormat($p)) {
                $kangisSide = $p;
            } else {
                $mainSide = $p;
            }
        }
        if ($kangisSide === null || $mainSide === null) {
            return [];
        }
        $k = $this->normalizeLifecycleFileNo($kangisSide);
        $mn = $this->normalizeLifecycleFileNo($mainSide);
        return ($k !== '' && $mn !== '' && $k !== $mn) ? [$k => $mn] : [];
    }

    /**
     * Resolve display-derived alias hints to their TRUE owning land file before they are locked.
     *
     * The "MAIN (KANGIS)" display can now surface an ANCESTOR's KANGIS number on a child search
     * (e.g. searching CON-COM-2026-431 displays "(MLKN 2455)" although MLKN 2455 belongs to the
     * grandmother CON-AG-2014-35). Blindly locking {MLKN 2455 => searched child} would fold the
     * KANGIS rows into the wrong block. Rule, per hint {kangis => searchedMain}:
     *   1. some row pairs the alias WITH the searched main  -> keep the searched main (this is the
     *      original R1 lock — protects the KNML 6992 multi-recert case);
     *   2. otherwise rows pair the alias with ANOTHER land file (recert row preferred) -> repoint
     *      the hint to that owner (the parent);
     *   3. no row pairs the alias at all -> keep the display hint (reverse-lookup-only aliases).
     */
    private function resolveAliasHintOwners(array $rows, array $hints): array
    {
        if (empty($hints)) {
            return $hints;
        }
        foreach ($hints as $kangisKey => $searchedMain) {
            $pairedWithSearched = false;
            $recertOwner = null;
            $anyOwner = null;
            foreach ($rows as $row) {
                if ($this->extractKangisLifecycleKey($row) !== $kangisKey) {
                    continue;
                }
                $main = $this->extractMainEndpoint($row);
                if ($main === '') {
                    continue;
                }
                $normMain = $this->normalizeLifecycleFileNo($main);
                if ($normMain === '' || $normMain === $kangisKey) {
                    continue;
                }
                if ($normMain === $searchedMain) {
                    $pairedWithSearched = true;
                    break;
                }
                if ($this->isKangisRecertificationRow($row)) {
                    $recertOwner = $recertOwner ?? $normMain;
                } else {
                    $anyOwner = $anyOwner ?? $normMain;
                }
            }
            if (!$pairedWithSearched) {
                $owner = $recertOwner ?? $anyOwner;
                if ($owner !== null) {
                    $hints[$kangisKey] = $owner;
                }
            }
        }
        return $hints;
    }

    private function tagRowsWithLifecycleFileNo(array $rows, array $aliasHints = [], string $primaryFileNo = ''): array
    {
        $normPrimary = $primaryFileNo !== '' ? $this->normalizeLifecycleFileNo($primaryFileNo) : '';
        // Build a KANGIS-alias -> main-file map. Recertification link rows are the
        // strongest signal, but any row that carries both a KANGIS number and a main
        // land number pairs them too (e.g. a KANGIS C of O row that also holds the
        // land file number), so KANGIS rows roll into their owning file's lifecycle.
        // Caller-supplied hints (e.g. from the "MAIN (KANGIS)" display) seed the map
        // for the searched file whose KANGIS rows may not carry the land number.
        $kangisToMain = $aliasHints;
        // Caller hints (the searched file's own "MAIN (KANGIS)" pairing) are
        // authoritative and locked: a KANGIS number can be recertified against several
        // files, so a stray recert row must never repoint the searched alias away from
        // the file the user actually searched.
        $lockedKeys = array_fill_keys(array_keys($aliasHints), true);
        foreach ($rows as $row) {
            $kangis = $this->extractKangisEndpoint($row);
            $main = $this->extractMainEndpoint($row);
            if ($kangis === '' || $main === '') {
                continue;
            }
            $normKangis = $this->normalizeLifecycleFileNo($kangis);
            $normMain = $this->normalizeLifecycleFileNo($main);
            if ($normKangis === $normMain || isset($lockedKeys[$normKangis])) {
                continue;
            }
            // Recertification rows may overwrite a weaker pairing; otherwise keep the
            // first pairing seen for a given KANGIS alias.
            if ($this->isKangisRecertificationRow($row) || !isset($kangisToMain[$normKangis])) {
                $kangisToMain[$normKangis] = $normMain;
            }
        }

        // Anchor each KANGIS key to the lifecycle owner of its CofO row. Recertification
        // rows for that same key must inherit this owner so they never split groups.
        $cofoLifecycleByKangis = [];
        foreach ($rows as $row) {
            $type = strtolower((string) ($row['transaction_type'] ?? ($row['instrument_type'] ?? '')));
            if (!str_contains($type, 'certificate of occupanc')) {
                continue;
            }

            $kangisKey = $this->extractKangisLifecycleKey($row);
            if ($kangisKey === '') {
                continue;
            }

            $owner = $this->extractLifecycleFileNo($row);
            if ($owner === null || $owner === '') {
                $main = $this->extractMainEndpoint($row);
                $owner = $main !== '' ? $this->normalizeLifecycleFileNo($main) : '';
            }

            if ($owner !== '' && isset($kangisToMain[$owner])) {
                $owner = $kangisToMain[$owner];
            }

            if ($owner !== '' && !isset($cofoLifecycleByKangis[$kangisKey])) {
                $cofoLifecycleByKangis[$kangisKey] = $owner;
            }
        }

        foreach ($rows as $i => $row) {
            $lifecycle = $this->extractLifecycleFileNo($row);
            $kangisKey = $this->extractKangisLifecycleKey($row);

            if ($kangisKey !== '' && isset($kangisToMain[$kangisKey])) {
                // The row is keyed by a KANGIS alias with a known owning land file
                // (hint-locked or recert-derived) — every KANGIS-keyed row for that
                // alias (C of O, Recertification, deed history) rolls into that file.
                $lifecycle = $kangisToMain[$kangisKey];
            } elseif ($this->isKangisRecertificationRow($row)) {
                // No mapped owner: anchor to the alias's C of O row when present,
                // otherwise fall back to the recert's own linked main file.
                if ($kangisKey !== '' && isset($cofoLifecycleByKangis[$kangisKey])) {
                    $lifecycle = $cofoLifecycleByKangis[$kangisKey];
                } else {
                    $main = $this->extractMainEndpoint($row);
                    if ($main !== '') {
                        $lifecycle = $this->normalizeLifecycleFileNo($main);
                    }
                }
            }
            if ($lifecycle !== '' && $this->isSystemTempFileNo($lifecycle) && $normPrimary !== '') {
                // System temporary files have no independent lifecycle and their numbers are
                // hidden in the UI; roll them into the primary searched file's group.
                $lifecycle = $normPrimary;
            }

            $rows[$i]['lifecycle_file_no'] = $lifecycle;
        }
        return $rows;
    }

    private function isKangisRecertificationRow(array $row): bool
    {
        $source = (string) ($row['source_table'] ?? '');
        $type = (string) ($row['transaction_type'] ?? '');
        if ($source !== 'Related Fileno') {
            return false;
        }
        return stripos($type, 'KANGIS') !== false && stripos($type, 'Recertification') !== false;
    }

    private function isKangisFormat(?string $fileNo): bool
    {
        $type = $this->identifyFileNumberType($fileNo);
        return $type === 'kangis' || $type === 'new_kangis';
    }

    private function isOldMlsKnFileNo(?string $v): bool
    {
        $v = strtoupper(trim((string) $v));
        return (bool) preg_match('/^KN[- ]\d+/i', $v);
    }

    private function resolveKnFileNoForLandFile(string $fileNo): ?string
    {
        $variants = $this->fileNumberVariants($fileNo);
        if (empty($variants)) {
            return null;
        }

        // 1. Check in related_file_number table
        if (Schema::connection('sqlsrv')->hasTable('related_file_number')) {
            $rfnRecord = DB::connection('sqlsrv')->table('related_file_number')
                ->whereIn('file_number', $variants)
                ->where(function ($q) {
                    $q->where('related_fileno', 'like', 'KN%');
                })
                ->orderByDesc('id')
                ->first();
            if ($rfnRecord && $this->isOldMlsKnFileNo($rfnRecord->related_fileno)) {
                return $rfnRecord->related_fileno;
            }

            // check the other way around
            $rfnRecordRev = DB::connection('sqlsrv')->table('related_file_number')
                ->whereIn('related_fileno', $variants)
                ->where(function ($q) {
                    $q->where('file_number', 'like', 'KN%');
                })
                ->orderByDesc('id')
                ->first();
            if ($rfnRecordRev && $this->isOldMlsKnFileNo($rfnRecordRev->file_number)) {
                return $rfnRecordRev->file_number;
            }
        }

        // 2. Check in file_indexings table
        if (Schema::connection('sqlsrv')->hasTable('file_indexings')) {
            $fiRecord = DB::connection('sqlsrv')->table('file_indexings')
                ->whereIn('file_number', $variants)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNotNull('new_kangis_file_no')->where('new_kangis_file_no', '<>', '')
                      ->orWhereNotNull('kangis_file_no')->where('kangis_file_no', '<>', '');
                })
                ->orderByDesc('id')
                ->first();
            if ($fiRecord) {
                $val = $fiRecord->new_kangis_file_no ?: $fiRecord->kangis_file_no;
                if ($this->isOldMlsKnFileNo($val)) {
                    return $val;
                }
            }
        }

        // 3. Check in pra table
        if (Schema::connection('sqlsrv')->hasTable('pra')) {
            $praRecord = DB::connection('sqlsrv')->table('pra')
                ->whereIn('mlsFNo', $variants)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNotNull('NewKANGISFileno')->where('NewKANGISFileno', '<>', '')
                      ->orWhereNotNull('kangisFileNo')->where('kangisFileNo', '<>', '');
                })
                ->orderByDesc('id')
                ->first();
            if ($praRecord) {
                $val = $praRecord->NewKANGISFileno ?: $praRecord->kangisFileNo;
                if ($this->isOldMlsKnFileNo($val)) {
                    return $val;
                }
            }
        }

        return null;
    }

    /**
     * A SYSTEM temporary file number (e.g. "TEMP-91950") is an internal placeholder from the
     * deed-registration pipeline — not a real land file. Per the client: it never gets a File
     * Commissioning or File Decommissioning row, and it never displays in the File No field.
     * (Distinct from a "(T)" temporary file, which is a genuine temporary file with its own
     * "Temporary File" timeline row.)
     */
    private function isSystemTempFileNo(?string $fileNo): bool
    {
        return (bool) preg_match('/^TEMP[-_ ]?\d+/i', trim((string) $fileNo));
    }

    /**
     * Display label for a recertification timeline row, per the client spec:
     *   - OLD KANGIS file (KNML/MLKN/KNGP)   -> "First KANGIS Recertification"  (the 2014–2024 exercise)
     *   - NEW KANGIS file (KN + digits)      -> "Second KANGIS Recertification" (the 2025–present exercise)
     *   - Ministry / Physical Planning recert -> "Ministry of Land and Physical Planning Recertification"
     *
     * First-vs-Second is decided by the LINKED KANGIS file's own number format ($kangisNo), not by the
     * searched file. Only a stored type that already denotes a recertification is relabelled — an untyped
     * or non-recert related row keeps its stored/neutral label (never masquerades as a recert).
     */
    private function recertDisplayLabel(?string $kangisNo, ?string $storedType): string
    {
        $stored = trim((string) $storedType);
        if ($stored === '') {
            return 'Related File';
        }
        if (stripos($stored, 'physical planning') !== false || stripos($stored, 'ministry') !== false || stripos($stored, 'Land Recertification') !== false) {
            return 'Land Recertification (File Commissioning)';
        }
        if (stripos($stored, 'recertification') !== false) {
            // "First/Second KANGIS Recertification" applies ONLY to a genuine KANGIS-format
            // file (Rules 6/7): old KNML/MLKN/KNGP -> First, new KN -> Second. When NEITHER
            // endpoint is a KANGIS number, a stored "KANGIS Recertification" type is spurious
            // (e.g. a land file wrongly linked as a recert, RES-1999-113) and must NOT be
            // relabelled a KANGIS recert — keep it neutral so it never shows a KANGIS line.
            if (!$this->isKangisFormat($kangisNo)) {
                return 'Related File';
            }
            return $this->identifyFileNumberType($kangisNo) === 'new_kangis'
                ? 'Second KANGIS Recertification'
                : 'First KANGIS Recertification';
        }
        return $stored;
    }

    /**
     * Suppress redundant neutral "Related File" link rows (source 'Related Fileno' with no real
     * transaction type). Per the client: when the related file number ALREADY displays with its
     * own transactions in the timeline, the bare link row adds nothing — drop it. Only when the
     * related file has NO transactions of its own does the link row remain, and then its
     * Instrument/Transaction Type displays blank instead of the "Related File" placeholder.
     */
    private function suppressRedundantRelatedFileRows(array $rows): array
    {
        $isNeutralLink = function (array $row): bool {
            if ((string) ($row['source_table'] ?? '') !== 'Related Fileno') {
                return false;
            }
            $type = trim((string) ($row['transaction_type'] ?? ''));
            return $type === '' || $type === '-' || strcasecmp($type, 'Related File') === 0;
        };

        // File numbers that appear on REAL rows (anything that isn't a neutral link row).
        $realNos = [];
        foreach ($rows as $row) {
            if ($isNeutralLink($row)) {
                continue;
            }
            foreach (['fileno', 'file_number', 'mlsFNo'] as $col) {
                $v = $this->normalizeLifecycleFileNo((string) ($row[$col] ?? ''));
                if ($v !== '' && $v !== '-') {
                    $realNos[$v] = true;
                }
            }
        }

        $out = [];
        foreach ($rows as $row) {
            if ($isNeutralLink($row)) {
                $no = $this->normalizeLifecycleFileNo(
                    (string) ($row['file_number'] ?? ($row['fileno'] ?? ($row['mlsFNo'] ?? '')))
                );
                if ($no !== '' && isset($realNos[$no])) {
                    continue; // the related file already displays with its own transactions
                }
                $row['transaction_type'] = '-'; // keep the link row, but blank the type label
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * A recertified LAND file carries an "-RC-" token in its number (e.g. RES-RC-1982-200,
     * CON-RES-RC-2005-1). Per the client spec such files show a synthetic "Ministry of Land
     * and Physical Planning Recertification" line under their File Commissioning. Excludes
     * KANGIS-format numbers (those carry a KANGIS Recertification instead).
     */
    private function isRecertLandFile(?string $fileNo): bool
    {
        $v = strtoupper(trim((string) $fileNo));
        if ($v === '' || $this->isKangisFormat($v)) {
            return false;
        }
        return (bool) preg_match('~(?:^|[-_/ ])RC(?:[-_/ ]|$)~', $v);
    }

    /**
     * An Occupancy Permit / Transfer of Title row. OP (temp and/or land file) and its ToT
     * (land file) legitimately share one prop_id, so the prop_id-misassignment guard must
     * never drop them. Matches the instrument/transaction label, tolerant of the "(OP)" suffix.
     */
    private function isOpTotRow(array $row): bool
    {
        $t = strtolower((string) ($row['transaction_type'] ?? ($row['instrument_type'] ?? '')));
        if ($t === '') {
            return false;
        }
        return str_contains($t, 'occupancy permit')
            || str_contains($t, 'transfer of title')
            || str_contains($t, '(op)');
    }

    /**
     * Transaction Date to show on a File Decommissioning row (client Rule 2): the real
     * "Date Decommissioned" only for a genuine KLAES decommission ('parcel_update_new' or
     * 'title_status_update'); a "back linkage" / backfilled decommission ('backfill' or an
     * unclassified/null event_type) shows a blank ('-') Transaction Date.
     */
    private function decommissionDisplayDate(?string $eventType, $rawDate): string
    {
        $date = trim((string) $rawDate);
        if ($date === '' || $date === '-') {
            return '-';
        }
        return in_array($eventType, ['parcel_update_new', 'title_status_update'], true) ? $date : '-';
    }

    /** A title-status decommission whose File Decommissioning row must be the LAST timeline line (Rule 3). */
    private function isTitleStatusDecommission(array $row): bool
    {
        return ($row['_decommission_event_type'] ?? null) === 'title_status_update';
    }

    /**
     * Build a print-format "Ministry of Land and Physical Planning Recertification" row for an
     * "-RC-" land file (client Rule 4). Classified into the recertification band (weight 8) so it
     * sits under the File Commissioning line and above the C of O.
     */
    private function makePrintMinistryRecertRow(string $fileNo, array $meta): array
    {
        $holder = $meta['commissioning_holder'] ?: $meta['file_title'] ?: '-';
        $knFileNo = $this->resolveKnFileNoForLandFile($fileNo) ?: '-';

        $txDate = '-';
        if (preg_match('/(?:^|[-_\/ ])(19\d{2}|20\d{2})(?:[-_\/ ]|$)/', $fileNo, $matches)) {
            $txDate = $matches[1];
        }

        return [
            '_is_recertification' => true,
            'sn' => 0,
            'file_no' => $fileNo,
            'lifecycle_file_no' => $fileNo,
            'grantor' => 'Kano State Ministry of Land and Physical Planning',
            'grantee' => $holder,
            'party_3' => '-',
            'party_4' => '-',
            'instrument_type' => 'Land Recertification (File Commissioning)',
            'transaction_type' => 'Land Recertification (File Commissioning)',
            'transaction_date' => $txDate,
            'reg_time' => '-',
            'reg_date' => '-',
            'reg_no' => '0/0/0',
            'size' => '-',
            'caveat' => 'No',
            'comments' => $knFileNo,
            'source_table' => 'Related Fileno',
            'location' => '',
            '_synthesized' => true,
        ];
    }

    private function extractKangisEndpoint(array $row): string
    {
        foreach ([$row['fileno'] ?? null, $row['file_number'] ?? null, $row['mlsFNo'] ?? null] as $c) {
            $c = trim((string) $c);
            if ($c !== '' && $c !== '-' && $this->isKangisFormat($c)) {
                return $c;
            }
        }
        return '';
    }

    private function extractMainEndpoint(array $row): string
    {
        // Explicit parent_file_number is the linked counterpart; prefer the non-KANGIS side.
        $parent = trim((string) ($row['parent_file_number'] ?? ''));
        if ($parent !== '' && $parent !== '-' && !$this->isKangisFormat($parent)) {
            return $parent;
        }
        // Otherwise scan the row's file-number columns for the non-KANGIS endpoint.
        foreach ([$row['fileno'] ?? null, $row['file_number'] ?? null, $row['mlsFNo'] ?? null] as $c) {
            $c = trim((string) $c);
            if ($c !== '' && $c !== '-' && !$this->isKangisFormat($c)) {
                return $c;
            }
        }
        return '';
    }

    private function extractOwnFileNo(array $row): string
    {
        foreach (['file_no', 'fileno', 'file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
            $c = trim((string) ($row[$col] ?? ''));
            if ($c !== '' && $c !== '-') {
                return $c;
            }
        }
        return '';
    }

    /**
     * Metadata used to order lifecycle-file groups and build synthetic rows.
     */
    private function resolveLifecycleFileMeta($conn, string $fileNo): array
    {
        $baseNo = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNo);
        $baseNo = trim($baseNo) !== '' ? trim($baseNo) : $fileNo;

        $commInfo = $this->resolveCommissioningInfo($baseNo);
        $commDate = $commInfo['date'] ?? '-';
        $commTimestamp = null;
        if ($commDate !== '-' && $commDate !== '') {
            $commTimestamp = rescue(fn () => Carbon::parse($commDate)->timestamp, null, false);
        }
        if ($commTimestamp === null) {
            $year = $this->extractYearFromFileNumber($baseNo);
            if ($year) {
                $commTimestamp = rescue(fn () => Carbon::create((int) $year, 1, 1)->timestamp, null, false);
            }
        }

        $lineage = $this->resolveFileLineage($conn, $fileNo);

        // If there is no genuine commissioning date, the file's lifecycle effectively
        // starts on its earliest real transaction (e.g. a subdivision child's creation
        // date). Use that as a fallback so related files order chronologically.
        $effectiveStartTimestamp = $commTimestamp;
        if ($effectiveStartTimestamp === null) {
            $effectiveStartTimestamp = $this->resolveEarliestTransactionTimestamp($conn, $baseNo);
        }

        return [
            'file_no' => $fileNo,
            'base_file_no' => $baseNo,
            'commissioning_date' => $commDate,
            'commissioning_timestamp' => $commTimestamp,
            'effective_start_timestamp' => $effectiveStartTimestamp,
            'commissioning_holder' => $this->resolveCommissioningHolder($conn, $baseNo),
            'file_title' => $this->resolveFileTitleForNumber($conn, $baseNo),
            'is_decommissioned' => $lineage['is_superseded'] ?? false,
            'decommission_date' => $lineage['decommission_date'] ?? null,
            'decommission_reason' => $lineage['decommission_reason'] ?? null,
            'decommission_holder' => $lineage['decommission_holder'] ?? null,
            'decommission_event_type' => $lineage['decommission_event_type'] ?? null,
            'is_temp' => (bool) preg_match('/\(\s*T\s*\)\s*$/i', $fileNo),
        ];
    }

    /**
     * Find the earliest real transaction timestamp for a file number, used as a
     * lifecycle-start fallback when no commissioning date is on record.
     */
    private function resolveEarliestTransactionTimestamp($conn, string $fileNo): ?int
    {
        if ($fileNo === '') {
            return null;
        }

        $variants = $this->fileNumberVariants($fileNo);
        if (empty($variants)) {
            return null;
        }

        $columnsByTable = [
            'pra' => ['mlsFNo', 'fileno'],
            'file_history_staging' => ['mlsFNo', 'fileno', 'file_number'],
            'CofO_staging' => ['file_number'],
            'deed_registrations' => ['file_number'],
        ];

        $earliest = null;
        $schema = Schema::connection($conn->getName());

        foreach ($columnsByTable as $table => $columns) {
            if (!$schema->hasTable($table)) {
                continue;
            }
            try {
                $query = $conn->table($table)
                    ->where(function ($q) use ($columns, $variants) {
                        foreach ($columns as $col) {
                            $q->orWhereIn($col, $variants);
                        }
                    });
                $this->applySoftDeleteFilter($query, $table);

                $dateCols = [];
                foreach (['transaction_date', 'reg_date', 'deeds_date'] as $c) {
                    if ($schema->hasColumn($table, $c)) {
                        $dateCols[] = $c;
                    }
                }
                if (empty($dateCols)) {
                    continue;
                }

                foreach ($dateCols as $dateCol) {
                    $minDate = $query->clone()
                        ->whereNotNull($dateCol)
                        ->where($dateCol, '<>', '')
                        ->where($dateCol, '<>', '-')
                        ->orderBy($dateCol)
                        ->value($dateCol);

                    if ($minDate) {
                        $ts = rescue(fn () => Carbon::parse($minDate)->timestamp, null, false);
                        if ($ts !== null && ($earliest === null || $ts < $earliest)) {
                            $earliest = $ts;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Best-effort: skip tables that fail.
            }
        }

        return $earliest;
    }

    /**
     * Build a print-format "File Commissioning" synthetic row for any lifecycle file.
     */
    private function makePrintCommissioningRow(string $fileNo, array $meta): array
    {
        $date = $meta['commissioning_date'] ?? '-';
        $txnDate = '-';
        if ($date === '-') {
            $txnDate = $this->extractYearFromFileNumber($fileNo) ?? '-';
        }
        $holder = $meta['commissioning_holder'] ?: $meta['file_title'] ?: '-';

        return [
            'sn' => 0,
            'file_no' => $fileNo,
            'lifecycle_file_no' => $fileNo,
            'grantor' => 'Kano State Ministry of Land and Physical Planning',
            'grantee' => $holder,
            'party_3' => '-',
            'party_4' => '-',
            'instrument_type' => 'File Commissioning',
            'transaction_date' => $txnDate,
            'reg_time' => '-',
            'reg_date' => $date,
            'reg_no' => '0/0/0',
            'size' => '-',
            'caveat' => 'No',
            'comments' => '-',
            'source_table' => 'File Commissioning',
            'location' => '',
            '_synthesized' => true,
        ];
    }

    /**
     * Build a print-format "Temporary File" synthetic row.
     */
    private function makePrintTemporaryFileRow(string $fileNo, array $meta): array
    {
        $holder = $meta['commissioning_holder'] ?: $meta['file_title'] ?: '-';

        return [
            'sn' => 0,
            'file_no' => $fileNo,
            'lifecycle_file_no' => $fileNo,
            'grantor' => 'Kano State Ministry of Land and Physical Planning',
            'grantee' => $holder,
            'party_3' => '-',
            'party_4' => '-',
            'instrument_type' => 'Temporary File',
            'transaction_date' => '-',
            'reg_time' => '-',
            'reg_date' => '-',
            'reg_no' => '0/0/0',
            'size' => '-',
            'caveat' => 'No',
            'comments' => '-',
            'source_table' => 'Temporary File',
            'location' => '',
            '_synthesized' => true,
        ];
    }

    /**
     * Build a print-format "File Decommissioning" synthetic row.
     */
    private function makePrintDecommissioningRow(string $fileNo, array $meta): array
    {
        $displayDate = $this->decommissionDisplayDate(
            $meta['decommission_event_type'] ?? null,
            $meta['decommission_date'] ?? '-'
        );
        $holder = $meta['decommission_holder'] ?: $meta['file_title'] ?: '-';
        $reason = $meta['decommission_reason'] ?? '-';

        return [
            'sn' => 0,
            'file_no' => $fileNo,
            'lifecycle_file_no' => $fileNo,
            'grantor' => 'Kano State Ministry of Land and Physical Planning',
            'grantee' => $holder,
            'party_3' => '-',
            'party_4' => '-',
            'instrument_type' => 'File Decommissioning',
            'transaction_date' => $displayDate,
            'reg_time' => '-',
            'reg_date' => $displayDate,
            '_decommission_event_type' => $meta['decommission_event_type'] ?? null,
            'reg_no' => '0/0/0',
            'size' => '-',
            'caveat' => 'No',
            'comments' => $reason,
            'source_table' => 'File Decommissioning',
            'location' => '',
            '_synthesized' => true,
        ];
    }

    /**
     * Ensure every lifecycle file has its commissioning row, and its decommissioning /
     * temporary-file rows where applicable. Existing synthetic rows are preserved and
     * only missing ones are added.
     */
    private function ensureLifecycleSyntheticRows($conn, array $rows, array $lifecycleFiles, string $searchedFileNo): array
    {
        $metaCache = [];
        $hasCommissioning = [];
        $hasDecommissioning = [];
        $hasTemp = [];
        $hasMinistryRecert = [];

        foreach ($rows as $row) {
            $fno = $row['lifecycle_file_no'] ?? null;
            if (!$fno) {
                continue;
            }
            $source = (string) ($row['source_table'] ?? '');
            $instrument = (string) ($row['instrument_type'] ?? '');
            $type = (string) ($row['transaction_type'] ?? '');

            if ($source === 'File Commissioning' || $source === 'DCIV File Commissioning'
                || $instrument === 'File Commissioning' || $instrument === 'DCIV File Commissioning') {
                $hasCommissioning[$fno] = true;
            }
            // A unit ST "ST File Commissioning – Fragmentation" row IS that unit's
            // commissioning event — don't also synthesize a generic "File Commissioning"
            // for it (which would replace the real ST label + date). The mother's ST
            // primary row is tagged to the LAND lifecycle, which already carries its own
            // Land File Commissioning row, so this is a no-op there.
            if ($source === 'ST File Commissioning') {
                $hasCommissioning[$fno] = true;
            }
            if ($source === 'File Decommissioning' || $instrument === 'File Decommissioning') {
                $hasDecommissioning[$fno] = true;
            }
            if ($source === 'Temporary File' || $instrument === 'Temporary File') {
                $hasTemp[$fno] = true;
            }
            // A Ministry recert may already be present as a real "Related Fileno" link row —
            // don't synthesize a second one for the same file (Rule 4, dedup).
            if (stripos($type, 'physical planning') !== false && stripos($type, 'recertification') !== false) {
                $hasMinistryRecert[$fno] = true;
            }
        }

        foreach ($lifecycleFiles as $fno => $_) {
            if (!isset($metaCache[$fno])) {
                $metaCache[$fno] = $this->resolveLifecycleFileMeta($conn, $fno);
            }
            $meta = $metaCache[$fno];

            // A KANGIS-format file (KNML/MLKN/KNGP/KN…) is an alias of a land file, never
            // its own lifecycle — suppress its synthetic Commissioning/Decommissioning
            // rows (its Recertification, classified separately, still appears). A SYSTEM
            // temporary number ("TEMP-xxx") is an internal placeholder and is suppressed
            // the same way.
            $isKangisLifecycle = $this->isKangisFormat($fno) || $this->isSystemTempFileNo($fno);

            if (empty($hasCommissioning[$fno]) && !$isKangisLifecycle) {
                $rows[] = $this->makePrintCommissioningRow($fno, $meta);
            }

            // Rule 4: an "-RC-" land file gets a Ministry of Land and Physical Planning
            // Recertification line (unless one already arrived as a real link row).
            if (empty($hasMinistryRecert[$fno]) && $this->isRecertLandFile($fno)) {
                $rows[] = $this->makePrintMinistryRecertRow($fno, $meta);
            }

            if ($meta['is_temp'] && empty($hasTemp[$fno])) {
                $rows[] = $this->makePrintTemporaryFileRow($fno, $meta);
            }

            if ($meta['is_decommissioned'] && empty($hasDecommissioning[$fno]) && !$isKangisLifecycle) {
                $rows[] = $this->makePrintDecommissioningRow($fno, $meta);
            }
        }

        return $rows;
    }

    /**
     * Deduplicate lifecycle event rows within each lifecycle file group. Each file
     * should display at most one File Commissioning, Temporary File, File
     * Decommissioning and KANGIS Recertification row. Keeps the most authoritative
     * row (explicit source_table match, real date, non-synthesized) when duplicates
     * exist.
     */
    private function dedupeLifecycleRows(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $fno = $row['lifecycle_file_no'] ?? null;
            if (!$fno) {
                $grouped[''][] = $row;
                continue;
            }
            $grouped[$fno][] = $row;
        }

        $result = [];
        foreach ($grouped as $fno => $groupRows) {
            // Pick the best-scored row per lifecycle-event key for the whole group.
            // KANGIS recertifications are keyed by KANGIS file number so distinct
            // KANGIS recerts can coexist while duplicates collapse.
            $bestByKey = [];
            foreach ($groupRows as $r) {
                $eventKey = $this->lifecycleEventDedupeKey($r);
                $type = $this->classifyLifecycleEventType($r);
                if ($eventKey === null || $type === null) {
                    continue;
                }
                if (!isset($bestByKey[$eventKey])
                    || $this->scoreLifecycleRow($r, $type) > $this->scoreLifecycleRow($bestByKey[$eventKey], $type)) {
                    $bestByKey[$eventKey] = $r;
                }
            }

            // Rebuild preserving original order: emit each event key's winner at the
            // position of its FIRST occurrence (so a Kangis Recertification stays
            // directly above its C of O), dropping subsequent duplicates.
            $emitted = [];
            foreach ($groupRows as $r) {
                $eventKey = $this->lifecycleEventDedupeKey($r);
                if ($eventKey === null) {
                    $result[] = $r;
                    continue;
                }
                if (isset($emitted[$eventKey])) {
                    continue;
                }
                $emitted[$eventKey] = true;
                $result[] = $bestByKey[$eventKey];
            }
        }
        return $result;
    }

    private function lifecycleEventDedupeKey(array $row): ?string
    {
        $type = $this->classifyLifecycleEventType($row);
        if ($type === null) {
            return null;
        }

        // The mother ST "ST File Commissioning" event is DISTINCT from the mother's
        // "Land File Commissioning" event — both classify as 'File Commissioning' but must
        // coexist in the same land lifecycle block, so give the ST primary its own key.
        if (!empty($row['_st_primary_commissioning'])) {
            return 'ST File Commissioning';
        }

        if ($type === 'Kangis Recertification') {
            $kangis = $this->extractKangisLifecycleKey($row);
            if ($kangis === '') {
                $kangis = $this->normalizeLifecycleFileNo($this->extractOwnFileNo($row));
            }
            return $type . '|' . $kangis;
        }

        return $type;
    }

    private function extractKangisLifecycleKey(array $row): string
    {
        $candidates = [
            $row['kangisFileNo'] ?? null,
            $row['NewKANGISFileno'] ?? null,
            $row['file_no'] ?? null,
            $row['fileno'] ?? null,
            $row['file_number'] ?? null,
            $row['mlsFNo'] ?? null,
            $row['parent_file_number'] ?? null,
        ];

        foreach ($candidates as $c) {
            $c = trim((string) $c);
            if ($c !== '' && $c !== '-' && $this->isKangisFormat($c)) {
                return $this->normalizeLifecycleFileNo($c);
            }
        }

        return '';
    }

    private function classifyLifecycleEventType(array $row): ?string
    {
        $source = (string) ($row['source_table'] ?? '');
        $instrument = (string) ($row['instrument_type'] ?? '');
        $type = (string) ($row['transaction_type'] ?? '');
        $synth = $row['_synthesized'] ?? false;

        if ($source === 'File Commissioning' || $source === 'DCIV File Commissioning' || $source === 'ST File Commissioning'
            || $instrument === 'File Commissioning' || $instrument === 'DCIV File Commissioning'
            || $type === 'File Commissioning' || $type === 'DCIV File Commissioning'
            || str_starts_with($type, 'ST File Commissioning')
            || ($synth && empty($row['_is_recertification']) && (str_contains($instrument, 'Commissioning') || str_contains($type, 'Commissioning')))) {
            return 'File Commissioning';
        }
        if ($source === 'Temporary File' || $instrument === 'Temporary File' || $type === 'Temporary File' || ($synth && str_contains($instrument, 'Temporary File'))) {
            return 'Temporary File';
        }
        if ($source === 'File Decommissioning' || $instrument === 'File Decommissioning' || $type === 'File Decommissioning' || ($synth && str_contains($instrument, 'Decommissioning'))) {
            return 'File Decommissioning';
        }
        if (stripos($type, 'KANGIS') !== false && stripos($type, 'Recertification') !== false) {
            return 'Kangis Recertification';
        }
        
        return null;
    }

    private function scoreLifecycleRow(array $row, string $eventType): int
    {
        $source = (string) ($row['source_table'] ?? '');
        $instrument = (string) ($row['instrument_type'] ?? '');
        $type = (string) ($row['transaction_type'] ?? '');
        $date = trim((string) ($row['transaction_date'] ?? ''));

        $score = 0;
        if ($source === $eventType) {
            $score += 100;
        }
        if (empty($row['_synthesized'])) {
            $score += 50;
        }
        if ($date !== '' && $date !== '-') {
            $score += 25;
        }
        if ($instrument === $eventType) {
            $score += 10;
        }
        if ($type === $eventType) {
            $score += 10;
        }
        return $score;
    }

    /**
     * Order lifecycle-file groups. The primary (searched) file is always first.
     * Remaining files are sorted by commissioning timestamp ascending, then by the
     * predecessor/successor relationship (predecessors before siblings before
     * successors), then by normalized file number for determinism.
     */
    private function orderLifecycleFiles(array $files, string $primaryFileNo, array $fileMeta): array
    {
        $conn = DB::connection('sqlsrv');
        $relationships = [];
        foreach ($files as $fno) {
            if ($fno === $primaryFileNo) {
                continue;
            }
            $relationships[$fno] = $this->classifyLifecycleRelationship($conn, $fno, $primaryFileNo);
        }

        // Rule 11: when a CHILD is searched, its PARENT (a predecessor) block renders FIRST, then the
        // searched child, then siblings/successors. Rank: predecessor = 0, searched = 1, other = 2.
        // A parent/mother search has no predecessors, so the searched file naturally stays first.
        // (This changes block ORDER only; the "Last Transaction" field stays scoped to the searched
        // file's own group, so it still reports the searched child's last dealing.)
        $rankOf = function ($f) use ($primaryFileNo, $relationships) {
            if ($f === $primaryFileNo) {
                return 1;
            }
            return (($relationships[$f] ?? 0) === -1) ? 0 : 2;
        };
        usort($files, function ($a, $b) use ($fileMeta, $relationships, $rankOf) {
            $rankA = $rankOf($a);
            $rankB = $rankOf($b);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            $ta = $fileMeta[$a]['effective_start_timestamp'] ?? ($fileMeta[$a]['commissioning_timestamp'] ?? PHP_INT_MAX);
            $tb = $fileMeta[$b]['effective_start_timestamp'] ?? ($fileMeta[$b]['commissioning_timestamp'] ?? PHP_INT_MAX);
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

            $ra = $relationships[$a] ?? 1;
            $rb = $relationships[$b] ?? 1;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            // Same effective start / relationship: fall back to the numeric serial in
            // the file number (e.g. RES-2026-123 → 123). Serials are generally assigned
            // sequentially within a year, so this gives a chronological, deterministic
            // order when commissioning/transaction dates cannot distinguish files.
            $sa = $this->extractSerialFromFileNumber($a);
            $sb = $this->extractSerialFromFileNumber($b);
            if ($sa !== null && $sb !== null && $sa !== $sb) {
                return $sa <=> $sb;
            }

            return strcmp($a, $b);
        });

        return $files;
    }

    /**
     * Extract the trailing numeric serial from a file number for deterministic ordering.
     */
    private function extractSerialFromFileNumber(?string $fileNo): ?int
    {
        $fileNo = trim((string) $fileNo);
        if ($fileNo === '') {
            return null;
        }
        // Strip temp suffix so CON-AG-2026-108(T) → CON-AG-2026-108.
        $fileNo = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $fileNo);
        if (preg_match('/(\d+)$/', $fileNo, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Classify how a related file relates to the primary file for ordering:
     *   -1 = predecessor (direct or indirect ancestor)
     *    0 = sibling / other
     *    1 = successor (direct or indirect descendant)
     *
     * The classification walks decommissioned_files successor_file_no chains up to
     * a hard depth limit so indirect descendants (e.g. grandchildren) are still
     * grouped with direct descendants rather than siblings.
     */
    private function classifyLifecycleRelationship($conn, string $fileNo, string $primaryFileNo): int
    {
        $primaryNorm = strtoupper(trim($primaryFileNo));
        $fileNorm = strtoupper(trim($fileNo));
        if ($primaryNorm === '' || $fileNorm === '') {
            return 0;
        }

        try {
            $schema = Schema::connection($conn->getName());
            if ($schema->hasTable('decommissioned_files')
                && $schema->hasColumn('decommissioned_files', 'successor_file_no')) {
                // Forward walk: primary → successors → ... → fileNo?
                $forward = $this->walkLifecycleChain($conn, $primaryNorm, $fileNorm, 'forward');
                if ($forward !== null) {
                    return 1;
                }

                // Backward walk: primary ← predecessors ← ... ← fileNo?
                $backward = $this->walkLifecycleChain($conn, $fileNorm, $primaryNorm, 'forward');
                if ($backward !== null) {
                    return -1;
                }
            }

            // Is $fileNo recorded as a parent/predecessor of primary?
            $parentRel = $conn->table('file_indexings')
                ->whereNull('deleted_at')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$primaryNorm])
                ->value('related_fileno');
            if ($parentRel) {
                $parents = $this->parseRelatedFileno($parentRel);
                foreach ($parents as $p) {
                    if (strtoupper(trim($p)) === $fileNorm) {
                        return -1;
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall through to sibling/other
        }

        // RC-prefix files (e.g. COM-RC-1982-233) carry the "-RC-" token because they are the
        // OLDER Ministry/Physical-Planning file that was later recertified INTO a newer MLS
        // number — by definition a predecessor of that land file, regardless of embedded year
        // or whether a decommissioned_files/related_fileno chain links them (per user: an RC
        // file must render before the land file it was recertified into).
        $fileIsRc = $this->isRecertLandFile($fileNo);
        $primaryIsRc = $this->isRecertLandFile($primaryFileNo);
        if ($fileIsRc && !$primaryIsRc) {
            return -1;
        }
        if (!$fileIsRc && $primaryIsRc) {
            return 1;
        }

        return 0;
    }

    /**
     * Walk the decommissioned_files successor chain to see if $target is reachable
     * from $start. Returns the hop count or null when not reachable.
     */
    private function walkLifecycleChain($conn, string $start, string $target, string $direction, int $maxDepth = 10): ?int
    {
        $start = strtoupper(trim($start));
        $target = strtoupper(trim($target));
        if ($start === $target) {
            return 0;
        }

        $visited = [$start => true];
        $queue = [['file' => $start, 'depth' => 0]];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current['depth'] >= $maxDepth) {
                continue;
            }

            $nextFiles = [];
            if ($direction === 'forward') {
                $successors = $conn->table('decommissioned_files')
                    ->whereRaw('UPPER(LTRIM(RTRIM(file_no))) = ?', [$current['file']])
                    ->pluck('successor_file_no');
                foreach ($successors as $succCsv) {
                    foreach (array_map('trim', explode(',', (string) $succCsv)) as $s) {
                        $s = strtoupper($s);
                        if ($s !== '') {
                            $nextFiles[] = $s;
                        }
                    }
                }
            }

            foreach ($nextFiles as $next) {
                if ($next === $target) {
                    return $current['depth'] + 1;
                }
                if (!isset($visited[$next])) {
                    $visited[$next] = true;
                    $queue[] = ['file' => $next, 'depth' => $current['depth'] + 1];
                }
            }
        }

        return null;
    }

    /**
     * Arrange the rows for a single lifecycle file into phases:
     *   1. Commissioning (File / DCIV)
     *   2. Temporary File
     *   3. Transactions (preserving their existing order)
     *   4. File Decommissioning
     */
    private function arrangeLifecycleFileRows(array $rows): array
    {
        $decommissioning = [];
        $transactions = [];

        foreach ($rows as $row) {
            $event = $this->classifyLifecycleEventType($row);
            if ($event === 'File Decommissioning') {
                $decommissioning[] = $row;
            } else {
                $transactions[] = $row;
            }
        }

        $canonicalTransactionType = function (?string $type): string {
            $raw = trim(mb_strtolower($type ?? ''));
            if ($raw === '' || $raw === '-') {
                return '';
            }
            if (str_contains($raw, 'right of occupancy') || str_contains($raw, 'right of occupanc') || preg_match('/^r\s*of\s*o$/', $raw)) {
                return 'right of occupancy';
            }
            if (str_contains($raw, 'certificate of occupancy') || str_contains($raw, 'cert of occupancy') || preg_match('/^c\s*of\s*o$/', $raw)) {
                return 'certificate of occupancy';
            }
            if (str_contains($raw, 'occupancy permit') || preg_match('/^o\s*p$/', $raw)) {
                return 'occupancy permit';
            }
            if (str_contains($raw, 'transfer of title') || str_contains($raw, 'tot')) {
                return 'transfer of title';
            }
            if (str_contains($raw, 'file commissioning')) {
                return 'file commissioning';
            }
            return $raw;
        };

        $weightOf = function (array $row) use ($canonicalTransactionType): int {
            $txType = $canonicalTransactionType($row['transaction_type'] ?? ($row['instrument_type'] ?? ''));
            $w = \App\Support\LegalSearchTimelineWeights::weightFor($row, $txType);
            return $w ?? 0;
        };

        $ts = function (array $r): ?int {
            $candidates = [
                $r['transaction_date'] ?? null,
                $r['deeds_date'] ?? null,
                $r['reg_date'] ?? null,
                $r['cofo_date'] ?? null,
                $r['certificateDate'] ?? null,
                $r['approval_date'] ?? null,
                $r['date'] ?? null,
            ];
            foreach ($candidates as $c) {
                $d = trim((string) $c);
                if ($d !== '' && $d !== '-') {
                    $parsed = rescue(fn () => \Carbon\Carbon::parse($d)->getTimestamp(), null, false);
                    if ($parsed !== null) return $parsed;
                }
            }
            return null;
        };

        usort($transactions, function (array $a, array $b) use ($weightOf, $ts): int {
            $wa = $weightOf($a);
            $wb = $weightOf($b);
            if ($wa !== $wb) {
                return $wb <=> $wa;
            }
            $ta = $ts($a);
            $tb = $ts($b);
            if ($ta === null && $tb === null) {
                return ((int) ($a['sn'] ?? 0)) <=> ((int) ($b['sn'] ?? 0));
            }
            if ($ta === null) return 1;
            if ($tb === null) return -1;
            return $ta <=> $tb;
        });

        // Recertification/CoFO pairing executes strictly inside this lifecycle's
        // transaction phase so rows never leave their lifecycle group.
        $transactions = $this->placeKangisRecertBeforeCofo($transactions);

        // Rule 4: the "Ministry of Land and Physical Planning Recertification" line for an
        // "-RC-" land file must sit directly UNDER the File Commissioning line (top of the
        // transaction band). It is usually undated, which would otherwise float it to the
        // very end of the block — so hoist it to the front of the transactions here.
        $commissioning = [];
        $ministryRecert = [];
        $otherTransactions = [];
        foreach ($transactions as $t) {
            $ty = (string) ($t['transaction_type'] ?? ($t['instrument_type'] ?? ''));
            $evt = $this->classifyLifecycleEventType($t);
            $isComm = ($evt === 'File Commissioning' || $evt === 'Temporary File' || $evt === 'DCIV File Commissioning' || $evt === 'ST File Commissioning');
            
            $isMinistry = (stripos($ty, 'physical planning') !== false && stripos($ty, 'recertification') !== false)
                || (stripos($ty, 'Land Recertification') !== false);
            
            if ($isComm) {
                $commissioning[] = $t;
            } elseif ($isMinistry) {
                $ministryRecert[] = $t;
            } else {
                $otherTransactions[] = $t;
            }
        }
        $transactions = array_merge($commissioning, $ministryRecert, $otherTransactions);

        // Sectional Titling: within an ST unit's block the transactions must read strictly
        // chronologically (e.g. Right of Occupancy before its later Assignment/Transfer of
        // Title), NOT by the global legal-hierarchy weight (which ranks Transfer of Title
        // above Right of Occupancy for OP/TOT parcels). Only re-sort when EVERY transaction
        // in this band is an ST row, so non-ST lifecycles keep their weighted order untouched.
        if (count($transactions) > 1 && $this->allStRows($transactions)) {
            $tsSt = function (array $r): ?int {
                $d = trim((string) ($r['transaction_date'] ?? ''));
                if ($d === '' || $d === '-') {
                    return null;
                }
                $parsed = rescue(fn () => \Carbon\Carbon::parse($d)->getTimestamp(), null, false);
                return $parsed;
            };
            usort($transactions, function (array $a, array $b) use ($tsSt): int {
                $ta = $tsSt($a);
                $tb = $tsSt($b);
                if ($ta === null && $tb === null) {
                    return ((int) ($a['sn'] ?? 0)) <=> ((int) ($b['sn'] ?? 0));
                }
                if ($ta === null) return 1;   // undated rows sink to the end of the band
                if ($tb === null) return -1;
                return $ta <=> $tb;
            });
        }

        return array_merge($transactions, $decommissioning);
    }

    /**
     * True when every row is a Sectional Titling row — an "ST File Commissioning" source,
     * an "ST …" instrument (ST Assignment, ST Fragmentation), or a "(ST)" instrument
     * (Right of Occupancy (ST)). Used to scope the ST chronological re-sort.
     */
    private function allStRows(array $rows): bool
    {
        foreach ($rows as $r) {
            $source = (string) ($r['source_table'] ?? '');
            $type = strtoupper((string) ($r['transaction_type'] ?? ($r['instrument_type'] ?? '')));
            $isSt = $source === 'ST File Commissioning'
                || strpos($type, 'ST ') === 0
                || strpos($type, '(ST)') !== false;
            if (!$isSt) {
                return false;
            }
        }
        return !empty($rows);
    }

    /**
     * Group a print-ready timeline by lifecycle owner.
     *
     * @param array  $rows           Print-format rows (already weighted/floating sorted).
     * @param string $primaryFileNo  The searched file number (lifecycle owner).
     * @param string $searchedFileNo The original user-typed file number.
     * @return array Rows ordered by lifecycle file, then commissioning → transactions → decommissioning.
     */
    private function groupTimelineByLifecycle(array $rows, string $primaryFileNo, string $searchedFileNo, array $aliasHints = []): array
    {
        $conn = DB::connection('sqlsrv');
        $rows = $this->tagRowsWithLifecycleFileNo($rows, $aliasHints, $primaryFileNo);

        $normPrimary = $this->normalizeLifecycleFileNo($primaryFileNo);
        $normSearched = $this->normalizeLifecycleFileNo($searchedFileNo);

        // Always include the main/base searched file even if it has no transaction rows.
        $mainFileNo = preg_replace('/\s*\(\s*T\s*\)\s*$/i', '', $normSearched);
        $mainFileNo = trim($mainFileNo) !== '' ? trim($mainFileNo) : $normSearched;

        $lifecycleFiles = [];
        foreach ($rows as $row) {
            $fno = $row['lifecycle_file_no'] ?? null;
            if ($fno && $fno !== '') {
                $lifecycleFiles[$fno] = true;
            }
        }
        if ($normPrimary !== '') {
            $lifecycleFiles[$normPrimary] = true;
        }
        if ($mainFileNo !== '' && $mainFileNo !== $normPrimary) {
            $lifecycleFiles[$mainFileNo] = true;
        }

        $fileMeta = [];
        foreach (array_keys($lifecycleFiles) as $fno) {
            $fileMeta[$fno] = $this->resolveLifecycleFileMeta($conn, $fno);
        }

        $rows = $this->ensureLifecycleSyntheticRows($conn, $rows, $lifecycleFiles, $searchedFileNo);
        $rows = $this->tagRowsWithLifecycleFileNo($rows, $aliasHints, $primaryFileNo);
        $rows = $this->dedupeLifecycleRows($rows);

        $orderedFiles = $this->orderLifecycleFiles(array_keys($lifecycleFiles), $normPrimary, $fileMeta);

        $result = [];
        foreach ($orderedFiles as $fno) {
            $fileRows = array_values(array_filter($rows, fn ($r) => ($r['lifecycle_file_no'] ?? '') === $fno));
            $fileRows = $this->arrangeLifecycleFileRows($fileRows);
            $result = array_merge($result, $fileRows);
        }

        return $result;
    }
}
