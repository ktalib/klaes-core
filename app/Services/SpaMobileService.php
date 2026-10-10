<?php

namespace App\Services;

use App\Models\SpaApplication;
use App\Models\SpaFieldData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for SPAS land-record and field-inspection writes.
 *
 * WHY THIS EXISTS
 * ---------------
 * The Add Land Record form is hand-maintained in two Blade files — the desktop
 * copy in special_assignment/field_data/index.blade.php and the mobile copy in
 * special_assignment/mobile.blade.php — with no shared partial and no shared
 * service. On 2026-08-15 a rule added to storeLandRecord()
 * (`lga` required_if customary) broke EVERY customary save from SPAS Mobile
 * with a 422, because the mobile copy had no LGA field to post. It failed
 * silently: mobile showed only a generic "Save failed".
 *
 * The offline Capacitor app would have been a third copy of the same rules.
 * Instead it validates against the rule set this class exposes
 * (`landRecordRules()` / `fieldDataRules()`), so an offline record is rejected
 * at capture time — while the surveyor is still standing on the plot and can
 * fix it — rather than sitting in the sync outbox failing forever.
 *
 * RULE: any change to validation here must be reflected in both Blade forms
 * and the mobile app's client-side mirror. That is now one edit here plus a
 * check of the front-ends, instead of three independent edits.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §5, §6.1
 */
class SpaMobileService
{
    /**
     * The uses the "General Landuse (Observed around) - Special" dropdown offers
     * for a customary title. One list for the desktop form, the mobile page and
     * the app's lookup API, so the three cannot disagree. Industrial was added on
     * request (2026-10-08); it was previously left out.
     */
    public const CUSTOMARY_LAND_USES = ['RESIDENTIAL', 'COMMERCIAL', 'AGRICULTURAL', 'INDUSTRIAL'];

    /** The register's land uses that a customary title may take, in register order. */
    public static function customaryLandUses($landUses)
    {
        return collect($landUses)
            ->filter(fn ($u) => in_array(strtoupper(trim((string) $u)), self::CUSTOMARY_LAND_USES, true))
            ->values();
    }

    private SpaBillingService $billing;

    /**
     * Optional on purpose.
     *
     * Most of this class is validation rules, and callers — including tests —
     * construct it bare to read them. Requiring the billing service would make
     * `new SpaMobileService()` fail for that harmless use, so it defaults
     * instead of being demanded.
     */
    public function __construct(?SpaBillingService $billing = null)
    {
        $this->billing = $billing ?? new SpaBillingService();
    }

    /**
     * Validation rules for creating a land record.
     *
     * Shared verbatim by the web controller, the JSON API, and (mirrored
     * client-side) the offline app. `photos.*` differs between transports:
     * the web/API path uploads files, the offline path pushes already-stored
     * local URIs, so the caller opts out with $withPhotos = false.
     */
    public function landRecordRules(bool $withPhotos = true): array
    {
        $rules = [
            'land_title_type' => 'required|in:statutory,customary',
            // `nullable` matters for the offline app. A customary record has no
            // file number — the server generates one — and a client that sends
            // `file_number: null` explicitly used to fail `string` with "The
            // file number must be a string". Offline that is unrecoverable: the
            // push 422s forever and the surveyor's record never lands.
            // `required_if` is an implicit rule and still fires for statutory.
            'file_number'     => 'nullable|required_if:land_title_type,statutory|string|max:255',
            'owner_name'      => 'required|string|max:255',
            'phone'           => 'nullable|string|max:20',
            'proposed_use'    => 'required|string|max:255',
            'existing_use'    => 'required|string|max:255',
            // A customary title has no indexed file to inherit an address from,
            // so the LGA is picked by hand and is the minimum needed to place it.
            'lga'             => 'required_if:land_title_type,customary|nullable|string|max:255',
            'district'        => 'nullable|string|max:255',
        ];

        if ($withPhotos) {
            $rules['photos.*'] = 'nullable|image|max:5120';
        }

        return $rules;
    }

    public function landRecordMessages(): array
    {
        return [
            'lga.required_if' => 'Please select the LGA for this customary title.',
        ];
    }

    /**
     * Validation rules for logging a field inspection.
     *
     * `spa_application_id` is deliberately NOT required here, unlike the old
     * inline rules in storeFieldData(). An inspection pushed from the offline
     * app may arrive before — or in the same batch as — its parent record, and
     * links by `spa_application_client_uuid` instead. The server column was
     * relaxed to nullable for exactly this reason, which is what lets the
     * outbox drain as a flat FIFO with no dependency ordering.
     *
     * requireServerParent() restores the stricter rule for the web forms,
     * where a parent id always exists.
     */
    public function fieldDataRules(bool $requireServerParent = false, bool $withPhotos = true): array
    {
        $rules = [
            'spa_application_id'          => 'nullable|exists:sqlsrv.spa_applications,id',
            'spa_application_client_uuid' => 'nullable|string|max:36',
            'inspection_date'             => 'required|date',
            'findings'                    => 'required|string',
        ];

        if ($requireServerParent) {
            $rules['spa_application_id'] = 'required|exists:sqlsrv.spa_applications,id';
        }

        if ($withPhotos) {
            $rules['photos.*'] = 'nullable|image|max:5120';
        }

        return $rules;
    }

    // -----------------------------------------------------------------------
    // Land records
    // -----------------------------------------------------------------------

    /**
     * Reject a duplicate file number with a message naming the existing record.
     *
     * One SPAS application per file number is the confirmed business rule.
     * UQ_spa_applications_file_number enforces it in the database; this check
     * runs first so the user sees a form error rather than a raw constraint
     * 500. Customary numbers are generated server-side and cannot collide, so
     * they skip the check.
     *
     * @return string|null null when the file number is free
     */
    public function duplicateFileNumberError(string $landTitleType, ?string $fileNumber): ?string
    {
        if ($landTitleType === 'customary' || ! $fileNumber) {
            return null;
        }

        $existing = SpaApplication::where('file_number', $fileNumber)->first();

        if (! $existing) {
            return null;
        }

        return 'File '.$fileNumber.' is already in SPAS (added '
            .optional($existing->created_at)->format('d/m/Y')
            .'). Open the existing record instead of adding it again.';
    }

    /**
     * Create a land record.
     *
     * @param  array  $data         validated input
     * @param  array  $photoPaths   already-stored relative storage paths
     * @param  string|null $createdBy
     */
    public function createLandRecord(array $data, array $photoPaths = [], ?string $createdBy = null): SpaApplication
    {
        $isCustomary = ($data['land_title_type'] ?? null) === 'customary';

        // The temporary customary number is generated server-side, never taken
        // from the client, so the sequence stays authoritative and
        // collision-free. An offline device that invented a placeholder number
        // gets the real one back in the response and must adopt it.
        $fileNumber = $isCustomary
            ? SpaApplication::generateCustomaryFileNumber()
            : ($data['file_number'] ?? null);

        $app = SpaApplication::create([
            'file_number'      => $fileNumber,
            'tracking_id'      => $isCustomary ? null : ($data['tracking_id'] ?? null),
            'file_indexing_id' => $isCustomary ? null : (($data['file_indexing_id'] ?? null) ?: null),
            'is_indexed'       => $isCustomary ? false : (bool) ($data['is_indexed'] ?? false),
            'land_title_type'  => $data['land_title_type'],
            'owner_name'       => $data['owner_name'],
            'phone'            => $data['phone'] ?? null,
            'location'         => $data['location'] ?? null,
            'district'         => $data['district'] ?? null,
            'lga'              => $data['lga'] ?? null,
            'land_use_type'    => $data['land_use_type'] ?? null,
            'proposed_use'     => $data['proposed_use'],
            'existing_use'     => $data['existing_use'],
            'photos'           => $photoPaths ?: null,
            'status'           => 'open',
            'created_by'       => $createdBy,
            'client_uuid'      => $data['client_uuid'] ?? null,
        ]);

        // A contravention is billable the moment it is recorded. Raising it
        // here rather than in a controller means it happens for EVERY caller —
        // the desktop form, the mobile web page and the offline app's sync push
        // all land on this one method. Never throws: a tariff problem must not
        // cost a surveyor their field data.
        $this->billing->billForContravention($app, $createdBy);

        // ...and the First Serve goes out at the same moment: recorded as served,
        // with the SMS when the record has a usable phone number. Never throws.
        (new SpaNoticeService($this->billing))->serveFirstForContravention($app, $createdBy, auth()->id());

        return $app->refresh();
    }

    /**
     * Validation rules for editing an existing land record.
     *
     * Separate from landRecordRules() because an edit cannot change what the
     * record *is*: `land_title_type` and `file_number` are fixed at creation
     * (the file number is the identity, and one application per file number is
     * enforced by a unique index), so neither is accepted here.
     *
     * `status` is office workflow — approving a record or marking a certificate
     * issued is not something a field device may do — so it is only allowed
     * when $withStatus is set, which is the web path.
     */
    public function landRecordUpdateRules(bool $withStatus = false): array
    {
        $rules = [
            'owner_name'    => 'required|string|max:255',
            'phone'         => 'nullable|string|max:20',
            'location'      => 'nullable|string|max:255',
            'district'      => 'nullable|string|max:255',
            'lga'           => 'nullable|string|max:255',
            'land_use_type' => 'nullable|string|max:255',
            'proposed_use'  => 'required|string|max:255',
            'existing_use'  => 'required|string|max:255',
        ];

        if ($withStatus) {
            $rules['status'] = 'required|in:open,in_progress,approved,certificate_issued,closed';
        }

        return $rules;
    }

    /**
     * Apply an edit to a land record.
     *
     * Only keys actually present in $data are written, so a partial payload
     * from the offline app cannot blank out fields it never displayed.
     */
    public function applyLandRecordUpdate(SpaApplication $app, array $data, bool $withStatus = false): SpaApplication
    {
        $fields = ['owner_name', 'phone', 'location', 'district', 'lga', 'land_use_type', 'proposed_use', 'existing_use'];

        if ($withStatus) {
            $fields[] = 'status';
        }

        $changes = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $data[$field];
            }
        }

        $app->update($changes);
        $app->refresh();

        // An edit can CREATE a contravention that did not exist before — the
        // prevailing use is often corrected after the first visit — so the
        // check runs again here. Idempotent, so an edit that merely fixes a
        // phone number cannot raise a second bill.
        $this->billing->billForContravention($app);
        // An edit that creates a contravention serves its First Serve too; once only.
        (new SpaNoticeService($this->billing))->serveFirstForContravention($app, auth()->user()->name ?? null, auth()->id());

        return $app;
    }

    /**
     * True when applying this payload would change nothing.
     *
     * This is what makes an update push safely retryable without a revision
     * column. A push that succeeded server-side but whose response was lost
     * gets replayed by the device; without this check the optimistic-concurrency
     * guard would see its own write as someone else's edit and raise a spurious
     * conflict at the surveyor.
     */
    public function isNoOpUpdate(SpaApplication $app, array $data, bool $withStatus = false): bool
    {
        $fields = ['owner_name', 'phone', 'location', 'district', 'lga', 'land_use_type', 'proposed_use', 'existing_use'];

        if ($withStatus) {
            $fields[] = 'status';
        }

        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ((string) $data[$field] !== (string) $app->{$field}) {
                return false;
            }
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Who may edit what (web, mobile page and app API alike)
    // -----------------------------------------------------------------------

    /**
     * A captured land record may be edited only by a Super Admin or by the
     * officer who captured it. Anyone else who visits the site adds a new
     * inspection to the record instead (a revisit) — see revisitsAllowed().
     *
     * created_by holds the capturing officer's display name (every path writes
     * the user's name there), so that is what is compared.
     */
    public static function canEditRecord($record, $user): bool
    {
        if (! $user || ! $record) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return self::sameName($record->created_by ?? null, $user->name ?? null);
    }

    /** An inspection: a Super Admin, or the officer who made that visit. */
    public static function canEditInspection($inspection, $user): bool
    {
        if (! $user || ! $inspection) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return ($inspection->surveyor_id && (int) $inspection->surveyor_id === (int) $user->id)
            || self::sameName($inspection->created_by ?? null, $user->name ?? null);
    }

    private static function sameName(?string $a, ?string $b): bool
    {
        $norm = fn ($v) => mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $v)), 'UTF-8');

        return $norm($a) !== '' && $norm($a) === $norm($b);
    }

    private static ?bool $revisitsAllowed = null;

    /** Whether a file may carry more than one inspection (the unique index is gone). */
    public static function revisitsAllowed(): bool
    {
        return self::$revisitsAllowed ??= ! DB::connection('sqlsrv')->selectOne(
            "SELECT 1 AS x FROM sys.indexes WHERE object_id = OBJECT_ID('spa_field_data') AND name = 'UQ_spa_field_data_file_number'"
        );
    }

    // -----------------------------------------------------------------------
    // Field data
    // -----------------------------------------------------------------------

    /**
     * Reject a second inspection for a file that already has one.
     *
     * Backed by the filtered unique index on spa_field_data.file_number — the
     * app-level check alone let two devices pushing concurrently both pass and
     * both insert.
     *
     * @return string|null null when no inspection exists yet
     */
    public function duplicateInspectionError(?string $fileNumber): ?string
    {
        // Revisits: once UQ_spa_field_data_file_number is gone (migration
        // 2026_10_08_140000) a file may carry several inspections, so a second
        // one is a new visit to the same record, not a duplicate.
        if (! $fileNumber || self::revisitsAllowed()) {
            return null;
        }

        if (! SpaFieldData::where('file_number', $fileNumber)->exists()) {
            return null;
        }

        return "A field inspection record already exists for file number {$fileNumber}.";
    }

    /**
     * Normalise coordinates into ['lat' => float, 'lng' => float].
     *
     * Accepts the JSON object the map picker posts, an already-decoded array
     * (the JSON API path), or a raw "lat, lng" string. Returns null when the
     * input is empty — callers treat "unparseable" as an error rather than
     * silently saving an inspection with no pin, so this throws instead of
     * degrading.
     *
     * @throws ValidationException when a value was supplied but is unusable
     */
    public function normalizeCoordinates($raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (is_array($decoded)
            && isset($decoded['lat'], $decoded['lng'])
            && is_numeric($decoded['lat'])
            && is_numeric($decoded['lng'])) {
            return ['lat' => (float) $decoded['lat'], 'lng' => (float) $decoded['lng']];
        }

        if (! is_array($raw)) {
            preg_match_all('/-?\d+(?:\.\d+)?/', (string) $raw, $matches);
            if (count($matches[0]) >= 2) {
                return ['lat' => (float) $matches[0][0], 'lng' => (float) $matches[0][1]];
            }
        }

        throw ValidationException::withMessages([
            'coordinates' => 'Coordinates could not be understood — please re-pick the pin location on the map.',
        ]);
    }

    /**
     * Create a field-inspection row and advance its parent application.
     *
     * @param  array  $data        validated input
     * @param  array  $photoPaths  already-stored relative storage paths
     */
    public function createFieldData(array $data, array $photoPaths = [], ?int $surveyorId = null, ?string $createdBy = null): SpaFieldData
    {
        $parentId = $data['spa_application_id'] ?? null;

        // An inspection pushed from offline may name its parent only by the
        // device-generated uuid. Resolve it to a server id when the parent has
        // already synced; leave it null (and keep the uuid) when it has not,
        // so the row can still land and be stitched up later.
        if (! $parentId && ! empty($data['spa_application_client_uuid'])) {
            $parentId = SpaApplication::where('client_uuid', $data['spa_application_client_uuid'])->value('id');
        }

        $record = SpaFieldData::create([
            'spa_application_id'          => $parentId,
            'spa_application_client_uuid' => $data['spa_application_client_uuid'] ?? null,
            'file_number'                 => $data['file_number'] ?? null,
            'surveyor_id'                 => $surveyorId,
            'inspection_date'             => $data['inspection_date'],
            'coordinates'                 => $this->normalizeCoordinates($data['coordinates'] ?? null),
            'parcel_geometry'             => $this->decodeJson($data['parcel_geometry'] ?? null),
            'findings'                    => $data['findings'],
            'photos'                      => $photoPaths ?: null,
            'status'                      => 'active',
            'created_by'                  => $createdBy,
            'client_uuid'                 => $data['client_uuid'] ?? null,
        ]);

        if ($parentId) {
            SpaApplication::where('id', $parentId)
                ->where('status', 'open')
                ->update(['status' => 'in_progress']);
        }

        return $record;
    }

    // -----------------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------------

    /**
     * Store uploaded photos and return their relative storage paths.
     *
     * @param  UploadedFile[]|null  $files
     */
    public function storePhotos(?array $files, string $folder): array
    {
        $paths = [];

        foreach ($files ?? [] as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $file->store($folder, 'public');
            }
        }

        return $paths;
    }

    /** Accepts a JSON string or an already-decoded array. */
    private function decodeJson($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
