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

            // Only filter when the searched file resolves to a definite prop_id; otherwise leave
            // file-number / orphan-only results untouched.
            if (!empty($searchedPropIds)) {
                $all = array_values(array_filter($all, function ($row) use ($searchedPropIds, $matchesSearchedFile, $isDistinctFile, $fileNo, $smeAllowed) {
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

        // KANGIS Recertification rows carry an empty/unreliable transaction date (or a
        // current-processing-year date), so the plain date sort above can strand them at
        // either end of the timeline. Business rule: when a C of O is present, the
        // Recertification must sit directly before it, not wherever its own date lands it.
        $all = $this->placeKangisRecertBeforeCofo($all);

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
                $_row['party_1'] = (str_contains($_type, 'ministry') || str_contains($_type, 'physical planning'))
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
                    ->select('file_title', 'district', 'lga', 'land_use_type', 'plot_number', 'tp_no', 'related_fileno', 'file_number', 'location', 'has_temp_file', 'temp_file_no', 'latitude', 'longitude', 'ground_rent_amount', 'ground_rent_receipt_date')
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
            foreach (($primaryCandidates ?? []) as $candidate) {
                if (strcasecmp((string) ($fileIndexingData->file_number ?? ''), (string) $candidate) === 0
                    || strcasecmp((string) ($fileIndexingData->temp_file_no ?? ''), (string) $candidate) === 0) {
                    $ownIndexedNumber = (string) $fileIndexingData->file_number;
                    break;
                }
            }
        }

        // Compute aggregate file_size using source weighting: CofO(4) > FH(3) > PRA(2) > Deed(1)
        $fileSize = null;
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

        return [
            'transactions' => $all,
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
            $out[] = [
                'id'                => $row->id,
                'file_number'       => $relNo, // orange-highlighted column
                'mlsFNo'            => $relNo,
                'fileno'            => $relNo,
                'kangisFileNo'      => null,
                'NewKANGISFileno'   => null,
                // Untyped related_file_number rows (e.g. Change-of-Purpose rename aliases with a
                // blank transaction_type) must NOT be relabelled "Recertification" — that made
                // rename/merger links masquerade as KANGIS recerts. Fall back to a neutral label.
                'transaction_type'  => $row->transaction_type ?: 'Related File',
                'transaction_date'  => $relIsManualLinkage ? '-' : $displayDate,
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
                'comments'          => $row->comment ?: '-',
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
                    'transaction_date'  => '-',
                    'sort_date'         => $sortDate,
                    'party_1'           => 'Kano State Ministry of Land and Physical Planning',
                    'party_2'           => $commHolder,
                    'comments'          => $commDate !== '' ? 'Commissioned ' . $commDate : '-',
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

        $number = $mlsRecord ? (trim((string) $mlsRecord->full_file_number) ?: null) : null;

        if ($mlsRecord && $mlsRecord->commissioning_date) {
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
                $decDate = (!empty($lineage['decommission_date']) && $lineage['decommission_date'] !== '-')
                    ? $lineage['decommission_date'] : '-';
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
                DB::raw("NULL AS deeds_date"),
                DB::raw("NULL AS deeds_time"),
                DB::raw("NULL AS reg_date"),
                DB::raw("transaction_time AS reg_time"),
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
            // CofO_staging has no reg_date/reg_time columns — the "Reg Time" the
            // report/timeline displays is actually this table's transaction_time.
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
     * Business rule shared by the on-screen LS Timeline and the printable report: when both a
     * Certificate of Occupancy and a KANGIS Recertification appear in a file's transaction
     * history, the Recertification must be displayed directly BEFORE the C of O. The
     * recertification is what admits the legacy file into KANGIS; the KANGIS C of O is issued
     * off the back of it, so the recertification always precedes it — never at the end of the
     * list, which is where a missing/current-year recertification date would otherwise strand
     * it. Every other transaction keeps its existing relative order.
     *
     * Works against either row shape produced by this service: the normalized search() rows
     * (keyed by 'transaction_type') and the printable-report rows (keyed by 'instrument_type').
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

        $recertRows = [];
        $rest = [];
        foreach ($rows as $row) {
            if ($isRecert($row)) {
                $recertRows[] = $row;
            } else {
                $rest[] = $row;
            }
        }

        if (empty($recertRows)) {
            return $rows;
        }

        // Insert before the FIRST C of O row, so the recertification sits above every
        // certificate on record when a file has more than one.
        $cofoIdx = null;
        foreach ($rest as $i => $row) {
            if ($isCofo($row)) {
                $cofoIdx = $i;
                break;
            }
        }

        if ($cofoIdx === null) {
            // No C of O on this file — leave the recertification wherever it naturally sorted.
            return $rows;
        }

        array_splice($rest, $cofoIdx, 0, $recertRows);

        return $rest;
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

        if (preg_match('/^[A-Z]{4}\s?\d{3,6}$/i', $cleanFileNo))
            return 'kangis';
        if (preg_match('/^KN\d{2,6}$/i', $cleanFileNo))
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

        $results = $this->search(['query' => $fileNo]);
        $transactions = $results['transactions'] ?? [];

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
                $transactions = $results['transactions'] ?? [];
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
                ->select('file_title', 'plot_number', 'plot_size', 'tp_no', 'land_use_type', 'related_fileno', 'file_number', 'has_temp_file', 'temp_file_no', 'latitude', 'longitude', 'location', 'ground_rent_amount', 'ground_rent_receipt_date')
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
            $rows[] = [
                'sn' => $idx + 1,
                'file_no' => $rowFileNo,
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
                'comments' => $t['is_caveated'] ? $tc($t['caveated_comment'] ?: ($t['comments'] ?: '-')) : $tc($t['comments'] ?: '-'),
                // Extra metadata (ignored by the print slip) so consumers that render
                // the same LS-weighed timeline on-screen — e.g. the PHS portal —
                // can show source badges and location identically to the slip.
                'source_table' => $labelToDb[$t['source_table'] ?? ''] ?? ($t['source_table'] ?? ''),
                'location' => $t['location'] ?? '',
            ];
        }

        // Same rule as the on-screen timeline: keep KANGIS Recertification directly before
        // the C of O in the printed transaction history, regardless of where the
        // weight/date sort above placed it. ('sn' is recomputed below once the
        // commissioning/temporary-file rows are unshifted onto the front.)
        $rows = $this->placeKangisRecertBeforeCofo($rows);

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
        // (e.g. RES-2001-3874 → 2001, CON-RES-1993-387 → 1993). Surface that year as
        // the Transaction Date on the row instead of leaving it blank.
        $commissioningTxnDate = '-';
        if ($commissioningDate === '-') {
            $commissioningTxnDate = $this->extractYearFromFileNumber($commissioningFileNo) ?? '-';
        }

        $commissioningRow = [
            'sn' => 0, // renumbered below
            'file_no' => $commissioningFileNo ?: '-',
            // Party 1 is the commissioning authority; Party 2 is the file owner/title
            // (the Ministry commissioned the file for them).
            'grantor' => 'Kano State Ministry of Land and Physical Planning',
            'grantee' => $commissioningHolder,
            'party_3' => '-',
            'party_4' => '-',
            'instrument_type' => 'File Commissioning',
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

        // ── Lineage commissioning chain ──
        // The searched file's commissioning row always opens the report; only
        // successor files add further synthetic commissioning rows (after the
        // parcel-update row that retired the searched file). Predecessors show
        // through their real transactions, never as a commissioning row.
        $rows = $this->placeCommissioningRows(
            $rows,
            $commissioningRow,
            $tempRow,
            $this->enrichLineageWithCommissioning(
                DB::connection('sqlsrv'),
                $this->resolveFileLineage(DB::connection('sqlsrv'), trim((string) $fileNo)),
                trim((string) $fileNo)
            )
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
        $parseDate = fn($d) => $d && $d !== '-' ? (rescue(fn() => \Carbon\Carbon::parse($d), null, false)) : null;

        $latestMortgage = collect($transactions)
            ->filter(fn($t) => stripos($t['transaction_type'] ?? '', 'deed of mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'tripartite mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'legal mortgage') !== false
                || stripos($t['transaction_type'] ?? '', 'equitable mortgage') !== false)
            ->map(fn($t) => $parseDate($t['reg_date'] ?? null))
            ->filter()
            ->max();

        if ($latestMortgage) {
            $latestRelease = collect($transactions)
                ->filter(fn($t) => stripos($t['transaction_type'] ?? '', 'deed of surrender and release') !== false)
                ->map(fn($t) => $parseDate($t['reg_date'] ?? null))
                ->filter()
                ->max();

            if (!$latestRelease || $latestRelease->lt($latestMortgage)) {
                $mortgageCaveat = true;
            }
        }

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
        $residualTerm = $displayResidualTerm !== ''
            ? $displayResidualTerm
            : $this->residualTermFromYear($landUse, $commencementDate ? (int) $commencementDate->year : null);

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
     * Residual Term of the Right of Occupancy: the land-use term (Residential/
     * Agricultural = 99 years, Commercial/Industrial = 40 years) minus the
     * years elapsed since the commencement year. Returns e.g. "28 Years", or
     * null when the land use has no defined term or the year is unusable.
     */
    private function residualTermFromYear(?string $landUse, ?int $commencementYear): ?string
    {
        $lu = strtoupper(trim((string) $landUse));
        $termYears = null;
        if (str_contains($lu, 'RESIDENT') || str_starts_with($lu, 'RES') || str_contains($lu, 'AGRIC') || str_starts_with($lu, 'AG')) {
            $termYears = 99;
        } elseif (str_contains($lu, 'COMMERC') || str_starts_with($lu, 'COM') || str_contains($lu, 'INDUSTR') || str_starts_with($lu, 'IND')) {
            $termYears = 40;
        }
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
        $isKangis = function ($value): bool {
            $v = trim((string) $value);
            return $v !== '' && (bool) preg_match('/^[A-Z]{4}\s?\d{3,6}$/i', $v);
        };

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
            if ($relatedMls && strcasecmp(trim($relatedMls), trim($searchedFileNo)) !== 0) {
                $display .= ' (' . $relatedMls . ')';
            } elseif ($kangisNumber && strcasecmp(trim($kangisNumber), trim($searchedFileNo)) !== 0) {
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
        // Only attempt for KANGIS legacy prefixes; normal MLS searches short-circuit here.
        if ($fileNo === '' || !preg_match('/^(MLKN|KNML|KNGP)\s?\d{1,6}$/i', $fileNo)) {
            return null;
        }

        $key = strtoupper(preg_replace('/\s+/', '', $fileNo));                 // "MLKN2455"
        $keyNoZero = preg_replace('/^([A-Z]+)0*(\d+)$/', '$1$2', $key);         // strip zero-pad
        $isKangis = fn ($v) => (bool) preg_match('/^(MLKN|KNML|KNGP)\s?\d/i', trim((string) $v));

        $pickMls = function ($candidates) use ($isKangis, $fileNo): ?string {
            foreach ($candidates as $cand) {
                $cand = trim((string) $cand);
                if ($cand !== '' && !$isKangis($cand) && strcasecmp($cand, $fileNo) !== 0) {
                    return $cand;
                }
            }
            return null;
        };

        // 1) PropID_Master — mother row carrying this KANGIS alias.
        try {
            $pm = $conn->table('PropID_Master')
                ->whereRaw("UPPER(REPLACE(LTRIM(RTRIM(ISNULL(kangisFileNo,''))),' ','')) IN (?, ?)", [$key, $keyNoZero])
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

        return null;
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
            $activeParent = $conn->table('file_indexings')
                ->whereNull('deleted_at')
                ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(LTRIM(RTRIM(ISNULL(related_fileno, ''))), '/', '-'), '=', '-'), '_', '-')) LIKE ?", ['%' . $normalizedFileNo . '%'])
                ->first(['file_number', 'related_fileno']);
            
            if ($activeParent && !str_starts_with(strtoupper($activeParent->file_number), 'ST-')) {
                $decoded = $parseRelatedFilenos($activeParent->related_fileno);
                if (!empty($decoded)) {
                    $isSme = true;
                    $allowed[] = trim($activeParent->file_number);
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
}
