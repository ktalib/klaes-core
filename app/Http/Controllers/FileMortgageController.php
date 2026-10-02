<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FileMortgageController extends Controller
{
    /**
     * Return mortgages associated with a given file number.
     * Query is intentionally simple: looks up `pra` table rows where fileno matches
     * and transaction_type or instrument_type contains 'mortgage'.
     */
    public function index(Request $request)
    {
        $fileno = (string) $request->query('fileno', '');
        if (trim($fileno) === '') {
            return response()->json(['count' => 0, 'mortgages' => []]);
        }

        $mortgages = DB::connection('sqlsrv')
            ->table('pra')
            ->where(function ($q) use ($fileno) {
                $q->where('mlsFNo', $fileno)
                    ->orWhere('kangisFileNo', $fileno)
                    ->orWhere('NewKANGISFileno', $fileno)
                    ->orWhere('fileno', $fileno);
            })
            ->where(function ($q) {
                $q->whereRaw("LOWER(transaction_type) LIKE '%mortgage%'")
                    ->orWhereRaw("LOWER(instrument_type) LIKE '%mortgage%'");
            })
            ->select(['id', 'transaction_type', 'instrument_type', 'Mortgagor', 'Mortgagee', 'Mortgagor_2', 'regNo', 'transaction_date'])
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json([
            'count' => $mortgages->count(),
            'mortgages' => $mortgages,
        ]);
    }
}
