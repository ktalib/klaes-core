<?php

namespace App\Http\Controllers;

use App\Models\LandRecommendation;
use App\Models\LandRecommendationBatchDocument;
use App\Support\LandRecommendationLog as RecLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Brief Recommendation: the Permanent Secretary memo raised for the MOTHER file of
 * a subdivision, merger, regrant, reinstatement, ... The children inherit this
 * brief, not whatever recommendation the mother was granted years ago.
 *
 * The flow is download -> edit and print in Word -> sign -> upload:
 *
 *  - details()    gives public/js/brief-recommendation.js the mother's particulars
 *                 to fill the Word template with (the filling happens in the
 *                 browser, so the officer leaving KLAES for Word loses nothing);
 *  - downloaded() records the download on the records it was taken for;
 *  - the signed copy is uploaded through LandRecommendationBatchDocumentController,
 *    the mother-recommendation upload, which files it in the mother's EDMS folder
 *    and is what the approval gate (motherRecommendationGate) waits for.
 */
class LandRecommendationBriefController extends Controller
{
    /** The memo templates under public/templates/brief-recommendation-*.docx. */
    public const VARIANTS = ['access-road', 'compensated', 'reinstatement'];

    /**
     * The mother file's particulars for the memo: its own most recent
     * recommendation where it has one, otherwise its indexing record.
     */
    public function details(Request $request)
    {
        $fileNo = trim((string) $request->query('file_no', ''));

        if ($fileNo === '') {
            return response()->json(['success' => false, 'message' => 'No file number supplied.'], 422);
        }

        // "X(T)" and "X" are the same file under a temporary number.
        $variants = array_values(array_unique(array_filter([
            $fileNo,
            preg_replace('/\s*\(T\)\s*$/i', '', $fileNo),
            $fileNo . '(T)',
        ])));

        $rec = LandRecommendation::whereIn('file_number', $variants)
            ->orderByDesc('id')
            ->first(['applicant_name', 'district', 'lga', 'layout_plan_no', 'purpose_of_clause', 'land_use']);

        $indexing = DB::connection('sqlsrv')->table('file_indexings')
            ->whereIn('file_number', $variants)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->first(['file_title', 'current_holder', 'district', 'lga', 'tp_no', 'land_use_type']);

        // current_holder is stored JSON-encoded ("\"NAME\""), file_title plain.
        $holder = '';
        if ($indexing) {
            $decoded = json_decode((string) $indexing->current_holder, true);
            $holder = trim(is_string($decoded) ? $decoded : (string) $indexing->file_title);
            if ($holder === '') {
                $holder = trim((string) $indexing->file_title);
            }
        }

        $pick = fn (?string $first, ?string $second) => trim((string) $first) !== '' ? trim((string) $first) : trim((string) $second);

        return response()->json([
            'success' => true,
            'found'   => (bool) ($rec || $indexing),
            'details' => [
                'fileNumber' => $fileNo,
                'applicant'  => $pick($rec->applicant_name ?? null, $holder),
                'district'   => $pick($rec->district ?? null, $indexing->district ?? null),
                'lga'        => $pick($rec->lga ?? null, $indexing->lga ?? null),
                'layout'     => $pick($rec->layout_plan_no ?? null, $indexing->tp_no ?? null),
                'purpose'    => $pick($pick($rec->purpose_of_clause ?? null, $rec->land_use ?? null), $indexing->land_use_type ?? null),
            ],
        ]);
    }

    /**
     * Record that a brief was downloaded. `key` is what the signed copy will be
     * uploaded under: a batch id, or a single record's per-record key. From the
     * capture form there is no record yet, so only the log line is written.
     */
    public function downloaded(Request $request)
    {
        $data = $request->validate([
            'file_no' => 'required|string|max:100',
            'variant' => 'required|string|in:' . implode(',', self::VARIANTS),
            'key'     => 'nullable|string|max:100',
        ]);

        $key = trim((string) ($data['key'] ?? ''));
        $updated = 0;

        if ($key !== '') {
            $recordId = LandRecommendationBatchDocument::subdivisionTemplateId($key);

            $query = DB::connection('sqlsrv')->table('land_recommendations');
            $recordId !== null ? $query->where('id', $recordId) : $query->where('rofo_batch_id', $key);

            $updated = $query->update([
                'brief_rec_variant'        => $data['variant'],
                'brief_rec_download_count' => DB::raw('ISNULL(brief_rec_download_count, 0) + 1'),
                'brief_rec_downloaded_at'  => now(),
                'brief_rec_downloaded_by'  => Auth::id(),
            ]);
        }

        RecLog::info('Brief recommendation downloaded', [
            'mother_file_no' => $data['file_no'],
            'variant'        => $data['variant'],
            'key'            => $key ?: null,
            'records'        => $updated,
            'user_id'        => Auth::id(),
        ]);

        return response()->json(['success' => true, 'records' => $updated]);
    }
}
