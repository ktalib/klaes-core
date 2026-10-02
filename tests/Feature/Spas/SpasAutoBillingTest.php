<?php

namespace Tests\Feature\Spas;

use App\Models\SpaApplication;
use App\Models\SpaBill;
use App\Models\SpaBillItem;
use App\Models\SpaBillLine;
use App\Services\SpaBillingService;
use App\Services\SpaMobileService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bills are raised by the finding, not by an officer remembering to.
 *
 * The old flow had someone open "Add Bill" and type an amount, which put the
 * tariff in people's heads and meant an unwatched contravention was never
 * billed. These tests pin the replacement: the tariff is data, and a record
 * whose approved use differs from the use on the ground is billed on save.
 */
class SpasAutoBillingTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['sqlsrv'];

    private function tariff(array $rows): void
    {
        // Start from a known tariff. Transactions roll this back.
        SpaBillItem::query()->update(['is_active' => 0]);

        foreach ($rows as $i => [$name, $amount, $active]) {
            SpaBillItem::updateOrCreate(
                ['name' => $name],
                ['amount' => $amount, 'is_active' => $active, 'sort_order' => $i + 1]
            );
        }
    }

    private function service(): SpaMobileService
    {
        return new SpaMobileService(new SpaBillingService());
    }

    private function payload(string $approved, string $prevailing): array
    {
        return [
            'land_title_type' => 'statutory',
            'file_number'     => 'ZZ-BILL-'.Str::random(8),
            'owner_name'      => 'ZZ TEST Owner',
            'proposed_use'    => $approved,
            'existing_use'    => $prevailing,
        ];
    }

    // -----------------------------------------------------------------------

    public function test_a_contravening_record_is_billed_on_save(): void
    {
        $this->tariff([
            ['ZZ Penalty Fee', 50000, 1],
            ['ZZ Change of Use Fee', 25000, 1],
        ]);

        $app = $this->service()->createLandRecord($this->payload('RESIDENTIAL', 'COMMERCIAL'));

        $bill = SpaBill::where('spa_application_id', $app->id)->first();

        $this->assertNotNull($bill, 'A contravention must raise a bill without anyone asking.');
        $this->assertSame('contravention', $bill->source);
        $this->assertEquals(75000, (float) $bill->amount);
        $this->assertSame('unpaid', $bill->status);
        // SpaBill::nextReference() is the single source of the numbering, so
        // an auto-raised bill is numbered exactly like a hand-entered one.
        $this->assertMatchesRegularExpression('/^SPAS-BILL-\d{4}-\d{3}$/', $bill->reference_id);
    }

    public function test_the_bill_records_the_lines_it_was_composed_of(): void
    {
        // Without stored lines a bill stops being explainable the moment
        // somebody edits the tariff — the total would match nothing.
        $this->tariff([['ZZ Penalty Fee', 50000, 1], ['ZZ Survey Fee', 10000, 1]]);

        $app = $this->service()->createLandRecord($this->payload('RESIDENTIAL', 'INDUSTRIAL'));
        $bill = SpaBill::where('spa_application_id', $app->id)->firstOrFail();

        $lines = SpaBillLine::where('spa_bill_id', $bill->id)->get();

        $this->assertCount(2, $lines);
        $this->assertEqualsCanonicalizing(
            ['ZZ Penalty Fee', 'ZZ Survey Fee'],
            $lines->pluck('name')->all()
        );
        $this->assertEquals((float) $bill->amount, (float) $lines->sum('amount'));
    }

    public function test_editing_the_tariff_does_not_change_a_bill_already_raised(): void
    {
        $this->tariff([['ZZ Penalty Fee', 50000, 1]]);

        $app = $this->service()->createLandRecord($this->payload('RESIDENTIAL', 'COMMERCIAL'));
        $bill = SpaBill::where('spa_application_id', $app->id)->firstOrFail();

        SpaBillItem::where('name', 'ZZ Penalty Fee')->update(['amount' => 999999]);

        $this->assertEquals(50000, (float) $bill->fresh()->amount);
        $this->assertEquals(
            50000,
            (float) SpaBillLine::where('spa_bill_id', $bill->id)->sum('amount'),
            'Historical lines must keep the amount charged at the time.'
        );
    }

    public function test_a_record_that_does_not_contravene_is_not_billed(): void
    {
        $this->tariff([['ZZ Penalty Fee', 50000, 1]]);

        $app = $this->service()->createLandRecord($this->payload('RESIDENTIAL', 'RESIDENTIAL'));

        $this->assertSame(0, SpaBill::where('spa_application_id', $app->id)->count());
    }

    /**
     * A record is saved and re-saved constantly — corrected in the office,
     * edited on a handset, pushed by sync. Each re-checks the contravention, so
     * without a guard the owner collects a new bill every time.
     */
    public function test_saving_repeatedly_raises_only_one_bill(): void
    {
        $this->tariff([['ZZ Penalty Fee', 50000, 1]]);

        $service = $this->service();
        $app = $service->createLandRecord($this->payload('RESIDENTIAL', 'COMMERCIAL'));

        $service->applyLandRecordUpdate($app, ['owner_name' => 'ZZ TEST Renamed', 'proposed_use' => 'RESIDENTIAL', 'existing_use' => 'COMMERCIAL']);
        $service->applyLandRecordUpdate($app, ['phone' => '08000000000', 'proposed_use' => 'RESIDENTIAL', 'existing_use' => 'COMMERCIAL']);

        $this->assertSame(1, SpaBill::where('spa_application_id', $app->id)->count());
    }

    /**
     * The prevailing use is often corrected after the first visit, so an edit
     * can create a contravention that did not exist when the record was made.
     */
    public function test_an_edit_that_creates_a_contravention_raises_the_bill(): void
    {
        $this->tariff([['ZZ Penalty Fee', 50000, 1]]);

        $service = $this->service();
        $app = $service->createLandRecord($this->payload('RESIDENTIAL', 'RESIDENTIAL'));

        $this->assertSame(0, SpaBill::where('spa_application_id', $app->id)->count());

        $service->applyLandRecordUpdate($app, [
            'owner_name'   => 'ZZ TEST Owner',
            'proposed_use' => 'RESIDENTIAL',
            'existing_use' => 'COMMERCIAL',
        ]);

        $this->assertSame(1, SpaBill::where('spa_application_id', $app->id)->count());
    }

    public function test_inactive_and_zero_amount_items_are_not_charged(): void
    {
        // The tariff seeds at zero so nothing is billed before an officer sets
        // real amounts — a zero line would be noise on the bill.
        $this->tariff([
            ['ZZ Penalty Fee', 50000, 1],
            ['ZZ Switched Off', 30000, 0],
            ['ZZ Not Priced', 0, 1],
        ]);

        $app = $this->service()->createLandRecord($this->payload('RESIDENTIAL', 'COMMERCIAL'));
        $bill = SpaBill::where('spa_application_id', $app->id)->firstOrFail();

        $this->assertEquals(50000, (float) $bill->amount);
        $this->assertSame(['ZZ Penalty Fee'], SpaBillLine::where('spa_bill_id', $bill->id)->pluck('name')->all());
    }

    public function test_no_tariff_means_no_bill_rather_than_an_error(): void
    {
        SpaBillItem::query()->update(['is_active' => 0]);

        $app = $this->service()->createLandRecord($this->payload('RESIDENTIAL', 'COMMERCIAL'));

        // The record must still save. Billing is a consequence of the record,
        // never a precondition for it.
        $this->assertInstanceOf(SpaApplication::class, $app);
        $this->assertNotNull($app->id);
        $this->assertSame(0, SpaBill::where('spa_application_id', $app->id)->count());
    }

    public function test_contravention_comparison_ignores_case_and_padding(): void
    {
        $service = new SpaBillingService();

        $app = new SpaApplication(['proposed_use' => ' residential ', 'existing_use' => 'RESIDENTIAL']);
        $this->assertFalse($service->contravenes($app));

        $app = new SpaApplication(['proposed_use' => 'Residential', 'existing_use' => 'Commercial']);
        $this->assertTrue($service->contravenes($app));

        // A blank on either side is unknown, not a contravention — billing
        // someone because a field was never filled in would be indefensible.
        $app = new SpaApplication(['proposed_use' => 'Residential', 'existing_use' => '']);
        $this->assertFalse($service->contravenes($app));
    }
}
