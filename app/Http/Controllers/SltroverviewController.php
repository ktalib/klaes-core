<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SltroverviewController extends Controller
{ 
    public function index() {
        $PageTitle = 'SLTR/First Registration';
        $PageDescription = 'Systematic Land Titling and Registration in Kano State';
        Log::info('File Tracker accessed', ['user_id' => auth()->id()]);
        return view('sltroverview.index', compact('PageTitle', 'PageDescription'));
    }
}
