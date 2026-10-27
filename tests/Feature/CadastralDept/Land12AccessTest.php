<?php

namespace Tests\Feature\CadastralDept;

use App\Models\User;
use App\Services\CadastralDept\DeskAccess;
use App\Support\Permissions\ModulePermissions;
use Tests\TestCase;

/**
 * Other Departments > Land > Cadastral to Land (Land 12) is the Cadastral Report desk's (2026-10-26).
 * Checked on the desk rule the page uses; users are unsaved, nothing is written.
 */
class Land12AccessTest extends TestCase
{
    private function user(string $roles, int $id): User
    {
        $u = new User();
        $u->id = $id;
        $u->assign_role = $roles;
        $u->type = 'user';
        ModulePermissions::forget($id);

        return $u;
    }

    /** Deleting a Land 12 request is Super Admin only (2026-10-26); refused before anything is read or deleted. */
    public function test_land_12_delete_refused_to_non_admin(): void
    {
        $this->actingAs($this->user('Cad - Report, Cad - Records', -9105));

        $response = app(\App\Http\Controllers\SurveyReportController::class)->destroy(999999999);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_only_the_cadastral_report_desk_opens_land_12(): void
    {
        $access = app(DeskAccess::class);

        $this->assertTrue($access->canWorkDesk($this->user('Cad - Report', -9101), 'ad_report'));
        $this->assertTrue($access->canWorkDesk($this->user('Cad - Records', -9102), 'ad_report'));
        $this->assertFalse($access->canWorkDesk($this->user('Cad - Registry', -9103), 'ad_report'));
        $this->assertFalse($access->canWorkDesk($this->user('Survey - Records', -9104), 'ad_report'));
    }
}
