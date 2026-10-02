<?php

namespace App\Http\Controllers;

class AlaesController extends Controller
{
    public function login()
    {
        return view('alaes.login');
    }

    public function fileTracker()
    {
        return view('alaes.file-tracker');
    }
}
