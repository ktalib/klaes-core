<?php

namespace App\Http\Controllers;

use App\Models\PrintLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrintManagerController extends Controller
{
    /**
     * Store a new print log entry.
     * ['Original', 'Duplicate', 'Triplicate'] [colors: 'red','blue','green']
     */
    public function log(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string',
            'document_type' => 'required|string',
            'status' => 'required|string', // Original, Duplicate, Triplicate
            'print_type' => 'required|string', // Individual, Batch
        ]);

        $log = PrintLog::create([
            'reference_number' => $request->reference_number,
            'document_type' => $request->document_type,
            'print_type' => $request->print_type,
            'status' => $request->status,
            'user_id' => Auth::id(),
        ]);

        if (str_contains($request->document_type, 'Legal Search')) {
            \App\Models\LegalSearchLog::where('search_value', $request->reference_number)
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->update(['printed' => true]);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    /**
     * Check the current print status for a document.
     */
    public function checkStatus(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string',
            'document_type' => 'required|string',
        ]);

        $logs = PrintLog::where('reference_number', $request->reference_number)
            ->where('document_type', $request->document_type)
            ->get();

        $isSingleStep = in_array($request->document_type, ['Recommendation For Grant', 'ST CofO', 'Legal Search Pay-Per-Search', 'Legal Search Online', 'Legal Search Official']);

        // Counted from the last reset, not from the beginning of time. A reset
        // writes a marker row (PrintLog::TYPE_RESET) rather than deleting anything,
        // so a document whose print was put back reads as unprinted here while its
        // history stays intact. Documents that have never been reset have no marker
        // and count exactly as they always did.
        $original   = PrintLog::countSinceReset($logs, 'Original');
        $duplicate  = PrintLog::countSinceReset($logs, 'Duplicate');
        $triplicate = PrintLog::countSinceReset($logs, 'Triplicate');

        $status = [
            'original'   => $original,
            'duplicate'  => $duplicate,
            'triplicate' => $triplicate,
            'completed'  => $isSingleStep
                ? $original > 0
                : ($original > 0 && $duplicate > 0 && $triplicate > 0),
        ];

        return response()->json([
            'success' => true,
            'status' => $status
        ]);
    }

    /**
     * Store multiple print log entries in batch.
     */
    public function batchLog(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string',
            'document_type' => 'required|string',
            'statuses' => 'required|array', // e.g., ['Original', 'Duplicate', 'Triplicate']
            'print_type' => 'required|string',
        ]);

        foreach ($request->statuses as $status) {
            PrintLog::create([
                'reference_number' => $request->reference_number,
                'document_type' => $request->document_type,
                'print_type' => $request->print_type,
                'status' => $status,
                'user_id' => Auth::id(),
            ]);
        }

        return response()->json([
            'success' => true
        ]);
    }



}
