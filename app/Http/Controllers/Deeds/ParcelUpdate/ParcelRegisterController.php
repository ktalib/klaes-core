<?php

namespace App\Http\Controllers\Deeds\ParcelUpdate;

use App\Http\Controllers\Controller;
use App\Services\ParcelRegisterService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Dedicated, read-only registers for Subdivision, Merger, Extension and Change of
 * Purpose: Parcel Update, Advance (Duplex) and Parcel Update - Legacy in one table.
 * Processing still happens on each workflow's own page; rows link back there.
 */
class ParcelRegisterController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request, string $type, ParcelRegisterService $service): View
    {
        abort_unless(isset(ParcelRegisterService::TYPES[$type]), 404);

        $search = trim((string) $request->query('search', ''));
        $source = trim((string) $request->query('source', ''));
        $status = trim((string) $request->query('status', ''));

        $all      = $service->rows($type);
        $filtered = $service->filter($all, $search, $source, $status);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $rows = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE)->values(),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('deeds.parcel_register.index', [
            'type'     => $type,
            'config'   => ParcelRegisterService::TYPES[$type],
            'rows'     => $rows,
            'counts'   => $all->countBy('source'),
            'statuses' => $all->pluck('status')->filter()->unique()->sort()->values(),
            'search'   => $search,
            'source'   => $source,
            'status'   => $status,
            'mode'     => $request->query('mode') === 'land' ? 'land' : 'deeds',
        ]);
    }
}
