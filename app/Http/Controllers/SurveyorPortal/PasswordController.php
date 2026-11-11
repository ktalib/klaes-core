<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Change password on the MLPP-SG portal (user, 2026-11-10). A company login must use it at the first sign-in,
 * because its password was sent by SMS and email; RequireSurveyorPasswordChange holds it here until then.
 */
class PasswordController extends Controller
{
    public function edit()
    {
        return view('surveyor_portal.account.password', ['forced' => (bool) Auth::guard('surveyor')->user()->must_change_password]);
    }

    public function update(Request $r)
    {
        $account = Auth::guard('surveyor')->user();
        $r->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Enter your current password.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The two new passwords do not match.',
        ]);

        if (! Hash::check($r->input('current_password'), $account->password)) {
            throw ValidationException::withMessages(['current_password' => 'That password is not correct.']);
        }
        // The temporary password must actually be replaced, not typed again as the new one.
        if (Hash::check($r->input('password'), $account->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from your current one.']);
        }

        $account->forceFill(['password' => Hash::make($r->input('password')), 'must_change_password' => false])->save();
        Log::info('MLPP-SG portal password changed', ['account' => $account->id]);

        return redirect()->route('surveyor-portal.dashboard')->with('status', 'Your password has been changed.');
    }
}
