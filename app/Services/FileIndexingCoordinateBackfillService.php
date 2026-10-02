<?php

namespace App\Services;

use App\Models\FileIndexing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FileIndexingCoordinateBackfillService
{
    /** @var array<string, array{lat: float, lng: float}|null> */
    private array $cache = [];

    /** @var array<string, string> district id => name */
    private array $districtNames = [];

    /** @var array<string, string> lga id => name */
    private array $lgaNames = [];

    /** Why the last geocode call failed, so a broken run can say so instead of just counting ERRORs. */
    private ?string $lastError = null;

    /**
     * When true, an LGA-tier match is refused rather than written. That tier is the
     * LGA's town centre, identical for every file in the LGA — accurate as "which
     * LGA", misleading as "where the parcel is". Off by default so behaviour matches
     * the generator's Pin on Map.
     */
    private bool $skipLgaTier = false;

    public function skipLgaTier(bool $skip = true): self
    {
        $this->skipLgaTier = $skip;
        return $this;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function remainingCount(bool $force = false): int
    {
        $query = FileIndexing::on('sqlsrv');
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }
        return $query->count();
    }

    /**
     * Geocode up to $limit rows (with id > $afterId) and write back latitude/longitude.
     * The $afterId cursor is required so rows that get SKIPPED_NO_ADDRESS (never
     * written to) don't keep getting re-selected as the "first N missing coords"
     * forever — the caller advances $afterId to the batch's last id each call.
     *
     * @return array{processed: int, written: int, remaining: int, last_id: ?int, counts: array<string, int>, last_error: ?string}
     */
    public function runBatch(int $limit, bool $dryRun = false, bool $force = false, ?int $afterId = null): array
    {
        $this->districtNames = DB::connection('sqlsrv')->table('districts')->pluck('name', 'id')->all();
        $this->lgaNames = DB::connection('sqlsrv')->table('lgas')->pluck('name', 'id')->all();

        $query = FileIndexing::on('sqlsrv')
            ->select(['id', 'plot_number', 'street_name', 'district', 'lga', 'latitude', 'longitude']);

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        $rows = $query->orderBy('id')->limit($limit)->get();

        $counts = [];
        $written = 0;
        $lastId = $afterId;

        foreach ($rows as $row) {
            $lastId = $row->id;
            $queries = $this->buildGeocodeQueries($row);

            if (empty($queries)) {
                $counts['SKIPPED_NO_ADDRESS'] = ($counts['SKIPPED_NO_ADDRESS'] ?? 0) + 1;
                continue;
            }

            $result = $this->geocodeChain($queries);
            // Report which tier answered: an "OK (lga)" hit is a town centroid shared
            // by every file in that LGA, not a parcel position.
            $status = $result['status'] === 'OK'
                ? 'OK (' . ($result['tier'] ?? 'unknown') . ')'
                : $result['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if ($result['status'] === 'OK' && !$dryRun) {
                $updateQuery = FileIndexing::on('sqlsrv')->where('id', $row->id);
                if (!$force) {
                    $updateQuery->whereNull('latitude')->whereNull('longitude');
                }
                $written += $updateQuery->update([
                    'latitude'  => $result['lat'],
                    'longitude' => $result['lng'],
                ]);
            }
        }

        return [
            'processed'  => $rows->count(),
            'written'    => $written,
            'remaining'  => $this->remainingCount($force),
            'last_id'    => $lastId,
            'counts'     => $counts,
            'last_error' => $this->lastError,
        ];
    }

    /**
     * Resolve coordinates for one report/application: prefer the latitude/longitude
     * already stored on the file-indexing row for the file number, otherwise geocode
     * the location parts (street-level location, district, LGA) through the same
     * Nominatim chain the bulk backfill uses.
     *
     * @return array{lat: float, lng: float, source: 'indexing'|'geocoded'}|null
     */
    public function coordinates(?string $fileNumber, array $parts): ?array
    {
        $fileNumber = trim((string) $fileNumber);

        if ($fileNumber !== '') {
            $row = FileIndexing::on('sqlsrv')
                ->where('file_number', $fileNumber)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('id')
                ->first(['latitude', 'longitude']);

            if ($row !== null) {
                return [
                    'lat' => (float) $row->latitude,
                    'lng' => (float) $row->longitude,
                    'source' => 'indexing',
                ];
            }
        }

        $this->districtNames = DB::connection('sqlsrv')->table('districts')->pluck('name', 'id')->all();
        $this->lgaNames = DB::connection('sqlsrv')->table('lgas')->pluck('name', 'id')->all();

        $location = $this->cleanPart($parts['location'] ?? null);
        $district = $this->cleanPart($this->resolveNamedPart($parts['district'] ?? null, $this->districtNames));
        $lga = $this->cleanPart($this->resolveNamedPart($parts['lga'] ?? null, $this->lgaNames));

        $build = function (array $parts) {
            $parts = array_values(array_filter($parts, fn ($p) => $p !== null && $p !== ''));
            if (empty($parts)) {
                return null;
            }
            return implode(', ', array_merge($parts, ['KANO', 'NIGERIA']));
        };

        $candidates = [];
        if ($location !== null) {
            $candidates[] = ['tier' => 'location', 'query' => $build([$location, $district, $lga])];
            $candidates[] = ['tier' => 'location', 'query' => $build([$location, $district])];
            $candidates[] = ['tier' => 'location', 'query' => $build([$location])];
        }
        if ($district !== null) {
            $candidates[] = ['tier' => 'district', 'query' => $build([$district, $lga])];
            $candidates[] = ['tier' => 'district', 'query' => $build([$district])];
        }
        if ($lga !== null) {
            $candidates[] = ['tier' => 'lga', 'query' => $build([$lga])];
        }

        $seen = [];
        $queries = [];
        foreach ($candidates as $candidate) {
            if ($candidate['query'] === null || isset($seen[$candidate['query']])) {
                continue;
            }
            $seen[$candidate['query']] = true;
            $queries[] = $candidate;
        }

        if (empty($queries)) {
            return null;
        }

        $result = $this->geocodeChain($queries);

        if (($result['status'] ?? '') !== 'OK') {
            return null;
        }

        return ['lat' => $result['lat'], 'lng' => $result['lng'], 'source' => 'geocoded'];
    }

    /**
     * Build the geocode queries for one row, most specific first.
     *
     * Nominatim (unlike Google) knows very little Kano street-level detail, so a
     * single full address mostly returns nothing. The chain degrades the same way
     * the generator's "Pin on Map" does - street, then district, then LGA alone -
     * so a row lands somewhere sensible instead of being skipped outright.
     *
     * Parts mirror buildGeocodeAddress() in
     * resources/views/fileindexing/addons/create_indexing.blade.php, plus resolving
     * legacy rows where district/lga were stored as bare reference-table IDs
     * (e.g. district="14") instead of names.
     *
     * @return array<int, array{tier: string, query: string}>
     */
    private function buildGeocodeQueries(FileIndexing $row): array
    {
        $plot = $this->cleanPart($row->plot_number);
        $street = $this->cleanPart($row->street_name);
        $district = $this->cleanPart($this->resolveNamedPart($row->district, $this->districtNames));
        $lga = $this->cleanPart($this->resolveNamedPart($row->lga, $this->lgaNames));

        $build = function (array $parts) {
            $parts = array_values(array_filter($parts, fn ($p) => $p !== null && $p !== ''));
            if (empty($parts)) {
                return null;
            }
            return implode(', ', array_merge($parts, ['KANO', 'NIGERIA']));
        };

        $candidates = [];

        // A plot number only means something next to a street name; on its own it is
        // a bare digit that drags the query away from any real match.
        if ($street !== null) {
            $candidates[] = ['tier' => 'street', 'query' => $build([$plot, $street, $district, $lga])];
            $candidates[] = ['tier' => 'street', 'query' => $build([$street, $district, $lga])];
        }
        if ($district !== null) {
            $candidates[] = ['tier' => 'district', 'query' => $build([$district, $lga])];
        }
        if ($lga !== null) {
            $candidates[] = ['tier' => 'lga', 'query' => $build([$lga])];
        }

        // Drop nulls and duplicates, keeping most-specific-first order.
        $seen = [];
        $queries = [];
        foreach ($candidates as $candidate) {
            $q = $candidate['query'];
            if ($q === null || isset($seen[$q])) {
                continue;
            }
            $seen[$q] = true;
            $queries[] = $candidate;
        }

        return $queries;
    }

    /**
     * Normalise one address part, dropping the placeholder values legacy rows hold.
     */
    private function cleanPart(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        // Defensive: strip legacy "STREET: X" / "DISTRICT: X" / "LGA: X" / "STATE: X" labels.
        $value = trim(preg_replace('/^(street|district|lga|state)\s*:\s*/i', '', $value));
        if ($value === '' || preg_match('/^select\s/i', $value)) {
            return null;
        }
        return $value;
    }

    /**
     * If $value is a bare numeric reference-table ID, resolve it to its name;
     * unresolvable numeric IDs are dropped rather than sent to the geocoder as-is.
     */
    private function resolveNamedPart(?string $value, array $namesById): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return $namesById[$value] ?? null;
        }
        return $value;
    }

    /**
     * Walk the query chain, returning the first hit.
     *
     * The tier that answered is reported back so the run summary can split hits into
     * street / district / LGA: an LGA-tier hit is a town centroid shared by every
     * file in that LGA, not a parcel position.
     *
     * @param array<int, array{tier: string, query: string}> $queries
     * @return array{status: string, lat?: float, lng?: float, tier?: string}
     */
    private function geocodeChain(array $queries): array
    {
        $lastStatus = 'ZERO_RESULTS';

        foreach ($queries as $candidate) {
            if ($this->skipLgaTier && $candidate['tier'] === 'lga') {
                $lastStatus = 'SKIPPED_LGA_ONLY';
                continue;
            }

            $result = $this->geocode($candidate['query']);

            if ($result['status'] === 'OK') {
                $result['tier'] = $candidate['tier'];
                return $result;
            }

            // A transport failure means the whole run is broken (blocked outbound,
            // rate limited) - do not burn the rest of the chain on it.
            if ($result['status'] === 'ERROR') {
                return $result;
            }

            $lastStatus = $result['status'];
        }

        return ['status' => $lastStatus];
    }

    /**
     * Geocode one address through OpenStreetMap's Nominatim - the same service the
     * browser uses via public/js/maps/leaflet-maps-shim.js, so bulk-filled and
     * hand-pinned coordinates agree. This replaced Google Geocoding, which the app
     * no longer calls anywhere and which needed an API key that never reached
     * production.
     *
     * @return array{status: string, lat?: float, lng?: float}
     */
    private function geocode(string $address): array
    {
        if (array_key_exists($address, $this->cache)) {
            $cached = $this->cache[$address];
            return $cached === null
                ? ['status' => 'ZERO_RESULTS']
                : ['status' => 'OK', 'lat' => $cached['lat'], 'lng' => $cached['lng']];
        }

        // Nominatim's usage policy caps callers at ~1 request/second. This is the
        // hard ceiling on throughput: roughly 3,000 rows/hour, cache hits aside.
        usleep(1_100_000);

        try {
            $response = Http::withHeaders([
                    // Nominatim rejects requests that do not identify their caller.
                    'User-Agent' => 'KLAES-KANGIS/1.0 (file indexing coordinate backfill)',
                    'Accept' => 'application/json',
                ])
                ->timeout(20)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'limit' => 5,
                    'countrycodes' => 'ng',
                    'addressdetails' => 0,
                    'q' => $address,
                ]);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return ['status' => 'ERROR'];
        }

        if (!$response->ok()) {
            $this->lastError = 'HTTP ' . $response->status() . ' from Nominatim';
            return ['status' => 'ERROR'];
        }

        $results = $response->json();

        if (!is_array($results) || empty($results)) {
            $this->cache[$address] = null;
            return ['status' => 'ZERO_RESULTS'];
        }

        $hit = $this->pickBestPlace($results);
        $lat = round((float) $hit['lat'], 7);
        $lng = round((float) $hit['lon'], 7);

        $this->cache[$address] = ['lat' => $lat, 'lng' => $lng];

        return ['status' => 'OK', 'lat' => $lat, 'lng' => $lng];
    }

    /**
     * Nominatim ranks an LGA's administrative boundary above the town inside it, and
     * a boundary's point is the polygon centroid - for "Albasu, Kano, Nigeria" that
     * is empty bush ~11km from Albasu town. Prefer the most specific settlement
     * (class "place"), exactly as the browser shim does.
     *
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>
     */
    private function pickBestPlace(array $results): array
    {
        $best = null;
        foreach ($results as $r) {
            if (($r['class'] ?? null) !== 'place') {
                continue;
            }
            if ($best === null || (float) ($r['place_rank'] ?? 0) > (float) ($best['place_rank'] ?? 0)) {
                $best = $r;
            }
        }
        return $best ?? $results[0];
    }
}
