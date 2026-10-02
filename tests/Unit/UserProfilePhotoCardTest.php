<?php

namespace Tests\Unit;

use App\Http\Controllers\UserController;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProfilePhotoService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UserProfilePhotoCardTest extends TestCase
{
    private function actor(bool $allowed = true): void
    {
        $actor = Mockery::mock(User::class)->makePartial();
        $actor->forceFill(['id' => 1, 'type' => 'super admin']);
        $actor->shouldReceive('isSuperAdmin')->andReturn($allowed);
        $actor->shouldReceive('can')->with('edit user')->andReturn($allowed);
        Auth::shouldReceive('user')->andReturn($actor);
    }

    private function target(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 2, 'first_name' => 'Musa']);
        $user->shouldReceive('getProfileUrlAttribute')->andReturn(null);
        return $user;
    }

    public function test_update_stores_only_the_photo_and_logs_the_change(): void
    {
        $this->actor();
        $user = $this->target();
        $user->shouldReceive('save')->once()->andReturn(true);
        $file = UploadedFile::fake()->image('musa.jpg');
        $photos = Mockery::mock(ProfilePhotoService::class);
        $photos->shouldReceive('store')->once()->with($file, $user)->andReturn('upload/profile/musa.jpg');
        $this->app->instance(ProfilePhotoService::class, $photos);
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('logAction')->once()->with('UPDATE', 'users', 2, Mockery::type('array'), Mockery::type('array'), Mockery::type('string'))->andReturn(new \App\Models\AuditLog());
        $this->app->instance(AuditService::class, $audit);
        $request = Request::create('/users/2/profile-photo', 'POST', ['action' => 'update'], [], ['profile' => $file]);
        $result = $this->app->make(UserController::class)->updateProfilePhoto($request, $user);
        $this->assertTrue($result->getData(true)['success']);
    }

    public function test_remove_uses_the_photo_service_and_logs_the_change(): void
    {
        $this->actor();
        $user = $this->target();
        $user->shouldReceive('save')->once()->andReturn(true);
        $photos = Mockery::mock(ProfilePhotoService::class);
        $photos->shouldReceive('remove')->once()->with($user)->andReturn(true);
        $this->app->instance(ProfilePhotoService::class, $photos);
        $audit = Mockery::mock(AuditService::class);
        $audit->shouldReceive('logAction')->once()->andReturn(new \App\Models\AuditLog());
        $this->app->instance(AuditService::class, $audit);
        $result = $this->app->make(UserController::class)->updateProfilePhoto(Request::create('/', 'POST', ['action' => 'remove']), $user);
        $this->assertTrue($result->getData(true)['success']);
    }

    public function test_upload_is_required_for_update(): void
    {
        $this->actor();
        $this->expectException(ValidationException::class);
        $this->app->make(UserController::class)->updateProfilePhoto(Request::create('/', 'POST', ['action' => 'update']), $this->target());
    }

    public function test_unprivileged_users_cannot_change_another_users_photo(): void
    {
        $this->actor(false);
        try {
            $this->app->make(UserController::class)->updateProfilePhoto(Request::create('/', 'POST', ['action' => 'remove']), $this->target());
            $this->fail('Unauthorized photo change was allowed.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }
}
