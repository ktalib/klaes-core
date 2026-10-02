<?php

namespace App\Http\Controllers;

class AlaesPortalController extends Controller
{
    public function landing()
    {
        return view('alaes_portal.landing');
    }

    public function login()
    {
        return view('alaes_portal.auth.login');
    }

    public function register()
    {
        return view('alaes_portal.auth.register');
    }
}
