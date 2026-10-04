<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function protected_pages_redirect_to_login_when_guest()
    {
        $this->get('/transactions')->assertRedirect('/login');
        $this->get('/reportes')->assertRedirect('/login');
    }

    /** @test */
    public function login_screen_is_public()
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión');
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/transactions');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    /** @test */
    public function authenticated_user_visiting_login_is_redirected_to_an_existing_page()
    {
        $user = User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->actingAs($user)->get('/login')->assertRedirect('/transactions');
    }

    /** @test */
    public function logout_invalidates_session()
    {
        $user = User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    /** @test */
    public function login_attempts_are_rate_limited()
    {
        User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong-password'])
                ->assertRedirect();
        }

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'secret123'])
            ->assertStatus(429);

        $this->assertGuest();
    }
}
