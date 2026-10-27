<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Services\CadastralDept\DeskAccess;
use App\Services\CadastralDept\PropertyIndexCards;
use App\Services\DocumentQr\QrRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Generate Property Index Card (user, 2026-10-16). Lists the cards filed so far, previews a
 * file's card from the Legal Search records, and generates and files it (PropertyIndexCards).
 * Registry registering a file generates its card on its own (CaseController::store).
 */
class PropertyIndexCardController extends Controller
{
    public function __construct(private PropertyIndexCards $cards)
    {
    }

    public function index(Request $request)
    {
        $installed = PropertyIndexCards::installed();
        $rows = null;
        $search = trim((string) $request->query('q', ''));
        if ($installed) {
            $rows = DB::connection('sqlsrv')->table(PropertyIndexCards::TABLE . ' as c')
                ->leftJoin('users as u', 'u.id', '=', 'c.generated_by')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->whereRaw('UPPER(c.file_number) LIKE ?', ['%' . strtoupper($search) . '%'])
                    ->orWhereRaw('UPPER(c.file_title) LIKE ?', ['%' . strtoupper($search) . '%'])))
                ->orderByDesc('c.created_at')->orderByDesc('c.id')
                ->select('c.*', 'u.first_name as generated_by_first', 'u.last_name as generated_by_last')
                ->paginate(25)->withQueryString();
        }

        return view('cadastral_dept.pic.index', [
            'installed' => $installed,
            'rows' => $rows,
            'search' => $search,
            'canGenerate' => $this->canGenerate($request),
        ]);
    }

    /** The card for ?file= from the records, or the sample card without one; ?format=pdf for the PDF. */
    public function preview(Request $request, QrRenderer $qr)
    {
        $file = trim((string) $request->query('file', ''));
        try {
            $pic = $file !== '' ? $this->cards->build($file) : $this->sample();
        } catch (\Throwable $e) {
            return redirect()->route('cadastral-dept.pic.index')->with('error', $e->getMessage());
        }
        $pic['qr'] = $qr->dataUri(route('cadastral-dept.pic.show', ['file' => $pic['file_number']]), 160);

        if ($request->query('format') === 'pdf') {
            return Pdf::loadView('cadastral_dept.pic.pdf', ['pic' => $pic])
                ->setPaper('a5', 'landscape')
                ->stream('PIC-' . $pic['file_number'] . '.pdf');
        }

        return view('cadastral_dept.pic.preview', [
            'pic' => $pic,
            'isSample' => $file === '',
            'card' => $file !== '' ? $this->cards->find($file) : null,
            'canGenerate' => $file !== '' && $this->canGenerate($request),
        ]);
    }

    /** The filed card PDF for ?file= (the QR code opens this). */
    public function show(Request $request)
    {
        $file = trim((string) $request->query('file', ''));
        $card = $file !== '' ? $this->cards->find($file) : null;
        if (!$card || !$card->path || !Storage::disk('public')->exists($card->path)) {
            return redirect()->route('cadastral-dept.pic.index', ['q' => $file])
                ->with('error', $file !== '' ? "No Property Index Card has been filed for {$file} yet." : 'Choose a file.');
        }

        return response()->file(Storage::disk('public')->path($card->path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($card->path) . '"',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['file_number' => 'required|string|max:100']);
        if (!$this->canGenerate($request)) {
            return back()->with('error', 'Only Registry generates Property Index Cards (role ' . (DeskAccess::deskRoles()['registry'] ?? 'Cad - Registry') . ').');
        }

        try {
            $card = $this->cards->generate($data['file_number'], 'manual', null, auth()->id());
        } catch (\Throwable $e) {
            return back()->with('error', 'The Property Index Card for ' . $data['file_number'] . ' could not be generated: ' . $e->getMessage());
        }

        return redirect()->route('cadastral-dept.pic.index', ['q' => $card->file_number])
            ->with('success', "Property Index Card for {$card->file_number} filed to EDMS ({$card->transactions_count} transaction(s)).");
    }

    private function canGenerate(Request $request): bool
    {
        return PropertyIndexCards::installed() && app(DeskAccess::class)->canWorkDesk($request->user(), 'registry');
    }

    /** The paper card for RES-1973-372 (Downloads/RES-1973-372_1.jpg), as sample values. */
    private function sample(): array
    {
        return [
            'file_number' => 'RES-1973-372',
            'name' => 'Garba Saleh',
            'locality' => 'Badawa L/O',
            'district' => 'Nasarawa',
            'lga' => 'Municipal',
            'plot_no' => '38',
            'tp_no' => 'TP-NSB-114B',
            'approx_area_ha' => '0.0327',
            'plan_no' => '2',
            'tracing_no' => 'LP-KN-240',
            'related_files' => [],
            // Placeholder rows to show the layout; the paper card records no dealings.
            'transactions' => [
                ['type' => 'Assignment', 'party1' => 'Garba Saleh', 'party2' => 'Sample Assignee', 'reg_datetime' => '12 Mar 2004 10:15', 'reg_particulars' => '0/0/0 (sample)', 'remarks' => 'Sample row'],
                ['type' => 'Mortgage', 'party1' => 'Sample Assignee', 'party2' => 'Sample Bank Plc', 'reg_datetime' => '05 Jul 2010 11:40', 'reg_particulars' => '0/0/0 (sample)', 'remarks' => 'Sample row'],
                ['type' => 'Release of Mortgage', 'party1' => 'Sample Bank Plc', 'party2' => 'Sample Assignee', 'reg_datetime' => '20 Jan 2015 09:05', 'reg_particulars' => '0/0/0 (sample)', 'remarks' => 'Sample row'],
            ],
            'form_no' => 'SY-039',
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => optional(auth()->user())->name,
            'sources' => ['sample'],
        ];
    }
}
