<?php

namespace App\Http\Middleware;

use App\Services\Phs\PhsPhoneSetupService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Holds a PHS member at the mobile-number card until they have proved one.
 *
 * phs_members.phone was added to a live table on 2026-10-01 with every row
 * empty. A column nobody is ever asked to fill stays empty, so the portal does
 * not open for a member without a PROVED number — they type their mobile,
 * receive a code on it and type it back (PhsPhoneSetupService).
 *
 * Runs AFTER phs.otp, so the order a member meets the two gates is: password,
 * sign-in code, then this. They are never shown two cards at once, and the
 * number is only ever asked for inside a session that has already proved who it
 * belongs to — which is what stops this card being a way to attach your own
 * handset to somebody else's account.
 *
 * Deliberately NOT applied to the card's own routes or to logout; see
 * routes/phs.php. A gate that blocks its own exit traps the member in it.
 */
class RequirePhsPhone
{
    public function __construct(private PhsPhoneSetupService $setup)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $member = Auth::guard('phs')->user();

        if (!$member || !$this->setup->needsSetup($member)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && !$request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Confirm the mobile number on your account to continue.',
                'phone_setup_required' => true,
                'redirect' => route('phs.phone.setup'),
            ], 403);
        }

        return redirect()->route('phs.phone.setup');
    }
}
