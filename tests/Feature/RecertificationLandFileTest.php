<?php

namespace Tests\Feature;

use App\Services\Recertification\RecertificationLandFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Read-only: sqlsrv is the guarded live connection in tests. */
class RecertificationLandFileTest extends TestCase
{
    public function test_unknown_land_file_resolves_to_null(): void
    {
        $this->assertNull(app(RecertificationLandFile::class)->resolve('NOPE-0000-0'));
        $this->assertNull(app(RecertificationLandFile::class)->resolve('   '));
    }

    public function test_next_kn_number_starts_at_configured_floor(): void
    {
        config(['recertification.kn_start' => 9000]);
        $kn = app(RecertificationLandFile::class)->nextKangisNumber();

        $this->assertMatchesRegularExpression('/^KN\d+$/', $kn);
        $this->assertGreaterThanOrEqual(9000, (int) substr($kn, 2));
    }

    public function test_file_title_splits_into_name_parts(): void
    {
        $svc = app(RecertificationLandFile::class);
        $person = fn ($t, $f, $m, $s) => ['title' => $t, 'firstName' => $f, 'middleName' => $m, 'surname' => $s];

        $this->assertSame($person('Alhaji', 'SHARU', 'ABBAS', 'ADAMU'), $svc->splitHolderName('ALH. SHARU ABBAS ADAMU'));
        $this->assertSame($person('Mrs.', 'RAMATU', 'LAWAL', 'YUSUF'), $svc->splitHolderName('Mrs.RAMATU LAWAL YUSUF'));
        $this->assertSame($person('Hajiya', 'ADAMA', null, 'MUHAMMAD'), $svc->splitHolderName('HAJIYA ADAMA MUHAMMAD'));
        $this->assertSame($person('Coln', 'A', null, 'AMINU'), $svc->splitHolderName('COL A AMINU [RTD]'));
        $this->assertSame($person(null, 'DATTI', 'UMAR', 'K/KAROFI'), $svc->splitHolderName('DATTI UMAR K/KAROFI'));
        $this->assertSame($person('Alhaji', 'HUSSAINI', null, 'ADAMU'), $svc->splitHolderName('ALHAJI HUSSAINI ADAMU C/O UMARU ABDULLAHI'));
        $this->assertSame($person('Alhaji', 'SANI', null, null), $svc->splitHolderName('ALH SANI'));

        foreach (['ALH. YAU SULEIMAN NIGERIA LTD', 'DALA BUILDING SOCIETY PLC', 'ABUBAKAR G MUSA & CO'] as $org) {
            $this->assertSame(['organisation' => $org, 'government' => false], $svc->splitHolderName($org), $org);
        }
        foreach (['KANO STATE JUDICIARY', 'MINISTRY OF ENVIRONMENT ECOLOGY AND FORESTRY DEPT.'] as $gov) {
            $this->assertSame(['organisation' => $gov, 'government' => true], $svc->splitHolderName($gov), $gov);
        }
        foreach (['MUSA ALI / MAIGARI DANDAGO', 'INUWA GAYA/YAKUBU SAID', 'HAFSATU HASSAN & HUSSAINA ABDU',
                  'FAMILY OF LATE ALH. AUDU BAKO'] as $joint) {
            $this->assertSame(['joint' => $joint], $svc->splitHolderName($joint), $joint);
        }
        $this->assertNull($svc->splitHolderName('MASCO'));
        $this->assertNull($svc->splitHolderName('  '));
    }

    public function test_commissioned_land_file_backfills_from_records(): void
    {
        $file = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('is_deleted', 0)->whereNotNull('plot_no')->where('plot_no', '<>', '')
            ->orderByDesc('id')->value('full_file_number');
        if (!$file) {
            $this->markTestSkipped('No commissioned land file with a plot number.');
        }

        $record = app(RecertificationLandFile::class)->resolve($file);

        $this->assertNotNull($record);
        $this->assertSame(strtoupper($file), strtoupper($record['land_file']));
        $this->assertArrayHasKey('plotNumber', $record['fields']);
        $this->assertContains($record['new_kangis']['source'], ['existing', 'new']);
        $this->assertArrayHasKey('file_no', $record['kangis']);
    }

    public function test_kn_series_is_new_kangis_and_others_are_kangis(): void
    {
        $this->assertTrue(RecertificationLandFile::isNewKangisNumber('KN4592'));
        $this->assertTrue(RecertificationLandFile::isNewKangisNumber('KN4592_6'));
        $this->assertFalse(RecertificationLandFile::isNewKangisNumber('KNGP 114'));
        $this->assertFalse(RecertificationLandFile::isNewKangisNumber('KNML 451'));
    }

    public function test_district_falls_back_to_location_text(): void
    {
        $file = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('is_deleted', 0)
            ->where(fn ($q) => $q->whereNull('district')->orWhere('district', ''))
            ->where('location', 'like', '%,%,%')
            ->where('location', 'not like', '%[0-9]%')
            ->orderByDesc('id')->value('full_file_number');
        if (!$file) {
            $this->markTestSkipped('No commissioned file with a district only in its location.');
        }

        $record = app(RecertificationLandFile::class)->resolve($file);

        $this->assertNotEmpty($record['fields']['district'] ?? null);
        $this->assertNotEmpty($record['fields']['lga'] ?? null);
    }

    public function test_gps_coordinates_backfill_from_file_indexing(): void
    {
        $file = DB::connection('sqlsrv')->table('file_indexings')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->whereRaw("TRY_CONVERT(float, latitude) BETWEEN 4 AND 14 AND TRY_CONVERT(float, longitude) BETWEEN 2 AND 15")
            // A land file (RES-/COM-...), not a KANGIS Registry index such as "KNGP 166".
            ->where('file_number', 'like', '%-%-%')
            ->orderByDesc('id')->value('file_number');
        if (!$file) {
            $this->markTestSkipped('No indexed file with coordinates.');
        }

        $coordinates = app(RecertificationLandFile::class)->resolve($file)['fields']['coordinates'] ?? null;

        $this->assertMatchesRegularExpression('/^-?[\d.]+, -?[\d.]+$/', (string) $coordinates);
    }
}
