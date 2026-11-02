<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Services\FileStatusLookupService;
use Illuminate\Http\Request;

/**
 * Cadastral > Verification > File Status > Search (user, 2026-10-31: "the same thing is going to
 * happen under Cadastral"): the Survey File Status search, copied. A file number, OP serial number
 * or name is looked up in every File Status list; the links open the lists view only (?url=cadastral).
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
            if (count($candidates) === 1) {
                $file = $candidates[0];
                $file['aliases'] = $lookup->aliases($file['file_number']);
                $sections = $lookup->status($file['file_number'], 'cadastral');
            }
        }

        return view('cadastral_dept.file_status', compact('by', 'q', 'candidates', 'file', 'sections'));
    }
}
