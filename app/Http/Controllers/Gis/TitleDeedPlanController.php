<?php

namespace App\Http\Controllers\Gis;

use App\Http\Controllers\Controller;
use App\Services\Tdp\TdpException;
use App\Services\Tdp\TdpLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * GIS → Title Deed Plan Management.
 *
 * The Title Deed Plans are files on the GIS server, one folder per LGA
 * (config/tdp.php). This screen browses them, previews them, and — for staff
 * who may write — uploads or replaces one, keeping the previous version.
 *
 * Nothing on this page assumes the folder is there: on a machine that is not
 * the GIS server every action degrades to a panel explaining what is missing.
 * The filesystem path is never handed to the browser; files are streamed
 * through file() by their path relative to the configured root, which
 * TdpLibrary::resolve() re-checks on every request.
 */
class TitleDeedPlanController extends Controller
{
    public function __construct(private TdpLibrary $library)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeView();

        $status = $this->library->status();
        $lga = trim((string) $request->query('lga', ''));
        $query = trim((string) $request->query('q', ''));

        $folders = $status['reachable'] ? $this->library->lgas() : [];

        // A folder that was renamed or removed since the link was made.
        if ($lga !== '' && !collect($folders)->contains(fn ($f) => strcasecmp($f['name'], $lga) === 0)) {
            $lga = '';
        }

        $results = $status['reachable']
            ? $this->library->search($lga ?: null, $query ?: null, (int) $request->query('page', 1))
            : ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => (int) config('tdp.per_page', 25), 'last_page' => 1];

        $unmatched = collect($folders)->where('matches_lga', false)->count();

        return view('gis.tdp.index', [
            'status' => $status,
            'folders' => $folders,
            'lga' => $lga,
            'query' => $query,
            'results' => $results,
            'totalPlans' => collect($folders)->sum('file_count'),
            'unmatchedFolders' => $unmatched,
            'knownLgas' => $this->library->knownLgas(),
            'canManage' => $this->allows('manage'),
            'uploadsEnabled' => $this->library->uploadsEnabled(),
            'allowedExtensions' => $this->library->allowedExtensions(),
            'maxUploadKb' => (int) config('tdp.max_upload_kb', 25600),
        ]);
    }

    /** The reconciliation report: LGA folders against the KLAES LGA list. */
    public function reconciliation()
    {
        $this->authorizeView();

        $status = $this->library->status();

        return view('gis.tdp.reconciliation', [
            'status' => $status,
            'report' => $status['reachable'] ? $this->library->reconcile() : null,
        ]);
    }

    /**
     * Stream one plan. `path` is relative to the configured root and is
     * re-validated by the library, so a crafted "..\..\web.config" resolves to
     * null and answers 404 — the same answer a file that is simply not there
     * gets, so the endpoint cannot be used to probe the disk.
     */
    public function file(Request $request)
    {
        $this->authorizeView();

        $path = (string) $request->query('path', '');
        $absolute = $this->library->resolve($path);

        abort_if($absolute === null, 404, 'That Title Deed Plan is not in the store.');

        $download = $request->boolean('download');
        $name = basename($absolute);

        return response()->file($absolute, [
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', $name) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /** Upload a new plan, or replace one (the old file is kept, timestamped). */
    public function store(Request $request)
    {
        $this->authorizeManage();

        $maxKb = max(1, (int) config('tdp.max_upload_kb', 25600));
        $extensions = implode(',', $this->library->allowedExtensions());

        $data = $request->validate([
            'lga' => ['required', 'string', 'max:120'],
            'file_number' => ['required', 'string', 'max:120'],
            'plan' => ['required', 'file', 'mimes:' . $extensions, 'max:' . $maxKb],
        ], [
            'plan.mimes' => 'A Title Deed Plan must be a ' . strtoupper(str_replace(',', ', ', $extensions)) . ' file.',
            'plan.max' => 'The plan may not be larger than ' . number_format($maxKb / 1024, 0) . ' MB.',
        ]);

        try {
            $result = $this->library->store($request->file('plan'), $data['lga'], $data['file_number']);
        } catch (TdpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('TDP upload failed', ['error' => $e->getMessage(), 'user_id' => Auth::id()]);

            return back()->withInput()->with('error', 'The plan could not be saved. The GIS server reported: ' . $e->getMessage());
        }

        $message = $result['replaced']
            ? $result['item']['name'] . ' replaced in ' . $result['item']['lga'] . '. The previous plan was kept as ' . $result['backup'] . '.'
            : $result['item']['name'] . ' added to ' . $result['item']['lga'] . '.';

        return redirect()
            ->route('tdp.index', ['lga' => $result['item']['lga'], 'q' => $result['item']['file_number']])
            ->with('success', $message);
    }

    // ── Access ───────────────────────────────────────────────────────────────

    private function authorizeView(): void
    {
        abort_unless($this->allows('view'), 403, 'Title Deed Plan Management needs the GIS - Title Deed Plan role.');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->allows('manage'), 403, 'Uploading a Title Deed Plan needs the GIS - Title Deed Plan role.');
    }

    /** Role check in the controller, as Land Charges and Configurable Entries do. */
    private function allows(string $ability): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        $held = array_map(fn ($r) => strtolower(trim((string) $r)), $user->assignedRoleNames());
        $allowed = array_map(fn ($r) => strtolower(trim((string) $r)), (array) (((array) config('tdp.roles', []))[$ability] ?? []));

        return array_intersect($allowed, $held) !== [];
    }
}
