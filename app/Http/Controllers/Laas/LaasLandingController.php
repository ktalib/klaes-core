<?php

namespace App\Http\Controllers\Laas;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LaasLandingController extends Controller
{
    public function index()
    {
        if (Auth::guard('laas')->check()) {
            return redirect()->route('laas.dashboard');
        }

        return view('laas.landing');
    }
}
