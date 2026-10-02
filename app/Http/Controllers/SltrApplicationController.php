<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SltrApplicationController extends Controller
{ 
    public function index() {
        $PageTitle = 'SLTR Applications';
        $PageDescription = 'Manage Systematic Land Titling and Registration applications';
        //Log::info('File Tracker accessed', ['user_id' => auth()->id()]);
        return view('sltrapplication.index', compact('PageTitle', 'PageDescription'));
    }
}
