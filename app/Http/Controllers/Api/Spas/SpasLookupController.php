<?php

namespace App\Http\Controllers\Api\Spas;

use App\Http\Controllers\Controller;
use App\Support\LgaNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reference data the SPAS offline app caches on device.
 *
 * These mirror the /api/reference/* endpoints the web forms already call. The
 * app points at these copies instead so everything it needs sits behind one
 * token guard and one caching convention.
 *
 * Conflict rule for all of it: server always wins, simple overwrite. None of
 * this is ever edited on the device.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §5, §6.2
 */
class SpasLookupController extends Controller
{
    /**
     * Indexed files for offline lookup, in pages.
     *
     * TWO MODES, because they have opposite needs.
     *
     * SEARCH (`q`, `file_numbers`, or a first page with no cursor) orders by
     * file_number — what a human expects — and returns a small result.
     *
     * MIRROR (`after_id`) walks the whole table so a handset can hold every
     * file and work anywhere, not just where it happened to have searched
     * before. It is keyset-paginated on `fi.id`: `WHERE id > ? ORDER BY id`
     * seeks straight into the clustered index. The obvious alternative, OFFSET,
     * degrades badly — SQL Server must count and discard every skipped row, so
     * the last pages of a 133,000-row walk cost far more than the first, and
     * the download slows to a crawl exactly when it is nearly done.
     *
     * The cursor also makes the walk RESUMABLE. A surveyor loses signal
     * mid-download constantly; keeping `after_id` on the device means the next
     * attempt continues instead of starting the whole mirror again.
     */
    public function fileIndex(Request $request): JsonResponse
    {
        $request->validate([
            'lga'          => 'nullable|string|max:255',
            'district'     => 'nullable|string|max:255',
            'file_numbers' => 'nullable|array|max:500',
            'q'            => 'nullable|string|max:255',
            'after_id'     => 'nullable|integer|min:0',
            'limit'        => 'nullable|integer|min:1|max:2000',
        ]);

        // Bigger ceiling than the old 1,000: a full mirror is ~130 round trips
        // at 1,000 a page, and each one costs a connection setup on a mobile
        // link. Still capped so one request cannot become a huge download.
        $limit = (int) $request->input('limit', 500);
        $mirroring = $request->filled('after_id');

        $query = DB::connection('sqlsrv')
            ->table('file_indexings as fi')
            ->leftJoin('fileNumber as fn', 'fn.tracking_id', '=', 'fi.tracking_id')
            ->whereNotNull('fi.file_number')
            // DCIV files are excluded from SPAS everywhere else; keeping the
            // cache consistent with the desktop list avoids offline results
            // that vanish once the surveyor is back online.
            ->where('fi.file_number', 'not like', 'DCIV%')
            ->where('fi.file_number', 'not like', 'DC/%')
            ->select(
                'fi.id as file_indexing_id', 'fi.tracking_id', 'fi.file_number',
                'fi.land_use_type', 'fi.location', 'fi.district', 'fi.lga',
                'fi.phone', 'fn.FileName as file_title'
            );

        // `current_holder` is NVARCHAR(MAX) — a LOB read per row. It is also
        // empty in practice: a sample of 250 files produced a usable name from
        // it ZERO times, because the column does not hold the JSON shape
        // ownerFromHolder() expects. Paying a LOB read on all 133,000 rows of a
        // mirror for a field that is always blank is the wrong trade, so it is
        // fetched only on the small search path, where the cost is trivial and
        // any row that does parse still benefits.
        if (! $mirroring) {
            $query->addSelect('fi.current_holder');
        }

        // Match the LGA's misspellings too, not just the canonical spelling.
        // `file_indexings.lga` is free text (196 distinct values against 45
        // canonical), so an exact filter on "Nasarawa" silently missed the
        // 3,388 files recorded as "NASSARAWA" — invisible offline, with no
        // error to explain the gap. See LgaNormalizer.
        if ($request->filled('lga')) {
            $query->whereIn('fi.lga', LgaNormalizer::variantsFor($request->input('lga')));
        }

        if ($request->filled('district')) {
            $query->where('fi.district', $request->district);
        }

        // Explicit list: the app asking to cache the files it has looked up.
        if ($request->filled('file_numbers')) {
            $query->whereIn('fi.file_number', $request->input('file_numbers'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('fi.file_number', 'like', '%'.$q.'%')
                    ->orWhere('fn.FileName', 'like', '%'.$q.'%')
                    ->orWhere('fi.location', 'like', '%'.$q.'%');
            });
        }

        if ($mirroring) {
            $query->where('fi.id', '>', (int) $request->input('after_id'))->orderBy('fi.id');
        } else {
            $query->orderBy('fi.file_number');
        }

        $rows = $query->limit($limit)->get();

        // A short page is the end of the walk. Reporting it explicitly means
        // the client stops on a definite signal rather than guessing from a
        // count, which would loop forever if the last page happened to be full.
        $hasMore = $mirroring && $rows->count() === $limit;

        return response()->json([
            'success'     => true,
            'count'       => $rows->count(),
            'truncated'   => $rows->count() >= $limit,
            'has_more'    => $hasMore,
            'next_after_id' => $mirroring && $rows->isNotEmpty()
                ? (int) $rows->last()->file_indexing_id
                : null,
            // Counted only on the first page of a mirror, so the app can show
            // real progress ("12,000 of 133,255") instead of an open-ended
            // spinner. Repeating the count on all ~130 pages would cost more
            // than the data itself.
            'total'       => $mirroring && (int) $request->input('after_id') === 0
                ? $this->fileIndexTotal($request)
                : null,
            'server_time' => now()->toIso8601String(),
            'data'        => $rows->map(fn ($r) => [
                'file_number'      => $r->file_number,
                'file_title'       => $r->file_title ?? '',
                // Blank on the mirror path by design (see the select above);
                // the client falls back to file_title, which is populated for
                // ~97% of files.
                'owner_name'       => isset($r->current_holder)
                    ? $this->ownerFromHolder($r->current_holder)
                    : '',
                'land_use_type'    => $r->land_use_type ?? '',
                'location'         => $r->location ?? '',
                'district'         => $r->district ?? '',
                'lga'              => $r->lga ?? '',
                'phone'            => $r->phone ?? '',
                'tracking_id'      => $r->tracking_id,
                'file_indexing_id' => $r->file_indexing_id,
            ]),
        ]);
    }

    /** Full pull — small table, no bounding needed. */
    public function landUses(): JsonResponse
    {
        $all = DB::connection('sqlsrv')->table('klas.dbo.land_uses')
            ->orderBy('landuse')->pluck('landuse');

        return response()->json([
            'success' => true,
            'data'    => $all,
            // Customary land is only ever held for these three uses, so the
            // customary General Landuse dropdown omits Industrial. Sent as a
            // separate list so the device does not re-derive (and re-diverge
            // from) that rule.
            'customary' => $all->filter(
                fn ($u) => in_array(strtoupper(trim($u)), ['RESIDENTIAL', 'COMMERCIAL', 'AGRICULTURAL'], true)
            )->values(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** 45 rows — trivial, mirror whole. */
    public function lgas(): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'data'        => DB::connection('sqlsrv')->table('lgas')
                                ->where('is_active', 1)->orderBy('name')->pluck('name'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * 1,818 rows, ~40KB — still trivial, mirror whole on login.
     *
     * Note `districts` has NO lga_id column, so districts cannot be filtered by
     * LGA. The two selects are independent by necessity, not by design; a
     * "districts within this LGA" feature needs a schema change first.
     */
    public function districts(): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'data'        => DB::connection('sqlsrv')->table('districts')
                                ->where('is_active', 1)->orderBy('name')->pluck('name'),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Next customary file number.
     *
     * Online-only by nature: the sequence is server-authoritative. An offline
     * device creates the record with a local placeholder and adopts the real
     * number returned by the push response.
     */
    public function nextCustomaryFileNumber(): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'file_number' => \App\Models\SpaApplication::generateCustomaryFileNumber(),
        ]);
    }

    /**
     * How many files a full mirror will cover, honouring the same filters.
     *
     * No join to fileNumber: the join only decorates rows with a title and
     * cannot change how many there are, and leaving it out keeps the count fast
     * enough to run inline on the first page.
     */
    private function fileIndexTotal(Request $request): int
    {
        $query = DB::connection('sqlsrv')
            ->table('file_indexings as fi')
            ->whereNotNull('fi.file_number')
            ->where('fi.file_number', 'not like', 'DCIV%')
            ->where('fi.file_number', 'not like', 'DC/%');

        if ($request->filled('lga')) {
            $query->whereIn('fi.lga', LgaNormalizer::variantsFor($request->input('lga')));
        }

        if ($request->filled('district')) {
            $query->where('fi.district', $request->input('district'));
        }

        return (int) $query->count();
    }

    private function ownerFromHolder($holder): string
    {
        $arr = $holder ? json_decode($holder, true) : null;

        if (! is_array($arr) || empty($arr)) {
            return '';
        }

        $first = $arr[0] ?? $arr;

        $name = trim(($first['title'] ?? '').' '.($first['first_name'] ?? '').' '.($first['surname'] ?? ''));

        return $name !== '' ? $name : (string) ($first['corporate_name'] ?? '');
    }
}
