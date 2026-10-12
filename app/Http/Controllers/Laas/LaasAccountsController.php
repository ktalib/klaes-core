<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use App\Models\Laas\LaasApplicant;
use App\Models\Laas\LaasStageNotification;
use App\Models\Laas\LaasTransferCandidate;
use App\Services\Laas\LaasAccountAdminService;
use App\Services\Laas\LaasTransferService;
use App\Support\HolderName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LAAS staff console: account contacts, counter sign-in slips, merges, and the
 * ownership-transfer queue. See docs/plans/LAAS_PORTAL_REBUILD_PLAN.md §3.3–3.5.
 */
class LaasAccountsController extends Controller
{
    public function __construct(
        private LaasAccountAdminService $admin,
        private LaasTransferService $transfers,
    ) {
    }

    /** One account: its holder's details and every file on it. */
    public function profile(Request $request, int $id)
    {
        $applicant = LaasApplicant::findOrFail($id);
        $search = trim((string) $request->query('q', ''));

        $applications = \App\Models\Laas\LaasApplication::where('laas_applicant_id', $applicant->id)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('file_number', 'like', "%{$search}%")
                ->orWhere('reference_no', 'like', "%{$search}%")
                ->orWhere('plot_no', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")))
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        // District / LGA from the commissioning row (the portal row keeps Location only).
        $property = \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('mls_file_no')
            ->whereIn('id', $applications->pluck('mls_file_no_id')->filter()->all() ?: [0])
            ->get(['id', 'plot_no', 'district', 'lga'])
            ->keyBy('id');

        $base = \App\Models\Laas\LaasApplication::where('laas_applicant_id', $applicant->id);

        return view('laas_admin.applicant', [
            'applicant'    => $applicant,
            'applications' => $applications,
            'property'     => $property,
            'search'       => $search,
            'stats'        => [
                'files'       => (clone $base)->whereNotNull('file_number')->count(),
                'retired'     => (clone $base)->where('lifecycle_status', \App\Models\Laas\LaasApplication::LIFECYCLE_RETIRED)->count(),
                'transferred' => (clone $base)->where('lifecycle_status', \App\Models\Laas\LaasApplication::LIFECYCLE_TRANSFERRED_OUT)->count(),
                'rofo_signed' => (clone $base)->where('stage', \App\Models\Laas\LaasApplication::STAGE_ROFO_SIGNED)->count(),
            ],
            'landUses'     => new \App\Services\Prs\Support\LandUseNormalizer(),
            'unreadAlerts' => LaasStageNotification::where('is_read', false)->count(),
        ]);
    }

    public function updateContact(Request $request, int $id)
    {
        $applicant = LaasApplicant::findOrFail($id);
        $data = $request->validate([
            'phone'       => ['nullable', 'string', 'max:30'],
            'email'       => ['nullable', 'string', 'max:150'],
            'text_signin' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['phone']) && !LaasApplicant::normalizePhone($data['phone'])) {
            return back()->with('error', 'That is not a valid Nigerian mobile number (11 digits, starting with 0).');
        }

        try {
            $result = $this->admin->updateContact($applicant, $data['phone'] ?? null, $data['email'] ?? null, (bool) ($data['text_signin'] ?? false), (int) Auth::id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = "Contact details for {$applicant->name} updated.";
        if ($result['sms'] === 'sent') {
            $message .= ' New sign-in details were texted to them.';
        } elseif ($result['temp_password']) {
            // Not delivered: hand it over at the counter instead.
            return view('laas_admin.slip', $this->admin->slipData($applicant->fresh(), $result['temp_password']) + [
                'notice' => 'The text could not be sent. Print this slip and hand it to the applicant.',
            ]);
        }

        return back()->with('status', $message);
    }

    /** Reset the temporary password and show the printable slip. */
    public function slip(int $id)
    {
        $applicant = LaasApplicant::findOrFail($id);
        $password = $this->admin->issueSlip($applicant, (int) Auth::id());

        return view('laas_admin.slip', $this->admin->slipData($applicant->fresh(), $password));
    }

    public function merge(Request $request, int $id)
    {
        $source = LaasApplicant::findOrFail($id);
        $data = $request->validate(['target_username' => ['required', 'string', 'max:60']]);

        $target = LaasApplicant::where('username', trim($data['target_username']))->first();
        if (!$target) {
            return back()->with('error', 'No portal account has the username "' . $data['target_username'] . '".');
        }

        try {
            $moved = $this->admin->merge($source, $target, (int) Auth::id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "{$moved} file(s) moved from {$source->username} to {$target->username}. {$source->username} is now suspended.");
    }

    public function transfers(Request $request)
    {
        $status = $request->query('status', LaasTransferCandidate::STATUS_PENDING);
        $status = in_array($status, ['pending', 'approved', 'dismissed', 'all'], true) ? $status : 'pending';

        $candidates = LaasTransferCandidate::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // A spelling variant of the same holder scores high: a hint, never a decision.
        $similarity = [];
        foreach ($candidates as $c) {
            similar_text(HolderName::normalise((string) $c->current_holder), HolderName::normalise((string) $c->transferee_name), $pct);
            $similarity[$c->id] = (int) round($pct);
        }

        return view('laas_admin.transfers', [
            'candidates'   => $candidates,
            'similarity'   => $similarity,
            'status'       => $status,
            'pendingCount' => LaasTransferCandidate::where('status', LaasTransferCandidate::STATUS_PENDING)->count(),
            'unreadAlerts' => LaasStageNotification::where('is_read', false)->count(),
        ]);
    }

    public function approveTransfer(Request $request, int $id)
    {
        $candidate = LaasTransferCandidate::findOrFail($id);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        try {
            $result = $this->transfers->approve($candidate, (int) Auth::id(), $note);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['temp_password']) {
            $applicant = $result['new_application']->applicant;

            return view('laas_admin.slip', $this->admin->slipData($applicant, $result['temp_password']) + [
                'notice' => "File {$candidate->file_number} moved to a new account for {$candidate->transferee_name}. Print this slip and hand it to them.",
            ]);
        }

        return redirect()->route('laas-admin.transfers')
            ->with('status', "File {$candidate->file_number} moved to {$result['username']}'s account.");
    }

    public function dismissTransfer(Request $request, int $id)
    {
        $candidate = LaasTransferCandidate::findOrFail($id);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        try {
            $this->transfers->dismiss($candidate, (int) Auth::id(), $note);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Dismissed: {$candidate->file_number} stays with {$candidate->current_holder}.");
    }
}
