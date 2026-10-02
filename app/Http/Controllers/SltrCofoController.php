<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModules;
use App\Services\Cofo\SltrCofoPipeline;
use App\Services\Tdp\TdpLibrary;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * SLTR Certificate of Occupancy — the ST CofO workflow, copied for SLTR.
 *
 *     FRONT  =  C of O Front Page   KLAES / SLTR  (frontPage, generate, save)
 *     BACK   =  TDP                 KANGIS / GIS  (index, storeTdp)
 *
 * Two screens, as ST has: Front Page and CofO. Both render the same view over the same
 * SltrCofoPipeline rows, so the rail, tiles and table cannot disagree.
 *
 * The certificate record is the Deeds INSTRUMENT CAPTURE (instrument_type 'SLTR Certificate
 * of Occupancy'), not a table of SLTR's own. Generating the front page fills only the four
 * capture columns Deeds leaves empty — party_2_address, duration, start_date, cofo_date —
 * and never touches what Deeds registered. Routes key on the instrument_capture id.
 *
 * The front page uses the ST certificate wording for now (agreed 2026-10-01; to be replaced
 * when SLTR supplies its own). Every view and partial is SLTR's own copy under sltr_cofo/,
 * not shared with ST, so either module can change without breaking the other.
 */
class SltrCofoController extends Controller
{
    use AuthorizesModules;

    /** One module per screen — see App\Console\Commands\InstallSltrCofo. */
    public const MODULE_FRONT_PAGE = 'SLTR - CofO Front Page';

    public const MODULE_COFO = 'SLTR - CofO';

    private const ACCEPTED = ['pdf', 'jpg', 'jpeg', 'png'];

    private const MAX_KB = 10240;

    public function __construct(
        private SltrCofoPipeline $pipeline,
        private TdpLibrary $library
    ) {
    }

    /** CofO Workflow -> CofO: every file beside its TDP. */
    public function index(Request $request)
    {
        return $this->screen($request, 'cofo');
    }

    /** CofO Workflow -> Front Page: the same queue, driven from the front-page actions. */
    public function frontPage(Request $request)
    {
        return $this->screen($request, 'front_page');
    }

    private function screen(Request $request, string $screen)
    {
        $module = $screen === 'front_page' ? self::MODULE_FRONT_PAGE : self::MODULE_COFO;

        $this->authorizeModule($module, 'view');

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        // Tiles and the rail count the whole registry; only the table is filtered.
        $all = $this->pipeline->rows();
        $certificates = $this->filter($all, $search, $status);

        $stages = SltrCofoPipeline::STAGES;
        $stageCounts = $this->pipeline->counts($all);

        $totals = [
            'live' => $all->count(),
            'registered' => $stageCounts['registration'],
            'front_pages' => $stageCounts['front_page'],
            'complete' => $stageCounts['merge'],
            'awaiting' => $all->where('has_tdp', false)->count(),
            'originals' => $stageCounts['original'],
            'from_gis' => $all->where('tdp_source', 'gis')->count(),
            'from_upload' => $all->where('tdp_source', 'upload')->count(),
        ];

        return view('sltr_cofo.index', [
            'screen' => $screen,
            'certificates' => $certificates,
            'search' => $search,
            'status' => $status,
            'store' => $this->library->status(),
            'stages' => $stages,
            'stageCounts' => $stageCounts,
            'totals' => $totals,
            'canEdit' => $this->userCanDo($module, 'edit'),
            // The screen tabs only show a screen the user can open.
            'canSeeFrontPage' => $this->userCanDo(self::MODULE_FRONT_PAGE, 'view'),
            'canSeeCofo' => $this->userCanDo(self::MODULE_COFO, 'view'),
            'PageTitle' => 'SLTR Certificate of Occupancy',
            'PageDescription' => $screen === 'front_page' ? 'Front Page' : 'Front Page + TDP — the complete certificate',
        ]);
    }

    private function filter($rows, string $search, string $status)
    {
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function ($r) use ($needle) {
                foreach ([$r->file_no, $r->registration_number, $r->holder_name, $r->plot_no, $r->district, $r->lga] as $value) {
                    if ($value !== null && str_contains(mb_strtolower((string) $value), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        $rows = match ($status) {
            'complete' => $rows->filter(fn ($r) => $r->stages_done['merge']),
            'awaiting' => $rows->filter(fn ($r) => !$r->has_tdp),
            'registered' => $rows->filter(fn ($r) => $r->stages_done['registration']),
            'unregistered' => $rows->filter(fn ($r) => !$r->stages_done['registration']),
            'front_page_due' => $rows->filter(fn ($r) => $r->stages_done['registration'] && !$r->stages_done['front_page']),
            'front_page_done' => $rows->filter(fn ($r) => $r->stages_done['front_page']),
            default => $rows,
        };

        return $rows->values();
    }

    /** The front page form for one file (?file=). */
    public function generate(Request $request)
    {
        $this->authorizeModule(self::MODULE_FRONT_PAGE, 'edit');

        $row = $this->pipeline->row((string) $request->query('file', ''));

        if (!$row) {
            return redirect()->route('sltr-cofo.front-page')
                ->with('error', __('That file is not in the SLTR CofO workflow.'));
        }

        if (!$row->stages_done['registration']) {
            return redirect()->route('sltr-cofo.front-page')
                ->with('error', __('This file has not been registered with Deeds as an SLTR Certificate of Occupancy yet. Register it before generating its front page.'));
        }

        return view('sltr_cofo.generate', [
            'row' => $row,
            'values' => $this->pipeline->prefill($row),
            'PageTitle' => 'Generate SLTR Certificate of Occupancy',
            'PageDescription' => 'Front Page',
        ]);
    }

    /**
     * Generate (or correct) the front page: fill the capture's empty certificate fields.
     */
    public function save(Request $request)
    {
        $this->authorizeModule(self::MODULE_FRONT_PAGE, 'edit');

        $validated = $request->validate([
            'file_no' => 'required|string|max:100',
            'holder_address' => 'required|string|max:1000',
            'start_date' => 'required|date',
            'total_term' => 'required|integer|min:1|max:99',
        ]);

        $row = $this->pipeline->row($validated['file_no']);

        if (!$row || !$row->stages_done['registration']) {
            return back()->withInput()->with('error', __('This file is not registered with Deeds as an SLTR Certificate of Occupancy, so its front page cannot be generated.'));
        }

        $address = trim($validated['holder_address']);

        /*
         | Only the four columns Deeds capture leaves empty. Holder, plot, location, land use
         | and the registration particulars are the registered record and are not ours to
         | change from here.
         |
         | cofo_date is the issue date: set on first generation, then kept, so correcting
         | the address later does not re-date a certificate already printed.
        */
        DB::connection('sqlsrv')->table('instrument_capture')
            ->where('id', $row->capture_id)
            ->update([
                'party_2_address' => normalizeLocationText($address) ?: $address,
                'duration' => (string) (int) $validated['total_term'],
                'start_date' => Carbon::parse($validated['start_date'])->format('Y-m-d'),
                'cofo_date' => !empty($row->capture->cofo_date)
                    ? substr((string) $row->capture->cofo_date, 0, 10)
                    : now()->format('Y-m-d'),
                'updated_by' => (string) Auth::id(),
                'updated_at' => now(),
            ]);

        return redirect()->route('sltr-cofo.front-page', ['q' => $row->file_no])
            ->with('success', $row->stages_done['front_page'] ? __('Front page updated.') : __('Front page generated.'))
            ->with('sltr_cofo_print', $row->capture_id);
    }

    /** The front page on its own. */
    public function printFrontPage($captureId)
    {
        $this->authorizeModule(self::MODULE_FRONT_PAGE, 'print');

        $row = $this->pipeline->rowForCapture((int) $captureId);

        if (!$row || !$row->stages_done['front_page']) {
            return redirect()->route('sltr-cofo.front-page')->with('error', __('No front page has been generated for this certificate yet.'));
        }

        return view('sltr_cofo.print_front_page', [
            'cofo' => $this->pipeline->certificate($row),
            'PageTitle' => 'SLTR Certificate of Occupancy',
        ]);
    }

    /** Attach (or replace) an uploaded TDP — the fallback for a plan not in the GIS store. */
    public function storeTdp(Request $request, $captureId)
    {
        $this->authorizeModule(self::MODULE_COFO, 'edit');

        if (!Schema::connection('sqlsrv')->hasTable('sltr_cofo_tdp')) {
            return back()->with('error', __('TDP storage is not initialized. Run the latest database migrations.'));
        }

        $request->validate([
            'tdp_file' => 'required|file|mimes:' . implode(',', self::ACCEPTED) . '|max:' . self::MAX_KB,
        ], [
            'tdp_file.mimes' => __('The TDP must be a PDF or an image (:types).', ['types' => implode(', ', self::ACCEPTED)]),
            'tdp_file.max' => __('The TDP may not be larger than :size MB.', ['size' => self::MAX_KB / 1024]),
        ]);

        $row = $this->pipeline->rowForCapture((int) $captureId);

        if (!$row || !$row->stages_done['front_page']) {
            return back()->with('error', __('Generate the front page before filing its Title Deed Plan.'));
        }

        $file = $request->file('tdp_file');
        $filePath = null;

        try {
            $fileName = 'sltr_tdp_' . $row->capture_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('sltr_tdp', $fileName, 'public');

            DB::connection('sqlsrv')->transaction(function () use ($row, $file, $filePath) {
                // Superseded, never deleted: the old plan may back a printed certificate.
                DB::connection('sqlsrv')->table('sltr_cofo_tdp')
                    ->where('file_no', $row->file_no)
                    ->where('is_active', 1)
                    ->update(['is_active' => 0, 'updated_at' => now()]);

                DB::connection('sqlsrv')->table('sltr_cofo_tdp')->insert([
                    'file_no' => $row->file_no,
                    'instrument_capture_id' => $row->capture_id,
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

            \Log::error('SLTR TDP upload failed', ['capture' => $row->capture_id, 'error' => $e->getMessage()]);

            return back()->with('error', __('Failed to attach the TDP: :message', ['message' => $e->getMessage()]));
        }

        return back()->with('success', __('TDP attached. The complete certificate can now be printed.'));
    }

    /** The complete certificate — front page, then the TDP as the back. */
    public function printComplete($captureId)
    {
        $this->authorizeModule(self::MODULE_COFO, 'print');

        $row = $this->pipeline->rowForCapture((int) $captureId);

        if (!$row || !$row->stages_done['front_page']) {
            return back()->with('error', __('No front page has been generated for this certificate yet.'));
        }

        // The same resolution the screen used: the GIS store first, the upload second.
        if ($row->tdp_source === 'gis') {
            $plan = $row->tdp_plan;
            $tdpUrl = route('tdp.file', ['path' => $plan['relative_path']]);
            $tdpIsPdf = $plan['extension'] === 'pdf';
            $tdp = (object) [
                'original_name' => $plan['name'],
                'file_path' => $plan['relative_path'],
            ];
        } elseif ($row->tdp_source === 'upload') {
            $tdp = $row->tdp;
            $tdpUrl = Storage::disk('public')->url($tdp->file_path);
            $tdpIsPdf = str_contains(strtolower((string) $tdp->mime_type), 'pdf');
        } else {
            return back()->with('error', __('This certificate has no TDP yet, so it has no back page.'));
        }

        return view('sltr_cofo.print_complete', [
            'cofo' => $this->pipeline->certificate($row),
            'tdp' => $tdp,
            'tdpUrl' => $tdpUrl,
            'tdpIsPdf' => $tdpIsPdf,
            'PageTitle' => 'SLTR Certificate of Occupancy',
        ]);
    }
}
