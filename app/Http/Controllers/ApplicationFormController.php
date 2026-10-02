<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApplicationFormController extends Controller
{ 
    public function residential() {
        $PageTitle = 'SLTR APPLICATION FORM: RESIDENTIAL';
        $PageDescription = 'Complete the application form for residential properties.';
        //Log::info('File Tracker accessed', ['user_id' => auth()->id()]);
        return view('sltr_application_form.residential', compact('PageTitle', 'PageDescription'));
    }


    
}
