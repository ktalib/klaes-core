<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralFmc;
use App\Services\CadastralDept\FileMovementCards;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\DocumentQr\QrRenderer;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\Request;

/**
 * File Movement Card: commission (AD Cad Info), view, print, and the page the
 * card's QR code opens, where a unit logs the file in or out.
 */
class FmcController extends Controller
{
    public function __construct(private FileMovementCards $cards)
    {
    }

    public function store(Request $request, int $case)
    {
        $data = $request->validate([
            'file_title' => 'nullable|string|max:255',
            'allottee_name' => 'nullable|string|max:255',
            'plot_no' => 'nullable|string|max:50',
            'district' => 'nullable|string|max:150',
            'lga' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'block_no' => 'nullable|string|max:50',
            'layout_no' => 'nullable|string|max:50',
        ]);
        $case = CadastralCase::findOrFail($case);

        try {
            $fmc = $this->cards->commission($case, $data, auth()->id());
        } catch (WorkflowGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Only the FMC roles open the card (fmc_roles); anyone else goes back to the file page.
        return (FileMovementCards::canView($request->user())
                ? redirect()->route('cadastral-dept.cases.fmc.show', $case->id)
                : redirect()->route('cadastral-dept.cases.show', $case->id))
            ->with('success', "{$fmc->fmc_no} commissioned. Print it and keep it with the file.");
    }

    public function show(int $case, WorkflowDefinition $def, QrRenderer $qr)
    {
        $this->mayView();
        $case = CadastralCase::findOrFail($case);
        $fmc = $case->fmc;
        abort_unless($fmc, 404);

        return view('cadastral_dept.fmc.show', $this->cardData($fmc, $def, $qr) + ['case' => $case]);
    }

    public function print(int $case, WorkflowDefinition $def, QrRenderer $qr)
    {
        $this->mayView();
        $case = CadastralCase::findOrFail($case);
        $fmc = $case->fmc;
        abort_unless($fmc, 404);
        $fmc->increment('print_count');

        return view('cadastral_dept.fmc.print', $this->cardData($fmc->fresh(), $def, $qr) + ['case' => $case]);
    }

    /** The page the QR code opens. */
    public function scan(string $token, WorkflowDefinition $def, QrRenderer $qr)
    {
        $this->mayView();
        $fmc = $this->byToken($token);

        return view('cadastral_dept.fmc.scan', $this->cardData($fmc, $def, $qr) + ['case' => $fmc->case]);
    }

    /** The card, its print and its QR page are for the FMC roles only (config fmc_roles, 2026-10-26). */
    private function mayView(): void
    {
        abort_unless(FileMovementCards::canView(auth()->user()), 403,
            'The File Movement Card is for ' . implode(', ', (array) config('cadastral_dept.fmc_roles', [])) . ' only.');
    }

    public function log(Request $request, string $token)
    {
        $this->mayView();
        $data = $request->validate([
            'direction' => 'required|in:in,out',
            'unit_key' => 'nullable|string|max:40',
            'unit_other' => 'nullable|string|max:150',
            'reason' => 'nullable|string|max:500',
        ]);
        $fmc = $this->byToken($token);

        try {
            $msg = $this->cards->log($fmc, $data['direction'], $data['unit_key'] ?? null, $data['unit_other'] ?? null, $data['reason'] ?? null, auth()->id());
        } catch (WorkflowGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $msg);
    }

    private function byToken(string $token): CadastralFmc
    {
        abort_unless(FileMovementCards::installed() && preg_match('/^[a-f0-9]{40}$/', $token), 404);

        return CadastralFmc::query()->where('qr_token', $token)->firstOrFail();
    }

    private function cardData(CadastralFmc $fmc, WorkflowDefinition $def, QrRenderer $qr): array
    {
        $url = route('cadastral-dept.fmc.scan', $fmc->qr_token);

        // The QR code holds the file's tracking ID, or its file number when it has none
        // (user, 2026-10-12). The scan page still opens from the link on the card's page.
        $tracking = trim((string) optional($fmc->case)->tracking_id);
        $qrLabel = $tracking !== '' ? 'Tracking ID' : 'File No.';
        $qrValue = $tracking !== '' ? $tracking : $fmc->file_number;

        return [
            'def' => $def,
            'fmc' => $fmc,
            'scanUrl' => $url,
            'qr' => $qr->dataUri($qrValue, 160),
            'qrLabel' => $qrLabel,
            'qrValue' => $qrValue,
            'timeline' => $this->cards->timeline($fmc),
            'loggedIn' => $this->cards->loggedIn($fmc),
        ];
    }
}
