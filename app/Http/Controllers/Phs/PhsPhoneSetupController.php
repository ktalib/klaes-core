<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use App\Models\Phs\PhsMember;
use App\Services\Phs\PhsPhoneSetupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The mobile-number card: the one screen a PHS member sees until the number on
 * their account has been proved. See PhsPhoneSetupService and RequirePhsPhone.
 *
 * Everything here acts on the SIGNED-IN member, never on an id from the request,
 * so the card can only ever attach a number to the account already holding the
 * session.
 */
class PhsPhoneSetupController extends Controller
{
    public function __construct(private PhsPhoneSetupService $setup)
    {
    }

    private function member(): PhsMember
    {
        return Auth::guard('phs')->user();
    }

    public function show(Request $request)
    {
        $member = $this->member();

        // Already done — nothing to show. Reached by the back button, or by a
        // second tab that was sitting on the card while the first one finished.
        if (!$this->setup->needsSetup($member)) {
            return redirect()->intended(route('phs.dashboard'));
        }

        return view('phs.auth.phone-setup', [
            'stagedPhone' => $this->setup->maskedStaged($member),
            'expiresIn' => $this->setup->expiresIn($member),
            'retryAfter' => $this->setup->cooldownRemaining($member),
            'ttlMinutes' => $this->setup->ttlMinutes(),
            'codeLength' => max(4, min(10, (int) config('phone_verification.code_length', 6))),
            'organization' => $member->institution->name ?? null,
            'memberEmail' => $member->email,
        ]);
    }

    /** Stage a number and text a code to it. */
    public function send(Request $request)
    {
        $request->validate(
            ['phone' => ['required', 'string', 'max:30']],
            ['phone.required' => 'Enter the mobile number you want to use.']
        );

        $result = $this->setup->send($this->member(), (string) $request->input('phone'));

        return redirect()->route('phs.phone.setup')
            ->with($result['sent'] ? 'status' : 'error', $result['message'])
            ->withInput($result['sent'] ? [] : $request->only('phone'));
    }

    /** Another code to the number already staged. */
    public function resend(Request $request)
    {
        $result = $this->setup->send($this->member(), null);

        return redirect()->route('phs.phone.setup')
            ->with($result['sent'] ? 'status' : 'error', $result['message']);
    }

    public function confirm(Request $request)
    {
        $request->validate(
            ['code' => ['required', 'string', 'max:10']],
            ['code.required' => 'Enter the code that was sent to you.']
        );

        $result = $this->setup->confirm($this->member(), (string) $request->input('code'));

        if (!$result['verified']) {
            return redirect()->route('phs.phone.setup')->with('error', $result['message']);
        }

        // intended() carries them to whatever they were trying to open when the
        // card stopped them, which RequirePhsPhone stashed.
        return redirect()->intended(route('phs.dashboard'))->with('status', $result['message']);
    }

    /** Throw the staged number away and ask for a number again. */
    public function change(Request $request)
    {
        $this->setup->discard($this->member());

        return redirect()->route('phs.phone.setup');
    }
}
