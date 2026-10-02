<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralChart;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Services\Cadastral\CadastralRegistryLookup;
use App\Services\Cadastral\ChartConflictScanner;
use App\Services\Cadastral\ReceiptHolds;
use Illuminate\Http\Request;

/**
 * Correspondence (cadastral copy) files — the registry's own record of them.
 *
 * A receipt's correspondence file is commissioned when the receipt is
 * registered (FileReceiptController::markRegistered → CorrespondenceFiles),
 * through the same cadastral_shadow_files register the legacy "Commission
 * Correspondence File (Match MLSFileNo)" screen writes. A file that already had
 * one is matched to it instead. So the primary list is registered receipts and
 * their shadow-file row; files ON HOLD are listed too, because a held file is
 * exactly the one this desk has to look at, and it has no correspondence yet.
 *
 * Boundary conflicts only exist once a file is charted (KLAES holds no parcel
 * geometry before then), so a row shows ChartConflictScanner's findings only
 * when the file has a current chart.
 */
class CorrespondenceController extends Controller
{
    public function __construct(
        private CadastralRegistryLookup $lookup,
        private ChartConflictScanner $conflicts,
    ) {}

    public function index(Request $r)
    {
        $holds = ReceiptHolds::available();

        $q = $this->baseQuery($holds)->with('shadowFile');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('file_number', 'like', "%$term%")
                  ->orWhere('file_title', 'like', "%$term%")
                  ->orWhere('receipt_ref', 'like', "%$term%");
            });
        }

        if ($s = $r->query('correspondence_status')) {
            $q->where('correspondence_status', $s);
        }

        match ($r->query('flag')) {
            'duplicate' => $q->where('duplicate_flag', true),
            'clean'     => $q->where('duplicate_flag', false),
            'held'      => $holds ? $q->where('hold_status', CadastralFileReceipt::HOLD_ON) : $q->whereRaw('1 = 0'),
            'cleared'   => $holds ? $q->where('hold_status', CadastralFileReceipt::HOLD_CLEARED) : $q->whereRaw('1 = 0'),
            default     => null,
        };

        $receipts = $q->orderByDesc('id')->paginate(15)->withQueryString();

        // Matched on the index flag alone there is no shadow row to show; read
        // the number the index records for those (one bounded read per row on
        // the visible page, as before).
        foreach ($receipts as $receipt) {
            $receipt->index_corresponding_fileno = ! $receipt->cadastral_shadow_file_id && $receipt->correspondence_status === 'matched'
                ? $this->lookup->correspondence($receipt->file_number)['corresponding_fileno']
                : null;
        }

        $conflicts = $this->boundaryConflicts($receipts->pluck('file_number')->filter()->unique()->all());

        $stats = [
            'created'    => $this->baseQuery($holds)->where('correspondence_status', 'created')->count(),
            'matched'    => $this->baseQuery($holds)->where('correspondence_status', 'matched')->count(),
            'duplicates' => $this->baseQuery($holds)->where('duplicate_flag', true)->count(),
            'on_hold'    => $holds ? $this->baseQuery($holds)->where('hold_status', CadastralFileReceipt::HOLD_ON)->count() : null,
        ];

        return view('cadastral_module.registry.correspondence', [
            'receipts'     => $receipts,
            'stats'        => $stats,
            'conflicts'    => $conflicts,
            'holdsEnabled' => $holds,
        ]);
    }

    /** Registered receipts (correspondence processed), plus open receipts on hold. */
    private function baseQuery(bool $holds)
    {
        return CadastralFileReceipt::query()->where(function ($w) use ($holds) {
            $w->whereNotNull('registered_at');

            if ($holds) {
                $w->orWhere(fn ($h) => $h->where('hold_status', CadastralFileReceipt::HOLD_ON)
                    ->whereNotIn('status', CadastralRegistryLookup::CLOSED_RECEIPT_STATUSES));
            }
        });
    }

    /**
     * Boundary/identity conflicts per file number, for files that have a
     * current chart. Files not yet charted are simply absent.
     *
     * @param  string[]  $fileNumbers
     * @return array<string, array{chart: CadastralChart, items: array}>
     */
    private function boundaryConflicts(array $fileNumbers): array
    {
        if ($fileNumbers === []) {
            return [];
        }

        $out = [];

        $charts = CadastralChart::query()
            ->current()
            ->with('coordinates')
            ->whereIn('file_number', $fileNumbers)
            ->get();

        foreach ($charts as $chart) {
            $out[$chart->file_number] = [
                'chart' => $chart,
                'items' => $this->conflicts->scan($chart),
            ];
        }

        return $out;
    }
}
