<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\Cadastral\ReceiptHolds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Duplicate and double-allocation checking for the Cadastral registry.
 *
 * RULE-BASED, NOT "AI-POWERED". The concept note asks for AI cross-checking;
 * there is no model host anywhere in KLAES and no training data, so what is
 * offered is deterministic multi-key matching the officer can read and argue
 * with: the file number as written, its normalised spelling, and other files
 * sharing a plot number in the same district.
 *
 * DUPLICATES ARE FILE-LEVEL, NOT GROUND-LEVEL. KLAES stores no parcel geometry,
 * so "conflicting plot boundaries" cannot be tested here. Two allocations can
 * only be compared by what they are labelled — see ChartConflictScanner for the
 * chart-level version of the same limitation.
 */
class DuplicateController extends Controller
{
    public function __construct(private CadastralRegistryLookup $lookup) {}

    public function index(Request $r)
    {
        $fileNumber = trim((string) $r->query('file_number', ''));
        $summary    = $fileNumber === '' ? null : $this->lookup->summarise($fileNumber);

        // Receipts the reception screen already flagged, so the desk has
        // somewhere to start when nothing has been typed.
        $flagged = CadastralFileReceipt::query()
            ->where('duplicate_flag', true)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // A hit puts the receipt On Hold (Phase 3, ReceiptHolds); the hold and
        // its clearing are shown and actioned here too once the columns exist.
        $holdsEnabled = ReceiptHolds::available();

        return view('cadastral_module.registry.duplicates', compact('fileNumber', 'summary', 'flagged', 'holdsEnabled'));
    }

    /**
     * The same check as JSON, for the live warning on the reception form.
     *
     * GET on purpose: it reads and reports, so the permission layer should infer
     * `view` from the verb rather than `create` from a POST.
     */
    public function lookup(Request $r): JsonResponse
    {
        $fileNumber = trim((string) $r->query('file_number', ''));

        if ($fileNumber === '') {
            return response()->json(['ok' => false, 'message' => 'No file number given.']);
        }

        $summary = $this->lookup->summarise($fileNumber);

        return response()->json([
            'ok'             => true,
            'format'         => $summary['format'],
            'has_warning'    => $summary['has_warning'],
            'shelf_location' => $summary['shelf_location'],
            'correspondence' => [
                'exists' => $summary['correspondence']['exists'],
                'status' => $summary['correspondence']['status'],
                'fileno' => $summary['correspondence']['corresponding_fileno'],
            ],
            'indexed'    => $summary['indexed'] ? [
                'file_title'  => $summary['indexed']->file_title,
                'plot_number' => $summary['indexed']->plot_number,
                'district'    => $summary['indexed']->district,
                'lga'         => $summary['indexed']->lga,
                'registry'    => $summary['indexed']->registry,
            ] : null,
            'duplicates' => $summary['duplicates']->map(fn ($d) => [
                'file_number' => $d->file_number,
                'file_title'  => $d->file_title,
                'registry'    => $d->registry,
                'category'    => $d->category,
            ])->values(),
            'doubles'    => $summary['doubles']->map(fn ($d) => [
                'file_number' => $d->file_number,
                'file_title'  => $d->file_title,
                'plot_number' => $d->plot_number,
                'district'    => $d->district,
            ])->values(),
        ]);
    }
}
