<?php

namespace App\Http\Controllers;

use App\Services\ScannerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SurveyPlanExtractionController extends Controller
{ 
    public function index() {
        $PageTitle = 'Survey Plan Extraction';
        $PageDescription = 'Upload scanned survey plans for automated data extraction';
        return view('survey_plan_extraction.index', compact('PageTitle', 'PageDescription'));
    }
}
