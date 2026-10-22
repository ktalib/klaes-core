<?php

namespace App\Services\Recertification;

use App\Services\FileIndexing\KangisBlockIndexing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The land file a recertification starts from.
 *
 * A recertification cannot exist without an existing land (MLS) file, so the
 * form starts by picking one. This service reads what the system already holds
 * about that file (fileNumber, file_indexings, mls_file_no, CofO_staging) so the
 * form can backfill it for the clerk to confirm, and works out the KANGIS file
 * number: the one the land file already carries, or the next KN number.
 *
 * The same lookup runs again on save, so values that were locked on the form are
 * taken from the records, never from the posted request.
 */
class RecertificationLandFile
{
    private ?array $columnCache = [];

    public function __construct(private KangisBlockIndexing $kangisBlock)
    {
    }

    /**
     * Everything the form needs about a land file, or null when the system has no
     * record of it.
     *
     * @return array{land_file:string, holder_name:?string, decommissioned:bool,
     *               kangis:array{file_no:?string, candidates:string[]},
     *               new_kangis:array{file_no:?string, source:string, candidates:string[]},
     *               fields:array<string,mixed>, existing_application:?array}|null
     */
    public function resolve(string $fileNo): ?array
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return null;
        }

        $db = DB::connection('sqlsrv');
        $upper = strtoupper($fileNo);

        // One file can have several fileNumber rows; any of them can carry the KANGIS number.
        $fnRows = $db->table('fileNumber')
            ->whereRaw('UPPER(LTRIM(RTRIM(mlsfNo))) = ?', [$upper])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->get();

        $indexRows = $db->table('file_indexings')
            ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$upper])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->whereNull('general_registry')->orWhere('general_registry', '<>', KangisBlockIndexing::REGISTRY))
            ->orderByDesc('id')
            ->get();

        $mls = $db->table('mls_file_no')
            ->whereRaw('UPPER(LTRIM(RTRIM(full_file_number))) = ?', [$upper])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first();

        if ($fnRows->isEmpty() && $indexRows->isEmpty() && !$mls) {
            return null;
        }

        $fn = $fnRows->first();
        $idx = $indexRows->first();

        $decommissioned = $fnRows->isNotEmpty() && $fnRows->every(fn ($r) => (int) ($r->is_decommissioned ?? 0) === 1)
            || ($idx && (int) ($idx->is_decommissioned ?? 0) === 1);

        $cofo = $this->cofoFor($upper);
        $pra = $this->praFor($upper);
        $oss = $this->firstRow('oss_applications', 'file_no', $upper, 'is_deleted');
        $customer = $this->firstRow('customers_staging', 'file_number', $upper, null, 'deleted_at');
        $applicantAddress = $this->ossApplicantAddress($oss);
        $pick = fn (...$values) => $this->firstFilled($values);
        $reg = fn (...$values) => $this->firstFilled(array_map(fn ($v) => $this->registrationValue($v), $values));

        // Location is "district, LGA, state" (never the plot): the last resort for both.
        $locationText = $pick($idx->location ?? null, $mls->location ?? null, $fn->location ?? null, $oss->location ?? null, $pra->property_description ?? null);
        $locationParts = $this->locationParts($locationText);

        // Source order per field: the file's own index / commissioning rows first,
        // then OSS (the application that created the file), then title records.
        $fields = [
            // Section C: plot (Plot No., District, LGA)
            'plotNumber' => $pick($idx->plot_number ?? null, $mls->plot_no ?? null, $fn->plot_no ?? null, $oss->plot_no ?? null, $pra->plot_no ?? null, $cofo->plot_no ?? null),
            'plotSize' => $this->hectares($pick($idx->plot_size ?? null, $pra->plot_size ?? null, $cofo->plot_size ?? null, $oss->land_area ?? null)),
            'district' => $this->upperOrNull($pick($idx->district ?? null, $mls->district ?? null, $fn->district ?? null, $oss->district ?? null, $pra->districtName ?? null, $cofo->districtName ?? null, $locationParts['district'] ?? null)),
            'lga' => $pick($idx->lga ?? null, $mls->lga ?? null, $fn->lga ?? null, $oss->lga ?? null, $pra->lgsaOrCity ?? null, $cofo->lgsaOrCity ?? null, $locationParts['lga'] ?? null),
            'coordinates' => $this->coordinates($indexRows),
            'currentLandUse' => $pick(...array_map(fn ($v) => $this->landUse($v), [$idx->land_use_type ?? null, $mls->land_use ?? null, $oss->application_type ?? null, $pra->land_use ?? null, $cofo->land_use ?? null])),
            'expiryDate' => $this->dateOrNull($cofo->lease_expires ?? ($pra->lease_expires ?? ($cofo->date_expired ?? null))),
            'startDate' => $this->dateOrNull($cofo->lease_begins ?? ($pra->lease_begins ?? null)),

            // Applicant
            'gender' => $this->gender($pick($idx->gender ?? null, $mls->gender ?? null, $oss->sex ?? null)),
            'nin' => $pick($idx->nin ?? null),
            'dateOfBirth' => $this->dateOrNull($idx->dob ?? null),
            'maritalStatus' => $this->lowerOrNull($pick($oss->marital_status ?? null)),
            'nationality' => $this->upperOrNull($pick($oss->nationality ?? null)),
            'stateOfOrigin' => $pick($oss->state_of_origin ?? null),
            'occupation' => $pick($oss->occupation ?? null),

            // Contact (phone, email, applicant address)
            'phoneNo' => $this->firstFilled(array_map(fn ($v) => $this->realPhone($v), [
                $idx->phone ?? null, $mls->phone_no ?? null, $fn->phone_no ?? null, $oss->phone ?? null, $customer->phone ?? null,
            ])),
            'emailAddress' => $this->firstFilled(array_map(fn ($v) => $this->realEmail($v), [
                $mls->email ?? null, $fn->email ?? null, $oss->email ?? null, $customer->email ?? null,
            ])),
            'addressLine1' => $pick($idx->residence_address ?? null, $applicantAddress['line1'] ?? null, $oss->residential_address ?? null, $oss->address ?? null, $mls->address ?? null, $fn->address ?? null),
            'cityTown' => $pick($applicantAddress['city'] ?? null),
            'state' => $pick($applicantAddress['state'] ?? null),

            // Section B: title (CofO first, then the root title record)
            'cofoNumber' => $pick($cofo->cofo_no ?? null),
            'serial_no' => $reg($cofo->serialNo ?? null, $pra->serialNo ?? null),
            'registrationPage' => $reg($cofo->pageNo ?? null, $pra->pageNo ?? null),
            'registrationVolume' => $reg($cofo->volumeNo ?? null, $pra->volumeNo ?? null),
            'registrationNo' => $reg($cofo->regNo ?? null, $pra->regNo ?? null),
            'registrationDate' => $this->dateOrNull($cofo->reg_date ?? ($cofo->transaction_date ?? ($pra->reg_date ?? ($pra->transaction_date ?? null)))),
            'commencementDate' => $this->dateOrNull($cofo->lease_begins ?? ($pra->lease_begins ?? null)),
        ];

        $holderName = $pick($idx->file_title ?? null, $mls->file_name ?? null, $fn->FileName ?? null, $oss->applicant_name ?? null);

        return [
            'land_file' => $pick($idx->file_number ?? null, $fn->mlsfNo ?? null, $mls->full_file_number ?? null) ?? $fileNo,
            'holder_name' => $holderName,
            // The file title split into name parts; filled into Sections A and B, editable.
            'holder_parts' => $this->splitHolderName($holderName),
            'tp_no' => $pick($idx->tp_no ?? null, $mls->tp_no ?? null, $fn->tp_no ?? null, $pra->tp_no ?? null),
            'location' => $pick($idx->location ?? null, $mls->location ?? null, $fn->location ?? null, $oss->location ?? null, $pra->location ?? null),
            'decommissioned' => (bool) $decommissioned,
            'fields' => array_filter($fields, fn ($v) => $v !== null && $v !== ''),
            'existing_application' => $this->existingApplication($upper),
        ] + $this->kangisFor($upper, $fnRows, $indexRows);
    }

    /**
     * The land file's KANGIS numbers, from the fileNumber rows, the land file's
     * index row, and KANGIS Registry indexes relating it. Two kinds:
     *   kangis      the (old-style) KANGIS File No, e.g. KNGP 114
     *   new_kangis  the New KANGIS File No, the KN<digits> series
     * When the records hold no New KANGIS number, one is assigned on save.
     */
    private function kangisFor(string $upper, $fnRows, $indexRows): array
    {
        $found = [];
        foreach ($fnRows as $r) {
            $found[] = $r->NewKANGISFileNo ?? null;
            $found[] = $r->kangisFileNo ?? null;
        }
        foreach ($indexRows as $r) {
            $found[] = $r->new_kangis_file_no ?? null;
            $found[] = $r->kangis_file_no ?? null;
        }
        try {
            foreach ($this->kangisBlock->kangisFilesFor($upper) as $k) {
                $found[] = $k;
            }
        } catch (\Throwable $e) {
            Log::warning('Recertification: KANGIS relation lookup failed', ['file' => $upper, 'error' => $e->getMessage()]);
        }

        $all = collect($found)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '' && strtoupper($v) !== $upper && strtoupper($v) !== 'N/A')
            ->unique(fn ($v) => strtoupper($v))
            ->values();

        [$new, $old] = $all->partition(fn ($v) => self::isNewKangisNumber($v));
        $old = $old->values()->all();
        $new = $new->values()->all();

        return [
            'kangis' => ['file_no' => $old[0] ?? null, 'candidates' => $old],
            'new_kangis' => [
                'file_no' => $new[0] ?? null,
                'source' => $new ? 'existing' : 'new',
                'candidates' => $new,
            ],
        ];
    }

    /** New KANGIS numbers are the KN<digits> series (a _N physical-copy suffix allowed). */
    public static function isNewKangisNumber(?string $v): bool
    {
        return (bool) preg_match('/^KN\d+(_\d+)?$/i', trim((string) $v));
    }

    /** True when a KANGIS file number the clerk picked exists in the system. */
    public function kangisFileExists(string $kangisFileNo): bool
    {
        $upper = strtoupper(trim($kangisFileNo));
        if ($upper === '') {
            return false;
        }
        $db = DB::connection('sqlsrv');

        return $db->table('fileNumber')
                ->whereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = ?', [$upper])
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->exists()
            || $db->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = ?', [$upper])
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->exists();
    }

    /** The live recertification already filed for this land file, if any. */
    public function existingApplication(string $fileNo): ?array
    {
        $upper = strtoupper(trim($fileNo));
        $row = DB::connection('sqlsrv')->table('recertification_applications')
            ->where(function ($q) use ($upper) {
                $q->whereRaw('UPPER(LTRIM(RTRIM(mlsfNo))) = ?', [$upper])
                    ->orWhereRaw('UPPER(LTRIM(RTRIM(mlsFileNo))) = ?', [$upper]);
            })
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first(['id', 'application_reference', 'NewKANGISFileno', 'created_at']);

        return $row ? [
            'id' => $row->id,
            'reference' => $row->application_reference,
            'kangis_file_no' => $row->NewKANGISFileno,
            'created_at' => $row->created_at,
        ] : null;
    }

    /** The newest CofO record for the land file, preferring a Certificate of Occupancy. */
    private function cofoFor(string $upper): ?object
    {
        try {
            return DB::connection('sqlsrv')->table('CofO_staging')
                ->where(function ($q) use ($upper) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = ?', [$upper])
                        ->orWhereRaw('UPPER(LTRIM(RTRIM(fileno))) = ?', [$upper]);
                })
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderByRaw("CASE WHEN instrument_type LIKE '%Occupancy%' OR title_type LIKE '%Occupancy%' OR cofo_no IS NOT NULL THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Recertification: CofO lookup failed', ['file' => $upper, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** The land file's newest live property record (pra), preferring one with real registration particulars. */
    private function praFor(string $upper): ?object
    {
        try {
            return DB::connection('sqlsrv')->table('pra')
                ->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = ?', [$upper])
                ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->orderByRaw("CASE WHEN regNo IS NOT NULL AND regNo NOT IN ('', '0', '0/0/0') THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Recertification: pra lookup failed', ['file' => $upper, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Newest live row of $table whose $column is the file number. */
    private function firstRow(string $table, string $column, string $upper, ?string $deletedFlag = null, ?string $deletedAt = null): ?object
    {
        try {
            return DB::connection('sqlsrv')->table($table)
                ->whereRaw("UPPER(LTRIM(RTRIM([{$column}]))) = ?", [$upper])
                ->when($deletedFlag, fn ($q) => $q->where(fn ($w) => $w->whereNull($deletedFlag)->orWhere($deletedFlag, 0)))
                ->when($deletedAt, fn ($q) => $q->whereNull($deletedAt))
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning("Recertification: {$table} lookup failed", ['file' => $upper, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * The applicant's address as captured on the OSS application, from the first
     * structured address block that has anything in it (residential, then the
     * correspondence blocks of each application type).
     *
     * @return array{line1:?string, city:?string, state:?string}|null
     */
    private function ossApplicantAddress(?object $oss): ?array
    {
        if (!$oss) {
            return null;
        }
        foreach (['res_addr', 'res_corr', 'com_corr', 'ind_corr', 'agr_corr'] as $p) {
            $part = fn ($name) => trim((string) ($oss->{"{$p}_{$name}"} ?? ''));
            $street = strtolower($part('street')) === 'other' ? $part('street_other') : $part('street');
            $district = strtolower($part('district')) === 'other' ? $part('district_other') : $part('district');
            $house = $part('house');
            $plot = $part('plot');

            $line1 = implode(', ', array_filter([
                $house !== '' ? "No. {$house}" : null,
                $plot !== '' ? "Plot {$plot}" : null,
                $street !== '' ? $street : null,
            ]));
            if ($line1 === '' && $district === '') {
                continue;
            }

            return [
                'line1' => $line1 !== '' ? $line1 : null,
                'city' => $district !== '' ? $district : ($part('lga') ?: null),
                'state' => $part('state') ?: null,
            ];
        }

        return null;
    }

    // ---- KN numbering -------------------------------------------------------

    /** The number the next new KANGIS file will get. A preview only: allocate() assigns it. */
    public function nextKangisNumber(): string
    {
        return 'KN' . $this->nextKangisSerial();
    }

    /**
     * Assign the next KN number. Call inside an sqlsrv transaction: the app lock
     * holds until it commits, so two clerks saving together get different numbers.
     */
    public function allocateKangisNumber(): string
    {
        DB::connection('sqlsrv')->statement(
            "DECLARE @r int; EXEC @r = sp_getapplock @Resource = 'recertification_kn', @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 15000; IF @r < 0 THROW 50001, 'Could not lock KN numbering', 1;"
        );

        return $this->nextKangisNumber();
    }

    private function nextKangisSerial(): int
    {
        $start = (int) config('recertification.kn_start', 9000);
        $db = DB::connection('sqlsrv');

        // Highest KN<digits> already issued by recertification (suffixes such as _2 ignored).
        $max = (int) $db->table('recertification_applications')
            ->where('NewKANGISFileno', 'like', 'KN[0-9]%')
            ->selectRaw("MAX(TRY_CAST(SUBSTRING(NewKANGISFileno, 3, CASE WHEN CHARINDEX('_', NewKANGISFileno) > 0 THEN CHARINDEX('_', NewKANGISFileno) - 3 ELSE 20 END) AS int)) AS n")
            ->value('n');

        $serial = max($start, $max + 1);

        // Never reuse a KN number that exists anywhere else in the system.
        while ($this->kangisNumberTaken('KN' . $serial)) {
            $serial++;
        }

        return $serial;
    }

    private function kangisNumberTaken(string $kn): bool
    {
        $db = DB::connection('sqlsrv');
        $like = $kn . '[_]%';

        $match = fn ($column) => fn ($q) => $q->where($column, $kn)->orWhere($column, 'like', $like);

        return $db->table('recertification_applications')->where($match('NewKANGISFileno'))->exists()
            || $db->table('fileNumber')->where(fn ($q) => $q->where($match('kangisFileNo'))->orWhere($match('NewKANGISFileNo')))->exists()
            || $db->table('file_indexings')->where($match('file_number'))->exists();
    }

    // ---- Commissioning record -----------------------------------------------

    /**
     * Mark the land file's commissioning row(s) as called up into KANGIS. Skipped
     * quietly until the migration has added the columns.
     */
    public function markCalledUp(string $landFile, string $kangisFileNo, int $applicationId): int
    {
        if (!$this->hasColumns('mls_file_no', ['kangis_file_no', 'kangis_called_up_at', 'recertification_application_id'])) {
            return 0;
        }

        return DB::connection('sqlsrv')->table('mls_file_no')
            ->whereRaw('UPPER(LTRIM(RTRIM(full_file_number))) = ?', [strtoupper(trim($landFile))])
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->update([
                'kangis_file_no' => $kangisFileNo,
                'kangis_called_up_at' => now(),
                'recertification_application_id' => $applicationId,
                'updated_at' => now(),
            ]);
    }

    private function hasColumns(string $table, array $columns): bool
    {
        $key = $table . ':' . implode(',', $columns);
        if (!array_key_exists($key, $this->columnCache)) {
            $schema = Schema::connection('sqlsrv');
            $this->columnCache[$key] = collect($columns)->every(fn ($c) => $schema->hasColumn($table, $c));
        }

        return $this->columnCache[$key];
    }

    // ---- Value clean-up -----------------------------------------------------

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $v) {
            $v = is_string($v) ? trim($v) : $v;
            if ($v !== null && $v !== '' && strtoupper((string) $v) !== 'N/A') {
                return (string) $v;
            }
        }

        return null;
    }

    private function upperOrNull(?string $v): ?string
    {
        return $v === null ? null : strtoupper($v);
    }

    /** Words that make a file title an organisation rather than a person. */
    private const ORGANISATION_WORDS = [
        'LTD', 'LIMITED', 'PLC', 'NIG', 'NIGERIA', 'COMPANY', 'CO', 'ENTERPRISE', 'ENTERPRISES', 'ENTERPRISRS',
        'VENTURES', 'SERVICES', 'SOCIETY', 'PROJECT', 'TRUSTEES', 'TRUSTEE', 'STATE', 'GOVERNMENT', 'MINISTRY',
        'JUDICIARY', 'BANK', 'ASSOCIATION', 'COOPERATIVE', 'CO-OPERATIVE', 'INDUSTRIES', 'INTERNATIONAL',
        'HOLDINGS', 'CONSTRUCTION', 'SCHOOL', 'COLLEGE', 'UNIVERSITY', 'MOSQUE', 'CHURCH', 'HOSPITAL', 'CLINIC',
        'AUTHORITY', 'COMMISSION', 'COUNCIL', 'AGENCY', 'BOARD', 'CORPORATION', 'FOUNDATION', 'INTEGRATED',
        'INVESTMENT', 'INVESTMENTS', 'GLOBAL', 'GROUP', 'PARTNERS', 'ESTATE', 'ESTATES', 'FEDERAL', 'LOCAL',
        'UNION', 'CENTRE', 'CENTER', 'INSTITUTE', 'PROPERTIES', 'MOTORS', 'PHARMACY', 'HOTEL', 'HOTELS',
        'GENERAL', 'VENTURE', 'RESOURCES', 'FARMS', 'FARM', 'COMMUNITY', 'DEVELOPMENT', 'MULTI', 'PETRO', 'BIZ',
        'FED', 'MIN', 'DEPT', 'DEPARTMENT', 'DIVISION', 'ISLAMIYYA', 'MADRASA', 'LAND',
        'ACADEMY', 'NURSERY', 'PRIMARY', 'SECONDARY', 'MARKET', 'PETROLEUM', 'OIL', 'GAS', 'TRADING', 'AGRO',
        'LAYOUT', 'SITE', 'PROPOSED', 'CEMETARY', 'CEMETERY', 'INDUSTRY', 'INDUSTRIAL', 'HOMES', 'CLUB',
    ];

    /** Organisation words that make it a Government Body rather than a company. */
    private const GOVERNMENT_WORDS = [
        'STATE', 'GOVERNMENT', 'MINISTRY', 'MIN', 'JUDICIARY', 'FEDERAL', 'FED', 'LOCAL', 'AUTHORITY',
        'COMMISSION', 'COUNCIL', 'AGENCY', 'BOARD', 'DEPT', 'DEPARTMENT', 'DIVISION',
    ];

    /**
     * Leading honorifics in file titles and the Title option they fill. A rank
     * with no listed option keeps its own spelling (the form adds it as a choice).
     */
    private const NAME_TITLES = [
        'ALHAJI' => 'Alhaji', 'ALH' => 'Alhaji', 'ALHAJ' => 'Alhaji', 'AL-HAJI' => 'Alhaji',
        'HAJIYA' => 'Hajiya', 'HAJ' => 'Hajiya', 'HAJIA' => 'Hajiya', 'ALHAJA' => 'Alhaja',
        'MALLAM' => 'Mallam', 'MALAM' => 'Mallam', 'MAL' => 'Mallam', 'MALAMA' => 'Malama',
        'MR' => 'Mr.', 'MRS' => 'Mrs.', 'MISS' => 'Miss', 'MASTER' => 'Master', 'DR' => 'Dr.', 'PROF' => 'Prof',
        'ENGR' => 'Engr.', 'ENG' => 'Engr.', 'ARC' => 'Arc.', 'ARCH' => 'Arc.', 'BARR' => 'Barr.',
        'CHIEF' => 'Chief', 'HON' => 'Honorable', 'HONOURABLE' => 'Honorable', 'HONORABLE' => 'Honorable',
        'SEN' => 'Senator', 'SENATOR' => 'Senator', 'REV' => 'Rev.', 'PASTOR' => 'Pastor', 'BISHOP' => 'Bishop',
        'SHEIKH' => 'Sheikh', 'SHEIK' => 'Sheikh', 'LADY' => 'Lady', 'SISTER' => 'Sister', 'KING' => 'King',
        'CAPT' => 'Capt', 'COL' => 'Coln', 'COLN' => 'Coln', 'MESSRS' => 'Messr', 'MESSR' => 'Messr',
        'GEN' => 'Gen.', 'MAJ' => 'Maj.', 'LT' => 'Lt.', 'SGT' => 'Sgt.', 'CPL' => 'Cpl.', 'AVM' => 'AVM',
        'ACP' => 'ACP', 'CP' => 'CP', 'DSP' => 'DSP', 'ASP' => 'ASP', 'INSP' => 'Insp.', 'BRIG' => 'Brig.',
    ];

    /**
     * Split a file title ("ALH. SHARU ABBAS ADAMU") into title, first name, other
     * names and surname: Kano titles are written first name first, surname last.
     * Organisations and joint holders ("X & Y", "X / Y", "X, Y") are not split.
     * A short prefix before a slash ("K/KAROFI") is a place name and stays.
     *
     * @return array{title:?string, firstName:?string, middleName:?string, surname:?string}|array{organisation:string}|null
     */
    public function splitHolderName(?string $name): ?array
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if ($name === '') {
            return null;
        }

        // "C/O <someone>" is a care-of address; brackets (RTD, a trading name) are not the name.
        $clean = preg_replace('/\s+C\/O\s.*$/i', '', $name);
        $clean = trim(preg_replace('/\s+/', ' ', preg_replace('/[\[(][^\])]*[\])]|\bRTD\.?$/i', ' ', $clean)));
        // A title run into the name ("MRS.RAMATU", "ALH.SUNUSI").
        $clean = preg_replace_callback('/^([A-Za-z]{2,7})\.(?=[A-Za-z])/', fn ($m) =>
            isset(self::NAME_TITLES[strtoupper($m[1])]) ? $m[1] . '. ' : $m[0], $clean);
        $words = array_values(array_filter(explode(' ', strtoupper($clean)), 'strlen'));
        $bare = array_map(fn ($w) => trim($w, '.,;:'), $words);

        if (!$words) {
            return null;
        }
        if (array_intersect($bare, self::ORGANISATION_WORDS)) {
            return [
                'organisation' => $name,
                'government' => (bool) array_intersect($bare, self::GOVERNMENT_WORDS),
            ];
        }
        // Several people, or a description rather than a name: left for the officer.
        $joint = preg_match('/[&,@]/', $clean)
            || preg_match('/\s\/|\/\s/', $clean)
            || (bool) array_filter($bare, fn ($w) => preg_match('/^[A-Z]{3,}\/[A-Z]/', $w));
        if ($joint || preg_match('/\d/', $clean) || preg_grep('/^(AND|OTHERS|THE|OF|FOR)$/', $bare)) {
            return ['joint' => $name];
        }

        $title = null;
        while ($words && isset(self::NAME_TITLES[$bare[0]])) {
            $title = $title ?? self::NAME_TITLES[$bare[0]];
            array_shift($words);
            array_shift($bare);
        }
        $words = array_map(fn ($w) => rtrim($w, ',;:'), $words);
        if (!$words) {
            return null;
        }
        // One name: with a title ("ALH SANI") it is the given name; alone it may be anything.
        if (count($words) === 1) {
            return $title ? ['title' => $title, 'firstName' => $words[0], 'middleName' => null, 'surname' => null] : null;
        }

        $surname = array_pop($words);
        $first = array_shift($words);

        return [
            'title' => $title,
            'firstName' => $first,
            'middleName' => $words ? implode(' ', $words) : null,
            'surname' => $surname,
        ];
    }

    private function lowerOrNull(?string $v): ?string
    {
        return $v === null ? null : strtolower($v);
    }

    /**
     * "lat, lng" from the newest index row with a usable pair. Values outside
     * Nigeria are typing errors (a handful point at Asia) and are skipped.
     */
    private function coordinates($indexRows): ?string
    {
        foreach ($indexRows as $row) {
            $lat = trim((string) ($row->latitude ?? ''));
            $lng = trim((string) ($row->longitude ?? ''));
            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }
            if ((float) $lat < 4 || (float) $lat > 14 || (float) $lng < 2 || (float) $lng > 15) {
                continue;
            }

            return $lat . ', ' . $lng;
        }

        return null;
    }

    /**
     * District and LGA read from a "district, LGA, state" location string. Parts
     * with digits (legacy rows sometimes lead with the plot) and the state are
     * dropped; a lone remaining part is taken as the LGA only.
     *
     * @return array{district:?string, lga:?string}
     */
    private function locationParts(?string $location): array
    {
        $parts = collect(explode(',', (string) $location))
            ->map(fn ($p) => trim($p))
            ->filter(fn ($p) => $p !== '' && !preg_match('/\d/', $p))
            ->values();

        // Drop a trailing state ("Kano", "Kano State") when something else remains.
        if ($parts->count() > 1 && preg_match('/^kano(\s+state)?$/i', $parts->last())) {
            $parts->pop();
        }

        return match (true) {
            $parts->count() >= 2 => ['district' => $parts[0], 'lga' => $parts[1]],
            $parts->count() === 1 => ['district' => null, 'lga' => $parts[0]],
            default => ['district' => null, 'lga' => null],
        };
    }

    /** Registration particulars of 0 or 0/0/0 are blanks, not values. */
    private function registrationValue($v): ?string
    {
        $v = trim((string) $v);

        return ($v === '' || preg_match('#^[0/\s]+$#', $v)) ? null : $v;
    }

    /** Placeholder phones (08000000000, SYS-########) are not real contact details. */
    private function realPhone(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $v);
        if ($digits === '' || stripos($v, 'SYS-') === 0 || preg_match('/^0?80{8,}$/', $digits)) {
            return null;
        }

        return $v;
    }

    private function realEmail(?string $v): ?string
    {
        return ($v && filter_var($v, FILTER_VALIDATE_EMAIL) && !str_ends_with(strtolower($v), '@portal.klaes.local')) ? $v : null;
    }

    private function gender(?string $v): ?string
    {
        $v = strtolower(trim((string) $v));

        return match (true) {
            in_array($v, ['m', 'male'], true) => 'male',
            in_array($v, ['f', 'female'], true) => 'female',
            default => null,
        };
    }

    private function landUse(?string $v): ?string
    {
        $v = strtolower(trim((string) $v));
        if ($v === '') {
            return null;
        }
        // The form offers the four land use types. Records use full names, short
        // codes, conversion (CON-) codes and common misspellings.
        $v = preg_replace('/^con-|-rc$/', '', $v);
        $map = [
            'res' => 'residential', 'residential' => 'residential', 'residencial' => 'residential', 'resdential' => 'residential',
            'com' => 'commercial', 'commercial' => 'commercial',
            'ind' => 'industrial', 'industrial' => 'industrial',
            'ag' => 'agricultural', 'agr' => 'agricultural', 'agric' => 'agricultural', 'agricultural' => 'agricultural',
        ];

        return $map[$v] ?? null;
    }

    /** Only a plain number is taken as hectares; anything else is left for the clerk. */
    private function hectares(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $clean = preg_replace('/\s*(ha|hectares?)\s*$/i', '', trim($v));

        return is_numeric($clean) && (float) $clean > 0 ? $clean : null;
    }

    private function dateOrNull($v): ?string
    {
        if (!$v) {
            return null;
        }
        try {
            $d = \Illuminate\Support\Carbon::parse($v);

            return $d->year > 1900 ? $d->toDateString() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
