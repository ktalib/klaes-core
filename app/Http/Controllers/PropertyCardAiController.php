<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyCardAiController extends Controller
{
    public function index(Request $request)
    {
        $PageTitle = 'AI Property Record Assistant';
        $PageDescription = '';
        // Optionally load property records similar to manual page if needed
        $Property_records = DB::connection('sqlsrv')
            ->table('property_records')
            ->orderBy('id', 'desc')
            ->limit(1)
            ->get();

        $pageLength = 50;
        return view('propertycard.ai.index', compact('PageTitle', 'PageDescription', 'Property_records', 'pageLength'));
    }
}