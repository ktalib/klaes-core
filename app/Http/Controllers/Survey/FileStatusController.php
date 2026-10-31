<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Services\FileStatusLookupService;
use Illuminate\Http\Request;

/**
 * Survey > File Status (CR 30102026 item 29, user 2026-10-31): the sidebar entry opens
 * this search page instead of the RofO list. A file number, OP serial number or name is
 * looked up in every File Status list; the lists themselves stay as tabs. Read-only.
 */
class FileStatusController extends Controller
{
    public function index(Request $r, FileStatusLookupService $lookup)
    {
        $by = array_key_exists($r->query('by'), FileStatusLookupService::BY) ? $r->query('by') : 'file';
        $q = trim((string) $r->query('q', ''));

        $candidates = [];
        $file = null;
        $sections = [];

        if ($q !== '') {
            $candidates = $lookup->candidates($q, $by);
            // One file (always, for a file number): straight to its status.
            if (count($candidates) === 1) {
                $file = $candidates[0];
                $file['aliases'] = $lookup->aliases($file['file_number']);
                $sections = $lookup->status($file['file_number']);
            }
        }

        return view('survey_module.file_status', compact('by', 'q', 'candidates', 'file', 'sections'));
    }
}
