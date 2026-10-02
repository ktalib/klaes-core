<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\PlotWorkflowService;
use App\Services\PropertyIdAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ManualFileLinkageController extends Controller
{
    public function index()
    {
        $linkages = DB::connection('sqlsrv')
            ->table('manual_file_linkages')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $states     = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas       = DB::connection('sqlsrv')->table('lgas')->where('is_active', 1)->orderBy('name')->get();
        $districts  = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = DB::connection('sqlsrv')->table('street_names')->orderBy('name')->get(['id', 'name']);

        // Distinct holding file numbers already in use — feeds the "Continue an existing chain" dropdown
        $holdingFiles = Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'holding_file_no')
            ? DB::connection('sqlsrv')->table('manual_file_linkages')
                ->whereNotNull('holding_file_no')
                ->where('holding_file_no', '!=', '')
                ->orderByDesc('created_at')
                ->pluck('holding_file_no')
                ->unique()
                ->values()
            : collect();

        return view('admin.manual_linkage.index', compact('linkages', 'states', 'lgas', 'districts', 'streetNames', 'holdingFiles'));
    }

    /**
     * AJAX: Validate a file number and return its details including decommission + already-linked status.
     */
    public function searchOldFile(Request $request)
    {
        $fileNumber = strtoupper(trim((string) $request->input('file_number')));

        if (empty($fileNumber)) {
            return response()->json(['error' => 'File number is required.'], 400);
        }

        $indexing    = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNumber)->first();
        $fileNoRecord = DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fileNumber)->first();

        if (!$indexing && !$fileNoRecord) {
            // Check if it already sits in the decommissioned archive
            $decommissioned = DB::connection('sqlsrv')->table('decommissioned_files')
                ->where('mls_file_no', $fileNumber)
                ->orderBy('decommissioning_date', 'desc')
                ->first();

            if ($decommissioned) {
                return response()->json([
                    'exists'                 => false,
                    'is_decommissioned'      => true,
                    'decommissioning_reason' => $decommissioned->decommissioning_reason ?? null,
                    'message'                => "File {$fileNumber} is already decommissioned"
                        . ($decommissioned->decommissioning_date
                            ? ' (archived ' . \Carbon\Carbon::parse($decommissioned->decommissioning_date)->format('d/m/Y') . ')'
                            : '')
                        . ($decommissioned->decommissioning_reason ? ': ' . $decommissioned->decommissioning_reason : '.'),
                ]);
            }

            return response()->json(['exists' => false, 'message' => 'File number not found in active records.']);
        }

        // Transaction counts across staging tables. Each table stores the file number
        // under a different column, so resolve the right one and never let a single
        // missing table/column break the whole verification.
        $cofoCount = $this->countByFileNumber('CofO_staging', ['mlsFNo', 'file_number'], $fileNumber);
        $praCount  = $this->countByFileNumber('pra', ['mlsFNo', 'fileno', 'file_number'], $fileNumber);
        $deedTable = Schema::connection('sqlsrv')->hasTable('deeds_registrations') ? 'deeds_registrations' : 'deed_registrations';
        $deedCount = $this->countByFileNumber($deedTable, ['fileno', 'parent_fileno', 'file_number'], $fileNumber);

        // Check if this file was already used as a source in a previous manual linkage
        $existingLinkage = DB::connection('sqlsrv')
            ->table('manual_file_linkages')
            ->whereRaw("old_file_numbers LIKE ?", ['%"' . $fileNumber . '"%'])
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'exists'           => true,
            'file_number'      => $indexing->file_number ?? $fileNoRecord->mlsfNo ?? $fileNumber,
            'file_title'       => $indexing->file_title ?? $fileNoRecord->FileName ?? 'N/A',
            'land_use'         => $indexing->land_use_type ?? 'N/A',
            'plot_number'      => $indexing->plot_number ?? 'N/A',
            'district'         => $indexing->district ?? 'N/A',
            'lga'              => $indexing->lga ?? 'N/A',
            'location'         => $indexing->location ?? 'N/A',
            'prop_id'          => $indexing->prop_id ?? 'None',
            'is_decommissioned' => false,
            'existing_linkage'  => $existingLinkage ? [
                'workflow_type'  => $existingLinkage->workflow_type,
                'new_file_number' => $existingLinkage->new_file_number,
                'date'           => \Carbon\Carbon::parse($existingLinkage->created_at)->format('d/m/Y'),
                'processed_by'   => $existingLinkage->processed_by,
            ] : null,
            'transactions' => [
                'cofo'  => $cofoCount,
                'pra'   => $praCount,
                'deeds' => $deedCount,
                'total' => $cofoCount + $praCount + $deedCount,
            ],
        ]);
    }

    /**
     * Persist the linkage, decommission old files, patch lineage on new files.
     * For Subdivision, accepts a children[] array so all N child plots are linked in one transaction.
     */
    public function store(Request $request)
    {
        $workflowType = $request->input('workflow_type');

        // Strip empty values submitted by hidden inputs in non-active panels
        $request->merge([
            'old_file_numbers' => array_values(array_filter(
                $request->input('old_file_numbers', []),
                fn($v) => trim((string) $v) !== ''
            )),
        ]);

        // --- Validation -------------------------------------------------------
        $baseRules = [
            'workflow_type'      => 'required|in:Subdivision,Merger,Plot Extension,Change of Purpose',
            // Not hard-required: a "finalize chain" save derives its legacy files from the
            // selected holding chain instead of a manual pick. Enforced manually below.
            'old_file_numbers'   => 'nullable|array',
            'old_file_numbers.*' => 'nullable|string',
            'applicant_name'     => 'nullable|string|max:255',
            'file_title'         => 'nullable|string|max:500',
            'approval_reference' => 'nullable|string|max:255',
            'approval_date'      => 'nullable|date',
            'remarks'            => 'nullable|string',
            'plot_number'        => 'nullable|string|max:255',
            'plot_size'          => 'nullable|string|max:255',
            'land_use_type'      => 'nullable|string|max:255',
            'land_value'         => 'nullable|numeric',
            'knupda_fee'         => 'nullable|numeric',
            'plan_no'            => 'nullable|string|max:255',
            'house_no'           => 'nullable|string|max:255',
            'street_name'        => 'nullable|string|max:255',
            'district'           => 'nullable|string|max:255',
            'lga'                => 'nullable|string|max:255',
            'state'              => 'nullable|string|max:255',
            'location'           => 'nullable|string|max:2000',
            'land_size'          => 'nullable|numeric',
            'purpose'            => 'nullable|string|max:50',
            // File numbers picked that are NOT in the indexing system — recorded as
            // related file numbers only (no decommission, no indexing row created).
            'unindexed_file_numbers'   => 'nullable|array',
            'unindexed_file_numbers.*' => 'nullable|string',
            // Temporary holding file: a logical container number that ties one transaction
            // chain (merger → subdivision → change of purpose) to a supporting prop_id.
            'use_holding_file'   => 'nullable|boolean',
            'holding_action'     => 'nullable|in:new,continue',
            'holding_file_no'    => 'nullable|string|max:100',
        ];

        if ($workflowType === 'Subdivision') {
            $baseRules['children']                      = 'required|array|min:1';
            $baseRules['children.*.new_file_number']    = 'required|string';
            $baseRules['children.*.file_title']         = 'nullable|string|max:500';
            $baseRules['children.*.plot_number']        = 'nullable|string|max:100';
            $baseRules['children.*.location']           = 'nullable|string|max:2000';
            $baseRules['children.*.plot_size']          = 'nullable|string|max:100';
            $baseRules['children.*.survey_plan_no']     = 'nullable|string|max:255';
        } else {
            $baseRules['new_file_number'] = 'required|string';
        }

        $validated = $request->validate($baseRules);

        // --- Normalize inputs -------------------------------------------------
        $oldFileNumbers = array_values(array_unique(array_map(
            fn ($f) => strtoupper(trim((string) $f)),
            $validated['old_file_numbers'] ?? []
        )));

        // Un-indexed file numbers: recorded as related only, never decommissioned/indexed
        $unindexedFiles = array_values(array_unique(array_map(
            fn ($f) => strtoupper(trim((string) $f)),
            array_filter($validated['unindexed_file_numbers'] ?? [], fn ($v) => trim((string) $v) !== '')
        )));

        $applicantName    = trim($validated['applicant_name'] ?? '');
        $fileTitle        = trim($validated['file_title'] ?? '');
        $remarks          = trim($validated['remarks'] ?? '');
        $approvalReference = trim($validated['approval_reference'] ?? '');
        $approvalDate     = $validated['approval_date'] ?? null;

        // Normalize children for Subdivision
        $children = [];
        if ($workflowType === 'Subdivision') {
            foreach ($validated['children'] as $child) {
                $children[] = [
                    'new_file_number' => strtoupper(trim($child['new_file_number'])),
                    'file_title'      => trim($child['file_title'] ?? ''),
                    'plot_number'     => trim($child['plot_number'] ?? ''),
                    'location'        => trim($child['location'] ?? ''),
                    'plot_size'       => trim($child['plot_size'] ?? ''),
                    'survey_plan_no'  => trim($child['survey_plan_no'] ?? ''),
                ];
            }
            // Guard: at least one child must be an indexed file (un-indexed children are
            // saved as related file numbers and need an indexed sibling to attach to).
            $allChildNumbers = array_column($children, 'new_file_number');
            if (empty(array_diff($allChildNumbers, $unindexedFiles))) {
                return back()
                    ->withErrors(['error' => 'At least one child plot must be an indexed file. '
                        . 'Un-indexed children are recorded as related file numbers and need an indexed child to link to.'])
                    ->withInput();
            }

            // Guard: no child file can equal the parent
            foreach ($children as $child) {
                if (in_array($child['new_file_number'], $oldFileNumbers, true)) {
                    return back()
                        ->withErrors(['error' =>
                            "The file {$child['new_file_number']} is selected as both the parent (source) file and a child plot. "
                            . "In a Subdivision the parent is the original plot that gets decommissioned, and each child must be a different, "
                            . "newly-created file. Pick the correct mother plot on the left, or choose a different child file — they cannot be the same number."
                        ])
                        ->withInput();
                }
            }
        } else {
            $newFileNumber = strtoupper(trim($validated['new_file_number']));
            if (in_array($newFileNumber, $oldFileNumbers, true)) {
                return back()
                    ->withErrors(['new_file_number' =>
                        "The file {$newFileNumber} is selected as both the legacy source file and the processed destination file. "
                        . "The source file is the old file that gets decommissioned, and the destination is the already-processed new file — "
                        . "they must be different file numbers."
                    ])
                    ->withInput();
            }
        }

        // Activation flag: when ON, this is an OPEN chain — the legacy/source files stay
        // active and may recur across multiple workflows, so the "already linked"
        // duplicate guard below is intentionally skipped.
        $useHoldingFile = (bool) ($validated['use_holding_file'] ?? false);

        // --- Duplicate guard --------------------------------------------------
        // Reject if any old file is already recorded as a source in manual_file_linkages.
        // Only enforced for FINAL (non-activated) workflows — an active chain reuses files.
        if (!$useHoldingFile) {
            $alreadyLinked = DB::connection('sqlsrv')
                ->table('manual_file_linkages')
                ->where(function ($q) use ($oldFileNumbers) {
                    foreach ($oldFileNumbers as $fn) {
                        $q->orWhereRaw("old_file_numbers LIKE ?", ['%"' . $fn . '"%']);
                    }
                })
                ->get(['workflow_type', 'new_file_number', 'old_file_numbers', 'created_at']);

            if ($alreadyLinked->isNotEmpty()) {
                $conflicts = $alreadyLinked->map(fn ($l) =>
                    implode(', ', json_decode($l->old_file_numbers, true) ?? [$l->old_file_numbers])
                    . ' → ' . $l->new_file_number
                    . ' (' . $l->workflow_type . ' on '
                    . \Carbon\Carbon::parse($l->created_at)->format('d/m/Y') . ')'
                )->implode('; ');

                return back()
                    ->withErrors(['error' => 'One or more source files are already linked: ' . $conflicts])
                    ->withInput();
            }
        }

        // --- Temporary holding file -------------------------------------------
        // When activation is ON the linkage runs under a temporary holding file number
        // that ties the whole transaction chain to one supporting prop_id. It is a
        // logical identifier only (no indexing row).
        $holdingFileNo  = null;

        if ($useHoldingFile) {
            // Supporting file must already be indexed. Its prop_id becomes the chain's
            // master reference — if it doesn't have one yet, it is auto-allocated downstream
            // (same as the normal linkage flow).
            if ($workflowType === 'Subdivision') {
                $supportingFile = $oldFileNumbers[0] ?? null;
            } else {
                $supportingFile = $newFileNumber;
            }
            $supportingIndexing = $supportingFile
                ? DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $supportingFile)->first()
                : null;

            if (!$supportingIndexing) {
                $label = $workflowType === 'Subdivision' ? 'parent' : 'supporting (destination)';
                return back()
                    ->withErrors(['error' =>
                        "Holding file mode requires the {$label} file ({$supportingFile}) to already be indexed. "
                        . 'Index that file first, or turn off the holding file option.'])
                    ->withInput();
            }

            $holdingAction = $validated['holding_action'] ?? 'new';

            if ($holdingAction === 'continue') {
                $holdingFileNo = strtoupper(trim($validated['holding_file_no'] ?? ''));
                if ($holdingFileNo === '') {
                    return back()
                        ->withErrors(['error' => 'Select or enter the existing holding file number to continue the transaction chain.'])
                        ->withInput();
                }
                $chainExists = DB::connection('sqlsrv')->table('manual_file_linkages')
                    ->where('holding_file_no', $holdingFileNo)->exists();
                if (!$chainExists) {
                    return back()
                        ->withErrors(['error' => "Holding file {$holdingFileNo} was not found in any prior linkage. "
                            . 'Check the number, or choose "Start a new holding file".'])
                        ->withInput();
                }
            } else {
                // new: generate a fresh holding number (ignore any submitted value)
                $holdingFileNo = $this->generateHoldingFileNo();
            }
        }

        // --- Finalize chain detection -----------------------------------------
        // Closing step: Activation ON + "Continue existing chain" + a holding number
        // selected + NO legacy file picked. The legacy files are then derived from the
        // chain and decommissioned — ALL of them EXCEPT the supporting/destination file,
        // which stays active. Lets the user finalize without a "missing file" error.
        $isFinalizeChain = $useHoldingFile
            && ($validated['holding_action'] ?? '') === 'continue'
            && $holdingFileNo
            && empty($oldFileNumbers)
            && $workflowType !== 'Subdivision';

        if ($isFinalizeChain) {
            $chainRows = DB::connection('sqlsrv')->table('manual_file_linkages')
                ->where('holding_file_no', $holdingFileNo)
                ->get(['new_file_number', 'old_file_numbers']);

            $chainFiles = [];
            foreach ($chainRows as $r) {
                if (!empty($r->new_file_number)) {
                    $chainFiles[] = strtoupper(trim($r->new_file_number));
                }
                foreach ((json_decode($r->old_file_numbers, true) ?: []) as $of) {
                    $of = strtoupper(trim((string) $of));
                    if ($of !== '') {
                        $chainFiles[] = $of;
                    }
                }
            }
            // Everything in the chain EXCEPT the supporting/destination file.
            $oldFileNumbers = array_values(array_diff(array_unique($chainFiles), [$newFileNumber]));
        } elseif ($workflowType !== 'Subdivision' && empty($oldFileNumbers)) {
            // A normal (non-finalize) non-subdivision linkage still needs a legacy file.
            return back()
                ->withErrors(['error' => 'Add at least one legacy/old file number, or finalize an existing chain.'])
                ->withInput();
        }

        // --- Main transaction -------------------------------------------------
        DB::connection('sqlsrv')->beginTransaction();

        try {
            $commissionedBy = Auth::user()->first_name . ' ' . Auth::user()->last_name;
            // Prefer an indexed source as the reference file (un-indexed ones have no record)
            $indexedOldFiles = array_values(array_diff($oldFileNumbers, $unindexedFiles));
            $firstOldFile    = $indexedOldFiles[0] ?? ($oldFileNumbers[0] ?? null);

            // Fetch details of the first source file BEFORE decommissioning deletes it
            $oldIndexing = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where('file_number', $firstOldFile)
                ->first();

            // Collect old prop_ids BEFORE decommission removes the active records
            $oldPropIds = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('file_number', $oldFileNumbers)
                ->whereNotNull('prop_id')
                ->pluck('prop_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($oldPropIds) && !empty($oldIndexing->prop_id)) {
                $oldPropIds = [(int) $oldIndexing->prop_id];
            }

            // Collect each source file's current owner (its party_2/grantee) BEFORE
            // decommission removes the active file_indexings rows. For a Merger these
            // become the grantor (party_1) on the new merged file — the people who owned
            // the plots being merged. Falls back to file_title when no holder is recorded.
            $oldHolders = DB::connection('sqlsrv')
                ->table('file_indexings')
                ->whereIn('file_number', $oldFileNumbers)
                ->get(['current_holder', 'file_title'])
                ->map(fn ($r) => trim((string) ($r->current_holder ?: $r->file_title ?: '')))
                ->filter(fn ($v) => $v !== '')
                ->unique()
                ->values()
                ->all();

            // Decommission only the INDEXED source files (un-indexed ones have no
            // record to decommission — they are captured as related file numbers).
            //
            // When the Supporting/Holding chain is ACTIVE, the chain is still open: the
            // legacy/source files must stay active so later workflows can reference them.
            // Decommissioning only happens on the FINAL workflow (activation OFF).
            $filesToDecommission = array_values(array_diff($oldFileNumbers, $unindexedFiles));
            $workflowService = app(PlotWorkflowService::class);
            // The old -> successor lineage pointer stored on decommissioned_files.successor_file_no
            // (read back by the Decommissioned Files list's Related File column and by Legal
            // Search's lineage resolver). A batch subdivision retires the mother into several
            // children at once, so this may be a CSV list.
            $successorFileNo = $workflowType === 'Subdivision'
                ? implode(', ', array_column($children, 'new_file_number'))
                : $newFileNumber;
            $decommReason = $workflowType === 'Subdivision'
                ? 'Subdivision → ' . $successorFileNo
                : "Manual Linkage: {$workflowType} → {$newFileNumber}";
            // Anything the service could not retire is reported, never swallowed: it archives
            // per file inside its own try/catch, so a failure here used to leave the source
            // file live while the linkage still saved and the page still said "decommissioned".
            $decommissionWarnings = [];
            if (($isFinalizeChain || !$useHoldingFile) && !empty($filesToDecommission)) {
                $decommSummary = $workflowService->decommissionFiles($filesToDecommission, $decommReason, $commissionedBy, $successorFileNo, true);
                $missed = array_values(array_diff($filesToDecommission, $decommSummary['archived'] ?? []));
                if (!empty($missed) || !empty($decommSummary['errors'])) {
                    $decommissionWarnings = array_values(array_filter(array_merge(
                        $missed ? ['Not decommissioned: ' . implode(', ', $missed)] : [],
                        $decommSummary['errors'] ?? []
                    )));
                    Log::warning('Manual linkage decommission incomplete', [
                        'workflow_type' => $workflowType,
                        'requested'     => $filesToDecommission,
                        'archived'      => $decommSummary['archived'] ?? [],
                        'errors'        => $decommSummary['errors'] ?? [],
                    ]);
                }
            }

            $allocationService = app(PropertyIdAllocationService::class);
            $linkageGroupId    = Str::uuid()->toString();

            // ----------------------------------------------------------------
            if ($workflowType === 'Subdivision') {
                // The mother's prop_id is what every child hangs off (parent_prop_id). When the
                // mother was never indexed — or its indexing row carried no prop_id — this used
                // to fall through as null and the whole subdivision lost its lineage: the
                // children pointed at nothing and the mother showed a blank PropID on the
                // Decommissioned Files list. Mint the mother a prop_id (and its PropID_Master
                // row) instead, so the parcel it stands for keeps an identity after retirement.
                $parentPropId = $oldPropIds[0] ?? null;
                if ($parentPropId === null && $firstOldFile) {
                    try {
                        $parentPropId = $allocationService->allocateOrRetrievePropId($firstOldFile, $firstOldFile);
                    } catch (\Throwable $e) {
                        Log::warning('Manual linkage: could not allocate a parent prop_id for subdivision mother', [
                            'mother_file' => $firstOldFile,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
                // Parent (subdividing) plot holder — becomes the Grantor on each child's PRA row.
                $parentHolder = $oldIndexing->current_holder
                    ?? ($oldIndexing->file_title ?? null);
                // PRA comment, shared by every child row.
                // e.g. "Plot Subdivision: 3 Plots Subdivided from RES-RC-1982-731"
                $subdivisionComment = 'Plot Subdivision: ' . count($children)
                    . ' Plots Subdivided from ' . $firstOldFile;

                // Un-indexed children are recorded as related file numbers only
                $unindexedChildNumbers = array_values(array_intersect(
                    array_column($children, 'new_file_number'),
                    $unindexedFiles
                ));
                // Each processed child's lineage = parent source(s) + un-indexed siblings
                $childRelatedFilenos = array_values(array_unique(array_merge($oldFileNumbers, $unindexedChildNumbers)));
                $firstIndexedChildId     = null;
                $firstIndexedChildNumber = null;
                $firstIndexedChildPropId = null;

                foreach ($children as $childData) {
                    $childFileNumber  = $childData['new_file_number'];

                    // Skip un-indexed children — they get no indexing row, only related links
                    if (in_array($childFileNumber, $unindexedFiles, true)) {
                        continue;
                    }

                    $childNewIndexing = DB::connection('sqlsrv')
                        ->table('file_indexings')
                        ->where('file_number', $childFileNumber)
                        ->first();

                    $childPropId = $childNewIndexing->prop_id ?? $allocationService->allocateOrRetrievePropId(
                        $childFileNumber,
                        $childFileNumber,
                        null,
                        null,
                        ['skip_lookup' => true, 'temp_fileno' => $firstOldFile]
                    );

                    // Per-child title (entered/auto-filled in the child row) drives holder + title
                    $childTitle = $childData['file_title']
                        ?: ($childNewIndexing->file_title ?? ($oldIndexing->file_title ?? 'Manual Linkage Result'));

                    // Resolved property address for this child (used for both file_indexings.location
                    // and the PRA row's location + property_description).
                    $childLocation = $childData['location']
                        ?: ($request->input('location')
                            ?: ($childNewIndexing->location ?? ($oldIndexing->location ?? null)));

                    // file_indexings row for this child
                    $indexingPayload = [
                        'file_number'      => $childFileNumber,
                        'file_title'       => $childTitle,
                        'land_use_type'    => $request->input('land_use_type')
                            ?: ($childNewIndexing->land_use_type ?? ($oldIndexing->land_use_type ?? 'N/A')),
                        'plot_number'      => $childData['plot_number']
                            ?: ($childNewIndexing->plot_number ?? ($oldIndexing->plot_number ?? 'N/A')),
                        'district'         => $request->input('district')
                            ?: ($childNewIndexing->district ?? ($oldIndexing->district ?? null)),
                        'lga'              => $request->input('lga')
                            ?: ($childNewIndexing->lga ?? ($oldIndexing->lga ?? null)),
                        'location'         => $childLocation,
                        'plot_size'        => $childData['plot_size']
                            ?: ($childNewIndexing->plot_size ?? null),
                        'tp_no'            => $childNewIndexing->tp_no ?? ($oldIndexing->tp_no ?? null),
                        'lpkn_no'          => $childNewIndexing->lpkn_no ?? ($oldIndexing->lpkn_no ?? null),
                        'tracking_id'      => $childNewIndexing->tracking_id ?? ($oldIndexing->tracking_id ?? null),
                        'original_holder'  => $childNewIndexing->original_holder
                            ?? ($oldIndexing->original_holder ?? ($childTitle ?: null)),
                        'current_holder'   => $childTitle
                            ?: ($childNewIndexing->current_holder ?? ($oldIndexing->current_holder ?? null)),
                        'parent_prop_id'   => $parentPropId,
                        'related_fileno'   => json_encode($childRelatedFilenos),
                        'has_transaction'  => 1,
                        'prop_id'          => $childPropId,
                        'general_registry' => $childNewIndexing->general_registry
                            ?? ($oldIndexing->general_registry ?? 'MLS'),
                        'updated_at'       => now(),
                    ];

                    if ($childNewIndexing) {
                        DB::connection('sqlsrv')->table('file_indexings')
                            ->where('id', $childNewIndexing->id)
                            ->update($indexingPayload + ['updated_by' => $commissionedBy]);
                        $childIndexingId = $childNewIndexing->id;
                    } else {
                        // file_indexings.created_by is NOT NULL with no default — omitting it
                        // aborts the whole linkage transaction, which is how a run can leave
                        // fileNumber/PRA/PropID_Master rows behind with no indexing row.
                        $indexingPayload['created_at'] = now();
                        $indexingPayload['created_by'] = $commissionedBy;
                        $indexingPayload['updated_by'] = $commissionedBy;
                        $childIndexingId = DB::connection('sqlsrv')->table('file_indexings')->insertGetId($indexingPayload);
                    }

                    if ($firstIndexedChildId === null) {
                        $firstIndexedChildId     = $childIndexingId;
                        $firstIndexedChildNumber = $childFileNumber;
                        $firstIndexedChildPropId = $childPropId;
                    }

                    // fileNumber row for this child
                    $childFileNoRecord = DB::connection('sqlsrv')
                        ->table('fileNumber')
                        ->where('mlsfNo', $childFileNumber)
                        ->first();

                    $fileNumberPayload = [
                        'mlsfNo'              => $childFileNumber,
                        'FileName'            => $childTitle
                            ?: ($childFileNoRecord->FileName ?? ($oldIndexing->file_title ?? 'Manual Linkage Result')),
                        'tracking_id'         => $childFileNoRecord->tracking_id ?? ($oldIndexing->tracking_id ?? null),
                        'commissioning_date'  => $childFileNoRecord->commissioning_date ?? now(),
                        'updated_at'          => now(),
                    ];
                    if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'parent_prop_id')) {
                        $fileNumberPayload['parent_prop_id'] = $parentPropId;
                    }
                    if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'related_fileno')) {
                        $fileNumberPayload['related_fileno'] = json_encode($childRelatedFilenos);
                    }

                    if ($childFileNoRecord) {
                        DB::connection('sqlsrv')->table('fileNumber')
                            ->where('id', $childFileNoRecord->id)
                            ->update($fileNumberPayload);
                    } else {
                        $fileNumberPayload['created_at'] = now();
                        DB::connection('sqlsrv')->table('fileNumber')->insert($fileNumberPayload);
                    }

                    // PRA transaction for this child (carry the holding number so the chain is traceable).
                    // Grantor = parent (subdividing) holder, Grantee = this child's holder.
                    // party_1/party_2 mirror Grantor/Grantee for table views that read the generic columns.
                    $childGrantee = $childTitle ?: null;
                    DB::connection('sqlsrv')->table('pra')->insert([
                        'prop_id'              => $childPropId,
                        'mlsFNo'               => $childFileNumber,
                        'title_type'           => 'Subdivision',
                        'transaction_type'     => 'Plot Subdivision',
                        'property_description' => $childLocation,
                        'location'             => $childLocation,
                        'temp_fileno'          => $holdingFileNo,
                        'Grantor'              => $parentHolder,
                        'Grantee'              => $childGrantee,
                        'party_1'              => $parentHolder,
                        'party_2'              => $childGrantee,
                        'parent_prop_id'       => $parentPropId,
                        'comments'             => $subdivisionComment,
                        // Legacy back-captured transaction — the real instrument date is
                        // unknown, so leave it blank. Stamping the processing date would
                        // make a years-old subdivision display as a 2026 transaction.
                        'transaction_date'     => null,
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);

                    // Audit row in manual_file_linkages for this child
                    $this->insertLinkageRow(
                        'Subdivision',
                        $oldFileNumbers,
                        $childFileNumber,
                        $childPropId,
                        $childTitle ?: ($oldIndexing->file_title ?? 'N/A'),
                        $remarks,
                        $commissionedBy,
                        $approvalReference,
                        $approvalDate,
                        $childData['plot_number'] ?: null,
                        $childData['plot_size'] ?: null,
                        $childData['survey_plan_no'] ?: null,
                        $linkageGroupId,
                        $holdingFileNo
                    );
                }

                // Record un-indexed children as related file numbers (attached to the first indexed child)
                $this->recordRelatedFileNumbers(
                    $firstIndexedChildId,
                    $firstIndexedChildNumber ?? '',
                    $unindexedChildNumbers,
                    $firstIndexedChildPropId,
                    'Subdivision'
                );

                app(AuditService::class)->logAction(
                    'MANUAL_LINKAGE',
                    'manual_file_linkages',
                    0,
                    ['old_files' => $oldFileNumbers],
                    [
                        'children'          => array_column($children, 'new_file_number'),
                        'unindexed_related' => $unindexedChildNumbers,
                        'workflow_type'     => 'Subdivision',
                        'linkage_group_id'  => $linkageGroupId,
                    ],
                    "Manually linked Subdivision from {$firstOldFile} to "
                        . count($children) . ' child plot(s)'
                );

                DB::connection('sqlsrv')->commit();
                $this->clearCache();

                $childCount = count($children);
                if (!empty($decommissionWarnings)) {
                    return redirect()->route('admin.manual-linkage.index')
                        ->with('warning', "Subdivision linkage saved: {$firstOldFile} → {$childCount} child plot(s) linked, "
                            . 'but the parent file was NOT decommissioned — ' . implode('; ', $decommissionWarnings));
                }

                return redirect()->route('admin.manual-linkage.index')
                    ->with('success', "Subdivision linkage saved: {$firstOldFile} → {$childCount} child plot(s) decommissioned and linked.");

            } else {
                // --- Merger / Plot Extension / Change of Purpose -------------
                $newIndexing      = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $newFileNumber)->first();
                $existingNewPropId = $newIndexing->prop_id ?? null;

                $propId = $existingNewPropId ?: $allocationService->allocateOrRetrievePropId(
                    $newFileNumber,
                    $newFileNumber,
                    null,
                    null,
                    ['temp_fileno' => $useHoldingFile ? $holdingFileNo : $firstOldFile]
                );

                // Best-effort: stamp the holding number onto the supporting file's PropID_Master
                // row (backfills temp_fileno only if NULL, so it never clobbers or mints anew).
                if ($useHoldingFile && $holdingFileNo) {
                    try {
                        $allocationService->allocateOrRetrievePropId(
                            $newFileNumber,
                            $newFileNumber,
                            null,
                            null,
                            ['temp_fileno' => $holdingFileNo]
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Holding file master-link backfill failed', [
                            'holding_file_no' => $holdingFileNo,
                            'file_number'     => $newFileNumber,
                            'error'           => $e->getMessage(),
                        ]);
                    }
                }

                $parentPropId = !empty($oldPropIds)
                    ? implode(',', $oldPropIds)
                    : ($oldIndexing->prop_id ?? null);

                // Resolved property address for the destination file (used for both
                // file_indexings.location and the PRA row's location + property_description).
                $destLocation = $request->input('location')
                    ?: ($newIndexing->location ?? ($oldIndexing->location ?? null));

                $indexingPayload = [
                    'file_number'      => $newFileNumber,
                    'file_title'       => $newIndexing->file_title
                        ?? ($fileTitle ?: ($applicantName ?: ($oldIndexing ? $oldIndexing->file_title : 'Manual Linkage Result'))),
                    'land_use_type'    => $request->input('land_use_type')
                        ?: ($newIndexing->land_use_type ?? ($oldIndexing->land_use_type ?? 'N/A')),
                    'plot_number'      => $request->input('plot_number')
                        ?: ($newIndexing->plot_number ?? ($oldIndexing->plot_number ?? 'N/A')),
                    'district'         => $request->input('district')
                        ?: ($newIndexing->district ?? ($oldIndexing->district ?? null)),
                    'lga'              => $request->input('lga')
                        ?: ($newIndexing->lga ?? ($oldIndexing->lga ?? null)),
                    'location'         => $destLocation,
                    'plot_size'        => $request->input('plot_size')
                        ?: ($newIndexing->plot_size ?? ($oldIndexing->plot_size ?? null)),
                    'tp_no'            => $newIndexing->tp_no ?? ($oldIndexing->tp_no ?? null),
                    'lpkn_no'          => $newIndexing->lpkn_no ?? ($oldIndexing->lpkn_no ?? null),
                    'tracking_id'      => $newIndexing->tracking_id ?? ($oldIndexing->tracking_id ?? null),
                    'original_holder'  => $newIndexing->original_holder
                        ?? ($oldIndexing->original_holder ?? ($applicantName ?: null)),
                    'current_holder'   => $applicantName
                        ?: ($newIndexing->current_holder ?? ($oldIndexing->current_holder ?? null)),
                    'parent_prop_id'   => $parentPropId,
                    'related_fileno'   => json_encode($oldFileNumbers),
                    'has_transaction'  => 1,
                    'prop_id'          => $propId,
                    'general_registry' => $newIndexing->general_registry
                        ?? ($oldIndexing->general_registry ?? 'MLS'),
                    'updated_at'       => now(),
                ];

                if ($newIndexing) {
                    DB::connection('sqlsrv')->table('file_indexings')
                        ->where('id', $newIndexing->id)
                        ->update($indexingPayload + ['updated_by' => $commissionedBy]);
                    $newIndexingId = $newIndexing->id;
                } else {
                    // See the Subdivision branch: created_by is NOT NULL with no default.
                    $indexingPayload['created_at'] = now();
                    $indexingPayload['created_by'] = $commissionedBy;
                    $indexingPayload['updated_by'] = $commissionedBy;
                    $newIndexingId = DB::connection('sqlsrv')->table('file_indexings')->insertGetId($indexingPayload);
                }

                // Record un-indexed source files (Merger) as related file numbers on the destination
                $unindexedSources = array_values(array_intersect($oldFileNumbers, $unindexedFiles));
                $this->recordRelatedFileNumbers($newIndexingId, $newFileNumber, $unindexedSources, $propId, $workflowType);

                $newFileNumberRecord = DB::connection('sqlsrv')
                    ->table('fileNumber')
                    ->where('mlsfNo', $newFileNumber)
                    ->first();

                $fileNumberPayload = [
                    'mlsfNo'             => $newFileNumber,
                    'FileName'           => $fileTitle
                        ?: ($applicantName ?: ($newFileNumberRecord->FileName ?? ($oldIndexing ? $oldIndexing->file_title : 'Manual Linkage Result'))),
                    'tracking_id'        => $newFileNumberRecord->tracking_id ?? ($oldIndexing->tracking_id ?? null),
                    'commissioning_date' => $newFileNumberRecord->commissioning_date ?? now(),
                    'updated_at'         => now(),
                ];
                if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'parent_prop_id')) {
                    $fileNumberPayload['parent_prop_id'] = $parentPropId;
                }
                if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'related_fileno')) {
                    $fileNumberPayload['related_fileno'] = json_encode($oldFileNumbers);
                }

                if ($newFileNumberRecord) {
                    DB::connection('sqlsrv')->table('fileNumber')
                        ->where('id', $newFileNumberRecord->id)
                        ->update($fileNumberPayload);
                } else {
                    $fileNumberPayload['created_at'] = now();
                    DB::connection('sqlsrv')->table('fileNumber')->insert($fileNumberPayload);
                }

                // Only collapse the source prop_ids into the destination on the FINAL
                // workflow (activation off, or finalizing an open chain). While the chain
                // is active the sources stay independent.
                if (($isFinalizeChain || !$useHoldingFile) && in_array($workflowType, ['Merger', 'Plot Extension', 'Change of Purpose'], true) && !empty($oldPropIds)) {
                    $workflowService->updateHistoricalPropId($oldPropIds, (int) $propId);
                }

                // Grantor = source/parent holder(s), Grantee = destination (new) holder.
                // party_1/party_2 mirror Grantor/Grantee for views that read the generic columns.
                // For a Merger the grantor is the combined current owners (party_2) of ALL the
                // merged source files, not just the first — otherwise party_1 collapses to a
                // single name and reads the same as the destination owner.
                $destGrantor = !empty($oldHolders)
                    ? $this->joinNames($oldHolders)
                    : ($oldIndexing->current_holder ?? ($oldIndexing->file_title ?? null));
                $destGrantee = $applicantName
                    ?: ($newIndexing->current_holder ?? ($newIndexing->file_title ?? null));

                // PRA comment per workflow type:
                //  Merger             -> "Plot Merger: A and B; NEW"
                //  Change of Purpose  -> "Old File Number: OLD New File Number: NEW"
                //  Plot Extension     -> "Plot Extension: A and B; NEW"
                if ($workflowType === 'Merger') {
                    $praComment = 'Plot Merger: ' . implode(' and ', $oldFileNumbers) . '; ' . $newFileNumber;
                } elseif ($workflowType === 'Change of Purpose') {
                    $praComment = 'Old File Number: ' . implode(', ', $oldFileNumbers)
                        . ' New File Number: ' . $newFileNumber;
                } elseif ($workflowType === 'Plot Extension') {
                    $praComment = 'Plot Extension: ' . implode(' and ', $oldFileNumbers) . '; ' . $newFileNumber;
                } else {
                    $praComment = "Manual Processed: {$workflowType} linkage";
                }

                DB::connection('sqlsrv')->table('pra')->insert([
                    'prop_id'              => $propId,
                    'mlsFNo'               => $newFileNumber,
                    'title_type'           => $workflowType,
                    'transaction_type'     => $workflowType,
                    'property_description' => $destLocation,
                    'location'             => $destLocation,
                    'temp_fileno'          => $holdingFileNo,
                    'Grantor'              => $destGrantor,
                    'Grantee'              => $destGrantee,
                    'party_1'              => $destGrantor,
                    'party_2'              => $destGrantee,
                    'parent_prop_id'       => $parentPropId,
                    'comments'             => $praComment,
                    // Legacy back-captured transaction — the real instrument date is
                    // unknown, so leave it blank. Stamping the processing date would
                    // make a years-old merger/CoP display as a 2026 transaction.
                    'transaction_date'     => null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                $linkageId = $this->insertLinkageRow(
                    $workflowType,
                    $oldFileNumbers,
                    $newFileNumber,
                    $propId,
                    $applicantName ?: ($oldIndexing ? $oldIndexing->file_title : 'N/A'),
                    $remarks,
                    $commissionedBy,
                    $approvalReference,
                    $approvalDate,
                    null, null, null,
                    $linkageGroupId,
                    $holdingFileNo
                );

                app(AuditService::class)->logAction(
                    'MANUAL_LINKAGE',
                    'manual_file_linkages',
                    $linkageId,
                    ['old_files' => $oldFileNumbers],
                    ['new_file' => $newFileNumber, 'workflow_type' => $workflowType, 'prop_id' => $propId],
                    "Manually linked {$workflowType} from " . implode(', ', $oldFileNumbers) . " to {$newFileNumber}"
                );

                DB::connection('sqlsrv')->commit();
                $this->clearCache();

                if (!empty($decommissionWarnings)) {
                    return redirect()->route('admin.manual-linkage.index')
                        ->with('warning', "Linkage saved for {$workflowType}, but not every source file was decommissioned — "
                            . implode('; ', $decommissionWarnings));
                }

                return redirect()->route('admin.manual-linkage.index')
                    ->with('success', "Successfully linked manually processed files for {$workflowType}!");
            }

        } catch (\Throwable $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('Manual file linkage failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withErrors(['error' => 'An error occurred during linkage: ' . $e->getMessage()])
                ->withInput();
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Join owner names into a human-readable list:
     *   1 name  -> "A"
     *   2 names -> "A and B"
     *   3+      -> "A, B and C"
     * Used for the merger grantor (party_1), which is the combined owners of the merged plots.
     */
    private function joinNames(array $names): string
    {
        $names = array_values(array_filter(
            array_map(fn ($n) => trim((string) $n), $names),
            fn ($n) => $n !== ''
        ));

        if (empty($names)) {
            return '';
        }
        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);
        return implode(', ', $names) . ' and ' . $last;
    }

    private function insertLinkageRow(
        string  $workflowType,
        array   $oldFileNumbers,
        string  $newFileNumber,
        mixed   $propId,
        string  $applicantName,
        string  $remarks,
        string  $commissionedBy,
        string  $approvalReference,
        ?string $approvalDate,
        ?string $childPlotNumber,
        ?string $childPlotSize,
        ?string $surveyPlanNo,
        string  $linkageGroupId,
        ?string $holdingFileNo = null
    ): int {
        $row = [
            'workflow_type'    => $workflowType,
            'old_file_numbers' => json_encode($oldFileNumbers),
            'new_file_number'  => $newFileNumber,
            'prop_id'          => $propId,
            'applicant_name'   => $applicantName,
            'remarks'          => $remarks,
            'processed_by'     => $commissionedBy,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        $optional = [
            'approval_reference' => $approvalReference ?: null,
            'approval_date'      => $approvalDate ?: null,
            'child_plot_number'  => $childPlotNumber,
            'child_plot_size'    => $childPlotSize,
            'survey_plan_no'     => $surveyPlanNo,
            'linkage_group_id'   => $linkageGroupId,
            'holding_file_no'    => $holdingFileNo,
        ];

        foreach ($optional as $col => $value) {
            if (Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', $col)) {
                $row[$col] = $value;
            }
        }

        return (int) DB::connection('sqlsrv')->table('manual_file_linkages')->insertGetId($row);
    }

    /**
     * Generate a unique temporary holding file number (TEMP-#####).
     * Re-rolls if the number already exists in a prior linkage or the PropID_Master.
     */
    private function generateHoldingFileNo(): string
    {
        do {
            $candidate = 'TEMP-' . mt_rand(10000, 99999);

            $taken = DB::connection('sqlsrv')->table('manual_file_linkages')
                ->where('holding_file_no', $candidate)->exists();

            if (!$taken && Schema::connection('sqlsrv')->hasTable('PropID_Master')) {
                $taken = DB::connection('sqlsrv')->table('PropID_Master')
                    ->where('temp_fileno', $candidate)->exists();
            }
        } while ($taken);

        return $candidate;
    }

    /**
     * AJAX: Validate an existing holding file number and return its transaction chain
     * (supporting file, prop_id, and the workflow stages already recorded under it).
     * Powers the "Continue existing chain" lookup in the modal.
     */
    public function searchHoldingFile(Request $request)
    {
        $holdingFileNo = strtoupper(trim((string) $request->input('holding_file_no')));

        if ($holdingFileNo === '') {
            return response()->json(['error' => 'Holding file number is required.'], 400);
        }

        $stages = DB::connection('sqlsrv')->table('manual_file_linkages')
            ->where('holding_file_no', $holdingFileNo)
            ->orderBy('created_at')
            ->get(['workflow_type', 'new_file_number', 'prop_id', 'created_at']);

        if ($stages->isEmpty()) {
            return response()->json([
                'exists'  => false,
                'message' => "Holding file {$holdingFileNo} was not found in any prior linkage.",
            ]);
        }

        return response()->json([
            'exists'          => true,
            'holding_file_no' => $holdingFileNo,
            'supporting_file' => $stages->first()->new_file_number,
            'prop_id'         => $stages->first()->prop_id,
            'stages'          => $stages->map(fn ($s) => [
                'workflow_type'   => $s->workflow_type,
                'new_file_number' => $s->new_file_number,
                'prop_id'         => $s->prop_id,
                'date'            => \Carbon\Carbon::parse($s->created_at)->format('d/m/Y'),
            ])->values(),
        ]);
    }

    /**
     * Download a CSV template for bulk file import.
     *  - subdivision → Child Plot Files columns
     *  - merger      → Source Plot Files columns
     * Only `file_number` is required; the rest are optional and back-filled from the
     * indexed record when left blank (same as the manual selection flow).
     */
    public function downloadCsvTemplate(Request $request)
    {
        $mode = $request->query('mode') === 'merger' ? 'merger' : 'subdivision';

        if ($mode === 'merger') {
            $headers = ['file_number', 'plot_no', 'house_no', 'street_name', 'district', 'lga', 'state', 'plot_size'];
            $sample  = ['RES-RC-2019-123', '45', '12', '', '', '', 'Kano', '0.50'];
            $fileName = 'merger_source_plots_template.csv';
        } else {
            $headers = ['file_number', 'file_title', 'plot_number', 'location', 'plot_size', 'survey_plan_no'];
            $sample  = ['RES-RC-2019-123', 'JOHN DOE', '45A', '', '', ''];
            $fileName = 'subdivision_child_plots_template.csv';
        }

        $callback = function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, $sample);
            fclose($out);
        };

        return response()->streamDownload($callback, $fileName, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Bulk-validate an uploaded CSV of child/source plot files and return per-row results.
     * Mirrors the manual selection validation: indexed files are back-filled from their
     * record, un-indexed files are still accepted (recorded as related file numbers on
     * save), decommissioned files are rejected, and duplicate rows are flagged.
     */
    public function bulkImportCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
            'mode'     => 'required|in:subdivision,merger',
        ]);

        $mode = $request->input('mode');
        $path = $request->file('csv_file')->getRealPath();

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['error' => 'Unable to read the uploaded file.'], 422);
        }

        // Header row → normalized column index map (lowercased, spaces/dashes → underscore)
        $rawHeader = fgetcsv($handle);
        if ($rawHeader === false) {
            fclose($handle);
            return response()->json(['error' => 'The CSV file is empty.'], 422);
        }
        $colMap = [];
        foreach ($rawHeader as $i => $name) {
            $key = preg_replace('/[\s\-]+/', '_', strtolower(trim((string) $name)));
            if ($key !== '') {
                $colMap[$key] = $i;
            }
        }

        // Aliases so common header spellings still resolve
        $col = function (array $row, array $candidates) use ($colMap) {
            foreach ($candidates as $c) {
                if (isset($colMap[$c]) && isset($row[$colMap[$c]])) {
                    $v = trim((string) $row[$colMap[$c]]);
                    if ($v !== '') {
                        return $v;
                    }
                }
            }
            return null;
        };

        $rows    = [];
        $seen    = [];
        $rowNo   = 1; // header was row 1
        $summary = ['total' => 0, 'imported' => 0, 'indexed' => 0, 'unindexed' => 0, 'duplicates' => 0, 'invalid' => 0];

        while (($data = fgetcsv($handle)) !== false) {
            $rowNo++;
            // Skip fully blank lines
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $summary['total']++;

            $fileNumber = strtoupper((string) ($col($data, ['file_number', 'fileno', 'file_no', 'mls_file_no', 'mlsfno']) ?? ''));

            if ($fileNumber === '') {
                $summary['invalid']++;
                $rows[] = ['row' => $rowNo, 'file_number' => '', 'status' => 'invalid', 'message' => 'Missing file number.'];
                continue;
            }

            // Duplicate within this upload
            if (isset($seen[$fileNumber])) {
                $summary['duplicates']++;
                $rows[] = ['row' => $rowNo, 'file_number' => $fileNumber, 'status' => 'duplicate', 'message' => 'Duplicate of row ' . $seen[$fileNumber] . '.'];
                continue;
            }
            $seen[$fileNumber] = $rowNo;

            $indexing     = DB::connection('sqlsrv')->table('file_indexings')->where('file_number', $fileNumber)->first();
            $fileNoRecord = $indexing ? null : DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $fileNumber)->first();

            // Reject already-decommissioned files (archived — cannot be reused)
            if (!$indexing && !$fileNoRecord) {
                $decommissioned = DB::connection('sqlsrv')->table('decommissioned_files')
                    ->where('mls_file_no', $fileNumber)
                    ->orderBy('decommissioning_date', 'desc')
                    ->first();
                if ($decommissioned) {
                    $summary['invalid']++;
                    $rows[] = [
                        'row'         => $rowNo,
                        'file_number' => $fileNumber,
                        'status'      => 'invalid',
                        'message'     => 'Already decommissioned'
                            . ($decommissioned->decommissioning_reason ? ': ' . $decommissioned->decommissioning_reason : '.'),
                    ];
                    continue;
                }
            }

            // CSV-supplied overrides (fall back to the indexed record when blank)
            $csvPlotNumber = $col($data, ['plot_number', 'plot_no', 'plot']);
            $csvFileTitle  = $col($data, ['file_title', 'title', 'holder', 'applicant_name']);
            $csvPlotSize   = $col($data, ['plot_size', 'size']);
            $csvSurveyPlan = $col($data, ['survey_plan_no', 'survey_plan', 'plan_no']);
            $csvHouseNo    = $col($data, ['house_no', 'house']);
            $csvStreet     = $col($data, ['street_name', 'street']);
            $csvDistrict   = $col($data, ['district']);
            $csvLga        = $col($data, ['lga']);
            $csvState      = $col($data, ['state']);
            $csvLocation   = $col($data, ['location', 'address']);

            $indexed = (bool) $indexing;

            if ($indexed) {
                $recordLocation = $indexing->location ?? null;
                $builtLocation  = $csvLocation
                    ?: ($recordLocation
                        ?: implode(', ', array_filter([
                            $csvPlotNumber ?: ($indexing->plot_number ?? null),
                            $csvStreet, $csvDistrict ?: ($indexing->district ?? null),
                            $csvLga ?: ($indexing->lga ?? null), $csvState,
                        ], fn ($v) => $v && $v !== 'N/A')));

                $details = [
                    'file_title'     => $csvFileTitle  ?: ($indexing->file_title ?? ''),
                    'plot_number'    => $csvPlotNumber ?: ($indexing->plot_number ?? ''),
                    'house_no'       => $csvHouseNo    ?: '',
                    'street_name'    => $csvStreet     ?: '',
                    'district'       => $csvDistrict   ?: ($indexing->district ?? ''),
                    'lga'            => $csvLga         ?: ($indexing->lga ?? ''),
                    'state'          => $csvState       ?: '',
                    'location'       => $builtLocation,
                    'plot_size'      => $csvPlotSize   ?: ($indexing->plot_size ?? ''),
                    'survey_plan_no' => $csvSurveyPlan ?: '',
                    'land_use'       => $indexing->land_use_type ?? '',
                    'prop_id'        => $indexing->prop_id ?? '',
                ];
                $summary['indexed']++;
            } else {
                // Un-indexed → still accepted, recorded as a related file number on save
                $details = [
                    'file_title'     => $csvFileTitle  ?: '',
                    'plot_number'    => $csvPlotNumber ?: '',
                    'house_no'       => $csvHouseNo    ?: '',
                    'street_name'    => $csvStreet     ?: '',
                    'district'       => $csvDistrict   ?: '',
                    'lga'            => $csvLga         ?: '',
                    'state'          => $csvState       ?: '',
                    'location'       => $csvLocation   ?: implode(', ', array_filter([$csvStreet, $csvDistrict, $csvLga, $csvState])),
                    'plot_size'      => $csvPlotSize   ?: '',
                    'survey_plan_no' => $csvSurveyPlan ?: '',
                    'land_use'       => '',
                    'prop_id'        => '',
                ];
                $summary['unindexed']++;
            }

            $summary['imported']++;
            $rows[] = [
                'row'         => $rowNo,
                'file_number' => $fileNumber,
                'status'      => $indexed ? 'indexed' : 'unindexed',
                'message'     => $indexed ? 'Verified indexed file.' : 'Not indexed — will be saved as a related file number.',
                'indexed'     => $indexed,
                'details'     => $details,
            ];
        }

        fclose($handle);

        if ($summary['total'] === 0) {
            return response()->json(['error' => 'No data rows found in the CSV (only a header was detected).'], 422);
        }

        return response()->json(['mode' => $mode, 'summary' => $summary, 'rows' => $rows]);
    }

    /**
     * Record un-indexed file numbers in the related_file_number table, attached to the
     * surviving/destination indexing record. One row per destination (the unique
     * (source_table, source_id) constraint allows a single row per source record), with
     * the related file numbers comma-joined.
     */
    private function recordRelatedFileNumbers(?int $destIndexingId, string $destFileNumber, array $relatedNumbers, $propId, string $workflowType): void
    {
        $relatedNumbers = array_values(array_filter(array_unique(array_map('trim', $relatedNumbers)), fn ($v) => $v !== ''));
        if ($destIndexingId === null || empty($relatedNumbers)) {
            return;
        }
        if (!Schema::connection('sqlsrv')->hasTable('related_file_number')) {
            return;
        }

        $row = [
            'related_fileno' => mb_substr(implode(', ', $relatedNumbers), 0, 500),
            'prop_id'        => ($propId !== null && $propId !== '') ? (string) $propId : null,
            'file_number'    => $destFileNumber,
            'updated_at'     => now(),
        ];
        $optional = [
            'transaction_type' => $workflowType,
            'comment'          => 'Manual linkage: un-indexed related file number(s)',
        ];
        foreach ($optional as $col => $value) {
            if (Schema::connection('sqlsrv')->hasColumn('related_file_number', $col)) {
                $row[$col] = $value;
            }
        }

        $match    = ['source_table' => 'file_indexings', 'source_id' => $destIndexingId];
        $existing = DB::connection('sqlsrv')->table('related_file_number')->where($match)->first();

        try {
            if ($existing) {
                DB::connection('sqlsrv')->table('related_file_number')->where('id', $existing->id)->update($row);
            } else {
                DB::connection('sqlsrv')->table('related_file_number')->insert($row + $match + ['created_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning('recordRelatedFileNumbers failed', ['error' => $e->getMessage(), 'dest' => $destFileNumber]);
        }
    }

    /**
     * Count rows in $table matching $fileNumber, using the first $candidateCols
     * that actually exists on the table. Returns 0 if the table or all columns
     * are missing, so a schema mismatch never 500s the verify endpoint.
     */
    private function countByFileNumber(string $table, array $candidateCols, string $fileNumber): int
    {
        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return 0;
        }

        $column = null;
        foreach ($candidateCols as $col) {
            if (Schema::connection('sqlsrv')->hasColumn($table, $col)) {
                $column = $col;
                break;
            }
        }
        if ($column === null) {
            return 0;
        }

        try {
            return (int) DB::connection('sqlsrv')->table($table)->where($column, $fileNumber)->count();
        } catch (\Throwable $e) {
            Log::warning("countByFileNumber failed for {$table}.{$column}", ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private function clearCache(): void
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
        } catch (\Throwable $e) {
            // non-fatal
        }
    }
}
