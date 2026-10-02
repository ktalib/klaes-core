<?php

namespace App\Http\Controllers\Phs;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PhsLandingController extends Controller
{
    /**
     * Public PHS Portal landing page (no login required).
     */
    public function index()
    {
        // If already signed in as a PHS member, jump straight to the dashboard.
        if (Auth::guard('phs')->check()) {
            return redirect()->route('phs.dashboard');
        }

        return view('phs.landing', [
            'packages' => PhsTokenController::packages(),
        ]);
    }
}
