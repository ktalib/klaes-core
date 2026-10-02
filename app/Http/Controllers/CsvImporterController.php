<?php

namespace App\Http\Controllers;

use App\Services\CsvImporter\Security\ImportAccessGuard;
use Illuminate\Http\Request;

class CsvImporterController extends Controller
{
    public function __construct(private readonly ImportAccessGuard $accessGuard)
    {
    }

    public function fileNumber(Request $request)
    {
        $this->accessGuard->assertSystemAdmin($request->user());

        return view('system-admin.csv-import.file-number');
    }

    public function fileIndexing(Request $request)
    {
        $this->accessGuard->assertSystemAdmin($request->user());

        return view('system-admin.csv-import.file-indexing');
    }
}
