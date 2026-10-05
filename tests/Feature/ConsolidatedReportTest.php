<?php

namespace Tests\Feature;

use App\Http\Controllers\ConsolidatedReportController;
use App\Services\ConsolidatedReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConsolidatedReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'sqlsrv', 'cache.default' => 'array']);
        DB::purge('sqlsrv');
    }

    private function table(string $name, array $columns): void
    {
        Schema::connection('sqlsrv')->create($name, function (Blueprint $table) use ($columns) {
            $table->increments('id');
            foreach ($columns as $column) $table->string($column)->nullable();
        });
    }

    private function report(string $name, array $filters = []): array
    {
        return app(ConsolidatedReportService::class)->generate($name, $filters)['data'];
    }

    public function test_bill_balance_includes_end_date_and_pending_bills_without_a_billing_row(): void
    {
        $this->table('billing', ['Payment_Status', 'bill_balance_reciept']);
        $this->table('deeds_bill_balances_metadata', ['billing_id', 'reference', 'file_number', 'applicant_name', 'location_station', 'district', 'amount', 'created_at']);
        DB::table('billing')->insert(['id' => 1, 'Payment_Status' => 'Paid']);
        foreach (['2026-10-03 23:59:59', '2026-10-04 00:00:00', '2026-10-03 12:00:00'] as $i => $date) {
            DB::table('deeds_bill_balances_metadata')->insert(['file_number' => 'LAND-' . $i, 'created_at' => $date, 'billing_id' => $i === 2 ? 1 : null]);
        }
        $rows = $this->report('bill-balance', ['start_date' => '2026-10-03', 'end_date' => '2026-10-03', 'status' => 'pending']);
        $this->assertCount(1, $rows);
        $this->assertSame('LAND-0', $rows[0]['file_number']);
        $this->assertArrayNotHasKey('status', $rows[0]);
    }

    private function applications(): void
    {
        $columns = ['fileno', 'np_fileno', 'applicant_type', 'applicant_title', 'first_name', 'middle_name', 'surname', 'corporate_name', 'multiple_owners_names',
            'is_deleted', 'sys_date', 'created_at', 'land_use', 'application_status', 'planning_recommendation_status',
            'main_application_id', 'property_state', 'property_street_name', 'property_district', 'property_lga', 'property_location', 'unit_district', 'unit_lga', 'unit_state', 'is_sua_unit'];
        $this->table('mother_applications', $columns);
        $this->table('subapplications', $columns);
    }

    public function test_applications_combine_primary_and_units_exclude_deleted_and_preserve_owner_names(): void
    {
        $this->applications();
        DB::table('mother_applications')->insert(['fileno' => 'P-1', 'corporate_name' => 'Example Ltd', 'created_at' => '2026-10-03 12:00:00']);
        DB::table('mother_applications')->insert(['fileno' => 'DELETED', 'is_deleted' => 1, 'created_at' => '2026-10-03 12:00:00']);
        DB::table('subapplications')->insert(['fileno' => 'S-1', 'is_sua_unit' => 1, 'multiple_owners_names' => '["Amina", "Bello"]', 'sys_date' => '2026-10-03 12:00:00']);
        $rows = $this->report('st-applications', ['start_date' => '2026-10-03', 'end_date' => '2026-10-03']);
        $this->assertCount(2, $rows);
        $this->assertSame(['EXAMPLE LTD', 'AMINA, BELLO'], array_column($rows, 'applicant'));
        $this->assertSame([1, 2], array_column($rows, 'sn'));
        $this->assertCount(1, $this->report('st-applications', ['status' => 'SUA']));
        $this->assertCount(0, $this->report('st-applications', ['status' => 'PUA']));
    }

    public function test_final_conveyance_uses_latest_record_and_excludes_deleted_applications(): void
    {
        $this->applications();
        $this->table('final_conveyance', ['application_id', 'generated_date', 'status']);
        DB::table('mother_applications')->insert([['id' => 1, 'fileno' => 'P-1', 'is_deleted' => 0], ['id' => 2, 'fileno' => 'P-2', 'is_deleted' => 1]]);
        DB::table('final_conveyance')->insert([
            ['application_id' => 1, 'generated_date' => '2026-09-01', 'status' => 'old'],
            ['application_id' => 1, 'generated_date' => '2026-10-03', 'status' => 'generated'],
            ['application_id' => 2, 'generated_date' => '2026-10-03', 'status' => 'generated'],
        ]);
        $rows = $this->report('st-fc');
        $this->assertCount(1, $rows);
        $this->assertSame('2026-10-03', $rows[0]['date']);
    }

    public function test_st_rofo_excludes_unissued_units_and_deleted_applications(): void
    {
        $this->applications();
        $this->table('rofo', ['sub_application_id', 'rofo_no', 'location', 'ground_rent', 'term_years', 'print_counter', 'created_at']);
        DB::table('subapplications')->insert([
            ['id' => 1, 'fileno' => 'U-1', 'is_deleted' => 0, 'main_application_id' => 10],
            ['id' => 2, 'fileno' => 'U-2', 'is_deleted' => 0, 'main_application_id' => null],
            ['id' => 3, 'fileno' => 'DELETED', 'is_deleted' => 1, 'main_application_id' => null],
        ]);
        DB::table('rofo')->insert([
            ['sub_application_id' => 1, 'rofo_no' => 'R-1', 'print_counter' => null],
            ['sub_application_id' => 3, 'rofo_no' => 'R-3', 'print_counter' => null],
        ]);
        DB::table('mother_applications')->insert(['id' => 10, 'property_district' => 'Tarauni UDB', 'property_lga' => 'Tarauni', 'property_state' => 'Kano']);
        $rows = $this->report('st-rofo', ['status' => 'unprinted']);
        $this->assertCount(1, $rows);
        $this->assertSame('R-1', $rows[0]['rofo_no']);
        $this->assertSame('TARAUNI UDB, TARAUNI, KANO', $rows[0]['location']);
        $this->assertArrayNotHasKey('ground_rent', $rows[0]);
        $this->assertArrayNotHasKey('status', $rows[0]);
    }

    public function test_sltr_report_uses_generation_date_and_excludes_pending_and_soft_deleted_rows(): void
    {
        $this->table('sltr_recommendations', ['sltr_number', 'applicant_name', 'location', 'land_use', 'ground_rent', 'ground_rent_unit', 'term', 'status', 'rofo_status', 'rofo_generated_at', 'rofo_print_count', 'deleted_at']);
        $base = ['sltr_number' => 'SLTR-1', 'status' => 'approved', 'rofo_status' => 'generated', 'rofo_generated_at' => '2026-10-03 23:59:59'];
        DB::table('sltr_recommendations')->insert($base);
        DB::table('sltr_recommendations')->insert(array_merge($base, ['rofo_status' => 'pending']));
        DB::table('sltr_recommendations')->insert(array_merge($base, ['deleted_at' => '2026-10-04']));
        $this->assertCount(1, $this->report('sltr-rofo', ['end_date' => '2026-10-03', 'status' => 'unprinted']));
    }

    public function test_invalid_date_range_is_rejected_before_querying(): void
    {
        $this->expectException(ValidationException::class);
        app(ConsolidatedReportController::class)->export(Request::create('/', 'GET', ['start_date' => '2026-10-04', 'end_date' => '2026-10-03']), 'valuation', app(ConsolidatedReportService::class));
    }

    public function test_valuation_search_and_print_status_are_combined(): void
    {
        $this->table('valuation_reports', ['file_number', 'full_name', 'property_type', 'address', 'inspection_date', 'value_figures', 'print_count', 'created_at']);
        DB::table('valuation_reports')->insert([
            ['file_number' => 'V-1', 'full_name' => 'Amina', 'print_count' => 1],
            ['file_number' => 'V-2', 'full_name' => 'Amina', 'print_count' => 0],
            ['file_number' => 'V-3', 'full_name' => 'Bello', 'print_count' => 1],
        ]);
        $rows = $this->report('valuation', ['search' => 'Amina', 'status' => 'printed']);
        $this->assertSame(['V-1'], array_column($rows, 'file_number'));
    }

    public function test_commissioning_excludes_reserved_numbers_and_filters_file_type(): void
    {
        $this->table('st_file_numbers', ['fileno', 'np_fileno', 'mls_fileno', 'file_no_type', 'applicant_type', 'first_name', 'surname', 'corporate_name', 'multiple_owners_names',
            'land_use', 'property_address', 'main_application_id', 'property_state', 'property_street_name', 'property_district', 'property_lga', 'status', 'date_commissioned']);
        DB::table('st_file_numbers')->insert([
            ['fileno' => 'RESERVED', 'file_no_type' => 'SUA', 'date_commissioned' => null],
            ['fileno' => 'COMMISSIONED', 'file_no_type' => 'SUA', 'date_commissioned' => '2026-10-03 12:00:00'],
            ['fileno' => 'PRIMARY', 'file_no_type' => 'PRIMARY', 'date_commissioned' => '2026-10-03 12:00:00'],
        ]);
        $this->assertSame(['COMMISSIONED'], array_column($this->report('st-commissioning', ['status' => 'SUA']), 'file_number'));
    }

    public function test_consent_keeps_additional_files_and_parties_in_one_application_row(): void
    {
        $this->table('consent_applications', ['application_tracking_no', 'file_number', 'consent_type', 'applicant_name', 'party_name', 'additional_properties', 'additional_applicants',
            'additional_parties', 'consideration', 'bill_total', 'status', 'created_at']);
        DB::table('consent_applications')->insert(['file_number' => 'C-1', 'consent_type' => 'Assignment', 'applicant_name' => 'Amina', 'party_name' => 'Bello',
            'additional_properties' => '[{"file_number":"C-2"}]', 'additional_parties' => '[{"name":"Hassan"}]']);
        $rows = $this->report('consent', ['status' => 'Assignment']);
        $this->assertCount(1, $rows);
        $this->assertSame('C-1, C-2', $rows[0]['file_number']);
        $this->assertSame('BELLO, HASSAN', $rows[0]['party']);
        $this->assertCount(0, $this->report('consent', ['status' => 'Gift']));
    }

    public function test_report_route_requires_authentication(): void
    {
        $route = app('router')->getRoutes()->getByName('consolidated-reports.export');
        $this->assertContains('auth', $route->gatherMiddleware());
    }
}
