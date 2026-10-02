<?php

namespace App\Http\Controllers\Api\Spas;

use App\Http\Controllers\Controller;
use App\Models\SpaApplication;
use App\Models\SpaFieldData;
use App\Services\SpaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Delta pull and idempotent push for the SPAS offline mobile app.
 *
 * All writes go through SpaMobileService, the same path the Blade forms use,
 * so an offline record is validated by exactly the rules a desktop save is.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §5, §6
 */
class SpasSyncController extends Controller
{
    /** Max rows per pull page. Keeps a 2G reconnect from stalling on one request. */
    private const PAGE_SIZE = 200;

    // -----------------------------------------------------------------------
    // Pull (server → device)
    // -----------------------------------------------------------------------

    public function records(Request $request): JsonResponse
    {
        $query = SpaApplication::query()->orderBy('updated_at')->orderBy('id');

        $this->applySinceCursor($query, $request->input('since'));

        $rows = $query->limit(self::PAGE_SIZE)->get();

        return $this->pullResponse($rows->map(fn (SpaApplication $a) => [
            'id'               => $a->id,
            'client_uuid'      => $a->client_uuid,
            'file_number'      => $a->file_number,
            'tracking_id'      => $a->tracking_id,
            'file_indexing_id' => $a->file_indexing_id,
            'is_indexed'       => (bool) $a->is_indexed,
            'land_title_type'  => $a->land_title_type,
            'owner_name'       => $a->owner_name,
            'phone'            => $a->phone,
            'location'         => $a->location,
            'district'         => $a->district,
            'lga'              => $a->lga,
            'land_use_type'    => $a->land_use_type,
            'proposed_use'     => $a->proposed_use,
            'existing_use'     => $a->existing_use,
            'photos'           => $this->photoUrls($a->photos),
            'scenario'         => $a->scenario,
            'status'           => $a->status,
            'created_by'       => $a->created_by,
            'created_at'       => optional($a->created_at)->toIso8601String(),
            'updated_at'       => optional($a->updated_at)->toIso8601String(),
        ]));
    }

    public function fieldData(Request $request): JsonResponse
    {
        $query = SpaFieldData::query()->orderBy('updated_at')->orderBy('id');

        $this->applySinceCursor($query, $request->input('since'));

        $rows = $query->limit(self::PAGE_SIZE)->get();

        return $this->pullResponse($rows->map(fn (SpaFieldData $f) => [
            'id'                          => $f->id,
            'client_uuid'                 => $f->client_uuid,
            'spa_application_id'          => $f->spa_application_id,
            'spa_application_client_uuid' => $f->spa_application_client_uuid,
            'file_number'                 => $f->file_number,
            'surveyor_id'                 => $f->surveyor_id,
            'inspection_date'             => optional($f->inspection_date)->toDateString(),
            'coordinates'                 => $f->coordinates,
            'parcel_geometry'             => $f->parcel_geometry,
            'findings'                    => $f->findings,
            'photos'                      => $this->photoUrls($f->photos),
            'status'                      => $f->status,
            'created_at'                  => optional($f->created_at)->toIso8601String(),
            'updated_at'                  => optional($f->updated_at)->toIso8601String(),
        ]));
    }

    // -----------------------------------------------------------------------
    // Push (device → server)
    // -----------------------------------------------------------------------

    public function storeRecord(Request $request, SpaMobileService $spa): JsonResponse
    {
        $request->validate(
            $spa->landRecordRules() + ['client_uuid' => 'required|string|max:36'],
            $spa->landRecordMessages()
        );

        // Idempotent create. A push that succeeded on the server but whose
        // response never reached the device WILL be retried — that is normal on
        // a field connection, not an error. Returning the existing row makes
        // the retry a no-op instead of a duplicate land record.
        $existing = SpaApplication::where('client_uuid', $request->client_uuid)->first();

        if ($existing) {
            return response()->json([
                'success'     => true,
                'duplicate'   => true,
                'id'          => $existing->id,
                'client_uuid' => $existing->client_uuid,
                'file_number' => $existing->file_number,
                'message'     => 'Already synced.',
            ]);
        }

        $duplicate = $spa->duplicateFileNumberError($request->land_title_type, $request->file_number);

        if ($duplicate) {
            // A real conflict, not a retry: another device or an office user
            // already registered this file. 409 rather than 422 so the app can
            // route it to the Conflicts list instead of retrying forever.
            return response()->json(['success' => false, 'conflict' => 'file_number', 'message' => $duplicate], 409);
        }

        $photos = $spa->storePhotos($request->file('photos'), 'spa/land-records');

        $app = $spa->createLandRecord(
            $request->all(),
            $photos,
            $request->user()->name ?? (string) $request->user()->id
        );

        return response()->json([
            'success'     => true,
            'id'          => $app->id,
            'client_uuid' => $app->client_uuid,
            // For a customary title this is NOT the number the device invented
            // offline — the server generates the authoritative one. The app must
            // adopt the value returned here.
            'file_number' => $app->file_number,
            'message'     => 'Land record saved.',
        ], 201);
    }

    public function storeFieldData(Request $request, SpaMobileService $spa): JsonResponse
    {
        $request->validate(
            $spa->fieldDataRules() + ['client_uuid' => 'required|string|max:36']
        );

        $existing = SpaFieldData::where('client_uuid', $request->client_uuid)->first();

        if ($existing) {
            return response()->json([
                'success'     => true,
                'duplicate'   => true,
                'id'          => $existing->id,
                'client_uuid' => $existing->client_uuid,
                'message'     => 'Already synced.',
            ]);
        }

        $duplicate = $spa->duplicateInspectionError($request->file_number);

        if ($duplicate) {
            return response()->json(['success' => false, 'conflict' => 'file_number', 'message' => $duplicate], 409);
        }

        $record = $spa->createFieldData(
            $request->all(),
            $spa->storePhotos($request->file('photos'), 'spa/field-data'),
            $request->user()->id,
            $request->user()->name ?? (string) $request->user()->id
        );

        return response()->json([
            'success'     => true,
            'id'          => $record->id,
            'client_uuid' => $record->client_uuid,
            // Null when the parent has not been pushed yet — the row is still
            // valid and gets stitched up by linkOrphans() on a later sync.
            'spa_application_id' => $record->spa_application_id,
            'message'     => 'Field data saved.',
        ], 201);
    }

    /**
     * Edit a land record that has already synced.
     *
     * Keyed by client_uuid, not server id, because the device knows its own
     * uuid without having to have recorded the server's id.
     *
     * CONCURRENCY (plan §6.3). An office user can edit a synced record from the
     * desktop UI while a surveyor holds a pending edit to the same row. Send
     * `base_updated_at` — the `updated_at` the device last saw — and a server
     * row that has moved on since is reported as a conflict rather than being
     * silently overwritten. Omit it for last-write-wins.
     */
    public function updateRecord(Request $request, string $clientUuid, SpaMobileService $spa): JsonResponse
    {
        $request->validate($spa->landRecordUpdateRules() + [
            'base_updated_at' => 'nullable|date',
        ]);

        $app = SpaApplication::where('client_uuid', $clientUuid)->first();

        if (! $app) {
            // Not synced yet — the create is still sitting in the outbox ahead
            // of this edit. The device should retry after the create drains.
            return response()->json([
                'success' => false,
                'message' => 'No synced record found for that client_uuid — push the create first.',
            ], 404);
        }

        // A replayed push whose response was lost. Answer 200, not 409: the
        // work is already done, and raising a conflict here would put a
        // meaningless prompt in front of the surveyor.
        if ($spa->isNoOpUpdate($app, $request->all())) {
            return response()->json([
                'success'   => true,
                'duplicate' => true,
                'id'        => $app->id,
                'message'   => 'Already up to date.',
            ]);
        }

        if ($conflict = $this->staleWrite($request, $app)) {
            return $conflict;
        }

        $app = $spa->applyLandRecordUpdate($app, $request->all());

        return response()->json([
            'success'     => true,
            'id'          => $app->id,
            'client_uuid' => $app->client_uuid,
            'updated_at'  => optional($app->updated_at)->toIso8601String(),
            'message'     => 'Land record updated.',
        ]);
    }

    /**
     * Edit an inspection that has already synced. Same contract as updateRecord.
     */
    public function updateFieldData(Request $request, string $clientUuid, SpaMobileService $spa): JsonResponse
    {
        $request->validate([
            'inspection_date' => 'required|date',
            'findings'        => 'required|string',
            'base_updated_at' => 'nullable|date',
        ]);

        $record = SpaFieldData::where('client_uuid', $clientUuid)->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'No synced inspection found for that client_uuid — push the create first.',
            ], 404);
        }

        if ($conflict = $this->staleWrite($request, $record)) {
            return $conflict;
        }

        $record->update([
            'inspection_date' => $request->inspection_date,
            'findings'        => $request->findings,
            'coordinates'     => $spa->normalizeCoordinates($request->input('coordinates')),
            'parcel_geometry' => $request->filled('parcel_geometry')
                ? json_decode($request->input('parcel_geometry'), true)
                : $record->parcel_geometry,
        ]);

        return response()->json([
            'success'     => true,
            'id'          => $record->id,
            'client_uuid' => $record->client_uuid,
            'updated_at'  => optional($record->fresh()->updated_at)->toIso8601String(),
            'message'     => 'Inspection updated.',
        ]);
    }

    /**
     * 409 when the server row has changed since the device last saw it.
     *
     * Compared at whole-second granularity because these columns are
     * DATETIME2(0) — the same precision limit that forces the `>=` pull cursor.
     */
    private function staleWrite(Request $request, $row): ?JsonResponse
    {
        if (! $request->filled('base_updated_at') || ! $row->updated_at) {
            return null;
        }

        $base = \Carbon\Carbon::parse($request->input('base_updated_at'))->startOfSecond();

        if ($row->updated_at->startOfSecond()->lessThanOrEqualTo($base)) {
            return null;
        }

        return response()->json([
            'success'  => false,
            'conflict' => 'stale_write',
            'message'  => 'This record was changed in the office after your device last synced. Review before overwriting.',
            // Sent back so the app can show both versions rather than guessing.
            'server_updated_at' => $row->updated_at->toIso8601String(),
            'server_row'        => $row->toArray(),
        ], 409);
    }

    /**
     * Attach photos to an already-pushed row.
     *
     * Text syncs first and photos follow, because a record that reaches the
     * office without its images is far more useful than one that never syncs
     * because a 3 MB upload keeps timing out on a 2G link.
     */
    public function storePhotos(Request $request, SpaMobileService $spa): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|in:record,field_data',
            'client_uuid' => 'required|string|max:36',
            'photos'      => 'required|array|min:1',
            'photos.*'    => 'required|image|max:5120',
        ]);

        $isRecord = $request->entity_type === 'record';

        $row = $isRecord
            ? SpaApplication::where('client_uuid', $request->client_uuid)->first()
            : SpaFieldData::where('client_uuid', $request->client_uuid)->first();

        if (! $row) {
            // The parent has not synced yet. 404 tells the app to leave this
            // upload in the outbox and retry after the parent lands, rather
            // than dropping the photos.
            return response()->json([
                'success' => false,
                'message' => 'No synced row found for that client_uuid — push the record first.',
            ], 404);
        }

        $new = $spa->storePhotos(
            $request->file('photos'),
            $isRecord ? 'spa/land-records' : 'spa/field-data'
        );

        $row->photos = array_values(array_unique(array_merge((array) $row->photos, $new)));
        $row->save();

        return response()->json([
            'success' => true,
            'photos'  => $this->photoUrls($row->photos),
            'message' => count($new).' photo(s) uploaded.',
        ]);
    }

    /**
     * Resolve field-data rows whose parent had not synced when they were pushed.
     *
     * Safe to call on every sync; it is a no-op when there is nothing orphaned.
     */
    public function linkOrphans(Request $request): JsonResponse
    {
        $orphans = SpaFieldData::whereNull('spa_application_id')
            ->whereNotNull('spa_application_client_uuid')
            ->get();

        $linked = 0;

        foreach ($orphans as $orphan) {
            $parentId = SpaApplication::where('client_uuid', $orphan->spa_application_client_uuid)->value('id');

            if (! $parentId) {
                continue;
            }

            $orphan->update(['spa_application_id' => $parentId]);

            SpaApplication::where('id', $parentId)
                ->where('status', 'open')
                ->update(['status' => 'in_progress']);

            $linked++;
        }

        return response()->json([
            'success'   => true,
            'linked'    => $linked,
            'remaining' => $orphans->count() - $linked,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Apply the delta cursor.
     *
     * `>=` is deliberate, not sloppy. created_at/updated_at are DATETIME2(0) —
     * whole-second precision. With a strict `>`, any row written in the same
     * second as the last row of a page is skipped PERMANENTLY, because the
     * cursor has already moved past it. The cost of `>=` is re-sending at most
     * one second of overlap, which the device discards by id/client_uuid.
     */
    private function applySinceCursor($query, $since): void
    {
        if (! $since) {
            return;
        }

        try {
            $query->where('updated_at', '>=', \Carbon\Carbon::parse($since));
        } catch (\Throwable $e) {
            // An unparseable cursor means a full pull, which is correct but
            // expensive — never silently return zero rows.
        }
    }

    private function pullResponse($data): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'count'       => $data->count(),
            // The device stores this as its next cursor. Taken from the server
            // clock, never the device's — field handsets drift.
            'server_time' => now()->toIso8601String(),
            // True when the page was filled: the client should pull again
            // immediately rather than waiting for the next sync tick.
            'has_more'    => $data->count() >= self::PAGE_SIZE,
            'data'        => $data,
        ]);
    }

    private function photoUrls($photos): array
    {
        return array_map(fn ($p) => asset('storage/'.$p), (array) $photos);
    }
}
