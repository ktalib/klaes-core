<?php

namespace App\Http\Controllers;

use App\Models\ConsentApplication;
use App\Models\DeedsApplication;
use App\Models\LandUseType;
use App\Models\Purpose;
use App\Models\StreetName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DeedsApplicationController extends Controller
{
    public function index(): View
    {
        // The table is filled page by page from data(); the page itself only
        // carries the counts and what the New Application form needs.
        $consent = ConsentApplication::where('application_type', 'application_for_censent');
        $typeCounts = (clone $consent)
            ->selectRaw('consent_type, COUNT(*) as n')
            ->groupBy('consent_type')
            ->pluck('n', 'consent_type');

        $stRows = $this->stAssignmentRows();

        $counts = [
            'total' => $typeCounts->sum() + $stRows->count(),
            'today' => (clone $consent)->whereDate('created_at', now()->toDateString())->count()
                + $stRows->filter(fn ($r) => $r->created_at->isToday())->count(),
            'Assignment' => (int) ($typeCounts['Assignment'] ?? 0),
            'ST Assignment' => $stRows->count(),
            'Gift' => (int) ($typeCounts['Gift'] ?? 0),
            'Mortgage' => (int) ($typeCounts['Mortgage'] ?? 0),
        ];

        $consentTypes = $typeCounts->keys()
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->when($stRows->isNotEmpty(), fn ($c) => $c->push('ST Assignment'))
            ->unique()
            ->sort()
            ->values();

        $states = DB::connection('sqlsrv')->table('States')->orderBy('StateName')->get();
        $lgas = DB::connection('sqlsrv')
            ->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $streetNames = StreetName::orderBy('name')->get(['id', 'name'])->toBase();
        $landUseTypes = LandUseType::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $purposes = Purpose::query()->orderBy('name')->get(['id', 'landuseid', 'name']);

        return view('deeds_applications.index', compact('counts', 'consentTypes', 'states', 'lgas', 'districts', 'streetNames', 'landUseTypes', 'purposes'));
    }

    /**
     * One page of the applications table, in the DataTables server-side shape.
     *
     * The list is two sources merged: consent applications and the ST
     * Assignments raised from approved ST memos. Only the sort key of each row is
     * read for the whole list; the full record is loaded for the page shown.
     */
    public function data(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = trim((string) $request->input('search.value', ''));
        $typeFilter = trim((string) $request->input('consent_type', ''));
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);

        // Sortable columns by DataTables column name. Party 3 and the workflow
        // strip are derived per row and are not sortable.
        $sortable = [
            'file_number' => 'file_number',
            'consent_type' => 'consent_type',
            'party1' => 'applicant_name',
            'party2' => 'party_name',
            'created_by' => 'created_by',
            'app_date' => 'app_date',
            'time' => 'created_at',
            'date' => 'created_at',
            'prints' => 'print_count',
        ];
        $orderIdx = $request->input('order.0.column');
        $orderName = $orderIdx !== null ? $request->input("columns.$orderIdx.name") : null;
        $sortKey = $sortable[$orderName] ?? 'created_at';
        $sortDesc = $orderName === null || strtolower((string) $request->input('order.0.dir', 'desc')) === 'desc';

        $consent = ConsentApplication::where('application_type', 'application_for_censent');
        $recordsTotal = (clone $consent)->count();

        if ($typeFilter !== '') {
            $consent->where('consent_type', $typeFilter);
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $consent->where(function ($q) use ($like) {
                foreach (['file_number', 'consent_type', 'applicant_name', 'party_name', 'created_by',
                          'additional_applicants', 'additional_parties', 'additional_properties'] as $col) {
                    $q->orWhere($col, 'like', $like);
                }
            });
        }

        $keys = $consent->get(['id', 'file_number', 'consent_type', 'applicant_name', 'party_name', 'created_by',
                               'application_date', 'application_submitted_date', 'print_count', 'created_at'])
            ->map(fn ($a) => (object) [
                'kind' => 'consent',
                'id' => $a->id,
                'file_number' => strtoupper((string) $a->file_number),
                'consent_type' => (string) $a->consent_type,
                'applicant_name' => strtoupper((string) $a->applicant_name),
                'party_name' => strtoupper((string) $a->party_name),
                'created_by' => strtoupper((string) $a->created_by),
                'app_date' => (string) ($a->application_submitted_date ?? $a->application_date ?? ''),
                'print_count' => (int) $a->print_count,
                'created_at' => (string) $a->created_at,
            ]);

        $stRows = $this->stAssignmentRows();
        $recordsTotal += $stRows->count();

        $stKeys = $stRows
            ->filter(function ($st) use ($typeFilter, $search) {
                if ($typeFilter !== '' && strcasecmp($typeFilter, 'ST Assignment') !== 0) {
                    return false;
                }
                if ($search === '') {
                    return true;
                }
                $haystack = implode(' ', [$st->file_number, 'ST Assignment', $st->applicant_name, $st->party_name,
                    $st->created_by, collect($st->st_units)->pluck('party2_name')->implode(' ')]);
                return stripos($haystack, $search) !== false;
            })
            ->map(fn ($st) => (object) [
                'kind' => 'st',
                'id' => $st->id,
                'file_number' => strtoupper((string) $st->file_number),
                'consent_type' => 'ST Assignment',
                'applicant_name' => (string) $st->applicant_name,
                'party_name' => (string) $st->party_name,
                'created_by' => (string) $st->created_by,
                'app_date' => (string) $st->application_date,
                'print_count' => 0,
                'created_at' => (string) $st->created_at,
                'row' => $st,
            ]);

        $all = $keys->concat($stKeys)->values();
        $all = $sortDesc
            ? $all->sortByDesc($sortKey, SORT_NATURAL | SORT_FLAG_CASE)->values()
            : $all->sortBy($sortKey, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $recordsFiltered = $all->count();

        $page = $length < 0 ? $all->slice($start) : $all->slice($start, $length);

        // Full records for the page only.
        $consentIds = $page->where('kind', 'consent')->pluck('id')->all();
        $full = $consentIds
            ? ConsentApplication::with('user')->whereIn('id', $consentIds)->get()->keyBy('id')
            : collect();
        $registered = $this->registeredInInstrument($full);

        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))->map(fn ($r) => trim($r))->filter();
        $isSupperAdmin = $assignRoles->contains(fn ($r) => strcasecmp($r, 'Supper Admin') === 0);

        $rows = [];
        $sn = $start;
        foreach ($page as $key) {
            $sn++;
            if ($key->kind === 'st') {
                $application = $key->row;
            } else {
                $application = $full->get($key->id);
                if (!$application) {
                    continue;
                }
                $application->applicant_name = strtoupper((string) $application->applicant_name);
                $application->party_name = strtoupper((string) $application->party_name);
                $application->is_registered_in_instrument = $registered[$application->id] ?? false;
            }

            $html = view('deeds_applications.partials._table_row_cells', [
                'application' => $application,
                'sn' => $sn,
                'isSupperAdmin' => $isSupperAdmin,
            ])->render();

            $cells = array_map('trim', explode('<!--cell-->', $html));
            array_shift($cells); // text before the first marker
            $rows[] = array_merge($cells, [
                'DT_RowAttr' => ['data-id' => (string) $application->id, 'data-file-no' => (string) $application->file_number],
            ]);
        }

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    /**
     * Whether each application's dealing is already in Instrument Capture, which
     * locks it against further edits. Looked up for the given rows only.
     */
    private function registeredInInstrument($applications): array
    {
        $files = $applications->pluck('file_number')
            ->map(fn ($f) => trim((string) $f))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (!$files) {
            return [];
        }

        // Chunked: each file number is bound twice, and sqlsrv caps a statement
        // at 2,100 parameters ("Show All" asks for every row).
        $instrumentMap = [];
        foreach (array_chunk($files, 900) as $chunk) {
            $instruments = DB::connection('sqlsrv')
                ->table('instrument_capture')
                ->where(function ($q) {
                    $q->where('is_deleted', 0)->orWhereNull('is_deleted');
                })
                ->where(function ($q) use ($chunk) {
                    $q->whereIn('mlsFNo', $chunk)->orWhereIn('temp_fileno', $chunk);
                })
                ->get(['mlsFNo', 'temp_fileno', 'instrument_type']);

            foreach ($instruments as $inst) {
                $instType = strtolower(trim($inst->instrument_type ?? ''));
                foreach ([$inst->mlsFNo, $inst->temp_fileno] as $no) {
                    if (!empty($no)) {
                        $instrumentMap[strtolower(trim($no))][] = $instType;
                    }
                }
            }
        }

        $result = [];
        foreach ($applications as $app) {
            $result[$app->id] = false;
            $fileKey = strtolower(trim((string) $app->file_number));
            if ($fileKey === '' || !isset($instrumentMap[$fileKey])) {
                continue;
            }
            $consentType = strtolower(trim($app->consent_type ?? ''));
            foreach ($instrumentMap[$fileKey] as $instType) {
                if (str_contains($instType, $consentType) || str_contains($consentType, $instType)) {
                    $result[$app->id] = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * ST Assignments, one per approved mother application with a generated
     * primary / physical-planning memo, shaped like a ConsentApplication so the
     * table renders both alike.
     */
    private function stAssignmentRows()
    {
        $stMemos = DB::connection('sqlsrv')
            ->table('memos')
            ->join('mother_applications', 'memos.application_id', '=', 'mother_applications.id')
            ->leftJoin('users', 'memos.created_by', '=', 'users.id')
            ->where('mother_applications.application_status', 'Approved')
            ->where(function($query) {
                $query->where('memos.memo_status', 'GENERATED')
                      ->orWhereNull('memos.memo_status')
                      ->orWhere('memos.memo_status', '');
            })
            ->whereIn(DB::raw('LOWER(memos.memo_type)'), ['primary', 'physical_planning', 'physical planning'])
            ->select(
                'memos.id as memo_id',
                'memos.created_at',
                'mother_applications.id as mother_id',
                'mother_applications.fileno as file_number',
                'mother_applications.applicant_title',
                'mother_applications.first_name',
                'mother_applications.surname',
                'mother_applications.corporate_name',
                'memos.created_by',
                'users.first_name as user_first_name',
                'users.last_name as user_last_name'
            )
            ->orderBy('memos.created_at', 'desc')
            ->get()
            ->unique('mother_id');

        $motherIds = $stMemos->pluck('mother_id')->all();
        if (!$motherIds) {
            return collect();
        }

        // Units for every mother at once: buyer_list first (standard for ST memos),
        // subapplications for a mother that has no buyers.
        $buyersByMother = DB::connection('sqlsrv')
            ->table('buyer_list')
            ->leftJoin('st_file_numbers', 'buyer_list.id', '=', 'st_file_numbers.buyer_list_id')
            ->whereIn('buyer_list.application_id', $motherIds)
            ->orderBy('buyer_list.id')
            ->get(['buyer_list.application_id', 'buyer_list.buyer_title', 'buyer_list.buyer_name', 'buyer_list.unit_no', 'st_file_numbers.fileno as unit_fileno'])
            ->groupBy('application_id');

        $withoutBuyers = array_values(array_diff($motherIds, $buyersByMother->keys()->all()));
        $subsByMother = $withoutBuyers
            ? DB::connection('sqlsrv')
                ->table('subapplications')
                ->leftJoin('st_file_numbers', 'subapplications.id', '=', 'st_file_numbers.subapplication_id')
                ->whereIn('subapplications.main_application_id', $withoutBuyers)
                ->orderBy('subapplications.id')
                ->get(['subapplications.main_application_id', 'subapplications.applicant_title', 'subapplications.first_name', 'subapplications.surname', 'subapplications.corporate_name', 'subapplications.unit_number', 'st_file_numbers.fileno as unit_fileno'])
                ->groupBy('main_application_id')
            : collect();

        return $stMemos->map(function ($memo) use ($buyersByMother, $subsByMother) {
            $party1 = strtoupper($memo->corporate_name ?: trim("{$memo->applicant_title} {$memo->first_name} {$memo->surname}"));

            $unitsData = collect($buyersByMother->get($memo->mother_id, []))->map(fn ($buyer) => [
                'unit_number' => $buyer->unit_no,
                'party2_name' => strtoupper(trim("{$buyer->buyer_title} {$buyer->buyer_name}")),
                'unit_fileno' => $buyer->unit_fileno,
            ]);
            if ($unitsData->isEmpty()) {
                $unitsData = collect($subsByMother->get($memo->mother_id, []))->map(fn ($sub) => [
                    'unit_number' => $sub->unit_number,
                    'party2_name' => strtoupper($sub->corporate_name ?: trim("{$sub->applicant_title} {$sub->first_name} {$sub->surname}")),
                    'unit_fileno' => $sub->unit_fileno,
                ]);
            }

            $party2 = 'N/A';
            if ($unitsData->count() > 0) {
                $firstUnitName = $unitsData->first()['party2_name'];
                $party2 = $unitsData->count() > 1
                    ? "{$firstUnitName} & (" . ($unitsData->count() - 1) . ") Others"
                    : $firstUnitName;
            }

            $stApp = new ConsentApplication();
            $stApp->setAttribute('id', 'memo_' . $memo->memo_id);
            $stApp->setAttribute('file_number', $memo->file_number);
            $stApp->setAttribute('consent_type', 'ST Assignment');
            $stApp->setAttribute('applicant_name', $party1);
            $stApp->setAttribute('party_name', $party2);
            $stApp->setAttribute('application_date', $memo->created_at);
            $stApp->setAttribute('created_at', \Carbon\Carbon::parse($memo->created_at));
            $stApp->setAttribute('print_count', 0);
            $stApp->setAttribute('status', 'Approved');
            $stApp->setAttribute('created_by', strtoupper(trim("{$memo->user_first_name} {$memo->user_last_name}")) ?: $memo->created_by);
            $stApp->setAttribute('st_units', $unitsData->values()->toArray());
            $stApp->setAttribute('is_st_assignment', true);
            $stApp->setAttribute('mother_party1', $party1);
            $stApp->setAttribute('memo_id', $memo->memo_id);

            return $stApp;
        })->values();
    }

    public function create(): View
    {
        return view('deeds_applications.create', [
            'application' => new DeedsApplication(['status' => DeedsApplication::defaultStatus()]),
            'statusOptions' => DeedsApplication::STATUS_OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $application = DeedsApplication::create($this->validatedData($request));

        return redirect()->route('deeds-applications.show', $application)->with('status', 'Application created.');
    }

    public function show(DeedsApplication $deedsApplication): View
    {
        return view('deeds_applications.show', ['application' => $deedsApplication]);
    }

    public function edit(DeedsApplication $deedsApplication): View
    {
        return view('deeds_applications.edit', [
            'application' => $deedsApplication,
            'statusOptions' => DeedsApplication::STATUS_OPTIONS,
        ]);
    }

    public function update(Request $request, DeedsApplication $deedsApplication): RedirectResponse
    {
        $deedsApplication->update($this->validatedData($request));

        return redirect()->route('deeds-applications.show', $deedsApplication)->with('status', 'Application updated.');
    }

    public function destroy(DeedsApplication $deedsApplication): RedirectResponse
    {
        $deedsApplication->delete();

        return redirect()->route('deeds-applications.index')->with('status', 'Application deleted.');
    }

    protected function validatedData(Request $request): array
    {
        return $request->validate([
            'application_number' => ['required', 'string', 'max:50'],
            'file_number' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:150'],
            'status' => ['required', 'string', 'in:' . implode(',', array_keys(DeedsApplication::STATUS_OPTIONS))],
            'submitted_by' => ['nullable', 'string', 'max:120'],
            'submitted_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    public function deleteMaster(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        // Enforce role permission Supper Admin
        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
            ->map(fn($r) => trim($r))
            ->filter();
        $isSupperAdmin = $assignRoles->contains(fn($r) => strcasecmp($r, 'Supper Admin') === 0);

        if (!$isSupperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $application = ConsentApplication::findOrFail($id);
            $fileNo = $application->file_number;

            // Formally log the action
            if (class_exists('\App\Services\AuditService')) {
                app(\App\Services\AuditService::class)->logAction(
                    'Deeds Master Delete',
                    'consent_applications',
                    $application->id,
                    null,
                    null,
                    "Deleted deeds consent application for file: {$fileNo}"
                );
            }

            // Physically delete the application
            $application->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Master record deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete master record: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resetPrintMaster(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        // Enforce role permission Supper Admin
        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
            ->map(fn($r) => trim($r))
            ->filter();
        $isSupperAdmin = $assignRoles->contains(fn($r) => strcasecmp($r, 'Supper Admin') === 0);

        if (!$isSupperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $application = ConsentApplication::findOrFail($id);
            $fileNo = $application->file_number;
            $previousPrintCount = $application->print_count;

            $application->print_count = 0;
            $application->save();

            // Formally log the action
            if (class_exists('\App\Services\AuditService')) {
                app(\App\Services\AuditService::class)->logAction(
                    'Deeds Master Print Reset',
                    'consent_applications',
                    $application->id,
                    null,
                    null,
                    "Reset print count from {$previousPrintCount} to 0 for file: {$fileNo}"
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Print count reset successfully. Editing is now allowed.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset print count: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteMasterBulk(Request $request): \Illuminate\Http\JsonResponse
    {
        // Enforce role permission Supper Admin
        $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
            ->map(fn($r) => trim($r))
            ->filter();
        $isSupperAdmin = $assignRoles->contains(fn($r) => strcasecmp($r, 'Supper Admin') === 0);

        if (!$isSupperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.'
            ], 403);
        }

        $ids = $request->input('ids');
        if (empty($ids) || !is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid IDs provided.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $deletedCount = 0;
            foreach ($ids as $id) {
                $application = ConsentApplication::find($id);
                if ($application) {
                    $fileNo = $application->file_number;

                    // Formally log the action
                    if (class_exists('\App\Services\AuditService')) {
                        app(\App\Services\AuditService::class)->logAction(
                            'Deeds Master Delete Bulk',
                            'consent_applications',
                            $application->id,
                            null,
                            null,
                            "Deleted deeds consent application for file: {$fileNo} (Bulk)"
                        );
                    }

                    $application->delete();
                    $deletedCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} master record(s)."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete bulk deletion: ' . $e->getMessage()
            ], 500);
        }
    }
}
