<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Office;
use Illuminate\Http\JsonResponse;

class OfficeController extends Controller
{
    /**
     * Return a simple list of active offices for public consumption.
     */
    public function index(): JsonResponse
    {
        $offices = Office::query()
            ->active()
            ->orderBy('office_name')
            ->get([
                'id',
                'office_code',
                'office_name',
                'office_abbreviation',
                'department',
                'is_active',
            ])
            ->map(function (Office $office) {
                return [
                    'id' => $office->id,
                    'code' => $office->office_code,
                    'name' => $office->office_name,
                    'abbreviation' => $office->office_abbreviation,
                    'department' => $office->department,
                    'is_active' => $office->is_active,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $offices,
        ]);
    }
}
