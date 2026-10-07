<?php

namespace Tests\Unit;

use App\Support\OpMatchDetails;
use App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class OpMatchDetailsTest extends TestCase
{
    private function entries(): array
    {
        return ['transaction_date' => '2025-03-02', 'regNo' => '12/34/56',
            'deeds_time' => '09:30', 'deeds_date' => '2025-03-05', 'tp_no' => 'TP/123'];
    }

    private function row(int $id = 10): object
    {
        return (object) ['id' => $id, 'mlsFNo' => 'RES-2025-1816', 'fileno' => '', 'op_serial_number' => '72'];
    }

    private function check(array $rows, array $serials = [], array $details = [])
    {
        $method = new \ReflectionMethod(OpResettlementApplicationController::class, 'opSerialFixes');
        $method->setAccessible(true);
        return $method->invoke(app(OpResettlementApplicationController::class), collect($rows), $serials, $details);
    }

    public function test_valid_serial_does_not_bypass_missing_details(): void
    {
        $response = $this->check([$this->row()]);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertTrue($body['needs_op_details']);
        $this->assertCount(5, $body['ops'][0]['errors']);
    }

    public function test_completed_details_include_registration_components(): void
    {
        $fixes = $this->check([$this->row()], [], [10 => $this->entries()]);
        $this->assertSame('12', $fixes[10]['serialNo']);
        $this->assertSame('34', $fixes[10]['pageNo']);
        $this->assertSame('56', $fixes[10]['volumeNo']);
        $this->assertSame('TP/123', $fixes[10]['tp_no']);
        $this->assertArrayNotHasKey('op_serial_number', $fixes[10]);
    }

    public function test_existing_values_and_legacy_aliases_are_preserved(): void
    {
        $row = (object) ['transaction_date' => '2020-01-01', 'serialNo' => '1', 'pageNo' => '2',
            'volumeNo' => '3', 'regNo' => '0/0/0', 'reg_date' => '2020-01-05',
            'reg_time' => '00:00:00', 'deeds_date' => '', 'deeds_time' => '', 'tp_no' => 'OLD/TP'];
        $result = OpMatchDetails::collect($row, $this->entries());
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['updates']);
        $this->assertSame('1/2/3', $result['values']['regNo']);
        $this->assertSame('00:00:00', $result['values']['deeds_time']);
    }

    public function test_invalid_dates_time_particulars_and_blank_tp_are_rejected(): void
    {
        $bad = ['transaction_date' => '2025-02-30', 'deeds_date' => 'bad', 'deeds_time' => '25:71',
            'regNo' => '0/0/0', 'tp_no' => '  '];
        $result = OpMatchDetails::collect($this->row(), $bad);
        $this->assertCount(5, $result['errors']);
        $this->assertSame([], $result['updates']);
    }

    public function test_every_merger_op_must_be_complete_before_returning_any_updates(): void
    {
        $response = $this->check([$this->row(10), $this->row(11)], [], [10 => $this->entries()]);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(11, $response->getData(true)['ops'][0]['pra_id']);
        $fixes = $this->check([$this->row(10), $this->row(11)], [], [10 => $this->entries(), 11 => $this->entries()]);
        $this->assertCount(2, $fixes);
    }

    public function test_missing_serial_and_details_are_collected_together(): void
    {
        $row = $this->row();
        $row->op_serial_number = '';
        $response = $this->check([$row]);
        $this->assertCount(6, $response->getData(true)['ops'][0]['errors']);
        $fixes = $this->check([$row], [10 => '72'], [10 => $this->entries()]);
        $this->assertSame('72', $fixes[10]['op_serial_number']);
    }
}
