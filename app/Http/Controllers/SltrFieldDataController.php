<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SltrFieldDataController  extends Controller
{
  

    public function FieldData()
    {
        $PageTitle = 'Sltr Field Data Collection';
        $PageDescription = 'Import, collect, and manage field data from Survey123';

        return view('sltr_field_data.index', compact('PageTitle', 'PageDescription'));
    }

 
 
}