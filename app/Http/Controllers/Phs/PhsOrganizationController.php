<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\Phs\PhsEmailHistory;
use App\Models\Phs\PhsMember;
use Illuminate\Http\Request;
use App\Models\Phs\PhsEditRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PhsOrganizationController extends Controller
{
    /** The console shell (super_admin only — guarded by phs.admin middleware). */
    public function index()
    {
        $member = Auth::guard('phs')->user();
        $institution = $member->institution;

        // Current plan = most recent completed token purchase; history = all purchases.
        $subscription = $institution->transactions()
            ->where('type', 'purchase')
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->first();

        $subscriptionHistory = $institution->transactions()
            ->where('type', 'purchase')
            ->orderByDesc('id')
            ->get();

        $members = $institution->members()->orderBy('id')->get();

        // Resolve the member limit from the active subscription package.
        $memberLimit = null;
        if ($subscription) {
            $pkgs = PhsTokenController::packages();
            $pkgKey = strtolower($subscription->package_name ?? '');
            $pkg = $pkgs[$pkgKey] ?? null;
            if (!$pkg) {
                foreach ($pkgs as $p) {
                    if (strtolower($p['name'] ?? '') === strtolower($subscription->package_name ?? '')) {
                        $pkg = $p;
                        break;
                    }
                }
            }
            $memberLimit = $pkg ? (int) $pkg['team_members'] : null;
        }

        // Correction requests raised by anyone in this organisation. Scoped to the
        // institution, not the member, because this console is where an
        // administrator answers "what have we reported, and what came back?".
        //
        // canRerun is decided per ROW by the model, and is true only for the
        // logged-in member's own authorisations: the free re-run is granted to the
        // member who was given the bad result, so an administrator cannot collect
        // it on a colleague's behalf. Everyone else's rows are read-only status.
        $editRequests = collect();
        try {
            $editRequests = PhsEditRequest::with('member')
                ->where('phs_institution_id', $institution->id)
                ->orderByRaw("CASE WHEN status = 'ready_for_rerun' THEN 0
                                   WHEN status = 'edit_requested'  THEN 1
                                   ELSE 2 END")
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        return view('phs.organization.index', [
            'member' => $member,
            'institution' => $institution,
            'members' => $members,
            'subscription' => $subscription,
            'subscriptionHistory' => $subscriptionHistory,
            'packages' => PhsTokenController::packages(),
            'memberLimit' => $memberLimit,
            'currentAdminCount' => $members->where('user_type', 'super_admin')->count(),
            'editRequests' => $editRequests,
            'readyForRerunCount' => $editRequests
                ->filter(fn ($r) => $r->authorisesFreeRerun())
                ->count(),
        ]);
    }

    public function listMembers()
    {
        $institution = Auth::guard('phs')->user()->institution;
        return response()->json([
            'success' => true,
            'data' => $institution->members()->orderBy('id')->get(),
        ]);
    }

    public function storeMember(Request $request)
    {
        $institution = Auth::guard('phs')->user()->institution;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:sqlsrv.phs_members,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'user_type' => ['required', 'in:super_admin,regular_user'],
            'access_role' => ['required', 'array', 'min:1'],
            'access_role.*' => ['required', 'in:search_only,report_viewer,analytics_viewer'],
            'token_allocation' => ['nullable', 'integer', 'min:0'],
        ]);

        // Enforce subscription member limit.
        $subscription = $institution->transactions()->where('type', 'purchase')->where('status', 'completed')->orderByDesc('id')->first();
        if ($subscription) {
            $pkgs = PhsTokenController::packages();
            $pkgKey = strtolower($subscription->package_name ?? '');
            $pkg = $pkgs[$pkgKey] ?? null;
            if (!$pkg) {
                foreach ($pkgs as $p) {
                    if (strtolower($p['name'] ?? '') === strtolower($subscription->package_name ?? '')) { $pkg = $p; break; }
                }
            }
            $memberLimit = $pkg ? (int) $pkg['team_members'] : null;
            if ($memberLimit !== null && $institution->members()->count() >= $memberLimit) {
                return response()->json([
                    'success' => false,
                    'message' => "Your {$subscription->package_name} plan allows a maximum of {$memberLimit} team members. Please upgrade to add more.",
                ], 422);
            }
        }

        // Enforce admin limit: max 1 super_admin per institution.
        if ($data['user_type'] === 'super_admin' && $institution->members()->where('user_type', 'super_admin')->count() >= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription only includes 1 Administrator slot. You already have an administrator.',
            ], 422);
        }

        $allocation = (int) ($data['token_allocation'] ?? 0);
        $accessRole = implode(',', $data['access_role']);

        // The allocation is funded from the organization wallet, so it can't exceed the balance.
        if ($allocation > (int) $institution->token_balance) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation exceeds the organization token balance (' . number_format($institution->token_balance) . ' available).',
            ], 422);
        }

        $member = \DB::connection('sqlsrv')->transaction(function () use ($institution, $data, $allocation, $accessRole) {
            $member = $institution->members()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'job_title' => $data['job_title'] ?? null,
                'department' => $data['department'] ?? null,
                'user_type' => $data['user_type'],
                'access_role' => $accessRole,
                'allocated_tokens' => $allocation,
                'status' => 'active',
            ]);

            if ($allocation > 0) {
                $institution->decrement('token_balance', $allocation);
            }

            return $member;
        });

        return response()->json(['success' => true, 'message' => 'Team member added.', 'data' => $member]);
    }

    public function updateMember(Request $request, $id)
    {
        $institution = Auth::guard('phs')->user()->institution;
        $member = $institution->members()->where('id', $id)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'user_type' => ['sometimes', 'in:super_admin,regular_user'],
            'access_role' => ['sometimes', 'array', 'min:1'],
            'access_role.*' => ['sometimes', 'in:search_only,report_viewer,analytics_viewer'],
            'status' => ['sometimes', 'in:active,suspended'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if (!empty($data['password'])) {
            $member->password = Hash::make($data['password']);
        }
        unset($data['password']);
        if (isset($data['access_role']) && is_array($data['access_role'])) {
            $data['access_role'] = implode(',', $data['access_role']);
        }
        $member->fill($data)->save();

        return response()->json(['success' => true, 'message' => 'Member updated.', 'data' => $member]);
    }

    public function destroyMember($id)
    {
        $current = Auth::guard('phs')->user();
        $institution = $current->institution;
        $member = $institution->members()->where('id', $id)->firstOrFail();

        if ($member->id === $current->id) {
            return response()->json(['success' => false, 'message' => 'You cannot remove your own account.'], 422);
        }

        // Keep at least one super admin.
        if ($member->isSuperAdmin() && $institution->members()->where('user_type', 'super_admin')->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'At least one administrator must remain.'], 422);
        }

        $member->delete();
        return response()->json(['success' => true, 'message' => 'Member removed.']);
    }

    public function activity()
    {
        $institution = Auth::guard('phs')->user()->institution;
        $memberEmails = $institution->members()->pluck('email')->filter()->values()->all();
        $recipientPool = collect(array_merge([$institution->email], $memberEmails))
            ->filter()
            ->map(fn ($email) => strtolower((string) $email))
            ->unique()
            ->values()
            ->all();

        $searches = $institution->searchLogs()->with('member')->orderByDesc('id')->limit(50)->get()
            ->map(fn($r) => [
                'type' => 'search',
                'description' => 'Search: ' . ($r->query ?: $r->file_number),
                'member' => optional($r->member)->name,
                'reference' => $r->reference_no,
                'at' => optional($r->created_at)->toDateTimeString(),
            ]);

        $txns = $institution->transactions()->with('member')->orderByDesc('id')->limit(50)->get()
            ->map(fn($r) => [
                'type' => $r->type,
                'description' => ucfirst(str_replace('_', ' ', $r->type)) . ': ' . $r->tokens . ' tokens' . ($r->package_name ? " ({$r->package_name})" : ''),
                'member' => optional($r->member)->name,
                'reference' => $r->reference_no,
                'at' => optional($r->created_at)->toDateTimeString(),
            ]);

        $emails = PhsEmailHistory::query()
            ->where('phs_institution_id', $institution->id)
            ->orWhereIn('recipient_email', $recipientPool)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'type' => 'email',
                'description' => 'Email: ' . ($r->subject ?: 'Notification sent to organization'),
                'member' => 'KLAES System',
                'reference' => $r->message_id,
                'at' => optional($r->sent_at ?? $r->created_at)->toDateTimeString(),
            ]);

        $merged = $searches->concat($txns)->concat($emails)->sortByDesc('at')->values();

        return response()->json(['success' => true, 'data' => $merged]);
    }

    public function emailHistory()
    {
        $institution = Auth::guard('phs')->user()->institution;
        $memberEmails = $institution->members()->pluck('email')->filter()->values()->all();
        $recipientPool = collect(array_merge([$institution->email], $memberEmails))
            ->filter()
            ->map(fn ($email) => strtolower((string) $email))
            ->unique()
            ->values()
            ->all();

        $rows = PhsEmailHistory::query()
            ->where('phs_institution_id', $institution->id)
            ->orWhereIn('recipient_email', $recipientPool)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'recipient_email' => $row->recipient_email,
                'subject' => $row->subject,
                'body_text' => $row->body_text,
                'body_html' => $row->body_html,
                'mailable' => $row->mailable,
                'message_id' => $row->message_id,
                'sent_at' => optional($row->sent_at ?? $row->created_at)->toDateTimeString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function updateBranding(Request $request)
    {
        $institution = Auth::guard('phs')->user()->institution;

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                \Illuminate\Validation\Rule::unique('sqlsrv.phs_institutions', 'username')->ignore($institution->id),
            ],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'secondary_color' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ], [
            'username.regex' => 'The username may only contain lowercase letters, numbers, and underscores.',
        ]);

        if (!empty($data['name'])) {
            $institution->name = $data['name'];
        }
        if (!empty($data['username'])) {
            $institution->username = $data['username'];
        }
        if (!empty($data['primary_color'])) {
            $institution->primary_color = $data['primary_color'];
        }
        if (!empty($data['secondary_color'])) {
            $institution->secondary_color = $data['secondary_color'];
        }

        if ($request->hasFile('logo')) {
            if ($institution->logo_path) {
                Storage::disk('public')->delete($institution->logo_path);
            }
            $institution->logo_path = $request->file('logo')->store('phs/logos', 'public');
        }
        if ($request->hasFile('banner')) {
            if ($institution->banner_path) {
                Storage::disk('public')->delete($institution->banner_path);
            }
            $institution->banner_path = $request->file('banner')->store('phs/banners', 'public');
        }

        $institution->save();

        return response()->json([
            'success' => true,
            'message' => 'Branding updated.',
            'data' => [
                'name' => $institution->name,
                'username' => $institution->username,
                'primary_color' => $institution->primary_color,
                'secondary_color' => $institution->secondary_color,
                'logo_url' => $institution->logo_path ? asset('storage/' . $institution->logo_path) : null,
                'banner_url' => $institution->banner_path ? asset('storage/' . $institution->banner_path) : null,
            ],
        ]);
    }
}
