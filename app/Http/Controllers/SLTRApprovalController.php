<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SLTRApprovalController extends Controller
{ 
    public function deeds() {
        $PageTitle = 'Deeds Registration';
        $PageDescription = 'This page allows you to manage the deeds registration process.';
        //Log::info('File Tracker accessed', ['user_id' => auth()->id()]);
        return view('sltr_approval.deeds', compact('PageTitle', 'PageDescription'));
    }


    
}
