<?php

namespace Tests\Feature\FileTracking;

use App\Http\Controllers\CreateFileTrackerController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * The File Log Table's Active view is split into three request-type tabs: In-transit,
 * DIIT (commissioned files never logged out) and Submitted Request. They must not
 * overlap and must add up to Active, and each tab's list must match its badge.
 */
class DiitRequestTypeTabTest extends TestCase
{
    private function listFor(string $type): array
    {
        $request = Request::create('/create-file-tracker/list', 'GET', [
            'status'            => 'not-completed',
            'per_page'          => 5,
            'file_request_type' => $type,
        ]);

        return app(CreateFileTrackerController::class)->list($request)->getData(true);
    }

    public function test_request_type_tabs_partition_the_active_files(): void
    {
        Auth::setUser(User::query()->orderBy('id')->firstOrFail());

        $all = $this->listFor('');
        $this->assertTrue($all['success']);
        $stats = $all['stats'];

        $this->assertSame(
            $stats['active'],
            $stats['in_transit'] + $stats['diit'] + $stats['submitted'],
            'In-transit + DIIT + Submitted Request must add up to Active.'
        );

        $inTransit = $this->listFor('MANUAL');
        $this->assertEquals($stats['in_transit'], $inTransit['pagination']['total_items']);
        $this->assertEmpty(array_filter($inTransit['data'], fn ($d) => !empty($d['is_diit'])));

        $diit = $this->listFor('DIIT');
        $this->assertEquals($stats['diit'], $diit['pagination']['total_items']);
        foreach ($diit['data'] as $row) {
            $this->assertTrue((bool) $row['is_diit'], 'The DIIT tab lists only DIIT cards.');
        }
    }
}
