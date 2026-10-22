<?php

namespace App\Http\Controllers;

use App\Services\PlotWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "File Already Decommissioned Before" lookup for the SPU / APU / Legacy Parcel Update
 * forms. The forms ask nothing for a file with no decommissioning record; for one that
 * has a record they show Yes, locked, with that record — the new files are then added
 * to it rather than written as a second record (PlotWorkflowService::decommissionFiles).
 */
class DecommissionedBeforeController extends Controller
{
    public function __invoke(Request $request, PlotWorkflowService $workflow): JsonResponse
    {
        $files = $request->query('file_no', []);
        $files = array_slice(array_values(array_filter(array_map(
            fn ($f) => trim((string) $f),
            is_array($files) ? $files : explode(',', (string) $files)
        ))), 0, 200);

        return response()->json(['files' => $workflow->decommissionedBefore($files)]);
    }
}
