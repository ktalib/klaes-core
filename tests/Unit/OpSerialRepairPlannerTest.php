<?php

namespace Tests\Unit;

use App\Services\OpSerialRepairPlanner;
use PHPUnit\Framework\TestCase;

class OpSerialRepairPlannerTest extends TestCase
{
    private function mother(int $id, $serial = '109', string $file = 'RES-2023-6336'): array
    {
        return ['id' => $id, 'instrument_type' => 'Occupancy Permit (OP)', 'op_serial_number' => $serial,
            'mlsFNo' => $file, 'serialNo' => '7', 'pageNo' => '7', 'volumeNo' => '3', 'party_2' => 'Holder', 'prop_id' => '123'];
    }

    public function test_unique_exact_file_can_fill_oss_blank_and_zero_but_never_replace_a_valid_serial(): void
    {
        $planner = new OpSerialRepairPlanner(['pra' => [$this->mother(1)]]);
        $target = ['id' => 9, 'file_no' => 'RES-2023-6336'];
        $this->assertSame('safe', $planner->plan('oss_applications', $target)['status']);
        $this->assertSame('safe', $planner->plan('oss_applications', $target + ['op_serial_number' => '0'])['status']);
        $this->assertSame('conflict', $planner->plan('oss_applications', $target + ['op_serial_number' => '110'])['status']);
        $this->assertSame('consistent', $planner->plan('oss_applications', $target + ['op_serial_number' => '109'])['status']);
    }

    public function test_duplicate_ops_are_ambiguous_even_if_the_serials_agree(): void
    {
        $planner = new OpSerialRepairPlanner(['pra' => [$this->mother(1), $this->mother(2)]]);
        $this->assertSame('ambiguous', $planner->plan('oss_applications', ['id' => 9, 'file_no' => 'RES-2023-6336'])['status']);
    }

    public function test_another_year_or_reused_prop_id_cannot_supply_a_serial(): void
    {
        $planner = new OpSerialRepairPlanner(['pra' => [$this->mother(1, '109', 'RES-2024-6336')]]);
        $this->assertSame('no_source', $planner->plan('oss_applications', ['id' => 9, 'file_no' => 'RES-2023-6336', 'prop_id' => '123'])['status']);
    }

    public function test_exact_lineage_can_disambiguate_several_ops(): void
    {
        $planner = new OpSerialRepairPlanner(['pra' => [$this->mother(1), $this->mother(2, '110')]]);
        $result = $planner->plan('pra', ['id' => 9, 'instrument_type' => 'Transfer of Title (OP)', 'source_op_table' => 'pra', 'source_op_id' => 2]);
        $this->assertSame('safe', $result['status']);
        $this->assertSame('110', $result['proposed']);
    }

    public function test_broken_or_deleted_source_links_do_not_fall_back_to_another_op(): void
    {
        $planner = new OpSerialRepairPlanner(['pra' => [$this->mother(1), $this->mother(2) + ['is_deleted' => 1]]]);
        foreach ([2, 99] as $id) {
            $target = ['id' => 9, 'mlsFNo' => 'RES-2023-6336', 'source_op_table' => 'pra', 'source_op_id' => $id];
            $this->assertSame('broken_source_link', $planner->plan('pra', $target)['status']);
        }
    }

    public function test_mother_repair_requires_complete_corresponding_registration_and_holder(): void
    {
        $source = ['id' => 2, 'instrument_type' => 'Occupancy Permit (OP)', 'mlsFNo' => 'RES-2023-6336',
            'serial_no' => '7', 'page_no' => '7', 'volume_no' => '3', 'party_2_name' => 'Holder', 'op_serial_number' => '109'];
        $planner = new OpSerialRepairPlanner(['instrument_capture' => [$source]]);
        $this->assertSame('safe', $planner->plan('pra', $this->mother(1, null))['status']);
        $target = $this->mother(1, null);
        $target['pageNo'] = null;
        $this->assertSame('no_source', $planner->plan('pra', $target)['status']);
    }

    public function test_source_zeros_and_invalid_target_text_are_not_automatically_repaired(): void
    {
        $target = ['id' => 9, 'file_no' => 'RES-2023-6336'];
        $planner = new OpSerialRepairPlanner(['pra' => [$this->mother(1, '0')]]);
        $this->assertSame('source_missing_or_invalid', $planner->plan('oss_applications', $target)['status']);
        $planner = new OpSerialRepairPlanner(['pra' => [$this->mother(1)]]);
        $this->assertSame('invalid_requires_review', $planner->plan('oss_applications', $target + ['op_serial_number' => 'N/A'])['status']);
    }

    public function test_commission_pins_the_correct_op_when_file_numbers_differ(): void
    {
        $planner = new OpSerialRepairPlanner(['pra' => [$this->mother(1, '109', 'TEMP-17')]],
            [['full_file_number' => 'RES-2023-6336', 'source_pra_id' => 1]]);
        $result = $planner->plan('oss_applications', ['id' => 9, 'file_no' => 'RES-2023-6336']);
        $this->assertSame('safe', $result['status']);
        $this->assertSame('commission_source_link', $result['basis']);
    }
}
