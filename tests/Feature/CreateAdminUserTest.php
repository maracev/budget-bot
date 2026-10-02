<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_admin_user_with_hashed_password()
    {
        Artisan::call('app:create-admin', ['--password' => 'secret123']);

        $user = User::where('email', 'admin@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('admin', $user->name);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    /** @test */
    public function it_is_idempotent_and_does_not_overwrite_password()
    {
        Artisan::call('app:create-admin', ['--password' => 'secret123']);
        Artisan::call('app:create-admin', ['--password' => 'other-password']);

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
        $this->assertTrue(Hash::check('secret123', User::first()->password));
    }

    /** @test */
    public function it_updates_password_with_force_option()
    {
        Artisan::call('app:create-admin', ['--password' => 'secret123']);
        Artisan::call('app:create-admin', ['--password' => 'new-password', '--force' => true]);

        $this->assertTrue(Hash::check('new-password', User::first()->password));
    }

    /** @test */
    public function it_fails_when_no_password_is_provided()
    {
        putenv('ADMIN_PASSWORD');
        $_ENV['ADMIN_PASSWORD'] = null;
        $_SERVER['ADMIN_PASSWORD'] = null;

        $exitCode = Artisan::call('app:create-admin');

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, User::count());
    }
}