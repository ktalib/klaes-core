<?php

namespace App\Http\Controllers;

// LandUseType replaced by klas.dbo.land_uses direct query
use App\Models\SpaApplication;
use App\Models\SpaFieldData;
use App\Models\SpaNotice;
use App\Models\SpaDepartmentReferral;
use App\Models\SpaMemo;
use App\Models\SpaCertificate;
use App\Services\BulkSmsNgService;
use App\Services\SpaMobileService;
use App\Services\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SpecialAssignmentController extends Controller
{
    // ─── PAGE METHODS ──────────────────────────────────────────────────────────

    /**
     * Which registry a file_indexings row belongs to, as SQL.
     *
     * `file_indexings.registry` is unusable on its own — it holds batch ordinals
     * ('1','2','3') as often as names — so this leans on `general_registry` and
     * the file-number prefix, which are consistent across the whole table.
     * Order matters: KANGIS is tested before ST so a KN* file never falls through.
     */
    private const REGISTRY_CASE_SQL = <<<'SQL'
        CASE
            WHEN UPPER(LTRIM(fi.file_number)) LIKE 'SLTR%'
                 OR UPPER(ISNULL(fi.general_registry, '')) LIKE '%SLTR%' THEN 'SLTR'
            WHEN UPPER(LTRIM(fi.file_number)) LIKE 'KN%'
                 OR UPPER(LTRIM(fi.file_number)) LIKE 'MLKN%'
                 OR UPPER(ISNULL(fi.general_registry, '')) LIKE '%KANGIS%' THEN 'KANGIS'
            WHEN UPPER(LTRIM(fi.file_number)) LIKE 'ST-%'
                 OR UPPER(LTRIM(fi.file_number)) LIKE 'ST/%'
                 OR UPPER(ISNULL(fi.general_registry, '')) LIKE 'ST %' THEN 'ST'
            ELSE 'MLS'
        END
        SQL;

    /** Narrow a land-records query to one registry (see REGISTRY_CASE_SQL for the rules). */
    private function applyRegistryFilter($query, string $registry)
    {
        switch (strtoupper($registry)) {
            case 'SLTR':
                return $query->where(function ($q) {
                    $q->whereRaw("UPPER(LTRIM(fi.file_number)) LIKE 'SLTR%'")
                      ->orWhereRaw("UPPER(ISNULL(fi.general_registry, '')) LIKE '%SLTR%'");
                });
            case 'KANGIS':
                return $query->where(function ($q) {
                    $q->whereRaw("UPPER(LTRIM(fi.file_number)) LIKE 'KN%'")
                      ->orWhereRaw("UPPER(LTRIM(fi.file_number)) LIKE 'MLKN%'")
                      ->orWhereRaw("UPPER(ISNULL(fi.general_registry, '')) LIKE '%KANGIS%'");
                });
            case 'ST':
                return $query->where(function ($q) {
                    $q->whereRaw("UPPER(LTRIM(fi.file_number)) LIKE 'ST-%'")
                      ->orWhereRaw("UPPER(LTRIM(fi.file_number)) LIKE 'ST/%'")
                      ->orWhereRaw("UPPER(ISNULL(fi.general_registry, '')) LIKE 'ST %'");
                });
            case 'MLS':
                return $query->whereRaw('('.self::REGISTRY_CASE_SQL.") = 'MLS'");
            default:
                return $query;
        }
    }

    public function landRecords(Request $request)
    {
        if ($request->ajax() || $request->input('ajax')) {
            $search   = $request->input('search.value', $request->input('search', ''));
            $start    = (int) $request->input('start', 0);
            $length   = (int) $request->input('length', 25);
            $registry = trim((string) $request->input('registry', ''));

            $base = DB::connection('sqlsrv')
                ->table('file_indexings as fi')
                ->leftJoin('fileNumber as fn', 'fn.tracking_id', '=', 'fi.tracking_id')
                ->leftJoin('spa_applications as spa', 'spa.file_number', '=', 'fi.file_number')
                ->whereNotNull('fi.file_number')
                ->where('fi.file_number', 'not like', 'DCIV%')
                ->where('fi.file_number', 'not like', 'DC/%')
                ->select(
                    'fi.id as file_indexing_id', 'fi.file_number', 'fi.land_use_type',
                    'fi.location', 'fi.district', 'fi.lga', 'fi.phone', 'fi.tracking_id',
                    'fi.current_holder', 'fn.FileName as file_title',
                    'spa.id as spa_id', 'spa.owner_name as spa_owner', 'spa.status as spa_status',
                    'spa.existing_use', 'spa.created_at as spa_created_at',
                    DB::raw('('.self::REGISTRY_CASE_SQL.') as registry_group')
                );

            if ($registry && strtoupper($registry) !== 'ALL') {
                $this->applyRegistryFilter($base, $registry);
            }

            if ($search) {
                $base->where(function($q) use ($search) {
                    $q->where('fi.file_number', 'like', '%'.$search.'%')
                      ->orWhere('fn.FileName', 'like', '%'.$search.'%')
                      ->orWhere('fi.location', 'like', '%'.$search.'%');
                });
            }

            if ($request->boolean('exclude_inspected')) {
                $usedFileNos = SpaApplication::whereIn('id', SpaFieldData::pluck('spa_application_id'))->pluck('file_number');
                $base->whereNotIn('fi.file_number', $usedFileNos);
            }

            $total = $base->count();
            $rows  = $base->orderByDesc('fi.created_at')->skip($start)->take($length)->get();

            $data = $rows->map(function ($r, $i) use ($start) {
                $hasSpa = !is_null($r->spa_id);

                // Resolve owner name
                $ownerName = $r->spa_owner ?? null;
                if (!$ownerName && $r->file_title)    $ownerName = $r->file_title;
                if (!$ownerName && $r->current_holder) {
                    $h = json_decode($r->current_holder, true);
                    if (is_array($h)) {
                        $f = $h[0] ?? $h;
                        $n = trim(($f['title']??'').' '.($f['first_name']??'').' '.($f['surname']??''));
                        if (!$n) $n = $f['corporate_name'] ?? '';
                        $ownerName = $n ?: null;
                    }
                }
                $ownerName = $ownerName ?: '—';

                // Contravention: Applied vs Prevailing
                $applied    = strtoupper(trim($r->land_use_type ?? ''));
                $prevailing = strtoupper(trim($r->existing_use  ?? ''));
                $contravening = $applied && $prevailing && $applied !== $prevailing;

                // Contravention badge
                $contravBadge = ($contravening)
                    ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200">⚠ Yes</span>'
                    : ($applied && $prevailing ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">✓ No</span>' : '<span class="text-gray-400 text-xs">—</span>');

                $prefill = e(json_encode([
                    'file_number'      => $r->file_number,
                    'owner_name'       => $ownerName,
                    'phone'            => $r->phone ?? '',
                    'location'         => $r->location ?? '',
                    'land_use_type'    => $r->land_use_type ?? '',
                    'file_indexing_id' => $r->file_indexing_id,
                    'tracking_id'      => $r->tracking_id ?? '',
                ]));

                if ($hasSpa) {
                    $sm = ['open'=>'bg-blue-100 text-blue-700','in_progress'=>'bg-amber-100 text-amber-700','approved'=>'bg-green-100 text-green-700','certificate_issued'=>'bg-purple-100 text-purple-700','closed'=>'bg-gray-100 text-gray-500'];
                    $sb = '<span class="px-2 py-0.5 rounded-full text-xs font-medium '.($sm[$r->spa_status??'']??'bg-gray-100 text-gray-500').'">'.($r->spa_status??'—').'</span>';
                    $action = '<div class="relative inline-block">'
                        .'<button class="btn-lr-toggle p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 border border-transparent hover:border-gray-200 transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg></button>'
                        .'<div class="lr-dropdown hidden absolute right-0 top-full z-50 mt-1 w-44 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">'
                        .'<button class="btn-lr-edit w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2" data-id="'.$r->spa_id.'" data-record=\''.e(json_encode(['file_number'=>$r->file_number,'owner_name'=>$ownerName,'phone'=>$r->phone??'','location'=>$r->location??'','land_use_type'=>$r->land_use_type??'','existing_use'=>$r->existing_use??'','status'=>$r->spa_status??'open'])).'\'><svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Edit SPAS</button>'
                        .'<button class="btn-lr-template w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2" data-tpl=\''.e(json_encode(['file_number'=>$r->file_number,'owner_name'=>$ownerName,'location'=>$r->location??'','land_use_type'=>$r->land_use_type??'','existing_use'=>$r->existing_use??''])).'\'><svg class="w-3.5 h-3.5 text-[rgb(186,191,12)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Field Template</button>'
                        .'<button class="btn-lr-delete w-full text-left px-3 py-2 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2" data-id="'.$r->spa_id.'" data-file="'.e($r->file_number??'').'"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>Remove SPAS</button>'
                        .'</div></div>';
                } else {
                    $sb = '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-400">Not added</span>';
                    $action = '<button class="btn-lr-add-spa inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-white rounded-lg hover:opacity-90" style="background:rgb(186,191,12);white-space:nowrap;" data-prefill=\''.$prefill.'\'>'
                        .'<svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Add to SPAS</button>';
                }

                return [
                    'DT_RowIndex'   => $start + $i + 1,
                    'id'            => $r->spa_id,
                    'registry'      => $r->registry_group ?? 'MLS',
                    'file_number'   => $r->file_number ?? '—',
                    'owner_name'    => $ownerName,
                    'phone'         => $r->phone ?? '—',
                    'location'      => $r->location ?? '—',
                    'land_use_type' => $r->land_use_type ?? '',
                    'existing_use'  => $r->existing_use  ?? '',
                    'contravention' => $contravBadge,
                    'status'        => $sb,
                    'status_raw'    => $hasSpa ? ($r->spa_status ?? 'open') : 'not_added',
                    'created_at'    => $hasSpa ? (\Carbon\Carbon::parse($r->spa_created_at)->format('d/m/Y')) : '—',
                    'action'        => $action,
                ];
            });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        $PageTitle       = 'Special Assignment – Land Records';
        $PageDescription = 'Manage and browse land records under special assignment.';
        $stats = [
            'total'       => DB::connection('sqlsrv')->table('file_indexings')->whereNotNull('file_number')->where('file_number','not like','DCIV%')->where('file_number','not like','DC/%')->count(),
            'open'        => SpaApplication::where('status', 'open')->count(),
            'in_progress' => SpaApplication::where('status', 'in_progress')->count(),
        ];

        $landUseTypes = DB::connection('sqlsrv')->table('klas.dbo.land_uses')->orderBy('landuse')->pluck('landuse');

        return view('special_assignment.land_records.index', compact('PageTitle', 'PageDescription', 'stats', 'landUseTypes'));
    }

    public function searchFileIndexings(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) return response()->json([]);
        $results = DB::connection('sqlsrv')
            ->table('file_indexings as fi')
            ->leftJoin('fileNumber as fn', 'fn.tracking_id', '=', 'fi.tracking_id')
            ->where('fi.file_number', 'like', '%'.$q.'%')
            ->select('fi.file_number', 'fi.land_use_type', 'fi.location', 'fn.FileName as file_title')
            ->orderBy('fi.file_number')->limit(20)->get()
            ->map(fn($r) => ['file_number'=>$r->file_number,'file_title'=>$r->file_title??'','land_use_type'=>$r->land_use_type??'','location'=>$r->location??'']);
        return response()->json($results);
    }

    public function fieldData(Request $request)
    {
        if ($request->ajax()) {
            $query = SpaApplication::with('fieldData')->orderByDesc('created_at');
            $total = $query->count();
            $data  = $query->skip($request->input('start', 0))
                           ->take($request->input('length', 10))
                           ->get()
                           ->map(function ($a, $i) use ($request) {
                               $inspection = $a->fieldData->first();
                               $hasInspection = $inspection !== null;

                               // Contravening flag
                               $applied    = strtoupper(trim($a->land_use_type ?? ''));
                               $prevailing = strtoupper(trim($a->existing_use ?? ''));
                               $contravening = $applied && $prevailing && $applied !== $prevailing;

                               // Build action dropdown.
                               // The inspection is captured inside the "Add Land Record" card, so the
                               // row menu no longer offers "Log Inspection" — a record without an
                               // inspection row simply has no actions to offer.
                               $action = '';

                               if ($hasInspection) {
                                   $action = '<div class="relative inline-block">'
                                       . '<button class="btn-action-toggle p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 border border-transparent hover:border-gray-200 transition-colors">'
                                       . '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>'
                                       . '</button>'
                                       . '<div class="action-dropdown hidden absolute right-0 top-full z-50 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">';

                                   $inspector = optional($inspection->surveyor)->name ?? '—';
                                   $inspPhotos = $inspection->photos ? array_map(fn($p) => asset('storage/'.$p), $inspection->photos) : [];
                                   $viewData = e(json_encode([
                                       'file'      => $a->file_number,
                                       'date'      => $inspection->inspection_date?->format('d/m/Y'),
                                       'inspector' => $inspector,
                                       'findings'  => $inspection->findings,
                                       'photos'    => $inspPhotos,
                                   ]));
                                   $action .= '<button class="btn-view-field w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2"'
                                       . ' data-info=\''.$viewData.'\''
                                       . '><svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>View</button>'
                                       . '<button class="btn-delete-field w-full text-left px-3 py-2 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2" data-id="'.$inspection->id.'">'
                                       . '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>Delete</button>';

                                   $action .= '</div></div>';
                               }

                               return [
                                   'DT_RowIndex'         => $request->input('start', 0) + $i + 1,
                                   'id'                  => $a->id,
                                   'file_number'         => $a->file_number ?? '—',
                                   'owner_name'          => $a->owner_name ?? '—',
                                   'location'            => $a->location ?? '—',
                                   'land_use_type'       => $a->land_use_type ?? '',
                                   'existing_use'        => $a->existing_use ?? '',
                                   'contravening'        => $contravening,
                                   // A land record is only added from the field, with the inspection
                                   // captured in the same card — so an added record counts as inspected.
                                   'inspection_status'   => 'inspected',
                                   'inspection_date'     => $hasInspection ? ($inspection->inspection_date?->format('d/m/Y') ?? '—') : '—',
                                   'findings'            => $hasInspection ? \Str::limit($inspection->findings ?? '', 50) : '—',
                                   'action'       => $action,
                               ];
                           });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        $PageTitle       = 'Special Assignment – Field Data';
        $PageDescription = 'Field maps and collected field records for special assignment.';

        $landUseTypes = DB::connection('sqlsrv')->table('klas.dbo.land_uses')->orderBy('landuse')->pluck('landuse');

        // Customary land is only ever held for these three uses, so the General
        // Landuse dropdown shown for a customary title omits Industrial.
        $customaryLandUses = $landUseTypes
            ->filter(fn ($u) => in_array(strtoupper(trim($u)), ['RESIDENTIAL', 'COMMERCIAL', 'AGRICULTURAL'], true))
            ->values();

        // Kano's 44 LGAs, for the customary-title address picker. Small enough to
        // render inline; districts (1,818 of them) are fetched on demand instead.
        $lgaOptions = DB::connection('sqlsrv')->table('lgas')
            ->where('is_active', 1)
            ->orderBy('name')
            ->pluck('name');

        $mapPoints = SpaFieldData::with('application')
            ->whereNotNull('coordinates')
            ->orderByDesc('created_at')
            ->take(500)
            ->get()
            ->map(function ($f) {
                $app        = $f->application;
                $approved   = strtoupper(trim(optional($app)->proposed_use  ?? ''));
                $prevailing = strtoupper(trim(optional($app)->existing_use  ?? ''));
                $appPhotos  = optional($app)->photos
                    ? array_map(fn($p) => asset('storage/'.$p), (array) optional($app)->photos)
                    : [];
                return [
                    'id'           => $f->id,
                    'file_number'  => $f->file_number ?? '—',
                    'coords'       => is_array($f->coordinates) ? $f->coordinates : json_decode($f->coordinates, true),
                    'date'         => $f->inspection_date?->format('d/m/Y') ?? '—',
                    'applied_use'  => optional($app)->land_use_type ?? '',
                    'approved_use' => optional($app)->proposed_use  ?? '',
                    'prevailing'   => optional($app)->existing_use  ?? '',
                    'owner'        => optional($app)->owner_name    ?? '—',
                    'location'     => optional($app)->location      ?? '—',
                    'contravening' => $approved && $prevailing && $approved !== $prevailing,
                    'photo'        => $appPhotos[0] ?? null,
                ];
            });

        // Inspections that reached the office with no pin.
        //
        // The map above filters on whereNotNull('coordinates'), so without this
        // these records are simply absent from it — invisible rather than
        // pending. A surveyor is allowed to save without a pin (offline,
        // sometimes there is no GPS fix and the alternative is losing the record
        // entirely), which makes surfacing them here the other half of that
        // decision. See plan §11.1, Q5.
        $awaitingLocation = SpaFieldData::with('application')
            ->where(function ($q) {
                $q->whereNull('coordinates')->orWhere('coordinates', '');
            })
            ->orderByDesc('created_at')
            ->take(200)
            ->get()
            ->map(fn ($f) => [
                'id'          => $f->id,
                'file_number' => $f->file_number ?? '—',
                'owner'       => optional($f->application)->owner_name ?? '—',
                'location'    => optional($f->application)->location ?? '—',
                'lga'         => optional($f->application)->lga ?? '—',
                'date'        => $f->inspection_date?->format('d/m/Y') ?? '—',
            ]);

        return view('special_assignment.field_data.index', compact('PageTitle', 'PageDescription', 'mapPoints', 'awaitingLocation', 'landUseTypes', 'customaryLandUses', 'lgaOptions'));
    }

    public function notice(Request $request)
    {
        if ($request->ajax()) {
            $type  = $request->input('type', 'first');

            // ── First Serve: driven automatically by Field Data (inspected) records ──
            // Every application that has a field-inspection appears here; the row shows
            // whether a first-serve notice has already been issued for it.
            if ($type === 'first') {
                // Every land record added to SPAS is a field record (it can only be
                // created from the field), so the list is no longer restricted to
                // applications carrying a spa_field_data row: the inspection section
                // of "Add Land Record" is optional, and that restriction silently
                // hid notices that had already been issued and sent.
                $rows = SpaApplication::orderByDesc('created_at')->get()
                    ->map(fn ($a) => (object) [
                        'id'          => $a->id,
                        'file_number' => $a->file_number,
                        'owner_name'  => $a->owner_name,
                        'phone'       => $a->phone,
                        'created_at'  => $a->created_at,
                    ]);

                // storeNotice accepts any file number, indexed or not, so a notice can
                // exist with no SPAS application behind it. Those rows have no
                // application to hang off, so carry them into the list directly —
                // otherwise issuing such a notice appears to succeed and then vanishes.
                $knownFiles = $rows->pluck('file_number')->filter()->all();
                $orphans = SpaNotice::where('notice_type', 'first')
                    ->when($knownFiles, fn ($q) => $q->whereNotIn('file_number', $knownFiles))
                    ->orderByDesc('created_at')
                    ->get()
                    ->unique('file_number')
                    ->map(fn ($n) => (object) [
                        'id'          => null,
                        'file_number' => $n->file_number,
                        'owner_name'  => $n->recipient_name,
                        'phone'       => $n->phone,
                        'created_at'  => $n->created_at,
                    ]);

                $all   = $rows->concat($orphans)->sortByDesc('created_at')->values();
                $total = $all->count();
                $data  = $all->slice((int) $request->input('start', 0))
                             ->take((int) $request->input('length', 10))
                             ->values()
                             ->map(function ($a, $i) use ($request) {
                                   $notice = SpaNotice::where('notice_type', 'first')
                                       ->where(fn($q) => $q->when($a->id, fn($qq) => $qq->where('spa_application_id', $a->id))
                                                           ->orWhere('file_number', $a->file_number))
                                       ->orderByDesc('created_at')->first();

                                   $served    = $notice !== null;
                                   $recipient = $notice->recipient_name ?? $a->owner_name ?? '—';
                                   $phone     = $notice->phone ?? $a->phone ?? '—';
                                   $daysAgo   = ($served && $notice->served_date) ? now()->diffInDays($notice->served_date) : null;

                                   $hasSecond = $served
                                       ? SpaNotice::where('notice_type', 'second')
                                              ->when($notice->spa_application_id,
                                                  fn($q) => $q->where('spa_application_id', $notice->spa_application_id),
                                                  fn($q) => $q->whereNull('spa_application_id')->where('file_number', $notice->file_number))
                                              ->exists()
                                       : false;

                                   if (!$served) {
                                       $action = '<button data-file="'.e($a->file_number).'" data-owner="'.e($a->owner_name).'" data-phone="'.e($a->phone).'" class="btn-issue-first inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-[rgb(186,191,12)] text-white hover:opacity-90"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Issue 1st Serve</button>';
                                   } elseif ($hasSecond) {
                                       $action = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Served</span>';
                                   } else {
                                       $action = '<button data-id="'.$notice->id.'" data-app="'.$notice->spa_application_id.'" class="btn-trigger-second inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-300 hover:bg-amber-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Not Served</button>';
                                   }

                                   return [
                                       'DT_RowIndex' => $request->input('start', 0) + $i + 1,
                                       'file_number' => $a->file_number ?? '—',
                                       'recipient'   => $recipient,
                                       'phone'       => $phone,
                                       'served_date' => $served ? ($notice->served_date?->format('d/m/Y') ?? '—') : '—',
                                       'sms_sent'    => ($served && $notice->sms_sent) ? '<span class="text-green-600 text-xs">✓ Sent</span>' : '<span class="text-gray-400 text-xs">—</span>',
                                       'status'      => $served ? $notice->status : 'pending',
                                       'days_ago'    => $daysAgo,
                                       'action'      => $action,
                                   ];
                               });

                return response()->json([
                    'draw'            => intval($request->input('draw')),
                    'recordsTotal'    => $total,
                    'recordsFiltered' => $total,
                    'data'            => $data,
                ]);
            }

            // ── Second Serve: driven automatically by the age of the first serve ──
            // A second serve is due 14 days after the first serve, so this tab lists
            // the first-serve notices that have reached that age. The scheduled
            // command (spa:trigger-second-service) issues them; a row that has not
            // been picked up yet shows as "Due" and can be triggered manually.
            $cutoff = now()->subDays(SpaNotice::SECOND_SERVE_AFTER_DAYS)->toDateString();

            $query = SpaNotice::where('notice_type', 'first')
                              ->whereNotNull('served_date')
                              ->whereDate('served_date', '<=', $cutoff)
                              ->orderBy('served_date');          // most overdue first
            $total = $query->count();
            $data  = $query->skip($request->input('start', 0))
                           ->take($request->input('length', 10))
                           ->get()
                           ->map(function ($n, $i) use ($request) {
                               $daysAgo = $n->served_date ? (int) $n->served_date->diffInDays(now()) : null;

                               $second = SpaNotice::where('notice_type', 'second')
                                              ->when($n->spa_application_id,
                                                  fn($q) => $q->where('spa_application_id', $n->spa_application_id),
                                                  fn($q) => $q->whereNull('spa_application_id')->where('file_number', $n->file_number))
                                              ->orderByDesc('created_at')->first();

                               return [
                                   'DT_RowIndex'   => $request->input('start', 0) + $i + 1,
                                   'file_number'   => $n->file_number ?? '—',
                                   'recipient'     => $n->recipient_name ?? '—',
                                   'phone'         => $n->phone ?? '—',
                                   'first_date'    => $n->served_date?->format('d/m/Y') ?? '—',
                                   'days_ago'      => $daysAgo,
                                   'served_date'   => $second?->served_date?->format('d/m/Y') ?? '—',
                                   'sms_sent'      => $second && $second->sms_sent
                                       ? '<span class="text-green-600 text-xs">✓ Sent</span>'
                                       : '<span class="text-gray-400 text-xs">—</span>',
                                   'status'        => $second ? ($second->status ?: 'served') : 'due',
                                   'action'        => $second
                                       ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Issued</span>'
                                       : '<button data-id="'.$n->id.'" data-app="'.$n->spa_application_id.'" class="btn-trigger-second inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-300 hover:bg-amber-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Issue Now</button>',
                               ];
                           });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        $PageTitle       = 'Special Assignment – Notice';
        $PageDescription = 'Manage first and second serve notices for special assignment.';
        $stats = [
            'first_total'  => SpaNotice::where('notice_type', 'first')->count(),
            'second_total' => SpaNotice::where('notice_type', 'second')->count(),
            'pending'      => SpaNotice::where('status', 'pending')->count(),
            // First serves that have reached 14 days — the second-serve queue.
            'second_due'   => SpaNotice::where('notice_type', 'first')
                                       ->whereNotNull('served_date')
                                       ->whereDate('served_date', '<=', now()->subDays(SpaNotice::SECOND_SERVE_AFTER_DAYS)->toDateString())
                                       ->count(),
        ];

        return view('special_assignment.notice.index', compact('PageTitle', 'PageDescription', 'stats'));
    }

    public function otherDepartments(Request $request)
    {
        if ($request->ajax() || $request->input('ajax')) {
            $dept  = $request->input('department', 'cadastral');
            $query = SpaDepartmentReferral::with(['referredBy','application'])->where('department', $dept)->orderByDesc('created_at');
            $total = $query->count();
            $data  = $query->skip($request->input('start', 0))
                           ->take($request->input('length', 10))
                           ->get()
                           ->map(function ($r, $i) use ($request) {
                               $canRespond = in_array($r->status, ['pending','responded']);
                               $action = '<div class="relative inline-block">'
                                   . '<button class="btn-dep-toggle p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 border border-transparent hover:border-gray-200 transition-colors">'
                                   . '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>'
                                   . '</button>'
                                   . '<div class="dep-dropdown hidden absolute right-0 top-full z-50 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">'
                                   . ($canRespond
                                       ? '<button class="btn-record-response w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2" data-id="'.$r->id.'"><svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Record Response</button>'
                                       : '')
                                   . '<button class="btn-dep-delete w-full text-left px-3 py-2 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2" data-id="'.$r->id.'" data-file="'.e($r->file_number ?? '').'"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>Delete</button>'
                                   . '</div></div>';
                               return [
                                   'DT_RowIndex'   => $request->input('start', 0) + $i + 1,
                                   'file_number'   => $r->file_number ?? '—',
                                   'owner_name'    => optional($r->application)->owner_name ?? '—',
                                   'referred_by'   => optional($r->referredBy)->name ?? '—',
                                   'referral_date' => $r->referral_date?->format('d/m/Y') ?? '—',
                                   'response_date' => $r->response_date?->format('d/m/Y') ?? '—',
                                   'notes'         => \Str::limit($r->notes ?? '', 60),
                                   'status'        => $r->status,
                                   'action'        => $action,
                               ];
                           });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        $PageTitle       = 'Special Assignment – Other Departments';
        $PageDescription = 'Cadastral, Physical Planning/KAMMA, and Survey department records.';
        $stats = [
            'total'     => SpaDepartmentReferral::count(),
            'pending'   => SpaDepartmentReferral::where('status', 'pending')->count(),
            'responded' => SpaDepartmentReferral::where('status', 'responded')->count(),
            'approved'  => SpaDepartmentReferral::where('status', 'approved')->count(),
        ];

        return view('special_assignment.other_departments.index', compact('PageTitle', 'PageDescription', 'stats'));
    }

    public function recordDepartmentResponse(Request $request, int $id)
    {
        $request->validate([
            'response_date'  => 'required|date',
            'status'         => 'required|in:responded,approved,rejected',
            'response_notes' => 'nullable|string',
        ]);

        $referral = SpaDepartmentReferral::findOrFail($id);
        $referral->update([
            'response_date'  => $request->response_date,
            'response_notes' => $request->response_notes,
            'status'         => $request->status,
        ]);

        return response()->json(['success' => true, 'message' => 'Response recorded for ' . ucwords(str_replace('_', ' ', $referral->department)) . '.']);
    }

    public function deleteDepartmentReferral(int $id)
    {
        SpaDepartmentReferral::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Referral deleted.']);
    }

    public function certificate(Request $request)
    {
        if ($request->ajax()) {
            $query = SpaCertificate::orderByDesc('created_at');
            $total = $query->count();
            $data  = $query->skip($request->input('start', 0))
                           ->take($request->input('length', 10))
                           ->get()
                           ->map(function ($c, $i) use ($request) {
                               return [
                                   'DT_RowIndex' => $request->input('start', 0) + $i + 1,
                                   'cert_number' => $c->cert_number ?? '—',
                                   'file_number' => $c->file_number ?? '—',
                                   'holder_name' => $c->holder_name ?? '—',
                                   'from_use'    => $c->from_use ?? '—',
                                   'to_use'      => $c->to_use ?? '—',
                                   'issue_date'  => $c->issue_date?->format('d/m/Y') ?? '—',
                                   'status'      => $c->status,
                                   'action'      => '<div class="relative inline-block">'
                                       .'<button class="btn-cert-toggle p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 border border-transparent hover:border-gray-200 transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg></button>'
                                       .'<div class="cert-dropdown hidden absolute right-0 top-full z-50 mt-1 w-44 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">'
                                       .'<button class="btn-print-cert w-full text-left px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2" data-id="'.$c->id.'"><svg class="w-3.5 h-3.5 text-[rgb(186,191,12)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Print Sheet</button>'
                                       .'</div></div>',
                               ];
                           });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        $PageTitle       = 'Special Assignment – Change of Purpose Sheet';
        $PageDescription = 'Issue and track Change of Purpose Sheets.';
        $stats = [
            'total'     => SpaCertificate::count(),
            'issued'    => SpaCertificate::where('status', 'issued')->count(),
            'collected' => SpaCertificate::where('status', 'collected')->count(),
            'revoked'   => SpaCertificate::where('status', 'revoked')->count(),
        ];

        $landUseTypes = DB::connection('sqlsrv')->table('klas.dbo.land_uses')->orderBy('landuse')->pluck('landuse');

        // Every file that appears on the Field Data page, which is every SPAS
        // application. The list used to be filtered to status = 'approved',
        // which hid files whose memo had been approved without the application
        // status following — the officer then could not find the file at all.
        // issueCertificate() still refuses anything without an approved memo, so
        // widening the list cannot issue a sheet that should not exist; it only
        // stops the file being invisible.
        $approvedAppIds = SpaMemo::where('commissioner_decision', 'approved')->pluck('spa_application_id')->filter();
        $approvedFiles  = SpaApplication::whereIn('id', $approvedAppIds)->pluck('file_number')->filter()->unique();

        $certApplications = SpaApplication::orderBy('file_number')
            ->get(['id', 'file_number', 'owner_name', 'land_use_type', 'existing_use'])
            ->map(function ($a) use ($approvedAppIds, $approvedFiles) {
                // Matched by id OR by file number, the same way issueCertificate
                // does: re-adding a file to SPAS gives it a new application id,
                // which would otherwise orphan its approved memo.
                $a->has_approved_memo = $approvedAppIds->contains($a->id)
                    || $approvedFiles->contains($a->file_number);

                return $a;
            });

        return view('special_assignment.certificate.index', compact('PageTitle', 'PageDescription', 'stats', 'landUseTypes', 'certApplications'));
    }

    public function memo(Request $request)
    {
        if ($request->ajax()) {
            $query = SpaMemo::with('application')->orderByDesc('created_at');
            $total = $query->count();
            $data  = $query->skip($request->input('start', 0))
                           ->take($request->input('length', 10))
                           ->get()
                           ->map(function ($m, $i) use ($request) {
                               return [
                                   'DT_RowIndex'   => $request->input('start', 0) + $i + 1,
                                   'memo_no'       => $m->memo_no ?? '—',
                                   'file_number'   => optional($m->application)->file_number ?? '—',
                                   'forwarded_to'  => $m->forwarded_to ?? '—',
                                   'forwarded_at'  => $m->forwarded_at?->format('d/m/Y') ?? '—',
                                   'decision'      => $m->commissioner_decision,
                                   'decided_at'    => $m->decided_at?->format('d/m/Y') ?? '—',
                                   // A memo is printable at every stage: pending
                                   // ones are what gets carried to the
                                   // Commissioner for signing in the first place.
                                   'action'        => '<div class="flex items-center gap-2">'
                                       .($m->commissioner_decision === 'pending'
                                           ? '<button data-id="'.$m->id.'" data-memo="'.e($m->memo_no).'" class="btn-decide text-xs px-3 py-1 rounded bg-[rgb(186,191,12)] text-white">Approve</button>'
                                           : '<span class="text-xs text-gray-400">'.ucfirst($m->commissioner_decision).'</span>')
                                       .'<a href="'.route('special-assignment.memo.print', $m->id).'" target="_blank" '
                                       .'class="text-xs px-3 py-1 rounded border border-gray-200 text-gray-600 hover:bg-gray-50">Print</a>'
                                       .'</div>',
                               ];
                           });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        $PageTitle       = 'Special Assignment – Memo';
        $PageDescription = 'Commissioner approval memos for Special Assignment cases.';
        $stats = [
            'total'    => SpaMemo::count(),
            'pending'  => SpaMemo::where('commissioner_decision', 'pending')->count(),
            'approved' => SpaMemo::where('commissioner_decision', 'approved')->count(),
            'rejected' => SpaMemo::where('commissioner_decision', 'rejected')->count(),
        ];
        $applications = SpaApplication::whereDoesntHave('memos')->select('id', 'file_number', 'owner_name')->get();

        return view('special_assignment.memo.index', compact('PageTitle', 'PageDescription', 'stats', 'applications'));
    }

    public function mobile(Request $request)
    {
        $mapPoints = SpaFieldData::with('application')
            ->whereNotNull('coordinates')
            ->orderByDesc('created_at')
            ->take(500)
            ->get()
            ->map(function ($f) {
                $app        = $f->application;
                $approved   = strtoupper(trim(optional($app)->proposed_use ?? ''));
                $prevailing = strtoupper(trim(optional($app)->existing_use ?? ''));
                $appPhotos  = optional($app)->photos
                    ? array_map(fn($p) => asset('storage/'.$p), (array) optional($app)->photos)
                    : [];
                return [
                    'id'           => $f->id,
                    'file_number'  => $f->file_number ?? '—',
                    'coords'       => is_array($f->coordinates) ? $f->coordinates : json_decode($f->coordinates, true),
                    'date'         => $f->inspection_date?->format('d/m/Y') ?? '—',
                    'land_use'     => optional($app)->land_use_type ?? '',
                    'approved_use' => optional($app)->proposed_use  ?? '',
                    'prevailing'   => optional($app)->existing_use  ?? '',
                    'owner'        => optional($app)->owner_name    ?? '—',
                    'location'     => optional($app)->location      ?? '—',
                    'contravening' => $approved && $prevailing && $approved !== $prevailing,
                    'photo'        => $appPhotos[0] ?? null,
                ];
            });

        $landUseTypes = DB::connection('sqlsrv')->table('klas.dbo.land_uses')->orderBy('landuse')->pluck('landuse');

        // Same two lists the desktop Field Data page builds, so both Add Land
        // Record forms offer identical choices (see fieldData()).
        $customaryLandUses = $landUseTypes
            ->filter(fn ($u) => in_array(strtoupper(trim($u)), ['RESIDENTIAL', 'COMMERCIAL', 'AGRICULTURAL'], true))
            ->values();

        $lgaOptions = DB::connection('sqlsrv')->table('lgas')
            ->where('is_active', 1)
            ->orderBy('name')
            ->pluck('name');

        return view('special_assignment.mobile', compact('mapPoints', 'landUseTypes', 'customaryLandUses', 'lgaOptions'));
    }

    public function report(Request $request)
    {
        $PageTitle       = 'Special Assignment – Report';
        $PageDescription = 'Summary reports and analytics for special assignment.';

        return view('special_assignment.report.index', compact('PageTitle', 'PageDescription'));
    }

    // ─── API / ACTION METHODS ──────────────────────────────────────────────────

    public function checkFileIndexed(Request $request)
    {
        $fileNo = trim($request->input('file_number', ''));
        if (!$fileNo) {
            return response()->json(['found' => false]);
        }

        // Fast path: direct match on file_indexings.file_number (indexed column)
        $record = DB::connection('sqlsrv')
            ->table('file_indexings as fi')
            ->leftJoin('fileNumber as fn', 'fn.tracking_id', '=', 'fi.tracking_id')
            ->where('fi.file_number', $fileNo)
            ->select(
                'fi.id as file_indexing_id', 'fi.tracking_id',
                'fi.file_number', 'fi.land_use_type',
                'fi.location', 'fi.district', 'fi.lga',
                'fi.phone', 'fn.FileName as file_title',
                'fi.dciv_status', 'fi.dciv_fileno', 'fi.dciv_reason'
            )
            ->orderByDesc('fi.created_at')
            ->first();

        // Slow-path fallback: search fileNumber columns if fast path missed
        if (!$record) {
            $record = DB::connection('sqlsrv')
                ->table('file_indexings as fi')
                ->join('fileNumber as fn', 'fn.tracking_id', '=', 'fi.tracking_id')
                ->where(function ($q) use ($fileNo) {
                    $q->where('fn.st_file_no', $fileNo)
                      ->orWhere('fn.mlsfNo', $fileNo)
                      ->orWhere('fn.kangisFileNo', $fileNo)
                      ->orWhere('fn.NewKANGISFileNo', $fileNo);
                })
                ->select(
                    'fi.id as file_indexing_id', 'fi.tracking_id',
                    'fi.file_number', 'fi.land_use_type',
                    'fi.location', 'fi.district', 'fi.lga',
                    'fi.phone', 'fn.FileName as file_title',
                    'fi.dciv_status', 'fi.dciv_fileno', 'fi.dciv_reason'
                )
                ->orderByDesc('fi.created_at')
                ->first();
        }

        if (!$record) {
            return response()->json(['found' => false]);
        }

        // Try to get owner name from current_holder JSON
        $holder = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where('id', $record->file_indexing_id)
            ->value('current_holder');

        $holderArr = $holder ? json_decode($holder, true) : null;
        $ownerName = '';
        if (is_array($holderArr) && !empty($holderArr)) {
            $first = $holderArr[0] ?? $holderArr;
            $ownerName = trim(($first['title'] ?? '') . ' ' . ($first['first_name'] ?? '') . ' ' . ($first['surname'] ?? ''));
            if (empty(trim($ownerName))) {
                $ownerName = $first['corporate_name'] ?? '';
            }
        }

        return response()->json([
            'found'           => true,
            'file_indexing_id'=> $record->file_indexing_id,
            'tracking_id'     => $record->tracking_id,
            'file_number'     => $record->file_number,
            'land_use_type'   => $record->land_use_type,
            'location'        => $record->location,
            'district'        => $record->district,
            'lga'             => $record->lga,
            'phone'           => $record->phone,
            'owner_name'      => $ownerName,
            'file_title'      => $record->file_title ?? '',
            'dciv_status'     => (int) ($record->dciv_status ?? 0),
            'dciv_fileno'     => $record->dciv_fileno ?? null,
            'dciv_reason'     => $record->dciv_reason ?? null,
        ]);
    }

    public function nextCustomaryFileNumber(Request $request)
    {
        return response()->json(['file_number' => SpaApplication::generateCustomaryFileNumber()]);
    }

    public function storeLandRecord(Request $request, SpaMobileService $spa)
    {
        // Rules live in SpaMobileService so the desktop form, the mobile form
        // and the offline app cannot drift apart again — a rule added here
        // alone once broke every customary save from mobile with a silent 422.
        $request->validate($spa->landRecordRules(), $spa->landRecordMessages());

        // One SPAS application per file number. Enforced in the database by
        // UQ_spa_applications_file_number; checked here first so a duplicate
        // reads as a form error instead of a raw unique-constraint 500.
        $duplicate = $spa->duplicateFileNumberError($request->land_title_type, $request->file_number);

        if ($duplicate) {
            return response()->json(['success' => false, 'message' => $duplicate], 422);
        }

        $photos = $spa->storePhotos($request->file('photos'), 'spa/land-records');

        $app = $spa->createLandRecord(
            $request->all(),
            $photos,
            auth()->user()->name ?? auth()->id()
        );

        return response()->json(['success' => true, 'id' => $app->id, 'file_number' => $app->file_number, 'message' => 'Land record saved.']);
    }

    public function updateLandRecord(Request $request, int $id, SpaMobileService $spa)
    {
        $app = SpaApplication::findOrFail($id);

        // Rules live in the service alongside the create rules, so an edit
        // cannot drift away from what the forms and the offline app enforce.
        // withStatus: only the office may move a record through its workflow —
        // the API omits it, so a field device cannot approve its own record.
        $request->validate($spa->landRecordUpdateRules(withStatus: true));

        $spa->applyLandRecordUpdate($app, $request->all(), withStatus: true);

        return response()->json(['success' => true, 'message' => 'Record updated.']);
    }

    public function deleteLandRecord(int $id)
    {
        $app = SpaApplication::findOrFail($id);
        $app->delete();
        return response()->json(['success' => true, 'message' => 'Record deleted.']);
    }

    public function storeFieldData(Request $request, SpaMobileService $spa)
    {
        // requireServerParent: the web forms always have a saved parent record.
        // Only the offline app may push an inspection that links by client_uuid.
        $request->validate($spa->fieldDataRules(requireServerParent: true));

        $duplicate = $spa->duplicateInspectionError($request->file_number);

        if ($duplicate) {
            return response()->json(['success' => false, 'message' => $duplicate], 422);
        }

        // Rejects an unparseable pin rather than silently saving the inspection
        // without one; throws ValidationException, which renders as a 422.
        try {
            $coordinates = $spa->normalizeCoordinates($request->input('coordinates'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first('coordinates'),
            ], 422);
        }

        $photos = $spa->storePhotos($request->file('photos'), 'spa/field-data');

        $data = $spa->createFieldData(
            $request->all(),
            $photos,
            auth()->id(),
            auth()->user()->name ?? auth()->id()
        );

        // Build map point for live map update
        $app      = SpaApplication::find($request->spa_application_id);
        $mapPoint = null;
        if ($coordinates) {
            $approved   = strtoupper(trim($app?->proposed_use ?? ''));
            $prevailing = strtoupper(trim($app?->existing_use ?? ''));
            $appPhotos  = $app?->photos ? array_map(fn($p) => asset('storage/'.$p), (array)$app->photos) : [];
            $mapPoint = [
                'id'           => $data->id,
                'file_number'  => $data->file_number ?? '—',
                'coords'       => $coordinates,
                'date'         => $data->inspection_date?->format('d/m/Y') ?? now()->format('d/m/Y'),
                'applied_use'  => $app?->land_use_type ?? '',
                'approved_use' => $app?->proposed_use  ?? '',
                'prevailing'   => $app?->existing_use  ?? '',
                'owner'        => $app?->owner_name    ?? '—',
                'location'     => $app?->location      ?? '—',
                'contravening' => $approved && $prevailing && $approved !== $prevailing,
                'photo'        => $appPhotos[0] ?? null,
            ];
        }

        return response()->json(['success' => true, 'id' => $data->id, 'message' => 'Field data saved.', 'mapPoint' => $mapPoint]);
    }

    public function deleteFieldData(int $id)
    {
        $record = SpaFieldData::findOrFail($id);
        if ($record->photos) {
            foreach ($record->photos as $path) {
                Storage::disk('public')->delete($path);
            }
        }
        $record->delete();
        return response()->json(['success' => true, 'message' => 'Field record deleted.']);
    }

    public function storeNotice(Request $request)
    {
        $request->validate([
            // Notices are now keyed off the file number directly — the file may or
            // may not be indexed, and need not have a Special Assignment application.
            'file_number'        => 'required|string|max:255',
            'recipient_name'     => 'required|string|max:255',
            'phone'              => 'required|string|max:20',
            'served_date'        => 'required|date',
        ]);

        // Link to a Special Assignment application when one exists for this file,
        // otherwise leave it null (free-style / non-indexed file).
        $spaApplicationId = SpaApplication::where('file_number', $request->file_number)->value('id');

        $notice = SpaNotice::create([
            'spa_application_id' => $spaApplicationId,
            'file_number'        => $request->file_number,
            'notice_type'        => 'first',
            'recipient_name'     => $request->recipient_name,
            'phone'              => $request->phone,
            'served_by'          => auth()->id(),
            'served_date'        => $request->served_date,
            // scheduled_date is set below, from the moment the SMS actually
            // went — not from now. A notice whose SMS failed has no second
            // serve due, because the first serve has not happened yet.
            'status'             => 'served',
            'created_by'         => auth()->user()->name ?? auth()->id(),
        ]);

        $sms = $this->sendNoticeSms($request->phone, $request->recipient_name, 'first', $request->file_number);

        $sentAt = $sms['sent'] ? now() : null;

        $notice->update([
            'sms_sent'       => $sms['sent'],
            'sms_sent_at'    => $sentAt,
            // The statutory two weeks run from delivery. Stamping a due date on
            // an undelivered notice would show the office a deadline that is
            // not actually running, and spa:trigger-second-service (correctly)
            // will not escalate it.
            'scheduled_date' => $sentAt
                ? $sentAt->copy()->addDays(\App\Models\SpaNotice::SECOND_SERVE_AFTER_DAYS)->toDateString()
                : null,
        ]);

        return response()->json([
            'success'    => true,
            'id'         => $notice->id,
            'sms_sent'   => $sms['sent'],
            'sms_code'   => $sms['code'],
            'sms_reason' => $sms['reason'],
            'message'    => 'First serve notice issued.',
        ]);
    }

    /**
     * Re-attempt the SMS for a notice whose send failed.
     *
     * Without this, a first serve whose SMS never went was a dead end: it is
     * (correctly) never escalated to a second serve, so it would sit forever
     * with the owner unaware and nothing chasing it. The gateway refuses some
     * messages on wording and still answers 200, so this is a routine state,
     * not a rare one.
     *
     * Re-sending a notice that already went is refused rather than duplicated —
     * the owner should not receive the same statutory warning twice.
     */
    public function resendNoticeSms(int $id)
    {
        $notice = SpaNotice::findOrFail($id);

        if ($notice->sms_sent) {
            return response()->json([
                'success' => false,
                'message' => 'That notice was already delivered on '
                    .optional($notice->sms_sent_at)->format('d/m/Y H:i').'.',
            ], 409);
        }

        $sms = $this->sendNoticeSms(
            $notice->phone,
            $notice->recipient_name,
            $notice->notice_type,
            $notice->file_number
        );

        if (! $sms['sent']) {
            return response()->json([
                'success'    => false,
                'sms_code'   => $sms['code'],
                'sms_reason' => $sms['reason'],
                'message'    => 'Still could not send: '.($sms['reason'] ?: 'gateway refused the message').'.',
            ], 422);
        }

        $sentAt = now();

        $notice->update([
            'sms_sent'    => true,
            'sms_sent_at' => $sentAt,
            // The clock starts now, not when the row was first created.
            'scheduled_date' => $notice->notice_type === 'first'
                ? $sentAt->copy()->addDays(SpaNotice::SECOND_SERVE_AFTER_DAYS)->toDateString()
                : $notice->scheduled_date,
        ]);

        return response()->json([
            'success' => true,
            'message' => $notice->notice_type === 'first'
                ? 'First serve delivered. The second serve falls due on '
                    .$sentAt->copy()->addDays(SpaNotice::SECOND_SERVE_AFTER_DAYS)->format('d/m/Y').'.'
                : 'Notice delivered.',
        ]);
    }

    public function triggerSecondNotice(Request $request, int $id)
    {
        $first = SpaNotice::findOrFail($id);

        // Dedup by SPAS application when linked, otherwise by file number (free-style files).
        $alreadyExists = SpaNotice::where('notice_type', 'second')
            ->when($first->spa_application_id,
                fn($q) => $q->where('spa_application_id', $first->spa_application_id),
                fn($q) => $q->whereNull('spa_application_id')->where('file_number', $first->file_number))
            ->exists();

        if ($alreadyExists) {
            return response()->json(['success' => false, 'message' => 'Second serve already issued for this application.'], 422);
        }

        $second = SpaNotice::create([
            'spa_application_id' => $first->spa_application_id,
            'file_number'        => $first->file_number,
            'notice_type'        => 'second',
            'recipient_name'     => $first->recipient_name,
            'phone'              => $first->phone,
            'served_by'          => auth()->id(),
            'served_date'        => now()->toDateString(),
            'status'             => 'served',
            'created_by'         => auth()->user()->name ?? auth()->id(),
        ]);

        $sms = $this->sendNoticeSms($first->phone, $first->recipient_name, 'second', $first->file_number);
        $second->update(['sms_sent' => $sms['sent'], 'sms_sent_at' => $sms['sent'] ? now() : null]);

        return response()->json([
            'success'    => true,
            'id'         => $second->id,
            'sms_sent'   => $sms['sent'],
            'sms_code'   => $sms['code'],
            'sms_reason' => $sms['reason'],
            'message'    => 'Second serve notice issued.',
        ]);
    }

    public function storeDepartmentReferral(Request $request)
    {
        $request->validate([
            'spa_application_id' => 'required|exists:sqlsrv.spa_applications,id',
            'department'         => 'required|in:cadastral,physical_planning,survey',
            'notes'              => 'nullable|string',
        ]);

        $referral = SpaDepartmentReferral::create([
            'spa_application_id' => $request->spa_application_id,
            'file_number'        => $request->file_number,
            'department'         => $request->department,
            'referred_by'        => auth()->id(),
            'referral_date'      => $request->referral_date ?: now()->toDateString(),
            'notes'              => $request->notes,
            'status'             => 'pending',
            'created_by'         => auth()->user()->name ?? auth()->id(),
        ]);

        return response()->json(['success' => true, 'id' => $referral->id, 'message' => 'Referral sent to ' . ucwords(str_replace('_', ' ', $request->department)) . '.']);
    }

    public function generateMemo(Request $request)
    {
        $request->validate([
            'spa_application_id' => 'required|exists:sqlsrv.spa_applications,id',
            'forwarded_to'       => 'required|string|max:255',
        ]);

        $memoNo = SpaMemo::generateMemoNumber();

        $memo = SpaMemo::create([
            'spa_application_id'    => $request->spa_application_id,
            'memo_no'               => $memoNo,
            'prepared_by'           => auth()->id(),
            'forwarded_to'          => $request->forwarded_to,
            'forwarded_at'          => now(),
            'commissioner_decision' => 'pending',
            'created_by'            => auth()->user()->name ?? auth()->id(),
        ]);

        SpaApplication::where('id', $request->spa_application_id)
            ->update(['status' => 'memo_stage']);

        return response()->json(['success' => true, 'id' => $memo->id, 'memo_no' => $memoNo, 'message' => "Memo {$memoNo} generated and forwarded."]);
    }

    /**
     * Printable Commissioner memo.
     *
     * Available before a decision as well as after: the pending memo is the
     * document physically carried to the Commissioner for signature, and the
     * decided one is the record of what was signed.
     */
    public function printMemo(int $id)
    {
        $memo = SpaMemo::with(['application', 'preparedBy'])->findOrFail($id);

        return view('special_assignment.memo.print', compact('memo'));
    }

    public function commissionerDecision(Request $request, int $id)
    {
        $request->validate([
            'decision' => 'required|in:approved,rejected',
            'notes'    => 'nullable|string',
        ]);

        $memo = SpaMemo::findOrFail($id);
        $memo->update([
            'commissioner_decision' => $request->decision,
            'commissioner_notes'    => $request->notes,
            'decided_at'            => now(),
        ]);

        if ($request->decision === 'approved') {
            SpaApplication::where('id', $memo->spa_application_id)->update(['status' => 'approved']);
        }

        // Notify the memo preparer
        try {
            $notifier = app(UserNotificationService::class);
            $title    = "Memo {$memo->memo_no} — Commissioner Decision";
            $body     = "The Commissioner has " . $request->decision . " memo {$memo->memo_no}.";
            if ($memo->prepared_by) {
                $notifier->create($memo->prepared_by, 'info', $title, $body, [], ['module' => 'special_assignment']);
            }
        } catch (\Throwable $e) {
            Log::warning('SPAS: notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => "Decision recorded: " . ucfirst($request->decision)]);
    }

    public function issueCertificate(Request $request)
    {
        $request->validate([
            // `integer` before `exists` so a non-numeric value (e.g. the string "null"
            // from an SPAS-less file) fails with a clean 422 instead of crashing SQL
            // Server's nvarchar→bigint conversion inside the exists query.
            'spa_application_id' => 'required|integer|exists:sqlsrv.spa_applications,id',
            'holder_name'        => 'required|string|max:255',
            'new_file_number'    => 'required|string|max:255',
            'from_use'           => 'required|string|max:255',
            // A change of purpose that does not change the purpose is not a
            // change of purpose. Issuing one would put a sheet on the file
            // recording a conversion that never happened, and flip the
            // application to `certificate_issued` on the strength of it.
            //
            // `different:from_use` alone compares raw strings, so
            // "COMMERCIAL" and "Commercial " would slip through — the values
            // come from a free-text-ish land-use list. The explicit check
            // below normalises case and padding first.
            'to_use'             => 'required|string|max:255|different:from_use',
            'issue_date'         => 'required|date',
        ], [
            'spa_application_id.integer' => 'Please select a valid Special Assignment application.',
            'spa_application_id.exists'  => 'The selected file does not have a Special Assignment application yet.',
            'to_use.different'           => 'The new purpose must differ from the approved land use.',
        ]);

        if (strtoupper(trim($request->from_use)) === strtoupper(trim($request->to_use))) {
            return response()->json([
                'success' => false,
                'errors'  => ['to_use' => ['The new purpose must differ from the approved land use.']],
                'message' => 'The new purpose is the same as the approved land use ('
                    .$request->from_use.'), so there is no change of purpose to issue.',
            ], 422);
        }

        $app = SpaApplication::findOrFail($request->spa_application_id);

        // Require approved memo. Match by spa_application_id OR by file number — when a
        // file is removed and re-added to SPAS it gets a fresh application id, which would
        // orphan an already-approved memo that still points at the old id.
        $approved = SpaMemo::where('commissioner_decision', 'approved')
            ->where(function ($q) use ($request, $app) {
                $q->where('spa_application_id', $request->spa_application_id)
                  ->orWhereIn('spa_application_id', function ($sub) use ($app) {
                      $sub->select('id')
                          ->from('spa_applications')
                          ->where('file_number', $app->file_number);
                  });
            })
            ->exists();

        if (!$approved) {
            return response()->json(['success' => false, 'message' => 'Cannot issue certificate without an approved Commissioner memo.'], 422);
        }

        $cert = SpaCertificate::create([
            'spa_application_id' => $request->spa_application_id,
            'cert_number'        => SpaCertificate::generateCertNumber(),
            'file_number'        => $app->file_number,
            'new_file_number'    => $request->new_file_number,
            'holder_name'        => $request->holder_name,
            'from_use'           => $request->from_use,
            'to_use'             => $request->to_use,
            'issue_date'         => $request->issue_date,
            'expiry_date'        => $request->expiry_date ?: null,
            'issued_by'          => auth()->id(),
            'status'             => 'issued',
            'created_by'         => auth()->user()->name ?? auth()->id(),
        ]);

        $app->update(['status' => 'certificate_issued']);

        return response()->json(['success' => true, 'cert_number' => $cert->cert_number, 'message' => "Change of Purpose Sheet {$cert->cert_number} issued."]);
    }

    public function printCertificate(int $id)
    {
        $cert = SpaCertificate::with('issuedBy')->findOrFail($id);
        return view('special_assignment.certificate.print', compact('cert'));
    }

    public function reportData(Request $request)
    {
        $year = $request->input('year', now()->year);

        // Server-side DataTable for activity log
        if ($request->input('table')) {
            $from = $request->input('from');
            $to   = $request->input('to');

            $query = DB::connection('sqlsrv')
                ->table('spa_notices')
                ->select('file_number', 'notice_type as section', 'recipient_name as description', 'status', 'created_at')
                ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to,   fn($q) => $q->whereDate('created_at', '<=', $to))
                ->orderByDesc('created_at');

            $total = $query->count();
            $rows  = $query->skip($request->input('start', 0))
                           ->take($request->input('length', 10))
                           ->get()
                           ->map(function ($r, $i) use ($request) {
                               return [
                                   'DT_RowIndex' => $request->input('start', 0) + $i + 1,
                                   'section'     => 'Notice (' . ucfirst($r->section) . ' Serve)',
                                   'file_number' => $r->file_number ?? '—',
                                   'description' => 'Served to: ' . ($r->description ?? '—'),
                                   'officer'     => '—',
                                   'date'        => $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') : '—',
                                   'status'      => $r->status,
                               ];
                           });

            return response()->json([
                'draw'            => intval($request->input('draw')),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $rows,
            ]);
        }

        // Monthly activity (notices issued)
        $monthly = DB::connection('sqlsrv')
            ->table('spa_notices')
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('total', 'month')
            ->toArray();

        $monthlyData = array_map(fn($m) => $monthly[$m] ?? 0, range(1, 12));

        return response()->json([
            'summary' => [
                'land_records'   => SpaApplication::count(),
                'notices_served' => SpaNotice::where('status', 'served')->count(),
                'certificates'   => SpaCertificate::where('status', 'issued')->count(),
                'pending'        => SpaApplication::whereIn('status', ['open', 'in_progress'])->count(),
            ],
            'monthly'       => $monthlyData,
            'status_counts' => [
                SpaApplication::where('status', 'open')->count(),
                SpaApplication::where('status', 'in_progress')->count(),
                SpaApplication::where('status', 'approved')->count(),
                SpaApplication::where('status', 'certificate_issued')->count(),
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        $from = $request->input('from');
        $to   = $request->input('to');

        $rows = DB::connection('sqlsrv')
            ->table('spa_applications as a')
            ->select('a.file_number', 'a.owner_name', 'a.phone', 'a.location', 'a.land_use_type', 'a.existing_use', 'a.scenario', 'a.status', 'a.created_at')
            ->when($from, fn($q) => $q->whereDate('a.created_at', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('a.created_at', '<=', $to))
            ->orderByDesc('a.created_at')
            ->get();

        $filename = 'spa-report-' . now()->format('Y-m-d') . '.csv';

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($rows) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['File No', 'Owner Name', 'Phone', 'Location', 'Approved Use', 'Existing Use', 'Scenario', 'Status', 'Date']);
            foreach ($rows as $r) {
                fputcsv($fh, [$r->file_number, $r->owner_name, $r->phone, $r->location, $r->land_use_type, $r->existing_use, $r->scenario, $r->status, $r->created_at]);
            }
            fclose($fh);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function storeBill(Request $request)
    {
        $request->validate([
            'spa_application_id' => 'required|exists:sqlsrv.spa_applications,id',
            'bill_type'          => 'required|string|max:100',
            'amount'             => 'required|numeric|min:0',
        ]);

        // Numbering lives on the model so this and the automatic path
        // (SpaBillingService) cannot disagree about the next reference.
        $refId = \App\Models\SpaBill::nextReference();

        $bill = \App\Models\SpaBill::create([
            'spa_application_id' => $request->spa_application_id,
            'reference_id'       => $refId,
            'bill_type'          => $request->bill_type,
            'description'        => $request->description,
            'amount'             => $request->amount,
            'due_date'           => $request->due_date ?: null,
            'status'             => 'unpaid',
            'created_by'         => auth()->user()->name ?? auth()->id(),
        ]);

        return response()->json(['success' => true, 'id' => $bill->id, 'reference_id' => $refId, 'message' => "Bill {$refId} saved."]);
    }

    // ─── BILL ITEMS (the contravention tariff) ────────────────────────────────

    /** The tariff, for the Bill Items Setting editor. */
    public function billItems()
    {
        return response()->json([
            'success' => true,
            'data'    => \App\Models\SpaBillItem::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    /**
     * Replace the tariff with what the editor submitted.
     *
     * Rows the officer removed are DEACTIVATED, never deleted: bills already
     * raised reference them, and deleting would leave historical lines pointing
     * at nothing. Deactivated items simply stop being charged.
     */
    public function saveBillItems(Request $request)
    {
        $request->validate([
            'items'             => 'required|array',
            'items.*.name'      => 'required|string|max:150',
            'items.*.amount'    => 'required|numeric|min:0',
            'items.*.is_active' => 'required|boolean',
        ]);

        $actor = auth()->user()->name ?? auth()->id();
        $keptIds = [];

        DB::connection('sqlsrv')->transaction(function () use ($request, $actor, &$keptIds) {
            foreach ($request->input('items') as $i => $row) {
                $values = [
                    'name'       => trim($row['name']),
                    'amount'     => $row['amount'],
                    'is_active'  => (bool) $row['is_active'],
                    'sort_order' => $row['sort_order'] ?? ($i + 1),
                    'updated_by' => $actor,
                ];

                $item = ! empty($row['id'])
                    ? \App\Models\SpaBillItem::find($row['id'])
                    : null;

                if ($item) {
                    $item->update($values);
                } else {
                    // updateOrCreate on the name, because the unique index makes
                    // a re-added item collide with the row that was deactivated
                    // earlier rather than creating a duplicate.
                    $item = \App\Models\SpaBillItem::updateOrCreate(
                        ['name' => $values['name']],
                        $values + ['created_by' => $actor]
                    );
                }

                $keptIds[] = $item->id;
            }

            \App\Models\SpaBillItem::whereNotIn('id', $keptIds)->update([
                'is_active'  => 0,
                'updated_by' => $actor,
            ]);
        });

        $total = \App\Models\SpaBillItem::billable()->sum(fn ($i) => (float) $i->amount);

        return response()->json([
            'success' => true,
            'message' => 'Tariff saved. Each contravention will be billed ₦'.number_format($total, 2).'.',
        ]);
    }

    /**
     * The items on a bill, with what is still owed on each.
     *
     * Feeds the payment form so an officer collects against the actual items
     * rather than typing one lump figure. "Paid so far" is per ITEM, summed
     * from previous payments' lines.
     */
    public function billPaymentSheet(int $id)
    {
        $bill = \App\Models\SpaBill::with(['lines', 'application'])->findOrFail($id);

        $paidPerLine = \App\Models\SpaPaymentLine::whereIn('spa_bill_line_id', $bill->lines->pluck('id'))
            ->selectRaw('spa_bill_line_id, SUM(amount_paid) AS paid')
            ->groupBy('spa_bill_line_id')
            ->pluck('paid', 'spa_bill_line_id');

        $lines = $bill->lines->map(function ($line) use ($paidPerLine) {
            $paid = (float) ($paidPerLine[$line->id] ?? 0);

            return [
                'id'          => $line->id,
                'name'        => $line->name,
                'amount'      => (float) $line->amount,
                'paid'        => $paid,
                'outstanding' => max(0, (float) $line->amount - $paid),
            ];
        })->values();

        // Payments made before item-by-item entry existed have no lines, so the
        // per-item figures above cannot account for them. Report the bill-level
        // total separately rather than letting the form imply nothing is paid.
        $billPaid    = (float) \App\Models\SpaPayment::where('spa_bill_id', $bill->id)->sum('amount_paid');
        $allocated   = (float) $lines->sum('paid');
        $unallocated = max(0, round($billPaid - $allocated, 2));

        return response()->json([
            'success' => true,
            'bill'    => [
                'id'           => $bill->id,
                'reference_id' => $bill->reference_id,
                'file_number'  => optional($bill->application)->file_number,
                'owner_name'   => optional($bill->application)->owner_name,
                'amount'       => (float) $bill->amount,
                'status'       => $bill->status,
                'total_paid'   => $billPaid,
                'balance'      => max(0, (float) $bill->amount - $billPaid),
                'unallocated'  => $unallocated,
            ],
            'lines'   => $lines,
        ]);
    }

    /**
     * Record a payment, item by item.
     *
     * The total is SUMMED FROM THE ITEMS on the server and never taken from the
     * request: the browser sends what was collected against each item, and a
     * posted total could disagree with them, leaving a receipt whose lines do
     * not add up to its own figure.
     *
     * A single `amount_paid` is still accepted for bills that have no items
     * (older hand-entered ones), so those keep working unchanged.
     */
    public function recordBillPayment(Request $request)
    {
        $request->validate([
            'spa_bill_id'          => 'required|exists:sqlsrv.spa_bills,id',
            'payment_date'         => 'required|date',
            'lines'                => 'array',
            'lines.*.id'           => 'required|integer',
            'lines.*.amount_paid'  => 'required|numeric|min:0',
            'amount_paid'          => 'nullable|numeric|min:0',
        ]);

        $bill  = \App\Models\SpaBill::with('lines')->findOrFail($request->spa_bill_id);
        $posted = collect($request->input('lines', []))
            ->mapWithKeys(fn ($l) => [(int) $l['id'] => round((float) $l['amount_paid'], 2)])
            ->filter(fn ($amount) => $amount > 0);

        // Only items that belong to THIS bill, so a tampered or stale form
        // cannot post an allocation against another bill's item.
        $billLines = $bill->lines->keyBy('id');
        $unknown   = $posted->keys()->reject(fn ($id) => $billLines->has($id));

        if ($unknown->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'This form is out of date — reopen the bill and try again.',
            ], 422);
        }

        $total = $posted->isNotEmpty()
            ? round($posted->sum(), 2)
            : round((float) $request->input('amount_paid', 0), 2);

        if ($total <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Enter an amount against at least one item.',
            ], 422);
        }

        $alreadyPaid = (float) \App\Models\SpaPayment::where('spa_bill_id', $bill->id)->sum('amount_paid');
        $balance     = round((float) $bill->amount - $alreadyPaid, 2);

        // Refuse to collect more than is owed. Without this the bill goes 'paid'
        // while the receipt shows a figure nobody can reconcile against it.
        if ($total > $balance) {
            return response()->json([
                'success' => false,
                'message' => 'That is more than the outstanding balance of ₦'.number_format($balance, 2).'.',
            ], 422);
        }

        $payment = DB::connection('sqlsrv')->transaction(function () use ($bill, $posted, $billLines, $total, $request) {
            $payment = \App\Models\SpaPayment::create([
                'spa_bill_id'        => $bill->id,
                'spa_application_id' => $bill->spa_application_id,
                'amount_paid'        => $total,
                'receipt_number'     => $request->receipt_number,
                'payment_date'       => $request->payment_date,
                'payment_method'     => $request->payment_method ?? 'cash',
                'recorded_by'        => auth()->user()->name ?? auth()->id(),
            ]);

            foreach ($posted as $lineId => $amount) {
                \App\Models\SpaPaymentLine::create([
                    'spa_payment_id'   => $payment->id,
                    'spa_bill_line_id' => $lineId,
                    // Copied, not referenced — see SpaPaymentLine.
                    'name'             => $billLines[$lineId]->name,
                    'amount_paid'      => $amount,
                ]);
            }

            $totalPaid = \App\Models\SpaPayment::where('spa_bill_id', $bill->id)->sum('amount_paid');
            $bill->update(['status' => $totalPaid >= $bill->amount ? 'paid' : 'partial']);

            return $payment;
        });

        $bill->refresh();

        return response()->json([
            'success'     => true,
            'id'          => $payment->id,
            'receipt_url' => route('special-assignment.bills.receipt', $payment->id),
            'message'     => 'Payment of ₦'.number_format($total, 2).' recorded. Bill status: '.$bill->status.'.',
        ]);
    }

    /** Printable receipt for one payment, itemised as it was collected. */
    public function printBillReceipt(int $id)
    {
        $payment = \App\Models\SpaPayment::with(['lines', 'bill.application', 'bill.lines'])->findOrFail($id);

        // Everything paid on this bill BEFORE this receipt, so it can show what
        // remains without the reader having to gather up the other receipts.
        $priorPaid = (float) \App\Models\SpaPayment::where('spa_bill_id', $payment->spa_bill_id)
            ->where('id', '<', $payment->id)
            ->sum('amount_paid');

        return view('special_assignment.bills.receipt', compact('payment', 'priorPaid'));
    }

    // ─── PRIVATE HELPERS ──────────────────────────────────────────────────────

    // ─── MOBILE LOGIN METHODS ──────────────────────────────────────────────────

    public function showMobileLogin()
    {
        if (auth()->check()) {
            return redirect()->route('special-assignment.mobile');
        }
        return view('special_assignment.mobile.login');
    }

    public function submitMobileLogin(Request $request)
    {
        $credentials = $request->validate([
            'identifier' => 'required|string',
            'password'   => 'required|string|min:6',
        ]);

        $fieldType = filter_var($credentials['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (auth()->attempt([$fieldType => $credentials['identifier'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('special-assignment.mobile'))->with('success', 'Welcome back!');
        }

        return back()->withInput($request->only('identifier'))->withErrors(['error' => 'Invalid credentials.']);
    }

    public function mobileLogout(Request $request)
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('special-assignment.mobile.login');
    }

    public function showForgotPassword()
    {
        return view('special_assignment.mobile.forgot-password');
    }

    public function submitForgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Email not found.']);
        }

        $token = \Illuminate\Support\Str::random(60);
        \Illuminate\Support\Facades\DB::table('password_resets')->updateOrInsert(
            ['email' => $request->email],
            ['token' => bcrypt($token), 'created_at' => now()]
        );

        // TODO: Send reset email with token
        return back()->with('success', 'Password reset link sent to your email.');
    }

    public function showResetPassword($token)
    {
        return view('special_assignment.mobile.reset-password', compact('token'));
    }

    public function submitResetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
            'token'    => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Email not found.']);
        }

        $user->update(['password' => bcrypt($request->password)]);
        \Illuminate\Support\Facades\DB::table('password_resets')->where('email', $request->email)->delete();

        return redirect()->route('special-assignment.mobile.login')->with('success', 'Password reset successfully. Please log in.');
    }

    /**
     * Send a contravention notice by SMS. The wording is fixed by the Ministry
     * and lives on SpaNotice so this and the scheduled second-serve command
     * always send the same text.
     */
    /**
     * @return array{sent:bool, code:?string, reason:?string} The result plus why
     *         it failed, so the caller can say what happened instead of "SMS
     *         could not be sent" — which is unactionable on a production box
     *         where the log is not to hand.
     */
    private function sendNoticeSms(string $phone, string $name, string $type, ?string $fileNo): array
    {
        // Resolve per call: lastStatusCode()/lastFailureReason() describe the most
        // recent send on that instance, so a shared singleton would report stale state.
        $sms = new BulkSmsNgService();

        try {
            $sent = $sms->send($phone, SpaNotice::smsBody($type));

            if (!$sent) {
                Log::warning('SPAS: notice SMS not sent', [
                    'phone'     => $phone,
                    'file_no'   => $fileNo,
                    'recipient' => $name,
                    'type'      => $type,
                    'code'      => $sms->lastStatusCode(),
                    'reason'    => $sms->lastFailureReason(),
                ]);
            }

            return [
                'sent'   => $sent,
                'code'   => $sms->lastStatusCode(),
                'reason' => $sent ? null : ($sms->lastFailureReason() ?? 'unknown gateway failure'),
            ];
        } catch (\Throwable $e) {
            Log::warning('SPAS: SMS send failed', [
                'phone'     => $phone,
                'file_no'   => $fileNo,
                'recipient' => $name,
                'type'      => $type,
                'error'     => $e->getMessage(),
            ]);

            return ['sent' => false, 'code' => null, 'reason' => 'exception: '.$e->getMessage()];
        }
    }
}
