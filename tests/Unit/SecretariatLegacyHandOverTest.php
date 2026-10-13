<?php

namespace Tests\Unit;

use App\Services\FileTracking\SecretariatFileLogService;
use Tests\TestCase;

/**
 * File Movement treats a hand-over logged before it existed (and never received)
 * as held at the destination office. New hand-overs still wait for receipt.
 */
class SecretariatLegacyHandOverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['file_movement.legacy_pending_before' => '2026-10-13T04:37:00+01:00']);
    }

    public function test_old_pending_hand_over_is_legacy(): void
    {
        $this->assertTrue(SecretariatFileLogService::isLegacyHandOver([
            'status' => 'pending_acceptance', 'office_code' => 'PS', 'timestamp' => '2026-06-22T12:01:00.000000Z',
        ]));
    }

    public function test_entry_without_a_timestamp_is_legacy(): void
    {
        $this->assertTrue(SecretariatFileLogService::isLegacyHandOver(['status' => 'pending_acceptance']));
    }

    public function test_hand_over_after_the_cutoff_is_not_legacy(): void
    {
        $this->assertFalse(SecretariatFileLogService::isLegacyHandOver([
            'status' => 'pending_acceptance', 'timestamp' => '2026-10-13T03:38:00.000000Z',   // 04:38 WAT
        ]));
    }

    public function test_hand_over_sent_from_file_movement_is_not_legacy(): void
    {
        $this->assertFalse(SecretariatFileLogService::isLegacyHandOver([
            'status' => 'pending_acceptance', 'sent_from_office_code' => 'SPS', 'timestamp' => '2026-06-22T12:01:00Z',
        ]));
    }

    public function test_received_entries_are_not_legacy(): void
    {
        $this->assertFalse(SecretariatFileLogService::isLegacyHandOver(['status' => 'active', 'timestamp' => '2026-06-22T12:01:00Z']));
        $this->assertFalse(SecretariatFileLogService::isLegacyHandOver(null));
    }

    public function test_no_cutoff_turns_the_rule_off(): void
    {
        config(['file_movement.legacy_pending_before' => null]);
        $this->assertFalse(SecretariatFileLogService::isLegacyHandOver(['status' => 'pending_acceptance']));
    }
}
