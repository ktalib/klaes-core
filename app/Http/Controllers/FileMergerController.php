<?php

namespace App\Http\Controllers;

use App\Services\FileMergerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin UI for managing File Merger groups — the user selects files that will be merged
 * together (or that function as a single unit) and saves them as one lineage group. Auto-derived
 * groups are read-only here; manual groups can be created and removed.
 */
class FileMergerController extends Controller
{
    public function index()
    {
        $rows = DB::connection('sqlsrv')->table('file_merger')
            ->orderByDesc('created_at')
            ->orderBy('merger_id')
            ->get();

        $groups = [];
        foreach ($rows as $r) {
            $gid = $r->merger_id;
            if (!isset($groups[$gid])) {
                $groups[$gid] = [
                    'merger_id' => $gid,
                    'source' => $r->source,
                    'reason' => $r->reason,
                    'created_at' => $r->created_at,
                    'members' => [],
                ];
            }
            $groups[$gid]['members'][] = $r;
        }

        foreach ($groups as &$g) {
            usort($g['members'], fn ($a, $b) => ($a->role === 'parent' ? 0 : 1) <=> ($b->role === 'parent' ? 0 : 1));
        }
        unset($g);

        return view('file_merger.index', ['groups' => array_values($groups)]);
    }

    public function store(Request $request, FileMergerService $service)
    {
        $data = $request->validate([
            'files' => 'required|array|min:2',
            'files.*.file_number' => 'required|string|max:255',
            'files.*.role' => 'nullable|in:parent,child',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $mergerId = $service->createManualGroup($data['files'], ($data['reason'] ?? null) ?: 'Manual grouping');
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('file-merger.index')->with('success', "Merger group {$mergerId} saved.");
    }

    public function destroy(string $mergerId, FileMergerService $service)
    {
        $service->deleteGroup($mergerId);

        return redirect()->route('file-merger.index')->with('success', 'Merger group removed.');
    }

    /**
     * Autofill file title/location as the user builds a group.
     */
    public function lookup(Request $request, FileMergerService $service)
    {
        $file = trim((string) $request->query('file_number', ''));
        if ($file === '') {
            return response()->json(['exists' => false]);
        }

        $d = $service->describeFile($file);

        return response()->json([
            'exists' => $d['exists'],
            'title' => $d['title'],
            'location' => $d['location'],
        ]);
    }
}
