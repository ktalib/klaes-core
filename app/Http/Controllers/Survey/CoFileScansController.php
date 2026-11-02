<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use Illuminate\Support\Facades\DB;

/**
 * CO Files > View Uploaded Files (CR 30102026 item 17, user 2026-10-31): until these files are
 * page-typed, the link opens the raw blind-scan pages (EDMS/BLIND_SCAN/<registry>_Raw/<file no>/...),
 * served from the public /storage junction. Typed pages stay in Doc-WARE, linked from the page.
 */
class CoFileScansController extends Controller
{
    public function show(SurveyCoFile $coFile)
    {
        $no = (string) $coFile->co_file_no;
        $pages = DB::connection('sqlsrv')->table('blind_scannings')
            // A plain match: the column's collation already ignores case and trailing spaces, and keeps the index.
            ->where('file_number', trim($no))
            ->orderBy('paper_size')->orderBy('original_filename')->orderBy('id')
            ->get(['id', 'original_filename', 'document_path', 'paper_size', 'status', 'created_at']);

        return view('survey_module.compensation.co_file_scans', compact('coFile', 'pages'));
    }
}
