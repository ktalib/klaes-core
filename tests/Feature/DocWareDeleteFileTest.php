<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Doc-WARE "Delete File" (Change Request 22102026 item 28) is Super Admin only.
 *
 * READ-ONLY against sqlsrv: every request here is refused (403 / 422) before the
 * delete service runs, so nothing is moved or written.
 */
class DocWareDeleteFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** Signed in with the login code already passed, so RequireLoginOtp never starts a challenge. */
    private function signIn(User $user): void
    {
        $this->actingAs($user)->withSession([\App\Services\LoginOtpService::PASSED_KEY => (string) $user->id]);
    }

    private function superAdmin(): User
    {
        $admin = User::query()->whereRaw("LOWER(type) IN ('supper admin', 'super admin')")->first()
            ?: User::query()->whereRaw("assign_role LIKE '%Supper Admin%'")->first();

        if (!$admin || !$admin->isSuperAdmin()) {
            $this->markTestSkipped('No super admin account in this database.');
        }

        return $admin;
    }

    private function ordinaryUser(): User
    {
        $user = User::query()->whereRaw("LOWER(ISNULL(type, '')) NOT IN ('supper admin', 'super admin')")
            ->orderBy('id')->limit(50)->get()
            ->first(fn (User $u) => !$u->isSuperAdmin());

        if (!$user) {
            $this->markTestSkipped('No non-super-admin account in this database.');
        }

        return $user;
    }

    public function test_non_super_admin_cannot_preview_or_delete(): void
    {
        $this->signIn($this->ordinaryUser());

        $this->getJson('/filearchive/delete/1/preview')->assertStatus(403);
        $this->deleteJson('/filearchive/delete/1', [
            'reason' => 'should never run',
            'confirm_file_number' => 'X',
        ])->assertStatus(403);
    }

    public function test_super_admin_delete_requires_reason_and_confirmation(): void
    {
        $this->signIn($this->superAdmin());

        $this->deleteJson('/filearchive/delete/1', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason', 'confirm_file_number']);
    }

    public function test_super_admin_delete_refuses_a_mismatched_file_number(): void
    {
        $this->signIn($this->superAdmin());

        $file = \App\Models\FileIndexing::on('sqlsrv')->whereNotNull('file_number')->orderBy('id')->first();
        if (!$file) {
            $this->markTestSkipped('No indexed files in this database.');
        }

        $this->deleteJson('/filearchive/delete/' . $file->id, [
            'reason' => 'mismatch check',
            'confirm_file_number' => $file->file_number . '-NOT-THIS-ONE',
        ])->assertStatus(422)->assertJson(['success' => false]);
    }
}
