<?php

namespace App\Services\Survey;

use App\Models\Sdi\SdiMapRequest;
use App\Models\Survey\SurveyorPortalAccount;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A map request from the KSSGOF portal (user, 2026-11-06). It joins Survey's own requests in the
 * Production Unit queue, Pending, with the account as the requesting body and contact.
 */
class PortalMapRequests
{
    public function __construct(private SurveyorPortalNotifier $notify)
    {
    }

    /**
     * Rebuilt from its points, so only the drawn outline is kept of what the browser sent: this comes from
     * outside the Ministry and is shown again on staff maps. Null when it is not an area.
     */
    public static function cleanGeometry(?string $json): ?string
    {
        $points = SdiMapRequest::boundaryPointsFromGeoJson(json_decode((string) $json, true));
        if (count($points) < 3) return null;

        $ring = array_map(fn ($p) => [$p['x'], $p['y']], $points);
        $ring[] = $ring[0];

        return json_encode(['type' => 'Feature', 'properties' => (object) [], 'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]]]);
    }

    /** @param array{map_type_id:int, map_name:string, map_reference:?string, map_description:?string, output_formats:array, geometry_geojson:string} $data */
    public function submit(SurveyorPortalAccount $account, array $data, ?UploadedFile $file = null): SdiMapRequest
    {
        $values = $data + SdiMapRequest::boundsFromGeoJson(json_decode($data['geometry_geojson'], true)) + [
            'surveyor_portal_account_id' => $account->id,
            'requester_other' => $account->holder_name,
            'contact_name'    => $account->name,
            'contact_phone'   => $account->phone,
            'contact_email'   => $account->email,
            'priority'        => 'medium',
            'received_date'   => now()->toDateString(),
            'status'          => SdiMapRequest::PENDING,
        ];

        // Saved quietly: SurveyModel stamps created_by with whoever is signed in, and here that is a portal account,
        // whose id would read as a staff user's. created_by and requested_by stay empty; the account column says who.
        // Two requests at the same moment can take one number; the unique index refuses the second.
        for ($try = 1; ; $try++) {
            try {
                $m = new SdiMapRequest($values + ['request_ref' => SdiMapRequest::nextRef('request_ref', 'MR', 4)]);
                $m->saveQuietly();
                break;
            } catch (QueryException $e) {
                if ($try >= 3 || ! str_contains($e->getMessage(), 'request_ref')) throw $e;
            }
        }

        if ($file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs("app/survey/sdi/map-requests/{$m->id}/request", Str::uuid() . '.' . $ext, SdiMapRequest::DISK);
            $m->fill(['request_file_path' => $path, 'request_file_name' => mb_substr($file->getClientOriginalName(), 0, 255)])->saveQuietly();
        }

        $this->notify->mapRequestSubmitted($m);

        return $m;
    }

    /** A file of the surveyor's own request, for download. */
    public function download(string $path, ?string $name)
    {
        abort_unless(Storage::disk(SdiMapRequest::DISK)->exists($path), 404);

        return Storage::disk(SdiMapRequest::DISK)->download($path, $name ?: basename($path));
    }
}
