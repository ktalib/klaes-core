<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Services\Survey\SurveyDocuments;
use App\Services\SurveyItsAnchors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sketch Plan upload on Computation Field Observations (change list p.2, 2026-10-29),
 * for GKN and for the Surveyor's Report (Private). An attachment, never a sidebar item
 * (recording 286). Filed into the file's EDMS folder under Survey Registry
 * (SurveyDocuments); a new one supersedes the last, and both stay in EDMS.
 */
class SurveySketchPlanController extends Controller
{
    public function __construct(private SurveyItsAnchors $anchors, private SurveyDocuments $documents)
    {
    }

    public function store(Request $r, string $its)
    {
        $anchor = $this->anchors->resolve($its);
        abort_unless($anchor, 404);
        if ($anchor['locked']) {
            throw ValidationException::withMessages(['sketch_plan' => "{$anchor['prefill']['its_number']} is closed; its records can no longer be changed."]);
        }

        $r->validate(['sketch_plan' => array_merge(['required'], SurveyDocuments::fileRule())],
            ['sketch_plan.required' => 'Choose the Sketch Plan to upload.'] + SurveyDocuments::messages('sketch_plan'));

        $fileNumber = $anchor['prefill']['file_number'] ?? null;
        if (! $fileNumber) {
            throw ValidationException::withMessages(['sketch_plan' => 'This survey has no file number, so there is no EDMS folder to file the Sketch Plan into.']);
        }
        $indexing = $this->documents->requireIndexing($fileNumber, 'sketch_plan');
        [$type, $id] = SurveyDocuments::owner($anchor);

        try {
            DB::connection('sqlsrv')->transaction(fn () => $this->documents->file($r->file('sketch_plan'), $indexing, $type, $id, SurveyDocuments::KIND_SKETCH_PLAN));
        } catch (\Throwable $e) {
            $this->documents->discardWritten();
            throw $e;
        }

        return back()->with('success', "Sketch Plan filed in {$indexing->file_number}'s EDMS folder (Survey Registry); it is now in the Page Typing queue.");
    }
}
