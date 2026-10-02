<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * ST Certificate of Occupancy — the complete, two-sided certificate.
 *
 *     FRONT  =  C of O Front Page   KLAES / ST   (CofoController, programmes.certificates)
 *     BACK   =  TDP                 KANGIS / GIS (this controller)
 *
 * This is the "CofO" item under Certificate -> CofO Workflow in the ST sidebar. The item
 * above it, "Front Page", is the existing programmes.certificates screen; there is one front
 * page and this does not duplicate it — it pairs each generated front page with its TDP and
 * prints the two together.
 *
 * On ordering: a certificate with no TDP yet is NORMAL, not an error. The TDP is produced by
 * a different department and is explicitly not required to arrive before the front page. The
 * only hard rule is the obvious one — the combined certificate cannot be printed without a
 * back page.
 *
 * On intake, the back page is looked for in two places, in this order:
 *
 *   1. The KANGIS Title Deed Plan store — C:\Kano State\TDP\<LGA>\<file number>.pdf, one
 *      folder per Kano LGA, read through App\Services\Tdp\TdpLibrary. This is the real
 *      source: GIS produces the plans there and nothing is copied into KLAES.
 *
 *   2. An upload attached on this screen, for a plan that has not reached the GIS store yet.
 *      Kept deliberately — it is what makes the workflow usable before every plan is filed.
 *
 * A store that is unreachable is not a failure: on any machine that is not the GIS server
 * the folder is simply absent, resolution falls through to the upload, and the screen says so.
 */
class StCofoTdpController extends Controller
{
    use AuthorizesModules;

    public function __construct(
        private \App\Services\Tdp\TdpLibrary $library,
        private \App\Services\Cofo\StCofoPipeline $pipeline
    ) {
    }

    /** Module this screen belongs to, as user_roles spells it. */
    private const MODULE = 'ST - Certificate';

    private const TABLE = 'st_cofo_tdp';

    /** Accepted back-page formats. PDF prints properly; images are what scanners produce. */
    private const ACCEPTED = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_KB = 10240; // 10MB

    /**
     * The CofO Workflow -> CofO screen: every generated front page beside its TDP.
     */
    public function index(Request $request)
    {
        $this->authorizeModule(self::MODULE, 'view');

        $PageTitle = 'ST Certificate of Occupancy';
        $PageDescription = 'Front Page + TDP — the complete certificate';

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $certificates = $this->certificates($search, $status);

        // So the screen can say plainly where back pages are coming from, and why some are
        // still missing when the GIS store cannot be reached from this machine.
        $store = $this->library->status();

        $stages = self::STAGES;

        /*
         | Tiles and the stage rail count the WHOLE registry, not the filtered page.
         |
         | A tile that moves when you type in the search box is not a summary, it is a second
         | copy of the result count — and clicking a tile to filter by it would then change
         | the number you just clicked. So the totals come from an unfiltered pass, exactly
         | as the ALAES queue does.
        */
        $all = ($search === '' && $status === '') ? $certificates : $this->certificates('', '');

        $totals = [
            'live' => $all->count(),
            // Units whose CofO data has actually been entered, as opposed to units merely
            // waiting in the queue. The two were conflated on the tiles before the queue
            // became unit-driven, which made 69 units look like 69 captured certificates.
            'captured' => $all->filter(fn ($r) => (bool) $r->cofo_id)->count(),
            'rofo' => $all->filter(fn ($r) => $r->stages_done['rofo'])->count(),
            'registered' => $all->filter(fn ($r) => $r->stages_done['registration'])->count(),
            'complete' => $all->filter(fn ($r) => $r->stages_done['merge'])->count(),
            'awaiting' => $all->where('has_tdp', false)->count(),
            'originals' => $all->filter(fn ($r) => $r->stages_done['original'])->count(),
            'from_gis' => $all->where('tdp_source', 'gis')->count(),
            'from_upload' => $all->where('tdp_source', 'upload')->count(),
        ];

        $stageCounts = [];
        foreach (array_keys($stages) as $key) {
            $stageCounts[$key] = $all->filter(fn ($r) => $r->stages_done[$key] ?? false)->count();
        }

        return view('programmes.cofo_complete', compact(
            'certificates',
            'search',
            'status',
            'store',
            'stages',
            'stageCounts',
            'totals',
            'PageTitle',
            'PageDescription'
        ));
    }

    /**
     * Attach (or replace) the TDP for one certificate.
     */
    public function store(Request $request, $subApplicationId)
    {
        $this->authorizeModule(self::MODULE, 'edit');

        if (!Schema::connection('sqlsrv')->hasTable(self::TABLE)) {
            return back()->with('error', __('TDP storage is not initialized. Run the latest database migrations.'));
        }

        $request->validate([
            'tdp_file' => 'required|file|mimes:' . implode(',', self::ACCEPTED) . '|max:' . self::MAX_KB,
        ], [
            'tdp_file.mimes' => __('The TDP must be a PDF or an image (:types).', ['types' => implode(', ', self::ACCEPTED)]),
            'tdp_file.max' => __('The TDP may not be larger than :size MB.', ['size' => self::MAX_KB / 1024]),
        ]);

        $subApplicationId = (int) $subApplicationId;

        /*
         | Validate against the CERTIFICATE, not the unit.
         |
         | st_cofo.sub_application_id does not always match subapplications.id. The live row
         | is the proof: its sub_application_id is 2142, which file_indexings and rofo also
         | use for this file, while the unit itself sits at subapplications.id = 49. Those
         | three agree with each other and disagree with subapplications, so 2142 is an
         | older identifier that survived a rebuild of that table.
         |
         | Checking subapplications here refused a TDP for a certificate that is listed on
         | the screen one click earlier — "That unit no longer exists" for a certificate
         | plainly in front of the officer. The certificate is the record; the unit row only
         | ever supplied the holder photograph.
        */
        $cofo = DB::connection('sqlsrv')->table('st_cofo')
            ->where('sub_application_id', $subApplicationId)
            ->where('is_active', 1)
            ->first();

        if (!$cofo) {
            return back()->with('error', __('No Certificate of Occupancy front page exists for that unit, so there is nothing to attach a Title Deed Plan to.'));
        }

        /*
         | The certificate must be registered before its back page is filed.
         |
         | An unregistered certificate has no registration particulars, so its front page
         | cannot be issued — a TDP attached now would be waiting on a front page that
         | cannot be printed. The Find TDP button is disabled for these rows, but a disabled
         | button is markup; this is the rule.
        */
        if (!$this->pipeline->hasParticulars($cofo)) {
            return back()->with('error', __('This certificate has not been registered with Deeds yet. Register it before filing its Title Deed Plan.'));
        }

        $file = $request->file('tdp_file');
        $filePath = null;

        try {
            $fileName = 'st_tdp_' . $subApplicationId . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('st_tdp', $fileName, 'public');

            DB::connection('sqlsrv')->transaction(function () use ($subApplicationId, $cofo, $file, $filePath) {
                // Supersede rather than delete: the previous TDP may already back a printed
                // certificate, and this is a land registry.
                DB::connection('sqlsrv')->table(self::TABLE)
                    ->where('sub_application_id', $subApplicationId)
                    ->where('is_active', 1)
                    ->update(['is_active' => 0, 'updated_at' => now()]);

                DB::connection('sqlsrv')->table(self::TABLE)->insert([
                    'sub_application_id' => $subApplicationId,
                    'st_cofo_id' => $cofo->id ?? null,
                    'file_path' => $filePath,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'is_active' => 1,
                    'uploaded_by' => Auth::id(),
                    'uploaded_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }

            \Log::error('ST TDP upload failed', ['unit' => $subApplicationId, 'error' => $e->getMessage()]);

            return back()->with('error', __('Failed to attach the TDP: :message', ['message' => $e->getMessage()]));
        }

        return back()->with('success', __('TDP attached. The complete certificate can now be printed.'));
    }

    /**
     * Print the complete certificate — front page, then the TDP as the back.
     */
    public function printComplete($subApplicationId)
    {
        $this->authorizeModule(self::MODULE, 'print');

        $subApplicationId = (int) $subApplicationId;

        /*
         | LEFT join, deliberately.
         |
         | The certificate is the record; the unit row only supplies the holder photograph.
         | An inner join means a certificate whose unit row has gone refuses to print while
         | the list screen — which left-joins — still shows it, and the two screens disagree
         | about whether the same certificate exists. The live row does exactly this today:
         | st_cofo.sub_application_id 2142 has no subapplications row.
         |
         | A missing unit costs the passport photo, not the certificate.
        */
        $cofo = DB::connection('sqlsrv')->table('st_cofo as c')
            ->leftJoin('subapplications as s', 'c.sub_application_id', '=', 's.id')
            ->where('c.sub_application_id', $subApplicationId)
            ->where('c.is_active', 1)
            ->select('c.*', 's.passport', 's.multiple_owners_passport')
            ->first();

        if (!$cofo) {
            return back()->with('error', __('No Certificate of Occupancy front page has been generated for this unit yet.'));
        }

        /*
         | The back page is resolved the same way the list screen resolves it, and in the
         | same order — the GIS store first, the upload second. If printing only knew about
         | uploads, a certificate the screen reports as "On GIS server" would refuse to
         | print, which is the worst kind of disagreement between two views of one record.
         |
         | The LGA is a hint, not a filter, for the reason given in certificates().
        */
        $plan = null;

        if ($this->library->isReachable() && trim((string) $cofo->file_no) !== '') {
            $plan = $this->library->findForFileNumber((string) $cofo->file_no, $cofo->property_lga ?: null)
                ?? $this->library->findForFileNumber((string) $cofo->file_no);
        }

        $tdp = $plan === null ? $this->activeTdp($subApplicationId) : null;

        if ($plan === null && !$tdp) {
            return back()->with('error', __('This certificate has no TDP yet, so it has no back page.'));
        }

        if ($plan !== null) {
            // Streamed through tdp.file, never as a filesystem path.
            $tdpUrl = route('tdp.file', ['path' => $plan['relative_path']]);
            $tdpIsPdf = $plan['extension'] === 'pdf';

            // The print view expects an object with original_name / file_path, so the plan
            // is presented in that shape rather than the view learning about two sources.
            $tdp = (object) [
                'original_name' => $plan['name'],
                'file_path' => $plan['relative_path'],
                'mime_type' => $tdpIsPdf ? 'application/pdf' : 'image/' . $plan['extension'],
            ];
        } else {
            $tdpUrl = Storage::disk('public')->url($tdp->file_path);
            $tdpIsPdf = str_contains(strtolower((string) $tdp->mime_type), 'pdf');
        }

        return view('programmes.print_cofo_complete', [
            'cofo' => $cofo,
            'tdp' => $tdp,
            'tdpUrl' => $tdpUrl,
            'tdpIsPdf' => $tdpIsPdf,
            'PageTitle' => 'Certificate of Occupancy — Complete',
            'PageDescription' => 'Front Page + TDP',
        ]);
    }

    /** The TDP currently backing this unit's certificate, if any. */
    private function activeTdp(int $subApplicationId)
    {
        if (!Schema::connection('sqlsrv')->hasTable(self::TABLE)) {
            return null;
        }

        return DB::connection('sqlsrv')->table(self::TABLE)
            ->where('sub_application_id', $subApplicationId)
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Generated front pages, each with its TDP state.
     *
     * Driven from st_cofo rather than from the applications list: a row here means a front
     * page exists, which is the thing this screen pairs a back page with.
     */
    private function certificates(string $search, string $status)
    {
        $connection = DB::connection('sqlsrv');

        /*
         | The queue is driven by the UNIT, not by st_cofo.
         |
         | A row in st_cofo means CofO data has already been captured — stage 3 onwards.
         | Driving the list from it showed one certificate while 68 units had RofOs issued
         | and were sitting, invisible, waiting to enter the CofO process. A workflow queue
         | has to show what is IN the workflow, not only what has already moved through it.
         |
         | So: every unit with an issued RofO, plus any unit that already carries a CofO
         | record even if its RofO is filed under a stale id (see canonicalUnitId). The
         | certificate is attached where one exists.
         |
         | st_cofo is joined on BOTH the unit id and the file number, because the two do not
         | always agree — the live certificate carries a pre-renumbering id.
        */
        $rows = $connection->table('subapplications as s')
            ->leftJoin('mother_applications as m', 'm.id', '=', 's.main_application_id')
            ->leftJoin('rofo as r', function ($join) {
                $join->on('r.sub_application_id', '=', 's.id')
                    ->where('r.active', 1)
                    ->whereNotNull('r.rofo_no');
            })
            ->leftJoin('st_cofo as c', function ($join) {
                $join->on('c.sub_application_id', '=', 's.id')
                    ->where('c.is_active', 1);
            })
            ->leftJoin('st_cofo as c2', function ($join) {
                // The legacy link: same file number, different unit id.
                $join->on('c2.file_no', '=', 's.fileno')
                    ->where('c2.is_active', 1);
            })
            ->where(function ($query) {
                $query->whereNull('s.is_deleted')->orWhere('s.is_deleted', 0);
            })
            // In the workflow means: a RofO was issued, or a CofO record already exists.
            ->where(function ($query) {
                $query->whereNotNull('r.rofo_no')
                    ->orWhereNotNull('c.id')
                    ->orWhereNotNull('c2.id');
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('s.fileno', 'like', $like)
                        ->orWhere('c.certificate_number', 'like', $like)
                        ->orWhere('c2.certificate_number', 'like', $like)
                        ->orWhere('s.first_name', 'like', $like)
                        ->orWhere('s.surname', 'like', $like)
                        ->orWhere('s.corporate_name', 'like', $like)
                        ->orWhere('m.property_plot_no', 'like', $like);
                });
            })
            ->select(
                's.id as unit_id',
                's.fileno as file_no',
                's.main_application_id',
                's.first_name',
                's.middle_name',
                's.surname',
                's.corporate_name',
                's.multiple_owners_names',
                's.block_number',
                's.floor_number',
                's.unit_number',
                's.is_sua_unit',
                'm.property_district',
                'm.property_lga',
                'm.property_plot_no',
                'm.land_use as mother_land_use',
                'm.scheme_no',
                'r.rofo_no',
                DB::raw('COALESCE(c.id, c2.id) as cofo_id'),
                DB::raw('COALESCE(c.certificate_number, c2.certificate_number) as certificate_number'),
                DB::raw('COALESCE(c.holder_name, c2.holder_name) as cofo_holder_name'),
                DB::raw('COALESCE(c.land_use, c2.land_use) as cofo_land_use'),
                DB::raw('COALESCE(c.plot_no, c2.plot_no) as plot_no'),
                DB::raw('COALESCE(c.block_no, c2.block_no) as block_no'),
                DB::raw('COALESCE(c.floor_no, c2.floor_no) as floor_no'),
                DB::raw('COALESCE(c.flat_no, c2.flat_no) as flat_no'),
                DB::raw('COALESCE(c.issued_date, c2.issued_date) as issued_date'),
                DB::raw('COALESCE(c.RegNo, c2.RegNo) as RegNo'),
                DB::raw('COALESCE(c.page_no, c2.page_no) as page_no'),
                // The id the CERTIFICATE carries, which is what st_cofo_tdp and the print
                // route key on; null when no certificate exists yet.
                DB::raw('COALESCE(c.sub_application_id, c2.sub_application_id) as cofo_unit_id')
            )
            ->orderByDesc('s.id')
            ->get();

        /*
         | One identity per row.
         |
         | `sub_application_id` stays the key everything else uses, and it must be the id the
         | CERTIFICATE carries when there is one — st_cofo_tdp rows and the print route were
         | written against that id. Where no certificate exists yet, the unit's own id is the
         | only one there is.
        */
        $rows = $rows->map(function ($row) {
            $row->sub_application_id = (int) ($row->cofo_unit_id ?: $row->unit_id);
            $row->land_use = $row->cofo_land_use ?: $row->mother_land_use;
            $row->plot_no = $row->plot_no ?: $row->property_plot_no;
            $row->block_no = $row->block_no ?: $row->block_number;
            $row->flat_no = $row->flat_no ?: $row->unit_number;

            // The holder: the certificate's name when captured, otherwise the applicant.
            if (!empty($row->cofo_holder_name)) {
                $row->holder_name = $row->cofo_holder_name;
            } elseif (!empty($row->corporate_name)) {
                $row->holder_name = $row->corporate_name;
            } elseif (!empty($row->multiple_owners_names)) {
                $owners = json_decode((string) $row->multiple_owners_names, true);
                $row->holder_name = is_array($owners) ? implode(', ', $owners) : null;
            } else {
                $row->holder_name = trim(implode(' ', array_filter([
                    $row->first_name ?? null,
                    $row->surname ?? null,
                ])));
            }

            return $row;
        });

        $tdps = $this->tdpLookup($rows->pluck('sub_application_id'));

        /*
         | The back page has two possible sources, and the GIS store wins.
         |
         |   1. KANGIS / GIS — C:\Kano State\TDP\<LGA>\<file number>.pdf, read straight off
         |      the GIS server by TdpLibrary. This is where TDPs actually come from, and the
         |      spec is explicit that the ST officer must not have to recreate GIS
         |      information that already exists.
         |
         |   2. An upload attached on this screen, kept as the fallback for a plan that has
         |      not reached the GIS store yet.
         |
         | The store being unreachable is not an error — on any machine that is not the GIS
         | server it simply is not there, so resolution quietly falls through to the upload.
        */
        $library = $this->library;
        $storeReady = $library->isReachable();

        $rows = $rows->map(function ($row) use ($tdps, $library, $storeReady) {
            $key = (int) $row->sub_application_id;

            $row->tdp_source = null;
            $row->tdp_plan = null;

            if ($storeReady && trim((string) $row->file_no) !== '') {
                /*
                 | The LGA is a HINT, not a filter.
                 |
                 | st_cofo.property_lga holds what was captured, and what was captured is not
                 | always a folder name — the one live row says "KUMBTSO", which is Kumbotso
                 | misspelled. Scoping the scan to that folder finds nothing and the plan
                 | looks missing when it is sitting in the store.
                 |
                 | So: try the named LGA first, because it is the fast path and disambiguates
                 | a file number filed under two LGAs; then fall back to the whole store.
                */
                $plan = $library->findForFileNumber((string) $row->file_no, $row->property_lga ?: null)
                    ?? $library->findForFileNumber((string) $row->file_no);

                if ($plan !== null) {
                    $row->tdp_plan = $plan;
                    $row->tdp_source = 'gis';
                }
            }

            $row->tdp = $tdps[$key] ?? null;

            if ($row->tdp_source === null && $row->tdp !== null) {
                $row->tdp_source = 'upload';
            }

            $row->has_tdp = $row->tdp_source !== null;

            return $row;
        });

        // Stage state, once the TDP is known — 'tdp' is one of the six stages.
        // Only the ids the CERTIFICATES carry; the unit's own RofO already came in on the
        // join above. This is what catches a RofO stranded under a renumbered id.
        $rofo = $this->rofoLookup($rows->pluck('sub_application_id'));
        $registered = $this->registeredLookup($rows->pluck('sub_application_id'), $rows->pluck('file_no'));

        $rows = $rows->map(function ($row) use ($rofo, $registered) {
            $key = strtoupper(trim((string) $row->file_no));

            if (isset($this->registeredFileNumbers[$key])) {
                $registered[(int) $row->sub_application_id] = true;
            }

            /*
             | The unit id the ST module uses today, resolved by file number.
             |
             | st_cofo, file_indexings and rofo all carry a PRE-REBUILD subapplications id
             | (2142 for the live row) while subapplications itself has renumbered (49).
             | 46 of 114 rofo rows point at an id that no longer exists, and the RofO
             | Applications screen — which joins on subapplications.id — therefore reports
             | "Not Generated" for RofOs that are signed and numbered.
             |
             | Looking under both ids finds the RofO; comparing them is what tells us the
             | link is stale, which the officer needs to know because the RofO screen will
             | keep disagreeing until the data is repaired.
            */
            $row->canonical_unit_id = $this->canonicalUnitId((string) $row->file_no);
            $row->rofo_stranded = false;

            return $this->decorateStages($row, $rofo, $registered);
        });

        if ($status === 'complete') {
            $rows = $rows->filter(fn ($r) => $r->has_tdp)->values();
        } elseif ($status === 'awaiting') {
            $rows = $rows->filter(fn ($r) => !$r->has_tdp)->values();
        } elseif ($status === 'registered') {
            $rows = $rows->filter(fn ($r) => $r->stages_done['registration'] ?? false)->values();
        }

        return $rows;
    }

    /**
     * @return array<int, object>  sub_application_id => active TDP row
     */
    private function tdpLookup($subApplicationIds): array
    {
        $ids = collect($subApplicationIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty() || !Schema::connection('sqlsrv')->hasTable(self::TABLE)) {
            return [];
        }

        $out = [];

        foreach ($ids->chunk(500) as $chunk) {
            $rows = DB::connection('sqlsrv')->table(self::TABLE)
                ->whereIn('sub_application_id', $chunk->all())
                ->where('is_active', 1)
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $out[(int) $row->sub_application_id] = $row;
            }
        }

        return $out;
    }

    /*
    |---------------------------------------------------------------------------
    | The workflow itself
    |---------------------------------------------------------------------------
    |
    | Six stages, in the order the brief lists them:
    |
    |     RofO -> CofO Registration (Deeds) -> Front Page / White Copy
    |          -> Title Deed Plan -> Merge -> Original
    |
    | The first two are PRE-CONDITIONS: they normally happened elsewhere before the record
    | reached this screen, so they are shown as state and never blocked on. The workflow
    | proper begins at the front page.
    |
    | Ordering is deliberately NOT enforced. A front page may exist while its TDP is still
    | pending and that is a normal state, not a fault — the TDP comes from another
    | department. The one real constraint is that Merge needs both sides, because the
    | certificate has no back without the TDP.
    */

    /**
     * The stage list lives in StCofoPipeline, not here.
     *
     * The Front Page screen renders the same rail, and two copies of this array would drift
     * apart the first time anyone added a stage to one screen. The per-row stage DETECTION
     * below stays in this controller because it works from rows already loaded for the
     * table; the service re-queries, which is right for a screen that has no such rows.
     */
    public const STAGES = \App\Services\Cofo\StCofoPipeline::STAGES;

    /**
     * Which stages this certificate has reached, the stage it is waiting on, and the
     * status pill that summarises both.
     */
    private function decorateStages(object $row, array $rofo, array $registered): object
    {
        $key = (int) $row->sub_application_id;

        /*
         | A row in st_cofo is CAPTURED CofO data, not an issued front page.
         |
         | The front page prints "registered as No. X at Page Y in Volume Z", so until the
         | certificate carries its registration particulars there is nothing to print. The
         | brief puts registration (Deeds) before the front page (ST) for that reason, and
         | treating every captured row as a finished front page showed stage 3 complete
         | while stage 2 was still empty — the pipeline running backwards.
         |
         | `captured` is tracked separately so the screen can say what is actually true:
         | the data is in, the certificate is not yet registered.
        */
        $registeredHere = isset($registered[$key]);
        $hasParticulars = $this->pipeline->hasParticulars($row);

        /*
         | A RofO counts if it exists under EITHER id — the one the certificate carries or
         | the one subapplications uses now. When only the legacy id matches, the RofO is
         | real but stranded: the RofO Applications screen cannot see it and will keep
         | showing "Not Generated" until the link is repaired. That is flagged, not hidden,
         | because silently dropping a signed RofO would be the worse error.
        */
        /*
         | The RofO is joined on the UNIT's own id, which is how the RofO Applications
         | screen finds it too — so the two screens agree by construction.
         |
         | `$rofo` is consulted only to catch the other case: a signed RofO filed under the
         | id the CERTIFICATE carries, which subapplications has since renumbered away from.
         | 46 of 114 rofo rows are in that state. Such a RofO is real, so it counts — but it
         | is flagged, because the RofO screen cannot see it and will keep saying
         | "Not Generated" until the link is repaired.
        */
        $rofoOnUnit = trim((string) ($row->rofo_no ?? '')) !== '';
        $rofoUnderLegacyId = isset($rofo[$key]);

        $row->rofo_stranded = !$rofoOnUnit && $rofoUnderLegacyId;

        $row->captured = true;
        $row->has_particulars = $hasParticulars;

        $done = [
            'rofo' => $rofoOnUnit || $rofoUnderLegacyId,
            'registration' => $registeredHere || $hasParticulars,
            'front_page' => $hasParticulars,
            'tdp' => $row->has_tdp,
        ];

        // Merge is not a stored artefact yet: having both sides IS being merged, because
        // the print pairs them on demand. When an Original is introduced it will be the
        // first of these with a record of its own behind it.
        $done['merge'] = $done['front_page'] && $done['tdp'];
        $done['original'] = false;

        $row->stages_done = $done;
        $row->stages_complete = count(array_filter($done));

        // The next thing anyone can actually act on, skipping the two pre-conditions —
        // they are reported, never chased from here.
        $current = 'original';
        foreach (['registration', 'front_page', 'tdp', 'merge', 'original'] as $stage) {
            if (!$done[$stage]) {
                $current = $stage;
                break;
            }
        }

        $row->current_stage = $current;

        $row->fsm_status = match (true) {
            // Captured but not registered: the front page cannot be issued yet, and saying
            // "awaiting TDP" here would point the officer at the wrong department.
            !$done['registration'] => 'PENDING_REGISTRATION',
            !$done['tdp'] => 'PENDING_TDP',
            !$done['original'] => 'GENERATING_DOCUMENTS',
            default => 'COMPLETED',
        };

        return $row;
    }

    /**
     * @return array<int, object>  sub_application_id => the active RofO row
     */
    /**
     * The unit id `subapplications` uses for this file number today.
     *
     * Resolved by file number rather than by id, because the id is exactly what went stale.
     * Returns 0 when there is no unit row — which is itself normal here, since the
     * certificate is the record and the unit may have been renumbered away.
     */
    private function canonicalUnitId(string $fileNumber): int
    {
        $fileNumber = trim($fileNumber);

        if ($fileNumber === '') {
            return 0;
        }

        if (!array_key_exists($fileNumber, $this->canonicalUnitIds)) {
            $this->canonicalUnitIds[$fileNumber] = (int) DB::connection('sqlsrv')
                ->table('subapplications')
                ->where('fileno', $fileNumber)
                ->value('id');
        }

        return $this->canonicalUnitIds[$fileNumber];
    }

    /** Per-request cache for canonicalUnitId(). */
    private array $canonicalUnitIds = [];

    private function rofoLookup($subApplicationIds): array
    {
        $ids = collect($subApplicationIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty() || !Schema::connection('sqlsrv')->hasTable('rofo')) {
            return [];
        }

        $out = [];

        foreach ($ids->chunk(500) as $chunk) {
            $rows = DB::connection('sqlsrv')->table('rofo')
                ->whereIn('sub_application_id', $chunk->all())
                ->where('active', 1)
                ->whereNotNull('rofo_no')
                ->get();

            foreach ($rows as $row) {
                $out[(int) $row->sub_application_id] = $row;
            }
        }

        return $out;
    }

    /**
     * Deeds registration, from EITHER register.
     *
     * KLAES has two that do not know about each other: SectionalCofOReg (written by the ST
     * registration screen) and deed_registrations typed 'Sectional Titling CofO'. A
     * certificate registered through either one is registered, so both are read here rather
     * than picking a winner — which register should own this is a decision for later, and
     * guessing it would under-report today.
     *
     * @return array<int, true>  sub_application_id => registered
     */
    private function registeredLookup($subApplicationIds, $fileNumbers): array
    {
        $ids = collect($subApplicationIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $out = [];
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('SectionalCofOReg')) {
            foreach ($ids->chunk(500) as $chunk) {
                $rows = DB::connection('sqlsrv')->table('SectionalCofOReg')
                    ->whereIn('sub_application_id', $chunk->all())
                    ->where('status', 'registered')
                    ->pluck('sub_application_id');

                foreach ($rows as $id) {
                    $out[(int) $id] = true;
                }
            }
        }

        // The other register keys on the file number, not the unit id, so the two are
        // bridged here rather than in SQL.
        $numbers = collect($fileNumbers)->filter()->map(fn ($n) => trim((string) $n))->unique()->values();

        if ($numbers->isNotEmpty() && $schema->hasTable('deed_registrations')) {
            $byNumber = [];

            foreach ($numbers->chunk(500) as $chunk) {
                $rows = DB::connection('sqlsrv')->table('deed_registrations')
                    ->whereIn('fileno', $chunk->all())
                    ->where('instrument_type', 'Sectional Titling CofO')
                    ->where('status', 'registered')
                    ->pluck('fileno');

                foreach ($rows as $number) {
                    $byNumber[strtoupper(trim((string) $number))] = true;
                }
            }

            $this->registeredFileNumbers = $byNumber;
        }

        return $out;
    }

    /** File numbers found in deed_registrations, filled by registeredLookup(). */
    private array $registeredFileNumbers = [];
}
